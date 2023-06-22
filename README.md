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



# Roadmap;
Buying me cafee is very motivational ☕
https://www.buymeacoffee.com/DonutsNL

or

If you like the plugin assign me some 💫 stars for my stargazer achievement 💪

1. WIP : Add configuration page
    - Add check to validate pattern does not exist in ticket templates;
    - Add feature suggest / support button;
    - Add feature check (New features should not overlap with something that can be done using ticket business rules);
2. Add option to automatically merge duplicates before matching;
3. Add option to detect and change ticket status based on pattern ?<status>;
4. Add option to detect and link monitored assets based on pattern ?<computer> ?<device> ?<etx>;
    - Option to automatically add asset (using template?) when missing in assets (might be usefull in Cloud environments);
    - Option to add special marker to automatically created assets for easy searches in assetmanagement;
5. Add tests
    - Check for hard coded urls;
    - Check (unit test) base functions of plugin;
    - Check database consistancy;
    - Check version references in files;
    - Check copyright headers present;
6. Add documentation on patterns, possible problems and assumptions
    - /^([0-9]+?).*/
    - Assumptions on upstream handling
7. Add ability to automatically link to close tickets while creating a new one;
8. Document plugin behaviour on matching
    - Matches all occurences and adds followups to all;
9. Ability to also search for patterns in ticket body 
    - (consideration could have serious functional or performance impact i.e. a huge emailchain with multiple matches and objects)
10. Ability to only search in tickets from specific source;
11. Add additional debugging options (log match results);
12. Add PHP version check in prereq (minimal 8.x);
13. Add RegMatch availability in prereq;
14. Add version check (against GIT)
    - Add convinient update button;
15. Add ability to only add folowup to first (oldest date) or latest (last date) ticket occurrance when multiple are found instead of adding followup to all occurrances;


