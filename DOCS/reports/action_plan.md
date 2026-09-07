# WP-HEART Action Plan & Implementation Tracker

This document outlines the strategic plan for developing, bug-fixing, and enhancing the WP-HEART plugin. It includes critical implementation details and a tracking system to monitor progress.

## Execution Tracker Status Legend
- `[ ]` Pending
- `[/]` In Progress
- `[x]` Completed
- `[-]` Blocked / Deferred

---

## Phase 1: Performance Tuning & Storage Management
**Goal:** Reduce database overhead caused by the Options and Transients APIs and ensure smooth operation on large-scale sites.

- `[ ]` **1.1 Enforce Audit Log Ring Buffer Limits**
  - *Implementation:* Modify `AuditStore.php` to strictly enforce a maximum item count (e.g., 50 items) before rotation. Hook into `update_option` to ensure the byte size of the serialized array does not exceed a predefined limit (e.g., 500KB) to protect the Object Cache.
- `[ ]` **1.2 Transient Garbage Collection**
  - *Implementation:* Review `CacheService.php`. Introduce a daily WP-Cron event (`wp_heart_daily_cleanup`) to flush expired transients matching the `wp_heart_*` prefix, as WordPress does not auto-delete expired transients unless accessed.
- `[ ]` **1.3 Graceful Degradation Policy**
  - *Implementation:* Add a threshold check. If the DB response time exceeds a specific limit or if the Object Cache is misconfigured, temporarily disable the internal Audit logger to relieve DB pressure.

## Phase 2: Multisite (WPMU) & WP-CLI Hardening
**Goal:** Ensure seamless compatibility across WordPress multisite networks and resolve CLI-related conflicts.

- `[ ]` **2.1 WP-CLI Multisite Resolution**
  - *Implementation:* Refactor `Cli\HeartCommand.php` to better handle the `SHORTINIT` environment. Add explicit `$_SERVER` superglobal checks and avoid misleading `dead_db` errors when iterating over network sites.
- `[ ]` **2.2 Core Table Recognition in WPMU**
  - *Implementation:* Update `CoreTableRecognizer.php` to accurately identify sub-site core tables (e.g., `wp_2_posts`, `wp_3_options`) versus custom plugin tables. Use `$wpdb->get_blog_prefix( $site_id )` dynamically.

## Phase 3: Security & Reliability Enhancements
**Goal:** Reinforce the security boundaries and ensure fail-safe query execution.

- `[ ]` **3.1 REST API Input Sanitization Audit**
  - *Implementation:* Conduct a full audit of all REST controllers (`Rest/Controllers/*`). Ensure that even though the API is read-only, all `$_GET` parameters (like pagination `page`, `per_page`, sorting `orderby`, `order`) are strictly cast to integers or validated against a strict regex/allowlist before being passed to the `QueryExecutor`.
- `[ ]` **3.2 WpdbAdapter Error Handling**
  - *Implementation:* Ensure that `$wpdb->suppress_errors(true)` does not swallow fatal errors that could lead to white screens. Reset `$wpdb->last_error` explicitly after every internal query to prevent sticky errors from leaking into other WordPress operations.

## Phase 4: Parked Features Implementation
**Goal:** Introduce the deferred features from the roadmap's "Future Release Parking Lot".

- `[ ]` **4.1 Persistent Snapshots & Historical Diff**
  - *Implementation:* Build an interface that allows saving the current DB metadata to a localized JSON file in the `wp-content/uploads/wp-heart/` directory. Implement a schema diff engine to compare the live DB against a saved JSON snapshot.
- `[ ]` **4.2 Query Profiler (Diagnostics UI)**
  - *Implementation:* Hook into `SAVEQUERIES` (if enabled in `wp-config.php`). Create a real-time table view in the frontend to display slow queries, execution times, and caller traces.
- `[ ]` **4.3 AI Assistant Query Engine**
  - *Implementation:* Integrate an LLM client (e.g., OpenAI or local model). Translate natural language prompts ("Show me all orphaned WooCommerce tables") into internal WP-HEART metadata search parameters.

## Phase 5: DevOps & Developer Experience (DX)
**Goal:** Streamline the contribution process and automate quality assurance.

- `[ ]` **5.1 Dockerized Development Environment**
  - *Implementation:* Add a `.wp-env.json` configuration file to spin up a standardized local testing environment containing WordPress, WP-HEART, and dummy plugins/themes for testing table classifications.
- `[ ]` **5.2 CI/CD Pipelines (GitHub Actions)**
  - *Implementation:* Create `.github/workflows/ci.yml`. Include jobs for `composer lint` (PHPCS), `composer analyse` (PHPStan), and `npm run build` (if frontend assets are touched).
- `[ ]` **5.3 Inline Documentation Expansion**
  - *Implementation:* Add DocBlocks (`/** ... */`) to core interfaces (`Container`, `WpdbAdapter`, `ClassificationEngine`) explaining the *why* behind the logic, improving onboarding for new contributors.
