# Heratio Library System User Manual

**Component:** ahgLibraryPlugin v2.0.0
**Platform:** Heratio (Access to Memory)
**Publisher:** The Archive and Heritage Group (Pty) Ltd
**Date:** March 2026

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Getting Started](#2-getting-started)
3. [Catalog Management](#3-catalog-management)
4. [Copy Management](#4-copy-management)
5. [OPAC (Online Public Access Catalog)](#5-opac-online-public-access-catalog)
6. [Circulation](#6-circulation)
7. [Patron Management](#7-patron-management)
8. [Holds](#8-holds)
9. [Fines](#9-fines)
10. [Acquisitions](#10-acquisitions)
11. [Serials](#11-serials)
12. [Interlibrary Loan (ILL)](#12-interlibrary-loan-ill)
13. [Heritage Accounting](#13-heritage-accounting)
14. [MARC Import/Export](#14-marc-importexport)
15. [Reports](#15-reports)

---

## 1. Introduction

The Heratio Library System is a full Integrated Library System (ILS) embedded within the AtoM archival platform. It is delivered through the ahgLibraryPlugin and is designed for institutions that manage lending libraries alongside archival, museum, gallery, or digital asset collections.

The system provides cataloging with MARC-inspired metadata fields, circulation management, patron services, an Online Public Access Catalog, acquisitions, serial control, interlibrary loan, and heritage accounting -- all within a single platform. Institutions no longer need a separate ILS when their library operations are modest to moderate in scale.

**Who is this for?**

- Librarians cataloging and circulating materials
- Archivists managing combined archive-library collections
- Library assistants handling checkout and return operations
- Administrators managing budgets, acquisitions, and patron accounts
- Patrons searching and placing holds via the OPAC

---

## 2. Getting Started

### 2.1 Accessing the Library System

The Library System is accessed through the main AtoM navigation. Once the ahgLibraryPlugin is enabled, the following navigation entries become available:

| Menu Item | URL | Access Level |
|-----------|-----|-------------|
| Library Catalog | `/library` | Staff (authenticated) |
| OPAC | `/opac` | Public |
| Circulation | `/circulation` | Staff |
| Patrons | `/patron` | Staff |
| Acquisitions | `/acquisition` | Staff |
| Serials | `/serial` | Staff |
| Interlibrary Loan | `/ill` | Staff |
| Library Reports | `/libraryReports` | Staff |

### 2.2 Navigation Overview

The Library module sidebar provides quick links between all subsystems. From any library page, you can navigate to Catalog, OPAC, Circulation, Patrons, Acquisitions, Serials, ILL, and Reports via the sidebar menu.

### 2.3 Permissions

All staff-facing modules (Catalog, Circulation, Patrons, Acquisitions, Serials, ILL, Reports) require authentication. The OPAC is available to the public for searching and viewing item details. Placing holds and viewing account information in the OPAC requires patron authentication.

---

## 3. Catalog Management

### 3.1 Browsing the Catalog

Navigate to **Library** (`/library`) to see the library catalog browse view. The browse page displays all library items with their title, material type, call number, ISBN, publisher, and circulation status. You can filter by material type and search by keyword.

### 3.2 Creating a Library Item

1. Navigate to `/library/add` or click the **Add** button on the catalog browse page.
2. Fill in the bibliographic fields across the following sections:

**Identification**
- **Material type** -- Select from: Monograph/Book, Serial/Journal, Volume, Issue, Chapter, Article, Manuscript, Map, Pamphlet, Musical Score, Electronic Resource
- **Call number** -- The shelf label (e.g., `823.914 SMI`)
- **Classification scheme** -- Dewey Decimal (DDC), Library of Congress (LCC), Universal Decimal (UDC), Bliss, Colon, or Custom
- **Classification number** and **Cutter number**
- **ISBN** / **ISSN** / **LCCN** / **OCLC number** / **DOI** / **Barcode**

**Publication**
- Publisher, publication place, publication date, copyright date, edition, edition statement, printing

**Physical Description**
- Pagination, dimensions, physical details, accompanying material

**Series**
- Series title, series number, series ISSN, subseries title

**Notes**
- General note, bibliography note, contents note, summary, target audience, system requirements, binding note

**Serial-Specific Fields** (shown when material type is Serial, Issue, or Article)
- Frequency, former frequency, numbering peculiarities, publication start/end dates, publication status

**Cataloging Administration**
- Cataloging source, cataloging rules (AACR2, RDA, or ISBD), encoding level

3. Click **Save** to create the record.

### 3.3 ISBN Lookup

The ISBN Lookup feature automatically populates bibliographic fields from external sources.

1. Enter an ISBN in the ISBN field on the item edit form.
2. Click the **Lookup** button.
3. The system queries configured providers (Open Library, Google Books, WorldCat) and returns available metadata including title, authors, publisher, publication date, pagination, subjects, and cover image.
4. Review the returned data and click **Apply** to populate the form fields.

To manage ISBN lookup providers, navigate to `/library/isbn-providers`. You can add, edit, enable, or disable providers from this page.

### 3.4 Cover Images

Cover images are retrieved automatically during ISBN lookup from Open Library and Google Books APIs. The system uses a cover proxy (`/library/cover/:isbn`) to serve cached cover images. Background processing of cover images is available via the CLI command:

```bash
php symfony library:process-covers
```

### 3.5 Subject Headings

Subject headings are added on the item edit form under the Subjects panel. Each subject entry includes:

| Field | Description |
|-------|-------------|
| Heading | The subject heading text (e.g., "South African history") |
| Heading type | Topical, Personal, Corporate, Geographic, Genre, or Meeting |
| Source | LCSH, MeSH, AAT, FAST, or Local |
| URI | Link to the authority record (optional) |
| LCSH ID | Library of Congress authority record identifier |
| Dewey number | Suggested Dewey classification |
| LCC number | Suggested LCC classification |
| Subdivisions | Topical, geographic, chronological, or form subdivisions (JSON) |

The system maintains a **Subject Authority** table (`library_subject_authority`) that tracks controlled subject headings with usage counts. When you enter a subject, the system suggests existing authority headings via autocomplete. Navigate to `/library/suggestSubjects` for AI-assisted subject suggestions based on the item title and content.

### 3.6 Creators

Each library item can have multiple creators with specific roles. Supported creator roles use MARC relator codes:

| Role | MARC Code | Description |
|------|-----------|-------------|
| Author | aut | Primary author |
| Editor | edt | Editor |
| Translator | trl | Translator |
| Illustrator | ill | Illustrator |
| Compiler | com | Compiler |
| Contributor | ctb | Contributor |
| Author of introduction | aui | Author of introduction |
| Author of afterword | aft | Author of afterword |
| Photographer | pht | Photographer |
| Composer | cmp | Composer |

One creator can be marked as the **primary creator** for display and citation purposes.

### 3.7 Editing and Deleting Items

- To edit an item, navigate to `/library/:slug/edit` or click the **Edit** button on the item view page.
- Deletion removes the library metadata record. The underlying AtoM information object is not affected.

---

## 4. Copy Management

Individual physical copies of a library item are tracked separately, each with its own barcode, location, and status.

### 4.1 Adding Copies

1. Open a library item for editing.
2. In the **Copies** panel, click **Add Copy**.
3. Fill in the following fields:

| Field | Description |
|-------|-------------|
| Barcode | Unique barcode (auto-generated if left blank, format: `C` + 7 digits) |
| Copy number | Sequential copy number (auto-incremented) |
| Accession number | Library accession reference |
| Call number suffix | e.g., `c.2`, `v.3` |
| Shelf location | Physical location in the library |
| Branch | Library branch or location name |
| Status | Available, Checked Out, On Hold, In Transit, In Processing, In Repair, Missing, Lost, Withdrawn, Reference Only, Restricted Access |
| Condition grade | Physical condition assessment |
| Condition notes | Free text notes on condition |
| Acquisition method | Purchase, Donation, Gift, Bequest, Exchange, Legal Deposit, Transfer, Unknown |
| Acquisition date | Date the copy was acquired |
| Acquisition cost | Cost of acquisition |
| Acquisition source | Vendor or donor name |

4. Click **Save**.

### 4.2 Withdrawing a Copy

To withdraw a copy from the collection:

1. Edit the copy record.
2. Set the **Status** to "Withdrawn".
3. Enter the **Withdrawal date** and **Withdrawal reason**.
4. Save. The copy will no longer appear as available for circulation.

Withdrawn and lost copies are excluded from the total and available copy counts on the parent item.

### 4.3 Copy Statuses

| Status | Description |
|--------|-------------|
| Available | On the shelf and available for checkout |
| Checked Out | Currently on loan to a patron |
| On Hold | Reserved for a patron to pick up |
| In Transit | Being moved between branches |
| In Processing | Being cataloged or prepared |
| In Repair | Undergoing conservation or repair |
| Missing | Cannot be located on the shelf |
| Lost | Confirmed lost |
| Withdrawn | Permanently removed from the collection |
| Reference Only | Available for in-library use only, not for loan |
| Restricted Access | Requires special permission to access |

---

## 5. OPAC (Online Public Access Catalog)

The OPAC is the public-facing search interface at `/opac`. It does not require authentication for searching and viewing.

### 5.1 Searching the Catalog

The OPAC search page provides the following search options:

- **Keyword** -- Searches across title, author, subject, ISBN, and call number simultaneously
- **Title** -- Searches within titles only
- **Author** -- Searches within creator/author names only
- **Subject** -- Searches within subject headings only
- **ISBN** -- Exact ISBN match
- **Call Number** -- Searches by call number prefix

Results are filtered to show only published items. Each result displays the title, author, material type, call number, and real-time availability (number of copies available versus total copies).

### 5.2 Faceted Search

Search results can be narrowed using facets for:

- Material type (Monograph, Serial, Manuscript, etc.)
- Publication date range
- Publisher
- Subject heading
- Classification scheme

### 5.3 Item Detail View

Click any result to view the full item detail page (`/opac/view/:id`), which shows:

- Complete bibliographic information
- Cover image (if available)
- All creators with their roles
- All subject headings with links to the authority record
- Copy availability table (branch, call number, status per copy)
- Automated citation generation in APA, MLA, and Chicago styles

### 5.4 Patron Account

Authenticated patrons can view their account at `/opac/account`, which displays:

- Current checkouts with due dates
- Hold requests and their queue position
- Outstanding fines and payment history
- Checkout history

### 5.5 Placing Holds via the OPAC

From the item detail page, authenticated patrons can place a hold by clicking the **Place Hold** button (`/opac/hold`). The system validates that the patron account is active, has not exceeded the maximum hold limit, and does not already have an active hold on the same item.

---

## 6. Circulation

The Circulation module (`/circulation`) is the staff workspace for checkout, return, and renewal operations.

### 6.1 Checkout

**By barcode (recommended for checkout stations):**

1. Navigate to `/circulation`.
2. Scan or enter the **patron barcode** (card number).
3. Scan or enter the **copy barcode**.
4. The system validates:
   - Patron account is active and not suspended or blocked
   - Patron has not exceeded the maximum checkout limit
   - Patron's membership has not expired
   - Outstanding fines do not exceed the block threshold
   - The copy is available and the material type is loanable
5. Upon success, the system displays the **due date** calculated from the applicable loan rule.

**By ID (manual):**

1. Select the patron from the patron list.
2. Select the copy from the item's copy list.
3. Confirm the checkout.

The due date is calculated automatically from the loan rule for the item's material type and the patron's type. A custom due date may be specified to override the default.

### 6.2 Check-in (Return)

1. Navigate to `/circulation`.
2. Scan or enter the **copy barcode**.
3. The system locates the active checkout record.
4. If the item is overdue, an overdue fine is automatically calculated and applied to the patron's account (see Section 9).
5. The copy status is set back to "Available".
6. If there are pending holds on the title, the system automatically promotes the next patron in the hold queue and marks the hold as "Ready for Pickup" with a 7-day expiry.

### 6.3 Renewals

1. Locate the active checkout (from the Circulation page or the patron's account view).
2. Click **Renew**.
3. The system checks:
   - The checkout has not exceeded the maximum renewal count (per loan rule)
   - There are no pending holds on the title (holds block renewals)
4. On success, a new due date is calculated from the current date plus the loan period.

### 6.4 Overdue Management

Navigate to `/circulation/overdue` to view all currently overdue checkouts. The overdue list displays:

- Patron name and contact information
- Item title, call number, ISBN
- Copy barcode
- Due date and number of days overdue

### 6.5 Loan Rules

Loan rules are configured per material type and patron type at `/circulation/loan-rules`. Each rule specifies:

| Field | Description |
|-------|-------------|
| Material type | The material type this rule applies to |
| Patron type | The patron type (use `*` for all patron types) |
| Loan period (days) | Number of days for the initial loan |
| Renewal period (days) | Number of days added per renewal |
| Maximum renewals | Maximum number of times a checkout can be renewed |
| Fine per day | Daily overdue fine amount |
| Fine cap | Maximum total fine for this material/patron combination |
| Grace period (days) | Number of days after due date before fines begin |
| Loanable | Whether this material type can be checked out at all |

The system resolves loan rules in order of specificity: exact material type + patron type match first, then material type with default patron type, then global default.

**Default loan rules (seeded on install):**

| Material Type | Loan Period | Max Renewals | Fine/Day | Fine Cap | Loanable |
|---------------|-------------|-------------|----------|----------|----------|
| Monograph | 21 days | 2 | 1.00 | 50.00 | Yes |
| Serial | 7 days | 1 | 2.00 | 50.00 | Yes |
| Volume | 21 days | 2 | 1.00 | 50.00 | Yes |
| Issue | 7 days | 0 | 2.00 | 30.00 | Yes |
| Article | 7 days | 1 | 1.00 | 30.00 | Yes |
| Manuscript | 1 day | 0 | 10.00 | 100.00 | No |
| Map | 7 days | 1 | 2.00 | 50.00 | Yes |
| Pamphlet | 14 days | 2 | 0.50 | 20.00 | Yes |
| Score | 14 days | 2 | 1.00 | 50.00 | Yes |
| Electronic | 0 days | 0 | 0.00 | -- | No |

---

## 7. Patron Management

### 7.1 Browsing Patrons

Navigate to `/patron` to see the patron list. The list displays patron name, card number, patron type, borrowing status, and outstanding fine balance. Use the search and filter options to locate specific patrons.

### 7.2 Registering a Patron

1. Navigate to `/patron/edit` or click **Add Patron**.
2. Fill in the required fields:

| Field | Required | Description |
|-------|----------|-------------|
| First name | Yes | Patron's given name |
| Last name | Yes | Patron's family name |
| Card number | Auto | Unique barcode/card number (auto-generated if blank) |
| Patron type | Yes | See patron types below |
| Email | No | Contact email |
| Phone | No | Contact phone number |
| Address | No | Physical address |
| Institution | No | Affiliated institution |
| Department | No | Department within the institution |
| ID number | No | National ID or student number |
| Date of birth | No | Date of birth |
| Membership start | Yes | Date membership begins |
| Membership expiry | No | Date membership expires (if applicable) |
| Max checkouts | Yes | Maximum concurrent checkouts (default: 5) |
| Max renewals | Yes | Maximum renewals per checkout (default: 2) |
| Max holds | Yes | Maximum concurrent holds (default: 3) |

3. Click **Save**.

### 7.3 Patron Types

Patron types are managed via the Dropdown Manager (ahg_dropdown taxonomy: `patron_type`). Default types:

| Code | Label | Description |
|------|-------|-------------|
| public | Public | General public community member |
| student | Student | Enrolled student |
| faculty | Faculty | Academic faculty |
| staff | Staff | Institutional staff |
| researcher | Researcher | Registered researcher |
| institutional | Institutional | Another institution or organization |
| child | Child (Under 18) | Minor patron |
| honorary | Honorary Member | Honorary membership |

Additional patron types can be added through **Admin > Dropdown Manager** without any code changes.

### 7.4 Viewing a Patron Account

Navigate to `/patron/view/:id` to see a patron's full account, including:

- Personal information and contact details
- Current checkouts with due dates
- Active holds and queue positions
- Fine history and outstanding balance
- Checkout history and statistics

### 7.5 Suspending a Patron

1. Navigate to the patron's account view.
2. Click **Suspend**.
3. Enter the suspension reason and optionally a suspension end date.
4. Confirm. The patron's borrowing status changes to "Suspended" and all checkout and hold operations are blocked.

### 7.6 Reactivating a Patron

1. Navigate to the suspended patron's account view.
2. Click **Reactivate**.
3. The borrowing status is restored to "Active".

### 7.7 Borrowing Statuses

| Status | Description |
|--------|-------------|
| Active | Normal borrowing privileges |
| Suspended | Manually suspended by staff |
| Expired | Membership has passed its expiry date |
| Blocked (Fines) | Automatically blocked due to excessive fines |
| Inactive | Account is dormant |

---

## 8. Holds

### 8.1 Placing a Hold

Staff can place holds from the Circulation module. Patrons can place holds through the OPAC (see Section 5.5).

1. Select the patron and the library item.
2. Optionally specify a pickup branch and notes.
3. The system validates:
   - Patron is active
   - Patron has not exceeded the maximum hold limit
   - Patron does not already have an active hold on the same item
   - The item exists in the catalog
4. The hold is created with status "Pending" and assigned a queue position.

Holds are placed on **items** (titles), not on individual copies. The system assigns a copy at fulfillment time.

### 8.2 Hold Queue Management

When a copy is returned, the system automatically:

1. Checks for pending holds on that title.
2. Promotes the first patron in the queue (ordered by hold date) to "Ready" status.
3. Sets a 7-day pickup expiry on the hold.
4. The copy status is set to "On Hold".

If the patron does not pick up the item within the expiry window, the hold expires and the next patron in the queue is promoted.

### 8.3 Hold Statuses

| Status | Description |
|--------|-------------|
| Pending | In the queue, waiting for a copy to become available |
| Available for Pickup | A copy is being held for the patron |
| Fulfilled | The patron has checked out the item |
| Expired | The hold expired before pickup |
| Cancelled | The hold was cancelled by the patron or staff |

### 8.4 Cancelling a Hold

From the patron's account view or from the hold management interface, click **Cancel Hold**. Enter an optional cancellation reason. The queue positions for remaining holds on the same item are recalculated.

---

## 9. Fines

### 9.1 How Fines Are Generated

Overdue fines are created **automatically** when an overdue item is returned. The fine amount is calculated as:

```
Fine = (Days Overdue - Grace Period) x Fine Per Day
```

The fine is capped at the maximum fine amount defined in the applicable loan rule. If a grace period is configured, no fine is charged for overdue days within the grace period.

### 9.2 Fine Types

| Type | Description |
|------|-------------|
| Overdue Fine | Daily charge for late returns |
| Lost Item Replacement | Charged when a copy is marked as lost (replacement value) |
| Damage Fee | Charged for damage to borrowed materials |
| Processing Fee | Administrative processing charge |
| Card Replacement | Fee for replacing a lost library card |
| ILL Service Fee | Fee for interlibrary loan services |

### 9.3 Viewing Patron Fines

Navigate to the patron's account view to see all fines, both outstanding and paid. The fine list shows:

- Fine type and description
- Amount and amount paid
- Fine date
- Associated checkout (if applicable)
- Payment status

### 9.4 Recording a Payment

1. Open the fine record from the patron's account.
2. Enter the payment amount and select the payment method (Cash, Card, EFT/Bank Transfer, Online, Salary Deduction).
3. Enter an optional payment reference number.
4. Save. If the payment covers the full amount, the fine status changes to "Paid". Partial payments are recorded with status "Partially Paid".

### 9.5 Waiving a Fine

Authorized staff can waive a fine:

1. Open the fine record.
2. Click **Waive**.
3. Enter the waive reason.
4. The fine status changes to "Waived" and the waiver is logged with the staff member's identity and date.

### 9.6 Balance Tracking

The patron record maintains running totals for:

- **Total fines owed** -- Sum of all outstanding fine amounts
- **Total fines paid** -- Sum of all payments made

When outstanding fines exceed a configurable threshold, the patron's borrowing status is automatically set to "Blocked (Fines)" and checkout operations are prevented until the balance is reduced.

---

## 10. Acquisitions

The Acquisitions module (`/acquisition`) manages the purchasing workflow for library materials.

### 10.1 Purchase Orders

**Creating an Order:**

1. Navigate to `/acquisition` and click **New Order**.
2. Fill in:
   - **Order number** (auto-generated if blank)
   - **Vendor name** and account details
   - **Order date** (defaults to today)
   - **Order type**: Purchase, Standing Order, Gift/Donation, Exchange, Deposit, Approval Plan
   - **Budget code** to charge
   - **Currency** (defaults to ZAR)
   - **Shipping address** and notes
3. Save the order in "Draft" status.

**Order Workflow:**

| Status | Description |
|--------|-------------|
| Draft | Order is being prepared |
| Submitted | Sent for approval |
| Approved | Approved for ordering |
| Ordered | Placed with the vendor |
| Partially Received | Some line items received |
| Received | All line items received |
| Cancelled | Order cancelled |

### 10.2 Order Lines

Each purchase order contains one or more line items:

1. Open an existing order at `/acquisition/order/:id`.
2. Click **Add Line Item**.
3. Enter the title, ISBN/ISSN, author, publisher, edition, material type, quantity, unit price, and discount percentage.
4. The line total is calculated automatically: `quantity x unit_price x (1 - discount_percent / 100)`.
5. The order total is the sum of all line totals plus tax and shipping.

Each line item can optionally be linked to an existing catalog record.

### 10.3 Receiving

When materials arrive:

1. Navigate to the order or use `/acquisition/receive`.
2. Select the line item(s) being received.
3. Enter the quantity received and the date.
4. The line item status updates. If all lines are fully received, the order status changes to "Received".

On receiving, the system can automatically create copy records in the catalog for the received items.

### 10.4 Budgets

Library budgets are managed at `/acquisition/budgets`. Each budget has:

| Field | Description |
|-------|-------------|
| Budget code | Unique identifier (e.g., `MON-2026`) |
| Fund name | Descriptive name |
| Fiscal year | e.g., `2025/2026` |
| Allocated amount | Total budget allocation |
| Committed amount | On-order amount (updated automatically) |
| Spent amount | Received/invoiced amount (updated automatically) |
| Category | Monographs, Serials, Electronic Resources, Special Collections, Binding, ILL, AV Media, General |
| Department | Departmental allocation |

The **available balance** is calculated as: `Allocated - Committed - Spent`.

---

## 11. Serials

The Serials module (`/serial`) manages serial subscriptions and the check-in of individual issues.

### 11.1 Subscriptions

**Creating a Subscription:**

1. Navigate to `/serial` and click **New Subscription**.
2. Link the subscription to an existing library item (the parent serial/periodical record).
3. Fill in:
   - Subscription number
   - Vendor name
   - Start date and end date
   - Renewal date
   - Frequency (Daily, Weekly, Biweekly, Semimonthly, Monthly, Bimonthly, Quarterly, Semiannual, Annual, Biennial, Triennial, Irregular)
   - Expected issues per year
   - Cost per year and currency
   - Budget code
   - Delivery method (Mail, Electronic, Both)
   - Routing list (ordered list of staff members who receive each issue)
4. Save.

**Subscription Statuses:** Active, Pending Renewal, Cancelled, Expired, Suspended.

### 11.2 Issue Check-in

When an issue arrives:

1. Navigate to `/serial/checkin`.
2. Select the subscription.
3. Enter the volume, issue number, part, supplement, and issue date.
4. The system marks the corresponding expected issue as "Received" and records the received date.
5. If the issue was not expected, a new issue record is created.

### 11.3 Expected Issues

The system generates expected issue records based on the subscription frequency. Each expected issue has:

- Volume and issue number
- Expected date
- Status: Expected, Received, Missing, Claimed, Damaged, Bound

### 11.4 Gap Analysis and Claims

Navigate to the subscription view (`/serial/view/:id`) to see a gap analysis showing:

- Issues received on time
- Late issues (received after expected date)
- Missing issues (expected date passed, not received)

For missing issues, click **Claim** to generate a claim record. The claim date and claim count are tracked. Claims can be submitted to the vendor at `/serial/claim`.

### 11.5 Routing Lists

Each subscription can have an ordered routing list of staff members. When an issue is checked in, the routing list indicates the sequence of staff who should receive the issue for review.

---

## 12. Interlibrary Loan (ILL)

The ILL module (`/ill`) manages borrowing materials from and lending materials to other libraries.

### 12.1 Creating a Request

1. Navigate to `/ill` and click **New Request** or go to `/ill/edit`.
2. Select the direction:
   - **Borrowing** -- Your institution requests a title from another library
   - **Lending** -- Another library requests a title from your collection
3. Fill in:
   - Patron (for borrowing requests)
   - Partner library name and contact details
   - Title, author, ISBN/ISSN, publisher, publication year
   - Volume/issue and pages (for serial articles)
   - Request date and needed-by date
   - For lending: link to the item and copy in your catalog
4. Save. The request is created with status "Requested".

### 12.2 Request Workflow

| Status | Description |
|--------|-------------|
| Requested | Initial request submitted |
| Approved | Request approved internally |
| Shipped | Item shipped to/from the partner library |
| Received | Item received |
| In Use | Patron is using the borrowed item |
| Returned | Item returned to the lending library |
| Overdue | Due date passed without return |
| Cancelled | Request cancelled |
| Denied | Request denied by the partner library |

### 12.3 Tracking and Shipping

For each ILL request, you can record:

- Shipping method and tracking number
- Shipped date, received date, due date, return date
- Cost and currency

### 12.4 ILL Costs

ILL service fees can be recorded per request. If the institution charges patrons for ILL services, a fine of type "ILL Service Fee" can be applied to the patron's account.

---

## 13. Heritage Accounting

The Library System includes heritage accounting fields aligned with GRAP 103 (South African public sector heritage asset accounting) and IPSAS 45 (international equivalent). These fields enable institutions to track the financial value of library items as heritage assets.

### 13.1 Heritage Fields on Library Items

The following fields are available on each library item record:

| Field | Description |
|-------|-------------|
| Heritage asset ID | Link to the heritage asset register |
| Acquisition method | Purchase, Donation, Gift, Bequest, Exchange, Deposit |
| Acquisition date | Date the item was acquired |
| Acquisition cost | Original cost of acquisition |
| Acquisition currency | Currency of the cost (default: ZAR) |
| Replacement value | Current replacement value |
| Insurance value | Insured value |
| Insurance policy | Policy reference number |
| Insurance expiry | Policy expiry date |
| Asset class code | Heritage asset classification code |
| Recognition status | Pending, Recognized, Derecognized |
| Valuation date | Date of the most recent valuation |
| Valuation method | Method used for valuation (e.g., market value, cost, expert appraisal) |
| Valuation notes | Free text notes on the valuation |
| Donor name | Name of the donor (for donated items) |
| Donor restrictions | Any restrictions imposed by the donor |
| Condition grade | Physical condition assessment |
| Conservation priority | Priority for conservation treatment |

### 13.2 Use Cases

- **Asset registers:** Generate a list of all items with their acquisition cost and current valuation for financial reporting.
- **Insurance management:** Track insurance coverage and flag items with expired policies.
- **Donor compliance:** Record donor restrictions and ensure they are honored in circulation and access policies.
- **Condition monitoring:** Track condition grades over time and prioritize conservation work.

---

## 14. MARC Import/Export

### 14.1 MARC 21 Field Mapping

The Library System maps MARC 21 fields to library item columns as follows:

| MARC Tag | Subfield | Library Item Field |
|----------|----------|-------------------|
| 010 | $a | LCCN |
| 020 | $a | ISBN |
| 022 | $a | ISSN |
| 035 | $a | OCLC number |
| 050 | $a, $b | Classification number, Cutter number |
| 082 | $a | Classification number (Dewey) |
| 090/099 | $a | Call number |
| 100/110 | $a | Author (primary creator) |
| 250 | $a | Edition statement |
| 260/264 | $a, $b, $c | Publication place, Publisher, Publication date |
| 300 | $a, $b, $c, $e | Pagination, Physical details, Dimensions, Accompanying material |
| 310 | $a | Frequency |
| 362 | $a | Numbering peculiarities |
| 440/490 | $a, $v, $x | Series title, Series number, Series ISSN |
| 500 | $a | General note |
| 504 | $a | Bibliography note |
| 505 | $a | Contents note |
| 520 | $a | Summary |
| 521 | $a | Target audience |
| 538 | $a | System requirements |
| 563 | $a | Binding note |
| 600 | $a | Subject - Personal name |
| 610 | $a | Subject - Corporate name |
| 611 | $a | Subject - Meeting |
| 630/650 | $a | Subject - Topical |
| 651 | $a | Subject - Geographic |
| 655 | $a | Subject - Genre/Form |
| 700/710 | $a | Contributor (additional creator) |

### 14.2 MarcXML Import

MARC records in MarcXML format can be imported into the library catalog. The MarcService parses each record, maps fields according to the table above, creates the library item, and associates creators and subjects.

### 14.3 CSV Export

All report views support CSV export. Navigate to the desired report and click the **Export CSV** button. Available export types include catalogue, creators, and subjects.

---

## 15. Reports

Navigate to `/libraryReports` for the library reporting dashboard. The following reports are available:

### 15.1 Dashboard (Index)

The main reports page displays summary statistics:

- Total items, available, on loan, reference-only counts
- Breakdown by material type
- Total unique creators and subjects
- Items added in the last 30 days

### 15.2 Catalogue Report

A filterable list of all catalog records showing title, material type, call number, ISBN, publisher, publication date, and circulation status. Filter by material type, status, keyword, or call number prefix. Exportable to CSV.

### 15.3 Creators Report

Lists all creators with their role and item count. Filter by role or search by name. Includes a summary showing total unique creators and breakdown by role. Exportable to CSV.

### 15.4 Subjects Report

Lists all subject headings with their type, source, and item count. Filter by subject type or source. Exportable to CSV.

### 15.5 Publishers Report

Lists all publishers with publication place and item count, ordered by frequency.

### 15.6 Call Numbers Report

Lists all items with call numbers, showing classification scheme, shelf location, title, and material type. Includes a summary of items with and without call numbers and breakdown by classification scheme.

### 15.7 Circulation Statistics

The Circulation module provides real-time statistics including:

- Active checkouts and overdue count
- Today's checkouts and returns
- Pending and ready holds
- Total and available copies
- Outstanding fine balance

---

## Appendix A: CLI Commands

| Command | Description |
|---------|-------------|
| `php symfony library:process-covers` | Process and cache cover images for items with ISBNs |

---

## Appendix B: Database Tables

| Table | Purpose |
|-------|---------|
| `library_item` | Core bibliographic metadata linked to `information_object` |
| `library_item_creator` | Item-creator relationships with roles |
| `library_item_subject` | Item-subject heading relationships |
| `library_copy` | Individual physical copies with barcodes |
| `library_patron` | Registered library patrons |
| `library_checkout` | Checkout/return transaction records |
| `library_hold` | Hold/reservation queue |
| `library_fine` | Fines, payments, and waivers |
| `library_loan_rule` | Configurable loan rules per material/patron type |
| `library_budget` | Acquisition fund allocations |
| `library_order` | Purchase orders |
| `library_order_line` | Purchase order line items |
| `library_subscription` | Serial subscriptions |
| `library_serial_issue` | Individual serial issue tracking |
| `library_ill_request` | Interlibrary loan requests |
| `library_settings` | Library module configuration |
| `library_subject_authority` | Controlled subject heading authority records |
| `library_entity_subject_map` | NER entity to subject authority mapping |

---

*Heratio Library System User Manual -- The Archive and Heritage Group (Pty) Ltd, 2026.*
