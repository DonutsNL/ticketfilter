# TicketFilter Security Remediation & Monitoring Automation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Remediate all 8 Teclib security review findings and quality issues, add source-targeting (Mailgate/API/Web UI), support nighttime monitoring auto-solve (New -> Solved/Closed) via GLPI ticket lifecycle, and implement standard GNU gettext internationalization (.po/.mo).

**Architecture:** Context-aware sender resolution and permission verification (Mailgate vs Interactive), entity-bounded pool searching, lifecycle-compliant status transitions via `Ticket::update()`, UI DOM-XSS hardening, and GNU gettext catalog in `locales/`.

**Tech Stack:** PHP 8.4, GLPI 11/12, Twig, Bootstrap/Tabler, GNU gettext (po/mo).

---

### Task 1: Model & Database Migration: Add Source Targeting & Auto-Solve Columns, Restrict Rights (Findings 3 & Quality)

**Files:**
- Modify: `src/Model/FilterPattern.php`
- Test: `tests/PluginLifecycleTest.php`

- [x] **Step 1: Write failing unit test in `tests/PluginLifecycleTest.php` for FilterPattern rights and new columns**
```php
// Check that FilterPattern enforces 'config' right and defines new source/auto-solve constants
assertCondition(FilterPattern::$rightname === 'config', 'FilterPattern::$rightname must be config');
assertCondition(defined(FilterPattern::class . '::FIELD_APPLY_TO_MAILGATE'), 'FIELD_APPLY_TO_MAILGATE must be defined');
assertCondition(defined(FilterPattern::class . '::FIELD_APPLY_TO_API'), 'FIELD_APPLY_TO_API must be defined');
assertCondition(defined(FilterPattern::class . '::FIELD_APPLY_TO_WEB'), 'FIELD_APPLY_TO_WEB must be defined');
assertCondition(defined(FilterPattern::class . '::FIELD_ENABLE_AUTO_SOLVE'), 'FIELD_ENABLE_AUTO_SOLVE must be defined');
assertCondition(defined(FilterPattern::class . '::FIELD_AUTO_SOLVE_STATUS'), 'FIELD_AUTO_SOLVE_STATUS must be defined');
```

- [x] **Step 2: Run test to verify it fails**
Run: `php tests/PluginLifecycleTest.php`
Expected: FAIL due to missing property `$rightname` or missing constants.

- [x] **Step 3: Update `src/Model/FilterPattern.php`**
Add:
```php
    public static $rightname = 'config';

    public const FIELD_APPLY_TO_MAILGATE = 'apply_to_mailgate';
    public const FIELD_APPLY_TO_API      = 'apply_to_api';
    public const FIELD_APPLY_TO_WEB      = 'apply_to_web';
    public const FIELD_ENABLE_AUTO_SOLVE = 'enable_auto_solve';
    public const FIELD_AUTO_SOLVE_STATUS = 'auto_solve_status';
```
Add explicit permission methods:
```php
    public static function canView(): bool
    {
        return \Session::haveRight('config', READ);
    }

    public static function canCreate(): bool
    {
        return \Session::haveRight('config', UPDATE);
    }

    public function canUpdateItem(): bool
    {
        return \Session::haveRight('config', UPDATE);
    }

    public function canPurgeItem(): bool
    {
        return \Session::haveRight('config', UPDATE);
    }
```
In `install()` / schema creation:
- Add columns:
  - `apply_to_mailgate` tinyint NOT NULL DEFAULT '1'
  - `apply_to_api` tinyint NOT NULL DEFAULT '0'
  - `apply_to_web` tinyint NOT NULL DEFAULT '0'
  - `enable_auto_solve` tinyint NOT NULL DEFAULT '0'
  - `auto_solve_status` tinyint NOT NULL DEFAULT '5' (5 = SOLVED, 6 = CLOSED)
- In the example pattern INSERT query: change `is_active` to `'0'` (quality finding: shipping disabled by default).
- Add incremental migrations via `$migration->addField` for existing installations.

- [x] **Step 4: Run test to verify it passes**
Run: `php tests/PluginLifecycleTest.php`
Expected: PASS

- [x] **Step 5: Commit**
```bash
git add src/Model/FilterPattern.php tests/PluginLifecycleTest.php
git commit -m "fix(security): restrict FilterPattern rights to config and add source targeting fields"
```

---

### Task 2: DOM-XSS Remediation in Simulator (Finding 2)

**Files:**
- Modify: `templates/config/simulator_tab.html.twig`
- Test: manual inspection / browser verification / test assertion

- [x] **Step 1: Inspect lines 251, 260, 281 and 357-365 in `templates/config/simulator_tab.html.twig`**
Review where `innerHTML` is currently assigned with unsanitized values.

- [x] **Step 2: Replace vulnerable innerHTML concatenations with DOM APIs and textContent**
At lines 250-256:
```javascript
const alertDiv = document.createElement('div');
alertDiv.className = 'alert alert-danger py-2 mb-0';
const icon = document.createElement('i');
icon.className = 'fas fa-times-circle me-1';
alertDiv.appendChild(icon);
alertDiv.appendChild(document.createTextNode(' ' + (data.error || 'Syntax error')));
const badge = document.getElementById('sampleSimBadge');
badge.replaceChildren(alertDiv);
```
At line 260:
```javascript
const tokenEl = document.getElementById('resMatchedToken');
if (data.token) {
    const code = document.createElement('code');
    code.textContent = data.token;
    tokenEl.replaceChildren(code);
} else {
    tokenEl.textContent = 'None';
}
```
At line 281:
```javascript
const errDiv = document.createElement('div');
errDiv.className = 'alert alert-danger py-2 mb-0';
const errIcon = document.createElement('i');
errIcon.className = 'fas fa-times-circle me-1';
errDiv.appendChild(errIcon);
errDiv.appendChild(document.createTextNode(' ' + err.message));
document.getElementById('sampleSimBadge').replaceChildren(errDiv);
```
At lines 357-365 in `data.matches.forEach`:
```javascript
data.matches.forEach(m => {
    const tr = document.createElement('tr');

    const tdId = document.createElement('td');
    const aLink = document.createElement('a');
    aLink.href = '{{ glpi_url }}/front/ticket.form.php?id=' + encodeURIComponent(m.ticket_id);
    aLink.target = '_blank';
    aLink.className = 'fw-semibold text-decoration-none';
    aLink.textContent = '#' + m.ticket_id;
    tdId.appendChild(aLink);

    const tdName = document.createElement('td');
    tdName.className = 'small text-truncate';
    tdName.style.maxWidth = '200px';
    tdName.setAttribute('title', m.ticket_name || '');
    tdName.textContent = m.ticket_name || '';

    const tdToken = document.createElement('td');
    const code = document.createElement('code');
    code.textContent = m.token || '';
    tdToken.appendChild(code);

    const tdCol = document.createElement('td');
    const colBadge = document.createElement('span');
    colBadge.className = m.is_collision ? 'badge bg-danger-lt' : 'badge bg-green-lt';
    colBadge.textContent = m.is_collision ? 'Collision' : 'Unique';
    tdCol.appendChild(colBadge);

    tr.appendChild(tdId);
    tr.appendChild(tdName);
    tr.appendChild(tdToken);
    tr.appendChild(tdCol);
    tbody.appendChild(tr);
});
```

- [x] **Step 3: Run existing SimulatorTest to ensure no regressions**
Run: `php tests/SimulatorTest.php`
Expected: PASS

- [x] **Step 4: Commit**
```bash
git add templates/config/simulator_tab.html.twig
git commit -m "fix(security): eliminate DOM-XSS in simulator table by using textContent and DOM APIs"
```

---

### Task 3: Context-Aware Sender Authorization & Followup Rights (Finding 1)

**Files:**
- Modify: `src/Engine/TicketProcessor.php`
- Modify: `src/Engine/FilterEngine.php`
- Test: `tests/SecurityIsolationTest.php`

- [x] **Step 1: Write failing test in `tests/SecurityIsolationTest.php` for web impersonation prevention**
Test that when `Session::getLoginUserID()` is active (interactive/web context), `$item->input['_users_id_requester']` is ignored and identity is derived from `Session::getLoginUserID()`.
Also test that if the user lacks `canAddFollowup` on target ticket, merge is rejected.

- [x] **Step 2: Run test to verify it fails**
Run: `php tests/SecurityIsolationTest.php`
Expected: FAIL (currently trusts input keys).

- [x] **Step 3: Update `TicketProcessor::extractSenderInfo()` and `verifySender()`**
1. Determine context:
   - Check if a valid `MailCollector` is active via `FilterEngine::getActiveMailCollector() !== null`.
   - If NO active mail collector and `Session::getLoginUserID() > 0`:
     - Set `$userId = (int)Session::getLoginUserID();`
     - Look up that user's default email from GLPI `User` object.
     - Never inspect `$item->input['_users_id_requester']` or `$item->input['_head']['from']`!
   - If active mail collector:
     - Derive identity from the mail collector's established requester.
2. In `TicketProcessor::verifySender()`:
   - In interactive context (`Session::getLoginUserID() > 0`), check `$targetTicket->canAddFollowup()`. If false, return `'authorized' => false, 'reason' => 'User lacks followup rights on target ticket'`.

- [x] **Step 4: Run test to verify it passes**
Run: `php tests/SecurityIsolationTest.php`
Expected: PASS

- [x] **Step 5: Commit**
```bash
git add src/Engine/TicketProcessor.php src/Engine/FilterEngine.php tests/SecurityIsolationTest.php
git commit -m "fix(security): prevent sender identity spoofing and enforce target followup rights"
```

---

### Task 4: Mailgate Interception Hardening & Memory Isolation (Finding 4 & Quality)

**Files:**
- Modify: `src/Engine/FilterEngine.php`
- Test: `tests/SecurityIsolationTest.php`

- [x] **Step 1: Write failing test in `tests/SecurityIsolationTest.php` for mailgate verification**
Test that `recordMailgateInterception` rejects processing when no `MailCollector` is on the call stack, even if `_mailgate` and `_uid` are present in `$item->input`.
Test that `$activeCollector->fields['refused_folder']` is NEVER overwritten in runtime memory.

- [x] **Step 2: Run test to verify it fails**
Run: `php tests/SecurityIsolationTest.php`
Expected: FAIL

- [x] **Step 3: Update `FilterEngine.php`**
In `recordMailgateInterception()`:
- Check `self::getActiveMailCollector() !== null`. If null, return immediately.
- Do NOT overwrite `$activeCollector->fields[$refusedFolderKey] = ...`!
- Instead, in `handleMailgateShutdown()`, execute `deleteMails($uid, $targetFolder)` where `$targetFolder` is read from `defined('MailCollector::ACCEPTED_FOLDER') ? MailCollector::ACCEPTED_FOLDER : 'accepted'`.
- Add proper logging via `Toolbox::logInFile` in catch blocks instead of silent Throwable swallowing.

- [x] **Step 4: Run test to verify it passes**
Run: `php tests/SecurityIsolationTest.php`
Expected: PASS

- [x] **Step 5: Commit**
```bash
git add src/Engine/FilterEngine.php tests/SecurityIsolationTest.php
git commit -m "fix(security): restrict mailgate operations to verified call stack and prevent memory corruption"
```

---

### Task 5: Strict Entity Isolation in TicketProcessor & FilterEngine (Finding 5)

**Files:**
- Modify: `src/Engine/TicketProcessor.php`
- Modify: `src/Engine/FilterEngine.php`
- Test: `tests/SecurityIsolationTest.php`

- [x] **Step 1: Write failing test in `tests/SecurityIsolationTest.php` for entity boundaries**
Test that when `entitiesId = 0` (Root Entity), tickets in sub-entities (or other non-root entities) are NOT matched unless recursive is allowed.
Test that `FilterEngine::evaluateTicket()` respects pattern `entities_id` and `is_recursive`.
Test that `%` and `_` wildcards in tokens are escaped in LIKE query.

- [x] **Step 2: Run test to verify it fails**
Run: `php tests/SecurityIsolationTest.php`
Expected: FAIL

- [x] **Step 3: Update `TicketProcessor::searchTicketPool()` and `FilterEngine::evaluateTicket()`**
In `TicketProcessor::searchTicketPool($token, $entitiesId, $isRecursive)`:
- Escape `%` and `_` in token:
  ```php
  $escapedToken = addcslashes($token, '%_');
  $where = [
      'name'       => ['LIKE', '%' . $escapedToken . '%'],
      'is_deleted' => 0,
  ];
  ```
- Unconditionally enforce entity restriction:
  ```php
  if ($entitiesId >= 0) {
      if ($isRecursive && function_exists('getSonsOf')) {
          $where['entities_id'] = getSonsOf('glpi_entities', $entitiesId);
      } else {
          $where['entities_id'] = $entitiesId;
      }
  }
  ```
In `FilterEngine::evaluateTicket()`:
- Check if the pattern's entity scope covers the incoming ticket's `entities_id`:
  ```php
  $patternEntity = (int)($pattern[FilterPattern::FIELD_ENTITIES_ID] ?? 0);
  $patternRecursive = !empty($pattern[FilterPattern::FIELD_IS_RECURSIVE]);
  // Validate entity scope
  ```

- [x] **Step 4: Run test to verify it passes**
Run: `php tests/SecurityIsolationTest.php`
Expected: PASS

- [x] **Step 5: Commit**
```bash
git add src/Engine/TicketProcessor.php src/Engine/FilterEngine.php tests/SecurityIsolationTest.php
git commit -m "fix(security): enforce strict entity boundary isolation and escape LIKE wildcards"
```

---

### Task 6: Execution Source Targeting & Nighttime Monitoring Auto-Solve Lifecycle

**Files:**
- Modify: `src/Engine/FilterEngine.php`
- Modify: `src/Engine/TicketProcessor.php`
- Modify: `src/TicketHandler.php`
- Test: `tests/SecurityIsolationTest.php`

- [x] **Step 1: Write tests for source targeting and auto-solve**
- Test that pattern with `apply_to_web = 0` ignores web requests.
- Test that pattern with `apply_to_mailgate = 1` processes mailgate requests.
- Test that `enable_auto_solve = 1` updates ticket via `Ticket::update()` and respects target status (`SOLVED` or `CLOSED`), without failing on `New` (`INCOMING`) status.

- [x] **Step 2: Run test to verify it fails**
Run: `php tests/SecurityIsolationTest.php`
Expected: FAIL

- [x] **Step 3: Implement Source Targeting in `FilterEngine::evaluateTicket()`**
Detect runtime channel:
- Mailgate: `self::getActiveMailCollector() !== null`
- API: `defined('GLPI_API')`
- Web: `Session::getLoginUserID() > 0 && !isCommandLine() && !defined('GLPI_API')`
Filter patterns based on:
- `apply_to_mailgate`
- `apply_to_api`
- `apply_to_web`

- [x] **Step 4: Implement Safe Auto-Solve in `TicketProcessor::mergeTicket()`**
In `TicketProcessor::mergeTicket()`:
Replace `$DB->update()` on line 600 with:
```php
$targetStatus = (int)($pattern[FilterPattern::FIELD_AUTO_SOLVE_STATUS] ?? CommonITILObject::SOLVED);
$ticketToUpdate = new Ticket();
$ticketToUpdate->update([
    'id'     => $targetTicketId,
    'status' => $targetStatus,
]);
```
And replace the raw reopen update on line 480 with:
```php
$ticketToUpdate = new Ticket();
$ticketToUpdate->update([
    'id'     => $targetTicketId,
    'status' => CommonITILObject::INCOMING,
]);
```
Update `TicketHandler.php:110, 128` similarly to use `$this->ticket->update(...)`.

- [x] **Step 5: Run test to verify it passes**
Run: `php tests/SecurityIsolationTest.php`
Expected: PASS

- [x] **Step 6: Commit**
```bash
git add src/Engine/FilterEngine.php src/Engine/TicketProcessor.php src/TicketHandler.php tests/SecurityIsolationTest.php
git commit -m "feat: implement execution source targeting and safe programmatic status updates"
```

---

### Task 7: UI & Controller Updates (Modal & Pattern Management)

**Files:**
- Modify: `templates/config/pattern_modal.html.twig`
- Modify: `templates/config/patterns_tab.html.twig`
- Modify: `src/Controller/TicketFilterController.php`

- [x] **Step 1: Add Execution Sources & Auto-Solve controls in `pattern_modal.html.twig`**
In Tab 1 ("Rule configuration"):
Add Source Targeting checkboxes (Tabler style):
- `apply_to_mailgate` (checked by default)
- `apply_to_api` (unchecked by default)
- `apply_to_web` (unchecked by default)
In Auto-solve section:
- Checkbox `enable_auto_solve`
- Dropdown `auto_solve_status`: Opgelost (`CommonITILObject::SOLVED` / 5) vs Gesloten (`CommonITILObject::CLOSED` / 6)
- Existing `SolvedMatchString` and `SolvedMatchStringLength`

- [x] **Step 2: Update `TicketFilterController.php` to handle new fields on save**
In `handleSavePattern()`:
Read and sanitize `apply_to_mailgate`, `apply_to_api`, `apply_to_web`, `enable_auto_solve`, `auto_solve_status`.

- [x] **Step 3: Update `patterns_tab.html.twig` table columns**
Show badges for active channels (`Mail`, `API`, `Web`) in soft Tabler badges (`bg-secondary-lt`).

- [x] **Step 4: Verify controller tests and modal data flow**
Run: `php tests/PluginLifecycleTest.php`
Expected: PASS

- [x] **Step 5: Commit**
```bash
git add templates/config/pattern_modal.html.twig templates/config/patterns_tab.html.twig src/Controller/TicketFilterController.php
git commit -m "feat(ui): add execution source selectors and auto-solve options to pattern modal"
```

---

### Task 8: Descriptor, Packaging, and GLPI Compatibility (Findings 6, 7, 8 & Quality)

**Files:**
- Modify: `ticketfilter.xml`
- Modify: `setup.php`

- [x] **Step 1: Update `ticketfilter.xml`**
- Remove or fix the dead link `v1.3.0/ticketfilter_1.3.0.zip`.
- Set GLPI compatibility to `<12.0.0` (quality finding: GLPI 12 unsupported until officially released and tested).
- Align versions cleanly.

- [x] **Step 2: Update `setup.php`**
- Set `PLUGIN_TICKETFILTER_MAX_GLPI` to `'11.9.99'`.

- [x] **Step 3: Commit**
```bash
git add ticketfilter.xml setup.php
git commit -m "chore: correct version descriptors and set max GLPI compatibility to <12.0.0"
```

---

### Task 9: Internationalization: Setup `locales/` (.po / .mo) & Vertaalfuncties

**Files:**
- Create: `locales/ticketfilter.pot`
- Create: `locales/nl_NL.po`
- Create: `locales/nl_NL.mo`
- Create: `locales/en_GB.po`
- Create: `locales/en_GB.mo`
- Modify: `setup.php` (verify locales registration if needed)
- Modify: PHP and Twig templates to ensure all user-facing strings use `__('...', 'ticketfilter')`.

- [x] **Step 1: Scan codebase and ensure all strings use `__('...', 'ticketfilter')`**
Check `TicketProcessor.php`, `FilterEngine.php`, `TicketFilterController.php`, `AuditLog.php`, and Twig files.

- [x] **Step 2: Generate `locales/ticketfilter.pot`**
Generate POT file with all msgids and empty msgstrs.

- [x] **Step 3: Create `nl_NL.po` and `en_GB.po` and compile to `.mo`**
Compile using `msgfmt` command:
`msgfmt locales/nl_NL.po -o locales/nl_NL.mo`
`msgfmt locales/en_GB.po -o locales/en_GB.mo`

- [x] **Step 4: Verify `Plugin::getAvailableLanguages('ticketfilter')` detects `en_GB` and `nl_NL`**
Run quick PHP command: `php -r "require 'src/compat.php'; echo count(glob('locales/*.mo'));"`
Expected: 2

- [x] **Step 5: Commit**
```bash
git add locales/ setup.php
git commit -m "feat(i18n): add GNU gettext locale catalog with nl_NL and en_GB translations"
```

---

### Task 10: Full Regression Testing & Verification

**Files:**
- Modify: `tests/RunAllTests.php`
- Modify: `tests/SecurityIsolationTest.php`

- [x] **Step 1: Run all test files**
Run:
- `php tests/PatternMatcherTest.php`
- `php tests/SimulatorTest.php`
- `php tests/SecurityIsolationTest.php`
- `php tests/PluginLifecycleTest.php`
Expected: 100% PASS

- [x] **Step 2: Run `php tests/RunAllTests.php`**
Verify zero errors.

- [x] **Step 3: Final commit and summary**
```bash
git status
```
