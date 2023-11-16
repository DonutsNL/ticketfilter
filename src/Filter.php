<?php

/**
 *  ------------------------------------------------------------------------
 *  Chris Gralike Ticket Filter
 *  Copyright (C) 2023 by Chris Gralike
 *  ------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Ticket Filter project.
 *
 * Ticket Filter plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Ticket Filter is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with ticket filter. If not, see <http://www.gnu.org/licenses/>.
 *
 * ------------------------------------------------------------------------
 *
 *  @package  	    TicketFilter
 *  @version	    1.2.0
 *  @author         Chris Gralike
 *  @copyright 	    Copyright (c) 2023 by Chris Gralike
 *  @license    	GPLv2+
 *  @see       	    https://github.com/DonutsNL/ticketfilter/readme.md
 *  @link		    https://github.com/DonutsNL/ticketfilter
 *  @since     	    1.0.1
 * ------------------------------------------------------------------------
 **/

namespace GlpiPlugin\Ticketfilter;

use GlpiPlugin\Ticketfilter\FilterPattern;
use GlpiPlugin\Ticketfilter\TicketHandler;
use Ticket;
use Session;
use MailCollector;
use Throwable;
use Toolbox;

class Filter {

    /**
     * PreItemAdd(Ticket item) : void -
     * Method called by pre_item_add hook validates the object and passes
     * it to the RegEx Matching then decides what to do.
     *
     * @param  Ticket $item      Hooked Ticket object passed by refference.
     * @return void              Object is passed by reference, no return values required
     * @since                    1.0.0
     * @see                      setup.php hook
     */
    public static function preItemAdd(Ticket $item) : void
    {
        if(is_object($item) &&
           is_array($item->input) &&
           key_exists('name', $item->input) &&      // Name key (that is the Subject of the email) should exist.
           !empty($item->input['name'])) {          // Name should not be emtpy, could happen with recurring tickets.
           // Search our pattern in the name field and find corresponding ticket(s) (if any)
           self::searchForMatches($item);
        }
    }
    
    /**
     * emptyReferencedObject(Ticket item) : void -
     * Clean the referenced item and delete any mailbox items remaining
     *
     * @param  Ticket $item         The original ticket passed by the pre_item_add hook.
     * @param  Ticket $reference    The matching ticket found using the patern.
     * @return bool                 Returns true on success false on failure.
     * @since                       1.0.0
     */
    private static function emptyReferencedObject(Ticket $item) : void
    {
        // We cancelled the ticket creation and by doing so the mailgate will not clean the
        // email from the mailbox. We need to clean it manually.
        // Send a meaningfull message if mailgate was triggered by user from UI.
        if(self::deleteEmail($item) === true) {
            Session::addMessageAfterRedirect(__("Ticket removed from mailbox, it is save to ignore related mailgate error"), true, WARNING);
        }
        $item->input = false;
        $item->fields = false;
    }

    /**
     * searchForMatches(Ticket item) : bool -
     * Perform a search in the glpi_tickets table using the searchString if one is found applying the
     * provided ticket match patterns from dropdown on the ticket name (email subject).
     *
     * @param  string $ticketSubject    Ticket name containing the Subject
     * @return int                      Returns the ticket ID of the matching ticket or 0 on no match.
     * @since                           1.0.0
     */
    private static function searchForMatches(Ticket $item) : bool
    {
        $itemIsMatched = [];        // Start with an empty array

        // Fetch patterns
        $patterns = FilterPattern::getFilterPatterns();

        // Evaluate patterns
        if(is_array($patterns)
        && !empty($patterns)
        && array_key_exists(FilterPattern::TICKETMATCHSTR, $patterns['0']))
        {
            // Loop through the patterns
            foreach($patterns as $k => $Filterpattern)
            {
                // If pattern is active process
                if($Filterpattern['is_active']) {
                    // decode html_entities_encoded string from database.
                    $p = html_entity_decode($Filterpattern[FilterPattern::TICKETMATCHSTR]);
                    if(preg_match_all("$p", $item->input['name'], $matchArray)) {

                        // If we found a match, use it to compose a searchstring for our sql query.
                        $searchString = (is_array($matchArray) && count($matchArray) <> 0 && array_key_exists('match', $matchArray)) ? '%'.$matchArray['match']['0'].'%' : false;
                        if($searchString){

                            // Protect against risky patterns or SQL injections by validating the length
                            // of the matchstring against what we expect it to be.
                            if(strlen($searchString) <= $Filterpattern[FilterPattern::TICKETMATCHSTRLEN]) {
                                $handler = new TicketHandler();
                                $r = $handler->searchTicketPool($searchString);

                                // If we find a ticket then add followups to each of the found tickets.
                                if(count($r) > 0) {
                                    foreach($r as $key)
                                    {
                                        $handler->initHandler($key, $Filterpattern);
                                        // Keep track what tickets where matched
                                        if ( $handler->processTicket($item) ) {
                                            $itemIsMatched[$key] = 'matched';
                                        }
                                    } // Loop.
                                } // No matching tickets found.
                            } else {
                                trigger_error('TicketFilter: Length of'.$searchString.' is longer then allowed by configured Ticket Match String Length', E_USER_WARNING);
                            }
                        } // No searchstring found with provided patterns.
                    } else {
                        trigger_error("TicketFilter: PregMatch failed! please review the pattern $p and correct it", E_USER_WARNING);
                    }
                } // Pattern is configured inactive.
            } // Loop.
        } else {
            trigger_error("TicketFilter: No ticketfilter patterns found, please configure them or disable the plugin", E_USER_WARNING);
        }

        // Do we have at least 1 succesfull followup, prevent GLPI from creating a new ticket
        if(is_array($itemIsMatched) &&
           in_array("matched", $itemIsMatched)) {
            // Clear $item->input and fields to stop object from being created
            // https://glpi-developer-documentation.readthedocs.io/en/master/plugins/hooks.html
            self::emptyReferencedObject($item);
        }
        return true;
    }

    /**
     * openMailGate(int Id) : MailCollector|null -
     * Returns a connected mailCollector object or []
     *
     * @param  int $Id               Mail Collector ID
     * @return MailCollector|null    Union return types might be problematic with older php versions.
     * @since                        1.0.0
     */
    private static function openMailGate(int $Id) : MailCollector|null
    {
        // Create a mailCollector
        $mailCollector = new MailCollector();
        $mailCollector->getFromDB((integer)$Id);
        try {
            $mailCollector->connect();
        }catch (Throwable $e) {
            Toolbox::logError('Error opening mailCollector', $e->getMessage(), "\n", $e->getTraceAsString());
            Session::addMessageAfterRedirect(__('TicketFilter Could not connect to the mail receiver because of an error'), true, WARNING);
            return null;
        }
        return $mailCollector;
    }

    /**
     * deleteEmail(Ticket item) : bool -
     * Delete original email from the mailbox to the accepted folder. This is not performed by the mailgate
     * if the Ticket passed by reference is nullified as described in the hook documentation.
     * This actually causes an error where mailgate leaves the email untouched.
     *
     * @param  Ticket $item     Ticket object containing the mailgate uid
     * @return bool             Returns true on success and false on failure
     * @since                   1.0.0
     */
    private static function deleteEmail(Ticket $item) : bool
    {
        // Check if ticket is fetched from the mailCollector if so open it;
        $mailCollector = (isset($item->input['_mailgate'])) ? self::openMailGate($item->input['_mailgate']) : false;
        if(is_object($mailCollector)){
            return ($mailCollector->deleteMails($item->input['_uid'], MailCollector::ACCEPTED_FOLDER)) ? true : false;
        } // instantiation of mailCollector failed
        return false;
    }
}
