# Embedded image metadata PII findings

Photographs, scans, and audio/video files often carry hidden metadata that contains personal information about the people who created or are depicted in them. Heratio scans this metadata automatically and surfaces anything that looks like PII for your review.

## What gets scanned

When a digital file is ingested or its metadata is re-extracted, Heratio reads:

- **EXIF data** - GPS coordinates of where the photo was taken, the camera's date/time stamp, the photographer's name.
- **IPTC IIM and IPTC Core** - the "By-line" (creator), "Contact" block (creator email, phone, address, city, state, country, postal code, website, job title).
- **XMP** - `dc:creator`, `Iptc4xmpCore:CreatorContactInfo` (same contact details), `exif:GPSLatitude` / `exif:GPSLongitude`.
- **Audio / video metadata** - artist names, GPS coordinates embedded by some cameras and phones.

Any of these that look like personal information are flagged.

## Categories Heratio detects

| Category               | Examples                                              |
|------------------------|-------------------------------------------------------|
| GPS coordinate         | A photograph's exact latitude / longitude / altitude. |
| Person name            | "By-line" creator, XMP `dc:creator`, EXIF Artist.     |
| Person contact info    | Email, phone, postal address, website of the creator. |
| Sensitive date         | Date the image was taken (when paired with a person). |

## Reviewing findings

Open **Admin > Privacy > Embedded PII Findings** (`/admin/privacy/embedded-findings`). The page shows:

- Summary cards at the top, one per category, with pending + resolved counts.
- A filter bar - narrow by category, narrow by resolution status.
- One row per finding, showing the digital object, source table, source field, the matched value, the confidence score, and the date the scan ran.

Click **Resolve** next to any finding to mark it as:

- **Pending review** - the default. Findings sit here until someone reviews them.
- **Redacted** - you've stripped the metadata from the file (or arranged for it to be stripped).
- **Cleared (not PII)** - false positive. Confidence wasn't a hit; the value is fine to keep.
- **Escalated to DPO** - you need a Data Protection Officer to decide. Keep the finding in the queue and add a note explaining why.

Each resolution captures who acted on it and when, so the audit trail is intact.

## Re-running the scan

Findings auto-generate on ingest. If you've imported objects that pre-date the scanner, run:

```
php artisan ahg:privacy:scan-embedded-backfill
```

Or against a single file:

```
php artisan ahg:privacy:scan-embedded-backfill --digital-object-id=123
```

The command is idempotent. Running it twice on the same files won't create duplicate findings.

## Why this matters

Embedded metadata is one of the most common ways institutions accidentally leak personal data. A donor photograph's EXIF GPS pins their home address. An IPTC By-line names a freelance photographer with their personal phone number. An XMP CreatorContactInfo block carries a school teacher's home email.

Surfacing these so they can be reviewed - and either redacted or cleared - is a baseline obligation under GDPR, POPIA, CCPA, CDPA, LGPD, and every other modern data protection regime.
