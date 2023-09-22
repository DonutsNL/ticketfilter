<?php
namespace GlpiPlugin\Ticketfilter;

use Ticket;
use ITILFollowup;
use CommonITILObject;
use GlpiPlugin\Ticketfilter\FilterPattern;

class TicketHandler{

    private $ticket;
    private $pattern = false;
    private $status = '-1';

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
     * getStatusHumanReadable(void) : string -
     * Returns humand readable ticket status of ticket being handled or (string) '0' if 
     * no ticket was loaded by initHandler().
     *
     * @param  void              
     * @return string            Returns human readable ticket status or (string) '0'
     * @since                    1.1.0
     */
    public function getReadableStatus() : string
    {
        if($this->status == '1') {
            switch ($this->ticket->fields['status']) {
                case CommonITILObject::INCOMING:
                    return 'New';
                case CommonITILObject::ASSIGNED:
                    return 'Assigned';
                case CommonITILObject::PLANNED:
                    return 'Planned';
                case CommonITILObject::SOLVED:
                    return 'Planned';
                case CommonITILObject::CLOSED:
                    return 'Closed';
                case CommonITILObject::ACCEPTED:
                    return 'Accepted';     
                case CommonITILObject::OBSERVED:
                    return 'Closed';
                case CommonITILObject::EVALUATION:
                    return 'Closed';
                case CommonITILObject::TEST:
                    return 'Closed';
                case CommonITILObject::QUALIFICATION:
                    return 'Closed';
            }
        } else {
            return '0';
        }
    }

    /**
     * linkAsFollowup(Ticket obj) : int -
     * Returns humand readable ticket status of ticket being handled or (string) '0' if 
     * no ticket was loaded by initHandler().
     *
     * @param  void              
     * @return string            Returns human readable ticket status or (string) '0'
     * @since                    1.1.0
     */
    public function linkAsFollowup(Ticket $item) : bool
    {
        if($this->status == '1') {
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

                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function getTicketUrl() : string
    {
        if($this->status == '1') {
            return $this->ticket->getLinkURL($this->getId());
        } else {
            return false;
        }
    }
}