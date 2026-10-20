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
require_once __DIR__ . '/../src/Model/FilterPattern.php';
require_once __DIR__ . '/../src/Engine/PatternMatcher.php';
require_once __DIR__ . '/../src/Engine/TicketProcessor.php';
require_once __DIR__ . '/../src/Engine/Simulator.php';

use GlpiPlugin\Ticketfilter\Engine\Simulator;

if (!function_exists('assertCondition')) {
    function assertCondition(bool $condition, string $description): void
    {
        if (!$condition) {
            throw new \Exception("Assertion failed: $description");
        }
        echo "  [PASS] $description\n";
    }
}

echo "Running SimulatorTest...\n";

// 1. Test sample match simulation
$pattern = '/.*?(?<match>\(JIRA-[0-9]+\)).*/i';
$subject = 'Re: (JIRA-5555) Outage update';
$solvedPattern = '/.*?(?<solved>Resolved|Closed).*/i';

$res = Simulator::simulateSample($pattern, $subject, '', 'tech@domain.com', $solvedPattern);
assertCondition($res['success'] === true, 'Simulator succeeds on valid pattern');
assertCondition($res['matched'] === true, 'Simulator confirms match');
assertCondition($res['token'] === '(JIRA-5555)', 'Simulator extracts token');

// 2. Test sample non-match simulation
$resNoMatch = Simulator::simulateSample($pattern, 'General inquiry with no issue key');
assertCondition($resNoMatch['success'] === true, 'Simulator succeeds on non-match');
assertCondition($resNoMatch['matched'] === false, 'Simulator reports no match');
assertCondition($resNoMatch['simulated_action'] === 'NO_MATCH', 'Simulator returns NO_MATCH action');

// 3. Test invalid regex in simulator
$resInvalid = Simulator::simulateSample('/(?<match>[0-9+/', 'test subject');
assertCondition($resInvalid['success'] === false, 'Simulator detects invalid pattern');
assertCondition($resInvalid['simulated_action'] === 'INVALID_REGEX', 'Simulator returns INVALID_REGEX');
assertCondition($resInvalid['error'] !== null, 'Simulator provides error message');

echo "SimulatorTest completed successfully!\n\n";
