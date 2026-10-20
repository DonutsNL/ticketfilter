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
require_once __DIR__ . '/../src/Engine/PatternMatcher.php';

use GlpiPlugin\Ticketfilter\Engine\PatternMatcher;

if (!function_exists('assertCondition')) {
    function assertCondition(bool $condition, string $description): void
    {
        if (!$condition) {
            throw new \Exception("Assertion failed: $description");
        }
        echo "  [PASS] $description\n";
    }
}

echo "Running PatternMatcherTest...\n";

// 1. Test normal matching with delimiters
$pattern = '/.*?(?<match>\(JIRA-[0-9]+\)).*/i';
$subject = 'Issue update for (JIRA-1042) in staging';
$res = PatternMatcher::match($pattern, $subject);
assertCondition($res['matched'] === true, 'Matches Jira ticket identifier');
assertCondition($res['token'] === '(JIRA-1042)', 'Extracts exact named token');
assertCondition($res['error'] === null, 'No error on successful match');

// 2. Test delimiter auto-wrapping
$rawNoDelim = '(?<match>[A-Z]{3}-[0-9]{4})';
$resAuto = PatternMatcher::match($rawNoDelim, 'Case ABC-1234 details');
assertCondition($resAuto['matched'] === true, 'Auto-wraps pattern without delimiters');
assertCondition($resAuto['token'] === 'ABC-1234', 'Extracts token from auto-wrapped pattern');

// 3. Test non-match (VERIFY FIX FOR THE REPORTED BUG: MUST NOT ERROR ON NON-MATCH)
$subjectUnrelated = 'Unrelated email about team lunch';
$resNonMatch = PatternMatcher::match($pattern, $subjectUnrelated);
assertCondition($resNonMatch['matched'] === false, 'Non-matching email returns matched=false');
assertCondition($resNonMatch['token'] === null, 'Non-matching email returns token=null');
assertCondition($resNonMatch['error'] === null, 'Non-matching email MUST NOT produce an error (Fix for preg_match bug)');

// 4. Test solved pattern matching
$solvedPattern = '/.*?(?<solved>Closed|Resolved).*/i';
$solvedSubject = 'Re: (JIRA-1042) Issue Resolved';
$resSolved = PatternMatcher::match($solvedPattern, $solvedSubject, PatternMatcher::GROUP_SOLVED);
assertCondition($resSolved['matched'] === true, 'Matches solved pattern');
assertCondition($resSolved['token'] === 'Resolved', 'Extracts solved keyword');

// 5. Test solved pattern non-match (MUST NOT ERROR)
$unsolvedSubject = 'Re: (JIRA-1042) Still investigating';
$resNotSolved = PatternMatcher::match($solvedPattern, $unsolvedSubject, PatternMatcher::GROUP_SOLVED);
assertCondition($resNotSolved['matched'] === false, 'Non-solved subject returns matched=false');
assertCondition($resNotSolved['error'] === null, 'Non-solved subject MUST NOT produce an error');

// 6. Test invalid regex syntax handling
$invalidPattern = '/(?<match>[0-9+/'; // missing closing bracket
$resInvalid = PatternMatcher::match($invalidPattern, 'test');
assertCondition($resInvalid['matched'] === false, 'Invalid regex returns matched=false');
assertCondition($resInvalid['error'] !== null, 'Invalid regex captures error description gracefully');

echo "PatternMatcherTest completed successfully!\n\n";
