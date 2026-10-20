# ADR 0001: TicketFilter Modernization, Security Hardening, and GLPI 11/12 Alignment

## Status
Accepted

## Context
The `ticketfilter` plugin provides automated ticket matching, followup injection, status updates, and ticket reopening based on regular expressions evaluated against incoming ticket subjects. However, the existing implementation exhibits critical shortcomings:

1. **Mailgate Breakage & Fatal Errors**: Uncaught exceptions bubble into GLPI core mail receivers, and a rogue `die()` call terminates CLI cron processes.
2. **False `preg_match` Error Storms**: Normal non-matches (where `preg_match` returns `0`) were incorrectly treated as errors, spamming GLPI logs with `E_USER_WARNING` notices on every mailgate fetch.
3. **No Audit Trail**: Administrators have no visibility into what tickets were merged, closed, or reopened by what pattern.
4. **No Pattern Simulation**: Administrators cannot test patterns prior to production, risking erroneous matches and false-positive merges.
5. **Security & Data Leak Vulnerabilities**:
   - Lack of sender authorization allows third parties to inject followups into arbitrary tickets if an identifier is known or guessed.
   - Injecting a followup may add the attacker as a ticket watcher/requester or trigger email notifications containing confidential ticket histories.
   - Cross-entity queries allow tickets in restricted entities (e.g., HR, Finance) to be modified by emails in public entities.
   - Overly generic regexes can cause "mass merges" across hundreds of tickets.
6. **GLPI 11/12 Compatibility**: GLPI 12 deprecates legacy plugin URL helpers (`Plugin::getWebDir()`), introduces new routing paradigms, and rejects the hardcoded `11.99.99` max GLPI version.

## Decision

We restructure `ticketfilter` to follow the architectural patterns and coding standards established in the `samlsso` plugin:

1. **Modular Service Layer**:
   - `FilterEngine`: Hook dispatcher with top-level `try ... catch (\Throwable $e)` mailgate isolation.
   - `PatternMatcher`: Regex syntax validation, delimiter normalization, and safe execution with `preg_last_error_msg()`.
   - `TicketProcessor`: Controlled followup creation, actor isolation (preventing unauthorized user promotion), and safe status updates.
   - `AuditLogger`: Persistent logging of all actions and errors to `glpi_plugin_ticketfilter_logs`.
   - `Simulator`: Interactive ad-hoc and historical ticket dry-run engine.
2. **Security Controls**:
   - Configurable `sender_policy`: Strict Requester, Any Ticket Actor, Same Domain, or Open.
   - Configurable `unmatched_sender_action`: Create standard ticket (default), add as private followup, or drop.
   - Strict `entities_id` scoping to prevent cross-entity data leakage.
   - Collision guard aborting merges when a pattern matches more than 1 ticket.
   - Maximum age limit on reopening closed tickets.
   - Compulsory notification suppression (`_disablenotif = true`) on automated updates.
3. **Database Lifecycle & Migrations**:
   - Model entities define static `install(Migration $migration)` and `uninstall(Migration $migration)`.
   - Plugin constants: `PLUGIN_NAME`, `PLUGIN_TICKETFILTER_VERSION` (2.0.0), `PLUGIN_TICKETFILTER_MIN_GLPI` (11.0.0), `PLUGIN_TICKETFILTER_MAX_GLPI` (12.9.99).
   - Lifecycle classes listed in `PLUGIN_TICKETFILTER_CLASSES`.
4. **GLPI 11 & 12 Compatibility**:
   - Dynamic web directory resolver `ticketfilter_plugin_web_dir()` compatible with GLPI 11 and GLPI 12.
   - Dual interface support: Twig-based configuration with tabbed views for Patterns, Audit Trail, Simulator, and Global Settings.
   - Automated log cleanup via GLPI `CronTask`.

## Consequences
- **Positive**: Complete mailgate isolation ensures email ingestion never breaks. Detailed audit logs provide full operational visibility. Safe simulation prevents production regex mistakes. Security policies prevent ticket infiltration and notification leaks.
- **Negative**: Additional database table (`glpi_plugin_ticketfilter_logs`) requiring periodic log rotation.
