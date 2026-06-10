# Truth Anchor: public verify-authenticity page (issue #1209)

The public "verify authenticity" page is the trust-anchor surface for Heratio:
a read-only, no-auth page that, given an information object, shows its
authenticity chain and the live cryptographic verification result. It is the
first slice of the #1209 north-star vision (a verifiable authenticity chain for
primary sources in a deepfake era). It builds directly on the C2PA provenance
layer shipped in #1201.

## What it does

For a record, the page renders:

- The record's public identity (title, reference code, permalink).
- An overall plain-language verdict: "Authenticity verified" / "Could not be
  verified" / "Documented, not signed" / "No provenance on record".
- One card per digitisation-provenance record, each showing who digitised it,
  when, on what device and software, the content fingerprint (SHA-256), any AI
  processing steps, and the per-record verification badge
  (verified / unsigned / could not be verified).

Verification is performed live on every request by re-hashing each assertion
and re-checking the Ed25519 claim signature. Nothing is cached. If a record has
no provenance at all, the page says so plainly (absence of credentials is not
evidence of forgery).

## Routes

All under `/verify` (multi-segment, so the single-segment IO slug catch-all
`/{slug}` never intercepts them). Public, no auth middleware.

| Route | Name | Notes |
|---|---|---|
| `GET /verify/id/{informationObjectId}` | `c2pa.verify.id` | numeric id; namespaced under `/id/` so a numeric slug cannot collide |
| `GET /verify/{slug}` | `c2pa.verify.slug` | slug, `where('slug', '.+')` supports multi-segment slugs |

## Code (package: ahg-c2pa)

- `src/Controllers/VerifyController.php` - `bySlug` / `byId`; resolves the IO,
  walks `ProvenanceRecordService::listForObject` + `verifyRecord`, builds the
  chain + a summary (total / signed / verified / tampered).
- `resources/views/verify/show.blade.php` - plain-language public view
  (`theme::layouts.1col`).
- `routes/web.php` - public `/verify` group added above the existing
  `/admin/c2pa` admin group.

It reuses the existing service end to end; signing and verification logic live
in `ProvenanceRecordService` + `C2paService` (not reimplemented). Ed25519 keys
resolve via `ai_inference_key` (shared with the EU AI Act / #61 inference
chain) or the on-disk `storage/keys/inference-signing.pk`.

## Verification states

| status | meaning shown to the public |
|---|---|
| `verified` | assertions re-hash to the signed claim and the Ed25519 signature checks out - not tampered |
| `unsigned` | record documents the digitisation but has no signed manifest |
| `failed` | signature or assertion hash does not match - treat with caution |
| `not-found` / `corrupt` / `manifest-missing` | rendered as "could not be verified" with the underlying error |

## Smoke test (verified 2026-06-10)

Created a provenance record for a real IO via `ProvenanceRecordService::record`,
verified it (`verified`, ok), then corrupted the bound manifest_json and
re-verified (`failed`, not ok), then deleted the test rows. Public page returns
HTTP 200 for id, slug, and an unknown slug (renders "Not found").
