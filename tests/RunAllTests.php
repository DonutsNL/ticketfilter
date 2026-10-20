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

$testDir = __DIR__;
$testFiles = [
    'PluginLifecycleTest.php',
    'PatternMatcherTest.php',
    'SimulatorTest.php',
    'SecurityIsolationTest.php',
];

echo "====================================================\n";
echo "TicketFilter Automated Test Suite (GLPI 11/12)\n";
echo "====================================================\n\n";

$allPassed = true;
foreach ($testFiles as $file) {
    $filePath = $testDir . '/' . $file;
    if (file_exists($filePath)) {
        try {
            require_once $filePath;
        } catch (\Throwable $e) {
            echo "  [FAIL] In $file: " . $e->getMessage() . "\n";
            $allPassed = false;
        }
    }
}

echo "====================================================\n";
if ($allPassed) {
    echo "RESULT: ALL TESTS PASSED SUCCESSFULLY! (100%)\n";
    echo "====================================================\n";
    exit(0);
} else {
    echo "RESULT: SOME TESTS FAILED.\n";
    echo "====================================================\n";
    exit(1);
}
