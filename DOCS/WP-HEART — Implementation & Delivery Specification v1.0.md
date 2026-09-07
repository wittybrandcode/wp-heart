# WP-HEART
## Database Observatory & Intelligence Platform for WordPress

### Implementation & Delivery Specification — v1.0

**Document Status:** Implementation Source of Truth  
**Product:** WP-HEART  
**Release:** 1.0 — Core Database Observatory  
**Plugin Slug:** `wp-heart`  
**Text Domain:** `wp-heart`  
**PHP Namespace:** `WPHeart\`  
**REST Namespace:** `wp-heart/v1`

---

# 1. Document Purpose

هذه الوثيقة تحول:

**Product Requirements Specification v1.0**

و

**Technical Design Specification v1.0**

إلى خطة تنفيذ عملية قابلة للتنفيذ والاختبار.

هذه الوثيقة هي المرجع المباشر لوكيل البرمجة أثناء بناء WP-HEART.

لكنها لا تملك صلاحية تغيير Product Requirements أو Technical Design.

القاعدة:

```text
Requirements
    ↓
Design
    ↓
Implementation
    ↓
Code
```

إذا ظهر تعارض، يجب التوقف عن الاختراع والرجوع إلى الوثيقة الأعلى.

---

# 2. Implementation Objective

الهدف هو إنتاج:

> Production-grade WordPress plugin suitable for real developer environments.

ليس الهدف:

- Prototype
- Mockup
- Demo
- UI-only application
- Partial proof of concept

Release 1.0 يجب أن تكون منتجًا حقيقيًا ومتكاملًا ضمن نطاق **Database Observatory / Inspection / Intelligence / Diagnostics**.

---

# 3. Release Boundary

يجب تنفيذ:

```text
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
```

ولا يجب تنفيذ:

```text
INSERT         ✗
UPDATE         ✗
DELETE         ✗
TRUNCATE       ✗

ALTER TABLE    ✗
DROP TABLE     ✗
CREATE TABLE   ✗

REPAIR         ✗
MIGRATION      ✗
ROLLBACK       ✗
```

هذه ليست مهامًا ناقصة.

إنها خارج Release 1.0 عمدًا.

---

# 4. Non-Negotiable Engineering Rules

يجب على كل Developer أو AI Coding Agent الالتزام بالقواعد التالية:

```text
Accuracy > Convenience
Security > Features
Evidence > Assumptions
Server-side Enforcement > Frontend Enforcement
Read-only > Mutation
Progressive Loading > Full Loading
Tested Behavior > Intended Behavior
```

يحظر:

```text
Direct $wpdb access from React
Frontend-only authorization
Assuming wp_ prefix
Hiding unknown tables
Inventing ownership
Inventing relationships
Unbounded database queries
Loading complete tables
Logging complete database content
Destructive SQL in Release 1.0
Business logic inside React components
```

---

# 5. Required Project Structure

البنية المستهدفة:

```text
wp-heart/
│
├── wp-heart.php
├── composer.json
├── package.json
├── phpunit.xml
├── phpcs.xml
├── phpstan.neon
├── readme.txt
│
├── src/
│   └── WPHeart/
│       ├── Application/
│       ├── Domain/
│       ├── Infrastructure/
│       ├── WordPress/
│       ├── Database/
│       ├── Discovery/
│       ├── Intelligence/
│       ├── Classification/
│       ├── Diagnostics/
│       ├── Search/
│       ├── Query/
│       ├── Security/
│       ├── REST/
│       ├── Cache/
│       ├── Audit/
│       └── Support/
│
├── tests/
│   ├── Unit/
│   ├── Integration/
│   ├── REST/
│   ├── Security/
│   ├── Performance/
│   └── Fixtures/
│
└── admin/
    └── app/
        └── src/
            ├── app/
            ├── components/
            ├── features/
            ├── pages/
            ├── hooks/
            ├── services/
            ├── api/
            ├── state/
            ├── types/
            └── utils/
```

قد تتغير الملفات الداخلية عند الحاجة، لكن architectural boundaries لا تتغير دون قرار موثق.

---

# 6. EPIC 01 — Project Foundation

## WH-001 — Plugin Bootstrap

### Objective

إنشاء نقطة الدخول الأساسية للـ plugin.

### Requirements

إنشاء:

```text
wp-heart.php
```

ويحتوي على:

- Plugin headers
- version
- text domain
- bootstrap
- activation hook
- deactivation hook
- uninstall integration

### Dependencies

None.

### Acceptance Criteria

- plugin activates without fatal errors
- plugin does not affect public frontend
- no database-wide scan on activation
- no destructive operation
- uninstall policy is explicit

### Tests

- activation test
- deactivation test
- uninstall safety test

---

# 7. WH-002 — PHP Autoloading

### Objective

إعداد PSR-4-compatible autoloading.

### Requirements

Namespace:

```text
WPHeart\
```

### Acceptance Criteria

- classes autoload correctly
- no random require statements
- no namespace collisions

### Tests

- autoload smoke test

---

# 8. WH-003 — Dependency Container

### Objective

إنشاء lightweight dependency container.

### Services

يجب أن يكون قادرًا على تسجيل الخدمات الأساسية:

```text
Database Inspector
Discovery
Schema
Classification
Intelligence
Diagnostics
Query
Security
Cache
Audit
```

### Tests

- container registration
- dependency resolution

---

# 9. WH-004 — Configuration

### Objective

إنشاء configuration layer موحد.

يجب أن يدير:

- plugin version
- feature flags حيث تكون مطلوبة
- cache settings
- capability configuration
- diagnostics configuration

لا يتم تخزين secrets.

---

# 10. EPIC 02 — Database Abstraction

## WH-010 — Database Adapter

### Objective

إنشاء abstraction فوق `$wpdb`.

### Requirements

يوفر interfaces للعمليات التالية:

```text
getDatabaseMetadata()
listTables()
getTableMetadata()
getColumns()
getIndexes()
getConstraints()
executeReadQuery()
```

### Acceptance Criteria

لا يحتوي Domain على direct `$wpdb`.

---

# 11. WH-011 — Database Identifier Safety

### Objective

تأمين التعامل مع:

- table names
- column names
- index names

### Requirements

يجب:

```text
validate
+
verify
+
safely construct
```

### Tests

- malicious identifiers
- invalid identifiers
- unknown identifiers
- SQL injection attempts

---

# 12. WH-012 — Database Capability Detection

### Objective

اكتشاف capabilities ذات الصلة بالبيئة.

مثل:

- database engine
- server version
- metadata availability
- privilege limitations where safely detectable

### Acceptance Criteria

لا يتم افتراض capabilities غير مثبتة.

---

# 13. EPIC 03 — Database Discovery

## WH-020 — Complete Table Discovery

### Objective

اكتشاف جميع الجداول المتاحة للاتصال الحالي.

### Requirements

- no `wp_` assumption
- include unknown tables
- include plugin tables
- include custom tables
- include legacy tables
- support multisite

### Acceptance Criteria

كل table accessible يظهر في discovery result.

---

# 14. WH-021 — Database Metadata

### Objective

استخراج:

- database metadata
- engine
- charset
- collation
- server information
- table count

### Requirements

تمييز:

```text
EXACT
ESTIMATED
UNKNOWN
```

حيث يلزم.

---

# 15. WH-022 — Table Metadata

### Objective

استخراج metadata لكل table.

### Requirements

- name
- engine
- charset
- collation
- estimated rows
- size
- metadata availability

---

# 16. WH-023 — Discovery Cache Integration

### Objective

ربط discovery مع Metadata Cache.

### Acceptance Criteria

- repeated discovery does not unnecessarily repeat expensive operations
- explicit refresh bypasses stale metadata
- freshness is exposed

---

# 17. EPIC 04 — Schema Inspection

## WH-030 — Column Inspector

### Objective

استخراج معلومات columns.

### Requirements

لكل column:

```text
name
position
data type
native type
nullable
default
extra
charset
collation
```

---

# 18. WH-031 — Index Inspector

### Objective

استخراج indexes.

### Requirements

```text
name
unique
primary
columns
cardinality
type
```

---

# 19. WH-032 — Constraint Inspector

### Objective

استخراج physical constraints حيث تكون متاحة.

### Important Rule

غياب foreign key لا يعتبر failure.

### Acceptance Criteria

النظام يميز بين:

```text
constraint exists
constraint unavailable
no constraint detected
```

ولا يحول غياب FK إلى diagnostic تلقائيًا.

---

# 20. WH-033 — Relationship Extraction

### Objective

إنشاء relationship objects.

### Types

```text
PHYSICAL
INFERRED
NONE
UNKNOWN
```

### Rule

Physical relationship يجب أن تكون مدعومة بdatabase metadata.

Inferred relationship يجب أن تحتوي evidence.

---

# 21. EPIC 05 — WordPress Intelligence

## WH-040 — WordPress Context Service

### Objective

اكتشاف:

- site context
- network context
- active multisite state
- actual table prefix

### Acceptance Criteria

لا توجد hardcoded WordPress table prefix.

---

# 22. WH-041 — Core Table Recognition

### Objective

تحديد tables المرتبطة بـ WordPress Core.

### Evidence

- WordPress APIs
- actual prefix
- known Core structures

### Output

```text
classification
confidence
evidence
```

---

# 23. EPIC 06 — Plugin Intelligence

## WH-050 — Plugin Registry

### Objective

بناء representation للـ installed/active plugins.

### Data

```text
plugin
slug
name
status
version where available
```

---

# 24. WH-051 — Plugin Table Detection

### Objective

اكتشاف العلاقة بين plugins والجداول.

### Evidence Sources

```text
plugin metadata
plugin schema
table patterns
column patterns
known signatures
registration evidence
```

### Rule

لا يتم إعلان ownership كحقيقة بدون evidence كافٍ.

---

# 25. WH-052 — Confidence Engine

### Objective

حساب confidence:

```text
HIGH
MEDIUM
LOW
UNKNOWN
```

### Requirements

Confidence يجب أن تكون نتيجة evidence aggregation.

لا تعتمد على table name وحده.

---

# 26. EPIC 07 — Classification

## WH-060 — Classification Engine

### Objective

تصنيف كل table إلى:

```text
CORE
PLUGIN
THEME_CUSTOM
UNKNOWN
ORPHAN_CANDIDATE
```

### Output

```text
classification
confidence
evidence[]
```

---

# 27. WH-061 — Unknown Classification

### Objective

ضمان أن أي table غير قابل للتحديد يبقى:

```text
UNKNOWN
```

### Rule

UNKNOWN لا يعني:

```text
broken
dangerous
unused
removable
```

---

# 28. WH-062 — Orphan Candidate Detection

### Objective

تحديد الجداول التي توجد أدلة على احتمال فقدان owner الأصلي.

### Rule

النتيجة:

```text
ORPHAN_CANDIDATE
```

وليست:

```text
SAFE_TO_DELETE
```

---

# 29. EPIC 08 — Health Engine

## WH-070 — Diagnostic Framework

### Objective

إنشاء framework قابل للتوسع.

كل diagnostic يجب أن يطبق contract موحدًا.

---

# 30. WH-071 — Initial Diagnostics

يجب تنفيذ diagnostics ذات صلة بـ Release 1.0.

الفئات:

```text
WordPress structural issues
Schema anomalies
Index observations
Unusual structures
Orphan candidates
Database metadata issues
```

لا يتم إضافة diagnostic لمجرد أن اكتشافه ممكن.

يجب أن تكون له قيمة developer واضحة.

---

# 31. WH-072 — Diagnostic Evidence

كل issue يجب أن يحتوي:

```text
ID
Severity
Affected object
Evidence
Explanation
Recommendation
```

---

# 32. WH-073 — Severity Engine

القيم:

```text
INFO
NOTICE
WARNING
ERROR
CRITICAL
```

CRITICAL requires clear evidence.

---

# 33. EPIC 09 — Security

## WH-080 — Capability System

إنشاء capabilities مستقلة للـ WP-HEART.

على الأقل:

```text
view_database
view_data
run_read_queries
view_audit
manage_settings
```

الأسماء النهائية يجب أن تكون ثابتة في code contract.

---

# 34. WH-081 — REST Authorization

كل endpoint privileged يجب أن يمتلك:

```text
authentication
capability check
permission callback
request validation
```

---

# 35. WH-082 — Nonce Protection

تطبيق nonce validation حيث يكون مطلوبًا ضمن WordPress request model.

---

# 36. WH-083 — Security Boundary

يجب أن تكون security checks server-side.

لا يجوز اعتبار:

```text
disabled button
hidden UI
frontend validation
```

security mechanism.

---

# 37. WH-084 — Error Sanitization

منع تسريب:

- credentials
- filesystem paths
- secrets
- unnecessary SQL internals
- sensitive database content

---

# 38. EPIC 10 — Read-Only Query Engine

## WH-090 — Query Policy

### Objective

إنشاء policy تمنع mutation في Release 1.0.

### Allowed

Read-only operations فقط.

### Forbidden

```text
INSERT
UPDATE
DELETE
TRUNCATE
ALTER
DROP
CREATE
RENAME
```

---

# 39. WH-091 — Query Validation

التحقق من:

- query structure
- statement type
- size
- limits
- permissions

---

# 40. WH-092 — Query Execution

التنفيذ يجب أن يمر:

```text
Authorization
↓
Validation
↓
Policy
↓
Query Service
↓
Database Adapter
```

---

# 41. WH-093 — Query Explain

إضافة EXPLAIN حيث يدعمه database environment.

لا يقوم EXPLAIN بأي mutation.

---

# 42. EPIC 11 — REST API

## WH-100 — REST Bootstrap

إنشاء:

```text
wp-heart/v1
```

---

# 43. WH-101 — Database Endpoint

```text
GET /database
```

يعيد database overview.

---

# 44. WH-102 — Tables Endpoints

```text
GET /tables
GET /tables/{table}
GET /tables/{table}/schema
GET /tables/{table}/indexes
GET /tables/{table}/relationships
```

كل identifier يخضع للـ validation.

---

# 45. WH-103 — Data Endpoints

```text
GET /tables/{table}/rows
GET /tables/{table}/rows/{id}
```

مع:

- pagination
- limits
- safe sorting
- safe filtering

---

# 46. WH-104 — Search Endpoint

```text
GET /search
```

يجب أن يكون bounded.

---

# 47. WH-105 — Health Endpoints

```text
GET /health
GET /issues
```

---

# 48. WH-106 — Query Endpoint

```text
POST /query
```

مع read-only policy server-side.

---

# 49. WH-107 — Audit Endpoint

```text
GET /audit
```

وفق capability المناسبة.

---

# 50. EPIC 12 — Metadata Cache

## WH-110 — Cache Service

يجب دعم:

```text
get
set
delete
invalidate
refresh
```

---

# 51. WH-111 — Cache Freshness

كل cached metadata يجب أن تكون قابلة لتحديد:

```text
created
expires
freshness
scope
```

---

# 52. WH-112 — Explicit Refresh

المستخدم يجب أن يستطيع طلب refresh للـ metadata.

---

# 53. EPIC 13 — Tables Explorer

## WH-120 — Tables Page

الواجهة تعرض:

```text
Table
Classification
Confidence
Rows
Size
Engine
Health
```

---

# 54. WH-121 — Filtering

دعم filtering على:

- name
- classification
- plugin
- size
- health

---

# 55. WH-122 — Sorting

Sorting يجب أن يكون server-safe.

لا يجوز السماح arbitrary SQL identifiers.

---

# 56. EPIC 14 — Data Inspector

## WH-130 — Table Detail

Tabs:

```text
Overview
Structure
Indexes
Constraints
Relationships
Data
Diagnostics
```

---

# 57. WH-131 — Data Browser

يدعم:

- pagination
- lazy loading
- controlled page sizes
- sorting
- filtering

---

# 58. WH-132 — Row Inspector

يدعم:

```text
NULL
TEXT
LONG TEXT
JSON
SERIALIZED
BINARY
```

ولا يقوم بإرسال البيانات خارج WordPress.

---

# 59. EPIC 15 — Global Search

## WH-140 — Search Engine

### Objective

البحث عن قيم عبر database ضمن حدود آمنة.

### Requirements

- scoped execution
- pagination
- limits
- asynchronous execution
- safe query construction

---

# 60. WH-141 — Search Results

يجب أن يعرض:

```text
table
column
row identifier where available
matched value preview
```

مع تجنب كشف محتوى غير ضروري.

---

# 61. EPIC 16 — Database Map

## WH-150 — Graph Model

إنشاء graph representation لـ:

```text
Tables
Columns
Physical Relationships
Inferred Relationships
```

---

# 62. WH-151 — Map API

API يجب أن يميز:

```text
origin = PHYSICAL
origin = INFERRED
```

مع confidence/evidence عند inference.

---

# 63. WH-152 — Map UI

عرض database graph مع visual distinction واضح بين أنواع العلاقات.

لا يجوز أن يبدو inferred relationship كـ foreign key فعلي.

---

# 64. EPIC 17 — Audit

## WH-160 — Audit Event Model

```text
AuditEvent
├── id
├── type
├── actor
├── timestamp
├── target
├── metadata
└── outcome
```

---

# 65. WH-161 — Audit Events

الأحداث:

```text
DATABASE_SCAN
TABLE_VIEW
ROW_VIEW
SEARCH
QUERY_EXECUTION
SETTINGS_CHANGE
```

---

# 66. WH-162 — Sensitive Audit Data

يمنع تسجيل:

- complete row data
- credentials
- tokens
- secrets
- unnecessary query result content

---

# 67. EPIC 18 — React Foundation

## WH-170 — React Application

إنشاء:

```text
React
TypeScript
```

---

# 68. WH-171 — API Client

إنشاء centralized API client.

يجب أن يتعامل مع:

- authentication
- REST errors
- loading
- pagination
- retries حيث تكون مناسبة

---

# 69. WH-172 — Application State

إدارة state دون وضع domain business logic داخل components.

---

# 70. EPIC 19 — Overview UI

## WH-180 — Dashboard

يعرض:

```text
Database Summary
Tables
Classification
Health
Unknown
Orphan Candidates
Metadata Freshness
```

---

# 71. EPIC 20 — Diagnostics UI

## WH-190 — Health Screen

عرض:

```text
Overall Health
Issues
Severity
Evidence
Affected Objects
Recommendations
```

---

# 72. EPIC 21 — Developer Query UI

## WH-200 — Query Console

يجب أن تعرض:

- editor
- execution
- results
- errors
- limits
- read-only state

يجب أن يكون واضحًا للمستخدم أن Release 1.0 read-only.

---

# 73. EPIC 22 — Search UI

## WH-210 — Global Search

يجب أن يدعم:

- query input
- filters
- loading state
- result pagination
- error handling

---

# 74. EPIC 23 — Settings & Permissions

## WH-220 — Permissions UI

عرض capabilities والـ access model المناسب.

لا يسمح UI بمنح صلاحيات WordPress بطريقة تتجاوز WordPress authorization model.

---

# 75. EPIC 24 — Multisite

## WH-230 — Multisite Context

اختبار وتنفيذ:

- network context
- site context
- table prefix
- site tables
- network tables

---

# 76. EPIC 25 — Performance Hardening

## WH-240 — Request Cost Control

كل endpoint يجب أن يكون bounded.

---

# 77. WH-241 — Large Table Safety

يجب منع:

```text
full table load
full row load
unbounded search
unbounded pagination
```

---

# 78. WH-242 — Metadata Optimization

التأكد من أن dashboard لا يقوم بـ:

```text
database-wide expensive scan
```

في كل request.

---

# 79. WH-243 — Async Loading

الـ frontend يجب أن يحمل:

```text
Overview
Tables
Schema
Rows
Diagnostics
```

بشكل progressive/asynchronous.

---

# 80. EPIC 26 — Accessibility

## WH-250 — Accessible Components

يجب ضمان:

- keyboard navigation
- focus management
- semantic controls
- accessible tables
- dialogs
- status announcements

---

# 81. EPIC 27 — Internationalization

## WH-260 — i18n

كل النصوص user-facing قابلة للترجمة.

---

# 82. WH-261 — RTL

يجب اختبار الواجهة في RTL.

لا يعتمد RTL على hacks موضعية.

---

# 83. EPIC 28 — Testing

## WH-270 — PHPUnit Foundation

إعداد:

```text
PHPUnit
WordPress PHPUnit Test Suite
```

---

# 84. WH-271 — Unit Tests

اختبار:

- classification
- evidence
- confidence
- relationship model
- query policy
- pagination
- diagnostics

---

# 85. WH-272 — Database Integration Tests

اختبار:

- discovery
- schema
- indexes
- constraints
- custom prefix
- large tables

---

# 86. WH-273 — REST Tests

اختبار:

- authentication
- permissions
- capabilities
- validation
- pagination
- errors
- query policy

---

# 87. WH-274 — Security Tests

يجب أن تشمل:

```text
SQL Injection
Identifier Injection
Privilege Bypass
REST Authorization Bypass
Capability Bypass
Nonce-related failures
Sensitive Error Leakage
```

---

# 88. WH-275 — Frontend Tests

إعداد:

```text
Jest
React Testing Library
```

اختبار:

- components
- loading
- errors
- tables
- inspectors
- search
- diagnostics
- query console
- accessibility behavior

---

# 89. WH-276 — Static Analysis

إعداد:

```text
PHPStan
PHPCS
WordPress Coding Standards
TypeScript type checking
```

---

# 90. WH-277 — Performance Tests

إنشاء benchmarks للعمليات الحساسة:

```text
Discovery
Schema
Classification
Search
Diagnostics
Pagination
```

الهدف اكتشاف regressions وليس فرض رقم عالمي واحد على كل server.

---

# 91. EPIC 29 — Error Handling & Logging

## WH-280 — Error Normalization

توحيد errors القادمة من:

```text
Database
Domain
Application
REST
Frontend
```

---

# 92. WH-281 — Internal Logger

تسجيل:

```text
Discovery failures
REST errors
Diagnostic failures
Query metadata
Security events
```

مع منع sensitive content logging.

---

# 93. EPIC 30 — Production Hardening

## WH-290 — Security Review

مراجعة:

- capabilities
- REST
- SQL
- identifiers
- output escaping
- sensitive data
- permissions

---

# 94. WH-291 — Performance Review

مراجعة:

- queries
- pagination
- caching
- frontend loading
- database scans
- large tables

---

# 95. WH-292 — Compatibility Review

اختبار:

```text
Standard WordPress
Custom Prefix
Multisite
MySQL
MariaDB
Large Database
Unknown Tables
Plugin Tables
Unusual Schemas
```

---

# 96. EPIC 31 — Packaging

## WH-300 — Production Build

إنتاج:

```text
production PHP
production JS/CSS
minified assets where appropriate
```

---

# 97. WH-301 — Plugin Packaging

الـ ZIP النهائي يجب أن:

- يحتوي الملفات المطلوبة فقط
- لا يحتوي development secrets
- لا يحتوي test artifacts غير المطلوبة
- لا يحتوي source credentials
- يعمل بعد installation

---

# 98. WH-302 — Versioning

يجب توحيد version بين:

- plugin header
- PHP constant
- frontend build metadata where required
- release artifacts

---

# 99. EPIC 32 — Documentation

## WH-310 — Developer Documentation

يجب توثيق:

- architecture
- module responsibilities
- REST API
- security
- capabilities
- database discovery
- classification
- diagnostics
- caching
- testing

---

# 100. WH-311 — User Documentation

توثيق:

- installation
- permissions
- database overview
- tables
- search
- diagnostics
- query console
- limitations

---

# 101. WH-312 — Security Documentation

شرح:

- read-only model
- capabilities
- database privileges
- privacy
- data handling
- audit behavior

---

# 102. EPIC 33 — Release Validation

## WH-320 — Functional Acceptance

يجب التحقق من قدرة المستخدم على:

```text
View database
View tables
Inspect schema
Inspect indexes
Inspect constraints
Browse rows
Inspect rows
Search
Classify
Understand ownership evidence
View health
Run read queries
View relationships
Use map
View audit
```

---

# 103. WH-321 — Security Acceptance

يجب إثبات:

```text
No unauthorized REST access
No capability bypass
No SQL injection
No identifier injection
No destructive query execution
No credential exposure
No sensitive error leakage
```

---

# 104. WH-322 — Performance Acceptance

يجب إثبات:

```text
No complete table loading
No millions-of-rows browser response
No unnecessary full database scans
Pagination works
Caching works
Large table inspection remains bounded
```

---

# 105. WH-323 — Compatibility Acceptance

يجب إثبات:

```text
Custom prefix works
Multisite works
Unknown tables remain visible
Plugin tables remain visible
Unusual schemas do not crash discovery
```

---

# 106. WH-324 — Accessibility Acceptance

يجب التحقق من:

```text
Keyboard navigation
Focus management
Screen-reader semantics
Accessible tables
Accessible dialogs
RTL
```

---

# 107. Mandatory Implementation Order

يجب تنفيذ المشروع بهذا الترتيب:

```text
01 Foundation
        ↓
02 Database Abstraction
        ↓
03 Discovery
        ↓
04 Schema
        ↓
05 WordPress Intelligence
        ↓
06 Plugin Intelligence
        ↓
07 Classification
        ↓
08 Health
        ↓
09 Security
        ↓
10 Query Policy
        ↓
11 REST
        ↓
12 Cache
        ↓
13 React Foundation
        ↓
14 Tables
        ↓
15 Data Inspector
        ↓
16 Search
        ↓
17 Query Console
        ↓
18 Database Map
        ↓
19 Audit
        ↓
20 Multisite
        ↓
21 Performance
        ↓
22 Accessibility / i18n / RTL
        ↓
23 Testing
        ↓
24 Packaging
        ↓
25 Documentation
        ↓
26 Release Validation
```

لا يجوز البدء من Database Map أو React UI قبل اكتمال domain/database foundations.

---

# 108. Task Execution Protocol

عند تنفيذ أي Task، يجب على AI Coding Agent اتباع:

```text
1. Read Requirements
2. Read Design
3. Read this Implementation Specification
4. Inspect current repository
5. Inspect dependencies
6. Inspect existing implementation
7. Identify affected modules
8. Implement smallest coherent change
9. Write/update tests
10. Run tests
11. Run static analysis
12. Run security checks
13. Review performance impact
14. Report changed files
15. Report tests
16. Report unresolved issues
17. Stop
```

لا ينتقل إلى Task التالية تلقائيًا إلا إذا طُلب منه ذلك.

---

# 109. Task Completion Report

كل Task مكتملة يجب أن ينتج عنها تقرير:

```text
Task:
WH-XXX

Status:
COMPLETE

Changed Files:
...

Implementation:
...

Tests:
...

Static Analysis:
...

Security:
...

Performance:
...

Known Limitations:
...

Next Recommended Task:
...
```

---

# 110. Rules for AI Coding Agent

يجب على الوكيل:

```text
Inspect before modifying.
Understand before refactoring.
Test before claiming completion.
```

ويحظر عليه:

```text
Invent architecture
Invent endpoints
Invent database facts
Invent ownership
Invent relationships
Silently change requirements
Silently change security policy
Silently introduce mutations
```

---

# 111. Handling Ambiguity

عند وجود ambiguity:

1. Requirements هي المرجع الأول.
2. Design يحدد architectural interpretation.
3. Implementation يختار أقل تفسير destructive.
4. إذا كان القرار architectural، يجب توثيقه.
5. لا يتم تجاوز requirements لمجرد سهولة التنفيذ.

---

# 112. Handling Existing Code

قبل إضافة أي code:

```text
Inspect repository
Inspect current architecture
Inspect dependencies
Inspect existing services
Inspect existing tests
```

لا يجوز إعادة بناء module موجود فقط لأن architecture النظري يقترح اسمًا مختلفًا.

إذا كان التعديل ضروريًا:

```text
Document reason
+
Preserve behavior
+
Add regression tests
```

---

# 113. Database Mutation Guard

يجب أن يكون هناك test-level وapplication-level protection يمنع Release 1.0 من تنفيذ mutation.

يجب اختبار أن query policy ترفض:

```text
INSERT
UPDATE
DELETE
TRUNCATE
ALTER
DROP
CREATE
RENAME
```

حتى لو حاول المستخدم تجاوز UI.

---

# 114. Evidence Integrity Guard

كل classification/relationship/diagnostic يجب أن يكون قابلًا للرجوع إلى evidence.

لا يجوز وجود:

```text
classification = PLUGIN
```

دون تفسير مصدرها.

---

# 115. Unknown Integrity Guard

يجب أن يفشل الاختبار إذا قامت أي طبقة بإخفاء table غير معروف.

قاعدة:

```text
Discovered ≠ Recognized
```

و:

```text
Unrecognized ≠ Invalid
```

---

# 116. Relationship Integrity Guard

يجب أن تفشل الاختبارات إذا تم تقديم inferred relationship كـ physical constraint.

قاعدة:

```text
Physical → database metadata
Inferred → evidence + confidence
```

---

# 117. Large Database Integrity Guard

يجب أن تفشل tests أو review gates عند وجود:

```text
SELECT * without bounds
Full-table browser payload
Unbounded search
Unbounded page size
```

في المسارات التي تتعامل مع database rows.

---

# 118. Security Integrity Guard

كل privileged REST endpoint يجب أن يمتلك:

```text
permission callback
+
capability validation
+
request validation
```

وكل database operation يجب أن تمر عبر policy المناسبة.

---

# 119. Performance Integrity Guard

لا يجوز إدخال operation مكلفة في:

```text
every request
every dashboard render
every component render
```

دون caching أو explicit user action أو architectural justification.

---

# 120. Release 1.0 Definition of Done

لا يعتبر WP-HEART Release 1.0 مكتملًا إلا إذا تحققت جميع الفئات التالية:

```text
[✓] Project Foundation
[✓] Database Abstraction
[✓] Complete Discovery
[✓] Schema Inspection
[✓] WordPress Intelligence
[✓] Plugin Intelligence
[✓] Evidence-based Classification
[✓] Unknown / Orphan Candidate Detection
[✓] Health Engine
[✓] Read-only Query Engine
[✓] REST API
[✓] Security Architecture
[✓] Metadata Cache
[✓] Tables Explorer
[✓] Data Browser
[✓] Row Inspector
[✓] Global Search
[✓] Database Map
[✓] Audit
[✓] Multisite
[✓] Large Database Safety
[✓] Accessibility
[✓] RTL
[✓] Internationalization
[✓] Automated Testing
[✓] Static Analysis
[✓] Security Testing
[✓] Performance Testing
[✓] Production Packaging
[✓] Documentation
[✓] Release Validation
```

---

# 121. Explicitly Forbidden Before Release 2.x

لا يجوز للـ Coding Agent إدخال أيًا من الآتي تحت أي Task غير مصرح بها:

```text
UPDATE rows
DELETE rows
INSERT rows
TRUNCATE tables
ALTER tables
DROP tables
CREATE tables
Automatic repair
Automatic optimization
Migration
Rollback
Destructive SQL console
```

ولا يجوز إضافة هذه الوظائف إلى REST API بشكل مخفي.

---

# 122. Future Architecture Hooks

يمكن إنشاء interfaces أو extension points التي تسمح مستقبلًا بإضافة:

```text
Snapshot Service
Schema Diff
Query Profiler
Performance Analyzer
Safe Operations
Repair Engine
Migration Engine
Documentation Generator
AI Analysis
```

لكن:

> Future hooks must not execute future functionality.

وجود interface ليس تصريحًا بتنفيذ feature.

---

# 123. Future Mutation Architecture

عند تطوير mutation في إصدار مستقبلي، يجب أن تكون البنية:

```text
UI
 ↓
REST
 ↓
Capability
 ↓
Authorization
 ↓
Operation Policy
 ↓
Validation
 ↓
Preview
 ↓
Confirmation
 ↓
Operation Service
 ↓
Audit
 ↓
Database
```

ولا يجوز اختصار هذه السلسلة.

---

# 124. Final Quality Gates

قبل release يجب أن تنجح:

```text
Functional Tests
Security Tests
Integration Tests
REST Tests
Performance Tests
Frontend Tests
Accessibility Checks
Static Analysis
Packaging Validation
Upgrade Validation
Uninstall Validation
```

ويجب اختبار التثبيت من package النهائي، وليس فقط development environment.

---

# 125. Final AI Coding Agent Master Prompt

استخدم النص التالي باعتباره التعليمات الأساسية لوكيل البرمجة:

> Build WP-HEART as a production-grade WordPress Database Observatory and Intelligence Platform.
>
> The authoritative project documents are:
>
> 1. WP-HEART Product Requirements Specification v1.0
> 2. WP-HEART Technical Design Specification v1.0
> 3. WP-HEART Implementation & Delivery Specification v1.0
>
> These documents are the source of truth.
>
> Implement the project task-by-task.
>
> Do not invent a competing architecture.
>
> Do not silently change requirements.
>
> Do not simplify the product into a prototype.
>
> Do not expand Release 1.0 beyond its defined scope.
>
> WP-HEART must observe the actual database rather than assume what should exist.
>
> Never assume the WordPress prefix is `wp_`.
>
> Never hide unknown tables.
>
> Never claim ownership without evidence.
>
> Never claim relationships without evidence.
>
> Always distinguish:
>
> - Physical
> - Inferred
> - Unknown
>
> Always distinguish:
>
> - Exact
> - Estimated
> - Cached
> - Unavailable
>
> The backend is the security boundary.
>
> Never rely on frontend validation for security.
>
> Never access `$wpdb` from React.
>
> Never expose database credentials.
>
> All privileged REST endpoints require proper authorization.
>
> All database identifiers must be validated.
>
> All dynamic database values must use safe query construction and prepared statements where applicable.
>
> Release 1.0 is read-only.
>
> Never implement INSERT, UPDATE, DELETE, TRUNCATE, ALTER, DROP, CREATE, migration, repair, or destructive SQL execution in Release 1.0.
>
> Never load an entire table into PHP memory or the browser.
>
> Design every operation for large databases.
>
> Use pagination, lazy loading, caching, asynchronous REST requests, and bounded queries.
>
> Health diagnostics must be evidence-backed.
>
> Do not label an issue CRITICAL without clear evidence.
>
> Unknown and orphan candidates must never be automatically treated as removable.
>
> Keep database reality separate from WordPress intelligence.
>
> Keep physical relationships separate from inferred relationships.
>
> Keep cache separate from historical snapshots.
>
> Business logic must not be placed inside React components.
>
> Every completed task must include appropriate automated tests.
>
> Security-sensitive functionality requires security tests.
>
> Performance-sensitive functionality requires performance tests.
>
> Before implementing a task:
>
> 1. inspect the repository;
> 2. inspect the current architecture;
> 3. inspect dependencies;
> 4. identify affected modules;
> 5. implement the smallest coherent change;
> 6. write/update tests;
> 7. run tests;
> 8. run static analysis;
> 9. review security;
> 10. review performance;
> 11. report changed files;
> 12. report tests;
> 13. report known limitations.
>
> Do not proceed to another task unless explicitly instructed.
>
> When ambiguity exists, choose the safest and least destructive interpretation and document the decision.
>
> The fundamental implementation principle is:
>
> **OBSERVE THE DATABASE AS IT ACTUALLY EXISTS, THEN ADD EVIDENCE-BASED WORDPRESS INTELLIGENCE WITHOUT ALTERING THAT REALITY.**

---

# 126. Final Implementation Principle

WP-HEART يجب أن يبنى من الداخل إلى الخارج:

```text
DATABASE
   ↓
DOMAIN
   ↓
INTELLIGENCE
   ↓
SECURITY
   ↓
REST
   ↓
FRONTEND
   ↓
TESTING
   ↓
PRODUCTION
```

وليس:

```text
UI
↓
Random Features
↓
Backend Patches
```

المنتج النهائي يجب أن يكون نتيجة Architecture واضحة، وليس تراكمًا لمجموعة features.

---

# 127. Final Project Contract

الوثائق الثلاث الآن مترابطة كالتالي:

```text
DOCUMENT 01
Product Requirements
"What WP-HEART is"
        ↓
DOCUMENT 02
Technical Design
"How WP-HEART works"
        ↓
DOCUMENT 03
Implementation & Delivery
"How WP-HEART is built"
        ↓
SOURCE CODE
"The implementation"
```

أي feature جديدة يجب أن يمكن تتبعها إلى Requirement.

وأي implementation يجب أن يمكن تتبعها إلى Design.

وأي code يجب أن يمكن تتبعه إلى Implementation Task.

هذه هي قاعدة:

> **Requirement → Design → Task → Code → Test**

---

# 128. Final Definition

WP-HEART Release 1.0 هو:

> **Production-grade database observability, inspection, intelligence, and diagnostics platform for WordPress developers.**

إنه يرى قاعدة البيانات كما هي.

يفهم ما يمكن فهمه منها.

يُظهر الأدلة التي بنى عليها استنتاجاته.

يحافظ على عدم اليقين عندما لا تكفي الأدلة.

ولا يغيّر قاعدة البيانات في Release 1.0.