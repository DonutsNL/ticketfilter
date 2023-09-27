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
 *  @version	    1.1.0
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
     * Method called by pre_item_add hook validates the object and passes
     * it to the RegEx Matching then decides what to do.
     *
     * @param  Ticket $item      Hooked Ticket object passed by refference.
     * @return void              Object is passed by reference, no return values required
     * @since                    1.0.0
     * @see                      setup.php hook
     */
    public static function PreItemAdd(Ticket $item) : void 
    {
        if(is_object($item)) {

            if(is_array($item->input)                  // Fields should be an array with values.
               && key_exists('name', $item->input)     // Name key (that is the Subject of the email) should exist.
               && !empty($item->input['name'])) {      // Name should not be emtpy, could happen with recurring tickets.

                // Search our pattern in the name field and find corresponding ticket(s) (if any)
                self::searchForMatches($item);
            }
        }
    }
    
    /**
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
        if(self::deleteEmail($item) == true) {
            Session::addMessageAfterRedirect(__("Ticket removed from mailbox, it is save to ignore any mailgate error"), true, WARNING);
        }
        $item->input = false;
        $item->fields = false;
    }

    /**
     * Perform a search in the glpi_tickets table using the searchString if one is found applying the
     * provided ticket match patterns from dropdown on the ticket name (email subject).
     *
     * @param  string $ticketSubject    Ticket name containing the Subject
     * @return int                      Returns the ticket ID of the matching ticket or 0 on no match.
     * @since                           1.0.0
     */
    private static function searchForMatches(Ticket $item) : bool
    {
        global $DB;
        $itemIsMatched = false;
        // Get the patterns if any;
        $patterns = FilterPattern::getFilterPatterns();

        if(is_array($patterns)
        && count($patterns) >= 1
        && array_key_exists('TicketMatchString', $patterns['0']))
        {
            // We assume that the name always only contains one pattern and return the first matching one.
            foreach($patterns as $k => $Filterpattern)
            {

                // If pattern is active process
                if($Filterpattern['is_active']) {

                    // decode html_entities_encoded string from database.
                    $p = html_entity_decode($Filterpattern['TicketMatchString']);
                    if(preg_match_all("$p", $item->input['name'], $matchArray)) {

                        // If we have a match, use it to compose a searchstring for our sql query.
                        $searchString = (is_array($matchArray) && count($matchArray) <> 0 && array_key_exists('match', $matchArray)) ? '%'.$matchArray['match']['0'].'%' : false;
                        if($searchString){

                            // Protect against risky patterns or SQL injections by validating the length
                            // of the matchstring against what we expect it to be.
                            if(strlen($searchString) <= $Filterpattern['TicketMatchStringLength']) {
                                foreach($DB->request(
                                    'glpi_tickets',
                                    [
                                        'AND' =>
                                        [
                                            'name'       => ['LIKE', $searchString],
                                            'is_deleted' => ['=', 0]
                                        ]
                                    ]
                                ) as $id => $row) {
                                    $r[]=$id;
                                }

                                // If we find a ticket then add followups to each of the found tickets.
                                if(is_array($r)
                                && count($r) > 0) {
                                    // Indicate passed item was matched against existing ticket.
                                    $itemIsMatched = true;
                                    foreach($r as $key)
                                    {
                                        // Initialize the ticketHandler.
                                        $handler = new TicketHandler();
                                        $handler->initHandler($key, $Filterpattern);

                                        // @todo: This link needs some work to generate FQDN
                                        if ( $handler->processTicket($item) ) {
                                            Session::addMessageAfterRedirect(__("<a href='".$handler->getTicketURL($key)."'>New ticket was matched to open ticket: $key and was added as a followup</a>"), true, INFO);
                                        } else {
                                            Session::addMessageAfterRedirect(__("Unable to add notification"), true, WARNING);
                                            // Adding followup failed, dont clear referenced object;
                                            $itemIsMatched = false;
                                        }
                                    }
                                }
                            } else {
                                Session::addMessageAfterRedirect('Length of'.$searchString.' is longer then allowed by configured Ticket Match String Length');
                                return false;
                            }
                        }

                    }
                }
            }               
        } else {
            trigger_error("No ticketfilter patterns found, please configure them or disable the plugin", E_USER_NOTICE);
            return false;
        }

        if($itemIsMatched){
            // Clear $item->input and fields to stop object from being created
            // https://glpi-developer-documentation.readthedocs.io/en/master/plugins/hooks.html
            self::emptyReferencedObject($item);
        }
        return true;
    }

    /**
     * Returns a connected mailCollector object or []
     * 
     * @param  int $Id               Mail Collector ID   
     * @return MailCollector|null    Union return types might be problematic with older php versions.  
     * @since                        1.0.0  
     * @todo                         Move to separate non static Match class           
     */
    private static function openMailGate(int $Id) : MailCollector|null
    {
        // Create a mailCollector
        if(is_numeric($Id)){
            $mailCollector = new MailCollector();
            $mailCollector->getFromDB((integer)$Id);
            try {
                $mailCollector->connect();
            }catch (Throwable $e) {
                Toolbox::logError('Error opening mailCollector', $e->getMessage(), "\n", $e->getTraceAsString());
                Session::addMessageAfterRedirect(__('TicketFilter Could not connect to the mail receiver because of an error'), true, WARNING);
                return null;
            }
        }
        return $mailCollector;
    }

    /**
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
            if($mailCollector->deleteMails($item->input['_uid'], MailCollector::ACCEPTED_FOLDER)) {
                return true;
            } else {
                return false;
            }
        }
        
        return false;
    }
}