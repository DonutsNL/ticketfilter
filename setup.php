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

use Glpi\Plugin\Hooks;
use GlpiPlugin\Ticketfilter\Audit\AuditLog;
use GlpiPlugin\Ticketfilter\Controller\TicketFilterController;
use GlpiPlugin\Ticketfilter\Cron\CleanLogsTask;
use GlpiPlugin\Ticketfilter\Engine\FilterEngine;
use GlpiPlugin\Ticketfilter\FilterPattern;

// Plugin constants
if (!defined('PLUGIN_TICKETFILTER_NAME')) {
    define('PLUGIN_TICKETFILTER_NAME', 'ticketfilter');
}
if (!defined('PLUGIN_TICKETFILTER_VERSION')) {
    define('PLUGIN_TICKETFILTER_VERSION', '2.0.0');
}
if (!defined('PLUGIN_TICKETFILTER_MIN_GLPI')) {
    define('PLUGIN_TICKETFILTER_MIN_GLPI', '11.0.0');
}
if (!defined('PLUGIN_TICKETFILTER_MAX_GLPI')) {
    define('PLUGIN_TICKETFILTER_MAX_GLPI', '12.9.99');
}

/**
 * Ordered list of fully-qualified class names for every plugin class that
 * implements a database lifecycle (install / uninstall).
 */
if (!defined('PLUGIN_TICKETFILTER_CLASSES')) {
    define('PLUGIN_TICKETFILTER_CLASSES', [
        'GlpiPlugin\\Ticketfilter\\FilterPattern',
        'GlpiPlugin\\Ticketfilter\\Audit\\AuditLog',
        'GlpiPlugin\\Ticketfilter\\Cron\\CleanLogsTask',
    ]);
}

// Include compatibility layer
require_once __DIR__ . '/src/compat.php';

/**
 * Initializes plugin hooks and registers services.
 *
 * @return void
 */
function plugin_init_ticketfilter(): void
{
    global $PLUGIN_HOOKS;

    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        include_once __DIR__ . '/vendor/autoload.php';
    }

    $plugin = new Plugin();
    if ($plugin->isInstalled(PLUGIN_TICKETFILTER_NAME) || $plugin->isActivated(PLUGIN_TICKETFILTER_NAME)) {
        Plugin::registerClass(TicketFilterController::class);
        Plugin::registerClass(FilterPattern::class);
        Plugin::registerClass(AuditLog::class);
        Plugin::registerClass(CleanLogsTask::class);
        Plugin::registerClass(FilterEngine::class);

        // Configuration page route handled by TicketFilterController
        $PLUGIN_HOOKS['config_page'][PLUGIN_TICKETFILTER_NAME] = TicketFilterController::CONFIG_ROUTE;

        // Add to config menu
        $PLUGIN_HOOKS['menu_toadd'][PLUGIN_TICKETFILTER_NAME]['config'] = [FilterPattern::class];

        // State plugin CSRF compliant
        $PLUGIN_HOOKS['csrf_compliant'][PLUGIN_TICKETFILTER_NAME] = true;

        // Hook on pre_item_add for tickets
        $PLUGIN_HOOKS[Hooks::PRE_ITEM_ADD][PLUGIN_TICKETFILTER_NAME] = [
            Ticket::class => [FilterEngine::class, 'preItemAdd']
        ];
    }
}

/**
 * Returns metadata and requirements for the plugin.
 *
 * @return array
 */
function plugin_version_ticketfilter(): array
{
    return [
        'name'         => 'TicketFilter',
        'version'      => PLUGIN_TICKETFILTER_VERSION,
        'author'       => 'Chris Gralike',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/DonutsNL/ticketfilter',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_TICKETFILTER_MIN_GLPI,
                'max' => PLUGIN_TICKETFILTER_MAX_GLPI,
            ],
            'php'  => [
                'min' => '8.0'
            ]
        ]
    ];
}

/**
 * Validates pre-requisites before install.
 *
 * @return bool
 */
function plugin_ticketfilter_check_prerequisites(): bool
{
    if (!extension_loaded('pcre')) {
        echo 'PCRE PHP extension is required.<br>';
        return false;
    }
    return true;
}

/**
 * Checks configuration status.
 *
 * @param bool $verbose Whether to display output.
 * @return bool
 */
function plugin_ticketfilter_check_config(bool $verbose = false): bool
{
    if ($verbose) {
        echo __('Installed and active.', 'ticketfilter');
    }
    return true;
}
