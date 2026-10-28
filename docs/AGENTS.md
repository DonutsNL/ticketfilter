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

