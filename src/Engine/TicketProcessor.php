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

use CommonITILActor;
use CommonITILObject;
use ITILFollowup;
use MailCollector;
use Ticket;
use GlpiPlugin\Ticketfilter\Audit\AuditLogger;
use GlpiPlugin\Ticketfilter\Model\FilterPattern;
use Throwable;

/**
 * Service managing ticket matching, authorization verification, followup creation,
 * and state transitions.
 */
class TicketProcessor
{
    public const DEFAULT_REOPEN_TEMPLATE = 'This ticket was reopened by automated rule "{pattern_name}" (match: {match_token}, sender: {sender_email}).';
    public const DEFAULT_SOLVED_TEMPLATE = 'This ticket was marked as solved by automated rule "{pattern_name}" (keyword: {solved_token}, sender: {sender_email}).';

    /**
     * Formats an automated intervention note with compliance and non-repudiation placeholders.
     *
     * @param string $template Custom template or empty string to use default.
     * @param string $defaultTemplate Fallback default template.
     * @param array<string, string> $context Placeholder replacement dictionary.
     * @return string
     */
    public static function formatInterventionMessage(string $template, string $defaultTemplate, array $context): string
    {
        $text = trim($template);
        if ($text === '') {
            $text = $defaultTemplate;
        }

        if (!isset($context['{datetime}'])) {
            $context['{datetime}'] = date('Y-m-d H:i:s');
        }
        if (!isset($context['{date}'])) {
            $context['{date}'] = date('Y-m-d');
        }
        if (!isset($context['{time}'])) {
            $context['{time}'] = date('H:i:s');
        }

        return strtr($text, $context);
    }
    /**
     * Searches for active, non-deleted tickets containing the matched token.
     *
     * @param string $token Matched identifier token.
     * @param int $entitiesId Entity context for multi-tenancy isolation.
     * @param bool $isRecursive Whether to include recursive child entities.
     * @return array List of matched ticket IDs.
     */
    public static function searchTicketPool(string $token, int $entitiesId = 0, bool $isRecursive = true): array
    {
        global $DB;

        $ticket = new Ticket();
        $table  = $ticket->getTable();
        $where  = [
            'name'       => ['LIKE', '%' . $token . '%'],
            'is_deleted' => 0,
        ];

        if ($entitiesId > 0) {
            if ($isRecursive && function_exists('getSonsOf')) {
                $entities = getSonsOf('glpi_entities', $entitiesId);
                $where['entities_id'] = $entities;
            } else {
                $where['entities_id'] = $entitiesId;
            }
        }

        $results = [];
        $iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => $table,
            'WHERE'  => $where,
            'ORDER'  => ['id DESC'],
            'LIMIT'  => 10,
        ]);

        foreach ($iterator as $row) {
            $results[] = (int)$row['id'];
        }

        return $results;
    }

    /**
     * Extracts sender identity information from incoming ticket input.
     *
     * @param Ticket $item Incoming ticket hook object.
     * @return array [int 'userId', string 'userEmail']
     */
    public static function extractSenderInfo(Ticket $item): array
    {
        $userId    = 0;
        $userEmail = '';

        if (isset($item->input['_users_id_requester']) && is_numeric($item->input['_users_id_requester'])) {
            $userId = (int)$item->input['_users_id_requester'];
        }

        if (isset($item->input['_actors']['requester']) && is_array($item->input['_actors']['requester'])) {
            foreach ($item->input['_actors']['requester'] as $actor) {
                if ($userId === 0 && isset($actor['items_id']) && is_numeric($actor['items_id'])) {
                    $userId = (int)$actor['items_id'];
                }
                if ($userEmail === '') {
                    if (!empty($actor['default_email'])) {
                        $userEmail = (string)$actor['default_email'];
                    } elseif (!empty($actor['alternative_email'])) {
                        $userEmail = (string)$actor['alternative_email'];
                    }
                }
            }
        }

        return [
            'userId'    => $userId,
            'userEmail' => strtolower(trim($userEmail)),
        ];
    }

    /**
     * Verifies if the incoming sender meets the configured authorization policy
     * for the target ticket.
     *
     * @param Ticket $incomingItem Incoming ticket object.
     * @param Ticket $targetTicket Existing matched ticket.
     * @param string $policy Configured verification policy.
     * @return array [bool 'authorized', string 'reason', string 'senderEmail', int 'senderId']
     */
    public static function verifySender(Ticket $incomingItem, Ticket $targetTicket, string $policy): array
    {
        $sender = self::extractSenderInfo($incomingItem);
        $senderEmail = $sender['userEmail'];
        $senderId    = $sender['userId'];

        if ($policy === FilterPattern::POLICY_OPEN) {
            return [
                'authorized'  => true,
                'reason'      => 'Policy is open',
                'senderEmail' => $senderEmail,
                'senderId'    => $senderId,
            ];
        }

        $userLinkClass = $targetTicket->userlinkclass ?? 'Ticket_User';
        if (!class_exists($userLinkClass)) {
            return [
                'authorized'  => false,
                'reason'      => 'User link class unavailable',
                'senderEmail' => $senderEmail,
                'senderId'    => $senderId,
            ];
        }

        $usrObj = new $userLinkClass();
        $targetActors = $usrObj->getActors((int)$targetTicket->fields['id']);

        $allowedUserIds   = [];
        $allowedEmails    = [];
        $allowedDomains   = [];

        foreach ($targetActors as $actorGroup) {
            if (!is_array($actorGroup)) {
                continue;
            }
            foreach ($actorGroup as $actor) {
                $type = (int)($actor['type'] ?? 0);

                $evaluate = false;
                if ($policy === FilterPattern::POLICY_STRICT_REQUESTER) {
                    $evaluate = ($type === CommonITILActor::REQUESTER);
                } elseif ($policy === FilterPattern::POLICY_ANY_ACTOR) {
                    $evaluate = true;
                } elseif ($policy === FilterPattern::POLICY_SAME_DOMAIN) {
                    $evaluate = ($type === CommonITILActor::REQUESTER);
                }

                if ($evaluate) {
                    if (!empty($actor['users_id'])) {
                        $allowedUserIds[] = (int)$actor['users_id'];
                    }
                    if (!empty($actor['alternative_email'])) {
                        $email = strtolower(trim((string)$actor['alternative_email']));
                        $allowedEmails[] = $email;
                        $domain = substr(strrchr($email, '@') ?: '', 1);
                        if ($domain !== '') {
                            $allowedDomains[] = $domain;
                        }
                    }
                }
            }
        }

        if ($senderId > 0 && in_array($senderId, $allowedUserIds, true)) {
            return [
                'authorized'  => true,
                'reason'      => 'Matched existing actor user ID',
                'senderEmail' => $senderEmail,
                'senderId'    => $senderId,
            ];
        }

        if ($senderEmail !== '' && in_array($senderEmail, $allowedEmails, true)) {
            return [
                'authorized'  => true,
                'reason'      => 'Matched existing actor email address',
                'senderEmail' => $senderEmail,
                'senderId'    => $senderId,
            ];
        }

        if ($policy === FilterPattern::POLICY_SAME_DOMAIN && $senderEmail !== '') {
            $senderDomain = substr(strrchr($senderEmail, '@') ?: '', 1);
            if ($senderDomain !== '' && in_array($senderDomain, $allowedDomains, true)) {
                return [
                    'authorized'  => true,
                    'reason'      => 'Matched organization domain',
                    'senderEmail' => $senderEmail,
                    'senderId'    => $senderId,
                ];
            }
        }

        return [
            'authorized'  => false,
            'reason'      => 'Sender is not present on target ticket actors',
            'senderEmail' => $senderEmail,
            'senderId'    => $senderId,
        ];
    }

    /**
     * Executes ticket merging, reopening, and solving safely.
     *
     * @param Ticket $incomingItem Incoming ticket hook object.
     * @param Ticket $targetTicket Loaded target ticket from database.
     * @param array $pattern FilterPattern configuration row.
     * @param string $matchedToken Extracted token that triggered match.
     * @param bool $isPrivate Whether followup should be marked private.
     * @return array [bool 'success', int 'followup_id', bool 'reopened', bool 'solved', string 'status']
     */
    public static function processTicket(
        Ticket $incomingItem,
        Ticket $targetTicket,
        array $pattern,
        string $matchedToken,
        bool $isPrivate = false
    ): array {
        global $DB;

        $targetTicketId = (int)$targetTicket->fields['id'];
        $patternName    = (string)($pattern[FilterPattern::FIELD_NAME] ?? 'Unknown');
        $patternId      = (int)($pattern[FilterPattern::FIELD_ID] ?? 0);
        $reopened       = false;
        $solved         = false;
        $suppressNotif  = !empty($pattern[FilterPattern::FIELD_SUPPRESNOTIF]);
        $sender         = self::extractSenderInfo($incomingItem);
        $subject        = (string)($incomingItem->input['name'] ?? '');

        $currentStatus = (int)$targetTicket->fields['status'];

        if ($currentStatus === CommonITILObject::CLOSED) {
            $allowReopen = !empty($pattern[FilterPattern::FIELD_REOPENCLOSED]);
            if (!$allowReopen) {
                return [
                    'success'     => false,
                    'followup_id' => 0,
                    'reopened'    => false,
                    'solved'      => false,
                    'status'      => 'closed_reopen_disabled'
                ];
            }

            $maxAgeDays = (int)($pattern[FilterPattern::FIELD_MAX_REOPEN_AGE_DAYS] ?? 30);
            if ($maxAgeDays > 0 && !empty($targetTicket->fields['closedate'])) {
                $closedTimestamp = strtotime((string)$targetTicket->fields['closedate']);
                $ageDays = (time() - $closedTimestamp) / (60 * 60 * 24);
                if ($ageDays > $maxAgeDays) {
                    return [
                        'success'     => false,
                        'followup_id' => 0,
                        'reopened'    => false,
                        'solved'      => false,
                        'status'      => 'closed_max_age_exceeded'
                    ];
                }
            }

            $DB->update(
                $targetTicket->getTable(),
                ['status' => CommonITILObject::INCOMING],
                ['id' => $targetTicketId]
            );

            $reopenContent = self::formatInterventionMessage(
                (string)($pattern[FilterPattern::FIELD_REOPEN_MESSAGE_TEMPLATE] ?? ''),
                self::DEFAULT_REOPEN_TEMPLATE,
                [
                    '{pattern_name}' => $patternName,
                    '{pattern_id}'   => (string)$patternId,
                    '{match_token}'  => $matchedToken,
                    '{sender_email}' => $sender['userEmail'],
                    '{subject}'      => $subject,
                    '{ticket_id}'    => (string)$targetTicketId,
                ]
            );

            $reopenFollowup = new ITILFollowup();
            $reopenFollowup->add([
                'items_id'      => $targetTicketId,
                'itemtype'      => Ticket::class,
                'content'       => $reopenContent,
                'users_id'      => 0,
                'add_reopen'    => 1,
                'is_private'    => 1,
                '_disablenotif' => true,
            ]);
            $reopened = true;
        }

        $itilFollowup = new ITILFollowup();

        $followupInput = [
            'items_id'      => $targetTicketId,
            'itemtype'      => Ticket::class,
            'content'       => $incomingItem->input['content'] ?? ($incomingItem->input['name'] ?? ''),
            'users_id'      => $sender['userId'],
            'is_private'    => ($isPrivate) ? 1 : (int)($pattern[FilterPattern::FIELD_IS_PRIVATE_FOLLOWUP] ?? 0),
            '_disablenotif' => $suppressNotif,
        ];

        if (isset($incomingItem->input['_filename'])) {
            $followupInput['_filename'] = $incomingItem->input['_filename'];
        }
        if (isset($incomingItem->input['_tag'])) {
            $followupInput['_tag'] = $incomingItem->input['_tag'];
        }

        $followupId = $itilFollowup->add($followupInput);
        if ($followupId === false) {
            return [
                'success'     => false,
                'followup_id' => 0,
                'reopened'    => $reopened,
                'solved'      => false,
                'status'      => 'followup_failed'
            ];
        }

        $solvedPattern = (string)($pattern[FilterPattern::FIELD_SOLVEDMATCHSTR] ?? '');
        if ($solvedPattern !== '') {
            $solvedMatch = PatternMatcher::match($solvedPattern, $subject, PatternMatcher::GROUP_SOLVED);

            if ($solvedMatch['matched'] && $solvedMatch['token'] !== null) {
                $maxLen = (int)($pattern[FilterPattern::FIELD_SOLVEDMATCHSTRLEN] ?? 25);
                if (mb_strlen($solvedMatch['token']) <= $maxLen) {
                    $DB->update(
                        $targetTicket->getTable(),
                        ['status' => CommonITILObject::SOLVED],
                        ['id' => $targetTicketId]
                    );

                    $solvedContent = self::formatInterventionMessage(
                        (string)($pattern[FilterPattern::FIELD_SOLVED_MESSAGE_TEMPLATE] ?? ''),
                        self::DEFAULT_SOLVED_TEMPLATE,
                        [
                            '{pattern_name}' => $patternName,
                            '{pattern_id}'   => (string)$patternId,
                            '{match_token}'  => $matchedToken,
                            '{solved_token}' => (string)$solvedMatch['token'],
                            '{sender_email}' => $sender['userEmail'],
                            '{subject}'      => $subject,
                            '{ticket_id}'    => (string)$targetTicketId,
                        ]
                    );

                    $solvedFollowup = new ITILFollowup();
                    $solvedFollowup->add([
                        'items_id'      => $targetTicketId,
                        'itemtype'      => Ticket::class,
                        'content'       => $solvedContent,
                        'users_id'      => 0,
                        'is_private'    => 1,
                        '_disablenotif' => true,
                    ]);
                    $solved = true;
                }
            }
        }

        return [
            'success'     => true,
            'followup_id' => (int)$followupId,
            'reopened'    => $reopened,
            'solved'      => $solved,
            'status'      => 'success'
        ];
    }

    /**
     * Safely deletes processed email from mailgate mailbox after successful merge.
     *
     * @param Ticket $item Incoming ticket hook object.
     * @return bool True if deleted or not mailgate, false on failure.
     */
    public static function deleteMailgateEmail(Ticket $item): bool
    {
        if (!isset($item->input['_mailgate']) || !isset($item->input['_uid'])) {
            return true;
        }

        try {
            $collectorId = (int)$item->input['_mailgate'];
            $uid         = (string)$item->input['_uid'];

            $mailCollector = new MailCollector();
            if ($mailCollector->getFromDB($collectorId)) {
                $mailCollector->connect();
                $targetFolder = defined(MailCollector::class . '::ACCEPTED_FOLDER')
                    ? MailCollector::ACCEPTED_FOLDER
                    : '/accepted';
                return (bool)$mailCollector->deleteMails($uid, $targetFolder);
            }
        } catch (Throwable $e) {
            AuditLogger::logError(
                sprintf('Failed to delete email from mailgate: %s', $e->getMessage()),
                $item->input['name'] ?? ''
            );
        }

        return false;
    }
}
