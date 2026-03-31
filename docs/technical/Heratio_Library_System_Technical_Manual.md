# Heratio Library System Technical Manual

**Plugin:** ahgLibraryPlugin v2.0.0
**Framework:** Heratio (atom-framework v2.8.2)
**Author:** The Archive and Heritage Group (Pty) Ltd
**Date:** 2026-03-08

---

## 1. Architecture Overview

### 1.1 Plugin Structure

The ahgLibraryPlugin implements a full Integrated Library System (ILS) within Heratio. It extends the archival platform with circulation, cataloging, acquisitions, serials, interlibrary loan, and public access catalog (OPAC) capabilities.

```
ahgLibraryPlugin/
  config/
    ahgLibraryPluginConfiguration.class.php   # Plugin bootstrap, route registration
  database/
    install.sql                                # Base schema + seed data
    migration_full_library.sql                 # Full ILS migration (18 tables)
  lib/
    Commands/
      ProcessCoversCommand.php                 # Framework CLI command
    Model/
      LibraryItem.php                          # Value object / DTO
    Repository/
      LibraryRepository.php                    # Query layer for library_item
      IsbnLookupRepository.php                 # ISBN cache + audit
      SubjectAuthorityRepository.php           # LCSH subject authority queries
    Service/
      LibraryService.php                       # Core cataloging logic
      CirculationService.php                   # Checkout / checkin / renew
      PatronService.php                        # Patron CRUD + validation
      HoldService.php                          # Hold queue management
      FineService.php                          # Fines + payments
      AcquisitionService.php                   # Purchase orders + budgets
      SerialService.php                        # Subscriptions + issue tracking
      ILLService.php                           # Interlibrary loan workflows
      OpacService.php                          # Public catalog search + self-service
      MarcService.php                          # MARC 21 import/export
      BookCoverService.php                     # ISBN cover image proxy
      IsbnMetadataMapper.php                   # ISBN metadata normalization
      SubjectSuggestionService.php             # NER-based subject suggestions
      WorldCatService.php                      # WorldCat API integration
    helper/
      BookCoverHelper.php                      # Template helper for covers
    task/
      libraryCoverProcessTask.class.php        # Symfony CLI task wrapper
  modules/
    library/          # Cataloging: browse, view, edit, ISBN lookup, providers
    opac/             # Public access catalog: search, item detail, holds, account
    circulation/      # Checkout, checkin, renew, overdue, loan rules
    patron/           # Patron registration, view, suspend, reactivate
    acquisition/      # Purchase orders, order lines, receiving, budgets
    serial/           # Subscription management, issue check-in, claims
    ill/              # Interlibrary loan requests, status transitions
    isbn/             # ISBN lookup, test, statistics
    libraryReports/   # Library reporting dashboard
  web/                # Static assets (CSS, JS)
```

### 1.2 Symfony 1.x Integration

The plugin integrates with AtoM's Symfony 1.x framework through `ahgLibraryPluginConfiguration`:

- **Event listeners:** Connects to `routing.load_configuration` for route registration and `context.load_factories` for helper loading.
- **Module enablement:** Registers nine modules (`library`, `isbn`, `libraryReports`, `opac`, `circulation`, `patron`, `acquisition`, `serial`, `ill`) via `sfConfig::set('sf_enabled_modules', ...)`.
- **Route registration:** Uses the framework's `RouteLoader` class to prepend routes. Routes are added in reverse priority order because `prependRoute` places each route at the front of the routing table.

### 1.3 Service Layer Pattern

All services follow the singleton pattern with `require_once` loading (necessary because Symfony 1.x does not autoload namespaced plugin classes):

```php
class CirculationService
{
    protected static ?CirculationService $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

Every service initializes a Monolog `RotatingFileHandler` writing to `{sf_log_dir}/library.log` with 30-day retention. All database operations use Laravel Query Builder (`Illuminate\Database\Capsule\Manager as DB`).

### 1.4 Dependencies

- **Required:** ahgCorePlugin (framework integration)
- **Optional integrations:** ahgHeritageAccountingPlugin (GRAP 103 asset tracking), ahgAIPlugin (NER-based subject suggestions), ahgPreservationPlugin (format identification)

---

## 2. Database Schema

The library system uses 18 tables. All tables use InnoDB with `utf8mb4_unicode_ci` collation. Status/type fields reference `ahg_dropdown` values via the `ahg_dropdown_column_map` table.

### 2.1 library_item (80 columns)

The core bibliographic record, linked 1:1 to AtoM's `information_object`.

| Column Group | Key Columns | Notes |
|---|---|---|
| **Identity** | `id` (PK, BIGINT UNSIGNED AUTO_INCREMENT), `information_object_id` (INT UNSIGNED, FK to `information_object.id`) | One library_item per information_object |
| **Classification** | `material_type` (VARCHAR 50, default 'monograph'), `call_number`, `classification_scheme` (dewey/lcc), `classification_number`, `dewey_decimal`, `cutter_number`, `shelf_location` | |
| **Identifiers** | `isbn` (VARCHAR 17), `issn` (VARCHAR 9), `lccn`, `oclc_number`, `openlibrary_id`, `goodreads_id`, `librarything_id`, `doi`, `barcode` | |
| **Publication** | `subtitle`, `responsibility_statement`, `edition`, `edition_statement`, `publisher`, `publication_place`, `publication_date`, `copyright_date`, `printing` | |
| **Physical** | `pagination`, `dimensions`, `physical_details` (TEXT), `language`, `accompanying_material` (TEXT) | |
| **Series** | `series_title`, `series_number`, `series_issn`, `subseries_title` | |
| **Notes** | `general_note`, `bibliography_note`, `contents_note`, `summary`, `target_audience`, `system_requirements`, `binding_note` | All TEXT type |
| **Serials** | `frequency`, `former_frequency`, `numbering_peculiarities`, `publication_start_date`, `publication_end_date`, `publication_status` | |
| **Circulation** | `total_copies` (SMALLINT, default 1), `available_copies` (SMALLINT, default 1), `circulation_status` (VARCHAR 30, default 'available') | Maintained by CirculationService |
| **Cataloging** | `cataloging_source`, `cataloging_rules`, `encoding_level` | |
| **Covers** | `cover_url`, `cover_url_original`, `ebook_preview_url`, `openlibrary_url` | |
| **Heritage Accounting** | `heritage_asset_id`, `acquisition_method`, `acquisition_date`, `acquisition_cost`, `acquisition_currency` (default 'ZAR'), `replacement_value`, `insurance_value`, `insurance_policy`, `insurance_expiry` | GRAP 103 / IPSAS 45 fields |
| **Valuation** | `asset_class_code`, `recognition_status` (default 'pending'), `valuation_date`, `valuation_method`, `valuation_notes` | |
| **Provenance** | `donor_name`, `donor_restrictions` | |
| **Condition** | `condition_grade`, `conservation_priority` | |
| **Timestamps** | `created_at`, `updated_at` | |

**Indexes:** `information_object_id` (unique implicit), `isbn`, `issn`, `call_number`, `barcode`.

### 2.2 library_item_creator

Links creators (authors, editors, etc.) to library items.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `library_item_id` | BIGINT UNSIGNED FK | CASCADE on delete |
| `name` | VARCHAR(500) NOT NULL | Creator display name |
| `role` | VARCHAR(50) DEFAULT 'author' | MARC relator: author, editor, translator, illustrator, compiler, contributor |
| `sort_order` | INT DEFAULT 0 | Display ordering |
| `authority_uri` | VARCHAR(500) | Link to authority record (VIAF, LCNAF) |
| `is_primary` | implied via role/sort_order | Primary creator used in OPAC display |

### 2.3 library_item_subject

Subject headings with LCSH authority integration.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `library_item_id` | BIGINT UNSIGNED FK | CASCADE on delete |
| `heading` | VARCHAR(500) NOT NULL | Subject heading text |
| `subject_type` | VARCHAR(50) DEFAULT 'topic' | topical, personal, corporate, geographic, genre, meeting |
| `source` | VARCHAR(100) | lcsh, mesh, aat, fast, local |
| `uri` | VARCHAR(500) | Link to authority |
| `lcsh_id` | VARCHAR(100) | Authority record ID (e.g., sh85034652) |
| `authority_id` | BIGINT UNSIGNED FK | FK to `library_subject_authority.id` (SET NULL on delete) |
| `dewey_number` | VARCHAR(50) | Suggested Dewey from authority |
| `lcc_number` | VARCHAR(50) | Suggested LCC from authority |
| `subdivisions` | JSON | Array of geographic/chronological/form subdivisions |

### 2.4 library_copy

Individual physical copies of a library item.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `library_item_id` | BIGINT UNSIGNED FK | CASCADE on delete |
| `copy_number` | SMALLINT UNSIGNED DEFAULT 1 | |
| `barcode` | VARCHAR(50) UNIQUE | Auto-generated if not provided (format: `C1234567`) |
| `accession_number` | VARCHAR(50) | |
| `call_number_suffix` | VARCHAR(20) | e.g., c.2, v.3 |
| `shelf_location` | VARCHAR(100) | |
| `branch` | VARCHAR(100) | Library branch/location |
| `status` | VARCHAR(30) DEFAULT 'available' | From ahg_dropdown `copy_status` |
| `condition_grade` | VARCHAR(30) | |
| `condition_notes` | TEXT | |
| `acquisition_method/date/cost/source` | Various | Per-copy acquisition data |
| `withdrawal_date`, `withdrawal_reason` | DATE, TEXT | For deaccessioned copies |

**Indexes:** `uk_barcode` (UNIQUE), `idx_item`, `idx_status`, `idx_branch`, `idx_accession`.

### 2.5 library_patron

Library borrower records.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `actor_id` | INT UNSIGNED | Optional FK to AtoM `actor` table |
| `card_number` | VARCHAR(50) UNIQUE | Auto-generated (format: `P123456`) |
| `patron_type` | VARCHAR(30) DEFAULT 'public' | From ahg_dropdown `patron_type` |
| `first_name`, `last_name` | VARCHAR(100) NOT NULL | |
| `email`, `phone`, `address`, `institution`, `department` | Various | Contact info |
| `id_number`, `date_of_birth` | VARCHAR(50), DATE | Identification |
| `membership_start`, `membership_expiry` | DATE | |
| `max_checkouts` | SMALLINT DEFAULT 5 | |
| `max_renewals` | SMALLINT DEFAULT 2 | |
| `max_holds` | SMALLINT DEFAULT 3 | |
| `borrowing_status` | VARCHAR(20) DEFAULT 'active' | active, suspended, expired, blocked, inactive |
| `total_fines_owed/paid` | DECIMAL(10,2) | Running totals |
| `total_checkouts` | INT UNSIGNED | Lifetime count |

### 2.6 library_checkout

Circulation transactions.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `copy_id` | BIGINT UNSIGNED FK | RESTRICT on delete (preserve history) |
| `patron_id` | BIGINT UNSIGNED FK | RESTRICT on delete |
| `checkout_date` | DATETIME NOT NULL | |
| `due_date` | DATE NOT NULL | Calculated from loan rules |
| `return_date` | DATETIME | NULL until returned |
| `renewed_count` | SMALLINT DEFAULT 0 | |
| `status` | VARCHAR(30) DEFAULT 'active' | active, returned, overdue, lost, claimed_returned, damaged |
| `checked_out_by`, `checked_in_by` | INT UNSIGNED | Staff user IDs |

**Indexes:** `idx_copy`, `idx_patron`, `idx_status`, `idx_due`, `idx_checkout_date`.

### 2.7 library_hold

Patron reservation queue.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `library_item_id` | BIGINT UNSIGNED FK | Holds target items, not copies |
| `patron_id` | BIGINT UNSIGNED FK | |
| `hold_date` | DATETIME | |
| `expiry_date` | DATE | 7-day pickup window when status is 'ready' |
| `queue_position` | SMALLINT | Auto-managed by HoldService |
| `status` | VARCHAR(30) DEFAULT 'pending' | pending, available, fulfilled, expired, cancelled |
| `notification_sent` | TINYINT(1) | |
| `pickup_branch` | VARCHAR(100) | |

**Indexes:** `idx_queue` (library_item_id, queue_position).

### 2.8 library_fine

Fees, penalties, and payment records.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `patron_id` | BIGINT UNSIGNED FK | RESTRICT on delete |
| `checkout_id` | BIGINT UNSIGNED FK | SET NULL on delete |
| `fine_type` | VARCHAR(30) DEFAULT 'overdue' | overdue, lost_item, damaged, processing, replacement_card, ill_fee |
| `amount` | DECIMAL(10,2) | |
| `paid_amount` | DECIMAL(10,2) DEFAULT 0 | |
| `status` | VARCHAR(20) DEFAULT 'outstanding' | outstanding, paid, partial, waived, referred |
| `payment_method` | VARCHAR(30) | cash, card, eft, online, deduction |
| `payment_reference` | VARCHAR(100) | Receipt number |
| `waived_by`, `waived_date`, `waive_reason` | Various | Staff override fields |

### 2.9 library_loan_rule

Per-material-type lending policies.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `material_type` | VARCHAR(50) NOT NULL | Matches library_item.material_type |
| `patron_type` | VARCHAR(30) DEFAULT '*' | `*` = all types |
| `loan_period_days` | SMALLINT DEFAULT 14 | |
| `renewal_period_days` | SMALLINT DEFAULT 14 | |
| `max_renewals` | SMALLINT DEFAULT 2 | |
| `fine_per_day` | DECIMAL(10,2) DEFAULT 1.00 | |
| `fine_cap` | DECIMAL(10,2) | Maximum fine per checkout |
| `grace_period_days` | SMALLINT DEFAULT 0 | |
| `is_loanable` | TINYINT(1) DEFAULT 1 | 0 = reference only |

**Unique key:** `uk_type_patron` (material_type, patron_type). Seed data includes 11 default rules for monograph, serial, volume, issue, article, manuscript, map, pamphlet, score, electronic, and chapter.

### 2.10 library_order

Purchase orders.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `order_number` | VARCHAR(50) UNIQUE | Auto-generated: `PO-YYYY-NNNN` |
| `vendor_id`, `vendor_name` | INT/VARCHAR | |
| `order_date`, `expected_date`, `received_date` | DATE | |
| `status` | VARCHAR(30) DEFAULT 'draft' | draft, submitted, approved, ordered, partial, received, cancelled |
| `order_type` | VARCHAR(30) DEFAULT 'purchase' | purchase, standing_order, gift, exchange, deposit, approval |
| `budget_code` | VARCHAR(50) | Links to library_budget |
| `subtotal/tax/shipping/total` | DECIMAL(15,2) | Calculated from order lines |
| `invoice_number/date`, `payment_status` | Various | |

### 2.11 library_order_line

Individual line items on a purchase order.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `order_id` | BIGINT UNSIGNED FK | CASCADE on delete |
| `library_item_id` | BIGINT UNSIGNED | Optional link to existing catalog record |
| `title`, `isbn`, `issn`, `author`, `publisher`, `edition` | Various | Bibliographic details for the order |
| `material_type` | VARCHAR(50) | |
| `quantity` | SMALLINT DEFAULT 1 | |
| `unit_price`, `discount_percent`, `line_total` | DECIMAL | |
| `quantity_received`, `received_date` | Various | |
| `status` | VARCHAR(30) DEFAULT 'ordered' | |
| `budget_code`, `fund_code` | VARCHAR(50) | |

### 2.12 library_budget

Fund allocation for acquisitions.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `budget_code` | VARCHAR(50) | |
| `fund_name` | VARCHAR(255) | |
| `fiscal_year` | VARCHAR(9) | e.g., '2025/2026' |
| `allocated_amount`, `committed_amount`, `spent_amount` | DECIMAL(15,2) | |
| `category` | VARCHAR(50) | monographs, serials, electronic, etc. |
| `status` | VARCHAR(20) DEFAULT 'active' | |

**Unique key:** `uk_code_year` (budget_code, fiscal_year).

### 2.13 library_subscription

Serial/periodical subscription tracking.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `library_item_id` | BIGINT UNSIGNED FK | Parent serial record |
| `vendor_id` | INT UNSIGNED | |
| `subscription_number`, `status` | VARCHAR | |
| `start_date`, `end_date`, `renewal_date` | DATE | |
| `frequency` | VARCHAR(30) | daily, weekly, monthly, quarterly, annual, etc. |
| `issues_per_year` | SMALLINT | |
| `cost_per_year`, `currency` | DECIMAL, VARCHAR | |
| `routing_list` | JSON | Ordered staff routing list |

### 2.14 library_serial_issue

Individual issue tracking for serials.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `subscription_id` | BIGINT UNSIGNED FK | CASCADE on delete |
| `library_item_id` | BIGINT UNSIGNED FK | |
| `volume`, `issue_number`, `part`, `supplement` | VARCHAR | |
| `issue_date`, `expected_date`, `received_date` | DATE | |
| `status` | VARCHAR(30) DEFAULT 'expected' | expected, received, missing, claimed, damaged, bound |
| `claim_date`, `claim_count` | DATE, SMALLINT | |
| `barcode` | VARCHAR(50) UNIQUE | |
| `bound_volume_id` | BIGINT UNSIGNED | FK to bound volume record |

### 2.15 library_ill_request

Interlibrary loan requests (borrow and lend).

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `request_number` | VARCHAR(50) UNIQUE | |
| `direction` | VARCHAR(20) | 'borrowing' or 'lending' |
| `patron_id` | BIGINT UNSIGNED | Borrowing patron |
| `partner_library/contact/email` | VARCHAR | Other library details |
| `title/author/isbn/issn/publisher/publication_year` | Various | Bibliographic identification |
| `library_item_id`, `copy_id` | BIGINT UNSIGNED | Our item (if lending) |
| `status` | VARCHAR(30) DEFAULT 'requested' | requested, approved, shipped, received, in_use, returned, overdue, cancelled, denied |
| `request_date`, `needed_by`, `shipped_date`, `received_date`, `due_date`, `return_date` | DATE | Status-driven date fields |
| `shipping_method`, `tracking_number` | VARCHAR | |
| `cost`, `currency` | DECIMAL, VARCHAR | |

### 2.16 library_settings

Plugin-specific key/value configuration.

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `setting_key` | VARCHAR(100) UNIQUE | |
| `setting_value` | TEXT | |
| `setting_type` | VARCHAR(37) DEFAULT 'string' | |
| `description` | VARCHAR(255) | |

### 2.17 library_subject_authority

Controlled subject heading vocabulary with LCSH integration.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `heading` | VARCHAR(500) NOT NULL | Display form |
| `heading_normalized` | VARCHAR(500) NOT NULL | Matching form |
| `heading_type` | VARCHAR(68) DEFAULT 'topical' | topical, personal, corporate, geographic, genre, meeting |
| `source` | VARCHAR(50) DEFAULT 'lcsh' | lcsh, mesh, local, etc. |
| `lcsh_id`, `lcsh_uri` | VARCHAR | Authority record identifiers |
| `suggested_dewey`, `suggested_lcc` | VARCHAR(50) | Classification suggestions |
| `broader_terms`, `narrower_terms`, `related_terms` | JSON | Hierarchical relationships |
| `usage_count` | INT UNSIGNED DEFAULT 1 | Frequency tracking |

**Indexes:** UNIQUE `uk_heading` (heading_normalized, heading_type, source), FULLTEXT `ft_heading` (heading).

### 2.18 library_entity_subject_map

Bridges NER-extracted entities to subject authority records.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `entity_type` | VARCHAR(50) | NER type: PERSON, ORG, GPE, etc. |
| `entity_value`, `entity_normalized` | VARCHAR(500) | Original and normalized forms |
| `subject_authority_id` | BIGINT UNSIGNED FK | CASCADE on delete |
| `co_occurrence_count` | INT UNSIGNED DEFAULT 1 | |
| `confidence` | DECIMAL(5,4) DEFAULT 1.0000 | |

---

## 3. Service Layer

### 3.1 CirculationService

Core circulation engine handling checkout, checkin, and renewal workflows.

**Key methods:**

| Method | Purpose |
|---|---|
| `checkout(int $copyId, int $patronId, ?string $dueDate)` | Validates patron eligibility, checks copy availability, creates checkout record, updates copy status, fulfills pending holds |
| `checkoutByBarcode(string $copyBarcode, string $patronBarcode)` | Station/kiosk mode - resolves IDs from barcodes then delegates to `checkout()` |
| `checkin(int $checkoutId)` | Returns item, calculates overdue fine if applicable, updates copy to 'available', triggers hold queue processing |
| `checkinByBarcode(string $copyBarcode)` | Finds active checkout by copy barcode, delegates to `checkin()` |
| `renew(int $checkoutId)` | Validates renewal count and pending holds, extends due date using loan rule period |
| `getLoanRule(string $materialType, string $patronType)` | Three-tier fallback: exact match -> material default (patron_type='default') -> global default (both 'default') |
| `getOverdueCheckouts()` | Joins checkout, patron, copy, item, and information_object for overdue report |
| `addCopy(int $libraryItemId, array $data)` | Creates copy with auto-generated barcode, updates item copy counts |
| `getStatistics()` | Dashboard counts: active checkouts, overdue, today's activity, holds, fines |

**Transaction handling:** Checkout and checkin operations use `DB::connection()->beginTransaction()` with try/catch rollback to ensure atomicity across multiple table updates.

**Loan rule fallback chain:**
```
1. Exact: material_type='monograph' AND patron_type='student'
2. Material default: material_type='monograph' AND patron_type='default'
3. Global default: material_type='default' AND patron_type='default'
```

### 3.2 PatronService

Patron lifecycle management.

| Method | Purpose |
|---|---|
| `create(array $data)` | Register patron with auto-generated barcode (format: `P` + 6 digits) |
| `update(int $id, array $data)` | Whitelist-filtered update (prevents mass assignment) |
| `find(int $id)`, `findByBarcode(string)`, `findByUserId(int)` | Lookup methods |
| `search(array $params)` | Paginated search across name, email, barcode; filterable by type and status |
| `canBorrow(int $patronId)` | Multi-check validation: status active, membership not expired, under max checkouts, fines below threshold |
| `suspend(int $patronId, ?string $reason)` | Sets status to 'suspended', appends reason to notes with timestamp |
| `reactivate(int $patronId)` | Restores status to 'active' |
| `getCheckouts/getHolds/getFines/getHistory(int $patronId)` | Patron activity queries with joins to item/title data |

**Fine threshold:** Configurable via `library_settings` table (`fine_block_threshold` key, default 10.00). Patrons with outstanding fines at or above this amount are blocked from borrowing.

### 3.3 HoldService

Hold queue management. Holds target items (not copies) -- the system assigns a copy at fulfillment.

| Method | Purpose |
|---|---|
| `placeHold(int $libraryItemId, int $patronId)` | Validates: active patron, under max holds, no duplicate hold, no active checkout of same item. If copies available, hold is immediately 'ready' with 7-day expiry. |
| `cancelHold(int $holdId, ?int $patronId)` | Verifies ownership if patron ID given, sets status to 'cancelled', reorders queue |
| `getQueue(int $libraryItemId)` | Returns ordered queue with patron details |
| `expireOverdueHolds()` | Batch job: expires 'ready' holds past expiry_date, promotes next pending hold. Should be run daily via cron. |

**Auto-promotion:** When a copy is returned (via `CirculationService::checkin`), the `processHoldQueue` method marks the next pending hold as 'ready' with a 7-day pickup window.

### 3.4 FineService

Fine creation, payments, waivers, and daily batch processing.

| Method | Purpose |
|---|---|
| `createFine(int $patronId, string $fineType, float $amount, ?int $checkoutId)` | Generic fine creation |
| `createLostItemFine(int $checkoutId)` | Marks copy as lost, charges replacement_value (falls back to acquisition_cost, then 25.00) |
| `recordPayment(int $fineId, float $amount, string $method)` | Partial or full payment; updates status to 'paid' or 'partial' |
| `waiveFine(int $fineId, ?string $reason)` | Forgives outstanding balance, appends reason to description |
| `generateDailyOverdueFines()` | Batch operation: recalculates accumulating fines for all overdue checkouts using loan rule rates with fine_cap enforcement. Should be run daily via cron. |
| `getPatronBalance(int $patronId)` | SUM(amount - amount_paid) for outstanding/partial fines |

**Fine calculation:** `days_overdue * fine_per_day`, capped at `fine_cap` from the applicable loan rule. Fines are created at checkin time; the daily batch updates amounts for items still checked out.

### 3.5 AcquisitionService

Purchase order and budget management.

| Method | Purpose |
|---|---|
| `createOrder(array $data)` | Creates PO with auto-generated number (`PO-YYYY-NNNN`) |
| `addOrderLine(int $orderId, array $data)` | Adds line item, recalculates order total |
| `receiveOrderLine(int $orderLineId, int $qty)` | Records receipt, updates line/order status (partial/received) |
| `getOrder(int $orderId)` | Returns order with all line items |
| `searchOrders(array $params)` | Paginated search by order number, vendor, status, type |
| `createBudget/getBudgets/getBudgetSummary/recordExpenditure` | Fund allocation and tracking with available amount calculation |

**Order status logic:** Automatically computed from line statuses -- all received = 'received', any received = 'partial', otherwise 'pending'.

### 3.6 SerialService

Subscription and issue management.

| Method | Purpose |
|---|---|
| `createSubscription/updateSubscription` | CRUD with whitelist-filtered updates |
| `checkinIssue(int $subscriptionId, array $data)` | Records receipt of a serial issue |
| `claimIssue(int $issueId)` | Marks expected issue as 'claimed' (missing/late) |
| `generateExpectedIssues(int $subId, string $start, string $end)` | Creates expected issue records based on frequency (daily through biennial) |
| `getGaps(int $subscriptionId)` | Returns expected/claimed issues with past expected_date (gap analysis) |
| `getDueForRenewal(int $daysAhead)` | Subscriptions needing renewal within N days |

**Frequency mapping:** The `getFrequencyDays()` method converts named frequencies (daily=1, weekly=7, monthly=30, quarterly=91, annual=365, etc.) to day counts for issue generation.

### 3.7 ILLService

Interlibrary loan workflow in both directions.

| Method | Purpose |
|---|---|
| `createRequest(array $data)` | Creates borrow or lend request |
| `updateStatus(int $requestId, string $newStatus)` | Transitions status with automatic date stamping (sent->sent_date, received->received_date+due_date, returned->returned_date) |
| `find(int $id)`, `search(array $params)` | Lookup with patron join |
| `getPatronRequests(int $patronId)` | Active requests for a patron |
| `getOverdueItems()` | Borrowed items past due_date |

**Status flow (borrowing):** requested -> approved -> shipped -> received -> in_use -> returned
**Status flow (lending):** requested -> approved -> shipped -> returned

### 3.8 OpacService

Public-facing catalog search and patron self-service.

| Method | Purpose |
|---|---|
| `search(array $params)` | Six search types: keyword, title, author, subject, ISBN, call_number. Keyword searches across title, ISBN, ISSN, call number, publisher, series, creators, and subjects. |
| `getItemDetail(int $libraryItemId)` | Full record with creators, subjects, copy availability, hold count. Filters by publication status (160 = Published). |
| `getFacets()` | Aggregated counts by material_type and publication_year |
| `getPatronAccount(int $patronId)` | Account summary: patron info, checkouts, holds, fines, balance |
| `getNewArrivals(int $limit)` | Recently cataloged items (published only) |
| `getPopular(int $limit, int $days)` | Most checked-out items in last N days |

**Publication filter:** All public queries join `status` table filtering `type_id=158` (publication status) and `status_id=160` (published). Draft and unpublished items are excluded from OPAC results.

### 3.9 MarcService

MARC 21 import and export via MarcXML format.

**Import (`importMarcXml`):**
1. Parses MarcXML with namespace handling (`http://www.loc.gov/MARC21/slim`)
2. Extracts bibliographic data using the `MARC_MAP` constant (20+ field mappings)
3. Detects material type from MARC leader (position 6-7)
4. Extracts creators from tags 100/110 (primary) and 700/710 (contributors)
5. Extracts subjects from tags 600-655 with source detection from indicator 2
6. Deduplicates by ISBN (updates existing records)
7. Creates `object` -> `information_object` -> `information_object_i18n` -> `slug` -> `status` -> `library_item` chain for new records

**Export (`exportMarcXml`):**
- Generates compliant MarcXML with leader, 008 control field, and all mapped data fields
- Builds leader based on material type (am=monograph, as=serial, em=map, cm=score, tm=manuscript)
- Writes creators, subjects, and all bibliographic fields as MARC data fields with proper indicators

**MARC field mapping (excerpt):**

| MARC Tag | Subfield | library_item Column |
|---|---|---|
| 020$a | a | isbn |
| 050$a/$b | a/b | classification_number / cutter_number |
| 082$a | a | classification_number |
| 245$a/$b | a/b | title (from information_object_i18n) |
| 260$a/$b/$c | a/b/c | publication_place / publisher / publication_date |
| 300$a/$b/$c | a/b/c | pagination / physical_details / dimensions |
| 490$a/$v | a/v | series_title / series_number |
| 520$a | a | summary |

---

## 4. Modules

### 4.1 library (Cataloging)

Primary catalog management interface.

| Action | Purpose |
|---|---|
| `browseAction` | Paginated browse with facets |
| `indexAction` | View single library item by slug |
| `editAction` | Create/edit library item (also handles `add`) |
| `isbnLookupAction` | ISBN metadata fetch |
| `isbnProvidersAction` | Manage ISBN lookup providers |
| `isbnProviderEditAction` | Create/edit provider |
| `isbnProviderToggleAction` | Enable/disable provider |
| `isbnProviderDeleteAction` | Remove provider |
| `coverProxyAction` | Proxy book cover images by ISBN |
| `suggestSubjectsAction` | NER-based subject heading suggestions |

### 4.2 opac (Public Access Catalog)

Public-facing search and self-service.

| Action | Purpose |
|---|---|
| `indexAction` | OPAC search page with facets, new arrivals, popular items |
| `viewAction` | Full catalog record display with copy availability |
| `holdAction` | Place/cancel hold (authenticated patrons) |
| `accountAction` | Patron self-service: checkouts, holds, fines |

### 4.3 circulation

Staff circulation desk.

| Action | Purpose |
|---|---|
| `indexAction` | Dashboard with statistics |
| `checkoutAction` | Checkout by ID or barcode |
| `checkinAction` | Return by ID or barcode |
| `renewAction` | Extend due date |
| `overdueAction` | Overdue items report |
| `loanRulesAction` | View/manage loan rules |

### 4.4 patron

Patron management (staff).

| Action | Purpose |
|---|---|
| `indexAction` | Paginated patron browse/search |
| `viewAction` | Patron detail with activity |
| `editAction` | Create/edit patron |
| `suspendAction` | Suspend borrowing privileges |
| `reactivateAction` | Restore active status |

### 4.5 acquisition

Acquisitions and budgets (staff).

| Action | Purpose |
|---|---|
| `indexAction` | Order browse/search |
| `orderAction` | View order with lines |
| `orderEditAction` | Create/edit order |
| `addLineAction` | Add line item to order |
| `receiveAction` | Record receipt of items |
| `budgetsAction` | Budget management |

### 4.6 serial

Serial subscription management.

| Action | Purpose |
|---|---|
| `indexAction` | Subscription browse |
| `viewAction` | Subscription detail with issues |
| `editAction` | Create/edit subscription |
| `checkinAction` | Check in received issue |
| `claimAction` | Mark issue as claimed/missing |

### 4.7 ill (Interlibrary Loan)

ILL request management.

| Action | Purpose |
|---|---|
| `indexAction` | Request browse/search |
| `viewAction` | Request detail |
| `editAction` | Create new ILL request |
| `statusAction` | Update request status |

### 4.8 isbn

ISBN utility module.

| Action | Purpose |
|---|---|
| `lookupAction` | ISBN lookup interface |
| `testAction` | Provider test page |
| `apiTestAction` | API endpoint testing |
| `statsAction` | ISBN lookup statistics (admin) |

### 4.9 libraryReports

Library reporting dashboard.

---

## 5. Routes

All routes are registered in `ahgLibraryPluginConfiguration::addRoutes()` using `RouteLoader`. Routes are organized into 8 groups with a total of 46 routes.

### Library Routes

| Route Name | URL Pattern | Module/Action |
|---|---|---|
| `library_browse` | `/library` | library/browse |
| `library_add` | `/library/add` | library/edit |
| `library_isbn_lookup` | `/library/isbnLookup` | library/isbnLookup |
| `library_isbn_providers` | `/library/isbn-providers` | library/isbnProviders |
| `library_isbn_provider_edit` | `/library/isbn-provider/edit/:id` | library/isbnProviderEdit |
| `library_isbn_provider_toggle` | `/library/isbn-provider/toggle/:id` | library/isbnProviderToggle |
| `library_isbn_provider_delete` | `/library/isbn-provider/delete/:id` | library/isbnProviderDelete |
| `library_api_isbn` | `/api/library/isbn/:isbn` | library/apiIsbnLookup |
| `library_cover_proxy` | `/library/cover/:isbn` | library/coverProxy |
| `library_suggest_subjects` | `/library/suggestSubjects` | library/suggestSubjects |
| `library_edit` | `/library/:slug/edit` | library/edit |
| `library_view` | `/library/:slug` | library/index (catch-all, checked last) |

### ISBN Routes

| Route Name | URL Pattern | Module/Action |
|---|---|---|
| `isbn_lookup` | `/isbn/lookup` | isbn/lookup |
| `isbn_test` | `/isbn/test` | isbn/test |
| `isbn_api_test` | `/isbn/apiTest` | isbn/apiTest |
| `isbn_stats` | `/admin/isbn/stats` | isbn/stats |

### OPAC Routes

| Route Name | URL Pattern | Module/Action |
|---|---|---|
| `opac_index` | `/opac` | opac/index |
| `opac_view` | `/opac/view/:id` | opac/view |
| `opac_hold` | `/opac/hold` | opac/hold |
| `opac_account` | `/opac/account` | opac/account |

### Circulation Routes

| Route Name | URL Pattern | Module/Action |
|---|---|---|
| `circulation_index` | `/circulation` | circulation/index |
| `circulation_checkout` | `/circulation/checkout` | circulation/checkout |
| `circulation_checkin` | `/circulation/checkin` | circulation/checkin |
| `circulation_renew` | `/circulation/renew` | circulation/renew |
| `circulation_overdue` | `/circulation/overdue` | circulation/overdue |
| `circulation_loan_rules` | `/circulation/loan-rules` | circulation/loanRules |

### Patron Routes

| Route Name | URL Pattern | Module/Action |
|---|---|---|
| `patron_index` | `/patron` | patron/index |
| `patron_view` | `/patron/view/:id` | patron/view |
| `patron_edit` | `/patron/edit/:id` | patron/edit |
| `patron_suspend` | `/patron/suspend` | patron/suspend |
| `patron_reactivate` | `/patron/reactivate` | patron/reactivate |

### Acquisition Routes

| Route Name | URL Pattern | Module/Action |
|---|---|---|
| `acquisition_index` | `/acquisition` | acquisition/index |
| `acquisition_order_view` | `/acquisition/order/:order_id` | acquisition/order |
| `acquisition_order_edit` | `/acquisition/order/edit/:id` | acquisition/orderEdit |
| `acquisition_add_line` | `/acquisition/add-line` | acquisition/addLine |
| `acquisition_receive` | `/acquisition/receive` | acquisition/receive |
| `acquisition_budgets` | `/acquisition/budgets` | acquisition/budgets |

### Serial Routes

| Route Name | URL Pattern | Module/Action |
|---|---|---|
| `serial_index` | `/serial` | serial/index |
| `serial_view` | `/serial/view/:id` | serial/view |
| `serial_edit` | `/serial/edit/:id` | serial/edit |
| `serial_checkin` | `/serial/checkin` | serial/checkin |
| `serial_claim` | `/serial/claim` | serial/claim |

### ILL Routes

| Route Name | URL Pattern | Module/Action |
|---|---|---|
| `ill_index` | `/ill` | ill/index |
| `ill_view` | `/ill/view/:id` | ill/view |
| `ill_edit` | `/ill/edit` | ill/edit |
| `ill_status` | `/ill/status` | ill/status |

---

## 6. Configuration

### 6.1 ahgLibraryPluginConfiguration.class.php

```php
class ahgLibraryPluginConfiguration extends sfPluginConfiguration
{
    public static $summary = 'Library & Bibliographic Cataloging';
    public static $version = '2.0.0';
    public static $libraryLevelIds = [1700, 1701, 1702, 1703, 1704];
}
```

The `$libraryLevelIds` array holds term IDs for library-specific levels of description (Book, Monograph, Periodical, Journal, Manuscript). These are used by `ahgDisplayPlugin` for sector detection.

### 6.2 Module Security

The `circulation`, `acquisition`, `serial`, and `ill` modules have `security.yml` configurations requiring authentication. The `opac` module has no security requirements (public access), though the `account` and `hold` actions check for authenticated patrons at the action level.

### 6.3 Dropdown Taxonomies

The migration seeds 14 dropdown taxonomies into `ahg_dropdown`:

| Taxonomy | Values |
|---|---|
| `patron_type` | student, staff, faculty, public, researcher, institutional, child, honorary |
| `borrowing_status` | active, suspended, expired, blocked, inactive |
| `checkout_status` | active, returned, overdue, lost, claimed_returned, damaged |
| `hold_status` | pending, available, fulfilled, expired, cancelled |
| `copy_status` | available, checked_out, on_hold, in_transit, in_processing, in_repair, missing, lost, withdrawn, reference, restricted |
| `fine_type` | overdue, lost_item, damaged, processing, replacement_card, ill_fee |
| `fine_status` | outstanding, paid, partial, waived, referred |
| `library_order_status` | draft, submitted, approved, ordered, partial, received, cancelled |
| `library_order_type` | purchase, standing_order, gift, exchange, deposit, approval |
| `serial_issue_status` | expected, received, missing, claimed, damaged, bound |
| `subscription_status` | active, pending, cancelled, expired, suspended |
| `ill_status` | requested, approved, shipped, received, in_use, returned, overdue, cancelled, denied |
| `ill_direction` | borrowing, lending |
| `payment_method` | cash, card, eft, online, deduction |
| `library_acquisition_method` | purchase, donation, gift, bequest, exchange, deposit, transfer, unknown |
| `budget_category` | monographs, serials, electronic, special_collections, binding, ill, media, general |

---

## 7. Integration Points

### 7.1 AtoM information_object

Every library item links to an AtoM `information_object` via `library_item.information_object_id`. The MARC import creates the full AtoM object chain:

```
object (class_name='QubitInformationObject')
  -> information_object (parent_id=1, repository_id)
  -> information_object_i18n (title, culture='en')
  -> slug (object_id, slug)
  -> status (type_id=158, status_id=159=Draft or 160=Published)
```

This integration means library items appear in AtoM's standard browse/search alongside archival descriptions. The `ahgDisplayPlugin` auto-detects library-sector items based on level of description terms.

### 7.2 Publication Status

The OPAC enforces publication status filtering using AtoM's `status` table:
- `type_id = 158` = Publication status type
- `status_id = 159` = Draft (excluded from OPAC)
- `status_id = 160` = Published (visible in OPAC)

### 7.3 Heritage Accounting (GRAP 103 / IPSAS 45)

The `library_item` table includes 18 heritage accounting columns for South African public sector compliance:

- **Acquisition tracking:** method, date, cost, currency
- **Valuation:** replacement_value, insurance_value/policy/expiry, valuation_date/method/notes
- **Asset classification:** heritage_asset_id (FK to heritage_asset table), asset_class_code, recognition_status
- **Provenance:** donor_name, donor_restrictions
- **Condition:** condition_grade, conservation_priority

The `FineService::createLostItemFine()` method uses `replacement_value` for charging lost item fees, falling back to `acquisition_cost`.

### 7.4 Subject Authority with LCSH

The `library_subject_authority` table provides a local cache of controlled subject headings with:
- LCSH identifiers and URIs
- Suggested Dewey and LCC classification numbers
- Hierarchical relationships (broader/narrower/related terms as JSON)
- Usage tracking for autocomplete ranking

The `library_entity_subject_map` table bridges NER-extracted entities to subject authorities, enabling AI-assisted subject heading assignment via `SubjectSuggestionService`.

---

## 8. CLI Commands

### library:process-covers

```bash
php symfony library:process-covers [--limit=N] [--force]
```

Implemented in `lib/task/libraryCoverProcessTask.class.php` and `lib/Commands/ProcessCoversCommand.php`.

Processes library items with ISBN but no `cover_url`:
1. Queries `library_item` for records with ISBN and NULL cover_url
2. Fetches cover images from configured ISBN providers (Open Library, Google Books, etc.)
3. Updates `cover_url` and `cover_url_original` columns
4. Respects rate limits on external APIs

**Options:**
- `--limit=N` : Process at most N items (default: all)
- `--force` : Re-fetch covers even if cover_url is already set

---

## 9. Key Patterns

### 9.1 Singleton Services with require_once

Symfony 1.x does not autoload namespaced plugin classes. Services are loaded explicitly:

```php
$pluginPath = sfConfig::get('sf_plugins_dir') . '/ahgLibraryPlugin/lib/Service/';
require_once $pluginPath . 'PatronService.php';
require_once $pluginPath . 'CirculationService.php';

$circService = CirculationService::getInstance();
```

### 9.2 Laravel Query Builder

All data access uses Laravel's query builder via the framework-initialized Capsule:

```php
use Illuminate\Database\Capsule\Manager as DB;

$item = DB::table('library_item')
    ->where('isbn', $isbn)
    ->first();

DB::table('library_checkout')->insertGetId([...]);
```

### 9.3 Monolog Logging

All services log to a shared rotating log file:

```php
$this->logger = new Logger('library.circulation');
$logPath = \sfConfig::get('sf_log_dir', '/tmp') . '/library.log';
$this->logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::DEBUG));
```

Log channels: `library`, `library.circulation`, `library.patron`, `library.hold`, `library.fine`, `library.acquisition`, `library.serial`, `library.ill`, `library.opac`, `library.marc`.

### 9.4 AhgController Base Class

Module actions extend `AhgController` (from ahgCorePlugin) for common functionality:
- Authentication and ACL checks
- JSON response helpers
- Error handling
- Template variable assignment

### 9.5 Barcode Generation

Patron and copy barcodes are auto-generated with uniqueness checks:
- **Patron:** `P` + 6 random digits (e.g., `P384729`)
- **Copy:** `C` + 7 random digits (e.g., `C2847391`)
- **Order:** `PO-YYYY-NNNN` sequential (e.g., `PO-2026-0042`)

### 9.6 Transaction Safety

Multi-table operations (checkout, checkin) use explicit transactions:

```php
DB::connection()->beginTransaction();
try {
    // Multiple table updates
    DB::connection()->commit();
} catch (\Exception $e) {
    DB::connection()->rollBack();
    return ['success' => false, 'error' => $e->getMessage()];
}
```

### 9.7 Cron Jobs

Two operations should be scheduled as daily cron jobs:
- `FineService::generateDailyOverdueFines()` -- recalculates accumulating overdue fines
- `HoldService::expireOverdueHolds()` -- expires 'ready' holds past their pickup window and promotes the next patron in queue

---

## Appendix: Entity Relationship Summary

```
information_object 1----1 library_item 1----* library_item_creator
                                        1----* library_item_subject ----* library_subject_authority
                                        1----* library_copy 1----* library_checkout ----* library_fine
                                        1----* library_hold
                                        1----* library_subscription 1----* library_serial_issue

library_patron 1----* library_checkout
               1----* library_hold
               1----* library_fine
               1----* library_ill_request

library_order 1----* library_order_line
library_budget (standalone, linked by budget_code)
library_entity_subject_map *----1 library_subject_authority
library_settings (standalone key/value)
library_loan_rule (standalone policy table)
```
