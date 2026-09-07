=== WP-HEART ===
Contributors: wp-heart
Tags: database, developer tools, diagnostics, db inspector, health
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Database Observatory & Intelligence Platform for WordPress. Observe, classify, search and diagnose the real database. Read-only in 1.0.

== Description ==

WP-HEART is a developer-first database observability platform:

* Discover every accessible table — Core, plugin, theme, custom, legacy, unknown. Never hides unknown tables.
* Inspect schema: columns, indexes, constraints, physical vs inferred relationships.
* Evidence-backed ownership classification (CORE / PLUGIN / THEME_CUSTOM / UNKNOWN / ORPHAN_CANDIDATE) with confidence.
* Modular health diagnostics with evidence and severity (unknown is never "broken", orphan is never "removable").
* Bounded data browser, row inspector, global search, relationship map.
* Read-only query console with server-side policy enforcement (Release 1.0 performs zero mutations).
* Audit architecture, metadata cache with explicit refresh, multisite + custom-prefix support, RTL + i18n + accessibility.

== Installation ==

1. Upload the `wp-heart` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Open the "HEART" menu (requires the `wp_heart_view_database` capability, granted to Administrators on activation).

No database-wide scan runs on activation and no tables are created. Internal state uses options/transients only.

== Frequently Asked Questions ==

= Will WP-HEART modify my database? =
No. Release 1.0 is strictly read-only. There is no code path that issues INSERT, UPDATE, DELETE, TRUNCATE, ALTER, DROP or CREATE against user data.

= Does it work with custom table prefixes and multisite? =
Yes. The prefix is always read from `$wpdb` and discovery enumerates every accessible table.

= What does ORPHAN_CANDIDATE mean? =
Evidence suggests the owning plugin/theme may no longer be present. It is informational only — never a recommendation to delete.

== Changelog ==

= 1.6.0 =
* Metadata snapshots: capture, list, inspect, compare (Added / Removed /
  Changed on tables, columns, indexes, constraints, classification),
  export download, and validated import — file-based, never row content.
* WP-CLI snapshot commands: create, list, delete, diff.

= 1.5.0 =
* WP-CLI commands: `wp heart overview|tables|health|search|cron|autoload`
  with table/json/count formats (same read-only services as REST).
* Cron observability: wp-cron schedule, Action Scheduler awareness,
  overdue/failed diagnostics, `/cron` endpoint, Overview Scheduled card.
* Read-only index advisor: suggests indexes for reference-like columns,
  capped, never creates anything.
* Site Health integration: read-only mode, autoload, transients tests.

= 1.4.0 =
* Options autoload audit: footprint diagnostics (512KB notice, 1MB
  warning), largest-autoloaded-options attribution, expired-transient
  waste detection — across legacy and modern autoload schemes.
* Overview Autoload card; `option:` affected items render as code,
  never dead links.

= 1.3.0 =
* Scoped views: filter tables by owner plugin (`owner` parameter) and a
  new `owners` directory endpoint (slug, name, active state, counts).
* Tables explorer shows an explicit filtered-view state with one-click
  filter clearing; overview distribution links deep into filtered views.
* Pagination now preserves active filters across pages.

= 1.2.0 =
* Row search inside the data browser (bounded LIKE across text columns).
* Engine filter on the tables explorer.
* Client-side CSV export of the visible page (no server cost).
* Sister-site core recognition on multisite; legacy sitecategories no
  longer raises false health alarms.
* Static analysis gates now execute locally: PHPStan level 5 clean,
  PHPCS WordPress 2.3 clean, PHPUnit bridge green.

= 1.1.0 =
* Unified on the dependency-free admin bundle (React scaffold removed).
* Token-driven redesign of all screens, Row Inspector modal, query history.
* Complete Arabic translation with RTL verification.

= 1.0.0 =
* Initial production release: Core Database Observatory.
