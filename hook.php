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

use GlpiPlugin\Ticketfilter\FilterPattern;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    include_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Registers plugin dropdown in Setup > Dropdowns.
 *
 * @return array
 */
function plugin_ticketfilter_getDropdown(): array
{
    return [FilterPattern::class => __('Ticket filter', 'ticketfilter')];
}

/**
 * Installs plugin tables and registers scheduled tasks.
 *
 * @return bool
 */
function plugin_ticketfilter_install(): bool
{
    $version   = plugin_version_ticketfilter();
    $migration = new Migration($version['version']);

    Session::addMessageAfterRedirect(
        sprintf(__('Installing TicketFilter version %s', 'ticketfilter'), PLUGIN_TICKETFILTER_VERSION),
        false,
        INFO
    );

    foreach (PLUGIN_TICKETFILTER_CLASSES as $class) {
        if (class_exists($class) && method_exists($class, 'install')) {
            $class::install($migration);
        }
    }

    if (function_exists('ticketfilter_reset_cache')) {
        ticketfilter_reset_cache();
    }

    return true;
}

/**
 * Uninstalls plugin tables and unregisters scheduled tasks.
 *
 * @return bool
 */
function plugin_ticketfilter_uninstall(): bool
{
    $version   = plugin_version_ticketfilter();
    $migration = new Migration($version['version']);

    Session::addMessageAfterRedirect(
        __('Uninstalling TicketFilter plugin', 'ticketfilter'),
        false,
        INFO
    );

    $reversed = array_reverse(PLUGIN_TICKETFILTER_CLASSES);
    foreach ($reversed as $class) {
        if (class_exists($class) && method_exists($class, 'uninstall')) {
            $class::uninstall($migration);
        }
    }

    if (function_exists('ticketfilter_reset_cache')) {
        ticketfilter_reset_cache();
    }

    return true;
}