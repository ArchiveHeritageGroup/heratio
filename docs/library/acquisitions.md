# Library Acquisitions

Heratio's library acquisitions module manages purchase orders, order lines,
vendors (suppliers), and acquisition budgets, with real-time budget accounting
and a GRAP 103 / IPSAS 17 disposal (write-off) workflow. It is exposed as a
staff web desk under `/library-manage/acquisition/*` and as a JSON:API under
`/api/library/*`. Issue tracker: heratio#1091 / heratio#1100.

## Summary

- **Orders** (`library_order`) carry a header (vendor, dates, budget, status,
  totals) and one or more **lines** (`library_order_line`).
- **Budgets** (`library_budget`) track `allocated_amount`, `committed_amount`
  and `spent_amount`. These are recomputed automatically after every order,
  line, receive, cancel, or write-off action - no nightly job is required.
- **Vendors** (`library_vendor`) are first-class suppliers (local or
  international); orders may reference a registered vendor or carry a free-text
  vendor name.
- All enumerated values (order status, order type, payment status, disposal
  reason, acquisition reason, vendor type) come from the `ahg_dropdown` table
  via the Dropdown Manager. There are no hardcoded option lists and no ENUM
  columns.

## Budget accounting model

For each budget (matched by `budget_code`):

- **Committed** = sum of all non-cancelled order-line totals on orders using
  that budget code. Cancelled / written-off orders are excluded, so cancelling
  releases the commitment.
- **Spent** = sum of line totals for lines whose status is `received`, on
  orders that are `ordered`, `partial`, or `received`.
- **Available** = `allocated_amount - committed_amount`.

Recalculation is triggered by: creating an order, adding/updating/removing a
line, receiving lines (full or partial), changing order status, and writing off
an order.

## Order lifecycle

Order status values live in the `library_order_status` dropdown taxonomy:
`draft`, `submitted`, `approved`, `ordered`, `partial`, `received`,
`cancelled`. The order-level status is derived from its line statuses on each
recalculation (all lines received -> `received`; some received -> `partial`;
otherwise `ordered`). A `cancelled` order keeps its terminal status; only its
monetary totals are refreshed.

### Receiving

- **Receive all**: marks every pending line as fully received, sets
  `received_date`, and (where the `library_copy` table exists) materialises one
  copy row per received unit.
- **Partial receipt**: receive specific quantities per line. A line with
  `quantity_received` between 1 and `quantity - 1` is marked `partial`; equal to
  `quantity` is `received`.

## GRAP 103 / IPSAS 17 disposal (write-off)

An order can be written off with an audited reason code from the
`acq_disposal_reason` taxonomy (`damaged`, `lost`, `obsolete`, `duplicate`,
`withdrawn`). Writing off:

- sets the order status to `cancelled` (releasing its budget commitment),
- records `written_off_reason`, `written_off_by`, and `written_off_date` on the
  order header,
- appends an optional note to the order notes with a timestamp and reason.

A second taxonomy, `acq_reason` (`purchase`, `gift`, `exchange`, `deposit`,
`approval`), provides standard acquisition reason codes.

## Web routes (staff desk)

| Path | Purpose |
|---|---|
| `/library-manage/acquisitions` | Order list (status filter + search) |
| `/library-manage/acquisition/dashboard` | Orders by vendor + budget utilisation |
| `/library-manage/acquisition/order/create` | New order form |
| `/library-manage/acquisition/order/{id}` | Order detail (lines, totals, status, write-off) |
| `/library-manage/acquisition/order/{id}/receive-all` | Receive all pending lines |
| `/library-manage/acquisition/order/{id}/write-off` | Write off (disposal) |
| `/library-manage/acquisition/budgets` | Budget list |
| `/library-manage/acquisition/budget/{id}` | Budget detail (utilisation bars) |
| `/library-manage/acquisition/vendors` | Vendor list + create/edit |

## JSON:API

Mounted under `/api/library`, key-authenticated (`api.auth:read|write|delete`):

- `GET|POST /orders`, `GET|PATCH|DELETE /orders/{id}`
- `PATCH /orders/{id}/receive` - body `{"data":{"attributes":{"receive_all":true}}}`
  or `{"data":{"attributes":{"lines":[{"id":1,"quantity_received":2}]}}}`.
  Updates received quantities, line/order status, and the linked budget.
- `GET|POST /orders/{id}/lines`, `GET|PATCH|DELETE /order-lines/{id}`
- `GET|POST /budgets`, `GET|PATCH|DELETE /budgets/{id}`
- `GET|POST /vendors`, `GET|PATCH|DELETE /vendors/{id}`

Use `?include=lines,vendor,budget` on order endpoints to eager-load
relationships.

## Schema notes

- `library_budget.fund_name` (not `name`) holds the fund label;
  `fiscal_year` is `VARCHAR(9)` (e.g. `2026` or `2026/27`), not an integer.
- `library_order` links to a budget by `budget_code` (string), not by id.
- Disposal columns on `library_order`: `written_off_reason`, `written_off_by`,
  `written_off_date` (added in migration
  `2026_05_31_000101_add_writeoff_fields_and_acq_dropdowns`).

## Tests

`packages/ahg-library/tests/Feature/Api/LibraryAcquisitionsApiTest.php` boots a
minimal Capsule + SQLite container and exercises the real
`LibraryAcquisitionService`: full receive increases spent, partial receipt sets
`partial` status and partial spend, cancel releases the commitment, and the
write-off workflow records the reason and frees the commitment. It also guards
the budget create/update column-mapping regression (`fund_name` /
`allocated_amount`).
