# WP-HEART — Master Execution Roadmap
## Production Release 1.0 — Core Database Observatory

**Canonical product:** WP-HEART — Database Observatory & Intelligence Platform for WordPress  
**Slug:** `wp-heart`  
**Namespace:** `WPHeart\\`  
**Text domain:** `wp-heart`  
**REST API:** `wp-heart/v1`  
**Execution environment:** OpenCode + Muse Spark 1.3  
**Roadmap status:** `NOT_STARTED`  
**Roadmap owner:** Orchestrator Agent  
**Roadmap is the execution ledger and release gate.

---

## 0. Purpose

This file is the single operational roadmap for implementing WP-HEART from repository state to a production-ready Release 1.0.

The roadmap is not a product specification. Product requirements live in Requirements; architecture lives in Design; implementation detail lives in the Implementation & Delivery specification. This roadmap answers one question only:

> What is the next safe, dependency-correct, testable unit of work, and what has actually been completed?

The orchestrator must update this file continuously during execution. A phase is never marked complete because code exists. A phase becomes complete only when its implementation, integration, tests, security checks, performance criteria, and acceptance evidence are complete.

---

## 1. Authoritative Document Chain

Execution must follow this precedence:

1. `WP-HEART — Requirements Specification.md` — product truth.
2. `WP-HEART — Design Specification.md` — architecture truth.
3. `WP-HEART — Complete Implementation Tasks Specification.md` — implementation truth.
4. `ROADMAP.md` — sequencing, state, gates, and execution evidence.
5. Repository code and tests — actual implementation state.

If a conflict is discovered, stop the affected work, record the conflict in the Decision Log, resolve it against the authoritative chain, and only then continue.

---

## 2. Release 1.0 Boundary

### Included

- Database Overview
- Complete database/table discovery
- Schema inspection
- Columns, indexes, constraints
- Physical vs inferred relationships
- Core WordPress intelligence
- Plugin intelligence
- Theme/context intelligence where supported by evidence
- Evidence-backed ownership/classification
- `UNKNOWN` and `ORPHAN CANDIDATE` visibility
- Tables Explorer
- Data Browser
- Row Inspector
- Global Database Search
- Database Health / Diagnostics Engine
- Read-only Query Console
- Query validation and backend read-only enforcement
- `EXPLAIN` capability where supported and safe
- Database Map / relationship visualization
- Audit architecture and safe event metadata
- Security/capability/nonce/request validation
- Metadata caching and explicit refresh
- Large-database safeguards
- Multisite and custom-prefix support
- React + TypeScript admin interface
- RTL and internationalization foundations
- Accessibility
- Automated testing, static analysis, release validation
- Production packaging and documentation

### Explicitly out of Release 1.0

- Arbitrary `INSERT`, `UPDATE`, `DELETE`
- Destructive DDL such as `DROP`, destructive `ALTER`, truncation, or destructive repair
- Automated database repair
- Migration execution
- Schema-diff execution workflows that mutate the database
- Historical snapshot system as a persistent time-series product
- Automated cleanup of unknown/orphan tables

These belong to later releases behind dedicated safety boundaries.

### Core invariants

`Unknown != Broken`  
`Orphan Candidate != Removable`  
`Inferred != Confirmed`  
`Estimated != Exact`  
`Detected != Actionable`

---

## 3. Execution State Model

Every phase and task uses exactly one of these states:

- `[ ]` `PENDING` — not started.
- `[~]` `IN_PROGRESS` — actively being implemented.
- `[x]` `DONE` — implementation and acceptance evidence complete.
- `[!]` `BLOCKED` — cannot proceed safely; blocker is documented.
- `[-]` `DEFERRED` — intentionally postponed by an explicit architectural/product decision.

Never use `DONE` for “mostly complete”, “UI exists”, “works locally”, or “tests pending”.

### State transition rule

`PENDING → IN_PROGRESS → DONE`

Alternative:

`PENDING → IN_PROGRESS → BLOCKED → IN_PROGRESS → DONE`

A task may move to `DEFERRED` only with a Decision Log entry.

---

## 4. Global Execution Gates

These gates apply to every phase:

### Gate A — Source alignment

- Requirements, Design, and Implementation Tasks have been read.
- Current repository state has been inspected.
- No competing architecture is introduced.

### Gate B — Security

- Authorization is server-side.
- Capabilities are enforced.
- Nonces are used where applicable.
- Request input is validated.
- Dynamic SQL values use prepared statements.
- Identifiers are separately validated/whitelisted.
- Database credentials are never exposed.
- No frontend-only security controls are trusted.

### Gate C — Data safety

- Release 1.0 database operations remain read-only.
- No destructive SQL path exists through UI or REST.
- Unknown/orphan candidates are never automatically deleted.
- Logs do not capture full sensitive database contents by default.

### Gate D — Large database

- No full-table loading into PHP memory.
- No full-table browser payloads.
- Pagination/lazy loading/batching is used.
- Expensive metadata is cached or explicitly requested.
- Exact counts are not performed unnecessarily.

### Gate E — Evidence integrity

- Confirmed facts are distinguished from inference.
- Relationship type is explicit: `PHYSICAL`, `INFERRED`, `NONE`, `UNKNOWN`.
- Classification carries evidence/confidence.
- Missing evidence results in `UNKNOWN`, not fabrication.

### Gate F — Regression

- Relevant automated tests pass.
- Static analysis passes or an explicit justified exception is logged.
- Existing features do not regress.

---

# 5. Master Execution Board

## Phase 00 — Repository Reconnaissance & Execution Bootstrap

**State:** `[ ]`  
**Primary owner:** Orchestrator + Explore/Scout agents  
**Exit gate:** repository map, toolchain map, dependency map, current test/build state, and baseline recorded.

Tasks:

- `[ ]` R0.1 Inspect repository tree and identify existing WP-HEART code.
- `[ ]` R0.2 Read Requirements, Design, and Implementation documents in full.
- `[ ]` R0.3 Detect current PHP/WordPress/Node/package manager/tooling versions.
- `[ ]` R0.4 Detect existing agents and project-local skills under OpenCode-compatible locations.
- `[ ]` R0.5 Establish baseline test/build/lint/static-analysis results.
- `[ ]` R0.6 Record architecture deviations already present in the repository.
- `[ ]` R0.7 Create/verify Roadmap execution tracking hooks.

**Evidence required:** baseline report + repository inventory + initial commit/reference.

---

## Phase 01 — Foundation

**State:** `[ ]`  
**Implementation anchors:** `WH-001` → `WH-004`  
**Exit gate:** plugin bootstraps cleanly, autoloading is stable, DI/configuration are testable, uninstall is non-destructive.

Tasks:

- `[ ]` WH-001 Plugin Bootstrap
- `[ ]` WH-002 PHP Autoloading
- `[ ]` WH-003 Dependency Container
- `[ ]` WH-004 Configuration / environment validation
- `[ ]` Foundation unit tests
- `[ ]` Activation/deactivation/uninstall verification

---

## Phase 02 — Database Abstraction & Capability Boundary

**State:** `[ ]`  
**Implementation anchors:** `WH-010` → `WH-012`  
**Exit gate:** all database access flows through a controlled backend abstraction with safe identifier handling and read-only capability awareness.

Tasks:

- `[ ]` WH-010 Database Adapter
- `[ ]` WH-011 Identifier Safety
- `[ ]` WH-012 Database Capabilities / privilege awareness
- `[ ]` Adapter compatibility coverage for MySQL/MariaDB differences
- `[ ]` Read-only policy foundation

---

## Phase 03 — Database Discovery

**State:** `[ ]`  
**Implementation anchors:** `WH-020` → `WH-023`  
**Exit gate:** WP-HEART can enumerate the real database without assuming `wp_` and without hiding unknown objects.

Tasks:

- `[ ]` WH-020 Complete Database Discovery
- `[ ]` WH-021 Database Metadata
- `[ ]` WH-022 Table Metadata
- `[ ]` WH-023 Cache Integration
- `[ ]` Custom-prefix tests
- `[ ]` Empty database tests
- `[ ]` Permission/error-path tests

---

## Phase 04 — Schema Intelligence

**State:** `[ ]`  
**Implementation anchors:** `WH-030` → `WH-033`  
**Exit gate:** columns, indexes, constraints, and relationship semantics are exposed accurately with no fabricated relationships.

Tasks:

- `[ ]` WH-030 Column Inspection
- `[ ]` WH-031 Index Inspection
- `[ ]` WH-032 Constraint Inspection
- `[ ]` WH-033 Relationship Engine
- `[ ]` Composite-key/no-primary-key coverage
- `[ ]` Physical vs inferred relationship tests

**Relationship semantics:**

`PHYSICAL` = actual database constraint evidence.  
`INFERRED` = evidence-based inference, never presented as a physical FK.  
`NONE` = no relationship detected.  
`UNKNOWN` = insufficient evidence.

---

## Phase 05 — WordPress Core Intelligence

**State:** `[ ]`  
**Implementation anchors:** `WH-040` → `WH-041`  
**Exit gate:** WordPress-known tables and contextual metadata are identified without overriding database reality.

Tasks:

- `[ ]` WH-040 WordPress Context Service
- `[ ]` WH-041 Core Table Recognition
- `[ ]` Core vs non-Core evidence tests
- `[ ]` Custom prefix and multisite context coverage

---

## Phase 06 — Plugin / Theme Intelligence

**State:** `[ ]`  
**Implementation anchors:** `WH-050` → `WH-052`  
**Exit gate:** ownership attribution is evidence-based and confidence-aware.

Tasks:

- `[ ]` WH-050 Plugin Registry Integration
- `[ ]` WH-051 Plugin Table Detection
- `[ ]` WH-052 Ownership / Confidence Scoring
- `[ ]` Theme/context attribution where evidence permits
- `[ ]` Unknown ownership tests
- `[ ]` Stale/inactive plugin table scenarios

---

## Phase 07 — Classification Engine

**State:** `[ ]`  
**Implementation anchors:** `WH-060` → `WH-062`  
**Exit gate:** every discovered table has explicit classification and evidence.

Tasks:

- `[ ]` WH-060 Classification Engine
- `[ ]` WH-061 Unknown Classification
- `[ ]` WH-062 Orphan Candidate Classification
- `[ ]` Evidence aggregation
- `[ ]` Confidence normalization
- `[ ]` Regression tests for false ownership claims

---

## Phase 08 — Health & Diagnostics Engine

**State:** `[ ]`  
**Implementation anchors:** `WH-070` → `WH-073`  
**Exit gate:** diagnostics are modular, evidence-backed, severity-aware, and safe on large databases.

Tasks:

- `[ ]` WH-070 Health Engine Framework
- `[ ]` WH-071 Initial Diagnostics
- `[ ]` WH-072 Evidence Model
- `[ ]` WH-073 Severity Model
- `[ ]` Diagnostic registry/extension hooks
- `[ ]` Large-table safeguards
- `[ ]` Critical-severity evidence tests

---

## Phase 09 — Security & Server Boundary

**State:** `[ ]`  
**Implementation anchors:** `WH-080` → `WH-084`  
**Exit gate:** privileged functionality is protected independently of frontend behavior.

Tasks:

- `[ ]` WH-080 Capability Model
- `[ ]` WH-081 REST Authentication/Authorization
- `[ ]` WH-082 Nonce Validation
- `[ ]` WH-083 Server Security Boundary
- `[ ]` WH-084 Error Sanitization
- `[ ]` SQL injection test suite
- `[ ]` Unauthorized endpoint tests
- `[ ]` Sensitive error disclosure tests

---

## Phase 10 — Read-Only Query Engine

**State:** `[ ]`  
**Implementation anchors:** `WH-090` → `WH-093`  
**Exit gate:** query execution is strictly read-only in Release 1.0 and cannot be bypassed from REST or UI.

Tasks:

- `[ ]` WH-090 Query Policy
- `[ ]` WH-091 Query Validation
- `[ ]` WH-092 Query Execution
- `[ ]` WH-093 Explain
- `[ ]` Dangerous SQL rejection tests
- `[ ]` Parameterization tests
- `[ ]` Query timeout/limit safeguards
- `[ ]` Query metadata redaction

---

## Phase 11 — REST API

**State:** `[ ]`  
**Implementation anchors:** `WH-100` → `WH-107`  
**Exit gate:** versioned REST contract exposes validated backend services with complete authorization and pagination semantics.

Tasks:

- `[ ]` WH-100 REST Bootstrap
- `[ ]` WH-101 Database endpoints
- `[ ]` WH-102 Tables endpoints
- `[ ]` WH-103 Data endpoints
- `[ ]` WH-104 Search endpoints
- `[ ]` WH-105 Health endpoints
- `[ ]` WH-106 Query endpoints
- `[ ]` WH-107 Audit endpoints
- `[ ]` Composite/no-PK row semantics
- `[ ]` Pagination/filter/sort validation
- `[ ]` Contract/integration tests

---

## Phase 12 — Cache & Refresh

**State:** `[ ]`  
**Implementation anchors:** `WH-110` → `WH-112`  
**Exit gate:** current-state metadata caching is correct, observable, invalidatable, and never confused with historical snapshots.

Tasks:

- `[ ]` WH-110 Cache Service
- `[ ]` WH-111 Freshness Policy
- `[ ]` WH-112 Explicit Refresh
- `[ ]` Cache invalidation tests
- `[ ]` Performance regression checks

**Rule:** cache != historical snapshot.

---

## Phase 13 — Frontend Foundation

**State:** `[ ]`  
**Implementation anchors:** `WH-170` → `WH-172` + design system foundation  
**Exit gate:** React/TypeScript application is connected to real REST data and contains no direct `$wpdb` access.

Tasks:

- `[ ]` WH-170 React Application
- `[ ]` WH-171 Centralized API Client
- `[ ]` WH-172 State/Data Layer
- `[ ]` Design tokens and status semantics
- `[ ]` Error/loading/empty/unknown states
- `[ ]` Keyboard navigation foundation
- `[ ]` RTL foundation

---

## Phase 14 — Overview / Dashboard

**State:** `[ ]`  
**Implementation anchors:** `WH-180`  
**Exit gate:** overview answers “what is actually happening?” using real backend evidence only.

Tasks:

- `[ ]` WH-180 Overview UI
- `[ ]` Database summary
- `[ ]` Largest/important tables views
- `[ ]` Classification distribution
- `[ ]` Health summary
- `[ ]` Evidence/confidence presentation
- `[ ]` Progressive disclosure paths to table/row

---

## Phase 15 — Tables Explorer & Data Inspector

**State:** `[ ]`  
**Implementation anchors:** `WH-120` → `WH-122`, `WH-130` → `WH-132`  
**Exit gate:** a developer can move from database → table → structure/indexes → paginated rows → row details safely.

Tasks:

- `[ ]` WH-120 Tables Page
- `[ ]` WH-121 Filtering
- `[ ]` WH-122 Sorting
- `[ ]` WH-130 Table Detail
- `[ ]` WH-131 Data Browser
- `[ ]` WH-132 Row Inspector
- `[ ]` No-primary-key table handling
- `[ ]` Composite-key row addressing
- `[ ]` Large-table pagination tests
- `[ ]` Progressive disclosure validation

---

## Phase 16 — Global Search

**State:** `[ ]`  
**Implementation anchors:** `WH-140` → `WH-141`  
**Exit gate:** search is bounded, safe, paginated, schema-aware, and returns source-aware results.

Tasks:

- `[ ]` WH-140 Search Engine
- `[ ]` WH-141 Search Results UI/API integration
- `[ ]` Search limits and query policy
- `[ ]` Large-database benchmarks
- `[ ]` Search security tests

---

## Phase 17 — Database Map

**State:** `[ ]`  
**Implementation anchors:** `WH-150` → `WH-152`  
**Exit gate:** graph never presents inferred relationships as physical facts.

Tasks:

- `[ ]` WH-150 Graph Model
- `[ ]` WH-151 Map API
- `[ ]` WH-152 Map UI
- `[ ]` Relationship type/status visual semantics
- `[ ]` Graph performance controls
- `[ ]` No-relationship/unknown handling

---

## Phase 18 — Diagnostics UI

**State:** `[ ]`  
**Implementation anchors:** `WH-190`  
**Exit gate:** issues are evidence-backed, severity-consistent, explainable, and actionable only where justified.

Tasks:

- `[ ]` WH-190 Diagnostics UI
- `[ ]` Severity display
- `[ ]` Evidence display
- `[ ]` Context links to affected tables/columns
- `[ ]` Unknown/insufficient-evidence semantics

---

## Phase 19 — Query Console UI

**State:** `[ ]`  
**Implementation anchors:** `WH-200`  
**Exit gate:** UI cannot bypass server-side query policy.

Tasks:

- `[ ]` WH-200 Query Console
- `[ ]` Read-only affordances
- `[ ]` Validation/error UX
- `[ ]` Explain results
- `[ ]` Pagination/limits where applicable
- `[ ]` Sensitive content handling

---

## Phase 20 — Search UI

**State:** `[ ]`  
**Implementation anchors:** `WH-210`  
**Exit gate:** search UX maps directly to backend semantics and remains responsive on large datasets.

Tasks:

- `[ ]` WH-210 Search UI
- `[ ]` Filters/scope controls
- `[ ]` Result drill-down
- `[ ]` Loading/empty/error states

---

## Phase 21 — Settings, Permissions & Multisite

**State:** `[ ]`  
**Implementation anchors:** `WH-220`, `WH-230`  
**Exit gate:** capabilities and site scope are correct for single-site and multisite environments.

Tasks:

- `[ ]` WH-220 Settings/Permissions
- `[ ]` WH-230 Multisite
- `[ ]` Network/site scope tests
- `[ ]` Capability escalation tests
- `[ ]` Custom-prefix matrix

---

## Phase 22 — Audit & Internal Observability

**State:** `[ ]`  
**Implementation anchors:** `WH-160` → `WH-162` + logging/error tasks  
**Exit gate:** diagnostic/audit metadata is useful without leaking database content.

Tasks:

- `[ ]` WH-160 Audit Model
- `[ ]` WH-161 Audit Events
- `[ ]` WH-162 Sensitive Data Policy
- `[ ]` Internal logger
- `[ ]` Failure telemetry
- `[ ]` Query execution metadata policy

---

## Phase 23 — Performance Engineering

**State:** `[ ]`  
**Implementation anchors:** `WH-240` → `WH-243`  
**Exit gate:** WP-HEART does not materially degrade the public frontend and behaves predictably on large databases.

Tasks:

- `[ ]` WH-240 Performance Baseline
- `[ ]` WH-241 Large Database Scenarios
- `[ ]` WH-242 Query/metadata optimization
- `[ ]` WH-243 Regression Benchmarks
- `[ ]` Million-row test scenarios where feasible
- `[ ]` Browser payload-size checks

---

## Phase 24 — Accessibility & Internationalization

**State:** `[ ]`  
**Implementation anchors:** `WH-250`, `WH-260`, `WH-261`  
**Exit gate:** primary workflows are keyboard-accessible, translatable, and RTL-safe.

Tasks:

- `[ ]` WH-250 Accessibility
- `[ ]` WH-260 Internationalization
- `[ ]` WH-261 RTL validation
- `[ ]` Focus management
- `[ ]` Non-color status semantics

---

## Phase 25 — Testing Matrix & Quality Engineering

**State:** `[ ]`  
**Implementation anchors:** `WH-270` → `WH-277`  
**Exit gate:** the test matrix covers domain, DB integration, REST, security, performance, and frontend behavior.

Required stack:

- Backend: PHPUnit + WordPress PHPUnit Test Suite + MySQL/MariaDB integration
- Frontend: Jest + React Testing Library + TypeScript checking
- Static analysis: PHPStan + PHPCS / WordPress Coding Standards

Test categories:

- `[ ]` Unit tests
- `[ ]` Integration tests
- `[ ]` REST contract/authorization tests
- `[ ]` Database compatibility tests
- `[ ]` Classification/evidence tests
- `[ ]` Security tests
- `[ ]` Performance tests
- `[ ]` Frontend component/workflow tests
- `[ ]` Upgrade tests
- `[ ]` Uninstall tests

Mandatory scenarios:

- `[ ]` Custom prefixes
- `[ ]` Multisite
- `[ ]` Empty DB
- `[ ]` Unknown tables
- `[ ]` Orphan candidates
- `[ ]` Huge tables
- `[ ]` No-PK / composite-PK tables
- `[ ]` Unusual column types
- `[ ]` Malformed serialized data
- `[ ]` Restricted DB privileges
- `[ ]` SQL injection attempts
- `[ ]` Unauthorized REST requests
- `[ ]` Error sanitization

---

## Phase 26 — Production Hardening

**State:** `[ ]`  
**Implementation anchors:** `WH-280` → `WH-292`  
**Exit gate:** production diagnostics, logging, code hygiene, security review, and packaging readiness are complete.

Tasks:

- `[ ]` WH-280 Error handling/logging
- `[ ]` WH-281 Production diagnostics controls
- `[ ]` WH-290 Hardening pass
- `[ ]` WH-291 Dependency/build audit
- `[ ]` WH-292 Release configuration validation

---

## Phase 27 — Packaging

**State:** `[ ]`  
**Implementation anchors:** `WH-300` → `WH-302`  
**Exit gate:** clean production artifact can be installed and run without development residue.

Tasks:

- `[ ]` WH-300 Production build
- `[ ]` WH-301 Packaging/manifest
- `[ ]` WH-302 Artifact integrity checks
- `[ ]` No development dependencies in release artifact
- `[ ]` No source maps/debug residue unless deliberately required

---

## Phase 28 — Documentation

**State:** `[ ]`  
**Implementation anchors:** `WH-310` → `WH-312`  
**Exit gate:** user/developer/security documentation matches shipped behavior.

Tasks:

- `[ ]` WH-310 Developer documentation
- `[ ]` WH-311 User documentation
- `[ ]` WH-312 Security documentation
- `[ ]` API documentation
- `[ ]` Troubleshooting / limitations
- `[ ]` Architecture decisions

---

## Phase 29 — Release Validation

**State:** `[ ]`  
**Implementation anchors:** `WH-320` → `WH-324`  
**Exit gate:** all Release 1.0 gates pass on clean, existing, legacy, and multisite scenarios.

Tasks:

- `[ ]` WH-320 Clean Installation
- `[ ]` WH-321 Existing WordPress Site
- `[ ]` WH-322 Legacy/unknown-table Database
- `[ ]` WH-323 Upgrade Test
- `[ ]` WH-324 Uninstall Test
- `[ ]` Full security validation
- `[ ]` Full performance validation
- `[ ]` Full build/package validation

---

# 6. Final Release Gate — v1.0.0

The release cannot be marked `READY` until every condition below is checked.

### Product truth

- `[ ]` Real database reality is always visible.
- `[ ]` Unknown objects are not silently hidden.
- `[ ]` Core/plugin/other ownership uses evidence.
- `[ ]` Physical and inferred relationships are distinct.
- `[ ]` Estimated and exact metadata are distinguished where relevant.

### Security

- `[ ]` No destructive DB mutation exists in Release 1.0.
- `[ ]` Server-side read-only query enforcement is verified.
- `[ ]` REST authorization is verified.
- `[ ]` SQL injection defenses are tested.
- `[ ]` Sensitive errors/content are not leaked.

### Performance

- `[ ]` No whole-database scan on normal dashboard load.
- `[ ]` No whole-table payload to browser.
- `[ ]` Pagination/lazy loading is verified.
- `[ ]` Caching is verified.
- `[ ]` Large-table behavior is benchmarked.

### Quality

- `[ ]` PHPUnit/WordPress test suite passes.
- `[ ]` Frontend tests pass.
- `[ ]` TypeScript check passes.
- `[ ]` PHPStan passes at project-defined level.
- `[ ]` PHPCS passes.
- `[ ]` Upgrade/uninstall tests pass.

### Product UX

- `[ ]` Overview → Table → Row workflow works.
- `[ ]` Diagnostics are explainable.
- `[ ]` Query Console is safe and bounded.
- `[ ]` Search works on real data.
- `[ ]` Map semantics are explicit.
- `[ ]` Accessibility and RTL checks pass.

### Release artifact

- `[ ]` Production build passes.
- `[ ]` Install artifact verified.
- `[ ]` Clean installation verified.
- `[ ]` Existing production-like site verified.
- `[ ]` Documentation matches implementation.

**Release status:** `NOT_READY`

Only after all checks pass:

**Release status:** `READY_FOR_1_0_0`

---

# 7. Execution Ledger

The orchestrator is the single writer for this section.

| Seq | Date/Time | Phase | Task | State | Commit/Ref | Tests | Security | Performance | Notes |
|---:|---|---|---|---|---|---|---|---|---|
| 001 | — | 00 | R0.1–R0.7 | PENDING | — | — | — | — | — |

Every completed unit must add one row. Never delete historical rows.

---

# 8. Decision Log

| ID | Date | Decision / Conflict | Source of Truth | Resolution | Affected Tasks |
|---|---|---|---|---|---|
| D-001 | — | — | — | — | — |

Architectural changes are not allowed to happen silently.

---

# 9. Blocker Log

| ID | Date | Phase/Task | Blocker | Severity | Owner | Required Action | Status |
|---|---|---|---|---|---|---|---|
| B-001 | — | — | — | — | — | — | OPEN |

A blocker is not a reason to improvise an unsafe shortcut.

---

# 10. Definition of Done for Every Task

A task can become `[x]` only when all applicable conditions are true:

1. Implementation exists in the correct architectural layer.
2. Existing code was inspected before modification.
3. Relevant tests were added or updated.
4. Relevant tests pass.
5. Static analysis passes or an exception is documented.
6. Security implications were reviewed.
7. Performance implications were reviewed.
8. Edge cases were handled.
9. No unrelated scope was introduced.
10. Documentation/comments were updated where behavior or contracts changed.
11. The task is integrated with its required dependencies.
12. The orchestrator has recorded evidence in this roadmap.

---

# 11. Parallel Execution Rules

Parallelism is allowed only where dependencies are truly independent.

Safe parallel pattern:

```text
ORCHESTRATOR
├── EXPLORATION / RESEARCH AGENT
├── BACKEND IMPLEMENTATION AGENT
├── FRONTEND IMPLEMENTATION AGENT
├── TEST / QA AGENT
└── SECURITY / PERFORMANCE REVIEW AGENT
```

The orchestrator owns integration order and the roadmap.

Do not parallelize two tasks that modify the same architectural contract unless the orchestrator explicitly sequences their merge.

No subagent may independently declare a phase `DONE`.

---

# 12. Stop Conditions

Execution must stop and record a blocker when:

- Requirements and Design conflict materially.
- A change would require undocumented architectural deviation.
- Security boundary cannot be proven.
- A task would introduce Release 1.0 database mutation.
- Test infrastructure is broken in a way that makes correctness unverifiable.
- The repository contains unexpected destructive automation or credentials.
- A proposed relationship/classification cannot be evidence-backed.
- A performance decision risks scanning or loading the database at unsafe scale.

Do not stop for ordinary ambiguity that can be resolved from the authoritative documents, existing code conventions, or safe established practice. Resolve it, record it, and continue.

---

# 13. Future Release Parking Lot

These items must not silently enter Release 1.0:

- `[ ]` Persistent metadata snapshots
- `[ ]` Historical schema diff
- `[ ]` Query profiler/history expansion
- `[ ]` Performance monitoring history
- `[ ]` Safe data mutations
- `[ ]` Safe schema operations
- `[ ]` Automated repairs
- `[ ]` Migration assistant
- `[ ]` Documentation generator expansion
- `[ ]` AI database assistant

Each future feature needs its own requirements/design/task boundary before implementation.

---

# 14. Orchestrator Completion Record

At the end of execution, populate this section:

**Release:** `1.0.0`  
**Final status:** `READY_FOR_1_0_0` / `NOT_READY`  
**Completion date:** `—`  
**Final commit/tag:** `—`  
**Automated tests:** `—`  
**Static analysis:** `—`  
**Security validation:** `—`  
**Performance validation:** `—`  
**Packaging validation:** `—`  
**Documentation validation:** `—`  
**Known limitations:** `—`  
**Open blockers:** `—`

---

## Operating Principle

> Build the truth first. Protect it. Test it. Expose it. Explain it. Only then ship it.

WP-HEART must remain faithful to the real database rather than replacing database reality with WordPress assumptions.
