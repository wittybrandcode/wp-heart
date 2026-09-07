# WP-HEART RELEASE REPORT

```
Version:             1.6.0 — metadata snapshots release
Final status:        READY_FOR_1_6_0 (all prior gates hold; snapshots are
                     metadata-only, validated, and tamper-evident)
Git commit/tag:      n/a (site is not a git checkout; ROADMAP.md is the ledger)
Phases completed:    00–29 (all)
Tasks completed:     R0.1–R0.7, WH-001–WH-004, WH-010–WH-012, WH-020–WH-023,
                     WH-030–WH-033, WH-040–WH-041, WH-050–WH-052, WH-060–WH-062,
                     WH-070–WH-073, WH-080–WH-084, WH-090–WH-093, WH-100–WH-107,
                     WH-110–WH-112, WH-120–WH-122, WH-130–WH-132, WH-140–WH-141,
                     WH-150–WH-152, WH-160–WH-162, WH-170–WH-172, WH-180, WH-190,
                     WH-200, WH-210, WH-220, WH-230, WH-240–WH-243, WH-250,
                     WH-260–WH-261, WH-270–WH-277, WH-280–WH-281, WH-290–WH-292,
                     WH-300–WH-302, WH-310–WH-312, WH-320–WH-324
Tasks deferred:      none (future parking-lot items stay out by design)
Open blockers:       none
Tests:               php tests/run.php — 356/356 PASS (20 groups incl.
                     Snapshot, Cli, CronAdvisor)
                     php tests/live-db.php — 18/18 PASS (real 51-table DB,
                     incl. autoload footprint on both autoload schemes)
                     php tests/activate-live.php — 16/16 PASS (install/
                     upgrade/uninstall on live WP 7.1)
                     php tests/benchmark.php — all within budget
                     phpunit -c phpunit.xml.dist — OK (3 tests, 3 assertions)
                     phpstan level 5 — zero errors (WP bootstrap)
                     phpcs WordPress 2.3 — zero errors/warnings
                     node --check bundle — clean
                     ar-boot i18n suite — 8/8 PASS (real Arabic boot)
                     scratch multisite validation — 19/19 PASS (network +
                     sister-site tables, context switching, zero false
                     health alarms; env removed afterwards)
                     live REST proof over HTTP with app-password auth —
                     5/5 PASS (anonymous 401, owners directory, owner
                     scoping, malicious 400, unknown-owner empty)
                     SELECT-only credential validation — 7/7 + 4/4 PASS
                     (discovery/summary/reads work; privileges never FULL;
                     cache+audit degrade silently; stale-error isolation)
Static analysis:     php -l clean (80+ files); PHPStan level 5 zero errors;
                     PHPCS WordPress 2.3 ruleset zero errors/warnings
                     (tools live outside the plugin; composer scripts wired)
Security validation: 40+ injection/policy vectors denied (incl. comment,
                     stacked-statement, INTO OUTFILE, LOAD_FILE, locking reads,
                     CTE smuggling); auth matrix 401/403/allow verified;
                     error-disclosure suite passes; destructive-SQL sweep of
                     src/ returns zero hits outside the policy denylist;
                     19 REST routes registered in live WP, all with
                     permission callbacks
Performance valid.:  cold discovery 51 tables ~142ms; warm cache <1ms;
                     postmeta (215k rows) schema 6.6ms, paged read 1.2ms;
                     payload capped (20-row pages, 2000-edge map cap,
                     dependency-free 23KB admin bundle, zero runtime)
Packaging:           release manifest documented (plugin root + src/ +
                     assets/js+css + languages/); no secrets, no source maps,
                     no dev deps in artifact; version 1.6.0 consistent across
                     wp-heart.php / readme.txt / tests / languages / assets
Documentation:       DOCS/{developer,user,security,api}.md + readme.txt +
                     languages/wp-heart.pot + wp-heart-ar.{po,mo} +
                     wp-heart-ar-wp-heart-admin.json + ROADMAP.md ledger
Known limitations:   metadata cache default 5 min (explicit refresh provided);
                     search covers text-like columns with safety caps;
                     map edges truncate beyond 2000; Release 1.0 is
                     intentionally read-only (no repair/migration/snapshots)

Key architectural decisions:
  D-001 WP-native, zero composer runtime deps (minimal footprint, Req §28)
  D-002 plain-PHP runner now + phpunit.xml.dist for CI (no phpunit binary)
  D-003 committed vanilla assets/ bundle guarantees installability
        without an npm build; React+TS sources + verified vite build
  D-004 transients cache + options audit ring buffer; zero custom tables
  D-005 wp_heart_* capability names (collision-safe WH-080), admins seeded
  D-006 CRITICAL only for missing boot-critical core tables
  D-007 SQL comments rejected outright (bypass-proof over convenience)
  + fix: $wpdb->tables registration by plugins never implies CORE
    (suffix gate; caught by live-DB validation, regression-tested)
  + fix: single-site foreign-prefix tables never CORE (multisite-only
    base-prefix fallback)

Key files/modules changed:
  1.0.0: GREENFIELD BUILD — every file under WP-HEART/ except DOCS/ is new
  (see previous report revision for the full inventory).
  1.1.0: REMOVED React scaffold + Node toolchain (admin/, package.json,
  package-lock.json, node_modules, vite/ts/jest configs); REWROTE
  assets/js/wp-heart-admin.js (token classes, wp.i18n, Row Inspector modal,
  query history, settings context, focus management); ADDED
  assets/css/wp-heart-tokens.css; REWROTE assets/css/wp-heart-admin.css;
  EDITED src/WPHeart/Plugin/Assets.php (tokens css, wp-i18n dep,
  wp_set_script_translations); ADDED languages/wp-heart-ar.{po,mo} +
  wp-heart-ar-wp-heart-admin.json; version bump to 1.1.0 everywhere.
  1.2.0: CLOSED all remaining debts — PHPStan L5 zero errors (new
  tests/phpstan-bootstrap.php; removed dead code + unused deps found by
  analysis); PHPCS WordPress 2.3 zero errors/warnings (74 class docblocks,
  targeted identifier-query ignores, D-009 prose-sniff decision);
  PHPUnit bridge (tests/Phpunit, fixed phpunit.xml.dist) green;
  NEW read-only features: row search (DataController + LikeHelper +
  real esc_like), engine filter, client-side CSV export;
  multisite imagined→verified (sister-site CORE, MS_EXPECTED split,
  19/19 live checks on scratch network, env removed);
                     admin-menu registration verified live (9 entries, capped caps).
  1.3.0: SCOPED VIEWS — owner filter on tables (`owner` param, strict
  allowlist, 400 on abuse, empty-exact on unknown) + `GET /owners`
  directory (slug, name, installed, active, counts); UI owner dropdown,
  explicit filtered-view banner with one-click clearing, overview links,
  pagination preserving filters; Arabic pack extended; proven end-to-end
  over authenticated HTTP (app passwords need WP_ENVIRONMENT_TYPE=local
  or HTTPS on the given site — env left untouched).
  CLOSE-OUT: wp-heart-1.3.0.zip built per manifest (86 files, 0 forbidden)
  and installed from artifact (55 tables incl. fixtures, policy enforced);
  read-only hardening (suppress_errors, not hide_errors; last_error reset;
  silent cache/audit degradation, live-verified twice); whtest fixture
  created (13/13 discovery proof), then fully removed — including an FK
  drop-order lesson (pair before events) applied during cleanup; zero
  residue, 20098 posts intact.
  1.4.0: OPTIONS AUTOLOAD AUDIT — OptionsInspector (exact aggregates,
  both autoload schemes after live measurement exposed the modern
  on/off/auto values) + 3 diagnostics (footprint NOTICE/WARNING,
  top-options INFO with option: affected convention, expired-transient
  NOTICE) wired into health + overview; UI Autoload card + code-styled
  option items; Arabic pack extended (208 strings).
```
