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
 *  @version	   1.0.0
 *  @author    	Chris Gralike
 *  @copyright 	Copyright (c) 2023 by Chris Gralike
 *  @license   	MIT
 *  @see       	https://github.com/DonutsNL/ticketfilter/readme.md
 *  @link		   https://github.com/DonutsNL/ticketfilter
 *  @since     	0.1
 * ------------------------------------------------------------------------
 **/

<<<<<<< HEAD
use GlpiPlugin\Ticketfilter\Configs;
=======
use GlpiPlugin\Ticketfilter\Config;
>>>>>>> 85292ed (In development)
/**
 * Summary of plugin_ticketFilter install
 * @return boolean
 * test
 */
// phpcs:ignore PSR1.Function.CamelCapsMethodName
function plugin_ticketfilter_install() : bool
{
<<<<<<< HEAD
   /*
=======
   
>>>>>>> 85292ed (In development)
   ProfileRight::addProfileRights(['ticketfilter:read']);
   ProfileRight::addProfileRights(['ticketfilter:create']);
   ProfileRight::addProfileRights(['ticketfilter:update']);
   ProfileRight::addProfileRights(['ticketfilter:purge']);
<<<<<<< HEAD
   */

   if (method_exists(Configs::class, 'install')) {
      $version   = plugin_version_ticketfilter();
      $migration = new Migration($version['version']);
      Configs::install($migration);
=======
   

   if (method_exists(Config::class, 'install')) {
      $version   = plugin_version_ticketfilter();
      $migration = new Migration($version['version']);
      Config::install($migration);
>>>>>>> 85292ed (In development)
   }
   return true;
}


/**
 * 
 * Summary of plugin_ticketFilter uninstall
 * @return boolean
 */
// phpcs:ignore PSR1.Function.CamelCapsMethodName
function plugin_ticketfilter_uninstall() : bool
{
<<<<<<< HEAD
   /*
=======
   
>>>>>>> 85292ed (In development)
   ProfileRight::deleteProfileRights(['ticketfilter:read']);
   ProfileRight::deleteProfileRights(['ticketfilter:create']);
   ProfileRight::deleteProfileRights(['ticketfilter:update']);
   ProfileRight::deleteProfileRights(['ticketfilter:purge']);
<<<<<<< HEAD
   */

   if (method_exists(Configs::class, 'uninstall')) {
      $version   = plugin_version_ticketfilter();
      $migration = new Migration($version['version']);
      Configs::uninstall($migration);
=======
   

   if (method_exists(Config::class, 'uninstall')) {
      $version   = plugin_version_ticketfilter();
      $migration = new Migration($version['version']);
      Config::uninstall($migration);
>>>>>>> 85292ed (In development)
   }
   return true;
}