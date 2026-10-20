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

namespace GlpiPlugin\Ticketfilter\Model;

use CommonDropdown;
use Config;
use DBConnection;
use Migration;

/**
 * FilterPattern configuration model representing ticket regex matching rules.
 */
class FilterPattern extends CommonDropdown
{
    /**
     * Database table name.
     */
    public const TABLE = 'glpi_plugin_ticketfilter_filterpatterns';

    /**
     * Table field constants.
     */
    public const FIELD_ID                      = 'id';
    public const FIELD_NAME                    = 'name';
    public const FIELD_COMMENT                 = 'comment';
    public const FIELD_ACTIVE                  = 'is_active';
    public const FIELD_DATE_CREATION           = 'date_creation';
    public const FIELD_DATE_MOD                = 'date_mod';
    public const FIELD_TICKETMATCHSTR          = 'TicketMatchString';
    public const FIELD_TICKETMATCHSTRLEN       = 'TicketMatchStringLength';
    public const FIELD_ASSETMATCHSTR           = 'AssetMatchString';
    public const FIELD_ASSETMATCHSTRLEN        = 'AssetMatchStringLength';
    public const FIELD_SOLVEDMATCHSTR          = 'SolvedMatchString';
    public const FIELD_SOLVEDMATCHSTRLEN       = 'SolvedMatchStringLength';
    public const FIELD_AUTOMERGE               = 'AutomaticallyMerge';
    public const FIELD_REOPENCLOSED            = 'ReopenClosedTickets';
    public const FIELD_MAX_REOPEN_AGE_DAYS     = 'max_reopen_age_days';
    public const FIELD_SEARCHBODY              = 'SearchTicketBody';
    public const FIELD_MATCHSOURCE             = 'MatchSpecificSource';
    public const FIELD_SENDER_POLICY           = 'sender_policy';
    public const FIELD_UNMATCHED_SENDER_ACTION = 'unmatched_sender_action';
    public const FIELD_IS_PRIVATE_FOLLOWUP     = 'is_private_followup';
    public const FIELD_SUPPRESNOTIF            = 'SuppressNotification';
    public const FIELD_REOPEN_MESSAGE_TEMPLATE = 'reopen_message_template';
    public const FIELD_SOLVED_MESSAGE_TEMPLATE = 'solved_message_template';
    public const FIELD_ENTITIES_ID             = 'entities_id';
    public const FIELD_IS_RECURSIVE            = 'is_recursive';

    /**
     * Sender policy constants.
     */
    public const POLICY_STRICT_REQUESTER       = 'strict_requester';
    public const POLICY_ANY_ACTOR              = 'any_actor';
    public const POLICY_SAME_DOMAIN            = 'same_domain';
    public const POLICY_OPEN                   = 'open';

    /**
     * Unmatched sender action constants.
     */
    public const ACTION_NEW_TICKET             = 'new_ticket';
    public const ACTION_PRIVATE_FOLLOWUP       = 'private_followup';
    public const ACTION_REJECT                 = 'reject';

    /**
     * Returns database table name.
     *
     * @param string|null $classname Class name.
     * @return string
     */
    public static function getTable($classname = null): string
    {
        return self::TABLE;
    }

    /**
     * Returns human-readable menu item title for navigation menus.
     *
     * @return string
     */
    public static function getMenuName(): string
    {
        return __('Ticket filter', 'ticketfilter');
    }

    /**
     * Returns human-readable type name.
     *
     * @param int $nb Number of items.
     * @return string
     */
    public static function getTypeName($nb = 0): string
    {
        return $nb > 1 ? __('Ticket filters', 'ticketfilter') : __('Ticket filter', 'ticketfilter');
    }

    /**
     * Dropdown menu content definition.
     *
     * @return array|bool
     */
    public static function getMenuContent()
    {
        $menu = [];
        if (Config::canUpdate()) {
            $menu['title'] = self::getMenuName();
            $menu['page']  = self::getSearchURL(false);
            $menu['icon']  = self::getIcon();
            $menu['links'] = [
                'search' => self::getSearchURL(false),
            ];
        }
        return (count($menu) > 0) ? $menu : false;
    }

    /**
     * Added links for user convenience.
     *
     * @return array Array containing requested interface links
     * @see CommonGLPI::getAdditionalMenuLinks()
     */
    public static function getAdditionalMenuLinks(): array
    {
        $links = [];
        $title = __('TicketFilter dashboard', 'ticketfilter');
        $links[$title] = '/plugins/ticketfilter/front/config';
        return $links;
    }

    /**
     * Search URL pointing strictly to TicketFilter plugin configuration.
     *
     * @param bool $full Whether to include full URL with base.
     * @return string
     */
    public static function getSearchURL($full = true): string
    {
        global $CFG_GLPI;
        $base = ($full && isset($CFG_GLPI['url_base'])) ? rtrim((string)$CFG_GLPI['url_base'], '/') : '';
        $path = function_exists('ticketfilter_plugin_web_dir') ? ticketfilter_plugin_web_dir() : '/plugins/ticketfilter';
        return $base . $path . '/front/config';
    }

    /**
     * Form URL pointing strictly to TicketFilter plugin configuration.
     *
     * @param bool $full Whether to include full URL with base.
     * @return string
     */
    public static function getFormURL($full = true): string
    {
        global $CFG_GLPI;
        $base = ($full && isset($CFG_GLPI['url_base'])) ? rtrim((string)$CFG_GLPI['url_base'], '/') : '';
        $path = function_exists('ticketfilter_plugin_web_dir') ? ticketfilter_plugin_web_dir() : '/plugins/ticketfilter';
        return $base . $path . '/front/config';
    }

    /**
     * Returns icon class.
     *
     * @return string
     */
    public static function getIcon(): string
    {
        return 'fas fa-filter';
    }

    /**
     * Additional form fields for the GLPI dropdown form.
     *
     * @return array
     */
    public function getAdditionalFields(): array
    {
        return [
            [
                'name'  => self::FIELD_TICKETMATCHSTR,
                'label' => __('Ticket identifier pattern (regex, must contain (?<match>...))', 'ticketfilter'),
                'type'  => 'text',
                'list'  => true,
            ],
            [
                'name'  => self::FIELD_TICKETMATCHSTRLEN,
                'label' => __('Maximum identifier length (characters)', 'ticketfilter'),
                'type'  => 'integer',
                'list'  => true,
                'min'   => 1,
            ],
            [
                'name'  => self::FIELD_SOLVEDMATCHSTR,
                'label' => __('Auto-solve keyword pattern (optional, must contain (?<solved>...))', 'ticketfilter'),
                'type'  => 'text',
                'list'  => true,
            ],
            [
                'name'  => self::FIELD_SOLVEDMATCHSTRLEN,
                'label' => __('Maximum auto-solve keyword length (characters)', 'ticketfilter'),
                'type'  => 'integer',
                'list'  => true,
                'min'   => 1,
            ],
            [
                'name'  => self::FIELD_ACTIVE,
                'label' => __('Active', 'ticketfilter'),
                'type'  => 'bool',
                'list'  => true,
            ],
            [
                'name'    => self::FIELD_SENDER_POLICY,
                'label'   => __('Merge when', 'ticketfilter'),
                'type'    => 'select',
                'values'  => [
                    self::POLICY_STRICT_REQUESTER => __('Requesters match (ticket creator only)', 'ticketfilter'),
                    self::POLICY_ANY_ACTOR        => __('An actor matches (requester, technician, or observer)', 'ticketfilter'),
                    self::POLICY_SAME_DOMAIN      => __('Sender domain matches (@company.com)', 'ticketfilter'),
                    self::POLICY_OPEN             => __('Always (allow any sender)', 'ticketfilter'),
                ],
                'default' => self::POLICY_STRICT_REQUESTER,
                'list'    => true,
            ],
            [
                'name'    => self::FIELD_UNMATCHED_SENDER_ACTION,
                'label'   => __('If sender does not match', 'ticketfilter'),
                'type'    => 'select',
                'values'  => [
                    self::ACTION_NEW_TICKET       => __('Create a standard new ticket', 'ticketfilter'),
                    self::ACTION_PRIVATE_FOLLOWUP => __('Add as private followup to existing ticket', 'ticketfilter'),
                    self::ACTION_REJECT           => __('Discard email (do not create ticket)', 'ticketfilter'),
                ],
                'default' => self::ACTION_NEW_TICKET,
                'list'    => true,
            ],
            [
                'name'  => self::FIELD_REOPENCLOSED,
                'label' => __('Reopen ticket if closed', 'ticketfilter'),
                'type'  => 'bool',
                'list'  => true,
            ],
            [
                'name'  => self::FIELD_MAX_REOPEN_AGE_DAYS,
                'label' => __('Only if closed within (days)', 'ticketfilter'),
                'type'  => 'integer',
                'min'   => 0,
                'list'  => true,
            ],
            [
                'name'  => self::FIELD_SEARCHBODY,
                'label' => __('Also search email body (if ID not found in subject)', 'ticketfilter'),
                'type'  => 'bool',
                'list'  => true,
            ],
            [
                'name'  => self::FIELD_SUPPRESNOTIF,
                'label' => __('Do not send email notification when merging', 'ticketfilter'),
                'type'  => 'bool',
                'list'  => true,
            ],
            [
                'name'  => self::FIELD_REOPEN_MESSAGE_TEMPLATE,
                'label' => __('Reopen private note template', 'ticketfilter'),
                'type'  => 'text',
                'list'  => false,
            ],
            [
                'name'  => self::FIELD_SOLVED_MESSAGE_TEMPLATE,
                'label' => __('Auto-solve private note template', 'ticketfilter'),
                'type'  => 'text',
                'list'  => false,
            ],
        ];
    }

    /**
     * Retrieves all active filter patterns from the database.
     *
     * @return array
     */
    public static function getFilterPatterns(): array
    {
        global $DB;

        $patterns = [];
        $table    = self::TABLE;

        if (!$DB || !$DB->tableExists($table)) {
            return [];
        }

        $iterator = $DB->request([
            'FROM'  => $table,
            'WHERE' => [self::FIELD_ACTIVE => 1],
            'ORDER' => [self::FIELD_ID . ' ASC']
        ]);

        foreach ($iterator as $row) {
            $patterns[] = $row;
        }

        return $patterns;
    }

    /**
     * Database installation and schema migration.
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
                `id`                        int {$defaultKeySign} NOT NULL AUTO_INCREMENT,
                `name`                      varchar(255) DEFAULT NULL,
                `comment`                   text,
                `is_active`                 tinyint NOT NULL DEFAULT '0',
                `date_creation`             timestamp NULL DEFAULT NULL,
                `date_mod`                  timestamp NULL DEFAULT NULL,
                `TicketMatchString`         text NOT NULL,
                `TicketMatchStringLength`   int NOT NULL DEFAULT '25',
                `AssetMatchString`          text NULL,
                `AssetMatchStringLength`    int NOT NULL DEFAULT '10',
                `SolvedMatchString`         text NULL,
                `SolvedMatchStringLength`   int NOT NULL DEFAULT '25',
                `AutomaticallyMerge`        tinyint NOT NULL DEFAULT '0',
                `ReopenClosedTickets`       tinyint NOT NULL DEFAULT '0',
                `max_reopen_age_days`       int NOT NULL DEFAULT '30',
                `SearchTicketBody`          tinyint NOT NULL DEFAULT '0',
                `MatchSpecificSource`       tinyint NOT NULL DEFAULT '1',
                `sender_policy`             varchar(30) NOT NULL DEFAULT 'strict_requester',
                `unmatched_sender_action`   varchar(30) NOT NULL DEFAULT 'new_ticket',
                `is_private_followup`       tinyint NOT NULL DEFAULT '0',
                `SuppressNotification`      tinyint NOT NULL DEFAULT '1',
                `reopen_message_template`   text NULL,
                `solved_message_template`   text NULL,
                `entities_id`               int {$defaultKeySign} NOT NULL DEFAULT '0',
                `is_recursive`              tinyint NOT NULL DEFAULT '1',
                PRIMARY KEY (`id`),
                KEY `name` (`name`),
                KEY `is_active` (`is_active`),
                KEY `entities_id` (`entities_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$defaultCharset} COLLATE={$defaultCollation} ROW_FORMAT=DYNAMIC;
            SQL;

            $DB->doQuery($query);

            // Insert safe default example
            $exampleQuery = <<<SQL
            INSERT INTO `$table` (
                `name`, `comment`, `is_active`, `TicketMatchString`, `TicketMatchStringLength`,
                `SolvedMatchString`, `SolvedMatchStringLength`, `sender_policy`, `unmatched_sender_action`,
                `ReopenClosedTickets`, `max_reopen_age_days`, `SuppressNotification`
            ) VALUES (
                'Jira Issue Matcher',
                'Matches external Jira issue keys such as (JIRA-1234) in the email subject',
                '1',
                '/.*?(?<match>\([A-Z]{2,10}-[0-9]{1,6}\)).*/i',
                '25',
                '/.*?(?<solved>Resolved|Closed).*/i',
                '25',
                'strict_requester',
                'new_ticket',
                '1',
                '30',
                '1'
            );
            SQL;

            $DB->doQuery($exampleQuery);
        } else {
            // Apply incremental column migrations for existing installations
            if (!$DB->fieldExists($table, self::FIELD_SENDER_POLICY)) {
                $migration->addField($table, self::FIELD_SENDER_POLICY, 'varchar(30)', ['value' => self::POLICY_STRICT_REQUESTER]);
            }
            if (!$DB->fieldExists($table, self::FIELD_UNMATCHED_SENDER_ACTION)) {
                $migration->addField($table, self::FIELD_UNMATCHED_SENDER_ACTION, 'varchar(30)', ['value' => self::ACTION_NEW_TICKET]);
            }
            if (!$DB->fieldExists($table, self::FIELD_MAX_REOPEN_AGE_DAYS)) {
                $migration->addField($table, self::FIELD_MAX_REOPEN_AGE_DAYS, 'integer', ['value' => 30]);
            }
            if (!$DB->fieldExists($table, self::FIELD_IS_PRIVATE_FOLLOWUP)) {
                $migration->addField($table, self::FIELD_IS_PRIVATE_FOLLOWUP, 'tinyint', ['value' => 0]);
            }
            if (!$DB->fieldExists($table, self::FIELD_REOPEN_MESSAGE_TEMPLATE)) {
                $migration->addField($table, self::FIELD_REOPEN_MESSAGE_TEMPLATE, 'text', ['value' => null]);
            }
            if (!$DB->fieldExists($table, self::FIELD_SOLVED_MESSAGE_TEMPLATE)) {
                $migration->addField($table, self::FIELD_SOLVED_MESSAGE_TEMPLATE, 'text', ['value' => null]);
            }
            if (!$DB->fieldExists($table, self::FIELD_ENTITIES_ID)) {
                $migration->addField($table, self::FIELD_ENTITIES_ID, 'integer', ['value' => 0]);
            }
            if (!$DB->fieldExists($table, self::FIELD_IS_RECURSIVE)) {
                $migration->addField($table, self::FIELD_IS_RECURSIVE, 'tinyint', ['value' => 1]);
            }
            $migration->migrationOneTable($table);
        }
    }

    /**
     * Self-heals table schema if required columns are missing in running instance.
     *
     * @return void
     */
    public static function ensureSchema(): void
    {
        global $DB;
        if (!isset($DB) || !is_object($DB) || !method_exists($DB, 'tableExists')) {
            return;
        }
        $table = self::TABLE;
        if (!$DB->tableExists($table)) {
            return;
        }
        if (method_exists($DB, 'fieldExists')) {
            if (!$DB->fieldExists($table, self::FIELD_REOPEN_MESSAGE_TEMPLATE)) {
                $DB->doQuery("ALTER TABLE `{$table}` ADD COLUMN `" . self::FIELD_REOPEN_MESSAGE_TEMPLATE . "` TEXT NULL");
            }
            if (!$DB->fieldExists($table, self::FIELD_SOLVED_MESSAGE_TEMPLATE)) {
                $DB->doQuery("ALTER TABLE `{$table}` ADD COLUMN `" . self::FIELD_SOLVED_MESSAGE_TEMPLATE . "` TEXT NULL");
            }
        }
    }

    /**
     * Database uninstallation.
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
