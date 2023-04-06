<?php
/**
 * -------------------------------------------------------------------------
 * FilterTicket plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE 
 *
 * This file is part of Ticket Filter.
 *
 * Example is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Example is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Example. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2023 by NOVECMASTEN.
 * @author    Chris Gralike
 * @author    Ruben Bras
 * @license   MIT
 * @link      https://github.com/DonutsNL/ticketfilter
 * @link      https://github.com/pluginsGLPI/example/blob/develop/setup.php
 * 
 * -------------------------------------------------------------------------
 */

use Ticket;
use Glpi\Plugin\Hooks;
use GlpiPlugin\TicketFilter\TicketFilter;


define("PLUGIN_TICKETFILTER_VERSION", "1.0.0");


function plugin_init_ticketfilter()
{

   global $PLUGIN_HOOKS;

   Plugin::registerClass('ticketFilter');

   $PLUGIN_HOOKS['csrf_compliant']['ticketFilter'] = true;

   $PLUGIN_HOOKS[Hooks::PRE_ITEM_ADD]['ticketFilter'] = [
      Ticket::class => [ticketFilter::class, 'preItemAdd'],
   ];
}


/**
 * Summary of plugin_version_mailanalyzer
 * Get the name and the version of the plugin
 * @return array
 */
function plugin_version_ticketfilter()
{
   return [
      'name'         => __('Ticket Filter'),
      'version'      => PLUGIN_TICKETFILTER_VERSION,
      'author'       => 'Chris Gralike, Ruben Bras',
      'license'      => 'GPLv2+',
      'homepage'     => 'https://github.com/donutsnl/ticketfilter',
      'requirements' => [
         'glpi' => [
            'min' => '10.0',
            'max' => '10.1'
            ]
         ]
   ];
}

/**
 * Summary of plugin_ticketfilter_check_prerequisites
 * check prerequisites before install : may print errors or add to message after redirect
 * @return bool
 */
function plugin_ticketfilter_check_prerequisites() : bool
{
   if (version_compare(GLPI_VERSION, '10.0', 'lt')
       && version_compare(GLPI_VERSION, '10.1', 'ge')) {
      echo "This plugin requires GLPI >= 10.0 and < 10.1";
      return false;
   }else{
      return true;
   }
}

/**
 * Summary of plugin_mailanalyzer_check_config
 * @return bool
 */
function plugin_ticketfilter_check_config() 
{
   return true;
}

