# WP-HEART
## Database Observatory & Intelligence Platform for WordPress

### Product Requirements Specification — v1.0

**Document Status:** Product Source of Truth  
**Release Target:** 1.0 — Core Database Observatory  
**Product Type:** WordPress Developer Tool  
**Canonical Product Name:** WP-HEART  
**Canonical Slug:** `wp-heart`  
**Text Domain:** `wp-heart`  
**PHP Namespace:** `WPHeart\`  
**REST Namespace:** `wp-heart/v1`

---

# 1. Product Definition

WP-HEART is a developer-first WordPress database observability and intelligence platform.

Its primary purpose is to expose, inspect, understand, classify, search, and diagnose the **actual database of a WordPress installation**.

WP-HEART does not limit its view to WordPress Core tables.

It must be capable of observing:

- WordPress Core tables
- Plugin-created tables
- Theme-created tables
- Custom application tables
- Legacy tables
- Unknown tables
- Orphaned tables
- Tables belonging to systems that cannot be confidently identified

The fundamental product principle is:

> **SHOW THE DATABASE AS IT ACTUALLY EXISTS.**

The system observes the physical database first, then adds WordPress-aware intelligence on top of that reality.

It must never alter the underlying database reality merely to make the interface simpler.

---

# 2. Product Vision

WP-HEART should become the database command center for WordPress developers.

The developer should be able to open one tool and answer questions such as:

- What tables actually exist?
- How large is each table?
- What is the structure of each table?
- Which columns and indexes exist?
- Which tables belong to WordPress Core?
- Which tables appear to belong to plugins?
- Which tables cannot be identified?
- Which tables appear orphaned?
- What relationships are physically defined?
- What relationships are only inferred from evidence?
- Are there structural or WordPress-specific database health problems?
- Where is specific data stored?
- How can the developer inspect the database safely?
- What evidence supports each classification or diagnostic?

WP-HEART is therefore not merely a database browser.

It is:

> **Database Observation + Database Intelligence + Database Diagnostics for WordPress.**

---

# 3. Product Philosophy

The product is governed by the following priorities:

```text
Accuracy > Convenience
Security > Features
Evidence > Assumptions
Observation > Guessing
Server-side enforcement > Frontend enforcement
Read operations > Mutation
Progressive loading > Full loading
Explicit uncertainty > False certainty
Actual database metadata > Naming conventions
Tested behavior > Intended behavior
```

Every major feature must respect these principles.

---

# 4. Core Conceptual Model

WP-HEART consists conceptually of three layers.

## Layer 1 — Database Reality

This layer observes the actual database.

It answers:

> What exists?

It includes:

- database metadata
- tables
- columns
- indexes
- storage engines
- table sizes
- row estimates
- actual constraints where available
- database capabilities
- schema metadata

This layer must not make assumptions about ownership.

---

## Layer 2 — WP-HEART Intelligence

This layer interprets the observed database.

It answers:

> What does this probably mean?

It includes:

- WordPress Core recognition
- plugin ownership detection
- theme/custom detection
- unknown detection
- orphan detection
- evidence aggregation
- confidence scoring
- WordPress-specific interpretation
- health diagnostics
- inferred relationships

Intelligence must never overwrite database reality.

---

## Layer 3 — Developer Interface

This layer presents the information to the developer.

It answers:

> How can I understand and investigate it efficiently?

It includes:

- Overview
- Tables Explorer
- Schema Inspector
- Data Browser
- Row Inspector
- Search
- Health
- Issues
- Query interface
- Database Map
- Audit
- Settings

The frontend must never directly access `$wpdb`.

All data must pass through the backend/domain layer and the versioned REST API.

---

# 5. Canonical Product Identity

All project documents and implementation artifacts must use the following canonical identity.

| Property | Canonical Value |
|---|---|
| Product Name | WP-HEART |
| Product Description | Database Observatory & Intelligence Platform for WordPress |
| Plugin Slug | `wp-heart` |
| Text Domain | `wp-heart` |
| PHP Namespace | `WPHeart\` |
| REST Namespace | `wp-heart/v1` |
| Main Plugin File | `wp-heart.php` |

The previous working name **WP Database Observatory** is deprecated as a product identity.

It may be used descriptively in documentation only when necessary, but must not create a competing product name, slug, namespace, or API namespace.

---

# 6. Release Strategy

WP-HEART is intended to become a complete production product.

However, the product must be developed through clearly bounded capability layers.

The first production release is:

> **Release 1.0 — Core Database Observatory**

Release 1.0 must be a complete, production-grade observability and inspection product.

It is not a prototype.

It is not a throwaway MVP.

It is not an incomplete proof of concept.

It must be suitable for real developer environments and real WordPress databases.

At the same time, Release 1.0 deliberately does not include arbitrary database mutation.

This creates a clean boundary:

```text
RELEASE 1.0

OBSERVE        ✓
DISCOVER       ✓
INSPECT        ✓
CLASSIFY       ✓
UNDERSTAND     ✓
SEARCH         ✓
DIAGNOSE       ✓
EXPLAIN        ✓
VISUALIZE      ✓
AUDIT          ✓
READ QUERY     ✓

MODIFY DATA   ✗
MODIFY SCHEMA ✗
REPAIR        ✗
MIGRATE       ✗
```

The architecture must nevertheless be extensible enough to support controlled database operations in future releases without redesigning the core system.

---

# 7. Release 1.0 Functional Scope

Release 1.0 must provide the following functional areas.

## 7.1 Database Overview

The Overview provides a high-level representation of the actual database.

It should expose, where available:

- database engine
- database server information
- database name without exposing credentials
- table count
- estimated database size
- WordPress-related table count
- plugin-related table count
- unknown table count
- orphan candidate count
- health summary
- metadata freshness
- discovery status
- diagnostic status

Metadata may be estimated when exact calculation is expensive.

The interface must explicitly distinguish:

```text
Estimated
Exact
Cached
Unavailable
```

where relevant.

---

# 8. Complete Database Discovery

WP-HEART must discover the actual tables accessible to the current WordPress database connection.

The system must:

- inspect the active database dynamically
- discover all visible tables
- support custom WordPress prefixes
- support multisite
- avoid assuming `wp_`
- avoid assuming a fixed number of tables
- avoid hiding tables that are not recognized
- preserve unknown tables in the result set

The system must not assume:

```text
10 tables
100 tables
10,000 rows
```

as fixed limits.

The database may contain millions of rows and a large number of tables.

Discovery must therefore be metadata-oriented and scalable.

---

# 9. Table Classification

Every discovered table must receive an explicit classification state.

The initial classification model is:

```text
CORE
PLUGIN
THEME_CUSTOM
UNKNOWN
ORPHAN_CANDIDATE
```

Classification must be evidence-based.

The system must never claim ownership solely because a table name appears to match a naming pattern.

Possible evidence may include:

- WordPress registered tables
- known Core schema
- active plugin metadata
- plugin source inspection where safely available
- plugin activation state
- table naming conventions
- schema signatures
- known plugin table patterns
- multisite context
- foreign-key metadata
- indexes and column patterns
- other reliable database metadata

Each classification should expose confidence where applicable.

Example:

```text
Table:
wp_example_events

Classification:
PLUGIN

Confidence:
HIGH

Evidence:
- Registered by detected plugin
- Schema signature matches known plugin structure
- Naming pattern consistent
```

The exact evidence model belongs to the Design Specification.

The requirement is that unsupported certainty is prohibited.

---

# 10. Unknown Tables

Unknown tables are first-class database objects.

WP-HEART must never hide a table simply because its ownership cannot be determined.

A table that cannot be confidently associated with Core, a plugin, or another known owner must remain visible as:

```text
UNKNOWN
```

Unknown does not mean:

```text
Broken
Dangerous
Unused
Orphaned
Safe to Delete
```

These concepts must remain separate.

---

# 11. Orphan Detection

WP-HEART may identify tables as:

```text
ORPHAN_CANDIDATE
```

when available evidence suggests that their original owner may no longer be present.

Examples of evidence may include:

- apparent plugin ownership with plugin no longer installed
- known historical plugin table signature
- no current owner detected
- legacy schema characteristics

An orphan candidate is not automatically considered removable.

WP-HEART must never automatically delete or recommend destructive action solely because a table is unknown or orphaned.

---

# 12. Database Schema Inspection

For every accessible table, WP-HEART must provide schema inspection.

The developer must be able to inspect:

- table name
- storage engine
- collation
- character set where available
- estimated/exact size where available
- row estimates
- columns
- column types
- nullability
- defaults
- extra attributes
- primary key
- indexes
- index columns
- index uniqueness
- index cardinality where available
- constraints where available

The system must represent unavailable metadata explicitly rather than inventing values.

---

# 13. Database Relationships

WP-HEART must distinguish between actual database relationships and inferred relationships.

The relationship model must include at minimum:

```text
PHYSICAL
INFERRED
NONE
UNKNOWN
```

## PHYSICAL

A relationship supported by actual database metadata, such as a declared foreign-key constraint.

## INFERRED

A relationship suggested by evidence such as:

- matching column names
- compatible data types
- known WordPress conventions
- plugin schema knowledge
- repeated identifier patterns
- application-level conventions

An inferred relationship must never be presented as an actual database constraint.

## NONE

No relationship has been identified.

## UNKNOWN

The system does not have enough information to determine the relationship.

The absence of a physical foreign key is not itself a database defect.

WP-HEART must treat logical/application-level relationships and physical database constraints as different concepts.

---

# 14. Data Browser

The developer must be able to inspect table rows.

The Data Browser must support:

- server-side pagination
- controlled page sizes
- column-aware rendering
- sorting where safely supported
- filtering where safely supported
- lazy loading
- asynchronous loading
- row selection
- opening an individual row

The system must never attempt to load an entire large table into PHP memory or the browser.

---

# 15. Row Inspector

The Row Inspector must display a complete individual row in a developer-friendly form.

It must support:

- all available columns
- data types
- null values
- long values
- serialized values where appropriate
- JSON values where appropriate
- truncated display with expandable content
- safe handling of binary or non-displayable data
- sensitive-data-aware rendering where necessary

The product must not silently send unnecessary database content to external services.

---

# 16. Global Database Search

WP-HEART must provide a database-wide search capability.

The developer must be able to search for a value across relevant tables and columns without manually opening every table.

Because a WordPress database can be large, search must be designed around:

- query limits
- pagination
- indexed execution where possible
- column selection
- controlled search scope
- asynchronous execution
- result limits
- cancellation or timeout behavior where appropriate

The system must not assume that unrestricted full-database scans are always acceptable.

---

# 17. Database Health Engine

WP-HEART must contain a modular Health Engine.

The Health Engine must inspect the database for evidence-backed issues.

Potential diagnostic categories include:

- structural anomalies
- suspicious indexes
- missing expected WordPress structures
- unusual table configurations
- suspicious storage characteristics
- orphan candidates
- schema inconsistencies
- WordPress-specific database conditions
- performance-relevant structural observations

Each diagnostic should produce:

```text
Issue
Severity
Evidence
Affected object
Explanation
Recommended investigation
```

Diagnostics must not exaggerate severity.

In particular:

> CRITICAL must require clear technical evidence.

The Health Engine must be modular so new diagnostics can be added independently.

---

# 18. Query Console

Release 1.0 may provide a developer Query Console for read operations.

The default policy is:

```text
READ ONLY
```

Supported query categories should be limited to safe read-oriented operations.

The backend—not the frontend—must enforce the read-only policy.

The system must never depend on UI controls such as hiding an Execute button as its security boundary.

Dynamic values must use safe query construction and prepared statements where applicable.

SQL identifiers require separate validation and handling because they cannot be treated like ordinary query parameters.

---

# 19. Query Explain

Where supported by the database engine, WP-HEART should provide query execution-plan information for eligible read queries.

The purpose is diagnostic understanding rather than automatic optimization.

The product must not automatically modify indexes, tables, or schema based on an EXPLAIN result.

---

# 20. Database Map

Release 1.0 may provide a Database Map that visualizes database objects and their relationships.

The map must clearly distinguish:

```text
Physical relationship
Inferred relationship
No known relationship
```

The visual representation must not imply a foreign-key relationship where none exists.

The map is an explanatory interface, not an authority that creates relationships.

---

# 21. Audit Architecture

WP-HEART must be architected so important developer actions can be audited.

Potential events include:

```text
DATABASE_SCAN
TABLE_VIEW
ROW_VIEW
SEARCH
QUERY_EXECUTION
SETTINGS_CHANGE
```

Release 1.0 does not perform destructive database mutations, but audit architecture must already exist so future privileged operations can use the same foundation.

Audit records must avoid storing complete sensitive database content by default.

---

# 22. Security Requirements

Security is a core product requirement.

WP-HEART must use WordPress security mechanisms including:

- authentication
- capability checks
- nonce validation where applicable
- strict request validation
- safe REST permission callbacks
- prepared statements for dynamic values
- identifier validation
- output escaping
- controlled error handling
- backend authorization
- query policy enforcement

The frontend must never be treated as a trusted security boundary.

The plugin must never:

- expose database credentials
- expose connection secrets
- allow privilege escalation
- allow arbitrary file access
- send database content to external services by default
- execute arbitrary destructive SQL in Release 1.0

---

# 23. Database Privilege Model

WP-HEART must recognize that WordPress authentication and database privileges are separate security layers.

The plugin must inspect and respect the privileges of the active database connection.

Release 1.0 must not assume that the plugin can safely switch database users.

The architecture should support a defense-in-depth model:

```text
WordPress Authentication
        ↓
WP-HEART Capability
        ↓
Nonce / Request Validation
        ↓
Backend Authorization
        ↓
Query Policy
        ↓
Database Privileges
```

A separate database account with restricted privileges may be documented as a deployment hardening option for environments requiring stronger database-level isolation.

WP-HEART must never require such an account merely to function in a standard WordPress installation.

---

# 24. Privacy

A WordPress database may contain:

- personal information
- emails
- names
- tokens
- API credentials
- application data
- private content

Therefore WP-HEART must follow a local-first model.

Release 1.0 must not send database content to external services by default.

The product must not require an external SaaS service for core database inspection.

Any future external integration must be explicitly designed, permissioned, and documented.

---

# 25. Performance Requirements

WP-HEART must not negatively affect the public WordPress frontend.

The plugin must avoid expensive operations during ordinary WordPress requests.

The architecture must use:

- metadata caching
- pagination
- lazy loading
- asynchronous REST requests
- incremental diagnostics
- query limits
- bounded result sets
- progressive rendering

The product must not perform complete database scans on every admin page load.

---

# 26. Large Database Requirements

WP-HEART must be designed for databases containing millions of rows.

It must never depend on:

```sql
SELECT * FROM table
```

without bounded pagination.

It must not blindly execute:

```sql
SELECT COUNT(*)
```

against every table on every dashboard request when doing so could be expensive.

Where appropriate, it may use:

- database metadata estimates
- engine statistics
- cached counts
- exact counts on explicit user request

The UI must distinguish:

```text
Estimated
Exact
Cached
```

when the distinction materially affects interpretation.

---

# 27. Caching Requirements

WP-HEART must cache expensive database metadata where appropriate.

The cache exists to avoid repeatedly rediscovering unchanged metadata.

Cached metadata must have:

- freshness information
- invalidation strategy
- explicit refresh capability
- failure handling

Important distinction:

> **Metadata Cache is not a Historical Snapshot.**

A cache represents current-state information retained temporarily for performance.

A snapshot represents historical database state and belongs to a separate future capability layer.

Release 1.0 must not silently turn cache storage into historical database snapshots.

---

# 28. Internal Storage

WP-HEART should avoid creating unnecessary custom database tables for Release 1.0.

Simple plugin settings should use WordPress options where appropriate.

Internal storage should only be introduced when there is a clear architectural requirement.

WP-HEART must not become an unnecessary database burden merely to inspect another database.

---

# 29. Multisite

WP-HEART must support WordPress Multisite.

The product must correctly understand:

- network-level tables
- site-specific tables
- custom prefixes
- multisite table patterns
- current site context
- network context where applicable

The architecture must not assume a single-site installation.

---

# 30. WordPress Compatibility

Release 1.0 must target common WordPress environments using supported MySQL/MariaDB configurations.

The implementation must not assume:

- `wp_` prefix
- fixed table count
- fixed row count
- fixed plugin schema
- existence of foreign keys
- uniform storage engines
- identical database configurations

Legacy and plugin-created structures must be treated as part of the real database environment.

---

# 31. Developer-First User Experience

The interface must feel like a:

> **Database Command Center**

rather than a conventional WordPress Settings page.

The UI should prioritize:

- high information density
- clarity
- fast navigation
- search
- filtering
- technical metadata
- contextual inspection
- side inspectors
- resizable panels where useful
- responsive layouts
- clear status indicators

The product must not imitate phpMyAdmin.

It should instead focus on understanding the WordPress database through a specialized developer workflow.

---

# 32. Primary Navigation

The canonical navigation for Release 1.0 is:

```text
Overview

Database
 ├── Tables
 ├── Map
 └── Search

Diagnostics
 ├── Health
 └── Issues

Developer
 ├── Query
 └── Audit

Settings
 └── Permissions
```

The exact visual implementation belongs to the Design Specification.

---

# 33. Accessibility and Internationalization

Accessibility must be a first-class product requirement.

The interface must support:

- keyboard navigation
- semantic controls
- accessible status communication
- appropriate focus management
- screen-reader-compatible interfaces
- accessible tables and inspectors
- reduced-motion considerations where applicable

The architecture must support internationalization.

RTL must be supported properly rather than treated as a cosmetic afterthought.

---

# 34. Frontend Architecture Requirement

The admin application should use:

```text
React
+
TypeScript
+
Versioned WordPress REST API
```

The frontend must not contain database business logic.

The frontend must never directly access:

```php
$wpdb
```

All domain operations must remain on the backend.

---

# 35. Testing Requirements

Testing is part of the product definition, not an optional implementation detail.

Release 1.0 must include automated testing for:

### Backend

- Unit Tests
- Integration Tests
- WordPress integration tests
- Database integration tests
- REST API tests
- Security tests
- Classification tests
- Health diagnostic tests
- Performance-sensitive tests

### Frontend

- Component tests
- State/logic tests
- REST integration behavior
- accessibility-oriented tests

### Critical scenarios

Tests must cover at minimum:

- custom prefixes
- standard installations
- multisite
- plugin tables
- unknown tables
- orphan candidates
- large tables
- unusual schemas
- missing metadata
- database permission limitations
- REST authorization
- SQL injection attempts
- malformed requests
- malformed database values
- performance-sensitive operations

The implementation architecture must define the concrete testing stack in the Design Specification.

The intended stack is:

```text
PHPUnit
WordPress PHPUnit Test Suite
MySQL/MariaDB integration

Jest
React Testing Library
TypeScript type checking

PHPStan
PHPCS / WordPress Coding Standards
```

---

# 36. Error Handling

WP-HEART must provide useful technical diagnostics without unnecessarily exposing sensitive internals.

Errors should follow the conceptual model:

```text
User-facing explanation
+
Developer diagnostic context
```

The system must avoid leaking:

- database credentials
- filesystem paths
- secrets
- unnecessary SQL internals
- sensitive database content

Internal logging may retain technical details required for debugging, subject to privacy and security constraints.

---

# 37. Logging

WP-HEART should have an internal logging architecture capable of recording events such as:

- discovery failures
- REST errors
- diagnostic failures
- query execution metadata
- security events

Logs must avoid storing complete database rows or sensitive database content by default.

---

# 38. Non-Goals for Release 1.0

WP-HEART Release 1.0 must not become:

- a backup plugin
- a migration plugin
- an automatic database optimizer
- a phpMyAdmin clone
- a general WordPress performance plugin
- a generic hosting control panel
- an unrestricted SQL administration tool

The product remains focused on:

> **Database observability, inspection, intelligence, and diagnostics for WordPress developers.**

---

# 39. Explicitly Deferred Capabilities

The following capabilities are intentionally outside Release 1.0:

```text
INSERT
UPDATE
DELETE
TRUNCATE

ALTER TABLE
DROP TABLE
CREATE TABLE

Automatic Repairs
Database Optimization by Mutation
Migration Workflows
Rollback of Database Mutations
Historical Change Tracking
Persistent Database Snapshots
Advanced Query Profiling
Automated Schema Migration
```

These are not forgotten requirements.

They are deliberately separated into a future **Database Operations** capability layer.

The architecture must be extensible enough to support them later through dedicated security boundaries.

They must never be introduced as shortcuts inside existing read-only services.

---

# 40. Future Product Direction

The long-term product evolution is:

```text
DATABASE OBSERVATORY
        ↓
DATABASE INTELLIGENCE
        ↓
DATABASE DIAGNOSTICS
        ↓
DATABASE OPTIMIZATION
        ↓
DATABASE OPERATIONS
```

Potential future capabilities include:

- persistent database snapshots
- schema diff
- database comparison
- change tracking
- query profiler
- advanced performance analysis
- automated diagnostics
- safe data operations
- safe schema operations
- repair workflows
- rollback mechanisms
- migration assistance
- database documentation generation
- AI-assisted database analysis

These capabilities must be implemented as controlled extensions of the core architecture.

They must not contaminate the security model of Release 1.0.

---

# 41. Product Safety Principle

WP-HEART must follow the principle:

> **Unknown does not mean broken.**
>
> **Orphaned does not mean removable.**
>
> **Inferred does not mean confirmed.**
>
> **Estimated does not mean exact.**
>
> **Visible does not mean trusted.**
>
> **Detected does not mean actionable.**

The product exists to improve developer understanding before developer action.

---

# 42. Release 1.0 Acceptance Criteria

Release 1.0 is considered functionally complete when a developer can:

1. Open WP-HEART and inspect the actual database.
2. Discover all accessible tables.
3. See tables regardless of whether they are recognized by WordPress.
4. Inspect table metadata.
5. Inspect table structure.
6. Inspect all available columns.
7. Inspect indexes.
8. Inspect physical constraints where available.
9. Distinguish physical relationships from inferred relationships.
10. Browse table rows safely.
11. Open an individual row.
12. Search database content within controlled limits.
13. Identify WordPress Core tables.
14. Identify plugin-related tables where evidence permits.
15. Identify unknown tables.
16. Identify orphan candidates where evidence permits.
17. See confidence and evidence behind intelligent classifications.
18. Run health diagnostics.
19. Understand the evidence behind health issues.
20. Execute supported read-only queries safely.
21. View query execution information where supported.
22. Explore database relationships through the Database Map.
23. Understand metadata freshness.
24. Use the plugin on custom-prefix installations.
25. Use the plugin on Multisite.
26. Use the plugin against large databases without attempting to load millions of rows into the browser.
27. Use the plugin without exposing database credentials.
28. Use the plugin without database mutation in Release 1.0.
29. Operate the plugin through secured WordPress capabilities and REST authorization.
30. Use the interface with keyboard-accessible and RTL-compatible workflows.
31. Rely on automated tests covering the critical database, security, REST, and performance paths.

---

# 43. Definition of Done for the Product Requirements

This Requirements Specification is considered complete when it provides an unambiguous answer to:

```text
WHAT IS WP-HEART?
WHAT PROBLEM DOES IT SOLVE?
WHAT IS INCLUDED IN RELEASE 1.0?
WHAT IS NOT INCLUDED?
WHAT ARE THE PRODUCT BOUNDARIES?
WHAT ARE THE SECURITY BOUNDARIES?
WHAT ARE THE PERFORMANCE BOUNDARIES?
WHAT MUST NEVER BE ASSUMED?
WHAT MUST ALWAYS BE EVIDENCE-BASED?
WHAT DOES SUCCESS LOOK LIKE?
```

It does not prescribe implementation details that belong to the Design Specification.

The next document must translate these requirements into the technical architecture without changing the product scope.

---

# 44. Source-of-Truth Rule

The three project documents have the following authority hierarchy:

```text
01 — Product Requirements Specification
             ↓
02 — Design Specification
             ↓
03 — Implementation & Delivery Specification
```

The Requirements Specification defines **what the product must be**.

The Design Specification defines **how the product is architected**.

The Implementation Specification defines **how the architecture is implemented and delivered**.

No lower-level document may silently introduce a feature that changes the product scope defined here.

If a conflict is discovered:

1. identify the conflict;
2. preserve the higher-level requirement;
3. resolve the ambiguity conservatively;
4. document the architectural consequence;
5. update the affected documents together.

No document may evolve independently in a way that creates contradictory product behavior.

---

# 45. Fundamental Product Statement

WP-HEART does not attempt to make the WordPress database look clean.

It attempts to make the WordPress database **understandable**.

Therefore:

> **Observe the database exactly as it exists.**
>
> **Preserve uncertainty where evidence is insufficient.**
>
> **Add WordPress intelligence without rewriting reality.**
>
> **Give developers the evidence they need to understand before they act.**