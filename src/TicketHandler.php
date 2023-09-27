<?php
namespace GlpiPlugin\Ticketfilter;

use Ticket;
use Session;
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
     * setStatusToSolved(void) : bool -
     * Updates the status of the loaded ticket to NEW int(1)
     *
     * @param  void              
     * @return bool            Allways returns true
     * @since                  1.2.0
     */
    public function setStatusToSolved() : bool 
    {
        global $DB;
        // Update status
        $update['status'] = CommonITILObject::SOLVED;

        $DB->update(
            $this->ticket->getTable(),
            $update,
            ['id' => $this->getId()]
        );

        return true;
    }

    /**
     * addSolvedMessage() : bool -
     * Adds private followup that plugin reopend the ticket if ticket was closed.
     *
     * @param  void            
     * @return bool          
     * @since                    1.2.0
     */
    public function addReopenedMessage($patternName = '') : bool
    {
        if($ItilFollowup = new ITILFollowup()) {
            $input['items_id']      = $this->getId();
            $input['content']       = "This closed ticket was reopened by the <b>ticketfilter plugin</b><br>
                                       as per configuration in pattern: $patternName.";
            $input['users_id']      = false;
            $input['add_reopen']    = 1;
            $input['itemtype']      = Ticket::class;
            // Check notification config
            if($this->pattern[FilterPattern::SUPPRESNOTIF]) {
                $input['_disablenotif'] = true;
            }
            if($ItilFollowup->add($input) === false) {
                return false;
            } else {
                return true;
            }
        }
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
                    if($this->setStatusToNew()){
                        $this->addReopenedMessage($this->pattern[FilterPattern::NAME]);
                    } else {
                        trigger_error('TicketFilter: Unable to update ticket closed status to new, database available?', E_USER_WARNING);
                    }
                } else {
                    // do nothing, dont add followup.
                    Session::addMessageAfterRedirect(__("<a href='".$this->getTicketURL()."'>A closed ticket with id: ".$this->getId()." was found</a>, but config prevented us from reopening it and adding a followup"), true, WARNING);
                    return false;
                }
            }

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
                    trigger_error('TicketFilter: Unable to add a new followup, database available?', E_USER_WARNING);
                }

                // Assess the title and solve the ticket if matched.
                if($this->pattern[FilterPattern::SOLVEDMATCHSTR]) {
                    if(!empty($this->pattern[FilterPattern::SOLVEDMATCHSTR])) {
                        $p = html_entity_decode($this->pattern[FilterPattern::SOLVEDMATCHSTR]);
                        // Perform the search
                        if(preg_match_all("$p", $item->input['name'], $matchArray)) {
                            // Do we have a match
                            if(is_array($matchArray) && count($matchArray) <> 0 && array_key_exists('solved', $matchArray)) {
                                if(strlen($matchArray['solved']['0']) <= $this->pattern[FilterPattern::SOLVEDMATCHSTRLEN]) {
                                    // Set status to solved.
                                    $this->setStatusToSolved();
                                } else {
                                    trigger_error('TicketFilter: Length of'.$matchArray['solved']['0'].' is longer then allowed by configured Ticket Match String Length', E_USER_WARNING);
                                }
                            } // Solved pattern not found
                        } else {
                            trigger_error("TicketFilter: PregMatch failed! please review the Solved pattern $p and correct it", E_USER_WARNING);
                        }
                    } // No solved pattern configured
                }
                Session::addMessageAfterRedirect(__("<a href='".$this->getTicketURL()."'>New ticket was matched to open ticket: ".$this->getId()." and was added as a followup</a>"), true, INFO);
                return true;
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

    /**
     * searchTicketPool(string $searchString) : array -
     * Search for non deleted tickets using searchstring as needle and return array of found ticket IDs or
     * an empty array with no hits.
     *
     * @param  string $searchString    The needle on which to perform a search in the ticketpool subjects.
     * @return array                   Returns an array of matched ticket IDs.
     * @since                          1.0.0
     */
    public function searchTicketPool(string $searchString) : array
    {
        global $DB;
        $t = new Ticket();
        $r = [];
        foreach($DB->request(
            $t->getTable(),
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
        return $r;
    }
}