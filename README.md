## _Match additional subject paterns to be added as followups_

In some scenario's upstream ticket systems or monitoring systems will be owning the origin ticket or asset state sending successive email updates to GLPI. In this scenario the required unique identifier that GLPI uses (i.e. [GLPI #000001]) will never be present. Instead an foreign identifier might be present in the subject. This causes GLPI to create new tickets for each update received by email cluttering the ticket pool. 

The TicketFilter plugin will allow you to add additional (foreign) paterns TicketFilter will try to match against existing tickets. If a match is found, TicketFilter will add the received email as followup in all tickets that contain the matched string. It will try to prevent notifications to be send to prevent email runaway issues.

# Installation and configuration;
0. Create a folder 'ticketfilter' inside the GLPI_HOME/marketplace/
1. Copy the contents of this repository into the GLPI_HOME/marketplace/ticketfilter folder.
2. Edit the ticketfilter/src/Filter.class.php and change the matchstring to your preferences.
3. Use the GLPI interface to install and activate the plugin.
4. Test your matchstring by manually adding tickets that have the pattern in their subject.
5. Test the matchstring by sending emails containing the pattern in its subject.

The current version of Ticket Filter does not have an configuration page. 
This will be added in the future.

<span style="color:yellow">!The patern should contain a named matchgroup with the name 'match', i.e. (?&lt;match>PATERN)</span>

Make sure to test your patterns
Example: https://regex101.com/r/htaEx7/1

# Uncertainties and possible issues;
1.  This plugin has not been tested with recurring tickets (that contain the matchstring) and will prob break the recurring ticket creation proces.
2.  The current version of the plugin does require you to manually alter a provided PHP file, if it breaks redownload the plugin and start over.
