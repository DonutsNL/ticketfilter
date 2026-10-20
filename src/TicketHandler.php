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

namespace GlpiPlugin\Ticketfilter;

use CommonITILObject;
use ITILFollowup;
use Ticket;
use GlpiPlugin\Ticketfilter\Engine\TicketProcessor;
use GlpiPlugin\Ticketfilter\Model\FilterPattern;

/**
 * Backward-compatible TicketHandler facade without rogue die/echo statements.
 */
class TicketHandler
{
    private ?Ticket $ticket = null;
    private array $pattern  = [];
    private bool $status    = false;

    /**
     * Initializes the ticket handler with ticket ID and pattern config.
     *
     * @param int|Ticket $ticket Target ticket or ticket ID.
     * @param array $filterPattern Pattern configuration.
     * @return bool
     */
    public function initHandler(int|Ticket $ticket, array $filterPattern): bool
    {
        if (is_int($ticket)) {
            $this->pattern = $filterPattern;
            $this->ticket  = new Ticket();
            if ($this->ticket->getFromDB($ticket)) {
                $this->status = true;
                return true;
            }
        } elseif ($ticket instanceof Ticket) {
            $this->pattern = $filterPattern;
            $this->ticket  = $ticket;
            $this->status  = true;
            return true;
        }
        return false;
    }

    /**
     * Returns ticket ID of loaded ticket.
     *
     * @return int
     */
    public function getId(): int
    {
        return ($this->status && $this->ticket) ? (int)$this->ticket->fields['id'] : 0;
    }

    /**
     * Returns status of loaded ticket.
     *
     * @return int
     */
    public function getStatus(): int
    {
        return ($this->status && $this->ticket) ? (int)$this->ticket->fields['status'] : 0;
    }

    /**
     * Sets ticket status to Incoming.
     *
     * @return bool
     */
    public function setStatusToNew(): bool
    {
        global $DB;
        if ($this->ticket && $this->getId() > 0) {
            return (bool)$DB->update(
                $this->ticket->getTable(),
                ['status' => CommonITILObject::INCOMING],
                ['id' => $this->getId()]
            );
        }
        return false;
    }

    /**
     * Sets ticket status to Solved.
     *
     * @return bool
     */
    public function setStatusToSolved(): bool
    {
        global $DB;
        if ($this->ticket && $this->getId() > 0) {
            return (bool)$DB->update(
                $this->ticket->getTable(),
                ['status' => CommonITILObject::SOLVED],
                ['id' => $this->getId()]
            );
        }
        return false;
    }

    /**
     * Adds reopened followup.
     *
     * @param string $patternName Pattern name.
     * @return bool
     */
    public function addReopenedMessage(string $patternName = ''): bool
    {
        if ($this->getId() > 0) {
            $followup = new ITILFollowup();
            return (bool)$followup->add([
                'items_id'      => $this->getId(),
                'itemtype'      => Ticket::class,
                'content'       => sprintf(__('This ticket was reopened by TicketFilter (pattern: %s)', 'ticketfilter'), $patternName),
                'users_id'      => 0,
                'add_reopen'    => 1,
                'is_private'    => 1,
                '_disablenotif' => true,
            ]);
        }
        return false;
    }

    /**
     * Adds solved followup.
     *
     * @param string $patternName Pattern name.
     * @return bool
     */
    public function addSolvedMessage(string $patternName = ''): bool
    {
        if ($this->getId() > 0) {
            $followup = new ITILFollowup();
            return (bool)$followup->add([
                'items_id'      => $this->getId(),
                'itemtype'      => Ticket::class,
                'content'       => sprintf(__('This ticket was solved by TicketFilter (pattern: %s)', 'ticketfilter'), $patternName),
                'users_id'      => 0,
                'is_private'    => 1,
                '_disablenotif' => true,
            ]);
        }
        return false;
    }

    /**
     * Searches for tickets in the pool containing search string.
     *
     * @param string $searchString Search pattern.
     * @return array
     */
    public function searchTicketPool(string $searchString): array
    {
        $token = trim($searchString, '%');
        return TicketProcessor::searchTicketPool($token);
    }

    /**
     * Verifies if requesters match.
     *
     * @param Ticket $item Incoming ticket hook object.
     * @return bool
     */
    public function verifyRequesters(Ticket $item): bool
    {
        if (!$this->ticket) {
            return false;
        }
        $policy = (!empty($this->pattern[FilterPattern::FIELD_MATCHSOURCE]))
            ? FilterPattern::POLICY_STRICT_REQUESTER
            : FilterPattern::POLICY_OPEN;

        $check = TicketProcessor::verifySender($item, $this->ticket, $policy);
        return $check['authorized'];
    }

    /**
     * Processes ticket followup merging safely without die() or echo.
     *
     * @param Ticket $item Incoming ticket hook object.
     * @return bool
     */
    public function processTicket(Ticket $item): bool
    {
        if (!$this->status || !$this->ticket) {
            return false;
        }

        $token = '';
        $result = TicketProcessor::processTicket($item, $this->ticket, $this->pattern, $token, false);
        return $result['success'];
    }
}
