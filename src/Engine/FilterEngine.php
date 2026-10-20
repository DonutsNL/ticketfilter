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

use MailCollector;
use NotImportedEmail;
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
     * Cache of messages merged during the current execution to coordinate
     * with GLPI MailCollector and prevent false positive 'error' reporting.
     *
     * @var array<int, array{collector_id: int, uid: string, message_id: string, subject: string}>
     */
    private static array $mergedMailgateMessages = [];

    /**
     * Whether the shutdown cleanup handler has been registered.
     */
    private static bool $cleanupRegistered = false;
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
                            self::recordMailgateInterception($item);
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

                self::recordMailgateInterception($item);
                $item->input = false;
                $item->fields = false;
                return true;
            }
        }

        return false;
    }

    /**
     * Hook callback invoked on pre_item_add for NotImportedEmail.
     *
     * Prevents GLPI from creating a false 'Operation failed' record in glpi_notimportedemails
     * when a ticket is intentionally intercepted and merged by TicketFilter.
     *
     * @param object $item
     * @return void
     */
    public static function preNotImportedEmailAdd(object $item): void
    {
        try {
            if (!isset($item->input) || !is_array($item->input)) {
                return;
            }

            $collectorId = (int)($item->input['mailcollectors_id'] ?? 0);
            $messageId   = (string)($item->input['messageid'] ?? '');
            $subject     = (string)($item->input['subject'] ?? '');

            foreach (self::$mergedMailgateMessages as $merged) {
                if ($collectorId > 0 && $merged['collector_id'] === $collectorId) {
                    if (
                        (!empty($messageId) && $merged['message_id'] === $messageId) ||
                        (!empty($subject) && $merged['subject'] === $subject)
                    ) {
                        $item->input  = false;
                        $item->fields = false;
                        return;
                    }
                }
            }
        } catch (Throwable $e) {
            // Fail silently to avoid breaking GLPI core error logging
        }
    }

    /**
     * Records mailgate message metadata and directs active collector to accepted folder.
     *
     * @param Ticket $item
     * @return void
     */
    public static function recordMailgateInterception(Ticket $item): void
    {
        if (empty($item->input['_mailgate'])) {
            return;
        }

        $collectorId = (int)$item->input['_mailgate'];
        $uid         = (string)($item->input['_uid'] ?? '');
        $messageId   = (string)($item->input['_head']['message_id'] ?? ($item->input['messageid'] ?? ''));
        $subject     = (string)($item->input['name'] ?? '');

        self::$mergedMailgateMessages[] = [
            'collector_id' => $collectorId,
            'uid'          => $uid,
            'message_id'   => $messageId,
            'subject'      => $subject,
        ];

        // Direct active MailCollector instance to route to ACCEPTED folder
        $activeCollector = self::getActiveMailCollector();
        if ($activeCollector !== null) {
            $acceptedFolderKey = defined(\MailCollector::class . '::ACCEPTED_FOLDER')
                ? \MailCollector::ACCEPTED_FOLDER
                : 'accepted';
            $refusedFolderKey  = defined(\MailCollector::class . '::REFUSED_FOLDER')
                ? \MailCollector::REFUSED_FOLDER
                : 'refused';

            if (!empty($activeCollector->fields[$acceptedFolderKey])) {
                $activeCollector->fields[$refusedFolderKey] = $activeCollector->fields[$acceptedFolderKey];
            }
        }

        self::registerMailgateCleanup();
    }

    /**
     * Returns recorded merged mailgate messages (for diagnostics and testing).
     *
     * @return array<int, array{collector_id: int, uid: string, message_id: string, subject: string}>
     */
    public static function getMergedMailgateMessages(): array
    {
        return self::$mergedMailgateMessages;
    }

    /**
     * Resets the in-memory mailgate state (for testing).
     *
     * @return void
     */
    public static function resetMailgateState(): void
    {
        self::$mergedMailgateMessages = [];
        self::$cleanupRegistered      = false;
    }

    /**
     * Inspects the call stack to locate the active MailCollector instance (if any).
     *
     * @return \MailCollector|null
     */
    public static function getActiveMailCollector(): ?\MailCollector
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 30);
        foreach ($trace as $frame) {
            if (isset($frame['object']) && $frame['object'] instanceof \MailCollector) {
                return $frame['object'];
            }
        }
        return null;
    }

    /**
     * Registers a shutdown handler to fix GLPI Mailgate error counting and flash messages.
     *
     * @return void
     */
    public static function registerMailgateCleanup(): void
    {
        if (self::$cleanupRegistered) {
            return;
        }
        self::$cleanupRegistered = true;

        register_shutdown_function([self::class, 'handleMailgateShutdown']);
    }

    /**
     * Executed at PHP shutdown to clean up mailgate error flags and flash messages.
     *
     * @return void
     */
    public static function handleMailgateShutdown(): void
    {
        global $DB;

        $mergedCount = count(self::$mergedMailgateMessages);
        if ($mergedCount === 0) {
            return;
        }

        // 1. Remove any stray record from glpi_notimportedemails
        if ($DB !== null && method_exists($DB, 'delete')) {
            try {
                foreach (self::$mergedMailgateMessages as $merged) {
                    if ($merged['collector_id'] > 0 && !empty($merged['message_id'])) {
                        $DB->delete('glpi_notimportedemails', [
                            'mailcollectors_id' => $merged['collector_id'],
                            'messageid'         => $merged['message_id'],
                        ]);
                    }
                }
            } catch (Throwable $e) {
                // Ignore DB cleanup errors on shutdown
            }
        }

        // 2. Adjust GLPI session message if present
        if (isset($_SESSION['MESSAGE_AFTER_REDIRECT'])) {
            $isDutch = (isset($_SESSION['glpilanguage']) && str_starts_with((string)$_SESSION['glpilanguage'], 'nl'));

            $errorKey = defined('ERROR') ? ERROR : 3;
            $infoKey  = defined('INFO')  ? INFO  : 0;

            if (isset($_SESSION['MESSAGE_AFTER_REDIRECT'][$errorKey]) && is_array($_SESSION['MESSAGE_AFTER_REDIRECT'][$errorKey])) {
                foreach ($_SESSION['MESSAGE_AFTER_REDIRECT'][$errorKey] as $k => $msg) {
                    if (is_string($msg) && preg_match('/errors=(\d+)/i', $msg, $matches)) {
                        $currentErrors = (int)$matches[1];
                        $newErrors     = max(0, $currentErrors - $mergedCount);

                        $updatedMsg = preg_replace('/errors=\d+/i', 'errors=' . $newErrors, $msg);
                        unset($_SESSION['MESSAGE_AFTER_REDIRECT'][$errorKey][$k]);

                        if ($newErrors === 0) {
                            $_SESSION['MESSAGE_AFTER_REDIRECT'][$infoKey][] = $updatedMsg;
                        } else {
                            $_SESSION['MESSAGE_AFTER_REDIRECT'][$errorKey][] = $updatedMsg;
                        }
                    }
                }

                if (empty($_SESSION['MESSAGE_AFTER_REDIRECT'][$errorKey])) {
                    unset($_SESSION['MESSAGE_AFTER_REDIRECT'][$errorKey]);
                }
            }

            // Add clear confirmation note to the INFO banner
            $infoNotice = $isDutch
                ? sprintf('TicketFilter: %d e-mail(s) succesvol samengevoegd met bestaand ticket.', $mergedCount)
                : sprintf('TicketFilter: %d email(s) successfully merged into existing ticket(s).', $mergedCount);

            if (!isset($_SESSION['MESSAGE_AFTER_REDIRECT'][$infoKey])) {
                $_SESSION['MESSAGE_AFTER_REDIRECT'][$infoKey] = [];
            }
            $_SESSION['MESSAGE_AFTER_REDIRECT'][$infoKey][] = $infoNotice;
        }
    }
}
