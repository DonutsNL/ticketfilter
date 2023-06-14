<?php

namespace GlpiPlugin\TicketFilter;
use Ticket;
use Config;

class Filter {
    
    /**
     * Method called by pre_item_add hook validates the object and passes
     * it to the RegEx Matching.
     * 
     * @param $item       Expects the Ticket object by refference.
     * @return void  
     * @since           1.0.0             
     */
    public static function PreItemAdd(Ticket $item) : void {
        if(is_object($item)){
            // Validate if there is a subject to match
            if(is_array($item->fields) 
               && key_exists('name', $item->fields) 
               && !empty($item->fields['name'])) {
               
                // TODO: Do something with debuglogging;
                // if(Config::Debug) { $this->log(); }
                
                self::Match($item->input['name']);
            } // else ignore call
        } // else ignore call
    }

    // Dump alle tickets die behandeld zijn in een nice format.
    private static function log($item) {
        // Dump het ticketObj.
        $logPath = pathinfo(__file__)['dirname'] . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR;
        $filename = md5(rand(1, 10000)).'.log';
        file_put_contents($logPath.$filename, var_export($item, true), FILE_APPEND);
    }

    /**
     * Perform a match on the given matchString if found performs
     * a search in the database and will try to perform a merge 
     * operation.
     * 
     * @param $tName    Ticket name containing the Subject
     * @return int      Returns the ticket ID of the matching ticket or 0 on no match. 
     * @since           1.0.0             
     */
    private static function Match(string $tName) : int {
        global $DB;
        $patern = '/.*?(?<match>\(CITI-[0-9]{1,4}\)).+/';
        // Do somthing with fetch all open tickets
        // Separate method gets cleaned reducing memory consumption
        // Possibly cache db results in property to prevent reloading;
        $match = preg_match($patern, $tName, );
        
        var_dump($match);
        $matchString = (count($match) <> 0) ? '%'.$match['match']['0'].'%' : false;


        if($matchString){
            // Get ticket subjects from database where matched field
            $sql = "SELECT id, name 
                    FROM glpi_tickets 
                    WHERE 1=1
                    AND name LIKE '$matchString'
                    AND is_deleted = '0'
                    AND status != '5'";

            $res = $DB->query($sql);
            if($res) {
                while($row = $res->fetch_assoc()){
                    echo "<pre>";
                    print_r($row);
                }
            }
        }
        print($sql);
        die('done');
    }
}