<?php

namespace GlpiPlugin\TicketFilter;

// use Config; todo: write config page
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
     * @param $item      Hooked Ticket object passed by refference.
     * @return void  
     * @since           1.0.0             
     */
    public static function PreItemAdd(Ticket $item) : void 
    {
        if(is_object($item)) {
        
            if(is_array($item->input)                  // Fields should be an array with values.
               && key_exists('name', $item->input)     // Name key (that is the Subject of the email) should exist.
               && !empty($item->input['name'])) {      // Name should not be emtpy.
                 
                
                // Search our pattern in the name field and find corresponding ticket (if any).
                $matches = self::searchForMatches($item->input['name']);
                if(is_array($matches)               // Should be an array (always)
                   && count($matches) >= 1) {       // Should have at least 1 element
                          

                    foreach($matches as $key)       // Add followups to all matching non closed tickets
                    {
                        // Fetch the found ticket.
                        $reference = new Ticket();
                        $reference->getFromDB((integer) $key);    

                        // Is found ticket closed? if so do nothing.
                        if($reference->fields['status'] != CommonITILObject::CLOSED) {
                   
                            if(self::createFollowup($item, $reference) == true) {
                                Session::addMessageAfterRedirect(__("<a href='https://mc.trippie.fun/glpi/front/ticket.form.php?id=$key'>Ticket was matched by to open ticket: $key and was added as a followup</a>"), true, INFO);
                            }
                           
                            // Clean the original ticket to stop creation
                            // https://glpi-developer-documentation.readthedocs.io/en/master/plugins/hooks.html
                            $item->input = false;
                            $item->fields = false;

                            // We cancelled the ticketcreation so we need to manually 
                            // Clean the email from the mailBox 
                            if(self::deleteEmail($item) === true) {
                                Session::addMessageAfterRedirect(__("Ticket removed from mailbox, save to ignore any mailgate error"), true, INFO);
                            }
                        }   
                    }
                }
                // We got nothing
                return;
            } //  ignore the hook
            return;
        } // ignore the hook
        return;
    }


    private static function createFollowup(Ticket $item, Ticket $reference) : bool
    {
        if($ticketFollowup = new ITILFollowup()) {
            // Populate Followup fields
            $input                  = $item->input;
            $input['items_id']      = $reference->input['id'];
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
            } // Create a follow-up
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

    private static function deleteEmail(Ticket $item) : bool
    {
        // Check if ticket is fetched from the mailCollector if so open it;
        if($mailCollector = (isset($item->input['_mailgate'])) ? self::openMailGate($item->input['_mailgate']) : false) {
            if($mailCollector->deleteMails($item->input['_uid'], MailCollector::ACCEPTED_FOLDER) === true) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }
}