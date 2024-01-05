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
 *  @version	    1.2.0
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
     * Table fields
     */
    const NAME              = 'name';
    const ACTIVE            = 'is_active';
    const DATE_CREATION     = 'date_creation';
    const DATE_MOD          = 'date_mod';
    const TICKETMATCHSTR    = 'TicketMatchString';
    const TICKETMATCHSTRLEN = 'TicketMatchStringLength';
    const ASSETMATCHSTR     = 'AssetMatchString';
    const ASSETMATCHSTRLEN  = 'AssetMatchStringLength';
    const SOLVEDMATCHSTR    = 'SolvedMatchString';
    const SOLVEDMATCHSTRLEN = 'SolvedMatchStringLength';
    const AUTOMERGE         = 'AutomaticallyMerge';
    const REOPENCLOSED      = 'ReopenClosedTickets';
    const SEARCHBODY        = 'SearchTicketBody';
    const MATCHSOURCE       = 'MatchSpecificSource';
    const SUPPRESNOTIF      = 'SuppressNotification';

     /**
     * getTypeName(int nb) : string -
     * Method called by pre_item_add hook validates the object and passes
     * it to the RegEx Matching then decides what to do.
     *
     * @param  int      $nb     number of items.
     * @return void
     */
    public static function getTypeName($nb = 0) : string
    {
        if ($nb > 0) {
           return __('Filterpatterns', 'ticketfilter');
        }
        return __('Filterpatterns', 'ticketfilter');
    }


    /**
     * getMenuContent() : array | bool -
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
     * getIcon() : string -
     * Sets icon for object.
     *
     * @return string   $icon
     */
    public static function getIcon() : string
    {
        return 'fas fa-filter';
    }

    /**
     * getAdditionalFields() : array -
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
                'label'     => __('Ticket match string', 'ticketfilter'),
                'type'      => 'text',
                'list'      => true,
            ],
            [
                'name'      => 'TicketMatchStringLength',
                'label'     => __('Ticket matched string maximum length', 'ticketfilter'),
                'type'      => 'integer',
                'list'      => true,
                'min'       => 1,
            ],
            /*
            [
                'name'      => 'AssetMatchString',
                'label'     => __('Asset match string', 'ticketfilter'),
                'type'      => 'text',
                'list'      => true,
            ],
            [
                'name'      => 'AssetMatchStringLength',
                'label'     => __('Asset match group  length', 'ticketfilter'),
                'type'      => 'integer',
                'list'      => true,
                'min'       => 1,
            ],
            [
                'name'      => 'AutomaticallyMerge',
                'label'     => __('Automatically merge', 'ticketfilter'),
                'type'      => 'bool',
                'list'      => true,
            ],
*/          [
                'name'      => 'SolvedMatchString',
                'label'     => __('Solved matchstring', 'ticketfilter'),
                'type'      => 'text',
                'list'      => true,
            ],
            [
                'name'      => 'SolvedMatchStringLength',
                'label'     => __('Solved match group length', 'ticketfilter'),
                'type'      => 'integer',
                'list'      => true,
                'min'       => 1,
            ],
            [
                'name'      => 'is_active',
                'label'     => __('Active', 'ticketfilter'),
                'type'      => 'bool',
            ],
              // version 1.3.0 changes;
              // Made config more descriptive as per enhancement
              // https://github.com/DonutsNL/ticketfilter/issues/4
            [
                'name'      => 'MatchSpecificSource',
                'label'     => __('Only apply with identical sources', 'ticketfilter'),
                'type'      => 'bool',
                'list'      => true,
            ],
            /*
            [
                'name'      => 'MatchStringLength',
                'label'     => __('The maximum length of the matchstring', 'ticketfilter'),
                'type'      => 'text',
                'list'      => true,
            ],
            */
            [
                'name'      => 'SuppressNotification',
                'label'     => __('Suppress Notifications', 'ticketfilter'),
                'type'      => 'bool',
                'list'      => true,
            ],
            [
                'name'      => 'ReopenClosedTickets',
                'label'     => __('Reopen any closed ticket if matched', 'ticketfilter'),
                'type'      => 'bool',
                'list'      => true,
            ],
        ];
    }

    /**
     * rawSearchOptions() : array -
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
     * getFilterPatterns() : array -
     * Get match patterns and config from dropdowns
     *
     * @return patterns              Array with all configured patterns
     * @since                        1.1.0
     */
    public static function getFilterPatterns() : array
    {
        global $DB;
        $patterns = [];
        $dropdown = new FilterPattern();
        $table = $dropdown::getTable();
        foreach($DB->request($table) as $id => $row){
            $patterns[] = [self::NAME                => $row[self::NAME],
                           self::ACTIVE              => $row[self::ACTIVE],
                           self::DATE_CREATION       => $row[self::DATE_CREATION],
                           self::DATE_MOD            => $row[self::DATE_MOD],
                           self::TICKETMATCHSTR      => $row[self::TICKETMATCHSTR],
                           self::TICKETMATCHSTRLEN   => $row[self::TICKETMATCHSTRLEN],
                           self::ASSETMATCHSTR       => $row[self::ASSETMATCHSTR],
                           self::ASSETMATCHSTRLEN    => $row[self::ASSETMATCHSTRLEN],
                           self::SOLVEDMATCHSTR      => $row[self::SOLVEDMATCHSTR],
                           self::SOLVEDMATCHSTRLEN   => $row[self::SOLVEDMATCHSTRLEN],
                           self::AUTOMERGE           => $row[self::AUTOMERGE],
                           self::REOPENCLOSED        => $row[self::REOPENCLOSED],
                           self::SEARCHBODY          => $row[self::SEARCHBODY],
                           self::MATCHSOURCE         => $row[self::MATCHSOURCE],
                           self::SUPPRESNOTIF        => $row[self::SUPPRESNOTIF]];
        }
        return $patterns;
    }

    /**
     * install(Migration migration) : void -
     * Install table needed for Ticket Filter configuration dropdowns
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

        // Create the base table if it does not yet exist;
        // Dont update this table for later versions, use the migration class;
        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");
            $query = <<<SQL
            CREATE TABLE IF NOT EXISTS `$table` (
            `id`                        int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `name`                      varchar(255) DEFAULT NULL,
            `comment`                   text,
            `is_active`                 tinyint NOT NULL DEFAULT '0',
            `date_creation`             timestamp NULL DEFAULT NULL,
            `date_mod`                  timestamp NULL DEFAULT NULL,
            `TicketMatchString`         text NOT NULL,
            `TicketMatchStringLength`   INT NOT NULL default 11,
            `AssetMatchString`          text NULL,
            `AssetMatchStringLength`    INT NOT NULL default 10,
            `SolvedMatchString`         text NULL,
            `SolvedMatchStringLength`   INT NOT NULL default 10,
            `AutomaticallyMerge`        tinyint NOT NULL DEFAULT '0',
            `ReopenClosedTickets`       tinyint NOT NULL DEFAULT '0',
            `SearchTicketBody`          tinyint NOT NULL DEFAULT '0',
            `MatchSpecificSource`       text NULL,
            `SuppressNotification`      tinyint NOT NULL DEFAULT '1',
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
            INSERT INTO `$table`(name, comment, is_active, TicketMatchString, TicketMatchStringLength, SolvedMatchString, SolvedMatchStringLength)
            VALUES('example', 'this is an example expression', '1', '/.*?(?<match>\(JIRA-[0-9]{1,4}\)).*/', '11', '/.*?(?<solved>Closed).*/', '6');
            SQL;
            $DB->query($query) or die($DB->error());
        }
        
        // version 1.3.0 changes;
        // Updated 1.3.0 alter MatchSpecific Source to tinyint (yes/no) as configuration for enhancement
        // https://github.com/DonutsNL/ticketfilter/issues/4
        $migration->changeField($table, 'MatchSpecificSource', 'MatchSpecificSource', 'tinyint', ['null' => false, 'value' => '1']);
        $migration->migrationOneTable($table);
    }

    /**
     * uninstall(Migration migration) : void -
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

    /**
     * Return dummy filterPattern for testing purposes
     *
     * @return array
     */
    public static function getDummyPattern() : array
    {
        return [self::NAME                => TestPattern,
                self::ACTIVE              => true,
                self::DATE_CREATION       => null,
                self::DATE_MOD            => null,
                self::TICKETMATCHSTR      => '/.*?(?<match>\(TESTMATCH-[0-9]{1,4}\)).*/',
                self::TICKETMATCHSTRLEN   => 16,
                self::ASSETMATCHSTR       => '/.*?(?<asset>\(TESTASSET-[0-9]{1,4}\)).*/',
                self::ASSETMATCHSTRLEN    => 16,
                self::SOLVEDMATCHSTR      => '/.*?(?<solved>\(TESTSOLVED-[0-9]{1,4}\)).*/',
                self::SOLVEDMATCHSTRLEN   => 18,
                self::AUTOMERGE           => true,
                self::REOPENCLOSED        => true,
                self::SEARCHBODY          => true,
                self::MATCHSOURCE         => true,
                self::SUPPRESNOTIF        => true];
    }

}
