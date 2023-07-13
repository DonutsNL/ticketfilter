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
     /**
     * Method called by pre_item_add hook validates the object and passes
     * it to the RegEx Matching then decides what to do.
     * 
     * @param  int      $nb     number of items.
     * @return void             
     */
    static function getTypeName($nb = 0) : string
    {
        if ($nb > 0) {
           return __('Filterpatterns', 'ticketfilter');
        }
        return __('Filterpatterns', 'ticketfilter');
    }


    /**
     * Method called by pre_item_add hook validates the object and passes
     * it to the RegEx Matching then decides what to do.
     * 
     * @return mixed             boolean|array       
     */
    public static function getMenuContent()
    {
        $menu = [];
        if (Config::canUpdate()) {
            $menu['title'] = self::getMenuName();
            $menu['page']  = '/' . Plugin::getWebDir('ticketfilter', false) . '/front/filterpattern.php';
            $menu['icon']  = self::getIcon();
        }
        if (count($menu)) {
            return $menu;
        }
        return false;
    }

    /**
     * Sets icon for object.
     * 
     * @return string   $icon   
     */
    public static function getIcon() : string
    { 
        return 'fas fa-filter'; 
    }

    /**
     * Fetch fields for Dropdown 'add' form. Array order is equal with
     * field order in the form
     * 
     * @return string   $icon   
     */
    public function getAdditionalFields()
    {
        return [
            [
                'name'      => 'TicketMatchString',
                'label'     => __('Ticket MatchString', 'ticketfilter'),
                'type'      => 'text',
                'list'      => true,
            ],
/*          [
                'name'      => 'LinkClosedTickets',
                'label'     => __('Link to closed source ticket', 'ticketfilter'),
                'type'      => 'bool',
                'list'      => true,
            ],
*/          [
                'name'      => 'AssetMatchString',
                'label'     => __('Asset MatchString', 'ticketfilter'),
                'type'      => 'text',
                'list'      => true,
            ],
/*          [
                'name'      => 'AutomaticallyMerge',
                'label'     => __('Automatically merge', 'ticketfilter'),
                'type'      => 'bool',
                'list'      => true,
            ],
*/          [
                'name'      => 'SolvedMatchString',
                'label'     => __('Solved Matchstring', 'ticketfilter'),
                'type'      => 'text',
                'list'      => true,
            ],
            [
                'name'      => 'is_active',
                'label'     => __('Active', 'ticketfilter'),
                'type'      => 'bool',
            ],
            [
                'name'      => 'MatchSpecificSource',
                'label'     => __('Match only from specific source', 'ticketfilter'),
                'type'      => 'text',
                'list'      => true,
            ],
            [
                'name'      => 'SuppressNotification',
                'label'     => __('Suppress Notifications', 'ticketfilter'),
                'type'      => 'bool',
                'list'      => true,
            ],
            
        ];
    }

    /**
     * Add fields to search and potential table columns
     * 
     * @return array   $rawSearchOptions   
     */
    public function rawSearchOptions() : array
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'                 => '5',
            'table'              => $this->getTable(),
            'field'              => 'TicketMatchString',
            'name'               => __('Ticket Match String', 'ticketfilter'),
            'searchtype'         => ['equals', 'notequals'],
            'datatype'           => 'specific',
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => $this->getTable(),
            'field'              => 'AssetMatchString',
            'name'               => __('Asset Match String', 'ticketfilter'),
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'                 => '7',
            'table'              => $this->getTable(),
            'field'              => 'SolvedMatchString',
            'name'               => __('Solved Match String', 'ticketfilter'),
            'datatype'           => 'text',
        ];
/*
        $tab[] = [
            'id'                 => '8',
            'table'              => $this->getTable(),
            'field'              => 'AutomaticallyMerge',
            'name'               => __('Automatically Merge', 'ticketfilter'),
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'                 => '9',
            'table'              => $this->getTable(),
            'field'              => 'LinkClosedTickets',
            'name'               => __('Link To Closed Tickets', 'ticketfilter'),
            'datatype'           => 'text',
        ];
*/
        $tab[] = [
            'id'                 => '9',
            'table'              => $this->getTable(),
            'field'              => 'MatchSpecificSource',
            'name'               => __('Match Specific Source', 'ticketfilter'),
            'datatype'           => 'text',
        ];

        return $tab;
    }
    
    /**
     * Install table needed for dropdowns
     * 
     * @return void
     * @see             hook.php:plugin_ticketfilter_install()  
     */
    public static function install(Migration $migration) : void
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
            `id`                   int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `name`                 varchar(255) DEFAULT NULL,
            `comment`              text,
            `is_active`            tinyint NOT NULL DEFAULT '0',
            `date_creation`        timestamp NULL DEFAULT NULL,
            `date_mod`             timestamp NULL DEFAULT NULL,
            `TicketMatchString`    text NOT NULL,
            `AssetMatchString`     text NULL,
            `SolvedMatchString`    text NULL,
            `AutomaticallyMerge`   tinyint NOT NULL DEFAULT '0',
            `LinkClosedTickets`    tinyint NOT NULL DEFAULT '0',
            `SearchTicketBody`     tinyint NOT NULL DEFAULT '0',
            `MatchSpecificSource`  text NULL,
            `SuppressNotification` tinyint NOT NULL DEFAULT '1',
            PRIMARY KEY (`id`),
            KEY `name` (`name`),
            KEY `is_active` (`is_active`),
            KEY `date_creation` (`date_creation`),
            KEY `date_mod` (`date_mod`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;
            SQL;
            $DB->query($query) or die($DB->error());

            // insert example rule;
            $query = <<<SQL
            INSERT INTO `$table`(name, comment, is_active, TicketMatchString) 
            VALUES('example', 'this is an example expression', '1', '/.*?(?<match>\(JIRA-[0-9]{1,4}\)).*/');
            SQL;
            $DB->query($query) or die($DB->error());
        }       
    }

    /**
     * Uninstall tables uncomment the line to make plugin clean table.
     * 
     * @return void
     * @see             hook.php:plugin_ticketfilter_uninstall()
     */
    public static function uninstall(Migration $migration) : void
    {
        $table = self::getTable();
        $migration->displayMessage("Uninstalling $table");
        $migration->dropTable($table);
    }
}