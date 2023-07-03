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
 *  @version	    1.0.0
 *  @author         Chris Gralike
 *  @copyright 	    Copyright (c) 2023 by Chris Gralike
 *  @license    	MIT
 *  @see       	    https://github.com/DonutsNL/ticketfilter/readme.md
 *  @link		    https://github.com/DonutsNL/ticketfilter
 *  @since     	    1.0.0
 *  @todo           !Keep it stupid simple!
 *                  -Create a fancy configuration page
 *                   -Config [Followup Match string] [AutoSolve Match String] [Supress followups] [regEx101 testlink/comment]
 *                   -Option to automatically merge duplicate oldest/latest ticket if more then 1 ticket is found with matchstring.
 *                   -Option to automatically link with closed ticket(s) on match closed (how to deal with multiple closed tickets?)
 *                  -Default GLPI behaviour is to load Plugin trazilion times with each dashboard graph, maybe create singleton pattern/caching?
 * ------------------------------------------------------------------------
 **/

namespace GlpiPlugin\Ticketfilter;

// use Config; todo: write a nice config page to administer the patterns
use Ticket;
use Session;
use CommonITILObject;
use ITILFollowup;
use MailCollector;
use Throwable;
use Toolbox;

class Filter {
    /**
     * Array with match strings to locate foreign ticket identifiers and
     * match them locally.
     * @since           1.0.0
     * @see             https://regex101.com/r/htaEx7/1             
     */
    public const MATCHPATERNS = ['/.*?(?<match>\(CITI-[0-9]{1,4}\)).*/'];

     /**
     * Disable notifications?
     * @since           1.0.0             
     */
    public const DISABLENOTIF = 1;
    
    /**
     * Method called by pre_item_add hook validates the object and passes
     * it to the RegEx Matching then decides what to do.
     * 
     * @param  Ticket $item      Hooked Ticket object passed by refference.
     * @return void  
     * @since                    1.0.0
     * @see                      setup.php hook             
     */
    public static function PreItemAdd(Ticket $item) : void 
    {
        if(is_object($item)) {
        
            if(is_array($item->input)                  // Fields should be an array with values.
               && key_exists('name', $item->input)     // Name key (that is the Subject of the email) should exist.
               && !empty($item->input['name'])) {      // Name should not be emtpy, could happen with recurring tickets.
                 
                
                // Search our pattern in the name field and find corresponding ticket (if any).
                $matches = self::searchForMatches($item->input['name']);
                if(is_array($matches)               // Should be an array (always)
                   && count($matches) >= 1) {       // Should have at least 1 element
                          

                    foreach($matches as $key)       // Add followups to all matching non closed tickets
                    {
                        // Fetch found ticket.
                        $reference = new Ticket();
                        $reference->getFromDB((integer) $key);    

                        // Is found ticket closed? if so do nothing.
                        if($reference->fields['status'] != CommonITILObject::CLOSED) {
                            // @todo: This link needs some work to generate FQDN
                            if(self::createFollowup($item, $reference) == true) {
                                Session::addMessageAfterRedirect(__("<a href='/front/ticket.form.php?id=$key'>New ticket was matched to open ticket: $key and was added as a followup</a>"), true, INFO);
                            }
                        } 
                    }

                    // Empty $item->input and fields to stop object from being created
                    // https://glpi-developer-documentation.readthedocs.io/en/master/plugins/hooks.html
                    self::emptyReferencedObject($item);
                } 
                return; // We got nothing
            } 
            return;     //  ignore the hook
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
        // We cancelled the ticketcreation so we need to manually 
        // Clean the email from the mailBox 
        if(self::deleteEmail($item) == true) {
            Session::addMessageAfterRedirect(__("Ticket removed from mailbox, it is save to ignore any mailgate error"), true, WARNING);
        }
        $item->input = false;
        $item->fields = false;
    }


    /**
     * Create a followup to be added to the referenced ticket
     * 
     * @param  Ticket $item         The original ticket passed by the pre_item_add hook.
     * @param  Ticket $reference    The matching ticket found using the patern. 
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
            if (self::DISABLENOTIF) { $input['_disablenotif'] = self::DISABLENOTIF; }
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
     * Perform a match on the given matchString if found perform
     * a search in the database and will try to perform a merge 
     * operation.
     * 
     * @param  string $ticketSubject    Ticket name containing the Subject
     * @return int                      Returns the ticket ID of the matching ticket or 0 on no match. 
     * @since                           1.0.0            
     */
    private static function searchForMatches(string $ticketSubject) : array
    {
        global $DB;
        // We assume that the name always only contains one pattern and return the first matching one.
        foreach(self::MATCHPATERNS as $matchPatern){
            if(preg_match_all($matchPatern, $ticketSubject, $matchArray)){
                $matchString = (is_array($matchArray) && count($matchArray) <> 0 && array_key_exists('match', $matchArray)) ? '%'.$matchArray['match']['0'].'%' : false;
                if($matchString){
                    foreach($DB->request(
                        'glpi_tickets',
                        [
                            'AND' =>
                            [
                                'name'       => ['LIKE', $matchString],
                                'is_deleted' => ['=', 0]
                            ]
                        ]
                    ) as $id => $row) {
                        $r[]=$id;
                    }
                    if(is_array($r)
                       && count($r) > 0) {
                        return $r;
                    }
                }
            }
        }
        return [];
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