# WP-HEART — Security Documentation (WH-312)

## Read-only guarantee (Release 1.0)

There is no code path that mutates user data: `QueryPolicy` (allow-listed
first keywords `SELECT/SHOW/DESCRIBE/DESC/EXPLAIN/WITH-read-only`, denied
keywords including `INSERT/UPDATE/DELETE/DROP/ALTER/CREATE/TRUNCATE/INTO/
LOAD_FILE/…`, comment and stacked-statement rejection, auto-LIMIT) is
enforced server-side in `QueryExecutor` and `Explainer`. The UI cannot bypass
it. The only `DELETE` statements in the plugin clean up WP-HEART's own
options/transients in `uninstall.php`.

## Layered boundary

```
WordPress authentication → wp_heart_* capability (or manage_options fallback)
→ wp_rest nonce for cookie auth (WordPress core) → REST permission_callback
→ request validation → identifier allowlists → $wpdb->prepare for values
→ read-only policy → sanitized errors
```

- Identifiers (tables/columns/directions) are validated against
  `/^[A-Za-z0-9_$]+$/`, verified against discovery/column allowlists, then
  backtick-quoted. Values always go through `$wpdb->prepare` + `esc_like`.
- Every privileged route defines an explicit `permission_callback`
  (contract-tested: 401 logged-out, 403 unauthorized).
- Errors are laundered via `ErrorSanitizer` (paths, credential fragments and
  SQL internals stripped); raw DB errors never reach REST responses or the UI.
- Credentials are never read, stored, transmitted or logged. The plugin never
  contacts external services (local-first, audited by test: no `http` calls
  in `src/`).

## Privacy

Audit log and internal logger keep metadata only (table names, counts,
durations, outcomes); secrets are redacted, long values truncated, row
contents never persisted. Search previews are truncated to 200 chars and
binary values are masked.

## Deployment hardening (optional)

For high-sensitivity sites, serve WordPress from a least-privilege DB user;
WP-HEART reports the effective grants (`Overview → privileges`, values
`FULL / LIMITED_VISIBLE / RESTRICTED / UNKNOWN`) and degrades gracefully when
`SHOW GRANTS` is hidden. Verified live under a SELECT-only credential:
discovery, summary and reads keep working, privileges are never reported
as FULL, and cache/audit writes degrade silently (no output, no fatals).
A separate SELECT-only DB user is a valid defense-in-depth layer but is
never required for the plugin to function.

## Reporting

Treat any mutation, privilege-escalation or data-leak finding as release
blocking. Regression suites: `QueryPolicyTest` (bypass vectors),
`ContractTest` (auth matrix), `ErrorSanitizerTest` (disclosure).
