#  Ticketfilter

> In some scenario's upstream ticket systems or monitoring systems will be owning the origin ticket or asset state sending successive email updates to GLPI. In this scenario the required unique identifier that GLPI uses (i.e. [GLPI #000001]) will never be present. Instead an foreign identifier might be present in the subject. This causes GLPI to create new tickets for each update received by email cluttering the ticket pool. 

> The TicketFilter plugin will allow you to add additional (foreign) paterns TicketFilter will try to match against existing tickets. If a match is found, TicketFilter will add the received email as followup in all tickets that contain the matched string. It will try to prevent notifications to be send to prevent email runaway issues.

## How to install


- Download the latest release from GitHub.
- Rename the folder inside the zipfile to `ticketfilter` (i didnt strip the version... yet)
- Copy the `ticketfilter` folder into the `GLPI_ROOT/marketplace/` folder.
- Open the GLPI plugins page and install the `ticketfilter` plugin.
- Click the `config` box or browse to `Setup > Dropdowns > Ticketfilter > Filterpatterns` to configure the patterns you want ticketfilter to match.

## What does the plugin do?

Basically the plugin is referenced using a so called hook during the GLPI Ticket pre_item_add phase. In this phase the 'to be added' ticket is passed to the plugin. 

The plugin then reads the `name` of the ticket (which is the Subject field of an email send tot GLPI) and tries to match the `TICKET MATCH STRING` patterns you configured. If it finds a pattern it will use the data corresponding with the pattern to perform a search in the ticket database for tickets with the same pattern in their names.

The matching algorithm will evaluate** all **match patterns configured in sequence and **will stop evaluation** when the first succesfull match is made. It will then work with that specific configuration. From this point onward all other configurations are ignored.

If tickets with simular patterns are found, then these tickets are loaded and the new ticket is **added as a new followup** in **each of the found tickets**. The plugin will not discriminate these tickets and will simply add followups to all of them no matter what the state or status. 

When the followup is added succesfully, the creation of a new ticket is canceled and the originating email (if the mailgate was used to create the new ticket) is deleted from the mailbox.

## How to create a pattern?

Ticketfilter uses regular expressions as patterns to be evaluated. These patterns can be created and tested using https://regex101.com/r/htaEx7/1. This link also includes an example to work from. Please consider the following:

* The expression used MUST include a named matchgroup called 'match' i.e. `...(?<match>PATTERN)...`
* The expression should include the regex delimiters i.e. `/.../`
* The following match all expressions should not be used `/(?<match>.*)/` or `/(?<match>.+)/`
* Currently only the `TICKET MATCH STRING` configuration field is used, other fields are for future usage.
* You are able to test you patterns by manually adding tickets with the configured pattern. 

## Do you like to plugin and want more

Buying me caffee is very motivational ☕
https://www.buymeacoffee.com/DonutsNL
or assign me some 💫 stars here and on the plugin page for my stargazer achievements 💪

* Use the issues to suggest new features or help me prioritize

Thank you in advance! 

## Uncertainties and possible issues
1.  This plugin has not been tested with recurring tickets (that contain the matchstring) and will prob break the recurring ticket creation proces.
2.  The current version of the plugin does require you to manually alter a provided PHP file, if it breaks redownload the plugin and start over.

## Roadmap
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

