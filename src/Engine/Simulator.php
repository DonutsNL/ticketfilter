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

namespace GlpiPlugin\Ticketfilter\Engine;

use CommonITILObject;
use Ticket;
use GlpiPlugin\Ticketfilter\Model\FilterPattern;

/**
 * Dry-run simulator allowing administrators to test patterns against sample strings
 * or existing production tickets without modifying data.
 */
class Simulator
{
    /**
     * Simulates pattern execution against a single test subject and body.
     *
     * @param string $pattern Regex pattern to test.
     * @param string $subject Sample subject line.
     * @param string $body Sample email/ticket body.
     * @param string $senderEmail Sample sender email address.
     * @param string $solvedPattern Optional solved pattern to test.
     * @param string $senderPolicy Security policy to evaluate.
     * @return array Structured simulation outcome.
     */
    public static function simulateSample(
        string $pattern,
        string $subject,
        string $body = '',
        string $senderEmail = '',
        string $solvedPattern = '',
        string $senderPolicy = FilterPattern::POLICY_STRICT_REQUESTER
    ): array {
        $validation = PatternMatcher::validate($pattern, PatternMatcher::GROUP_MATCH);
        if (!$validation['valid']) {
            return [
                'success'           => false,
                'error'             => $validation['error'],
                'matched'           => false,
                'token'             => null,
                'target_tickets'    => [],
                'collision'         => false,
                'would_reopen'      => false,
                'would_solve'       => false,
                'simulated_action'  => 'INVALID_REGEX',
            ];
        }

        $matchResult = PatternMatcher::match($pattern, $subject, PatternMatcher::GROUP_MATCH);
        if (!$matchResult['matched'] && $body !== '') {
            $matchResult = PatternMatcher::match($pattern, $body, PatternMatcher::GROUP_MATCH);
        }

        if (!$matchResult['matched'] || empty($matchResult['token'])) {
            return [
                'success'          => true,
                'error'            => null,
                'matched'          => false,
                'token'            => null,
                'target_tickets'   => [],
                'collision'        => false,
                'would_reopen'     => false,
                'would_solve'      => false,
                'simulated_action' => 'NO_MATCH',
            ];
        }

        $token = $matchResult['token'];
        $matchingTicketIds = TicketProcessor::searchTicketPool($token);
        $totalCandidates   = count($matchingTicketIds);

        $targetTicket    = null;
        $targetTicketId  = 0;
        $wouldReopen     = false;
        $wouldSolve      = false;
        $simulatedAction = 'NO_EXISTING_TICKET_FOUND';

        if ($totalCandidates > 0) {
            if ($senderEmail !== '') {
                $dummyItem = new Ticket();
                $dummyItem->input = [
                    'name'                      => $subject,
                    'content'                   => $body,
                    '_users_id_requester'       => 0,
                    '_users_id_requester_notif' => [
                        'alternative_email' => [$senderEmail],
                    ],
                    '_head' => [
                        'from' => $senderEmail,
                    ],
                ];

                foreach ($matchingTicketIds as $candidateId) {
                    $cand = new Ticket();
                    if (!$cand->getFromDB($candidateId)) {
                        continue;
                    }
                    $check = TicketProcessor::verifySender($dummyItem, $cand, $senderPolicy);
                    if ($check['authorized']) {
                        $targetTicket   = $cand;
                        $targetTicketId = $candidateId;
                        break;
                    }
                }
            } else {
                // If no sender email supplied to simulator, default to lowest ID (original)
                $cand = new Ticket();
                if ($cand->getFromDB($matchingTicketIds[0])) {
                    $targetTicket   = $cand;
                    $targetTicketId = $matchingTicketIds[0];
                }
            }

            if ($targetTicket !== null) {
                $status = (int)$targetTicket->fields['status'];
                if ($status === CommonITILObject::CLOSED) {
                    $wouldReopen     = true;
                    $simulatedAction = 'WOULD_REOPEN_AND_MERGE';
                } else {
                    $simulatedAction = 'WOULD_MERGE_FOLLOWUP';
                }

                if ($solvedPattern !== '') {
                    $solvedCheck = PatternMatcher::match($solvedPattern, $subject, PatternMatcher::GROUP_SOLVED);
                    if ($solvedCheck['matched']) {
                        $wouldSolve      = true;
                        $simulatedAction = ($wouldReopen) ? 'WOULD_REOPEN_MERGE_AND_SOLVE' : 'WOULD_MERGE_AND_SOLVE';
                    }
                }
            } else {
                $simulatedAction = 'WOULD_SKIP_UNMATCHED_SENDER';
            }
        }

        return [
            'success'          => true,
            'error'            => null,
            'matched'          => true,
            'token'            => $token,
            'target_tickets'   => $matchingTicketIds,
            'collision'        => ($totalCandidates > 1),
            'would_reopen'     => $wouldReopen,
            'would_solve'      => $wouldSolve,
            'simulated_action' => $simulatedAction,
        ];
    }

    /**
     * Batch evaluates a pattern against existing tickets in the database.
     *
     * @param string $pattern Regex pattern to evaluate.
     * @param int $limit Maximum number of historical tickets to inspect.
     * @param int $entitiesId Entity restriction.
     * @return array [int 'total_scanned', int 'total_matched', array 'matches', int 'collisions']
     */
    public static function simulateHistorical(string $pattern, int $limit = 50, int $entitiesId = 0): array
    {
        global $DB;

        $validation = PatternMatcher::validate($pattern, PatternMatcher::GROUP_MATCH);
        if (!$validation['valid']) {
            return [
                'success'       => false,
                'error'         => $validation['error'],
                'total_scanned' => 0,
                'total_matched' => 0,
                'matches'       => [],
                'collisions'    => 0,
            ];
        }

        $ticket = new Ticket();
        $table  = $ticket->getTable();
        $where  = ['is_deleted' => 0];

        if ($entitiesId > 0) {
            $where['entities_id'] = $entitiesId;
        }

        $iterator = $DB->request([
            'SELECT' => ['id', 'name', 'status', 'date_creation', 'closedate'],
            'FROM'   => $table,
            'WHERE'  => $where,
            'ORDER'  => ['id DESC'],
            'LIMIT'  => max(1, min($limit, 500)),
        ]);

        $scanned    = 0;
        $matched    = 0;
        $collisions = 0;
        $details    = [];

        foreach ($iterator as $row) {
            $scanned++;
            $subject = (string)$row['name'];
            $matchResult = PatternMatcher::match($pattern, $subject, PatternMatcher::GROUP_MATCH);

            if ($matchResult['matched'] && !empty($matchResult['token'])) {
                $matched++;
                $token = $matchResult['token'];
                $poolMatches = TicketProcessor::searchTicketPool($token);
                $isCollision = (count($poolMatches) > 1);

                if ($isCollision) {
                    $collisions++;
                }

                $details[] = [
                    'ticket_id'      => (int)$row['id'],
                    'ticket_name'    => $subject,
                    'token'          => $token,
                    'status'         => (int)$row['status'],
                    'matched_pool'   => $poolMatches,
                    'is_collision'   => $isCollision,
                    'would_reopen'   => ((int)$row['status'] === CommonITILObject::CLOSED),
                ];
            }
        }

        return [
            'success'       => true,
            'error'         => null,
            'total_scanned' => $scanned,
            'total_matched' => $matched,
            'matches'       => $details,
            'collisions'    => $collisions,
        ];
    }
}
