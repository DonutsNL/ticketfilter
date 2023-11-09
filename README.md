#  Ticketfilter
Ticketfilter will allow you to configure ticket title or emailsubject match patterns that, if matched against existing tickets, will add that tickets content as an followup instead of creating a new ticket. This allows you for example to link monitoring systems that use destinct identifiers in their notifications to update GLPI tickets without being aware of the GLPI identifier [GLPI #12345]. This can also be used to link upstream ticket systems like JIRA with GLPI using mail notification. In addition to matching the upstream identifiers, ticket filter will allow you to detect additional terms in the subject that, if detected, will update the ticket status to solved. This feature will ensure that false positive monitoring notifications, or tickets being closed upstream will also be closed in GLPI.

## How to install
- Make sure your installation runs on at least PHP8. 
- Download the latest release from GitHub.
- Rename the folder inside the zipfile to `ticketfilter` (i didnt strip the version... yet)
- Copy the `ticketfilter` folder into the `GLPI_ROOT/marketplace/` folder.
- Open the GLPI plugins page and install the `ticketfilter` plugin.
- Click the `config` box or browse to `Setup > Dropdowns > Ticketfilter > Filterpatterns` to configure the patterns you want ticketfilter to match.

## What does the plugin do?
Each time a ticket is created by GLPI, either manually or by the mailgate, the plugin in called before the ticket is processed. Ticketfilter will then load all the patterns configured in the ticketfilter dropdown. After loading the patterns it start evaluating these patterns using regular expression match against the ticket title (or email subject). If one of the configured patterns matches, it will use the found pattern to perform a search in the ticketpool (not deleted). If one or multiple tickets are found it will add the ticket being processed as an followup to those tickets. After the followup(s) is/are added it will prevent GLPI from creating a new ticket.

Each time a matched ticket is being processed, it will perform additional evaluations. For example it will evaluate the current status of the found ticket. If the ticket is closed, and the given pattern config allow to reopen, it will add the followup to the closed ticket and reopen it with the status 'NEW.' It will also evaluate if it needs to suppress notifications on adding followups. Finally it evaluates the solved pattern in the given ticket title/email subject. If the additional term (for example 'Closed') is found, it will additionally update the ticket status to solved. The plugin will perform these evaluations on each of the tickets found with the configured ticket match string.

## How to create a pattern?
Ticketfilter uses regular expressions as patterns to be evaluated. These patterns can be created and tested using https://regex101.com/r/htaEx7/1. This link also includes an example to work from. Please consider the following:

* The ticket expressions used MUST include a named matchgroup called 'match' i.e. `...(?<match>PATTERN)...`
* The all expression should include the regex delimiters i.e. `/.../`
* The following match all expressions should not be used `/(?<match>.*)/` or `/(?<match>.+)/`
* It is VERY IMPORTANT that the matchstring will only match UNIQUE identifier for example '[GLPI #12345]'
  matching something generic will result in multiple unintended tickets to be matched and processed!
* You are able to test you patterns by manually adding tickets with the configured pattern. 

## Need help?
* Simply ask by creating an issue
* Also used for bugs and suggestions for new features or helping me prioritize

## Do you like to plugin and want more

Assign me some 💫 stars here and on the plugin page for my stargazer achievements 💪

Feeling gracious? 
Buying me caffee is very motivational ☕
https://www.buymeacoffee.com/DonutsNL


## Uncertainties and possible issues
1.  This plugin is manually tested and might still have bugs;
2.  Configuring generic matchstrings like /(?<match>.*)/ might just work but might also fail hard, so always test before flight!
