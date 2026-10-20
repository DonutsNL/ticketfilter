# TicketFilter

**TicketFilter** allows you to configure regex match patterns on incoming ticket titles and email subjects. When an incoming message matches an existing ticket in GLPI (for instance, an external tracking ID like `[JIRA-1234]` or `#567`), TicketFilter merges the content as a followup rather than creating a duplicate new ticket. It can also update ticket status to solved if an upstream resolution keyword is detected, or reopen closed tickets within a configurable age window.

---

## Key Features

- **Mailgate Fail-Safe Isolation**: Unconditional exception sandboxing ensures internal errors or regex compilation faults never crash the GLPI mailgate or discard unread emails.
- **Audit Trail & Activity Log**: Dedicated database log (`glpi_plugin_ticketfilter_logs`) and UI tab tracking every merge, reopen, solve, policy skip, and error with direct ticket links.
- **Pattern Simulator & Dry-Runner**: Test-run candidate patterns against custom sample strings or historical production tickets with collision detection before activating.
- **Security & Actor Protection**:
  - Configurable **Sender Verification Policy** (*Strict Requester*, *Any Actor*, *Same Domain*, or *Open*).
  - Configurable **Fallback Behavior** (*Standard New Ticket*, *Private Followup*, or *Reject*).
  - Unknown senders are never elevated to ticket actors.
  - Multi-entity boundary isolation.
  - Collision guard preventing mass-merges if a regex matches multiple tickets in the pool.
- **GLPI 11 & GLPI 12 Compatible**: Fully compatible with GLPI 11 and GLPI 12 using modern Twig templates, controllers, and dynamic asset path resolvers.
- **Automated Retention**: Built-in GLPI `CronTask` (`cleanLogs`) automatically purges audit trail records older than 60 days.

---

## Installation

1. Copy the `ticketfilter` directory into your GLPI `plugins/` or `marketplace/` directory.
2. In GLPI, navigate to **Setup > Plugins**.
3. Click **Install**, then **Enable** for TicketFilter.
4. Navigate to **Setup > Dropdowns > TicketFilter > Filterpatterns** or click **Configure** on the plugin card to manage rules, review the audit trail, or run simulations.

---

## Creating a Pattern

Patterns are standard regular expressions:
* Must include the named match group: `(?<match>PATTERN)` (e.g., `/.*?(?<match>\(JIRA-[0-9]+\)).*/i`).
* Solved patterns must include the named group: `(?<solved>PATTERN)` (e.g., `/.*?(?<solved>Resolved|Closed).*/i`).
* Use the built-in **Pattern Simulator** tab to test your regex before saving.

---

## Architecture & Code Standards

TicketFilter is structured according to the architectural patterns of the **samlsso** plugin:
- Full PSR-12 and PSR-4 namespace mapping (`GlpiPlugin\Ticketfilter\`).
- Strict separation into service layers (`Audit`, `Engine`, `Model`, `Cron`, `Controller`).
- Comprehensive unit test suite in `tests/RunAllTests.php`.
- Strict traceability with Architectural Decision Records in `ADRS/` and version logging in `changelog.md`.
