<?php
namespace GlpiPlugin\Ticketfilter;

use Ticket;
use ITILFollowup;
use CommonITILObject;
use GlpiPlugin\Ticketfilter\FilterPattern;

class TicketHandler{

    private $ticket;             // The referenced ticket object
    private $pattern = false;    // The pattern configuration 
    private $status = '-1';      // Status of this object, 1 if reference ticket is loaded.

    /**
     * initHandler(int ticketId) : bool -
     * Loads a ticket from the database with given ID.
     *
     * @param  int ticketId      identity of the ticket that needs to be loaded
     * @param  array pattern     array holding the pattern used for the match. 
     * @return void              Object is passed by reference, no return values required
     * @since                    1.1.0
     */
    public function initHandler(int $ticketId, array $filterPattern) : bool
    {
        // Load the ticket we need to modify.
        if(is_int($ticketId)){
            if(is_array($filterPattern)){
                $this->pattern = $filterPattern;
            } else {
                return false;
            }

            $this->ticket = new Ticket();
            $this->ticket->getFromDB((integer) $ticketId);
            // Check if we where able to fetch the correct ticket.
            if($this->ticket->fields['id'] == $ticketId){
                $this->status = '1';
                return true;
            } else {
                return false;
            }
        }
    }

     /**
     * getId(void) : int -
     * Returns ticket ID of ticket being handled or (int) 0 if no ticket was loaded by initHandler();
     * 
     * @param  void              
     * @return int               Status identifier or 0 if no ticket was loaded;
     * @since                    1.1.0
     */
    public function getId() : int
    {
        if($this->status == '1') {
            return (int) $this->ticket->fields['id'];
        } else {
            return 0;
        }
    }

    /**
     * getStatus(void) : int -
     * Returns ticket status of ticket being handled or (int) 0 if no ticket was loaded by initHandler();
     * 
     * @param  void              
     * @return int               Status identifier or 0 if no ticket was loaded;
     * @since                    1.1.0
     */
    public function getStatus() : int
    {
        if($this->status == '1') {
            return (int) $this->ticket->fields['status'];
        } else {
            return 0;
        }
    }

    /**
     * setStatusToNew(void) : bool -
     * Updates the status of the loaded ticket to NEW int(1)
     *
     * @param  void              
     * @return bool            Allways returns true
     * @since                  1.2.0
     */
    public function setStatusToNew() : bool 
    {
        global $DB;
        // Update status
        $update['status'] = CommonITILObject::INCOMING;

        $DB->update(
            $this->ticket->getTable(),
            $update,
            ['id' => $this->getId()]
        );

        return true;
    }

    /**
     * addSolvedMessage() : bool -
     * Adds private followup that plugin auto solved the ticket if solvedstring was found.
     *
     * @param  void            
     * @return bool          
     * @since                    1.2.0
     */
    public function addSolvedMessage() : bool
    {
        return true;
    }

    /**
     * processTicket(Ticket obj) : bool -
     * Adds a followup based on the passed ticket to the loaded ticket or returns (bool) false if 
     * no ticket was loaded by initHandler().
     *
     * @param  Ticket $item             
     * @return bool          
     * @since                    1.1.0
     */
    public function processTicket(Ticket $item) : bool
    {
        if($this->status == '1') {
            // Do we need to reopen the closed ticket?
            if($this->getStatus() == CommonITILObject::CLOSED) {
                if($this->pattern[FilterPattern::REOPENCLOSED]) {
                    $this->setStatusToNew();
                } else {
                    // do nothing, let GLPI continue processing the new ticket.
                    return false;
                }
            } else {
                // Add the followup
                if($ItilFollowup = new ITILFollowup()) {
                    // Populate Followup fields
                    $input                  = $item->input;
                    $input['items_id']      = $this->getId();
                    $input['users_id']      = false;
                    $input['users_id']      = (isset($item->input['_users_id_requester'])) ? $item->input['_users_id_requester'] : $input['users_id'];
                    $input['add_reopen']    = 1;
                    $input['itemtype']      = Ticket::class;
                    
                    // Check notification config
                    if($this->pattern[FilterPattern::SUPPRESNOTIF]) {
                        $input['_disablenotif'] = true;
                    }
                    // Unset some stuff
                    unset($input['urgency']);
                    unset($input['entities_id']);
                    unset($input['_ruleid']);

                    if($ItilFollowup->add($input) === false) {
                        return false;
                    }

                    // Assess the title and close the ticket if matched.
                    if($this->pattern[FilterPattern::SOLVEDMATCHSTR]) {
                        // Future use
                        // If matched, add followup with descriptive message;
                        // If matched, update status to solved;
                    }
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
    }

    /**
     * getTicketUrl(void) : string -
     * Returns the url to the ticket loaded by the handler or returns (string) '0' 
     * no ticket was loaded by initHandler().
     *
     * @param  void              
     * @return string            Returns human readable ticket status or (string) '0'
     * @since                    1.1.0
     */
    public function getTicketUrl() : string
    {
        if($this->status == '1') {
            return $this->ticket->getLinkURL($this->getId());
        } else {
            return '0';
        }
    }
}