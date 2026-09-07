# WP-HEART — REST API Reference (`wp-heart/v1`)

All responses: `{ "data": …, "meta": { freshness?, pagination?, … } }`.
Errors: `{ code, message (sanitized), data: { status } }`.

| Method | Route | Cap | Notes |
|---|---|---|---|
| GET | `/database` | view_database | Summary + classification distribution + health counts + `autoload: {bytes, count, top[{name, bytes}], accuracy: EXACT}` |
| POST | `/refresh` | view_database | `flush_all()` cache invalidation |
| GET | `/tables?page&per_page&search&classification&engine&owner&orderby&order` | view_database | `orderby=name/size/rows/classification`; `engine` exact (e.g. INNODB); `owner` exact plugin slug (case-insensitive); totals EXACT |
| GET | `/owners` | view_database | Observed owners: `{slug, name, installed, active, count}`, most tables first |
| GET | `/tables/{table}` | view_database | Full detail (schema + classification + relationships) |
| GET | `/tables/{table}/schema` | view_database | Columns + metadata |
| GET | `/tables/{table}/indexes` | view_database | Grouped indexes |
| GET | `/tables/{table}/relationships` | view_database | Relationships + constraints (`origin` explicit) |
| GET | `/tables/{table}/rows?page&per_page&orderby&order&search` | view_data | Bounded; `search` (2–100 chars) matches text-like columns (first 10); `row_search: {active, columns}` meta; `row_addressing: {mode: single/composite/none}` |
| GET | `/tables/{table}/rows/{id}` | view_data | Single-column PK only; composite/no-PK → 400 + guidance |
| GET | `/search?q&tables&page&per_page` | view_data | `q` 2–100 chars; totals ESTIMATED; capped |
| GET | `/health` | view_database | Status rollup (`ok/attention`), severity counts |
| GET | `/cron` | view_database | Scheduled tasks: `wp_cron {total, overdue, events[]}`, `action_scheduler {available, by_status, overdue_pending, failed_recent[]}` (metadata only, never callback args) |
| GET | `/snapshots` | view_database | Snapshot registry (id, label, created_at, tables) |
| POST | `/snapshots` `{label}` | manage_settings | Capture a metadata snapshot (never row content) |
| GET | `/snapshots/{id}` | view_database | Full snapshot document |
| DELETE | `/snapshots/{id}` | manage_settings | Delete snapshot file + registry |
| GET | `/snapshots/{id}/download` | view_database | `{filename, content}` for client-side download |
| POST | `/snapshots/import` `{snapshot}` | manage_settings | Validated import (5MB cap, re-identified, input ids ignored) |
| GET | `/snapshots/compare?a=&b=` | view_database | Added / Removed / Changed diff with summary counts |
| GET | `/issues?severity` | view_database | Full evidence issues, severity-descending |
| POST | `/query` `{sql}` | run_queries | Read-only; `{rows, columns}` + `{row_count, truncated, elapsed_ms, read_only: true}` |
| POST | `/query/explain` `{sql}` | run_queries | `{plan}` for SELECT/WITH only |
| GET | `/map` | view_database | `{nodes, edges, truncated}`; edges capped at 2000 |
| GET | `/audit?page&per_page&type` | view_audit | Newest-first, EXACT totals |
| GET | `/settings` | view_database | Tunables + capability labels + version |
| POST | `/settings` | manage_settings | Validated + clamped (`cache_ttl` 60–3600, …) |
| GET | `/context` | view_database | Multisite/site/prefix resolution |

## Examples

```http
GET /wp-json/wp-heart/v1/tables?classification=UNKNOWN&per_page=20
GET /wp-json/wp-heart/v1/tables/wp_postmeta/rows?page=2&orderby=meta_id&order=DESC
GET /wp-json/wp-heart/v1/search?q=hello&page=1
POST /wp-json/wp-heart/v1/query
{"sql": "SELECT ID, post_title FROM wp_posts LIMIT 10"}
POST /wp-json/wp-heart/v1/query/explain
{"sql": "SELECT * FROM wp_postmeta WHERE meta_key = 'x'"}
```

Pagination meta: `{page, per_page, total|null, total_mode: EXACT|ESTIMATED|UNKNOWN, has_more}`.
Accuracy labels on metadata: `EXACT | ESTIMATED | CACHED | UNKNOWN | UNAVAILABLE`.
Relationship origins: `PHYSICAL | INFERRED | NONE | UNKNOWN`.
Classification: `CORE | PLUGIN | THEME_CUSTOM | UNKNOWN | ORPHAN_CANDIDATE` + `confidence` + `evidence[]`.
Health issue `affected` entries naming options use the `option:{name}` form
(they are option names, not tables, and must not be linked as tables).
