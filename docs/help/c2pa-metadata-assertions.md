> Heratio Help Center article. Category: AI & Compliance.

# C2PA Standard Metadata Assertions

## What this adds to your C2PA manifests

When Heratio signs a digital object with a C2PA 2.1 provenance manifest,
it now also carries the embedded image metadata (EXIF, IPTC, XMP) inside
the manifest itself. That means if the file is later migrated to a new
format, the metadata still travels with the provenance record.

Three new assertion blocks appear in the manifest:

- **stds.exif** - camera Make / Model, image dimensions, capture date,
  GPS coordinates, software, copyright string, image description.
- **stds.iptc** - By-line, Headline, Caption, Keywords, City/Country,
  Credit, Source, IPTC subject codes.
- **stds.xmp** - Dublin Core view of the same data (dc:creator, dc:title,
  dc:subject, dc:rights) plus XMP rights flags.

You don't need to do anything to opt in - if a digital object has rows
in `digital_object_metadata`, `media_metadata`, or `dam_iptc_metadata`,
the assertions appear automatically on the next manifest issuance.

## Privacy: GPS redaction

Heratio is opinionated about embedded location data. When the embedded
PII scanner (issue #751) flags an asset's GPS coordinates as a pending
or escalated PII finding, the GPS keys are stripped from the
**stds.exif** assertion before it is signed, and a marker
`_pii_redacted: true` is added to the assertion so verifiers can see the
redaction was intentional.

The original GPS data is **not** rewritten in the source file - the
redaction only affects what is carried inside the C2PA manifest. To
restore GPS into the manifest, mark the PII finding as **Cleared (not
PII)** in the Embedded Findings dashboard; the next manifest re-issue
will include the coordinates.

Other PII types (person names, contact info, sensitive dates) don't gate
the C2PA manifest at present - they're surfaced through the normal PII
workflow.

## Verifying a manifest

`C2paService::verify($signedManifest, $publicKeyResolver)` round-trips
every assertion: it re-canonicalises and re-hashes each, matches against
the claim's pinned hash, then verifies the Ed25519 claim signature.

A passing `verify()` confirms the manifest has not been altered since
signing, which transitively confirms the embedded EXIF / IPTC / XMP
subset is exactly what Heratio attested to.

If a verifier doesn't understand one of the new labels, it ignores it -
the C2PA 2.1 spec requires forward-compatible parsing. Unknown
assertions don't break older verifiers.

## Common questions

**Q: Will this make my manifests larger?**
Yes, but typically by a few hundred bytes per assertion. The three
assertion blocks together usually add 1-3 KB to the manifest JSON.

**Q: What if my digital object has no embedded metadata?**
Then the loader returns empty arrays for all three and no assertions
are emitted. The manifest still issues, just with fewer assertion
references in the claim.

**Q: Does the AI-suggestion path also emit these?**
Yes, when the AI run is anchored to a specific digital object (HTR
output for a scanned page, NER over a specific document image, etc.).
Free-text AI outputs that have no underlying file skip the stds.* block
because there's no file to extract metadata from.

**Q: How do I disable this for a specific asset?**
There is no per-asset opt-out today. If you need one, raise an issue -
the loader is injectable so a "redact-all-metadata" variant is a
small change.

**Q: Where in the codebase does this live?**
`packages/ahg-c2pa/` - specifically `Manifest/StandardMetadataLoader.php`
and `Manifest/Assertion.php`. The Ed25519 signing key is shared with
inference receipts (issue #693) and the broader C2PA scaffold (#676).

## Compliance / standards alignment

- C2PA 2.1 specification, Standard Metadata Assertions section
- IPTC IIM Photo Metadata standard
- XMP / Dublin Core (W3C)
- Exif 2.32 (CIPA DC-008)

These assertions improve preservation-chain coverage for the EU AI Act
Article 12 evidence trail and for archival standards like ISO 16363
(Trusted Digital Repositories) where embedded technical metadata must
survive format migrations with attestation.
