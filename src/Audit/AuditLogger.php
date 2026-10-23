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

namespace GlpiPlugin\Ticketfilter\Audit;

use Toolbox;
use Throwable;

/**
 * Service responsible for recording audit trail events and diagnostics.
 */
class AuditLogger
{
    /**
     * Records an audit event safely into the database and plugin log file.
     *
     * @param string $actionTaken Action identifier constant from AuditLog.
     * @param string $status Outcome status (success, warning, error, info).
     * @param string $message Summary message describing the event.
     * @param int $patternId ID of the matched pattern, or 0.
     * @param string $patternName Name of the matched pattern.
     * @param string $incomingSubject Subject line of the incoming email/ticket.
     * @param string $incomingSender Sender email address or user identifier.
     * @param string $matchedToken Extracted token that triggered the match.
     * @param int $targetTicketId ID of the matched existing ticket, or 0.
     * @param array $details Additional contextual data encoded as JSON.
     * @param int $entitiesId Entity ID context.
     * @return bool True if logged successfully, false otherwise.
     */
    public static function logEvent(
        string $actionTaken,
        string $status,
        string $message,
        int $patternId = 0,
        string $patternName = '',
        string $incomingSubject = '',
        string $incomingSender = '',
        string $matchedToken = '',
        int $targetTicketId = 0,
        array $details = [],
        int $entitiesId = 0
    ): bool {
        global $DB;

        try {
            if ($DB && $DB->tableExists(AuditLog::TABLE)) {
                $encodedDetails = (!empty($details)) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;

                $DB->insert(
                    AuditLog::TABLE,
                    [
                        AuditLog::FIELD_PATTERN_ID       => $patternId,
                        AuditLog::FIELD_PATTERN_NAME     => $patternName,
                        AuditLog::FIELD_INCOMING_SUBJECT  => mb_substr($incomingSubject, 0, 255),
                        AuditLog::FIELD_INCOMING_SENDER   => mb_substr($incomingSender, 0, 255),
                        AuditLog::FIELD_MATCHED_TOKEN     => mb_substr($matchedToken, 0, 255),
                        AuditLog::FIELD_TARGET_TICKETS_ID => $targetTicketId,
                        AuditLog::FIELD_ACTION_TAKEN      => $actionTaken,
                        AuditLog::FIELD_STATUS            => $status,
                        AuditLog::FIELD_MESSAGE           => $message,
                        AuditLog::FIELD_DETAILS           => $encodedDetails,
                        AuditLog::FIELD_ENTITIES_ID       => $entitiesId,
                    ]
                );
            }

            if (class_exists(Toolbox::class) && method_exists(Toolbox::class, 'logInFile')) {
                $logLine = sprintf(
                    "[%s] [%s] Ticket #%d | Pattern: '%s' | Token: '%s' | Sender: '%s' | %s\n",
                    $status,
                    $actionTaken,
                    $targetTicketId,
                    $patternName,
                    $matchedToken,
                    $incomingSender,
                    $message
                );
                $logFile = defined('PLUGIN_TICKETFILTER_NAME') ? PLUGIN_TICKETFILTER_NAME : 'ticketfilter';
                Toolbox::logInFile($logFile, $logLine);
            }

            return true;
        } catch (Throwable $e) {
            if (class_exists(Toolbox::class) && method_exists(Toolbox::class, 'logInFile')) {
                $logFile = defined('PLUGIN_TICKETFILTER_NAME') ? PLUGIN_TICKETFILTER_NAME : 'ticketfilter';
                Toolbox::logInFile(
                    $logFile,
                    sprintf("Failed to write audit log: %s\n", $e->getMessage())
                );
            }
            return false;
        }
    }

    /**
     * Logs a successful ticket followup merge.
     *
     * @param int $patternId Pattern identifier.
     * @param string $patternName Pattern name.
     * @param string $subject Incoming subject.
     * @param string $sender Incoming sender.
     * @param string $token Matched identifier token.
     * @param int $ticketId Target ticket ID.
     * @param int $entitiesId Entity ID.
     * @param array $extra Contextual payload.
     * @return bool
     */
    public static function logMerged(
        int $patternId,
        string $patternName,
        string $subject,
        string $sender,
        string $token,
        int $ticketId,
        int $entitiesId = 0,
        array $extra = []
    ): bool {
        return self::logEvent(
            AuditLog::ACTION_MERGED,
            AuditLog::STATUS_SUCCESS,
            sprintf(__('Matched pattern "%s" and added incoming ticket as followup.', 'ticketfilter'), $patternName),
            $patternId,
            $patternName,
            $subject,
            $sender,
            $token,
            $ticketId,
            $extra,
            $entitiesId
        );
    }

    /**
     * Logs a ticket reopening action.
     *
     * @param int $patternId Pattern identifier.
     * @param string $patternName Pattern name.
     * @param string $subject Incoming subject.
     * @param string $sender Incoming sender.
     * @param string $token Matched token.
     * @param int $ticketId Target ticket ID.
     * @param int $entitiesId Entity ID.
     * @return bool
     */
    public static function logReopened(
        int $patternId,
        string $patternName,
        string $subject,
        string $sender,
        string $token,
        int $ticketId,
        int $entitiesId = 0
    ): bool {
        return self::logEvent(
            AuditLog::ACTION_REOPENED,
            AuditLog::STATUS_INFO,
            sprintf(__('Reopened closed ticket #%d per pattern "%s" configuration.', 'ticketfilter'), $ticketId, $patternName),
            $patternId,
            $patternName,
            $subject,
            $sender,
            $token,
            $ticketId,
            [],
            $entitiesId
        );
    }

    /**
     * Logs a ticket solved action.
     *
     * @param int $patternId Pattern identifier.
     * @param string $patternName Pattern name.
     * @param string $subject Incoming subject.
     * @param string $sender Incoming sender.
     * @param string $token Matched token.
     * @param int $ticketId Target ticket ID.
     * @param int $entitiesId Entity ID.
     * @return bool
     */
    public static function logSolved(
        int $patternId,
        string $patternName,
        string $subject,
        string $sender,
        string $token,
        int $ticketId,
        int $entitiesId = 0
    ): bool {
        return self::logEvent(
            AuditLog::ACTION_SOLVED,
            AuditLog::STATUS_SUCCESS,
            sprintf(__('Ticket #%d marked as solved by pattern "%s".', 'ticketfilter'), $ticketId, $patternName),
            $patternId,
            $patternName,
            $subject,
            $sender,
            $token,
            $ticketId,
            [],
            $entitiesId
        );
    }

    /**
     * Logs an action skipped due to sender verification policy failure.
     *
     * @param int $patternId Pattern identifier.
     * @param string $patternName Pattern name.
     * @param string $subject Incoming subject.
     * @param string $sender Incoming sender.
     * @param string $token Matched token.
     * @param int $ticketId Target ticket ID.
     * @param string $policy Applied policy name.
     * @param int $entitiesId Entity ID.
     * @return bool
     */
    public static function logSkippedPolicy(
        int $patternId,
        string $patternName,
        string $subject,
        string $sender,
        string $token,
        int $ticketId,
        string $policy,
        int $entitiesId = 0
    ): bool {
        return self::logEvent(
            AuditLog::ACTION_SKIPPED_POLICY,
            AuditLog::STATUS_WARNING,
            sprintf(__('Sender "%s" is not authorized on ticket #%d under policy "%s". Merge skipped.', 'ticketfilter'), $sender, $ticketId, $policy),
            $patternId,
            $patternName,
            $subject,
            $sender,
            $token,
            $ticketId,
            ['policy' => $policy],
            $entitiesId
        );
    }

    /**
     * Logs an action skipped due to ambiguous collision (> 1 tickets matched).
     *
     * @param int $patternId Pattern identifier.
     * @param string $patternName Pattern name.
     * @param string $subject Incoming subject.
     * @param string $sender Incoming sender.
     * @param string $token Matched token.
     * @param array $matchedTicketIds List of colliding ticket IDs.
     * @param int $entitiesId Entity ID.
     * @return bool
     */
    public static function logCollision(
        int $patternId,
        string $patternName,
        string $subject,
        string $sender,
        string $token,
        array $matchedTicketIds,
        int $entitiesId = 0
    ): bool {
        return self::logEvent(
            AuditLog::ACTION_SKIPPED_COLLISION,
            AuditLog::STATUS_WARNING,
            sprintf(__('Ambiguous match: token "%s" matched %d tickets (%s). Merge aborted to prevent data corruption.', 'ticketfilter'), $token, count($matchedTicketIds), implode(', ', $matchedTicketIds)),
            $patternId,
            $patternName,
            $subject,
            $sender,
            $token,
            0,
            ['colliding_tickets' => $matchedTicketIds],
            $entitiesId
        );
    }

    /**
     * Logs an action skipped due to entity mismatch.
     *
     * @param int $patternId Pattern identifier.
     * @param string $patternName Pattern name.
     * @param string $subject Incoming subject.
     * @param string $sender Incoming sender.
     * @param int $ticketEntitiesId Ticket entity ID.
     * @param int $patternEntitiesId Pattern entity ID.
     * @return bool
     */
    public static function logSkippedEntity(
        int $patternId,
        string $patternName,
        string $subject,
        string $sender,
        int $ticketEntitiesId,
        int $patternEntitiesId
    ): bool {
        return self::logEvent(
            AuditLog::ACTION_SKIPPED_ENTITY,
            AuditLog::STATUS_INFO,
            sprintf(__('Pattern "%s" (entity #%d) does not apply to ticket entity #%d.', 'ticketfilter'), $patternName, $patternEntitiesId, $ticketEntitiesId),
            $patternId,
            $patternName,
            $subject,
            $sender,
            '',
            0,
            ['ticket_entity' => $ticketEntitiesId, 'pattern_entity' => $patternEntitiesId],
            $ticketEntitiesId
        );
    }

    /**
     * Logs an error caught during processing.
     *
     * @param string $errorMessage Error description.
     * @param string $subject Incoming subject.
     * @param string $sender Incoming sender.
     * @param array $extra Diagnostics context.
     * @return bool
     */
    public static function logError(
        string $errorMessage,
        string $subject = '',
        string $sender = '',
        array $extra = []
    ): bool {
        return self::logEvent(
            AuditLog::ACTION_ERROR,
            AuditLog::STATUS_ERROR,
            $errorMessage,
            0,
            '',
            $subject,
            $sender,
            '',
            0,
            $extra,
            0
        );
    }
}
