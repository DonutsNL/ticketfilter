<?php

namespace GlpiPlugin\TicketFilter;

use Config;
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
     */
    public const MATCHPATERNS = ['/.*?(?<match>\(CITI-[0-9]{1,4}\)).*/']; //(CITI-1) - (CITI-1234) pattern should contain a named group (?<match>...)
    public const DISABLENOTIF = 1;                                        // Prevent GLPI from sending notifications on added followups to break runaway conditions between automated systems and GLPI.
    
    /**
     * Method called by pre_item_add hook validates the object and passes
     * it to the RegEx Matching.
     * 
     * @param $item       Expects the Ticket object by refference.
     * @return void  
     * @since           1.0.0             
     */
    public static function PreItemAdd(Ticket $item) : void 
    {
        if(is_object($item)){
            if(is_array($item->input)                  // Fields should be an array with values.
               && key_exists('name', $item->input)     // Name key (that is the Subject of the email) should exist.
               && !empty($item->input['name'])) {      // Name should not be emtpy, recurring tickets without template will create empty tickets without name.
                
                // Check if ticket is fetched from the mailCollector if so open it;
                if($mailCollector = (isset($item->input['_mailgate'])) ? self::openMailGate($item->input['_mailgate']) : false) {
                    $uid = $item->input['_uid'];
                }


                // Search our pattern in the name field and find corresponding ticket (if any).
                $matches = self::searchForMatches($item->input['name']);
                if(is_array($matches)               // Should be an array (always)
                   && count($matches) >= 1          // Should contain at least 1 ticket id (open ticket to add followup or closed to at least link the ticket)
                   && count($matches) <= 2) {       // Could contain 2 tickets (closed and opened one) if more than 2 '(closed) tickets are there we give up
                    
                    if(count($matches) == 1) {      // If there is only one ticket, we either add an followup (open) or link the new ticket (closed).
                        
                        $rID = $matches['0'];
                        $refTicket = new Ticket();
                        $refTicket->getFromDB((integer)$rID);
                        if($refTicket->fields['status'] != CommonITILObject::CLOSED) {
                            // If not not closed, create followup
                            $ticketFollowup         = new ITILFollowup();
                            $input                  = $item->input;
                            $input['items_id']      = $rID;
                            $input['users_id']      = (isset($item->input['_users_id_requester'])) ? $item->input['_users_id_requester'] : $input['users_id'];
                            $input['add_reopen']    = 1;
                            $input['itemtype']      = Ticket::class;
                            // Do not create the element if we dont want
                            // notifications to be send.
                            if (self::DISABLENOTIF) { $input['_disablenotif'] = self::DISABLENOTIF; }

                            unset($input['urgency']);
                            unset($input['entities_id']);
                            unset($input['_ruleid']);
                            $ticketFollowup->add($input); // Create a follow-up
                            $item->input = false;   // Clean the input of the referenced object to prevent the new ticket from being created
                            $item->fields = false;  // Clean the input of the referenced object to prevent the new ticket from being created

                            // We cancelled the ticketcreation so we need to manually 
                            // Clean the email from the mailBox 
                            $mailCollector->deleteMails($uid, MailCollector::ACCEPTED_FOLDER);

                            // Verify we are in the application?
                            // warn user that new ticket was added as followup instead. 
                            Session::addMessageAfterRedirect(__("<a href='https://mc.trippie.fun/glpi/front/ticket.form.php?id=$rID'>Ticket was matched by to open ticket: $rID by TicketFilter and added as followup</a>"), true, WARNING);
                            //die('followup added');
                        } else {
                            // If closed Link the new ticket.
                            $item->input['_link'] = ['link' => '1', 
                                                    'tickets_id_1' => '0', 
                                                    'tickets_id_2' => $rID];
                            // TODO: Something with logging.
                            Session::addMessageAfterRedirect(__("<a href='https://mc.trippie.fun/glpi/front/ticket.form.php?id=$rID'>Ticket was matched to a closed ticket: $rID by TicketFilter and linked</a>"), true, INFO);
                        }     
                    } else {
                        // Handle multiple tickets find the open one and add the followup.
                        foreach($matches as $index => $rID) { // Needs cleaning to $array[] = refId instead of $array[tID] = Row;
                            $refTicket = new Ticket();
                            $refTicket->getFromDB((integer)$rID);
                            if($refTicket->fields['status'] != CommonITILObject::CLOSED) {
                                // If not not closed, create followup
                                $ticketFollowup         = new ITILFollowup();
                                $input                  = $item->input;
                                $input['items_id']      = $rID;
                                $input['users_id']      = (isset($item->input['_users_id_requester'])) ? $item->input['_users_id_requester'] : $input['users_id'];
                                $input['add_reopen']    = 1;
                                $input['itemtype']      = Ticket::class;
                                // Do not create the element if we dont want
                                // notifications to be send.
                                if (self::DISABLENOTIF) { $input['_disablenotif'] = self::DISABLENOTIF; }

                                unset($input['urgency']);
                                unset($input['entities_id']);
                                unset($input['_ruleid']);
                                $ticketFollowup->add($input); // Create a follow-up
                                $item->input = false;   // Clean the input of the referenced object to prevent the new ticket from being created
                                $item->fields = false;  // Clean the input of the referenced object to prevent the new ticket from being created

                                // We cancelled the ticketcreation so we need to manually 
                                // Clean the email from the mailBox 
                                $mailCollector->deleteMails($uid, MailCollector::ACCEPTED_FOLDER);

                                // Verify we are in the application?
                                // warn user that new ticket was added as followup instead. 
                                Session::addMessageAfterRedirect(__("<a href='https://mc.trippie.fun/glpi/front/ticket.form.php?id=$rID'>Ticket was matched by to open ticket: $rID by TicketFilter and added as followup</a>"), true, WARNING);
                            }
                        }
                    }
                }
                // We got nothing
                return;
            } //  no fields? ignore the hook
            return;
        } // no object? ignore the hook
        return;
    }

    /**
     * Perform a match on the given matchString if found perform
     * a search in the database and will try to perform a merge 
     * operation.
     * 
     * @param $tName    Ticket name containing the Subject
     * @return int      Returns the ticket ID of the matching ticket or 0 on no match. 
     * @since           1.0.0  
     * @todo            Move to separate non static Match class           
     */
    private static function searchForMatches(string $tName) : array
    {
        global $DB;
        // We assume that the name always only contains one pattern and return the first matching one.
        foreach(self::MATCHPATERNS as $matchPatern){
            if(preg_match_all($matchPatern, $tName, $matchArray)){
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
     * @param $Id               Mail Collector ID   
     * @return MailCollector    | []   
     * @since                   1.0.0  
     * @todo                    Move to separate non static Match class           
     */
    private static function openMailGate(int $Id) : mixed
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
                return[];
            }
        }
        return $mailCollector;
    }
}