## _Match additional subject paterns to be added as followups_

In some scenario's upstream ticket systems or monitoring systems will be owning the origin ticket or asset state sending successive email updates to GLPI. In this scenario the required unique identifier that GLPI uses (i.e. [GLPI #000001]) will never be present. Instead an foreign identifier might be present in the subject. This causes GLPI to create new tickets for each update received by email cluttering the ticket pool. 

The TicketFilter plugin will allow you to add additional (foreign) paterns TicketFilter will try to match against existing tickets. If a match is found, TicketFilter will add the received email as followup in all tickets that contain the matched string. It will try to prevent notifications to be send to prevent email runaway issues.

# Installation and configuration;
0. Create a folder 'ticketfilter' inside the GLPI_HOME/marketplace/
1. Copy the contents of this repository into the GLPI_HOME/marketplace/ticketfilter folder.
2. Edit the ticketfilter/src/Filter.php and change the matchstring to your preferences.
3.     See for more information: https://github.com/DonutsNL/ticketfilter/wiki/Editing-the-patern
4. Use the GLPI interface to install and activate the plugin.
5. Test your matchstring by manually adding tickets that have the pattern in their subject.
6. Test the matchstring by sending emails containing the pattern in its subject.
7. Test variations on your pattern to make sure it matches correctly using regex101.com;

The current version of Ticket Filter does not have an configuration page. 
This will be added in the future.

<span style="color:yellow">!The patern should contain a named matchgroup with the name 'match', i.e. (?&lt;match>PATERN)</span>

Make sure to test your patterns
Example: https://regex101.com/r/htaEx7/1

# Uncertainties and possible issues;
1.  This plugin has not been tested with recurring tickets (that contain the matchstring) and will prob break the recurring ticket creation proces.
2.  The current version of the plugin does require you to manually alter a provided PHP file, if it breaks redownload the plugin and start over.

# Roadmap;
1. WIP : Add configuration page
    - Add extensive checks to validate user input en validate pattern correctness;
    -     ^/ .... /$ delimiters should be present
    -     ..(?<match>...) named match group should be present
    -     .+ as singular pattern should not be allowed i.e. /(?<match>.+)/
    -     .* as singular pattern should not be allowed i.e. /(?<match>.*)/  
    - Add check to validate pattern does not exist in ticket templates;
    - Add feature suggest / support button;
    - Add feature check (New features should not overlap with something that can be done using ticket business rules);
3. Add option to automatically merge duplicates before matching;
4. Add option to detect and change ticket status based on pattern ?&lt;status>;
5. Add option to detect and link monitored assets based on pattern ?&lt;computer> ?&lt;device> ?&lt;etc>;
    - Option to automatically add asset (using template?) when missing in assets (might be usefull in Cloud environments);
    - Option to add special marker to automatically created assets for easy searches in assetmanagement;
6. Add tests
    - Check for hard coded urls;
    - Check (unit test) base functions of plugin;
    - Check database consistancy;
    - Check version references in files;
    - Check copyright headers present;
7. Add documentation on patterns, possible problems and assumptions
    - /^([0-9]+?).*/
    - Assumptions on upstream handling
8. Add ability to automatically link to close tickets while creating a new one;
9. Document plugin behaviour on matching
    - Matches all occurences and adds followups to all;
10. Ability to also search for patterns in ticket body 
    - (consideration could have serious functional or performance impact i.e. a huge emailchain with multiple matches and objects)
11. Ability to only search in tickets from specific source;
12. Add additional debugging options (log match results);
13. Add PHP version check in prereq (minimal 8.x);
14. Add RegMatch availability in prereq;
15. Add version check (against GIT)
    - Add convinient update button;
16. Add ability to only add folowup to first (oldest date) or latest (last date) ticket occurrance when multiple are found instead of adding followup to all occurrances;

# Do you like to plugin and want more?
Buying me cafee is very motivational ☕
https://www.buymeacoffee.com/DonutsNL
or
Assign me some 💫 stars here and on the plugin page for my stargazer achievements 💪

Use the issues to suggest new features or help me prioritize

Thank you in advance! 
