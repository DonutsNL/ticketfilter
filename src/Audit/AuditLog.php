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

use CommonDBTM;
use DBConnection;
use Migration;

/**
 * AuditLog entity managing the persistent audit trail for all TicketFilter actions.
 */
class AuditLog extends CommonDBTM
{
    /**
     * Database table name.
     */
    public const TABLE = 'glpi_plugin_ticketfilter_logs';

    /**
     * Table column constants.
     */
    public const FIELD_ID                 = 'id';
    public const FIELD_DATE_CREATION      = 'date_creation';
    public const FIELD_PATTERN_ID        = 'plugin_ticketfilter_patterns_id';
    public const FIELD_PATTERN_NAME       = 'pattern_name';
    public const FIELD_INCOMING_SUBJECT   = 'incoming_subject';
    public const FIELD_INCOMING_SENDER    = 'incoming_sender';
    public const FIELD_MATCHED_TOKEN      = 'matched_token';
    public const FIELD_TARGET_TICKETS_ID  = 'tickets_id';
    public const FIELD_ACTION_TAKEN       = 'action_taken';
    public const FIELD_STATUS             = 'status';
    public const FIELD_MESSAGE            = 'message';
    public const FIELD_DETAILS            = 'details';
    public const FIELD_ENTITIES_ID        = 'entities_id';

    /**
     * Action taken constants.
     */
    public const ACTION_MERGED            = 'MERGED_FOLLOWUP';
    public const ACTION_REOPENED          = 'TICKET_REOPENED';
    public const ACTION_SOLVED            = 'TICKET_SOLVED';
    public const ACTION_SKIPPED_POLICY    = 'SKIPPED_SENDER_POLICY';
    public const ACTION_SKIPPED_CLOSED    = 'SKIPPED_REOPEN_DISABLED';
    public const ACTION_SKIPPED_AGE       = 'SKIPPED_MAX_AGE_EXCEEDED';
    public const ACTION_SKIPPED_COLLISION = 'SKIPPED_AMBIGUOUS_COLLISION';
    public const ACTION_SKIPPED_ENTITY    = 'SKIPPED_ENTITY_MISMATCH';
    public const ACTION_ERROR             = 'ERROR';
    public const ACTION_SIMULATION        = 'SIMULATION';

    /**
     * Execution status constants.
     */
    public const STATUS_SUCCESS           = 'success';
    public const STATUS_WARNING           = 'warning';
    public const STATUS_ERROR             = 'error';
    public const STATUS_INFO              = 'info';

    /**
     * Returns the table name for this model.
     *
     * @return string
     */
    public static function getTable($classname = null): string
    {
        return self::TABLE;
    }

    /**
     * Returns human-readable type name.
     *
     * @param int $nb Number of items.
     * @return string
     */
    public static function getTypeName($nb = 0): string
    {
        return __('Audit Logs', 'ticketfilter');
    }

    /**
     * Installs the audit log table during plugin installation or migration.
     *
     * @param Migration $migration GLPI migration handler.
     * @return void
     */
    public static function install(Migration $migration): void
    {
        global $DB;

        $defaultCharset   = DBConnection::getDefaultCharset();
        $defaultCollation = DBConnection::getDefaultCollation();
        $defaultKeySign   = DBConnection::getDefaultPrimaryKeySignOption();
        $table            = self::TABLE;

        if (!$DB->tableExists($table)) {
            $migration->displayMessage(sprintf(__('Installing %s table', 'ticketfilter'), $table));

            $query = <<<SQL
            CREATE TABLE IF NOT EXISTS `$table` (
                `id`                                int {$defaultKeySign} NOT NULL AUTO_INCREMENT,
                `date_creation`                     timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `plugin_ticketfilter_patterns_id`   int {$defaultKeySign} NOT NULL DEFAULT '0',
                `pattern_name`                      varchar(255) DEFAULT NULL,
                `incoming_subject`                  varchar(255) DEFAULT NULL,
                `incoming_sender`                   varchar(255) DEFAULT NULL,
                `matched_token`                     varchar(255) DEFAULT NULL,
                `tickets_id`                        int {$defaultKeySign} NOT NULL DEFAULT '0',
                `action_taken`                      varchar(50) NOT NULL,
                `status`                            varchar(20) NOT NULL DEFAULT 'success',
                `message`                           text DEFAULT NULL,
                `details`                           longtext DEFAULT NULL,
                `entities_id`                       int {$defaultKeySign} NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `date_creation` (`date_creation`),
                KEY `plugin_ticketfilter_patterns_id` (`plugin_ticketfilter_patterns_id`),
                KEY `tickets_id` (`tickets_id`),
                KEY `action_taken` (`action_taken`),
                KEY `status` (`status`),
                KEY `entities_id` (`entities_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$defaultCharset} COLLATE={$defaultCollation} ROW_FORMAT=DYNAMIC;
            SQL;

            $DB->doQuery($query);
        }
    }

    /**
     * Drops the audit log table during plugin uninstallation.
     *
     * @param Migration $migration GLPI migration handler.
     * @return void
     */
    public static function uninstall(Migration $migration): void
    {
        $table = self::TABLE;
        $migration->displayMessage(sprintf(__('Uninstalling %s table', 'ticketfilter'), $table));
        $migration->dropTable($table);
    }
}
