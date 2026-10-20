# Changelog

## 2.0.0
- Restructured codebase into modern modular service architecture aligned with modern GLPI standards (`Engine`, `Controller`, `Audit`, `Cron`, `Model`).
- Redesigned pattern configuration modal into 3 focused tabs (*Pattern rules*, *Permissions*, *Notifications & notes*) following native Tabler/Bootstrap design.
- Added comprehensive audit trail database schema, logging service, and GLPI CronTask for automated retention cleanup.
- Added interactive regex pattern simulator and historical ticket test-runner for safe dry-runs.
- Added customizable intervention notes for ticket reopening and auto-solving with dynamic non-repudiation audit placeholders.
- Added configurable sender verification policies (*Requesters match*, *An actor matches*, *Sender domain matches*, *Always*) and unmatched fallback actions (*New ticket*, *Private followup*, *Reject*).
- Added multi-entity isolation checks preventing tickets in restricted entities from being matched across tenant boundaries.
- Added collision guard preventing mass-merging when generic patterns match multiple tickets in the pool.
- Added maximum age restriction for reopening closed tickets to prevent zombie ticket resurrection.
- Added strict mailgate fail-safe exception isolation ensuring errors in pattern processing never stop mail collection.
- Updated GLPI navigation menu entry to *Ticket filter* with direct routing to the plugin configuration dashboard.
- Fixed preg_match false-negative warning storm caused by treating non-matching regex result (0) as an error.
- Fixed premature process termination caused by leftover die() statement in ticket solving routine.
- Fixed unbuffered echo and print statements interfering with CLI cron and JSON responses.
- Fixed HTTP 403 Forbidden on AJAX operations in GLPI 11 by dynamically propagating CSRF tokens and headers across modal and simulator requests.
- Fixed UI locking and button unresponsiveness after errors by adding automatic tab validation focus, resilient button state recovery, and modal backdrop cleanup.
- Fixed mailgate multi-email batch interruption by eliminating mid-batch IMAP mutations and implementing reliable post-batch mailbox moving with explicit IMAP session expunge/logout at shutdown for Office 365 Exchange Online.
- Prioritized original tickets (lowest ID first) during candidate ticket resolution to prevent collision DoS from unauthorized senders.
- Isolated technical merge audit trail into independent private followups (`ITILFollowup` with `is_private=1`, `users_id=0`, `_disablenotif=true`), preventing internal filter metadata from leaking into customer-facing notifications.
- Resolved anonymous and alternative email requester identification in mailgate collections.
- Suppressed bogus `glpi_notimportedemails` error records and synchronized mailgate redirect session counters on successfully merged tickets.
- Added comprehensive architectural mailgate processing flow documentation (`docs/mailgate_flow.md`).
- Full compatibility with GLPI 11 and GLPI 12 with PHP 8.0+.

## 1.4.0
- Updated GLPI version constraints for GLPI 11 compatibility.
- Initial support for PHP 8.0+.

## 1.3.0
- Added MatchSpecificSource option to verify ticket requesters.
- Added SolvedMatchString and SolvedMatchStringLength configuration options.
- Added SuppressNotification option on followups.
- Added ReopenClosedTickets option.

## 1.2.0
- Added TicketHandler helper class for searching and manipulating tickets.
- Added support for adding followups to existing tickets.

## 1.1.0
- Initial implementation of regex pattern matching in preItemAdd hook.
- Added FilterPattern dropdown configuration model.

## 1.0.0
- Initial release of ticketfilter plugin for GLPI.
