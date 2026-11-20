# Project & Workspace Instructions

## 1. No Marketing Bloat & "Just There" Features
- **Never add marketing bloat describing characteristics that are silently part of the implementation ("Just There Features").**
- Internal architectural qualities such as exception sandboxing, fail-safes, resilience guarantees, database integrity, and security protections are standard engineering baseline requirements. They must perform their work silently in the backend.
- Never boast about or advertise internal engineering safeguards in administrative screens, settings pages, diagnostic cards, or headers (e.g. do NOT write "Mailgate Resilience Guarantee", "Safe regex engine", or promotional taglines under headers).
- User interfaces, configuration forms, and diagnostics must remain strictly functional, minimalist, and objective.

## 2. Clean, Functional, and Minimalist Interface
- Follow GLPI's native Bootstrap / Tabler design language.
- Avoid "Christmas tree" designs: no rainbow of colored icons, no neon code blocks, no high-saturation pill badges.
- Use soft Tabler badges (`bg-*-lt`), neutral icons (`text-muted`), and standard `<code>` monospace styling.

## 3. Strict Semantic Versioning for Releases
- **Never reuse existing release version numbers.** Every subsequent release or release package containing changes, fixes, or improvements must increment the version number following Semantic Versioning (patch for bug fixes e.g. 2.0.1, minor for backward-compatible features e.g. 2.1.0, major for breaking changes e.g. 3.0.0).
- Update all corresponding version metadata across `setup.php`, plugin XML descriptor (e.g. `ticketfilter.xml`), `changelog.md`, and packaging scripts (`tools/mkzip.sh`) whenever preparing a release.

## 4. Git Remote Synchronization
- **Always push individual fixes to the remote repository.** Never leave verified fixes or commits sitting solely on the local branch. Individual fixes and commits must always be pushed to the remote repository (`origin`) promptly once verified.

## 5. Mandatory Backward-Compatibility & CSP Compliance Verification
- **Always verify GLPI 11 & GLPI 12 backward compatibility before releasing.**
  - **PHP 8 `strict_types=1` vs. GLPI 11 untyped Core APIs**: Never return untyped GLPI Core calls (e.g. `Session::haveRight()`, which returns a bitwise `int` in GLPI 11 vs. `bool` in GLPI 12) directly from typed methods without explicit casts (`(bool)`, `(int)`, `(string)`).
  - **Property type invariance**: Keep version-selected `*Compat` intermediates (`FilterPatternCompat`) so GLPI 11 (untyped `$rightname`) and GLPI 12 (typed `string $rightname`) both compile cleanly.
  - **Schema & facades**: Keep foreign keys `INT UNSIGNED` (`migration:unsigned_keys`), preserve backward-compatible class facades (`Filter`, `FilterPattern`, `TicketHandler`), and support both token-based (GLPI 11) and tokenless (GLPI 12) CSRF workflows.
- **Enforce GLPI Content Security Policy (CSP) compliance (`script-src 'nonce-...' 'unsafe-eval'`):**
  - Declare `'csp_compliant' => true` in `plugin_version_ticketfilter()`.
  - Never use inline event handlers (`onclick`, `onchange`, `oninput`, `onsubmit`, etc.), `javascript:` URLs, PHP-printed `<script>` blocks, or `.innerHTML` DOM sinks. Use `data-*` attributes with delegated listeners inside literal Twig `<script>` blocks (which receive automatic GLPI nonces) and safe DOM builders (`createElement`, `textContent`, `replaceChildren`).
  - Run `php tests/RunAllTests.php` (including `tests/BackwardCompatibilityAndCspTest.php`) before every commit and release.
