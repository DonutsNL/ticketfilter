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

namespace GlpiPlugin\Ticketfilter\Utility;

use GlpiPlugin\Ticketfilter\Audit\AuditLog;
use GlpiPlugin\Ticketfilter\Model\FilterPattern;

/**
 * Diagnostic utility verifying database schema, permissions, and runtime readiness.
 */
class SanityChecker
{
    /**
     * Executes diagnostic sanity checks on the plugin environment.
     *
     * @return array [bool 'status', array 'messages']
     */
    public static function check(): array
    {
        global $DB;

        $checks   = [];
        $messages = [];
        $status   = true;

        if (!$DB) {
            $msg = __('Database connection unavailable.', 'ticketfilter');
            return [
                'status'   => false,
                'messages' => [$msg],
                'checks'   => [['message' => $msg, 'passed' => false]],
            ];
        }

        if ($DB->tableExists(FilterPattern::TABLE)) {
            $msg = sprintf(__('Patterns table (%s) exists.', 'ticketfilter'), FilterPattern::TABLE);
            $checks[] = ['message' => $msg, 'passed' => true];
            $messages[] = $msg;
        } else {
            $status = false;
            $msg = sprintf(__('Patterns table (%s) is missing.', 'ticketfilter'), FilterPattern::TABLE);
            $checks[] = ['message' => $msg, 'passed' => false];
            $messages[] = $msg;
        }

        if ($DB->tableExists(AuditLog::TABLE)) {
            $msg = sprintf(__('Audit log table (%s) exists.', 'ticketfilter'), AuditLog::TABLE);
            $checks[] = ['message' => $msg, 'passed' => true];
            $messages[] = $msg;
        } else {
            $status = false;
            $msg = sprintf(__('Audit log table (%s) is missing.', 'ticketfilter'), AuditLog::TABLE);
            $checks[] = ['message' => $msg, 'passed' => false];
            $messages[] = $msg;
        }

        $testRegex = @preg_match('/(?<match>test)/', 'test', $m);
        if ($testRegex === 1 && isset($m['match'])) {
            $msg = __('PCRE2 regex engine supports named capture groups.', 'ticketfilter');
            $checks[] = ['message' => $msg, 'passed' => true];
            $messages[] = $msg;
        } else {
            $status = false;
            $msg = __('PCRE regex engine failed to evaluate named capture groups.', 'ticketfilter');
            $checks[] = ['message' => $msg, 'passed' => false];
            $messages[] = $msg;
        }

        return [
            'status'   => $status,
            'messages' => $messages,
            'checks'   => $checks,
        ];
    }
}
