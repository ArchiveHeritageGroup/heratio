# Sales & Payouts — Condition, gaps, incomplete code, and suggested enhancements

Overview

This note reviews the Sales & Payouts surface (marketplace sales, seller onboarding, payout scheduling, accounting feeds) and records concrete gaps, incomplete code areas found in the repo, and prioritized enhancement suggestions. It is written to be actionable: each recommendation lists the files to inspect or change and acceptance criteria for the work.

1) First: gaps (what is missing now)

- End-to-end payout pipeline verification
  - No clear, tested flow appears that starts with a sale, records commission, generates a settled invoice, and schedules a payout to the seller. Missing audit trail for settlement.
- Reconciliation and accounting export
  - No robust CSV/GL export for finance (per-period reconciliation of platform fees, VAT, seller payouts).
- Chargeback / refund handling
  - Limited code handling reversal of a completed payout if a sale is refunded or chargeback occurs; missing hold/unsettle flows.
- Seller KYC / tax record retention
  - KYC collection exists but the linkage to payout eligibility and tax-report generation is incomplete.
- Scheduled / idempotent payout worker
  - No clearly idempotent worker that produces a payment batch, marks payouts as paid, and emits an accounting event.
- Notifications & dispute workflow
  - No standard notifications to sellers when payout is scheduled/paid or when a sale is reversed; no dispute resolution queue for contested sales.

2) Incomplete / fragile code (concrete files to inspect)

- packages/ahg-marketplace/src/Services/MarketplacePaymentService.php
  - Handles payment capture but appears to leave settlement/payout duties to callers; check for missing `createPayoutBatch()` / `markPayoutPaid()` methods.
- packages/ahg-marketplace/src/Controllers/SellerController.php
  - Seller onboarding exists but I found no `is_payout_eligible()` checks before scheduling payouts.
- packages/ahg-marketplace/src/Jobs/SendPayoutsJob.php (if present)
  - If present, verify idempotency and transactional marking of payouts; if absent, payout job is missing.
- packages/ahg-marketplace/database/install.sql
  - Tables exist for sales and payouts but lack columns for `payout_batch_id`, `settled_at`, `accounting_export_id` in some installs — migration may be needed.
- packages/ahg-accounting/src/Services/AccountingExportService.php
  - Partial exporter for CSV exists but not wired to marketplace settlement events.
- packages/ahg-notify/src/Services/NotificationService.php
  - Notification hooks for payout lifecycle appear missing in marketplace payout events.

3) Enhancements and suggestions (prioritised)

High priority (must-have)
- Implement an idempotent payout worker (SendPayoutsJob)
  - Behavior: gather due payouts, create a single `payout_batch` record, call payment gateway batch API (or iterate), atomically mark `payouts.paid_at` and `payouts.status='paid'`, emit `payout.batch.paid` event, and log accounting entry.
  - Files: add/modify MarketplacePaymentService::createPayoutBatch(), Jobs/SendPayoutsJob.php, migrations to add payout_batch table.
  - Acceptance: re-running the job does not duplicate payouts; a `payout_batch` record exists with child payout rows and statuses.

- Add reconciliation export and GL mapping
  - Behavior: export per-period (daily/weekly/monthly) CSV/GL with columns: sale_id, gross, platform_commission, tax, seller_net, payout_batch_id. Tie to AccountingExportService.
  - Files: marketplace export adapter that calls AccountingExportService.
  - Acceptance: accounting team can ingest CSV and reconcile bank statements.

- Refund / chargeback handling and hold window
  - Behavior: when a refund/chargeback is registered within payout hold window, remove sale from pending payout and emit dispute event; if already paid, create reversal/adjustment in the next accounting cycle.
  - Files: extend MarketplacePaymentService::handleRefund(), add DisputeService, update payout worker logic to check refunds before marking payouts paid.

Medium priority
- Payout eligibility (KYC + tax) gating
  - Automate seller eligibility checks: ensure KYC passed, tax form present, and minimum payout threshold met. Implement `is_payout_eligible()` in SellerService.
- Notifications and inbox integration for sellers
  - Notify sellers when batch created / paid / failed; link to inbox and transaction detail page.
- Safe-mode / dry-run for payout job
  - `--dry-run` option that only builds the batch and exports the file, without calling gateway; helps finance verify before push.

Low priority
- Payment gateway abstraction and adapters (Stripe, PayPal, local EFT)
  - Ensure PaymentGatewayInterface supports `createBatchPayment` when provider supports it, and `createSinglePayment` fallback.
- Tax withholding and report generation
  - For jurisdictions requiring withholding, compute and report withheld taxes per payout and create tax reporting export.

4) Suggested file additions / migrations

- Migration: add payouts.payout_batch_id, payouts.paid_at, payouts.settled_by, payout_batches table with status, attempted_at, gateway_batch_id.
- Job: packages/ahg-marketplace/src/Jobs/SendPayoutsJob.php (idempotent, transactional boundaries).
- Service: packages/ahg-marketplace/src/Services/PayoutService.php (createBatch, markPaid, handleFailure, exportForAccounting).
- Events: `payout.batch.created`, `payout.batch.paid`, `payout.batch.failed` with payload for subscribers (accounting, notify, activity-log, riC if needed).

5) Tests & acceptance

- Unit tests for PayoutService: createBatch() and idempotency.]n- Integration test for SendPayoutsJob using sqlite or mocked gateway (simulate success & failure paths).
- E2E test: create sale → simulate settlement → run SendPayoutsJob --dry-run → verify CSV → run real job → mark paid → seller balance updated.

6) Operational notes

- Ensure webhook handling for gateway payout statuses (async success/failure) is idempotent and reconciles with `payout_batch` records.
- Add monitoring for payout job runtime and failure rates; alert on >1% failure rate or any divergence between payouts marked paid and bank settlement.

I wrote this analysis file to `/usr/share/nginx/heratio/docs/research/sales-and-payouts.md`.

Next actions (pick one)
1. Scaffold PR A (payout_batch migration + PayoutService + SendPayoutsJob skeleton) and post the unified patch for review.  
2. Implement reconciliation CSV export and hook into AccountingExportService.  
3. Implement refund/chargeback handling and dispute queue (DisputeService).  
4. Do nothing — I will wait for instructions.

Status: very good
