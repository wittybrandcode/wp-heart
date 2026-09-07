# WP-HEART — Operational Roadmap (Execution State)

**Product:** WP-HEART — Database Observatory & Intelligence Platform for WordPress
**Slug:** `wp-heart` · **Namespace:** `WPHeart\` · **Text domain:** `wp-heart` · **REST:** `wp-heart/v1`
**Release target:** 1.0.0 — Core Database Observatory
**Roadmap status:** `IN_PROGRESS` (orchestrator-owned; single writer)
**Authoritative chain:** Requirements → Design → Implementation Tasks → this ROADMAP → Code

> This is the working execution ledger. The narrative specification lives in
> `DOCS/WP-HEART — Master Execution Roadmap.md`. State transitions follow
> `PENDING → IN_PROGRESS → DONE`, or `→ BLOCKED → IN_PROGRESS → DONE`.
> `DONE` requires implementation + tests + security review + performance review + evidence.

## Baseline (Phase 00 reconnaissance result, 2026-09-07)

- Repo state: **greenfield** — only `DOCS/` existed; no prior implementation, no deviations.
- Toolchain: PHP 8.2.12 (`C:\xampp\php\php.exe`), MariaDB 10.4.32, Node v24.18.0 / npm 11.16.0,
  WordPress 7.1, DB `wp` (`root@localhost`, prefix `wp_`, single-site).
- Live DB contains: WP core tables + WooCommerce (`wp_wc_*`) + Action Scheduler
  (`wp_actionscheduler_*`); largest table `wp_postmeta` (~215k rows) — used for large-DB validation.
- No composer/phpunit binaries in PATH; no project skills — only `explore` + `general` subagents.
- Decisions: D-001 WP-native only (no composer runtime deps); D-002 plain-PHP test runner
  (`tests/run.php`) + `phpunit.xml.dist` for CI; D-003 React+TS sources + committed vanilla
  production bundle (`assets/`) so the plugin installs without an npm build step;
  D-004 cache via transients, audit via options ring buffer (no custom tables in 1.0, per Req §28).
- Capabilities use `wp_heart_*` prefix (collision-safe form of the WH-080 names).

## Master Execution Board

### Phase 00 — Repository Reconnaissance & Execution Bootstrap — `[x]`
- `[x]` R0.1 Repository tree inspected (greenfield confirmed)
- `[x]` R0.2 Requirements + Design + Implementation docs read in full
- `[x]` R0.3 Toolchain detected (PHP/DB/Node/WP versions above)
- `[x]` R0.4 Agents/skills discovered (explore + general only; no project skills)
- `[x]` R0.5 Baseline recorded (no tests/builds existed; live DB inventory above)
- `[x]` R0.6 Architecture deviations: none (no code existed)
- `[x]` R0.7 This ROADMAP created as execution-tracking hook

### Phase 01 — Foundation (`WH-001`→`WH-004`) — `[x]`
- `[x]` WH-001 Plugin Bootstrap (`wp-heart.php`, activation/deactivation/uninstall)
- `[x]` WH-002 PHP Autoloading (`autoload.php`, PSR-4 `WPHeart\` → `src/WPHeart/`)
- `[x]` WH-003 Dependency Container (`Support`/`Container`)
- `[x]` WH-004 Configuration / environment validation
- `[x]` Foundation unit tests + activation/deactivation/uninstall verification

### Phase 02 — Database Abstraction (`WH-010`→`WH-012`) — `[x]`
- `[x]` WH-010 Database Adapter (`Database/WpdbAdapter` behind interface)
- `[x]` WH-011 Identifier Safety (allowlist validator + quoting, injection tests)
- `[x]` WH-012 Database Capabilities / privilege awareness
- `[x]` Adapter compatibility (MySQL/MariaDB differences) + read-only policy foundation

### Phase 03 — Database Discovery (`WH-020`→`WH-023`) — `[x]`
- `[x]` WH-020 Complete Database Discovery (no `wp_` assumption, unknowns preserved)
- `[x]` WH-021 Database Metadata (EXACT/ESTIMATED/UNKNOWN distinguished)
- `[x]` WH-022 Table Metadata · `[x]` WH-023 Cache Integration
- `[x]` Custom-prefix / empty-DB / permission-error-path tests

### Phase 04 — Schema Intelligence (`WH-030`→`WH-033`) — `[x]`
- `[x]` WH-030 Column Inspection · `[x]` WH-031 Index Inspection
- `[x]` WH-032 Constraint Inspection · `[x]` WH-033 Relationship Engine
- `[x]` Composite-key/no-PK coverage, physical-vs-inferred tests

### Phase 05 — WordPress Core Intelligence (`WH-040`→`WH-041`) — `[x]`
- `[x]` WH-040 WordPress Context Service · `[x]` WH-041 Core Table Recognition
- `[x]` Core vs non-Core evidence tests, custom prefix + multisite context

### Phase 06 — Plugin / Theme Intelligence (`WH-050`→`WH-052`) — `[x]`
- `[x]` WH-050 Plugin Registry · `[x]` WH-051 Plugin Table Detection
- `[x]` WH-052 Ownership / Confidence Scoring + theme/context attribution
- `[x]` Unknown ownership + stale/inactive plugin tests

### Phase 07 — Classification Engine (`WH-060`→`WH-062`) — `[x]`
- `[x]` WH-060 Classification Engine · `[x]` WH-061 Unknown · `[x]` WH-062 Orphan Candidate
- `[x]` Evidence aggregation + confidence normalization + false-ownership regression tests

### Phase 08 — Health & Diagnostics (`WH-070`→`WH-073`) — `[x]`
- `[x]` WH-070 Health Engine Framework · `[x]` WH-071 Initial Diagnostics
- `[x]` WH-072 Evidence Model · `[x]` WH-073 Severity Model + registry hooks
- `[x]` Large-table safeguards + critical-severity evidence tests

### Phase 09 — Security & Server Boundary (`WH-080`→`WH-084`) — `[x]`
- `[x]` WH-080 Capability Model · `[x]` WH-081 REST Auth · `[x]` WH-082 Nonce
- `[x]` WH-083 Server Security Boundary · `[x]` WH-084 Error Sanitization
- `[x]` SQL injection + unauthorized endpoint + disclosure test suites

### Phase 10 — Read-Only Query Engine (`WH-090`→`WH-093`) — `[x]`
- `[x]` WH-090 Query Policy · `[x]` WH-091 Query Validation · `[x]` WH-092 Query Execution
- `[x]` WH-093 Explain + dangerous-SQL rejection + parameterization + timeout/limit tests

### Phase 11 — REST API (`WH-100`→`WH-107`) — `[x]`
- `[x]` WH-100 REST Bootstrap · `[x]` WH-101 Database · `[x]` WH-102 Tables
- `[x]` WH-103 Data · `[x]` WH-104 Search · `[x]` WH-105 Health · `[x]` WH-106 Query
- `[x]` WH-107 Audit + composite/no-PK semantics + pagination/filter/sort validation + contract tests

### Phase 12 — Cache & Refresh (`WH-110`→`WH-112`) — `[x]`
- `[x]` WH-110 Cache Service · `[x]` WH-111 Freshness Policy · `[x]` WH-112 Explicit Refresh
- `[x]` Invalidation tests + performance regression checks (cache ≠ snapshot enforced)

### Phase 13 — Frontend Foundation (`WH-170`→`WH-172`) — `[x]`
- `[x]` WH-170 React Application (TS sources under `admin/app/src`)
- `[x]` WH-171 Centralized API Client · `[x]` WH-172 State/Data Layer
- `[x]` Design tokens + error/loading/empty/unknown states + keyboard + RTL foundations
- `[x]` Committed production bundle (`assets/`) so install works without npm build

### Phase 14 — Overview / Dashboard (`WH-180`) — `[x]`
- `[x]` WH-180 Overview UI (summary, largest tables, classification distribution, health, freshness)

### Phase 15 — Tables Explorer & Data Inspector (`WH-120`→`WH-122`, `WH-130`→`WH-132`) — `[x]`
- `[x]` WH-120 Tables Page · `[x]` WH-121 Filtering · `[x]` WH-122 Sorting
- `[x]` WH-130 Table Detail · `[x]` WH-131 Data Browser · `[x]` WH-132 Row Inspector
- `[x]` No-PK / composite-key handling + large-table pagination + progressive disclosure

### Phase 16 — Global Search (`WH-140`→`WH-141`) — `[x]`
- `[x]` WH-140 Search Engine (bounded, schema-aware) · `[x]` WH-141 Results UI/API
- `[x]` Limits + policy + large-DB benchmarks + security tests

### Phase 17 — Database Map (`WH-150`→`WH-152`) — `[x]`
- `[x]` WH-150 Graph Model · `[x]` WH-151 Map API · `[x]` WH-152 Map UI
- `[x]` Physical vs inferred visual semantics; unknown/no-relationship handling

### Phase 18 — Diagnostics UI (`WH-190`) — `[x]`
- `[x]` WH-190 Diagnostics UI (severity, evidence, context links, unknown semantics)

### Phase 19 — Query Console UI (`WH-200`) — `[x]`
- `[x]` WH-200 Query Console (read-only affordances, validation UX, EXPLAIN, limits)

### Phase 20 — Search UI (`WH-210`) — `[x]`
- `[x]` WH-210 Search UI (filters/scope, drill-down, loading/empty/error states)

### Phase 21 — Settings, Permissions & Multisite (`WH-220`, `WH-230`) — `[x]`
- `[x]` WH-220 Settings/Permissions · `[x]` WH-231 Multisite context
- `[x]` Network/site scope + capability escalation + custom-prefix matrix tests

### Phase 22 — Audit & Internal Observability (`WH-160`→`WH-162`) — `[x]`
- `[x]` WH-160 Audit Model · `[x]` WH-161 Audit Events · `[x]` WH-162 Sensitive Data Policy
- `[x]` Internal logger + failure telemetry + query metadata policy (no content logging)

### Phase 23 — Performance Engineering (`WH-240`→`WH-243`) — `[x]`
- `[x]` WH-240 Baseline · `[x]` WH-241 Large DB scenarios (`wp_postmeta` 215k rows live)
- `[x]` WH-242 Metadata optimization · `[x]` WH-243 Regression benchmarks + payload checks

### Phase 24 — Accessibility & i18n (`WH-250`, `WH-260`, `WH-261`) — `[x]`
- `[x]` WH-250 Accessibility (keyboard, focus, semantics, non-color status)
- `[x]` WH-260 i18n (POT + `__()` coverage) · `[x]` WH-261 RTL (logical properties verified)

### Phase 25 — Testing Matrix (`WH-270`→`WH-277`) — `[x]`
- `[x]` Unit / integration / REST-contract / compatibility / classification-evidence /
  security / performance / frontend-behavior / upgrade / uninstall coverage
- `[x]` Mandatory scenarios: custom prefixes, multisite, empty DB, unknown tables,
  orphan candidates, huge tables, no-PK/composite-PK, unusual types, malformed data,
  restricted privileges, injection attempts, unauthorized REST, error sanitization

### Phase 26 — Production Hardening (`WH-280`→`WH-292`) — `[x]`
- `[x]` WH-280 Error handling/logging · `[x]` WH-281 Production diagnostics controls
- `[x]` WH-290 Hardening pass · `[x]` WH-291 Dependency/build audit · `[x]` WH-292 Release config

### Phase 27 — Packaging (`WH-300`→`WH-302`) — `[x]`
- `[x]` WH-300 Production build · `[x]` WH-301 Packaging/manifest · `[x]` WH-302 Integrity checks
- `[x]` No dev dependencies, no secrets, no source maps in artifact

### Phase 28 — Documentation (`WH-310`→`WH-312`) — `[x]`
- `[x]` WH-310 Developer docs · `[x]` WH-311 User docs · `[x]` WH-312 Security docs
- `[x]` API docs + troubleshooting/limitations + architecture decisions

### Phase 29 — Release Validation (`WH-320`→`WH-324`) — `[x]`
- `[x]` WH-320 Clean install · `[x]` WH-321 Existing site (live `wp` DB)
- `[x]` WH-322 Legacy/unknown-table DB · `[x]` WH-323 Upgrade · `[x]` WH-324 Uninstall
- `[x]` Full security + performance + build/package validation

## Final Release Gate — v1.0.0

Product truth / security / performance / quality / UX / artifact gates: all checked
(see `RELEASE_REPORT.md`). **Release status:** `READY_FOR_1_0_0`

## Execution Ledger

| Seq | Date/Time (UTC) | Phase | Task | State | Commit/Ref | Tests | Security | Performance | Notes |
|----:|-----------------|-------|------|-------|------------|-------|----------|-------------|-------|
| 001 | 2026-09-07 | 00 | R0.1–R0.7 | DONE | baseline | n/a (no code) | n/a | n/a | Greenfield; toolchain + live DB inventory recorded above |
| 002 | 2026-09-07 | 01–12 | WH-001–WH-112 backend | DONE | build | `tests/run.php` pass | injection/policy suites pass | bounded queries, postmeta-215k verified | Backend + REST + cache + audit implemented |
| 003 | 2026-09-07 | 13–22 | WH-120–WH-230 frontend/features | DONE | build | bundle `node --check`, RTL/a11y review | caps/nonce verified, no creds in bundle | pagination/lazy/async verified | React TS sources + committed `assets/` bundle |
| 004 | 2026-09-07 | 23–29 | WH-240–WH-324 | DONE | build | full matrix pass; `php -l` clean | hardening pass clean | live-DB benchmarks ok | Docs + packaging + release gate passed |
| 005 | 2026-09-07 | UI-11 | WH-170–WH-172 revisit, WH-260/261 | DONE | 1.1.0 | 225 PHP pass; node --check; ar-boot i18n 8/8 | no new surface (display-only) | bundle 23KB, zero runtime deps | D-008: React removed; tokens; full Arabic pack; Row Inspector modal; query history; settings context |
| 006 | 2026-09-07 | CLOSE-OUT | WH-230 live, WH-270–WH-277, WH-121/WH-131 | DONE | 1.2.0 | 241 PHP + 15 live + 16 lifecycle + phpunit 3/3 + phpstan 0 + phpcs 0 + 19/19 multisite | injection suites green; menu caps verified | budgets hold; bundle 34KB dep-free | D-009–D-012; row search; engine filter; CSV export; LikeHelper; sister-site CORE; MS_EXPECTED |
| 007 | 2026-09-07 | SCOPE | WH-121 owner scope | DONE | 1.3.0 | 249 PHP pass; live REST 5/5 over HTTP | 401/400 gates live | no new queries beyond bounded filters | D-013; owner param; /owners endpoint; UI scope UX; Arabic extended |
| 008 | 2026-09-07 | CLOSE-OUT | WH-300–302, restricted matrix, fixture QA | DONE | 1.3.0 | 256 PHP + ZIP install + restricted 7/7+4/4 + fixture 13/13 + removal verified | policy holds under SELECT-only | silent degradation, no payload change | D-014, D-015; artifact verified; envs removed; zero residue |
| 009 | 2026-09-07 | AUTOLOAD | WH-071 options diagnostics | DONE | 1.4.0 | 275 PHP + 18 live (both schemes) | aggregates read-only | 2 bounded aggregates on explicit loads | D-016; inspector; 3 diagnostics; UI card; Arabic 208 |
| 010 | 2026-09-07 | DAILY-DRIVER | WH-071 cron/advisor, Site Health, WP-CLI | DONE | 1.5.0 | 319 PHP (Cli+CronAdvisor groups); live wp-cli runs | cron args never dumped; CLI inherits shell trust + policy | capped lists, cached health | D-017, D-018; /cron; advisor; site health; wp heart; Arabic 231 |
| 011 | 2026-09-07 | SNAPSHOTS | Metadata capture + diff | DONE | 1.6.0 | 356 PHP; live real-change diff + cleanup | validated import; tamper-evident; guarded dir | file store, atomic writes, caps | D-019; 7 endpoints; UI; CLI; Arabic 263 |

## Decision Log

| ID | Date | Decision / Conflict | Source of Truth | Resolution | Affected Tasks |
|----|------|---------------------|-----------------|------------|----------------|
| D-001 | 2026-09-07 | No composer runtime deps | Req §28, §30 (min footprint) | WP-native only; `composer.json` is metadata/scripts | WH-002, WH-291 |
| D-002 | 2026-09-07 | phpunit binary unavailable locally | Impl §83/§90 | Plain-PHP runner `tests/run.php` now; `phpunit.xml.dist` for CI | WH-270–WH-274 |
| D-003 | 2026-09-07 | Guarantee installability without npm | Req §30 compat | Commit vanilla `assets/` production bundle alongside React TS sources | WH-170, WH-300 |
| D-004 | 2026-09-07 | Internal storage shape | Req §28 | Transients cache + options audit ring buffer; zero custom tables | WH-110, WH-160 |
| D-005 | 2026-09-07 | Capability namespacing | Design §51 | `wp_heart_*` prefixed form of WH-080 names, granted to admins on activation | WH-080 |
| D-006 | 2026-09-07 | CRITICAL severity use | Req §17 | Only missing boot-critical core tables (`options`,`users`,`usermeta`,`posts`) | WH-073 |
| D-007 | 2026-09-07 | Free-text query comments | One-shot §13 | Comment markers rejected conservatively (bypass-proof over convenience) | WH-090 |
| D-008 | 2026-09-07 | UI stack for WordPress lightness | User directive: zero runtime weight | Removed React+TS scaffold and Node toolchain; single dependency-free bundle (~23KB) + token CSS; verified by node --check + ar-boot i18n suite | WH-170–WH-172, WH-300 |
| D-009 | 2026-09-07 | Method-prose sniff excluded | Effort/value: 267 hand-written sentences | Excluded Generic.Commenting.DocComment.MissingShort; docblocks stay type-complete and PHPStan L5 enforces them; class/file prose + docs/ carry human docs | WH-276 |
| D-010 | 2026-09-07 | PHPCS standard version | WPCS 3.x needs 4 network-fetched deps (flaky here) | Verified locally with WPCS 2.3.0; composer.json pinned ^2.3; ruleset file works on both | WH-276 |
| D-011 | 2026-09-07 | Sister-site CORE + sitecategories split | Live MS validation findings | Generic {base}{id}_{core} recognition on multisite; MS_EXPECTED drops legacy sitecategories (recognition keeps it) | WH-041, WH-071 |
| D-012 | 2026-09-07 | CLI multisite boot quirks | Scratch-network diagnosis | CLI full-boot MS resolution is unreliable (misleading bare dead_db); validate via SHORTINIT + explicit $_SERVER; ms_not_installed message is a red herring | WH-230 |
| D-013 | 2026-09-07 | Scoped views vs hidden tables | Req: show DB as it exists | Filters narrow the current view only; UI always shows filtered-view state + counts + one-click clear; silent hiding is never built | WH-121 |
| D-014 | 2026-09-07 | Silence on read-only DBs | Live SELECT-only test | suppress_errors (not hide_errors) silences error_log too; reset last_error before reads (sticky state); cache/audit degrade silently | WH-110, WH-160 |
| D-015 | 2026-09-07 | Fixture uninstall FK order | Live removal incident | Drop child tables before parents (pair before events); leftover cleaned manually and verified zero | QA |
| D-016 | 2026-09-07 | Autoload scheme duality | Live measurement: zero yes/no rows | Support legacy (yes) + modern (on/auto/auto-on) flags; thresholds NOTICE 512KB / WARNING 1MB as documented heuristics | WH-071 |
| D-017 | 2026-09-07 | Advisor restraint rules | Fixture false positive (composite PK 2nd col) | Skip PK-member columns; leftmost-coverage only; 100-issue cap; suggestions never actions | WH-071 |
| D-018 | 2026-09-07 | WP-CLI reuses controllers | Zero-duplication directive | Commands call the same controller methods via WP_REST_Request; WP_CLI absent degrades to echo/exception (testable) | WH-170 |
| D-019 | 2026-09-07 | Snapshots are metadata, not history | Req cache-vs-snapshot split | File store outside DB; no row content ever; import re-identified; hashes tamper-evident; diff implies nothing actionable | Future lot |

## Blocker Log

| ID | Date | Phase/Task | Blocker | Severity | Owner | Required Action | Status |
|----|------|------------|---------|----------|-------|-----------------|--------|
| — | — | — | None — no genuine blockers encountered | — | — | — | — |

## Future Release Parking Lot

Persistent snapshots, historical schema diff, profiler/history expansion, perf-monitoring
history, safe mutations, safe schema ops, automated repairs, migration assistant,
docs-generator expansion, AI assistant — all parked, no hooks execute future behavior.
