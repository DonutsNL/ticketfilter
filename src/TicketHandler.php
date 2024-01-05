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
 *  @since     	    1.2.0
 * ------------------------------------------------------------------------
 **/

namespace GlpiPlugin\Ticketfilter;

use Ticket;
use Session;
use ITILFollowup;
use CommonITILObject;
use CommonITILActor;
use GlpiPlugin\Ticketfilter\FilterPattern;

class TicketHandler{

    private $ticket;             // The referenced ticket object
    private $pattern = false;    // The pattern configuration
    private $status = false;     // Status of this object

    /**
     * initHandler(int ticketId, array filterPattern) : bool -
     * Loads a ticket from the database with provided ticket ID
     * and populates used pattern config for various evals.
     *
     * @param  int|Ticket ticket Either an testObject or ticket Id of the referenced ticket that needs to be loaded
     * @param  array pattern     array holding the pattern used for the match.
     * @return void              Object is passed by reference, no return values required
     * @since                    1.1.0
     */
    public function initHandler(int|Ticket $ticket, array $filterPattern) : bool
    {
        // Load the ticket we need to modify.
        if(is_int($ticket)){
            if(is_array($filterPattern)){
                $this->pattern = $filterPattern;
            } else {
                return false;
            }

            $this->ticket = new Ticket();
            $this->ticket->getFromDB((integer) $ticket);
            // Check if we where able to fetch the correct ticket.
            if($this->ticket->fields['id'] == $ticket){
                $this->status = true;
                return true;
            } else {
                return false;
            }
        } else {
            // Load ticket directly for testing purposes.
            // Minimal validation to allow negative assertions
            if(is_object($ticket)) {
                $this->ticket = $ticket;
                $this->pattern = $filterPattern;
                $this->status = true;
            }
        }
    }

     /**
     * getId() : int -
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
     * getStatus() : int -
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
     * setStatusToNew() : bool -
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
     * setStatusToSolved() : bool -
     * Updates the status of the loaded ticket to SOLVED int(5)
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
     * addReopendMessage(string patternName = '') : bool -
     * Adds a followup to indicate that ticketfilter reopend the closed ticket .
     *
     * @param  void
     * @return bool
     * @since                    1.2.0
     */
    public function addReopenedMessage($patternName = '') : bool
    {
        if($itilFollowup = new ITILFollowup()) {
            // Check notification config
            if($this->pattern[FilterPattern::SUPPRESNOTIF]) {
                $input['_disablenotif'] = true;
            }
            $input['items_id']      = $this->getId();
            $input['content']       = "This ticket has been <b>REOPENED</b> by plugin: ticketfilter<br>
                                       as per pattern configuration: $patternName.";
            $input['users_id']      = false;
            $input['add_reopen']    = 1;
            $input['itemtype']      = Ticket::class;
            return ($itilFollowup->add($input) === false) ? false : true;
        }
    }

    /**
     * addSolvedMessage(string patternName = '') : bool -
     * Adds followup to indicate that ticketfilter updated the status to solved.
     *
     * @param  void
     * @return bool
     * @since                    1.2.0
     */
    public function addSolvedMessage($patternName = '') : bool
    {
        if($itilFollowup = new ITILFollowup()) {
            // Check notification config
            if($this->pattern[FilterPattern::SUPPRESNOTIF]) {
                $input['_disablenotif'] = true;
            }
            $input['items_id']      = $this->getId();
            $input['content']       = "This ticket was <b>SOLVED</b> by plugin: ticketfilter<br>
                                       as per configuration of the Solved Pattern in pattern: $patternName.";
            $input['users_id']      = false;
            $input['add_reopen']    = 1;
            $input['itemtype']      = Ticket::class;

            return ($itilFollowup->add($input) === false) ? false : true;
        }
    }
    /**
     * processTicket(Ticket ticket) : bool -
     * Adds a followup based on the passed ticket to the loaded ticket residing in ticketHandler->ticket,
     * also performs validations and evaluates if the loaded ticket needs to be reopend, resolved or assets
     * should be linked based on the provided pattern configuration in ticketHandler->pattern.
     *
     * @param  Ticket $item      // Ticket object received from the pre_item_add hook.
     * @return bool
     * @since                    1.1.0
     */
    public function processTicket(Ticket $item) : bool
    {
        // Process the followup.
        if($this->status == '1') {

            // Evaluate the requesters of both tickets
            if (!$this->verifyRequesters($item)) {
                return false;
            }

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
            if($itilFollowup = new ITILFollowup()) {
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

                if($itilFollowup->add($input) === false) {
                    trigger_error('TicketFilter: Unable to add a new followup, database available?', E_USER_WARNING);
                    return false;
                }

                Session::addMessageAfterRedirect(__("<a href='".$this->getTicketURL()."'>New ticket was matched to open ticket: ".$this->getId()." and was added as a followup</a>"), true, INFO);
            }

            // Assess the title and solve the ticket if matched with the solved match string.
            if($this->pattern[FilterPattern::SOLVEDMATCHSTR] &&
               !empty($this->pattern[FilterPattern::SOLVEDMATCHSTR])) {
                    $p = html_entity_decode($this->pattern[FilterPattern::SOLVEDMATCHSTR]);
                    // Perform the search
                    if(preg_match_all("$p", $item->input['name'], $matchArray)) {
                        // Do we have a match
                        if(is_array($matchArray) && count($matchArray) <> 0 && array_key_exists('solved', $matchArray)) {
                            if(strlen($matchArray['solved']['0']) <= $this->pattern[FilterPattern::SOLVEDMATCHSTRLEN]) {
                                $this->addSolvedMessage($this->pattern[FilterPattern::NAME]);
                                // Set status to solved.
                                $this->setStatusToSolved();
                                print "Ticket updated to solved!<br>";
                                Session::addMessageAfterRedirect(__("<a href='".$this->getTicketURL()."'>New ticket was solved by the plugin!</a>"), true, INFO);
                            } else {
                                print "Solved Patern length issue <br>";
                                trigger_error('TicketFilter: Length of'.$matchArray['solved']['0'].' is longer then allowed by configured Ticket Match String Length', E_USER_WARNING);
                            }
                        } else {// Solved pattern not found
                            print "Patern not found <br>";
                        }
                    } else {
                        print "Pregmatch failed <br>";
                        trigger_error("TicketFilter: PregMatch failed! please review the Solved pattern $p and correct it", E_USER_WARNING);
                    }
            } else{
               print "No solved string found!";
            }
            die();
            return true;
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
     * an empty array with no hits. Can be used without initializing ticketHandler->initHandler()
     *
     * @param  string $searchString    The needle on which to perform a search in the ticketpool subjects.
     * @return array                   Returns an array of matched ticket IDs.
     * @since                          1.2.0
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

     /**
     * verifyRequesters(Ticket $item) : bool -
     * verify if the requesters in the 'to be created' ticket are present
     * in the 'to be merged' ticket. Returns false if a match could not be made.
     *
     * @param  Ticket $item     Ticket object containing the mailgate uid
     * @return bool             Returns true on success and false on failure
     * @since                   1.0.0
     */
    public function verifyRequesters(Ticket $item) : bool
    {
        // Match mailsender with ticket requester.
        if(!$this->pattern[FilterPattern::MATCHSOURCE]) {
            // Config does not require us to verify the users so we eval true.
            return true;
        } else {
            // https://github.com/DonutsNL/ticketfilter/issues/4
            $succesfullUserMatch = false;

            // Get all the users from the ticket Object received from the pre_item_add hook.
            foreach($item->input['_actors']["requester"] as $null => $mailUser){
                $usersToBeMatched[] = [
                    'userId'    => $mailUser['items_id'],
                    'userEmail' => ($mailUser['default_email']) ? $mailUser['default_email'] : $mailUser['alternative_email']
                ];
            }

            // Fetch and match the requesters of the currently processed ticket.
            // With the requesters in the ticket object received from the pre_item_add hook.
            $usrObj = new $this->ticket->userlinkclass();
            $actors = $usrObj->getActors($this->getId());
            foreach ( $actors as $null => $ticketUsers) {
                // Verify there are users assigned to process
                if(is_array($ticketUsers) && count($ticketUsers) > 0) {
                    foreach($ticketUsers as $null => $userType) {
                        // Only evaluate user type requesters ignore watchers and technicians
                        if($userType['type'] == CommonITILActor::REQUESTER){
                            // First search alternative email because we dont want
                            // to match an users_id:int(0) that would match with all anonymous users
                            // assigned to the ticket.
                            if(!empty($userType['alternative_email'])) {
                                if(array_search(($userType['alternative_email']), array_column($usersToBeMatched, 'userEmail')) === false){
                                    echo "no match on email: {$userType['alternative_email']}<br>";
                                }else{
                                    echo "match on email in: {$userType['users_id']}<br>";
                                    $succesfullUserMatch = true;
                                }
                            } else {
                                // Try to match any non zero users_id in usersToBeMatched.
                                if(array_search($userType['users_id'], array_column($usersToBeMatched, 'userId')) === false){
                                    echo "no match on userId: {$userType['users_id']}<br>";
                                }else{
                                    echo "match on userId in: {$userType['users_id']}<br>";
                                    $succesfullUserMatch = true;
                                }
                            }
                        } // Ignore the user that is either watcher or technician @see glpi/src/Ticket_User.php
                    } // foreach
                }// No requesters assigned to ticket
            }// foreach

            // We did not match any user from the email with the referenced ticket
            // Per configuration do not merge the followup.
            return ($succesfullUserMatch) ? true : false;
        }
    }
}
