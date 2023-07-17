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
use CommonDBTM;
use Ticket;
use Session;
use CommonITILObject;
use Exception;
use ITILFollowup;
use MailCollector;
use Throwable;
use Toolbox;


class Filter extends CommonDBTM {
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
                 
                // Search our pattern in the name field and find corresponding ticket(s) (if any).
                $matches = self::searchForTicketMatches($item->input['name']);
                if(is_array($matches['tickets'])                // Should be an array (always)
                   && count($matches['tickets']) >= 1           // Should have at least 1 element
                   && is_array($matches['filterpattern'])) {    // The matching pattern should be present   
                          
                    // Add followups to each matching tickets
                    foreach($matches['tickets'] as $key)       
                    {
                        // Fetch found ticket.
                        $reference = new Ticket();
                        $reference->getFromDB((integer) $key);    

                        // Is found ticket closed? if so do nothing.
                        if($reference->fields['status'] != CommonITILObject::CLOSED) {
                            // @todo: This link needs some work to generate FQDN
                            if(self::createFollowup($item, $reference) == true) {
                                Session::addMessageAfterRedirect(__("<a href='".$reference->getLinkURL($key)."'>New ticket was matched to open ticket: $key and was added as a followup</a>"), true, INFO);
                            }
                        } 
                    }

                    // Clear $item->input and fields to stop object from being created
                    // https://glpi-developer-documentation.readthedocs.io/en/master/plugins/hooks.html
                    self::emptyReferencedObject($item);
                } 
                return; // ignore the hook
            } 
            return;     // ignore the hook
        } 
        return;         // ignore the hook
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
     * Create a followup in the matching ticket using the processed ticket.
     * 
     * @param  Ticket $item         The processed ticket passed by the pre_item_add hook.
     * @param  Ticket $reference    The matching ticket found using the pattern. 
     * @return bool                 Returns true on success false on failure. 
     * @since                       1.0.0          
     */
    private static function createFollowup(Ticket $item, Ticket $reference) : bool
    {
        if($ticketFollowup = new ITILFollowup()) {
            // Populate Followup fields
            $input                  = $item->input;
            $input['items_id']      = $reference->fields['id'];
            $input['users_id']      = false;
            $input['users_id']      = (isset($item->input['_users_id_requester'])) ? $item->input['_users_id_requester'] : $input['users_id'];
            $input['add_reopen']    = 1;
            $input['itemtype']      = Ticket::class;
            // Do not create the element if we dont want
            // notifications to be send.
            $input['_disablenotif'] = true;
            // Unset some stuff
            unset($input['urgency']);
            unset($input['entities_id']);
            unset($input['_ruleid']);

            if($ticketFollowup->add($input) === false) {
                Session::addMessageAfterRedirect(__("Failed to add followup to ticket {$reference->input['id']}"), true, WARNING);
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Perform a search in the glpi_tickets table using the searchString if one is found applying the 
     * provided ticket match patterns from dropdown on the ticket name (email subject).
     * 
     * @param  string $ticketSubject    Ticket name containing the Subject
     * @return int                      Returns the ticket ID of the matching ticket or 0 on no match. 
     * @since                           1.0.0            
     */
    private static function searchForTicketMatches(string $ticketSubject) : array
    {
        global $DB;
        // Get the patterns if any;
        $patterns = self::getFilterPatterns();

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
                    if(preg_match_all("$p", $ticketSubject, $matchArray)) {

                        // If we have a match, use it to compose a searchstring for our sql query.
                        $searchString = (is_array($matchArray) && count($matchArray) <> 0 && array_key_exists('match', $matchArray)) ? '%'.$matchArray['match']['0'].'%' : false;
                        if($searchString){

                            // Protect against SQL injections validate length is
                            // equal to what we expect it to be.
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
                                // If we find tickets using our matchString then return
                                // stop processing, return tickets and the matching
                                // Filterpattern configuration. 
                                if(is_array($r)
                                && count($r) > 0) {
                                    return ['tickets' => $r,
                                            'filterpattern' => $Filterpattern];
                                }
                            } else {
                                Session::addMessageAfterRedirect('Searchstring length was longer then configured Ticket Match String Length');
                            }
                        }

                    }
                }
            }               
        } else {
            trigger_error("No ticketfilter patterns found, please configure them or disable the plugin", E_USER_NOTICE);
        }
        return [];
    }

    /**
     * Get match patterns and config from dropdowns
     *   
     * @return patterns              Array with all configured patterns
     * @since                        1.1.0
     * @todo    Figure out if there isnt an method in the dropdown object
     *          that allows us to retrieve the reference table contents in
     *          one itteration.            
     */
    private static function getFilterPatterns() : array
    {
        global $DB;
        $patterns = [];
        $dropdown = new FilterPattern();
        $table = $dropdown::getTable();
        foreach($DB->request($table) as $id => $row){
            $patterns[] = ['name'                    => $row['name'],
                           'is_active'               => $row['is_active'],
                           'date_creation'           => $row['date_creation'],
                           'date_mod'                => $row['date_mod'],
                           'TicketMatchString'       => $row['TicketMatchString'],
                           'TicketMatchStringLength' => $row['TicketMatchStringLength'],
                           'AssetMatchString'        => $row['AssetMatchString'],
                           'AssetMatchStringLength'  => $row['TicketMatchStringLength'],
                           'SolvedMatchString'       => $row['SolvedMatchString'],
                           'SolvedMatchStringLength' => $row['TicketMatchStringLength'],
                           'AutomaticallyMerge'      => $row['AutomaticallyMerge'],
                           'LinkClosedTickets'       => $row['LinkClosedTickets'],
                           'SearchTicketBody'        => $row['SearchTicketBody'],
                           'MatchSpecificSource'     => $row['MatchSpecificSource']];
        }
        return $patterns;
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