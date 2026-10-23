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

require_once __DIR__ . '/Shims.php';
require_once __DIR__ . '/../setup.php';
require_once __DIR__ . '/../hook.php';

use GlpiPlugin\Ticketfilter\Controller\TicketFilterController;
use GlpiPlugin\Ticketfilter\FilterPattern;

if (!function_exists('assertCondition')) {
    function assertCondition(bool $condition, string $description): void
    {
        if (!$condition) {
            throw new \Exception("Assertion failed: $description");
        }
        echo "  [PASS] $description\n";
    }
}

echo "Running PluginLifecycleTest...\n";

// 1. Verify constant definition
assertCondition(
    defined('PLUGIN_TICKETFILTER_NAME') && PLUGIN_TICKETFILTER_NAME === 'ticketfilter',
    'PLUGIN_TICKETFILTER_NAME is defined as ticketfilter'
);

// 2. Verify web dir computation
$webDir = ticketfilter_plugin_web_dir();
assertCondition(
    str_ends_with($webDir, '/plugins/ticketfilter'),
    "ticketfilter_plugin_web_dir() resolves to /plugins/ticketfilter (got: $webDir)"
);

// 3. Verify dropdown registration points to root namespace FilterPattern
$dropdowns = plugin_ticketfilter_getDropdown();
assertCondition(
    isset($dropdowns[FilterPattern::class]),
    'plugin_ticketfilter_getDropdown() registers GlpiPlugin\\Ticketfilter\\FilterPattern'
);
assertCondition(
    !isset($dropdowns['GlpiPlugin\\Ticketfilter\\Model\\FilterPattern']),
    'plugin_ticketfilter_getDropdown() does not register sub-namespaced Model\\FilterPattern'
);

// 4. Verify hooks registration under ticketfilter key
global $PLUGIN_HOOKS;
plugin_init_ticketfilter();

assertCondition(
    isset($PLUGIN_HOOKS['config_page']['ticketfilter']) &&
    $PLUGIN_HOOKS['config_page']['ticketfilter'] === TicketFilterController::CONFIG_ROUTE,
    'Config page hook is registered under ticketfilter key with front/config'
);

assertCondition(
    isset($PLUGIN_HOOKS['csrf_compliant']['ticketfilter']) &&
    $PLUGIN_HOOKS['csrf_compliant']['ticketfilter'] === true,
    'CSRF compliant hook is registered under ticketfilter key'
);

assertCondition(
    isset($PLUGIN_HOOKS['menu_toadd']['ticketfilter']['config']) &&
    $PLUGIN_HOOKS['menu_toadd']['ticketfilter']['config'] === [FilterPattern::class],
    'menu_toadd hook is registered under ticketfilter key with FilterPattern'
);

// 5. Verify FilterPattern menu title, direct destination page, and links
assertCondition(
    FilterPattern::getMenuName() === 'Ticket filter',
    'FilterPattern::getMenuName() returns Ticket filter'
);
$menuContent = FilterPattern::getMenuContent();
assertCondition(
    is_array($menuContent) && ($menuContent['title'] ?? '') === 'Ticket filter',
    'FilterPattern::getMenuContent() title is Ticket filter'
);
assertCondition(
    is_array($menuContent) && ($menuContent['page'] ?? '') === '/plugins/ticketfilter/front/config',
    'FilterPattern::getMenuContent() page links directly to /plugins/ticketfilter/front/config'
);
$additionalLinks = FilterPattern::getAdditionalMenuLinks();
assertCondition(
    in_array('/plugins/ticketfilter/front/config', $additionalLinks, true),
    'FilterPattern::getAdditionalMenuLinks() includes TicketFilter Dashboard link'
);

// 6. Verify TicketFilterController does not hijack front/filterpattern.php
$controllerRef = new ReflectionClass(TicketFilterController::class);
$configMethod = $controllerRef->getMethod('config');
$routeAttributes = $configMethod->getAttributes(\Symfony\Component\Routing\Attribute\Route::class);
$hasFilterpatternRoute = false;
foreach ($routeAttributes as $attr) {
    $args = $attr->getArguments();
    $path = $args[0] ?? ($args['path'] ?? '');
    if ($path === 'front/filterpattern.php') {
        $hasFilterpatternRoute = true;
    }
}
assertCondition(
    !$hasFilterpatternRoute,
    'TicketFilterController::config() does not hijack front/filterpattern.php route'
);

// 7. Verify resilience against pre-existing PLUGIN_NAME constant collision (e.g. from samlsso)
$phpCode = '
define("PLUGIN_NAME", "samlsso");
require_once "' . dirname(__DIR__) . '/setup.php";
require_once "' . dirname(__DIR__) . '/src/compat.php";
if (!defined("PLUGIN_TICKETFILTER_NAME") || PLUGIN_TICKETFILTER_NAME !== "ticketfilter") {
    exit(1);
}
if (!str_ends_with(ticketfilter_plugin_web_dir(), "/plugins/ticketfilter")) {
    exit(2);
}
exit(0);
';
exec('php -r ' . escapeshellarg($phpCode), $execOutput, $exitCode);
assertCondition(
    $exitCode === 0,
    'Resilient against pre-existing PLUGIN_NAME constant collision (e.g. from samlsso)'
);

// 8. Verify FilterPattern search and form URLs point directly to TicketFilter settings
assertCondition(
    str_ends_with(FilterPattern::getSearchURL(false), '/plugins/ticketfilter/front/config'),
    'FilterPattern::getSearchURL(false) resolves to /plugins/ticketfilter/front/config'
);
assertCondition(
    str_ends_with(FilterPattern::getFormURL(false), '/plugins/ticketfilter/front/config'),
    'FilterPattern::getFormURL(false) resolves to /plugins/ticketfilter/front/config'
);

// 9. Verify TicketFilterController pattern CRUD handlers
$controller = new TicketFilterController();
$reqGetInvalid = \Symfony\Component\HttpFoundation\Request::create('/front/config', 'GET', ['action' => 'get_pattern', 'id' => 0]);
$resGetInvalid = $controller->config($reqGetInvalid);
assertCondition(
    $resGetInvalid instanceof \Symfony\Component\HttpFoundation\JsonResponse,
    'TicketFilterController::config() returns JsonResponse on get_pattern action'
);
$dataGetInvalid = json_decode((string)$resGetInvalid->getContent(), true);
assertCondition(
    isset($dataGetInvalid['success']) && $dataGetInvalid['success'] === false,
    'get_pattern with id=0 returns success=false'
);

// Verify save_pattern validation (missing name)
$reqSaveNoName = \Symfony\Component\HttpFoundation\Request::create('/front/config', 'POST', [
    'action'            => 'save_pattern',
    'name'              => '',
    'TicketMatchString' => '/(?<match>[0-9]+)/',
]);
$resSaveNoName = $controller->config($reqSaveNoName);
$dataSaveNoName = json_decode((string)$resSaveNoName->getContent(), true);
assertCondition(
    isset($dataSaveNoName['success']) && $dataSaveNoName['success'] === false,
    'save_pattern with empty name returns success=false'
);

// Verify save_pattern validation (missing (?<match>...) named capture group)
$reqSaveNoGroup = \Symfony\Component\HttpFoundation\Request::create('/front/config', 'POST', [
    'action'            => 'save_pattern',
    'name'              => 'Test Pattern',
    'TicketMatchString' => '/[0-9]+/',
]);
$resSaveNoGroup = $controller->config($reqSaveNoGroup);
$dataSaveNoGroup = json_decode((string)$resSaveNoGroup->getContent(), true);
assertCondition(
    isset($dataSaveNoGroup['success']) && $dataSaveNoGroup['success'] === false,
    'save_pattern without (?<match>...) named group returns success=false'
);

// Verify delete_pattern validation (invalid id)
$reqDelInvalid = \Symfony\Component\HttpFoundation\Request::create('/front/config', 'POST', [
    'action' => 'delete_pattern',
    'id'     => 0,
]);
$resDelInvalid = $controller->config($reqDelInvalid);
$dataDelInvalid = json_decode((string)$resDelInvalid->getContent(), true);
assertCondition(
    isset($dataDelInvalid['success']) && $dataDelInvalid['success'] === false,
    'delete_pattern with id=0 returns success=false'
);

// Verify CSRF token is included in JSON responses when token-based CSRF is active (GLPI 11)
assertCondition(
    isset($dataGetInvalid['csrf_token']) && $dataGetInvalid['csrf_token'] === 'mock_csrf_token_glpi11',
    'get_pattern JSON response includes fresh csrf_token'
);
assertCondition(
    isset($dataSaveNoName['csrf_token']) && $dataSaveNoName['csrf_token'] === 'mock_csrf_token_glpi11',
    'save_pattern JSON response includes fresh csrf_token'
);
assertCondition(
    isset($dataDelInvalid['csrf_token']) && $dataDelInvalid['csrf_token'] === 'mock_csrf_token_glpi11',
    'delete_pattern JSON response includes fresh csrf_token'
);

// 10. Verify TicketProcessor intervention message formatting & non-repudiation placeholders
$customTemplate = 'Ticket #{ticket_id} reopened by rule {pattern_name} (ID: {pattern_id}) - match: {match_token}, sender: {sender_email}, subject: "{subject}"';
$formatted = \GlpiPlugin\Ticketfilter\Engine\TicketProcessor::formatInterventionMessage(
    $customTemplate,
    \GlpiPlugin\Ticketfilter\Engine\TicketProcessor::DEFAULT_REOPEN_TEMPLATE,
    [
        '{pattern_name}' => 'Jira Pattern',
        '{pattern_id}'   => '42',
        '{match_token}'  => 'JIRA-1234',
        '{sender_email}' => 'auditor@example.com',
        '{subject}'      => 'Urgent fix needed',
        '{ticket_id}'    => '999',
    ]
);
assertCondition(
    $formatted === 'Ticket #999 reopened by rule Jira Pattern (ID: 42) - match: JIRA-1234, sender: auditor@example.com, subject: "Urgent fix needed"',
    'formatInterventionMessage correctly substitutes all custom placeholders'
);

// Fallback to default reopen template when template is empty
$defaultReopenFormatted = \GlpiPlugin\Ticketfilter\Engine\TicketProcessor::formatInterventionMessage(
    '',
    \GlpiPlugin\Ticketfilter\Engine\TicketProcessor::DEFAULT_REOPEN_TEMPLATE,
    [
        '{pattern_name}' => 'Jira Pattern',
        '{pattern_id}'   => '42',
        '{match_token}'  => 'JIRA-1234',
        '{sender_email}' => 'auditor@example.com',
        '{subject}'      => 'Urgent fix needed',
        '{ticket_id}'    => '999',
    ]
);
assertCondition(
    str_contains($defaultReopenFormatted, 'Jira Pattern') && str_contains($defaultReopenFormatted, 'JIRA-1234') && str_contains($defaultReopenFormatted, 'auditor@example.com'),
    'formatInterventionMessage falls back to default reopen message with audit details when blank'
);

// Fallback to default solved template when template is empty
$defaultSolvedFormatted = \GlpiPlugin\Ticketfilter\Engine\TicketProcessor::formatInterventionMessage(
    '',
    \GlpiPlugin\Ticketfilter\Engine\TicketProcessor::DEFAULT_SOLVED_TEMPLATE,
    [
        '{pattern_name}' => 'Jira Pattern',
        '{pattern_id}'   => '42',
        '{match_token}'  => 'JIRA-1234',
        '{solved_token}' => 'RESOLVED',
        '{sender_email}' => 'auditor@example.com',
        '{subject}'      => 'Urgent fix needed [RESOLVED]',
        '{ticket_id}'    => '999',
    ]
);
assertCondition(
    str_contains($defaultSolvedFormatted, 'RESOLVED') && str_contains($defaultSolvedFormatted, 'Jira Pattern'),
    'formatInterventionMessage falls back to default solved message with solved token when blank'
);

// Verify FilterPattern template field constants and schema resilience
assertCondition(
    defined(FilterPattern::class . '::FIELD_REOPEN_MESSAGE_TEMPLATE') && FilterPattern::FIELD_REOPEN_MESSAGE_TEMPLATE === 'reopen_message_template',
    'FIELD_REOPEN_MESSAGE_TEMPLATE constant is defined'
);
assertCondition(
    defined(FilterPattern::class . '::FIELD_SOLVED_MESSAGE_TEMPLATE') && FilterPattern::FIELD_SOLVED_MESSAGE_TEMPLATE === 'solved_message_template',
    'FIELD_SOLVED_MESSAGE_TEMPLATE constant is defined'
);
assertCondition(FilterPattern::$rightname === 'config', 'FilterPattern::$rightname must be config');
assertCondition(defined(FilterPattern::class . '::FIELD_APPLY_TO_MAILGATE') && FilterPattern::FIELD_APPLY_TO_MAILGATE === 'apply_to_mailgate', 'FIELD_APPLY_TO_MAILGATE must be defined');
assertCondition(defined(FilterPattern::class . '::FIELD_APPLY_TO_API') && FilterPattern::FIELD_APPLY_TO_API === 'apply_to_api', 'FIELD_APPLY_TO_API must be defined');
assertCondition(defined(FilterPattern::class . '::FIELD_APPLY_TO_WEB') && FilterPattern::FIELD_APPLY_TO_WEB === 'apply_to_web', 'FIELD_APPLY_TO_WEB must be defined');
assertCondition(defined(FilterPattern::class . '::FIELD_ENABLE_AUTO_SOLVE') && FilterPattern::FIELD_ENABLE_AUTO_SOLVE === 'enable_auto_solve', 'FIELD_ENABLE_AUTO_SOLVE must be defined');
assertCondition(defined(FilterPattern::class . '::FIELD_AUTO_SOLVE_STATUS') && FilterPattern::FIELD_AUTO_SOLVE_STATUS === 'auto_solve_status', 'FIELD_AUTO_SOLVE_STATUS must be defined');
FilterPattern::ensureSchema();

echo "PluginLifecycleTest completed successfully!\n\n";



