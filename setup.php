<?php
/**
 * -------------------------------------------------------------------------
 * TicketFilter plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of TicketFilter.
 *
 * TicketFilter is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * TicketFilter is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with TicketFilter. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2006-2022 by TicketFilter plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/TicketFilter
 * -------------------------------------------------------------------------
 */

use Ticket;
use MailCollector;
use Glpi\Plugin\Hooks;
use GlpiPlugin\ticketfilter\TicketFilter;

define('PLUGIN_TICKETFILTER_VERSION', '1.0.0');
// Minimal GLPI version, inclusive
define('PLUGIN_TICKETFILTER_MIN_GLPI', '10.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_TICKETFILTER_MAX_GLPI', '10.0.99');

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_ticketfilter() {
   global $PLUGIN_HOOKS,$CFG_GLPI;

   // Register our class; 
   Plugin::registerClass('ticketFilter');

   // State this plugin cross-site request forgery compliant
   $PLUGIN_HOOKS['csrf_compliant']['ticketfilter'] = true;

   // Add hook (callback) on the PRE_ITEM_ADD event.
   $PLUGIN_HOOKS[Hooks::PRE_ITEM_ADD]['ticketfilter'] = [
      Ticket::class => [ticketFilter::class, 'preItemAdd'],
   ];

   $PLUGIN_HOOKS['item_add']['ticketfilter'] = [
      Ticket::class => [ticketFilter::class, 'preItemAdd']
   ];

   $PLUGIN_HOOKS['item_purge']['ticketfilter'] = [
      Ticket::class => [ticketFilter::class, 'preItemAdd']
   ];

}


/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_ticketfilter() {
   return [
      'name'           => 'Plugin TICKETFILTER',
      'version'        => PLUGIN_TICKETFILTER_VERSION,
      'author'         => 'TICKETFILTER plugin team',
      'license'        => 'GPLv2+',
      'homepage'       => '',
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
 * OPTIONNAL, but recommanded
 *
 * @return boolean
 */
function plugin_ticketfilter_check_prerequisites() {
   if (false) {
      return false;
   }
   return true;
}

/**
 * Check configuration process
 *
 * @param boolean $verbose Whether to display message on failure. Defaults to false
 *
 * @return boolean
 */
function plugin_ticketfilter_check_config($verbose = false) {
   if (true) { // Your configuration check
      return true;
   }

   if ($verbose) {
      echo __('Installed / not configured', 'TICKETFILTER');
   }
   return false;
}