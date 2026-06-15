> Heratio Help Center article. Category: User Guide / Loans.

# Loan Management User Guide

Loan Management records and tracks objects moving in and out of your institution: outgoing loans (objects you lend to a partner venue) and incoming loans (objects you borrow). Every loan carries a unique loan number, a partner institution, key dates, insurance and fee details, the objects involved, supporting documents, condition and facility reports, shipments, costs, extensions, and a full status history. A separate touring-exhibition scheduler checks whether an object is already committed elsewhere before you book it into a new venue. The module is sector-agnostic and jurisdiction-neutral, so it works the same for museums, galleries, archives, libraries, and digital-asset collections in any market.

## Overview

A loan is a single agreement stored in the `ahg_loan` table. Each loan has a `loan_type` of either `out` (you are lending) or `in` (you are borrowing), and a `sector` (for example museum, gallery, archive, library, or digital assets). The loan number is generated automatically in the form `MUS-LO-YYYY-NNNN` for outgoing loans and `MUS-LI-YYYY-NNNN` for incoming loans, where `YYYY` is the year and `NNNN` is a sequential counter.

Each loan moves through a defined lifecycle of statuses. The system enforces which status changes are allowed, so a loan can only advance along valid paths. Every change is written to the status history with the user who made it and an optional comment.

A loan groups together several kinds of related records, all linked back to the loan:

- Objects on the loan (`ahg_loan_object`)
- Documents (`ahg_loan_document`)
- Extensions to the end date (`ahg_loan_extension`)
- Status history (`ahg_loan_status_history`)
- Condition reports and their images (`ahg_loan_condition_report`, `ahg_loan_condition_image`)
- Facility reports and their images (`ahg_loan_facility_report`, `ahg_loan_facility_image`)
- Shipments, couriers, and tracking events (`ahg_loan_shipment`, `ahg_loan_courier`, `ahg_loan_shipment_event`)
- Costs (`ahg_loan_cost`)
- Notification templates and a send log (`ahg_loan_notification_template`, `ahg_loan_notification_log`)

## Key features

- **Outgoing and incoming loans** - one record type handles both lending and borrowing, distinguished by `loan_type`.
- **Automatic loan numbers** - generated per type and year; you do not enter them by hand.
- **Status lifecycle with enforced transitions** - the system only offers the next statuses that are valid from the current one.
- **Partner institution details** - name, contact name, email, phone, and address for the borrower or lender.
- **Dates** - request date, start date, end date, and return date, with overdue highlighting.
- **Insurance and fees** - insurance type, value, currency, policy number, provider, plus an optional loan fee and fee currency.
- **Objects on the loan** - add archival descriptions (information objects) by searching title or identifier, with per-object insurance value and special / display requirements.
- **Documents** - upload files such as loan agreements, insurance certificates, condition reports, facility reports, correspondence, invoices, receipts, and customs documents.
- **Extensions** - push out the end date with a recorded reason; the previous and new end dates are kept.
- **Returns** - record a return date, which moves the loan to the returned status.
- **Condition and facility reports** - view environmental, security, and condition assessments captured against the loan.
- **Shipments and couriers** - track outbound and return shipments with tracking numbers and event timelines.
- **Costs** - record itemised costs with vendor, invoice, paid status, and who bears the cost.
- **Statistics sidebar** - totals for all loans, active loans out, active loans in, overdue loans, loans due within 30 days, and total insurance value.
- **Touring-exhibition scheduling** - book an object into a venue for a date window only when that window is clear of conflicting tour bookings, committed loans, and on-display exhibition placements.

## How to use

All loan pages require you to be logged in. Actions that create, update, or delete data are protected by access-control permissions (create, update, delete).

### Browse and filter loans

Open the loan list at `/loan`. The page shows a paginated table of loans plus a statistics sidebar with overdue and due-soon panels and quick-action links.

You can filter the list with:

- **Search** - matches loan number, title, or partner institution.
- **Type** - all types, loans out, or loans in.
- **Status** - for example pending, approved, active (on loan), or returned.
- **Overdue** - show only loans past their end date that are still active.

The list can be sorted by loan number, title, partner institution, start date, end date, status, created date, or updated date.

### Create a loan

From the loan list, use **New Loan Out** or **New Loan In** (also available under Quick Actions), or open `/loan/create`. You can also start a loan pre-filled from an archival description, in which case the originating object is automatically attached to the new loan.

The create form requires:

- **Loan type** (out or in)
- **Sector**
- **Partner institution**

Optional fields include title, description, purpose (exhibition, research, conservation, photography, education, or other), partner contact details and address, request / start / end dates, insurance type, value, currency, policy number and provider, loan fee and fee currency, repository, and notes. New loans are created with the status **draft**, and an initial status-history entry is recorded.

### View and edit a loan

Open a loan at `/loan/{id}` to see all its details: general information, partner information, insurance and fees, objects, documents, extensions, condition reports, facility reports, shipments, costs, and the status-history timeline. If the loan is past its end date and still active, an overdue banner is shown.

Edit a loan at `/loan/{id}/edit`. The end date must not be earlier than the start date.

### Add or remove objects

On the loan detail page, search for an archival description by title or identifier and add it to the loan, optionally with an insurance value and special or display requirements. Each added object starts with a status of **pending**. Use the remove action in the objects table to take an object off the loan.

### Upload documents

On the loan detail page, choose a file and a document type, then upload. Supported types include loan agreement, insurance certificate, condition report, facility report, correspondence, invoice, receipt, customs document, and other. Files are stored under the loan number inside the configured uploads path. The maximum upload size is 20 MB per file.

### Change status

Use the **Change Status** menu on the loan detail page. Only valid next statuses are offered. The defined transitions are:

- `draft` to submitted or cancelled
- `submitted` to under review or cancelled
- `under_review` to approved, rejected, or cancelled
- `approved` to preparing or cancelled
- `preparing` to dispatched or cancelled
- `dispatched` to in transit or cancelled
- `in_transit` to received or cancelled
- `received` to on loan or cancelled
- `on_loan` to return requested or cancelled
- `return_requested` to returned or cancelled
- `returned` to closed or cancelled
- `rejected` to cancelled
- `closed` and `cancelled` are end states with no further transitions

When a loan is moved to **approved**, the approving user and the approval date are recorded. You can add an optional comment with any transition; it is stored in the status history.

### Extend a loan

When a loan is active (dispatched, in transit, received, or on loan), use **Extend** to set a new end date and a required reason. The new date must be in the future. The previous and new end dates are kept in the extension history.

### Record a return

When a loan is in the **return requested** status, use **Record Return** to enter the return date and optional notes. This sets the return date and moves the loan to the **returned** status.

### Delete a loan

Administrators can delete a loan from the detail page. Deleting a loan also removes all of its related records (objects, documents, extensions, status history, condition and facility reports and their images, shipments and shipment events, notification log entries, and costs).

### Touring-exhibition scheduling

To plan an object across multiple venues, open its tour schedule at `/loan/tour/object/{objectId}`. The page shows a single timeline merging that object's tour stops, committed loans, and on-display exhibition windows.

To book a venue, enter a venue name (and optionally city, country, status, and notes) with a start and end date. The scheduler checks the requested window against:

1. Other active tour bookings for the same object (tentative or committed)
2. Committed outgoing loans carrying the same object (any active loan status from submitted through return requested)
3. On-display windows in the digital-twin exhibition, when that data is present

Date windows are inclusive on both ends; two windows conflict when one starts on or before the other ends and vice versa. If any conflict is found, nothing is saved and the conflicts are listed. If the window is clear, the booking is saved with a status of **tentative** or **committed**. Cancelling a booking sets its status to cancelled and frees the window for reuse, while keeping the row for audit.

## Configuration

The loan module has no dedicated configuration keys of its own. The behaviour worth knowing about:

- **Uploads location** - loan documents are written under the central `heratio.uploads_path` setting, inside a `loans/{loan_number}` folder. There are no loan-specific path settings.
- **Database tables** - the core schema is installed from `packages/ahg-loan/database/install.sql`. The touring-exhibition table `ahg_loan_tour_booking` is created automatically on first boot (from `database/install_tour.sql`) if it does not already exist.
- **Default seed data** - the install ships default notification templates (due-date reminders at 30, 14, and 7 days, an overdue notice, and an approval notice) and a starter list of couriers.
- **Currency defaults** - several currency fields default to `ZAR` at the database level, but each loan, fee, cost, and shipment stores its own currency, so any currency can be used per record.
- **Access control** - create, update, and delete actions are guarded by the application's access-control middleware. Deleting a loan additionally requires an administrator account.

## References

- Source package: `packages/ahg-loan/`
- GitHub issue: https://github.com/ArchiveHeritageGroup/heratio/issues/593
- Related: the touring scheduler can reference digital-twin exhibition placements when that module is installed.
