<?php
/**
 *  ------------------------------------------------------------------------
 *  Chris Gralike, Ruben Bras - Ticket Filter
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
 *  @package  	   TicketFilter
 *  @version	   1.1.0
 *  @author    	Chris Gralike
 *  @copyright 	Copyright (c) 2023 by Chris Gralike
 *  @license   	GPLv2+
 *  @see       	https://github.com/DonutsNL/ticketfilter/readme.md
 *  @link		   https://github.com/DonutsNL/ticketfilter
 *  @since     	1.0
 * ------------------------------------------------------------------------
 **/
use Glpi\Plugin\Hooks;
use GlpiPlugin\Ticketfilter\Filter;
use GlpiPlugin\Ticketfilter\Filterpatern;

// Maximum GLPI version, exclusive
// Minimal GLPI version, inclusive
define('PLUGIN_TICKETFILTER_VERSION', '1.0.4');
define('PLUGIN_TICKETFILTER_MIN_GLPI', '10.0.0');
define('PLUGIN_TICKETFILTER_MAX_GLPI', '10.0.99');

/**
 * Init hooks of the plugin.
 *
 * @return void
 */
function plugin_init_ticketfilter() : void
{
   global $PLUGIN_HOOKS;

   Plugin::registerClass(Filter::class);
   Plugin::registerClass(FilterPatern::class);

   // Config page: redirect to dropdown page
   $PLUGIN_HOOKS['config_page']['ticketfilter'] = 'front/filterpatern.php';

   // State this plugin cross-site request forgery compliant
   $PLUGIN_HOOKS['csrf_compliant']['ticketfilter'] = true;

   // Add hook (callback) on the PRE_ITEM_ADD event.
   // We assume that only new tickets are potential duplicates if the
   // source ticket system is not adding the GLPI identifier.
   $PLUGIN_HOOKS[HOOKS::PRE_ITEM_ADD]['ticketfilter'] = [
      Ticket::class       => [Filter::class, 'PreItemAdd']
   ];
}


/**
 * Get the name and the version of the plugin
 *
 * @return array
 */
function plugin_version_ticketfilter() : array
{
   return [
      'name'           => 'Plugin TICKETFILTER',
      'version'        => PLUGIN_TICKETFILTER_VERSION,
      'author'         => 'TICKETFILTER plugin team',
      'license'        => 'GPLv2+',
      'homepage'       => 'https://github.com/DonutsNL/ticketfilter',
      'requirements'   => [
         'glpi' => [
            'min' => PLUGIN_TICKETFILTER_MIN_GLPI,
            'max' => PLUGIN_TICKETFILTER_MAX_GLPI,
         ]
      ]
   ];
}


/**
 * Check pre-requisites before install
 * @return boolean
 */
function plugin_ticketfilter_check_prerequisites() : bool
{
   if (false) {
      return false;
   }
   return true;
}

/**
 * Check configuration process
 *
 * @param boolean $verbose Whether to display message on failure. Defaults to false
 * @return boolean
 */
function plugin_ticketfilter_check_config($verbose = false) : bool
{
   if (true) { // Your configuration check
      return true;
   }

   if ($verbose) {
      echo __('Installed / not configured', 'TICKETFILTER');
   }
   return false;
}
