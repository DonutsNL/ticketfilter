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
 *  @version	    1.1.0
 *  @author         Chris Gralike
 *  @copyright 	    Copyright (c) 2023 by Chris Gralike
 *  @license    	GPLv2+
 *  @see       	    https://github.com/DonutsNL/ticketfilter/readme.md
 *  @link		    https://github.com/DonutsNL/ticketfilter
 *  @since     	    1.1.0
 * ------------------------------------------------------------------------
 **/

namespace GlpiPlugin\Ticketfilter;
use CommonDropdown;
use DBConnection;
use Migration;
use Plugin;
use Config;

class FilterPattern extends CommonDropdown
{
    static function getTypeName($nb = 0) {
        if ($nb > 0) {
           return __('Filterpatterns', 'ticketfilter');
        }
        return __('Filterpatterns', 'ticketfilter');
    }

    public static function getMenuContent()
    {
        $menu = [];
        if (Config::canUpdate()) {
            $menu['title'] = self::getMenuName();
            $menu['page']  = '/' . Plugin::getWebDir('oauthimap', false) . '/front/filterpattern.php';
            $menu['icon']  = self::getIcon();
        }
        if (count($menu)) {
            return $menu;
        }
        return false;
    }

    public static function getIcon() 
    { 
        return 'fas fa-filter'; 
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
                'label'    => __('Ticket MatchString', 'ticketfilter'),
                'type'     => 'text',
            ],
            [
                'name'     => 'AssetMatchString',
                'label'    => __('Asset MatchString', 'ticketfilter'),
                'type'     => 'text',
            ],
            [
                'name'     => 'SolvedMatchString',
                'label'    => __('Solved Matchstring', 'ticketfilter'),
                'type'     => 'text',
            ],
            [
                'name'     => 'AutomaticallyMerge',
                'label'    => __('Automatically merge', 'ticketfilter'),
                'type'     => 'bool',
            ],
            [
                'name'     => 'LinkClosedTickets',
                'label'    => __('Link to closed source ticket', 'ticketfilter'),
                'type'     => 'bool',
            ],
            [
                'name'     => 'MatchSpecificSource',
                'label'    => __('Match only from specific source', 'ticketfilter'),
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

    /**
     * Uninstall previously installed data for this class.
     */
    public static function uninstall(Migration $migration)
    {
        $table = self::getTable();
        $migration->displayMessage("Uninstalling $table");
        $migration->dropTable($table);
    }
}