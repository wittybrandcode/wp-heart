# WP-HEART
## Database Observatory & Intelligence Platform for WordPress

### Technical Design Specification — v1.0

**Document Status:** Architecture Source of Truth  
**Product:** WP-HEART  
**Release Target:** 1.0 — Core Database Observatory  
**Plugin Slug:** `wp-heart`  
**PHP Namespace:** `WPHeart\`  
**REST Namespace:** `wp-heart/v1`

---

# 1. Purpose

هذه الوثيقة تحدد البنية التقنية لـ WP-HEART كما تم تعريف نطاق المنتج في Product Requirements Specification v1.0.

الوثيقة تجيب عن:

- كيف يكتشف WP-HEART قاعدة البيانات؟
- كيف يمثلها داخليًا؟
- كيف يميز الواقع الفعلي عن الاستنتاج؟
- كيف يكتشف ملكية الجداول؟
- كيف يبني Health Engine؟
- كيف يؤمن REST API؟
- كيف يمنع Database Mutation في Release 1.0؟
- كيف يتعامل مع قواعد البيانات الكبيرة؟
- كيف يعمل مع custom prefixes وMultisite؟
- كيف تتواصل React UI مع WordPress backend؟
- كيف يتم اختبار النظام؟
- كيف تبقى البنية قابلة للتوسع مستقبلًا؟

هذه الوثيقة لا تضيف متطلبات منتج جديدة.

عند وجود تعارض بينها وبين Requirements Specification، تكون Requirements Specification هي المرجع الأعلى.

---

# 2. Architectural Principle

البنية الأساسية لـ WP-HEART هي:

```text
┌─────────────────────────────────────────────┐
│             Developer Interface             │
│           React + TypeScript UI             │
└──────────────────────┬──────────────────────┘
                       │
                       │ REST API
                       ▼
┌─────────────────────────────────────────────┐
│              Application Layer              │
│ Controllers / Authorization / DTOs / Policy │
└──────────────────────┬──────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────┐
│                Domain Layer                  │
│ Discovery / Intelligence / Diagnostics       │
│ Classification / Search / Query Policy      │
└──────────────────────┬──────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────┐
│          Database Inspection Layer           │
│ Metadata / Schema / Indexes / Constraints    │
└──────────────────────┬──────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────┐
│             WordPress Database              │
│                 MySQL / MariaDB             │
└─────────────────────────────────────────────┘
```

المبدأ:

> **Database Reality → Intelligence → Developer Interface**

ولا يجوز عكس هذا الاتجاه.

---

# 3. Architectural Separation

يجب فصل المسؤوليات التالية:

```text
Database Connection
        ↓
Database Discovery
        ↓
Metadata Extraction
        ↓
Schema Inspection
        ↓
Domain Normalization
        ↓
WordPress Intelligence
        ↓
Classification
        ↓
Diagnostics
        ↓
REST Presentation
        ↓
React UI
```

لا يجوز أن تقوم React components بتنفيذ database logic.

ولا يجوز أن تقوم REST controllers ببناء business logic معقد.

ولا يجوز أن يعتمد classification على UI.

ولا يجوز أن يعتمد security على frontend controls.

---

# 4. Backend Architecture

البنية المقترحة:

```text
src/
└── WPHeart/
    ├── Application/
    ├── Domain/
    ├── Infrastructure/
    ├── WordPress/
    ├── Database/
    ├── Discovery/
    ├── Intelligence/
    ├── Classification/
    ├── Diagnostics/
    ├── Search/
    ├── Query/
    ├── Security/
    ├── REST/
    ├── Cache/
    ├── Audit/
    └── Support/
```

كل module يجب أن يمتلك مسؤولية واضحة.

الهدف هو تجنب إنشاء class ضخمة تقوم بكل شيء.

---

# 5. Project Foundation

الـ plugin entry point:

```text
wp-heart.php
```

مسؤولياته محدودة إلى:

- WordPress plugin headers
- version definition
- bootstrap
- loading dependencies
- initialization
- hooks

لا يجوز وضع business logic داخل ملف plugin الرئيسي.

---

# 6. Dependency Management

يجب استخدام dependency injection قدر الإمكان.

يجب أن تكون الخدمات الأساسية قابلة للاستبدال في الاختبارات.

مثال معماري:

```text
DatabaseInspectorInterface
        ↑
        └── WpdbDatabaseInspector

TableDiscoveryInterface
        ↑
        └── DatabaseTableDiscovery

ClassificationEngineInterface
        ↑
        └── ClassificationEngine
```

الهدف هو فصل domain logic عن تفاصيل WordPress و`$wpdb`.

---

# 7. Database Abstraction Layer

WP-HEART يعتمد على `$wpdb` للوصول إلى قاعدة بيانات WordPress، ولكن لا يجب أن تنتشر `$wpdb` داخل domain/application code.

البنية:

```text
Domain/Application
        ↓
Database Interfaces
        ↓
WordPress Database Adapter
        ↓
$wpdb
```

يجب أن تكون طبقة Database قادرة على:

- الحصول على database metadata
- اكتشاف الجداول
- قراءة table metadata
- قراءة column metadata
- قراءة index metadata
- قراءة constraint metadata
- تنفيذ read queries
- pagination
- query limits

---

# 8. Database Reality Model

يجب أن يحتفظ النظام بمفهوم مستقل لـ Database Reality.

المعلومات التي تأتي مباشرة من قاعدة البيانات يجب أن تكون قابلة للتمييز عن المعلومات التي تم استنتاجها.

مثال:

```text
Physical Table
    ↓
Observed Metadata
    ↓
Normalized Domain Object
    ↓
Intelligence
```

لا يجوز للـ Intelligence تعديل observed metadata.

---

# 9. Domain Model

الـ domain model الأساسي يجب أن يتضمن:

```text
Database
Table
Column
Index
Constraint
Relationship
Plugin
Classification
Evidence
HealthIssue
Query
AuditEvent
```

---

# 10. Database Entity

يمثل قاعدة البيانات التي يعمل عليها WP-HEART.

يحتوي منطقيًا على:

```text
Database
├── name
├── engine
├── serverVersion
├── charset
├── collation
├── tableCount
├── estimatedSize
├── metadataFreshness
└── capabilityInformation
```

أي معلومة غير متاحة يجب تمثيلها كـ unavailable/unknown وليس اختراعها.

---

# 11. Table Entity

يمثل جدولًا فعليًا تم اكتشافه.

```text
Table
├── name
├── engine
├── charset
├── collation
├── estimatedRows
├── size
├── columns[]
├── indexes[]
├── constraints[]
├── relationships[]
└── classification
```

الـ `name` هو الاسم الفعلي في قاعدة البيانات.

لا يجوز تطبيعه إلى اسم افتراضي.

---

# 12. Column Entity

يجب أن يمثل المعلومات الفعلية عن العمود:

```text
Column
├── name
├── ordinalPosition
├── dataType
├── nativeType
├── nullable
├── default
├── extra
├── charset
└── collation
```

---

# 13. Index Entity

```text
Index
├── name
├── unique
├── primary
├── columns[]
├── cardinality
└── type
```

إذا لم تتوفر معلومة معينة، يجب عدم اختراعها.

---

# 14. Constraint Entity

يجب أن يكون هناك model مستقل للقيود الفعلية.

مثال:

```text
Constraint
├── name
├── type
├── table
├── columns
├── referencedTable
└── referencedColumns
```

في Release 1.0، أهم constraint هو foreign-key metadata حيث تكون متاحة.

غياب constraint ليس خطأ بحد ذاته.

---

# 15. Relationship Model

Relationship ليست دائمًا Foreign Key.

لذلك يجب أن يكون model العلاقة:

```text
Relationship
├── source
├── target
├── type
├── evidence[]
├── confidence
└── origin
```

حيث:

```text
origin =
    PHYSICAL
    INFERRED
```

العلاقة الفيزيائية تعتمد على database metadata.

العلاقة المستنتجة تعتمد على evidence.

لا يجوز عرض العلاقة المستنتجة كأنها constraint فعلي.

---

# 16. Evidence Model

كل Intelligence مهمة يجب أن تكون قابلة لتفسير سبب نتيجتها.

النموذج المفاهيمي:

```text
Evidence
├── source
├── type
├── description
├── strength
└── metadata
```

مصادر evidence قد تشمل:

```text
WORDPRESS_CORE
PLUGIN_METADATA
PLUGIN_SCHEMA
TABLE_NAME_PATTERN
COLUMN_PATTERN
DATABASE_CONSTRAINT
MULTISITE_CONTEXT
KNOWN_SCHEMA_SIGNATURE
OTHER
```

---

# 17. Confidence Model

يجب أن تكون نتائج Intelligence قابلة للتعبير عن درجة الثقة.

النموذج المقترح:

```text
HIGH
MEDIUM
LOW
UNKNOWN
```

مثال:

```text
Classification:
PLUGIN

Confidence:
HIGH

Evidence:
Plugin metadata
+
Known schema signature
+
Registered table
```

لا يجوز إعطاء:

```text
HIGH
```

عندما تكون الأدلة ضعيفة.

---

# 18. Table Classification Engine

Classification Engine مسؤول عن تفسير ملكية الجداول.

المدخلات:

```text
Observed Table
+
WordPress Context
+
Plugin Context
+
Schema Evidence
+
Naming Evidence
```

المخرجات:

```text
Classification
+
Confidence
+
Evidence[]
```

التصنيف لا يغير الجدول نفسه.

---

# 19. Classification Priority

عند وجود أدلة متعارضة، يجب أن يتبع النظام مبدأ:

> أقوى دليل يتغلب على أضعف دليل.

لكن لا يجوز تحويل هذا إلى قاعدة جامدة تعتمد على الاسم وحده.

يجب أن تكون classification نتيجة evidence aggregation.

---

# 20. WordPress Core Intelligence

يجب أن يكون هناك طبقة متخصصة لفهم WordPress Core.

يمكنها استخدام:

- WordPress database API
- registered table names
- WordPress installation context
- multisite context
- known Core schema

ولا يجوز افتراض أن prefix هو:

```text
wp_
```

يجب استخدام prefix الفعلي الذي توفره بيئة WordPress.

---

# 21. Plugin Intelligence

Plugin Intelligence مسؤولة عن محاولة اكتشاف العلاقة بين الجداول والـ plugins.

مصادر evidence قد تشمل:

- active plugins
- plugin metadata
- known table registration
- plugin source analysis عندما يكون آمنًا ومتاحًا
- schema signatures
- table naming patterns
- column patterns
- known plugin structures

النتيجة ليست دائمًا مؤكدة.

لذلك يجب أن تنتج:

```text
Plugin
+
Confidence
+
Evidence
```

---

# 22. Unknown Detection

إذا لم تكن الأدلة كافية:

```text
Classification = UNKNOWN
```

ولا يجوز تحويل UNKNOWN إلى:

```text
ORPHAN
```

بلا evidence إضافي.

---

# 23. Orphan Candidate Detection

Orphan detection طبقة تحليلية مستقلة.

يمكنها استخدام:

```text
Known ownership
+
Current plugin state
+
Schema evidence
+
Historical signatures where available
```

النتيجة:

```text
ORPHAN_CANDIDATE
```

ولا تعني:

```text
DELETE
```

ولا تعني:

```text
SAFE TO REMOVE
```

---

# 24. Health Engine

Health Engine عبارة عن framework وليس diagnostic واحدًا.

```text
HealthEngine
    ├── Diagnostic A
    ├── Diagnostic B
    ├── Diagnostic C
    └── Diagnostic N
```

كل diagnostic يجب أن يكون مستقلًا.

---

# 25. Diagnostic Contract

كل diagnostic يجب أن يحدد:

```text
ID
Name
Description
Severity
Evidence
Affected Objects
Explanation
Recommendation
```

مثال:

```text
Issue:
Suspicious Missing Index

Severity:
WARNING

Affected:
wp_example

Evidence:
...

Recommendation:
Inspect query patterns before taking action.
```

---

# 26. Severity Model

يجب استخدام مستويات واضحة:

```text
INFO
NOTICE
WARNING
ERROR
CRITICAL
```

`CRITICAL` لا يستخدم إلا عند وجود evidence تقني واضح.

لا يجوز رفع severity لإجبار المستخدم على اتخاذ إجراء.

---

# 27. Query Architecture

Query execution يجب أن يمر عبر:

```text
REST Controller
        ↓
Authorization
        ↓
Query Policy
        ↓
Query Validation
        ↓
Query Service
        ↓
Database Adapter
```

ولا يجوز:

```text
REST Controller
        ↓
$wpdb مباشرة
```

---

# 28. Read-Only Policy

Release 1.0 تعمل في:

```text
READ-ONLY MODE
```

Query Policy يجب أن ترفض العمليات غير المسموحة.

يجب أن تكون السياسة enforced server-side.

إخفاء زر في React ليس security control.

---

# 29. Query Validation

يجب التحقق من:

- request structure
- query length
- allowed statement type
- query limits
- timeout policy
- pagination where applicable
- user capability

ويجب رفض query غير المسموح بها قبل التنفيذ.

---

# 30. SQL Safety

القيم الديناميكية يجب أن تستخدم prepared statements حيث ينطبق ذلك.

أما أسماء الجداول والأعمدة فلا يمكن تمريرها كقيم prepared statement.

لذلك يجب:

```text
Validate Identifier
+
Allowlist / strict identifier rules
+
Safe query construction
```

---

# 31. REST API Architecture

REST API هي طبقة الاتصال الرسمية بين frontend وbackend.

Namespace:

```text
wp-heart/v1
```

أمثلة مفاهيمية:

```text
GET    /database
GET    /tables
GET    /tables/{table}
GET    /tables/{table}/schema
GET    /tables/{table}/rows
GET    /tables/{table}/rows/{id}
GET    /search
GET    /health
GET    /issues
POST   /query
GET    /audit
GET    /settings
```

هذه المسارات تمثل architecture contract، أما التفاصيل النهائية للـ payloads فتحددها API contract أثناء التنفيذ.

---

# 32. REST Security

كل endpoint يجب أن يحدد صراحة:

```text
Authentication
Capability
Request Validation
Nonce requirement where applicable
Input validation
Output sanitization
Query policy
Rate / cost controls where appropriate
```

لا يجوز وجود endpoint privileged بدون permission callback واضح.

---

# 33. REST Response Model

يفضل توحيد شكل responses.

مثال مفاهيمي:

```text
{
    data: ...,
    meta: ...,
    errors: ...
}
```

يجب أن تتضمن metadata عند الحاجة:

```text
estimated
exact
cached
freshness
pagination
```

---

# 34. Pagination Contract

كل endpoint قد يعيد عددًا كبيرًا من النتائج يجب أن يدعم server-side pagination.

مثال:

```text
page
per_page
has_more
total
total_mode
```

حيث:

```text
total_mode =
    EXACT
    ESTIMATED
    UNKNOWN
```

---

# 35. Database Search Architecture

Search Engine يجب أن يفصل:

```text
Search Request
        ↓
Search Scope
        ↓
Candidate Tables
        ↓
Candidate Columns
        ↓
Bounded Queries
        ↓
Result Normalization
```

يجب أن تكون عمليات البحث bounded.

لا يجوز ترك query غير محدودة يمكن أن تسبب database exhaustion.

---

# 36. Database Map Architecture

Database Map يعتمد على Domain Graph.

```text
Table Nodes
+
Physical Relationships
+
Inferred Relationships
```

يجب أن يحتوي كل edge على:

```text
origin
confidence
evidence
```

الـ frontend لا يقوم باستنتاج العلاقات.

---

# 37. Cache Architecture

الـ cache مخصص للـ metadata expensive operations.

مثل:

```text
database metadata
table list
schema metadata
plugin detection metadata
health metadata where appropriate
```

يجب أن يحتوي cache entry منطقيًا على:

```text
key
value
created_at
expires_at
version
scope
```

---

# 38. Cache Invalidation

يجب دعم:

```text
Automatic expiration
+
Explicit Refresh
+
Invalidation after relevant state changes
```

يجب ألا يعتمد النظام على cache دائم لا يمكن تحديثه.

---

# 39. Cache vs Snapshot

يجب الحفاظ على الفصل الصريح:

```text
CACHE
= performance mechanism

SNAPSHOT
= historical state representation
```

Release 1.0 تحتاج Cache.

Persistent Historical Snapshots ليست جزءًا من Cache Architecture.

إذا أضيفت Snapshots مستقبلًا، يجب أن تكون capability مستقلة.

---

# 40. Performance Architecture

المبدأ:

```text
Never scan unnecessarily.
Never load unnecessary rows.
Never block the admin UI.
```

يجب أن تستخدم العمليات الثقيلة:

- caching
- pagination
- lazy loading
- asynchronous REST
- bounded queries
- incremental processing

---

# 41. Large Database Strategy

في قاعدة بيانات ضخمة:

```text
Database Discovery
        ↓
Metadata first
        ↓
Lazy detail loading
        ↓
Explicit expensive operations
```

مثال:

عند فتح Tables Explorer، لا يجب تحميل كل rows.

عند فتح Table:

```text
Metadata first
↓
Rows only when requested
```

---

# 42. Estimated vs Exact Metadata

يجب أن يكون domain model قادرًا على التعبير عن:

```text
value
+
accuracy mode
```

مثال:

```text
rowCount:
1,240,000

mode:
ESTIMATED
```

وعند طلب exact count:

```text
rowCount:
1,237,841

mode:
EXACT
```

---

# 43. Frontend Architecture

الواجهة:

```text
React
+
TypeScript
```

تتواصل فقط مع:

```text
wp-heart/v1
```

البنية:

```text
src/
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

Business logic لا يوضع داخل UI components.

---

# 44. Frontend Data Flow

```text
Page
 ↓
Feature Hook
 ↓
API Client
 ↓
REST API
 ↓
Backend Service
```

لا يجوز:

```text
React
 ↓
$wpdb
```

---

# 45. UI Information Architecture

الواجهة الأساسية:

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

كل شاشة يجب أن تخدم developer investigation workflow.

---

# 46. Table Explorer Architecture

Table Explorer يجب أن يعتمد على:

```text
Table Query
+
Filters
+
Sorting
+
Pagination
+
Classification
```

الجدول يجب أن يظهر:

- name
- classification
- confidence
- size
- rows
- engine
- health indicators

دون إجراء database-wide expensive operations عند كل render.

---

# 47. Table Inspector

عند اختيار جدول:

```text
Overview
Structure
Indexes
Constraints
Relationships
Data
Diagnostics
```

يتم تحميل التفاصيل تدريجيًا.

---

# 48. Row Inspector

Row Inspector يجب أن يستخدم lazy rendering للقيم الكبيرة.

يجب التعامل مع:

```text
NULL
TEXT
LONG TEXT
JSON
SERIALIZED DATA
BINARY
```

بحسب نوع البيانات.

لا يجب افتراض أن كل قيمة قابلة للعرض كنص عادي.

---

# 49. Sensitive Data Handling

UI يجب ألا تقوم بإرسال البيانات إلى external services.

يجب أن يكون التعامل مع sensitive fields محليًا.

في المستقبل يمكن إضافة configurable masking policies، لكن Release 1.0 لا تحتاج إلى خدمة خارجية للـ masking.

---

# 50. Security Architecture

Security model متعدد الطبقات:

```text
WordPress Authentication
        ↓
WP-HEART Capability
        ↓
Nonce / Request Verification
        ↓
REST Permission
        ↓
Application Authorization
        ↓
Query Policy
        ↓
Database Privileges
```

كل طبقة لها وظيفة مختلفة.

---

# 51. Capability Model

يجب ألا يعتمد WP-HEART فقط على:

```text
manage_options
```

كحل معماري نهائي.

يجب تصميم capabilities مستقلة، مثل:

```text
view_database
view_data
run_read_queries
view_audit
manage_settings
```

الأسماء النهائية يجب تثبيتها أثناء implementation contract.

Capabilities يجب أن تسمح بأقل privilege ممكن.

---

# 52. Database Privilege Awareness

يجب على WP-HEART التعامل مع حقيقة أن WordPress database user قد يمتلك صلاحيات تختلف حسب البيئة.

يمكن للنظام اكتشاف أو عرض privilege limitations حيث يمكن ذلك بأمان.

لكن WP-HEART لا يفترض أنه يستطيع تبديل database user.

الـ database-level privilege isolation يبقى طبقة deployment security إضافية.

---

# 53. Error Architecture

يجب فصل:

```text
Internal Exception
        ↓
Application Error
        ↓
Safe REST Error
```

المستخدم يحصل على رسالة مفهومة.

Developer diagnostic context يمكن تسجيله داخليًا.

لا يجب كشف:

- credentials
- filesystem paths
- secrets
- unnecessary SQL internals

---

# 54. Logging Architecture

Logger مركزي:

```text
Logger
├── Discovery
├── REST
├── Diagnostics
├── Query
├── Security
└── System
```

يجب أن تكون logs structured قدر الإمكان.

لا يجب تسجيل database content بالكامل.

---

# 55. Audit Architecture

AuditEvent model:

```text
AuditEvent
├── id
├── eventType
├── actor
├── timestamp
├── target
├── metadata
└── outcome
```

الأحداث الأولية:

```text
DATABASE_SCAN
TABLE_VIEW
ROW_VIEW
SEARCH
QUERY_EXECUTION
SETTINGS_CHANGE
```

Audit metadata يجب أن يتجنب sensitive content.

---

# 56. Multisite Architecture

يجب أن يكون WordPress context service قادرًا على التمييز بين:

```text
Single Site
Multisite Network
Current Site
Network Context
```

Discovery يجب أن يرى database reality الكاملة التي يمكن للاتصال الحالي الوصول إليها، مع إبقاء WordPress interpretation مرتبطة بالسياق الصحيح.

---

# 57. Prefix Handling

يجب أن يكون prefix ديناميكيًا بالكامل.

يحظر:

```text
$wp_prefix = 'wp_';
```

كافتراض.

يجب استخدام:

```text
$wpdb->prefix
```

والـ WordPress APIs ذات الصلة عند الحاجة.

لكن database discovery لا يجب أن يقتصر على tables التي تبدأ بهذا prefix.

---

# 58. Unknown Database Objects

أي object غير معروف يجب أن يظل قابلًا للوصول من خلال:

```text
Database
→ Tables
```

Classification layer هي التي تقول:

```text
UNKNOWN
```

وليس Discovery layer.

هذا الفصل مهم حتى لا تقوم Intelligence بإخفاء Database Reality.

---

# 59. Internal State

WP-HEART يجب أن يفرق بين:

```text
Observed State
Derived State
Cached State
User Settings
Audit State
```

لا يجوز تخزين derived classification وكأنها database fact.

---

# 60. State Freshness

كل information حساسة للزمن يجب أن يكون لها مفهوم freshness.

مثال:

```text
Discovery:
Fresh 2 minutes ago

Classification:
Fresh 2 minutes ago

Health:
Calculated 3 minutes ago
```

يساعد ذلك المطور على فهم ما إذا كان يشاهد database state حاليًا أو cached information.

---

# 61. Testing Architecture

Testing stack الرسمي:

```text
Backend
PHPUnit
WordPress PHPUnit Test Suite

Frontend
Jest
React Testing Library

Static Analysis
PHPStan
PHPCS / WordPress Coding Standards

Type Safety
TypeScript

Integration
MySQL / MariaDB test environment
```

---

# 62. Backend Unit Tests

يجب اختبار:

- classification logic
- evidence aggregation
- confidence calculation
- relationship normalization
- query policy
- pagination logic
- diagnostic rules
- error normalization

هذه الاختبارات يجب ألا تعتمد على WordPress عندما لا تكون هناك حاجة لذلك.

---

# 63. Integration Tests

يجب اختبار:

- actual database discovery
- schema inspection
- indexes
- constraints
- custom prefixes
- multisite
- plugin tables
- unknown tables
- large tables

---

# 64. REST Tests

يجب اختبار:

- authentication
- capability enforcement
- nonce behavior where applicable
- invalid requests
- malformed identifiers
- unauthorized access
- pagination
- query policy
- response schema

---

# 65. Security Tests

يجب أن تتضمن:

```text
SQL Injection
Privilege Escalation
Unauthorized REST Access
Identifier Injection
Malformed Requests
Capability Bypass
Nonce Bypass
Sensitive Error Leakage
```

---

# 66. Frontend Tests

يجب اختبار:

- rendering
- loading states
- error states
- pagination
- filters
- table inspection
- row inspection
- health display
- confidence display
- inferred vs physical relationship visualization
- accessibility behavior

---

# 67. Performance Tests

يجب إنشاء tests/benchmarks للعمليات الحساسة:

```text
Large table discovery
Schema inspection
Table listing
Search
Pagination
Health diagnostics
Classification
```

الهدف ليس تحقيق رقم ثابت لكل installation.

الهدف منع architecture من الانزلاق إلى full-table/full-database loading.

---

# 68. Accessibility Architecture

Accessibility يجب أن تكون جزءًا من component design.

يجب مراعاة:

- keyboard navigation
- focus management
- semantic HTML
- accessible tables
- accessible dialogs
- screen-reader labels
- status announcements
- reduced motion

---

# 69. Internationalization Architecture

كل النصوص user-facing يجب أن تكون قابلة للترجمة.

يجب عدم hard-code النصوص داخل business logic.

RTL يجب أن يكون architecture-ready منذ البداية.

---

# 70. Observability of WP-HEART Itself

WP-HEART يجب أن يكون قادرًا على تشخيص أخطائه دون كشف معلومات حساسة.

يجب أن تكون هناك visibility حول:

```text
Discovery status
Cache status
Diagnostic status
REST errors
Query failures
Permission limitations
```

---

# 71. Extensibility Model

يجب تصميم architecture بحيث يمكن إضافة future capabilities:

```text
Snapshot Service
Schema Diff Engine
Query Profiler
Performance Analyzer
Safe Operations
Repair Engine
Migration Engine
Documentation Generator
AI Analysis Layer
```

لكن هذه services لا تدخل في Release 1.0 read-only execution path.

---

# 72. Future Mutation Boundary

أي future mutation يجب أن تمر عبر طبقة مستقلة:

```text
UI
 ↓
REST
 ↓
Authorization
 ↓
Operation Policy
 ↓
Safety Checks
 ↓
Operation Service
 ↓
Audit
 ↓
Database
```

ولا يجوز تنفيذ:

```text
UPDATE
DELETE
ALTER
DROP
```

داخل Query Controller كاختصار.

---

# 73. Future Safe Operations

Safe Operations ستكون capability مستقلة.

ستحتاج مستقبلًا إلى:

- explicit permissions
- operation preview
- validation
- confirmation
- audit
- transaction where supported
- rollback strategy where possible
- failure handling

لكن هذه requirements ليست جزءًا من Release 1.0 execution path.

---

# 74. Future Repair Architecture

Repair Engine يجب مستقبلًا أن يكون منفصلًا عن Health Engine.

Health Engine:

```text
Detect
Explain
Recommend
```

Repair Engine:

```text
Plan
Validate
Preview
Confirm
Execute
Audit
```

لا يجوز أن يتحول diagnostic إلى automatic repair.

---

# 75. Snapshot Architecture — Future

Persistent snapshots يجب أن تكون domain capability مستقلة.

Snapshot يجب أن تمثل:

```text
Database Metadata
Schema
Indexes
Constraints
Classification State
Health State
```

ولا يجوز أن تحتوي complete table rows بشكل صامت.

---

# 76. Schema Diff — Future

Schema Diff يجب أن يقارن:

```text
Snapshot A
vs
Snapshot B
```

ويحدد:

```text
Added
Removed
Changed
```

على مستوى:

- tables
- columns
- indexes
- constraints

ويجب أن يبقى منفصلًا عن mutation engine.

---

# 77. API Versioning

REST namespace:

```text
wp-heart/v1
```

أي breaking change يجب ألا يحدث بصمت.

عند الحاجة إلى breaking API:

```text
wp-heart/v2
```

يمكن أن يظهر مستقبلًا.

---

# 78. Data Transfer Objects

REST responses يجب ألا تعتمد مباشرة على domain entities.

يفضل:

```text
Domain Model
    ↓
DTO / Presenter
    ↓
REST Response
```

هذا يمنع تسريب internal implementation details.

---

# 79. Authorization Boundary

Authorization يجب أن تتم قبل تنفيذ العمليات المكلفة.

الترتيب:

```text
Authenticate
↓
Authorize
↓
Validate
↓
Execute
```

وليس:

```text
Execute
↓
Check permission
```

---

# 80. Query Cost Control

كل عملية database يجب أن يكون لها cost awareness.

يجب تحديد:

- maximum result size
- maximum page size
- query timeout strategy where possible
- search limits
- expensive operation confirmation where appropriate

الهدف منع WP-HEART من تحويل نفسه إلى سبب في database exhaustion.

---

# 81. Database Identifier Safety

Table names وcolumn names وindex names لا تعامل كـ arbitrary user strings.

يجب:

```text
Validate
Normalize
Verify existence
Construct safely
```

قبل استخدامها في SQL.

---

# 82. No Hidden Assumptions

يحظر على architecture افتراض:

```text
wp_ prefix
Fixed Core tables
Plugin naming conventions
Foreign keys always exist
Fixed storage engine
Fixed row counts
Small database
Single-site environment
```

كل هذه assumptions يجب أن تكون قابلة للكسر دون انهيار النظام.

---

# 83. Relationship Truth Policy

قاعدة أساسية للـ UI والـ API:

> لا يتم تقديم relationship كـ database fact إلا إذا كانت مدعومة بـ database metadata فعلية.

إذا كانت inferred:

```text
relationship.origin = INFERRED
```

ويجب أن يظهر ذلك في الـ API والـ UI.

---

# 84. Classification Truth Policy

قاعدة مماثلة:

> لا يتم تقديم ownership كحقيقة مؤكدة إلا إذا كانت الأدلة كافية.

لذلك:

```text
Owner
+
Confidence
+
Evidence
```

هي الوحدة الصحيحة للـ Intelligence result.

---

# 85. Health Truth Policy

Health Engine لا يصدر أحكامًا مطلقة عندما تكون البيانات ناقصة.

مثال:

بدل:

```text
Database is broken.
```

يجب أن يقول:

```text
Potential issue detected.

Evidence:
...

Confidence:
...

Recommended investigation:
...
```

---

# 86. Deployment Model

WP-HEART يعمل كـ WordPress plugin.

لا يحتاج Release 1.0 إلى:

- external daemon
- external database
- external SaaS
- external API

Core functionality يجب أن تعمل داخل WordPress environment.

---

# 87. Public Site Isolation

WP-HEART admin functionality يجب ألا تدخل في frontend rendering path إلا عند الضرورة.

لا يجوز:

```text
Database discovery
```

في كل frontend request.

يجب تحميل functionality الخاصة بالـ admin عند الحاجة فقط.

---

# 88. Activation Behavior

Activation يجب أن يكون lightweight.

لا يجب إجراء database-wide scans عند activation إلا إذا كانت هناك ضرورة تقنية واضحة.

يجب أن تبدأ discovery عند فتح WP-HEART أو عند explicit refresh.

---

# 89. Uninstall Behavior

Uninstall يجب ألا يحذف database user data.

أي WP-HEART internal data يجب أن يكون له سياسة uninstall صريحة.

في Release 1.0، يفضل الحد الأدنى من internal storage.

---

# 90. Architecture Dependency Direction

الاتجاه المسموح:

```text
Infrastructure
      ↓
Domain
```

ليس المقصود أن Domain يعتمد على WordPress implementation details.

الأفضل:

```text
Domain
↑
Application
↑
Infrastructure Adapters
```

بحيث يمكن اختبار Domain دون WordPress قدر الإمكان.

---

# 91. Module Responsibilities

### Database

مسؤول عن الاتصال وقراءة database metadata.

### Discovery

مسؤول عن اكتشاف database objects.

### Schema

مسؤول عن تفسير structure.

### Intelligence

مسؤول عن WordPress/plugin interpretation.

### Classification

مسؤول عن ownership classification.

### Diagnostics

مسؤول عن health analysis.

### Search

مسؤول عن bounded database search.

### Query

مسؤول عن read-only query execution.

### Security

مسؤول عن authorization/policy.

### Cache

مسؤول عن metadata caching.

### REST

مسؤول عن API transport.

### Frontend

مسؤول عن presentation وinteraction.

---

# 92. Engineering Rules

يجب أن يلتزم التنفيذ بالقواعد التالية:

```text
No business logic in React components.
No direct $wpdb access from frontend.
No database credentials exposed.
No assumed wp_ prefix.
No hidden unknown tables.
No unsupported ownership claims.
No unsupported relationships.
No frontend-only security.
No destructive SQL in Release 1.0.
No unbounded full-table loading.
No database-wide scan on every request.
No silent sensitive-data logging.
```

---

# 93. Architectural Testing Gate

لا تعتبر feature مكتملة إلا إذا كانت:

```text
Implemented
+
Tested
+
Security Reviewed
+
Performance Reviewed
```

feature التي تعمل لكنها تكسر أحد هذه الأبعاد ليست production-ready.

---

# 94. Implementation Order

التنفيذ يجب أن يبدأ من الداخل إلى الخارج:

```text
01 Foundation
      ↓
02 Database Abstraction
      ↓
03 Database Discovery
      ↓
04 Schema Inspection
      ↓
05 WordPress Intelligence
      ↓
06 Plugin Intelligence
      ↓
07 Classification
      ↓
08 Health Engine
      ↓
09 Security / Query Policy
      ↓
10 REST API
      ↓
11 Frontend Foundation
      ↓
12 Tables Explorer
      ↓
13 Data Inspector
      ↓
14 Search
      ↓
15 Query Console
      ↓
16 Database Map
      ↓
17 Audit
      ↓
18 Performance Hardening
      ↓
19 Multisite
      ↓
20 i18n / RTL / Accessibility
      ↓
21 Testing / Packaging / Release
```

لا يبدأ المشروع من UI mockups ثم يتم اختراع backend لاحقًا.

---

# 95. Release 1.0 Architecture Boundary

الـ architecture النهائي لـ Release 1.0 هو:

```text
OBSERVATION
    ✓

DISCOVERY
    ✓

SCHEMA INSPECTION
    ✓

CLASSIFICATION
    ✓

WORDPRESS INTELLIGENCE
    ✓

PLUGIN INTELLIGENCE
    ✓

HEALTH
    ✓

SEARCH
    ✓

READ QUERY
    ✓

EXPLAIN
    ✓

DATABASE MAP
    ✓

AUDIT ARCHITECTURE
    ✓

CACHE
    ✓

MULTISITE
    ✓

ACCESSIBILITY
    ✓

INTERNATIONALIZATION
    ✓

DATABASE MUTATION
    ✗

REPAIR
    ✗

MIGRATION
    ✗
```

---

# 96. Architectural Quality Gate

قبل الانتقال إلى Implementation Specification يجب أن تكون الإجابات التالية واضحة:

```text
Where does database access happen?
Where does business logic happen?
Where does classification happen?
Where does evidence live?
Where is confidence calculated?
Where is authorization enforced?
Where is read-only policy enforced?
Where is caching handled?
Where is pagination enforced?
Where are relationships represented?
How are inferred relationships separated?
How are unknown tables preserved?
How are errors normalized?
How is REST versioned?
How is frontend isolated from $wpdb?
How are large databases handled?
How are tests structured?
```

إذا لم تكن الإجابة موجودة في architecture، فلا يجب أن يخترعها Implementation Agent أثناء البرمجة.

---

# 97. Source-of-Truth Relationship

العلاقة بين الوثائق:

```text
Product Requirements
        │
        │ defines WHAT
        ▼
Technical Design
        │
        │ defines HOW
        ▼
Implementation & Delivery
        │
        │ defines EXECUTION
        ▼
Production Code
```

كل طبقة تعتمد على الطبقة الأعلى.

ولا يجوز للـ Implementation Specification تغيير architecture.

---

# 98. Final Architectural Principle

WP-HEART يجب أن يبقى:

> **A database observability system with WordPress intelligence.**

وليس:

> a generic database administration panel with WordPress branding.

الـ database reality هي المصدر الأول.

الـ intelligence طبقة تفسير.

الـ UI طبقة عرض.

والأمان ليس feature إضافية، بل boundary معماري.

---

# 99. Final Statement

البنية النهائية يجب أن تحقق المعادلة التالية:

```text
REAL DATABASE
      +
EVIDENCE
      +
WORDPRESS CONTEXT
      +
SAFE OBSERVATION
      +
CLEAR PRESENTATION
      =
WP-HEART
```

WP-HEART observes first.

WP-HEART interprets second.

WP-HEART explains third.

WP-HEART does not mutate the database in Release 1.0.

وأي capability مستقبلية تتعلق بالتعديل أو الإصلاح أو migration يجب أن تدخل عبر طبقة مستقلة ذات authorization وvalidation وaudit وsafety controls خاصة بها.