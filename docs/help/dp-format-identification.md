> Heratio Help Center article. Category: Digital Preservation.

# Format identification (PRONOM)

## Why identify formats?

You cannot protect against a format becoming unreadable if you do not know which
formats you hold. Format identification tells you exactly what each file is - not
by trusting its extension, which can be wrong or disguised, but by reading the
file's internal byte signature.

## PRONOM and PUIDs

Heratio identifies formats against **PRONOM**, the international technical format
registry maintained by The National Archives (UK). Each format has a **PUID**
(PRONOM Unique Identifier), for example:

- `fmt/353` - TIFF
- `fmt/276` - PDF 1.7
- `fmt/43` - JPEG

A PUID pins the exact format and version, which is what lets you judge
obsolescence risk and plan migration.

## How Heratio does it

Heratio uses **Siegfried**, a PRONOM signature tool (the same family as DROID),
to identify files by their byte signatures and record the PUID, format name, and
version. Identification can run:

- **at ingest**, as a per-file step (enable "Format identification" in ingest
  settings), and
- **on demand or on a schedule**, sweeping records that have not been identified.

## Doing it in Heratio

- View identification coverage and confidence at
  `/admin/preservation/identification`.
- View the **format registry** - every format you hold with its preservation risk
  level - at `/admin/preservation/formats`. Low-risk formats (open, well
  supported) need no action; high or critical-risk formats are candidates for
  migration.

## What identification feeds

- **Risk assessment and migration planning** - at-risk formats are flagged so you
  can convert them to stable preservation formats (see the conversion view).
- **Your maturity score** - PUID coverage and a monitored format registry raise
  your "Content / file formats" level in the preservation maturity assessment.

## Note

Identification needs the Siegfried tool installed on your server. If it is not
present, Heratio falls back to basic MIME-type information only, and your PUID
coverage will be low until Siegfried is available.

## See also

- Digital preservation overview (help)
- Fixity and checksums (help)
- Your preservation maturity score (help: "Preservation Maturity Self-Assessment")
