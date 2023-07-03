<?php

/**
 *  ------------------------------------------------------------------------
 *  Chris Gralike Ticket Filter
 *  Copyright (C) 2023 by Chris Gralike
 *  ------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Ticket Filter project.
 *
 * Ticket Filter plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Ticket Filter is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with ticket filter. If not, see <http://www.gnu.org/licenses/>.
 *
 * ------------------------------------------------------------------------
 *
 *  @package  	    TicketFilter
 *  @version	    1.0.0
 *  @author         Chris Gralike
 *  @copyright 	    Copyright (c) 2023 by Chris Gralike
 *  @license    	MIT
 *  @see       	    https://github.com/DonutsNL/ticketfilter/readme.md
 *  @link		    https://github.com/DonutsNL/ticketfilter
 *  @since     	    1.0.0
 *  @todo           !Keep it stupid simple!
 *                  -Create a fancy configuration page
 *                   -Config [Followup Match string] [AutoSolve Match String] [Supress followups] [regEx101 testlink/comment]
 *                   -Option to automatically merge duplicate oldest/latest ticket if more then 1 ticket is found with matchstring.
 *                   -Option to automatically link with closed ticket(s) on match closed (how to deal with multiple closed tickets?)
 *                  -Default GLPI behaviour is to load Plugin trazilion times with each dashboard graph, maybe create singleton pattern/caching?
 * ------------------------------------------------------------------------
 **/

namespace GlpiPlugin\Ticketfilter;
use CommonDropdown;
use DBConnection;
use Migration;

<<<<<<< HEAD:src/Configs.php
class Configs extends CommonDropdown
=======
class Config extends CommonDropdown
>>>>>>> 85292ed (In development):src/Config.php
{
    static function getTypeName($nb = 0) {

        if ($nb > 0) {
<<<<<<< HEAD:src/Configs.php
           return __('Plugin Ticketfilter Dropdowns', 'ticketfilter');
        }
        return __('Plugin Ticketfilter Dropdowns', 'ticketfilter');
     }

    public static function install(Migration $migration)
    {
        global $DB;

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

        $table = self::getTable();

        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");
            $query = <<<SQL
            CREATE TABLE IF NOT EXISTS `$table` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `name` varchar(255) DEFAULT NULL,
            `is_active` tinyint NOT NULL DEFAULT '0',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            `TicketMatchString` text NOT NULL,
            `AssetMatchString` text NULL,
            `SolvedMatchString` text NULL,
            `AutomaticallyMerge` tinyint NOT NULL DEFAULT '0',
            `LinkClosedTickets` tinyint NOT NULL DEFAULT '0',
            `SearchTicketBody` tinyint NOT NULL DEFAULT '0',
            `MatchSpecificSource` text NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`),
            KEY `is_active` (`is_active`),
            KEY `date_creation` (`date_creation`),
            KEY `date_mod` (`date_mod`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;
            SQL;
            $DB->query($query) or die($DB->error());
        }
    }

=======
           return __('Plugin Ticketfilter Dropdowns', 'configfilter');
        }
        return __('Plugin Ticketfilter Dropdowns', 'configfilter');
    }


    public function getAdditionalFields()
    {
        return [
            [
                'name'  => 'is_active',
                'label' => __('Active', 'ticketfilter'),
                'type'  => 'bool',
            ],
            [
                'name'     => 'TicketMatchString',
                'label'    => __('TicketMatchString', 'ticketfilter'),
                'type'     => 'text',
            ],
            [
                'name'     => 'AssetMatchString',
                'label'    => __('AssetMatchString', 'ticketfilter'),
                'type'     => 'text',
            ],
            [
                'name'     => 'SolvedMatchString',
                'label'    => __('SolvedMatchString', 'ticketfilter'),
                'type'     => 'text',
            ],
            [
                'name'     => 'AutomaticallyMerge',
                'label'    => __('AutomaticallyMerge', 'ticketfilter'),
                'type'     => 'bool',
            ],
            [
               'name'     => 'LinkClosedTickets',
               'label'    => __('LinkClosedTickets', 'ticketfilter'),
               'type'     => 'bool',
            ],
            [
               'name'     => 'SearchTicketBody',
               'label'    => __('SearchTicketBody', 'ticketfilter'),
               'type'     => 'bool',
            ],
            [
            'name'     => 'MatchSpecificSource',
            'label'    => __('MatchSpecificSource', 'ticketfilter'),
            'type'     => 'text',
            ],
        ];
    }

    public static function install(Migration $migration)
    {
        global $DB;

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

        $table = self::getTable();

        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");
            $query = <<<SQL
            CREATE TABLE IF NOT EXISTS `$table` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `name` varchar(255) DEFAULT NULL,
            `comment` text,
            `is_active` tinyint NOT NULL DEFAULT '0',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            `TicketMatchString` text NOT NULL,
            `AssetMatchString` text NULL,
            `SolvedMatchString` text NULL,
            `AutomaticallyMerge` tinyint NOT NULL DEFAULT '0',
            `LinkClosedTickets` tinyint NOT NULL DEFAULT '0',
            `SearchTicketBody` tinyint NOT NULL DEFAULT '0',
            `MatchSpecificSource` text NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`),
            KEY `is_active` (`is_active`),
            KEY `date_creation` (`date_creation`),
            KEY `date_mod` (`date_mod`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;
            SQL;
            $DB->query($query) or die($DB->error());
        }
    }

>>>>>>> 85292ed (In development):src/Config.php
    /**
     * Uninstall previously installed data for this class.
     */
    public static function uninstall(Migration $migration)
    {
<<<<<<< HEAD:src/Configs.php

=======
>>>>>>> 85292ed (In development):src/Config.php
        $table = self::getTable();
        $migration->displayMessage("Uninstalling $table");
        $migration->dropTable($table);
    }
}