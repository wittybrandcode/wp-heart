# WP-HEART — User Documentation (WH-311)

## Installation

1. Upload the `wp-heart` folder to `/wp-content/plugins/` (use the release ZIP
   contents, not the development checkout: exclude `tests/`, `admin/app/src`,
   `DOCS/`, config files — see WH-301 manifest below).
2. Activate **WP-HEART** in Plugins. Activation is lightweight: no scans, no
   new tables, no data changes. Administrators receive the `wp_heart_*`
   capabilities automatically.
3. Open the **HEART** menu.

Release ZIP manifest (include only):

```
wp-heart.php, uninstall.php, autoload.php, readme.txt
src/, assets/, languages/
```

## Tour- **Overview** — database identity (engine, charset, size as ESTIMATED),
  classification distribution, **autoload footprint** (exact bytes, option
  count, largest contributors), health summary, largest tables.
- **Tables** — every accessible table with classification + confidence,
  an **Owner** column naming the plugin/entity behind each table
  (clickable to scope the view), filter by name/classification/**owner plugin**/engine, sort by
  name/size/rows. Choosing a scope shows an explicit **filtered view**
  banner with one-click clearing — filters narrow the current view, they
  never hide tables from the database itself.
- **Table detail** — structure, indexes, constraints, relationships
  (PHYSICAL vs INFERRED is always labeled), paged data with **row search**
  across text columns, **CSV export** of the visible page (generated locally
  in your browser — zero server cost), and a row inspector modal for
  single-primary-key tables.
- **Map** — relationship graph; dashed INFERRED edges are guesses, never facts.
- **Search** — database-wide bounded search (min 2 chars, per-column caps,
  totals are ESTIMATED).
- **Health** — diagnostics with severity, affected objects, evidence and
  recommendations — now including autoload footprint, largest autoloaded
  options, and expired transients. `UNKNOWN` means “not enough evidence”, never “broken”;
  `ORPHAN_CANDIDATE` means “investigate”, never “delete”.
- **Query** — read-only console (`SELECT`/`SHOW`/`DESCRIBE`/`EXPLAIN` only,
  auto-LIMIT, comments and stacked statements rejected) + EXPLAIN plans.
- **Audit** — who viewed/ran what (metadata only, never row contents).
- **Settings** — cache TTL, page sizes, query caps, capability reference,
  explicit metadata refresh.

## Permissions

`wp_heart_view_database`, `wp_heart_view_data`, `wp_heart_run_queries`,
`wp_heart_view_audit`, `wp_heart_manage_settings`. Administrators hold them
all; grant granularly with a role editor. `manage_options` always implies
access (fallback).

## WP-CLI

Terminal-first developers get the same read-only power without a browser:

```
wp heart overview
wp heart tables --classification=PLUGIN --format=table
wp heart tables --owner=woocommerce --format=count
wp heart health --severity=WARNING
wp heart search "hello world" --format=json
wp heart cron
wp heart autoload
wp heart snapshot-create --label="before update"
wp heart snapshots
wp heart snapshot-diff <id-a> <id-b>
```

Every command supports `--format=table|json|count`. WP-CLI sessions run
with shell privileges by design; the read-only query policy still applies
to everything the commands execute.

## Scheduled tasks & index advice

- **Overview → Scheduled** shows wp-cron totals/overdue plus Action
  Scheduler status; failing hooks surface in **Diagnostics**.
- **Diagnostics** may suggest indexes for reference-like columns. These are
  evidence-backed suggestions only — WP-HEART never creates indexes.
- Findings also appear in **Tools → Site Health** (autoload footprint,
  expired transients, read-only mode).

## Languages & RTL

The interface ships in English with a complete Arabic translation and a
right-to-left layout (verified under a real `ar` boot: `is_rtl()`,
translated strings, script translations and admin render). SQL/code areas
stay left-to-right intentionally.

## Multisite & custom prefixes

Fully supported. The active prefix is read from `$wpdb` on every request;
network tables are interpreted in site context (`Settings → Context` shows
the resolved context).

## Limitations (Release 1.0)

- Strictly read-only: no editing, repair, optimization or migration.
- Metadata cache (default 5 min) — use **Refresh metadata** after external changes.
- Search covers text-like columns and is capped for safety.
- The map truncates edges beyond 2000 for payload safety.
