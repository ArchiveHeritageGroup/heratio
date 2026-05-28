# Library Acquisitions — Full CRUD Build-out Plan

**Status:** Approved (Phase 0 unblocked)
**Module:** `packages/ahg-library`
**PSIS parity source:** `atom-ahg-plugins/ahgLibraryPlugin/modules/acquisition`
**Author:** Plain Sailing Information Systems
**Decisions locked:** 2025-05-28 (Option A / real-time budgets / auto-copy on receipt / budget_code)

---

## 1. Why this plan exists

Acquisitions is the weakest corner of the Library module. Serials, by contrast, is
already a full CRUD (create / edit / update / delete / add-issue / subscription /
predict / coverage / clone / overdue-claims / claim) and needs no work.

Two problems must be addressed:

1. **It is broken, not merely thin.** `LibraryAcquisitionService` reads and writes
   `library_acquisition_order` / `library_acquisition_order_line`. **Those tables do
   not exist.** The real tables are `library_order` / `library_order_line` /
   `library_budget`. Every service method is wrapped in `Schema::hasTable()`, so it
   silently returns `0` / `[]` / `false` — the orders list is always empty, "create
   order" is a no-op, budgets never load. Acquisitions looks alive but does nothing.

2. **Even once pointed at the right tables, the UI exposes only:** list orders, view
   one order, edit one order, batch-capture lookup, budgets list. There is no create,
   no line management, no receiving, no approval, no invoice/payment, no write-off, no
   vendor management. The user's instinct is correct: this needs a full acquisitions
   CRUD.

The good news: the **schema is already rich and supports the whole workflow** —
`library_order` carries vendor, dates (order/expected/received), status, financials
(subtotal/tax/shipping/total/currency), invoice fields, approval fields;
`library_order_line` already models receiving (`quantity_received`, `received_date`);
`library_budget` already has `allocated_amount` / `committed_amount` / `spent_amount`.
So most of this is wiring, not net-new modelling.

---

## 2. Target acquisition workflow

```
draft ─► approved ─► ordered ─► partially_received ─► received ─► invoiced ─► paid ─► closed
   │
   └────────────────────────► cancelled / written_off (with reason + budget release)
```

PSIS parity vocabulary today is narrow (`draft / approved / ordered / received /
cancelled`). We extend it to the lifecycle above. **All status values come from the
Dropdown Manager** (`ahg_dropdown`, new taxonomies `acq_order_status`,
`acq_line_status`, `acq_payment_status`) — no ENUM columns, no hardcoded `<option>`
lists (per project rules).

### Budget accounting (real-time, per-action)

- **Order placed / approved** → sum(line totals for this budget_code) added to
  `library_budget.committed_amount`.
- **Receipt** → committed_amount -= received line total; spent_amount += received line total.
- **Cancel / write-off (pre-receipt)** → committed_amount -= order line total; spent unchanged.
- **Available = allocated − committed − spent.**
- Recalculate runs as a service method (`BudgetService::recalculateBudgetBalances()`)
  called synchronously on every order/receive/cancel action. No nightly job needed.

---

## 3. Phases

Each phase is independently releasable (`./bin/release minor "..."`). Phase 0 is a
hard blocker for everything else.

### Phase 0 — Schema/service reconciliation (BLOCKER)

Point `LibraryAcquisitionService` at the real PSISA tables and columns. No UI change.

**Schema target (Option A — PSISA alignment):**

| Heratio stub table (does not exist) | PSISA target table |
|---|---|
| `library_acquisition_budget` | `library_budget` |
| `library_acquisition_order` | `library_order` |
| `library_acquisition_order_line` | `library_order_line` |

**What the service must change:**
- Swap table references: `library_acquisition_order` → `library_order`, etc.
- Swap key: `budget_id` (int) → `budget_code` (string FK to `library_budget.budget_code`).
  Add `fund_code` to lines (both string, both on `library_budget`).
- Widen `createOrder()` / `addLine()` to full column set:
  - `library_order`: `vendor_id`, `vendor_name`, `expected_date`, `order_type`,
    `currency`, `subtotal`, `tax`, `shipping`, `total`, `invoice_number`,
    `invoice_date`, `payment_status`, `approved_by`, `approved_date`.
  - `library_order_line`: `library_item_id`, `isbn`, `issn`, `author`, `publisher`,
    `edition`, `material_type`, `qty`, `unit_price`, `discount_percent`, `line_total`,
    `qty_received`, `received_date`, `status`, `fund_code`.
- Drop all `Schema::hasTable()` guards — the real tables always exist.
- Seed dropdown taxonomies: `acq_order_status`, `acq_line_status`, `acq_payment_status`,
  `acq_order_type` (purchase / standing / gift / exchange / deposit / approval).
  Use `AhgDropdown::firstOrCreate()` in the service provider boot.

**Files:** `src/Services/LibraryAcquisitionService.php`, `src/Providers/AhgLibraryServiceProvider.php`.

**Acceptance:** `/library-manage/acquisitions` list renders real rows from `library_order`;
`/acquisition/order/{id}` view shows a real order; a seeded order appears without
creating new data.

---

### Phase 1 — Orders CRUD + line management

- **Create order:** `GET/POST /library-manage/acquisition/order/create`
  (`library.acquisition-order-create` / `-store`). Wire to `createOrder()`.
- **Delete/cancel order:** `DELETE /library-manage/acquisition/order/{id}`.
- **Lines:** add / edit / remove line items on an order
  (`addLine` exists in service; add `updateLine` / `deleteLine`). Recompute
  `line_total`, order `subtotal`/`total`.
- New view: `acquisition/order-create.blade.php`; extend `order.blade.php` with a
  line-items editor + "Add line" / per-line edit + remove.
- **Acceptance:** create an order from scratch, add 3 lines, totals roll up, edit a
  line, delete a line, delete the order.

**Service methods to add:** `updateLine()`, `deleteLine()`, `recalcOrderTotals()`,
`deleteOrder()`.

---

### Phase 2 — Receiving / receipts (PSIS `receiveAction` parity)

- **Receive:** `POST /library-manage/acquisition/order/{id}/receive` per line, with a
  `quantity_received` field (supports **partial receipts**).
- Roll up line status (`ordered → partially_received → received`) and order status.
- **Auto-create `library_copy` rows on full receipt.** When every line on an order
  reaches `qty_received = qty`, create one `library_copy` per line with:
  - `library_item_id` (newly created via `LibraryService::createItem()` or linked
    via `library_item_id` on the line)
  - `barcode` (auto-generated)
  - `accession_number` (auto-generated from next sequence)
  - `status` = circulatable (e.g. `available`)
  - `created_by`, `created_at`
  - Copy is immediately visible in the catalogue and circulatable.
- New view: `acquisition/receive.blade.php` (line grid with qty-received inputs).
- **Acceptance:** partial receive (3 of 5) flips line to `partially_received` and
  order to `partially_received`; receiving the remainder flips both to `received` and
  creates one library_copy per line; received date stamped.

**Service methods to add:** `receiveLine()`, `receiveOrder()`, `createCopyFromLine()`.

---

### Phase 3 — Budgets / funds CRUD + commitment accounting

- **Budget CRUD:** create / edit budget
  (`createBudget` exists; add `updateBudget`). Routes
  `library.budget-create` / `-store` / `-edit` / `-update`.
- **Commitment accounting:** hook order placement → `committed_amount`; receipt/invoice
  → `spent_amount`; cancel/write-off → release. Show allocated / committed / spent /
  available on the budgets page.
- New view: `acquisition/budget-form.blade.php`; extend `budgets.blade.php` with
  Add/Edit buttons and the four-column rollup.
- **Acceptance:** placing an order against a fund increases committed; receiving moves
  it to spent; available = allocated − committed − spent stays correct.

---

### Phase 4 — Approval + Invoice / Payment

- **Approve:** `POST .../order/{id}/approve` → sets `approved_by` / `approved_date`,
  status `draft → approved`. Gated by `acl:update`.
- **Invoice/payment:** `POST .../order/{id}/invoice` → `invoice_number` / `invoice_date`
  / `payment_status`. Modal on the order view.
- **Acceptance:** approve transitions status + records approver; recording an invoice
  sets payment_status and moves committed → spent.

---

### Phase 5 — Cancel / Write-off

- **Cancel** (pre-receipt) vs **Write-off** (post-receipt, e.g. damaged/lost stock) as
  distinct actions with a **reason** captured and an audit trail
  (`written_off_by`, `written_off_date`, `write_off_reason` — add columns to
  `library_order`).
- Release any outstanding budget commitment on cancel; record a loss line on write-off.
- **Acceptance:** cancelling a draft releases commitment; writing off a received order
  records reason + actor and does not double-count the budget.

---

### Phase 6 — Vendors

Today `vendor_id` + `vendor_name` are free-text on the order (PSIS keeps it free-text).

**Decision deferred.** Keep free-text (zero new tables) until after Phases 0–2 prove
out. Revisit `library_vendor` table + CRUD at Phase 6 review.

---

### Phase 7 — Bulk import + claims (optional)

- **Bulk import** of orders/lines from CSV (PSIS `bulkImport` + `bulkImportSample`).
  Reuse the ingest-wizard CSV pattern.
- **Claims** for late orders (parallel to the existing serials claim flow) — claim a
  line whose `expected_date` has passed and nothing received.

---

## 4. Cross-cutting requirements (every phase)

- **Routes:** all under `/library-manage/acquisition/...`. `library-manage` is already
  on the slug catch-all exclusion list, so no `/{slug}` collision.
- **Views are LOCKED pages.** Each phase that touches a `.blade.php` needs a one-shot
  `./bin/unlock` for that path and is flagged to the user at release.
- **Dropdowns, not ENUM.** New status/payment vocab seeded via `AhgDropdown::firstOrCreate()`
  in the service provider boot.
- **ACL.** `acl:create` on store routes, `acl:update` on mutate routes (matches the
  patron/circulation pattern).
- **Service-first.** Controllers call `LibraryAcquisitionService`; no query logic in
  controllers or views (Quality Standard).
- **Docs + in-app help on BOTH sites.** Update `docs/library-complete-guide.md` §
  Acquisitions + `ahg:help-ingest`; file the PSIS twin on `ArchiveHeritageGroup/atom-ahg-plugins`
  per `feedback_always_file_psis_twin`.
- **Smoke test** each action (blade compile + live render + a real create→receive→close
  pass) before supplying the release command.

---

## 5. Release sequencing

| Release | Scope | Risk |
|---|---|---|
| v+0.0.x | Phase 0 reconciliation | Low — service-only, makes the page actually work |
| v+0.1.0 | Phase 1 Orders + lines | Medium |
| v+0.1.0 | Phase 2 Receiving + auto-copy | Medium |
| v+0.1.0 | Phase 3 Budgets + commitment | Medium |
| v+0.1.0 | Phase 4 Approval + invoice | Low |
| v+0.1.0 | Phase 5 Cancel / write-off | Medium |
| v+0.1.0 | Phase 6 Vendors (if approved later) | Medium |
| v+0.1.0 | Phase 7 Bulk import + claims | Low |

---

## 6. Locked decisions

| # | Decision | Resolution |
|---|---|---|
| D1 | Schema: Heratio stubs vs PSISA rich tables | **Option A — PSISA schema** (`library_order`, `library_budget`, etc.) |
| D2 | Budget key | **`budget_code`** (string, matches PSISA) |
| D3 | Vendors | **Free-text** for now; revisit Phase 6 |
| D4 | Auto-copy on full receipt | **YES** — `library_copy` rows created on receipt |
| D5 | Write-off audit | **Columns on `library_order`** (simple) |
| D6 | Budget accounting | **Real-time per-action** (`BudgetService::recalc...()`) |
