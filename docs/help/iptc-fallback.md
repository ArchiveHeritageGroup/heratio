# IPTC fallback in metadata exports

When you upload a TIFF, JPEG, or PSD that carries IPTC headers (the
embedded metadata fields written by Photoshop, Lightroom, ExifTool, or
most camera firmware), Heratio extracts the headers into the
`dam_iptc_metadata` table the first time the digital object is processed.
The three fields the export pipeline cares about are:

- **By-line** - the photographer or creator name.
- **Copyright Notice** - the rights statement.
- **Keywords** - one or more subject terms.

Historically the export endpoints (OAI-PMH, Dublin Core, EAD) only looked
at the ISAD(G) fields you filled in by hand. If the archival description
had an empty Author / Rights / Keywords field but the file carried rich
IPTC headers, harvesters saw an empty record.

From this release the exporters fall through to the IPTC value when the
matching ISAD field is empty. The ISAD field always wins when it's set -
the fallback never overrides anything you typed.

## What changes for harvesters

| Predicate          | Was (ISAD empty)              | Now (ISAD empty + IPTC present)   |
| ------------------ | ----------------------------- | --------------------------------- |
| `dc:creator`       | element omitted               | IPTC By-line                      |
| `dc:rights`        | element omitted               | IPTC Copyright Notice             |
| `dc:subject`       | element omitted               | one element per IPTC keyword      |
| EAD `<origination>`| element omitted               | IPTC By-line in `<name>` wrapper  |
| EAD `<userestrict>`| element omitted               | IPTC Copyright Notice in `<p>`    |
| EAD `<controlaccess><subject>` | element omitted    | one `<subject>` per keyword       |

## How to spot a fallback

Every time the export pipeline falls through to an IPTC value it writes
an `info`-level row to the System Log (Settings > Logs). Filter the log
for `IPTC fallback fired` to see which descriptions are leaning on the
extracted headers rather than your edited ISAD fields.

Once you see a row you usually want to do one of two things:

1. **Promote the IPTC value into ISAD.** Open the description in the
   editor, copy the value from the IPTC tab into the canonical ISAD field
   (Author / Reproduction Conditions / Subject Access Points), and save.
   The next harvest emits the same value from the canonical source and
   the fallback goes quiet for that record.
2. **Override the IPTC value.** Edit the ISAD field to whatever the
   correct authoritative value should be. The fallback only fires when
   the ISAD field is empty, so any non-empty value overrides the IPTC.

## What gets emitted

The fallback feeds:

- OAI-PMH `oai_dc` (the `Identify`-backed harvester surface).
- OAI-PMH `oai_ead` and `oai_ead3` crosswalks.
- The Dublin Core / Dublin Core Qualified XML download from the
  Metadata Export dashboard.
- The EAD 2002 / EAD 3 XML download.
- The bulk CSV / EAD export jobs from the Export dashboard.

## Edge cases

- **Multi-valued IPTC Keywords** are stored as either a JSON array (the
  default ExifTool output) or a comma / semicolon / pipe-delimited
  string. Heratio accepts both formats.
- **Malformed IPTC blobs** are silently ignored - a corrupt payload can
  never poison an OAI harvest.
- **Repositories with thousands of IPTC-only records** will see one
  audit row per record per field per export pass. The resolver dedupes
  inside a single request, so a `ListRecords` page doesn't multiply rows.
