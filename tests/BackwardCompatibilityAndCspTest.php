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
 *  @version    2.0.3
 *  @author     Chris Gralike
 *  @copyright  Copyright (c) 2023-2026 by Chris Gralike
 *  @license    GPLv3+
 *  @link       https://github.com/DonutsNL/ticketfilter
 *  ------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/Shims.php';
require_once __DIR__ . '/../setup.php';

if (!function_exists('assertCondition')) {
    function assertCondition(bool $condition, string $description): void
    {
        if (!$condition) {
            throw new \Exception("Assertion failed: $description");
        }
        echo "  [PASS] $description\n";
    }
}

echo "Running BackwardCompatibilityAndCspTest...\n";

$pluginRoot = dirname(__DIR__);

// 1. Verify plugin_version_ticketfilter() declares GLPI CSP compliance and GLPI 11/12 support
$versionInfo = plugin_version_ticketfilter();
assertCondition(
    isset($versionInfo['csp_compliant']) && $versionInfo['csp_compliant'] === true,
    'plugin_version_ticketfilter() declares csp_compliant => true'
);
assertCondition(
    isset($versionInfo['requirements']['glpi']['min'], $versionInfo['requirements']['glpi']['max'])
        && version_compare((string) $versionInfo['requirements']['glpi']['min'], '11.0.0', '<=')
        && version_compare((string) $versionInfo['requirements']['glpi']['max'], '12.0.0', '>='),
    'plugin_version_ticketfilter() declares backward-compatible GLPI 11 and GLPI 12 version range'
);

// 2. Verify isolated GLPI 11 runtime compatibility (untyped $rightname + bitwise int Session::haveRight())
$glpi11Probe = <<<'PHP'
declare(strict_types=1);
define('GLPI_VERSION', '11.0.5');
define('READ', 1);
define('UPDATE', 2);
class CommonDBTM {}
class CommonDropdown extends CommonDBTM {
    public static $rightname = 'dropdown';
}
class Session {
    public static function haveRight($module, $right) {
        return intval($_SESSION['glpiactiveprofile'][$module] ?? 0) & $right;
    }
}
$_SESSION['glpiactiveprofile']['config'] = 3;
require_once $argv[1] . '/src/Model/FilterPattern.php';
require_once $argv[1] . '/src/FilterPattern.php';
$ref = new ReflectionProperty(\GlpiPlugin\Ticketfilter\Model\FilterPattern::class, 'rightname');
if ($ref->hasType()) {
    fwrite(STDERR, "Expected untyped \$rightname on GLPI 11\n");
    exit(1);
}
$fp = new \GlpiPlugin\Ticketfilter\FilterPattern();
if (
    \GlpiPlugin\Ticketfilter\FilterPattern::canView() !== true
    || \GlpiPlugin\Ticketfilter\FilterPattern::canCreate() !== true
    || \GlpiPlugin\Ticketfilter\FilterPattern::canUpdate() !== true
    || \GlpiPlugin\Ticketfilter\FilterPattern::canDelete() !== true
    || \GlpiPlugin\Ticketfilter\FilterPattern::canPurge() !== true
    || $fp->canUpdateItem() !== true
    || $fp->canPurgeItem() !== true
    || $fp->canDeleteItem() !== true
) {
    fwrite(STDERR, "GLPI 11 right checks did not return strict bool true\n");
    exit(2);
}
$_SESSION['glpiactiveprofile']['config'] = 0;
if (
    \GlpiPlugin\Ticketfilter\FilterPattern::canView() !== false
    || \GlpiPlugin\Ticketfilter\FilterPattern::canCreate() !== false
    || \GlpiPlugin\Ticketfilter\FilterPattern::canUpdate() !== false
    || \GlpiPlugin\Ticketfilter\FilterPattern::canDelete() !== false
    || \GlpiPlugin\Ticketfilter\FilterPattern::canPurge() !== false
    || $fp->canUpdateItem() !== false
    || $fp->canPurgeItem() !== false
    || $fp->canDeleteItem() !== false
) {
    fwrite(STDERR, "GLPI 11 zero-right checks did not return strict bool false\n");
    exit(3);
}
exit(0);
PHP;

$out11 = [];
$rc11  = 0;
exec('php -r ' . escapeshellarg($glpi11Probe) . ' -- ' . escapeshellarg($pluginRoot) . ' 2>&1', $out11, $rc11);
assertCondition(
    $rc11 === 0,
    'Isolated GLPI 11 probe passes (untyped $rightname and bitwise int Session::haveRight() under strict_types=1)'
        . ($rc11 !== 0 ? ' [output: ' . implode(' | ', $out11) . ']' : '')
);

// 3. Verify isolated GLPI 12 runtime compatibility (typed string $rightname + bool Session::haveRight())
$glpi12Probe = <<<'PHP'
declare(strict_types=1);
define('GLPI_VERSION', '12.0.0');
define('READ', 1);
define('UPDATE', 2);
class CommonDBTM {}
class CommonDropdown extends CommonDBTM {
    public static string $rightname = 'dropdown';
}
class Session {
    public static function haveRight(string $module, int $right): bool {
        return (bool) ((int) ($_SESSION['glpiactiveprofile'][$module] ?? 0) & $right);
    }
}
$_SESSION['glpiactiveprofile']['config'] = 3;
require_once $argv[1] . '/src/Model/FilterPattern.php';
require_once $argv[1] . '/src/FilterPattern.php';
$ref = new ReflectionProperty(\GlpiPlugin\Ticketfilter\Model\FilterPattern::class, 'rightname');
$type = $ref->getType();
if (!$type instanceof ReflectionNamedType || $type->getName() !== 'string') {
    fwrite(STDERR, "Expected typed string \$rightname on GLPI 12\n");
    exit(1);
}
$fp = new \GlpiPlugin\Ticketfilter\FilterPattern();
if (
    \GlpiPlugin\Ticketfilter\FilterPattern::canView() !== true
    || \GlpiPlugin\Ticketfilter\FilterPattern::canCreate() !== true
    || \GlpiPlugin\Ticketfilter\FilterPattern::canUpdate() !== true
    || \GlpiPlugin\Ticketfilter\FilterPattern::canDelete() !== true
    || \GlpiPlugin\Ticketfilter\FilterPattern::canPurge() !== true
    || $fp->canUpdateItem() !== true
    || $fp->canPurgeItem() !== true
    || $fp->canDeleteItem() !== true
) {
    fwrite(STDERR, "GLPI 12 right checks did not return strict bool true\n");
    exit(2);
}
exit(0);
PHP;

$out12 = [];
$rc12  = 0;
exec('php -r ' . escapeshellarg($glpi12Probe) . ' -- ' . escapeshellarg($pluginRoot) . ' 2>&1', $out12, $rc12);
assertCondition(
    $rc12 === 0,
    'Isolated GLPI 12 probe passes (typed string $rightname and bool Session::haveRight() under strict_types=1)'
        . ($rc12 !== 0 ? ' [output: ' . implode(' | ', $out12) . ']' : '')
);

// 4. Verify backward-compatible facade classes remain loadable
assertCondition(class_exists(\GlpiPlugin\Ticketfilter\Filter::class), 'Backward-compatible facade GlpiPlugin\\Ticketfilter\\Filter exists');
assertCondition(class_exists(\GlpiPlugin\Ticketfilter\FilterPattern::class), 'Backward-compatible facade GlpiPlugin\\Ticketfilter\\FilterPattern exists');
assertCondition(class_exists(\GlpiPlugin\Ticketfilter\TicketHandler::class), 'Backward-compatible facade GlpiPlugin\\Ticketfilter\\TicketHandler exists');

// 5. Static scan: enforce zero CSP violations across all templates and production PHP files
$templateViolations = [];
$templateIter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pluginRoot . '/templates', FilesystemIterator::SKIP_DOTS)
);
$inlineHandlerRegex = '/\bon(?:click|dblclick|mousedown|mouseup|mouseover|mouseout|change|input|submit|reset|focus|blur|keyup|keydown|keypress|load|error)\s*=/i';
$jsUrlRegex         = '/javascript\s*:/i';
$unsafeDomSinkRegex = '/\.(?:innerHTML|outerHTML)\s*=|insertAdjacentHTML\s*\(|document\.write\s*\(/i';

foreach ($templateIter as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), '.twig')) {
        continue;
    }
    $relPath = ltrim(str_replace($pluginRoot, '', $file->getPathname()), '/');
    $lines   = file($file->getPathname(), FILE_IGNORE_NEW_LINES) ?: [];

    foreach ($lines as $idx => $line) {
        $lineNo = $idx + 1;
        if (preg_match($inlineHandlerRegex, $line)) {
            $templateViolations[] = "$relPath:$lineNo contains inline event handler";
        }
        if (preg_match($jsUrlRegex, $line)) {
            $templateViolations[] = "$relPath:$lineNo contains javascript: URL";
        }
        if (preg_match($unsafeDomSinkRegex, $line)) {
            $templateViolations[] = "$relPath:$lineNo contains unsafe DOM HTML sink";
        }
    }
}

assertCondition(
    count($templateViolations) === 0,
    'All Twig templates are 100% CSP-compliant (zero inline handlers, zero javascript: URLs, zero innerHTML sinks)'
        . (count($templateViolations) > 0 ? ' [' . implode('; ', $templateViolations) . ']' : '')
);

// 6. Static scan: enforce zero PHP-emitted <script> tags, inline handlers, or uncast Session::haveRight() returns
$phpViolations = [];
$phpFiles = [
    $pluginRoot . '/setup.php',
    $pluginRoot . '/hook.php',
];
$srcIter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pluginRoot . '/src', FilesystemIterator::SKIP_DOTS)
);
foreach ($srcIter as $file) {
    if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
        $phpFiles[] = $file->getPathname();
    }
}

foreach ($phpFiles as $phpPath) {
    $relPath = ltrim(str_replace($pluginRoot, '', $phpPath), '/');
    $lines   = file($phpPath, FILE_IGNORE_NEW_LINES) ?: [];

    foreach ($lines as $idx => $line) {
        $lineNo = $idx + 1;
        if (preg_match('/<\s*script\b/i', $line)) {
            $phpViolations[] = "$relPath:$lineNo prints raw <script> tag from PHP";
        }
        if (preg_match($inlineHandlerRegex, $line)) {
            $phpViolations[] = "$relPath:$lineNo contains inline event handler in PHP";
        }
        if (preg_match($jsUrlRegex, $line)) {
            $phpViolations[] = "$relPath:$lineNo contains javascript: URL in PHP";
        }
        if (preg_match('/return\s+\\\\?Session::haveRight\s*\(/i', $line)) {
            $phpViolations[] = "$relPath:$lineNo returns Session::haveRight() without (bool) cast";
        }
    }
}

assertCondition(
    count($phpViolations) === 0,
    'All production PHP files pass CSP and GLPI 11 strict_types static checks'
        . (count($phpViolations) > 0 ? ' [' . implode('; ', $phpViolations) . ']' : '')
);

echo "BackwardCompatibilityAndCspTest completed successfully!\n\n";
