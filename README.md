## _Match additional subject paterns as followups_

In some scenario's upstream ticket systems will be owning the ticket send to GLPI. In this scenario an unique identifier might be added by the upstream ticketing system and the GLPI identifier [GLPI #000001] will never be present.

TicketFilter will allow you to add additional (foreign) paterns that should be matched in order to make sure additional updates from the upstream ticketing system are added as followups and are not created as new ticket.

# Configure
The current version of Ticket Filter does not have an configuration page. Instead the src\Filter.class.php needs to be edited. The Filter.class.php contains a class constant with an array holding the paterns to be matched. 

The patern should contain a named matchgroup with the name 'match', i.e. (?<match>PATERN)

Make sure to test your pattern 
Example: https://regex101.com/r/htaEx7/1