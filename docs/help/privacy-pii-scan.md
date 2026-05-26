# PII Scan

Heratio includes a built-in personal-data scanner that examines free-text fields in archival descriptions for emails, phone numbers, national identifiers, credit-card numbers, IP addresses and dates of birth. The scanner is pattern-based - it runs entirely on the host with no external AI calls - so it is safe to use against sensitive content that may not leave your jurisdiction.

## Running a scan

You can invoke a scan on a single information_object from the CLI:

```
php artisan privacy:scan-io 12345
```

Add `--no-persist` to print findings to the terminal without writing a scan-report row. Use `--jurisdiction=` (one of `gdpr`, `popia`, `uk_gdpr`, `ccpa`) to override the configured default for one-off jurisdictional checks.

Each persisted scan writes a row in the `ahg_pii_scan_report` table. The row captures total hits, a count per type, and the full finding list (capped at 500 entries) as JSON.

## Reading the result

A finding is a tuple of `type`, `value`, `offset_start`, `offset_end` and `confidence`. Confidence between 0 and 1 reflects how strong the signal is:

- ~0.9-0.95 for emails, well-formed SSNs and Luhn-validated credit cards
- ~0.7-0.85 for E.164 phone numbers and IPs
- ~0.55 for free-form dates of birth (further bounded by a 1900-current-year sanity window)
- Lower values when a regex matches but a checksum (Luhn) fails - useful at review time as a soft signal rather than a hard hit

Open a finding and decide one of four outcomes:

- `pending` (default) - newly scanned, not yet reviewed
- `reviewed` - the privacy officer has examined the findings
- `redacted` - the underlying record has been redacted to remove the PII
- `accepted_risk` - PII is intentional (donor agreement, archival context) and remains

## Privacy jurisdiction

The configured jurisdiction is stored in `ahg_setting.privacy_jurisdiction`. Set it to your home market to favour locally-relevant regex sets (e.g. SA ID numbers under POPIA, NINOs under UK GDPR, SSNs under CCPA). When set to `gdpr`, the scanner uses a maximum-recall union of all national-id and phone patterns - useful for institutions that hold international holdings.

## What the scanner does not do

The Phase 1 scanner does not OCR images, does not analyse PDF or TIFF binary content, and does not produce redaction artefacts. Image and document redaction is handled by the visual redaction editor (separate Phase 1 deliverable). Auto-deletion + subject rights portal arrive in a later phase.
