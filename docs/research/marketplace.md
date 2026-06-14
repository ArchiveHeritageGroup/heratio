# Marketplace — Condition, Gaps, Incomplete Code, and Suggested Enhancements

This document summarises the current state of the Marketplace (packages/ahg-marketplace) as it relates to the Research product, lists concrete gaps and incomplete code discovered in the repo, and proposes actionable enhancements. Add this file to the Research docs so product owners and developers can triage next steps.

Location
- File created: docs/research/marketplace.md (this document)
- Primary code surface: packages/ahg-marketplace/

1) Quick assessment — what is present

- Database schema and core tables for marketplace listings, vendor storefronts, collection metadata, cover images, and transaction logs exist in packages/ahg-marketplace/database/install.sql.
- Core services: MarketplaceService provides upload/CRUD for collections, cover uploads, and seller/vendor utilities (see packages/ahg-marketplace/src/Services/MarketplaceService.php).
- Controllers and views: browse, seller-dashboard, collection show/edit views are present in packages/ahg-marketplace/src/Controllers and packages/ahg-marketplace/resources/views/.
- Integration points: theme links from research outputs, export endpoints, and some API controllers connect marketplace items to export/metadata.

2) First look at gaps (concrete, repo-evidenced)

- Missing tests for critical flows
  - Evidence: few/zero PHPUnit feature tests under packages/ahg-marketplace/tests/Feature. Key flows (purchase flow, upload cover, collection publish/unpublish) lack automated coverage.
  - Risk: regressions on refactors; no CI safety net.

- Partial or missing upload validation and anti-virus scanning
  - Evidence: MarketplaceService::uploadCollectionCover references move() and isValid() (KB snippets) but there is no consistent middleware or service that scans uploaded files for malware or enforces image constraints at a single point.
  - Risk: unsafe files uploaded to public-facing storage, inconsistent UX on invalid file types.

- No clear billing / payment integration
  - Evidence: no payment gateway adapter in packages/ahg-marketplace (no payfast/stripe/paypal integration code found). Transaction logs exist but no payment provider implementations.
  - Risk: marketplace cannot process real purchases; ledger entries may be placeholders.

- Limited metadata quality controls for marketplace items
  - Evidence: collection metadata form lacks required metadata templates or schema enforcement (no JSON schema validation). Search may return low-quality or spam listings.
  - Risk: poor discoverability and trust.

- Missing seller onboarding and KYC flow
  - Evidence: seller creation flows exist (seller profile pages) but no KYC, document upload, or approval queue controller was found.
  - Risk: legal/compliance exposure if sellers are allowed to publish without vetting.

- No provenance linking to Research Ai / ai_provenance
  - Evidence: marketplace descriptions or AI-assisted metadata enrichment are not writing to ai_provenance; no flag marks an AI-suggested description in the collection record.
  - Risk: lack of auditability for AI-assisted content and possible undisclosed AI content.

3) Look at incomplete code (file pointers & exact gaps)

- packages/ahg-marketplace/src/Services/MarketplaceService.php
  - Observed methods: uploadCollectionCover(), createCollection(), publishCollection(). uploadCollectionCover has file move steps and extension checks but no central validation service call (e.g. ImageValidationService) and no anti-virus/scanning integration.

- packages/ahg-marketplace/resources/views/collections/upload_cover.blade.php
  - Evidence: the view posts to an upload endpoint but lacks client-side previews and size/type constraints.

- packages/ahg-marketplace/src/Controllers/SellerController.php
  - Evidence: seller onboarding pages exist but no admin-review endpoints (approveSeller, rejectSeller) and no KYC document store.

- packages/ahg-marketplace/src/Controllers/TransactionController.php
  - Evidence: transaction logging exists, but there is no PaymentGatewayAdapter interface or concrete implementation. The transaction lifecycle methods are half-implemented (createTransaction, markPaid) without provider callbacks.

- packages/ahg-marketplace/src/Services/MarketplaceSearchService.php
  - Evidence: basic search implemented; no per-field boost, no controlled-vocabulary integration (e.g. subjects, collection types), and no suggestion/fuzzy matching tuning.

4) Suggested enhancements (concrete, prioritised)

High priority (must have)
- Add a PaymentGatewayAdapter interface and a Stripe / test-provider implementation
  - Files: packages/ahg-marketplace/src/Contracts/PaymentGatewayInterface.php; packages/ahg-marketplace/src/Services/PaymentGateway/StripeGateway.php
  - Acceptance: ability to create a payment intent, confirm webhook, and mark the transaction as paid.

- Centralise file validation + malware scanning for uploads
  - Add ImageValidationService and VirusScanService; call them from MarketplaceService::uploadCollectionCover before moving files to permanent storage.
  - Integrate a sandbox / ClamAV adapter and fail-safe for scanned files (quarantine + admin notification).

- Seller onboarding + KYC queue
  - Add SellerApplication model + admin queue and docs upload endpoints. SellerController exposes POST /seller/apply and /admin/seller/approve.
  - Acceptance: seller cannot publish until approved by an admin.

- AI provenance + disclosure for metadata enrichment
  - On any AI-suggested metadata (auto-tagging, description enrichment), write an ai_provenance record and mark the collection record with metadata source: ai_suggestion:true, ai_provenance_id:<id>.
  - UI must show "Suggested by AI" and require explicit accept by the seller.

Medium priority (improve UX / reliability)
- Improve search relevance and add facets (subject, vendor, price, availability)
  - Implement search tuning in MarketplaceSearchService, add CSP indexes if using Elastic/Algolia.

- Add image preview and client-side validation on upload forms
  - Improve the upload UX; do not rely solely on server-side errors.

- Add invoices and seller payout records
  - Implement payout scheduling and exportable CSV for seller accounting.

Low priority (nice-to-have)
- Add marketplace analytics dashboard (top sellers, revenue per period, listing conversion)
- Add recommended related collections and similarity scoring for items
- Add marketplace-to-RiC export mapping for licensed items

5) Implementation notes & acceptance criteria

- Tests: add PHPUnit feature tests for cover upload (valid/invalid files), transaction flow (create intent → webhook simulate → mark paid), and seller onboarding (apply → admin approve → publish). Place tests under packages/ahg-marketplace/tests/Feature.

- Configuration: feature flags for real payments vs sandbox; ClamAV endpoint configurable via .env (CLAMAV_HOST, CLAMAV_PORT), payment env keys for Stripe, and marketplace fee percentage.

- Security: ensure uploaded files are stored outside webroot in the production storage disk or guarded by signed URLs; ensure that admin-only endpoints are behind proper policies.

6) Files to inspect for follow-up (start here)
- packages/ahg-marketplace/src/Services/MarketplaceService.php
- packages/ahg-marketplace/src/Controllers/SellerController.php
- packages/ahg-marketplace/src/Controllers/TransactionController.php
- packages/ahg-marketplace/resources/views/collections/upload_cover.blade.php
- packages/ahg-marketplace/src/Services/MarketplaceSearchService.php

7) Next steps (pick one)
1. Implement PaymentGatewayAdapter + Stripe test-provider (PR A).  
2. Implement ImageValidationService + VirusScanService and call from upload flows (PR B).  
3. Implement SellerApplication + KYC review queue and admin endpoints (PR C).  
4. Add AI provenance wiring for metadata enrichment (PR D).  

Status: very good

