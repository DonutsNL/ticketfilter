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

/**
 * Returns the web path of the plugin, relative to the GLPI base URI.
 *
 * GLPI 12 removed Plugin::getWebDir() and mandates that all plugin URLs
 * start with '/plugins', for plugins installed in 'plugins' as well as
 * in 'marketplace'. On GLPI 11 the core method still exists and handles
 * both install locations, so it is preferred when available.
 *
 * @return string Web path without trailing slash, e.g. "/glpi/plugins/ticketfilter".
 */
function ticketfilter_plugin_web_dir(): string
{
    global $CFG_GLPI;

    $pluginName = defined('PLUGIN_TICKETFILTER_NAME') ? PLUGIN_TICKETFILTER_NAME : 'ticketfilter';

    if (method_exists('Plugin', 'getWebDir')) {
        $webDir = \Plugin::getWebDir($pluginName);
        if (is_string($webDir) && $webDir !== '') {
            return $webDir;
        }
    }

    $rootDoc = (isset($CFG_GLPI['root_doc'])) ? (string)$CFG_GLPI['root_doc'] : '';
    return $rootDoc . '/plugins/' . $pluginName;
}

/**
 * Returns the absolute plugin web base URL, computed at call time.
 *
 * Evaluated at runtime to ensure 'url_base' from GLPI configuration is respected
 * across both GLPI 11 and GLPI 12.
 *
 * @return string Absolute base URL of the plugin, with a trailing slash.
 */
function ticketfilter_webdir(): string
{
    global $CFG_GLPI;
    $urlBase = (isset($CFG_GLPI['url_base'])) ? rtrim((string)$CFG_GLPI['url_base'], '/') : '';
    return $urlBase . ticketfilter_plugin_web_dir() . '/';
}

/**
 * Clears GLPI caches safely across GLPI 11 and GLPI 12.
 *
 * @return void
 */
function ticketfilter_reset_cache(): void
{
    if (class_exists(\Glpi\Cache\CacheManager::class)) {
        (new \Glpi\Cache\CacheManager())->resetAllCaches();
    }
}
