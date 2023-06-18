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

/**
 * Summary of plugin_ticketFilter install
 * @return boolean
 * test
 */
function plugin_ticketfilter_install() : bool 
{
   return true;
}


/**
 * Summary of plugin_ticketFilter uninstall
 * @return boolean
 */
function plugin_ticketfilter_uninstall() : bool
{

   // nothing to uninstall
   // do not delete table

   return true;
}