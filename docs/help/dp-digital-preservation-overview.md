> Heratio Help Center article. Category: Digital Preservation.

# Digital preservation in Heratio: an overview

## What this is

Digital preservation is the set of activities that keep your digital files
usable, authentic, and trustworthy for decades - long after the original
software, file formats, and storage media have changed. This article explains the
core ideas in plain language and points to where each one lives in Heratio.

## The key ideas

- **OAIS** is the international reference model (ISO 14721) for how an archive
  takes in, stores, and gives access to digital content. It gives us the language
  of "information packages": what you receive (a SIP), what you keep (an AIP), and
  what you hand out (a DIP).
- **Fixity** means a file is unchanged, bit for bit, over time. Heratio proves
  this with checksums - digital fingerprints it computes on ingest and rechecks
  later. A mismatch means corruption or tampering.
- **Format identification** means knowing exactly what kind of file you hold
  (using the international PRONOM registry and PUIDs), so you can spot formats at
  risk of becoming unreadable and migrate them in good time.
- **Preservation metadata (PREMIS)** records everything that happens to a file -
  every fixity check, virus scan, format identification, and migration - so the
  history is auditable.
- **Packaging (BagIt and METS)** bundles files with their checksums and metadata
  so they can move and be stored safely.
- **Multiple copies** in separate places, verified regularly, protect against
  loss; Heratio can replicate to several targets and repair a damaged file from a
  good copy.

## Where to find it in Heratio

| Task | Where |
| --- | --- |
| Preservation dashboard | Admin -> Digital Preservation (`/admin/preservation`) |
| Fixity / checksum log | `/admin/preservation/fixity-log` |
| Format identification (PRONOM) | `/admin/preservation/identification` |
| Format registry / at-risk formats | `/admin/preservation/formats` |
| PREMIS events (full history) | `/admin/preservation/events` |
| OAIS packages (SIP/AIP/DIP) | `/admin/preservation/packages` |
| Backup / replication | `/admin/preservation/backup` |
| Maturity self-assessment (NDSA) | `/admin/preservation-maturity` |

## See also

- How fixity and checksums work (help: "Fixity and checksums")
- Format identification with PRONOM (help: "Format identification")
- OAIS packages: SIP, AIP, DIP (help: "OAIS packages")
- Your preservation maturity score (help: "Preservation Maturity Self-Assessment")
