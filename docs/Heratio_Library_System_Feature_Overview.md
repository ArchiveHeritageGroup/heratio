# Heratio --- Full Library System (ILS)

**Component:** ahgLibraryPlugin v2.0.0
**Category:** GLAM Sector --- Library
**Publisher:** The Archive and Heritage Group (Pty) Ltd
**Date:** March 2026

---

## Summary

Heratio's Full Library System delivers a complete Integrated Library System (ILS) within the AtoM archival platform. Building on a 71-field MARC-inspired cataloging foundation, the plugin adds circulation management, patron services, an Online Public Access Catalog (OPAC), acquisitions, serial control, interlibrary loan, and full MARC 21 import/export.

For institutions that manage both archival collections and lending libraries, the plugin eliminates the need for a separate ILS by embedding library operations directly into the AtoM environment. Heritage accounting fields aligned with GRAP 103 and IPSAS 45 enable asset valuation and financial tracking on every library item.

---

## Key Features

### 1. Cataloging and Bibliographic Records

The cataloging module provides a comprehensive 71-field bibliographic record based on MARC conventions, covering all standard descriptive, physical, and administrative metadata.

- **ISBN / ISSN validation** with automatic check-digit verification
- **Call number management** supporting Dewey Decimal, Library of Congress Classification (LCC), and Universal Decimal Classification (UDC)
- **Subject headings** with Library of Congress Subject Headings (LCSH) authority linking
- **Creator and contributor tracking** using MARC relator codes (author, editor, illustrator, translator, and more)
- **WorldCat integration** for bibliographic lookup and record enrichment
- **Book cover retrieval** from Open Library and Google Books APIs
- **Material type classification** (monograph, serial, audiovisual, electronic resource, map, manuscript, mixed materials, and others)

### 2. Circulation

The circulation module handles the full checkout-return-renew lifecycle with support for barcode scanning at a dedicated checkout station.

- **Checkout, return, and renewal** via barcode scan or manual entry
- **Configurable loan rules** per material type and patron type (e.g., 21-day loan for books, 7-day for DVDs, extended loans for staff)
- **Automatic overdue detection** with configurable grace periods
- **Copy management** --- add multiple physical copies per title, each with its own barcode, condition grade, and shelving location
- **Hold queue processing** --- when an item is returned, the system automatically promotes the next patron in the hold queue and generates a pickup notification
- **Renewal limits** configurable per loan rule

### 3. Patron Management

Library patrons are registered and managed with full borrowing-privilege controls, linked to AtoM user accounts where applicable.

- **Patron registration** with unique barcode assignment
- **Configurable patron types** managed via the Dropdown Manager (e.g., student, faculty, community member, staff)
- **Borrowing limits** per patron type (maximum concurrent checkouts, maximum holds)
- **Account suspension and reactivation** with reason tracking
- **Activity dashboard** showing current checkouts, hold requests, outstanding fines, and borrowing history
- **Integration with AtoM user accounts** for seamless authentication

### 4. Holds and Reservations

Patrons can place holds on items that are currently checked out or otherwise unavailable. The hold queue is managed automatically.

- **Hold placement** from the OPAC or staff interface
- **Automatic queue management** --- first-come, first-served ordering
- **Auto-promotion** when an item is returned, the next hold in queue is activated
- **Configurable pickup window** (default 7 days) after which uncollected holds expire
- **Expiry processing** with automatic queue advancement
- **Cancel and fulfill** operations for staff

### 5. Fines and Payments

The fines module calculates overdue charges automatically and tracks all financial transactions against patron accounts.

- **Automatic overdue fine calculation** based on loan rules (daily rate per material type)
- **Lost item replacement charges** linked to item value
- **Payment recording** supporting cash, card, and electronic transfer
- **Fine waivers** with mandatory reason tracking for audit compliance
- **Configurable fine thresholds** --- block borrowing when balance exceeds a set limit
- **Patron balance display** on checkout and in the patron dashboard

### 6. Online Public Access Catalog (OPAC)

The OPAC provides a public-facing search interface for library users, with self-service capabilities for registered patrons.

- **Multi-field search** across keyword, title, author, subject, ISBN, and call number
- **Faceted filtering** by material type, publication year, language, and availability status
- **Real-time availability display** showing copy count and current checkout status
- **New arrivals** and **popular items** sections
- **Patron self-service (My Account)** --- view current loans, renewal, hold placement, fine balance, and borrowing history
- **Responsive design** for desktop and mobile access

### 7. Acquisitions

The acquisitions module manages the purchasing workflow from order placement through receiving and budget tracking.

- **Purchase orders** with multiple order lines per vendor
- **Vendor management** with contact details and performance tracking
- **Receiving workflow** supporting partial and full receipt
- **Budget management** with fiscal year tracking and allocation per fund
- **Expenditure recording** linked to purchase orders
- **Automatic order number generation** with configurable prefix

### 8. Serial Control

The serial control module manages periodical subscriptions, issue tracking, and gap analysis.

- **Subscription management** with start/end dates, frequency, and vendor
- **Issue check-in** with barcode and expected-date matching
- **Expected issue generation** based on subscription frequency
- **Gap analysis** identifying missing issues across subscriptions
- **Claim tracking** for unreceived issues
- **Renewal alerts** with configurable lead time
- **Frequency support** from daily through biennial (daily, weekly, biweekly, monthly, bimonthly, quarterly, semiannual, annual, biennial)

### 9. Interlibrary Loan (ILL)

The ILL module facilitates both borrowing and lending between institutions.

- **Borrowing requests** --- request items from partner libraries on behalf of patrons
- **Lending requests** --- process incoming requests from other institutions
- **Status workflow:** Submitted, Approved, Sent, Received, Returned, Cancelled
- **Overdue tracking** for borrowed items with configurable due dates
- **Patron-linked requests** with notification on status changes
- **Request history** for reporting and analysis

### 10. MARC 21 Import/Export

Full MARC 21 support enables interoperability with other library systems and union catalogs.

- **MarcXML import** with automatic field mapping to the 71-field schema
- **Leader analysis** for material type detection
- **Subject source detection** recognizing LCSH, MeSH, AAT, and FAST vocabularies
- **MarcXML export** with complete MARC field generation from cataloging data
- **ISBN deduplication on import** to prevent duplicate records
- **Batch import** for large-scale migrations from legacy systems

### 11. Heritage Accounting Integration

For institutions required to report on heritage assets, the plugin includes 18 heritage accounting columns on every library item, aligned with South African GRAP 103 and the international IPSAS 45 standard.

- **Acquisition method and cost** recording
- **Insurance value** and **replacement value** tracking
- **Recognition status** (recognized, not recognized, pending)
- **Condition grade** assessment
- **Conservation priority** classification
- **Valuation date** and **revaluation tracking**
- **Disposal and deaccession** recording
- **Full integration** with ahgHeritageAccountingPlugin for consolidated heritage asset reporting

### 12. Dropdown-Driven Configuration

All status values, types, and categories throughout the library system are managed via the Dropdown Manager in the administration interface. No code changes are required to customize vocabularies.

**14 library-specific dropdown taxonomies:**

| Taxonomy | Examples |
|----------|----------|
| Material Type | Monograph, Serial, Audiovisual, Electronic Resource |
| Patron Type | Student, Faculty, Staff, Community Member |
| Copy Condition | New, Good, Fair, Poor, Damaged |
| Copy Status | Available, Checked Out, On Hold, In Processing |
| Checkout Status | Active, Returned, Overdue, Lost |
| Fine Type | Overdue, Lost Item, Damage, Processing |
| Payment Method | Cash, Card, Transfer, Waiver |
| Order Status | Draft, Submitted, Approved, Received, Cancelled |
| ILL Status | Submitted, Approved, Sent, Received, Returned |
| Subscription Status | Active, Suspended, Cancelled, Expired |
| Classification Scheme | Dewey, LCC, UDC |
| Serial Frequency | Daily, Weekly, Monthly, Quarterly, Annual |
| Acquisition Method | Purchase, Donation, Exchange, Legal Deposit |
| Conservation Priority | Urgent, High, Medium, Low |

---

## Architecture

```
+-----------------------------------------------------------+
|              ahgLibraryPlugin v2.0.0                      |
|                                                           |
|  Modules: library, circulation, patron, opac,            |
|           acquisition, serial, ill, isbn, reports         |
|                                                           |
|  Services: 14 (Circulation, Patron, Hold, Fine,          |
|           Acquisition, Serial, ILL, OPAC, MARC,          |
|           Library, BookCover, ISBN, Subject, WorldCat)    |
|                                                           |
|  Database: 18 tables, 46 routes                          |
|  Heritage Accounting: GRAP 103 / IPSAS 45               |
+-----------------------------------------------------------+
```

---

## Database Tables

| Table | Purpose |
|-------|---------|
| library_item | Core bibliographic record (71 fields + 18 heritage accounting) |
| library_item_creator | Creator/contributor with MARC relator codes |
| library_item_subject | Subject headings with LCSH authority linking |
| library_copy | Individual physical copies with barcode, condition, location |
| library_patron | Library patrons with borrowing privileges |
| library_checkout | Circulation transactions |
| library_hold | Hold/reservation queue |
| library_fine | Fines and payment tracking |
| library_loan_rule | Configurable loan policies per material/patron type |
| library_subscription | Serial subscriptions |
| library_serial_issue | Individual serial issues |
| library_order | Purchase orders |
| library_order_line | Order line items |
| library_budget | Budget allocation and tracking |
| library_ill_request | Interlibrary loan requests |
| library_subject_authority | Controlled subject headings |
| library_entity_subject_map | NER entity to subject mapping |
| library_settings | Library-specific configuration |

---

## Key URL Routes

| Route | Purpose |
|-------|---------|
| `/library` | Browse library catalog |
| `/opac` | Public catalog search (OPAC) |
| `/circulation` | Checkout station |
| `/patron` | Patron management |
| `/acquisition` | Purchase orders and receiving |
| `/serial` | Subscription and issue management |
| `/ill` | Interlibrary loan requests |

---

## Technical Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.3 or higher |
| MySQL | 8.0 or higher |
| AtoM | 2.10 or higher |
| atom-framework | v2.8.2 or higher |
| ahgCorePlugin | Required (provides Dropdown Manager) |
| ahgHeritageAccountingPlugin | Optional (for full heritage asset reporting) |

---

## Standards Compliance

| Standard | Coverage |
|----------|----------|
| MARC 21 | Full MarcXML import/export with leader analysis and relator codes |
| LCSH | Subject heading authority linking |
| Dewey Decimal | Call number classification |
| Library of Congress Classification | Call number classification |
| Universal Decimal Classification | Call number classification |
| GRAP 103 | Heritage asset accounting (South Africa) |
| IPSAS 45 | Heritage asset accounting (International) |

---

## About The Archive and Heritage Group

The Archive and Heritage Group (Pty) Ltd develops Heratio, a comprehensive modernization of the Access to Memory (AtoM) archival platform. Heratio extends AtoM with 80 plugins covering the full GLAM spectrum --- galleries, libraries, archives, museums, and digital asset management --- serving institutions internationally.

For more information, contact: **johan@theahg.co.za**
