# WP-HEART — Developer Documentation (WH-310)

**Version:** 1.6.0 · **Namespace:** `WPHeart\` · **REST:** `wp-heart/v1`

## Architecture

```
Dependency-free admin SPA (assets/js/wp-heart-admin.js, ~23KB, no runtime)
+ token-driven CSS (assets/css/wp-heart-tokens.css + wp-heart-admin.css)
        │  REST wp-heart/v1 (wp.i18n for strings, wp_set_script_translations)
Controllers (src/WPHeart/Rest/Controllers) — transport only, no business logic
        │  TableAssembler composes services
Domain (Classification, Discovery, Diagnostics, Search, Query, Intelligence)
        │  DatabaseAdapterInterface
WpdbAdapter → $wpdb (the only class that touches $wpdb)
```

Dependency direction: `Infrastructure → Application → Domain`. Domain is
testable without WordPress (see `tests/`).

## Module responsibilities

| Module | Owns |
|---|---|
| `Config` | Defaults + validated option overrides (never secrets) |
| `Container` | Lightweight DI (`Plugin::service('discovery')`) |
| `Database` | `WpdbAdapter`, `IdentifierValidator`, `CapabilityInspector` |
| `Discovery` | `TableDiscovery` (all visible tables, no `wp_` assumption), `DatabaseService` |
| `Schema` | Column / index / constraint inspectors, `RelationshipEngine` |
| `Intelligence` | WP context, core recognition, plugin registry/detection, themes, confidence, orphans |
| `Classification` | `ClassificationEngine` (CORE → PLUGIN → THEME_CUSTOM → ORPHAN_CANDIDATE → UNKNOWN) |
| `Diagnostics` | `HealthEngine` + 7 checks in `Diagnostics/Checks` (register more via `register()`) |
| `Security` | `Capabilities` (`wp_heart_*`), `Permission::rest()`, `ErrorSanitizer` |
| `Query` | `QueryPolicy` (deny-list + allow-list) → `QueryValidator` → `QueryExecutor` → `Explainer` |
| `Search` | Bounded, schema-aware LIKE search, ESTIMATED totals |
| `Cache` | Transients (`wp_heart_*`), `flush_all()` on explicit refresh |
| `Audit` | Options ring buffer (500), redacted metadata |
| `Rest` | 16 routes, uniform `{data, meta}` envelopes, per-route `permission_callback` |
| `Logging` | WP_DEBUG-gated, redacting logger |

## Key invariants (enforced by tests)

- `Unknown != Broken`, `Orphan Candidate != Removable`, `Inferred != Confirmed`,
  `Estimated != Exact`, `Cache != Snapshot`.
- Registration on `$wpdb->tables` by a plugin never implies CORE
  (`CoreTableRecognizer` requires a known core suffix — regression-tested).
- Inferred relationships always carry `origin: INFERRED` + evidence.

## Conventions

- PHP 7.4-compatible (no enums, no `str_contains`, no `match`), WPCS style.
- Frontend: zero runtime dependencies by decision D-008 (WordPress lightness).
  One IIFE bundle, `wp.i18n.__` for every user string, CSS variables from
  `wp-heart-tokens.css`, logical CSS properties only (RTL-first).
- Identifiers: validate → (optional allowlist) → backtick-quote. Values: `$wpdb->prepare`.
- Every privileged route: `Permission::rest( Capabilities::* )`.
- User-facing strings: `__()` with `wp-heart` domain (PHP) or `wp.i18n`
  (JS); Arabic pack ships as `languages/wp-heart-ar.{po,mo}` plus
  `wp-heart-ar-wp-heart-admin.json` for the bundle.
- Regenerate translations after string changes with the dev-only
  `wh-compile-lang.php` flow, then re-verify under an `ar` boot.

## Testing

- `php tests/run.php` — 241 checks, zero dependencies (stubs + fakes).
- `php tests/live-db.php` — boots real WP, read-only integration (15 checks).
- `php tests/benchmark.php` — perf budgets (discovery, schema, paging, search).
- `phpunit -c phpunit.xml.dist` — bridge (plain suite + live + perf-gated).
- `phpstan analyse -c phpstan.neon` — level 5, zero errors (WP bootstrap in `tests/phpstan-bootstrap.php`).
- `phpcs --standard=phpcs.xml` — WordPress 2.3 ruleset, zero errors/warnings.
- `node --check assets/js/wp-heart-admin.js` — bundle syntax gate.

## Adding a diagnostic

Implement `DiagnosticInterface`, register in `HealthEngine::with_defaults()`
(or at runtime via the `health` service), cover with a unit test that asserts
severity justification and the issue contract keys.
