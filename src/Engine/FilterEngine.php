<?php

/**
 *  ------------------------------------------------------------------------
 *  TicketFilter Plugin for GLPI
 *  Copyright (C) 2023-2026 by Chris Gralike
 *  ------------------------------------------------------------------------
 *
 *  LICENSE
 *
 *  This file is part of TicketFilter plugin for GLPI.
 *
 *  TicketFilter is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  TicketFilter is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with TicketFilter. If not, see <http://www.gnu.org/licenses/>.
 *
 *  ------------------------------------------------------------------------
 *
 *  @package    TicketFilter
 *  @version    2.0.0
 *  @author     Chris Gralike
 *  @copyright  Copyright (c) 2023-2026 by Chris Gralike
 *  @license    GPLv3+
 *  @link       https://github.com/DonutsNL/ticketfilter
 *  ------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\Ticketfilter\Engine;

use Ticket;
use Toolbox;
use GlpiPlugin\Ticketfilter\Audit\AuditLogger;
use GlpiPlugin\Ticketfilter\Model\FilterPattern;
use Throwable;

/**
 * Top-level hook dispatcher with complete mailgate fault isolation.
 */
class FilterEngine
{
    /**
     * Hook callback invoked on pre_item_add for tickets.
     *
     * Enclosed in an unconditional top-level try/catch block to guarantee that no
     * internal error, SQL failure, or regex exception ever crashes the mailgate or
     * disrupts normal ticket creation.
     *
     * @param Ticket $item Hook ticket object passed by reference.
     * @return void
     */
    public static function preItemAdd(Ticket $item): void
    {
        try {
            if (
                !is_object($item) ||
                !is_array($item->input) ||
                empty($item->input['name'])
            ) {
                return;
            }

            self::evaluateTicket($item);
        } catch (Throwable $e) {
            $subject = (isset($item->input['name'])) ? (string)$item->input['name'] : 'Unknown';
            AuditLogger::logError(
                sprintf('Mailgate isolation intercepted error: %s', $e->getMessage()),
                $subject,
                '',
                ['trace' => $e->getTraceAsString()]
            );

            if (class_exists(Toolbox::class) && method_exists(Toolbox::class, 'logInFile')) {
                $logFile = defined('PLUGIN_TICKETFILTER_NAME') ? PLUGIN_TICKETFILTER_NAME : 'ticketfilter';
                Toolbox::logInFile(
                    $logFile,
                    sprintf("Mailgate exception bypassed for ticket '%s': %s\n", $subject, $e->getMessage())
                );
            }
        }
    }

    /**
     * Evaluates configured filter patterns against the incoming ticket.
     *
     * @param Ticket $item Incoming ticket object passed by reference.
     * @return bool True if a pattern was matched and processed, false otherwise.
     */
    private static function evaluateTicket(Ticket $item): bool
    {
        $patterns = FilterPattern::getFilterPatterns();
        if (empty($patterns)) {
            return false;
        }

        $subject     = (string)$item->input['name'];
        $body        = (string)($item->input['content'] ?? '');
        $entitiesId  = (int)($item->input['entities_id'] ?? 0);
        $senderInfo  = TicketProcessor::extractSenderInfo($item);
        $senderEmail = $senderInfo['userEmail'];

        foreach ($patterns as $pattern) {
            $rawPattern = (string)($pattern[FilterPattern::FIELD_TICKETMATCHSTR] ?? '');
            if ($rawPattern === '') {
                continue;
            }

            $searchBody = !empty($pattern[FilterPattern::FIELD_SEARCHBODY]);
            $matchResult = PatternMatcher::match($rawPattern, $subject, PatternMatcher::GROUP_MATCH);

            if (!$matchResult['matched'] && $searchBody && $body !== '') {
                $matchResult = PatternMatcher::match($rawPattern, $body, PatternMatcher::GROUP_MATCH);
            }

            if (!$matchResult['matched'] || empty($matchResult['token'])) {
                continue;
            }

            $token = $matchResult['token'];
            $maxLen = (int)($pattern[FilterPattern::FIELD_TICKETMATCHSTRLEN] ?? 25);
            if (mb_strlen($token) > $maxLen) {
                AuditLogger::logError(
                    sprintf('Matched token "%s" exceeds maximum allowed length (%d)', $token, $maxLen),
                    $subject,
                    $senderEmail
                );
                continue;
            }

            $matchedTicketIds = TicketProcessor::searchTicketPool($token, $entitiesId);
            if (empty($matchedTicketIds)) {
                continue;
            }

            $policy          = (string)($pattern[FilterPattern::FIELD_SENDER_POLICY] ?? FilterPattern::POLICY_STRICT_REQUESTER);
            $totalCandidates = count($matchedTicketIds);
            $targetTicket    = null;
            $targetTicketId  = 0;

            // Sequential candidate validation: lowest ID (original ticket) evaluated first
            foreach ($matchedTicketIds as $candidateId) {
                $candidate = new Ticket();
                if (!$candidate->getFromDB($candidateId)) {
                    continue;
                }

                $verification = TicketProcessor::verifySender($item, $candidate, $policy);
                if ($verification['authorized']) {
                    $targetTicket   = $candidate;
                    $targetTicketId = $candidateId;
                    break;
                }
            }

            if ($targetTicket === null) {
                // Sender was not authorized on any candidate ticket for this token
                $unmatchedAction  = (string)($pattern[FilterPattern::FIELD_UNMATCHED_SENDER_ACTION] ?? FilterPattern::ACTION_NEW_TICKET);
                $fallbackTicketId = $matchedTicketIds[0];

                if ($unmatchedAction === FilterPattern::ACTION_REJECT) {
                    AuditLogger::logSkippedPolicy(
                        (int)$pattern[FilterPattern::FIELD_ID],
                        (string)$pattern[FilterPattern::FIELD_NAME],
                        $subject,
                        $senderEmail,
                        $token,
                        $fallbackTicketId,
                        $policy,
                        $entitiesId
                    );
                    TicketProcessor::deleteMailgateEmail($item);
                    $item->input = false;
                    $item->fields = false;
                    return true;
                }

                if ($unmatchedAction === FilterPattern::ACTION_PRIVATE_FOLLOWUP) {
                    $fallbackTicket = new Ticket();
                    if ($fallbackTicket->getFromDB($fallbackTicketId)) {
                        $processResult = TicketProcessor::processTicket($item, $fallbackTicket, $pattern, $token, true);
                        if ($processResult['success']) {
                            AuditLogger::logMerged(
                                (int)$pattern[FilterPattern::FIELD_ID],
                                (string)$pattern[FilterPattern::FIELD_NAME],
                                $subject,
                                $senderEmail,
                                $token,
                                $fallbackTicketId,
                                $entitiesId,
                                ['private_followup' => true, 'policy_bypass' => 'unmatched_sender_action']
                            );
                            TicketProcessor::deleteMailgateEmail($item);
                            $item->input = false;
                            $item->fields = false;
                            return true;
                        }
                    }
                }

                AuditLogger::logSkippedPolicy(
                    (int)$pattern[FilterPattern::FIELD_ID],
                    (string)$pattern[FilterPattern::FIELD_NAME],
                    $subject,
                    $senderEmail,
                    $token,
                    $fallbackTicketId,
                    $policy,
                    $entitiesId
                );
                continue;
            }

            // An authorized target ticket was selected (original lowest ID prioritized)
            $processResult = TicketProcessor::processTicket($item, $targetTicket, $pattern, $token, false);
            if ($processResult['success']) {
                $mergeContext = [];
                if ($totalCandidates > 1) {
                    $mergeContext['candidate_resolution'] = sprintf('Candidate ticket #%d matched (1 of %d with token "%s")', $targetTicketId, $totalCandidates, $token);
                }

                AuditLogger::logMerged(
                    (int)$pattern[FilterPattern::FIELD_ID],
                    (string)$pattern[FilterPattern::FIELD_NAME],
                    $subject,
                    $senderEmail,
                    $token,
                    $targetTicketId,
                    $entitiesId,
                    $mergeContext
                );

                if ($processResult['reopened']) {
                    AuditLogger::logReopened(
                        (int)$pattern[FilterPattern::FIELD_ID],
                        (string)$pattern[FilterPattern::FIELD_NAME],
                        $subject,
                        $senderEmail,
                        $token,
                        $targetTicketId,
                        $entitiesId
                    );
                }

                if ($processResult['solved']) {
                    AuditLogger::logSolved(
                        (int)$pattern[FilterPattern::FIELD_ID],
                        (string)$pattern[FilterPattern::FIELD_NAME],
                        $subject,
                        $senderEmail,
                        $token,
                        $targetTicketId,
                        $entitiesId
                    );
                }

                TicketProcessor::deleteMailgateEmail($item);
                $item->input = false;
                $item->fields = false;
                return true;
            }
        }

        return false;
    }
}
