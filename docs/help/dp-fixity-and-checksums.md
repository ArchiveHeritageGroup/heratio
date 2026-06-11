> Heratio Help Center article. Category: Digital Preservation.

# Fixity and checksums

## What is fixity?

Fixity is the assurance that a file has not changed - not by a single bit - since
it entered your archive. It is the most basic integrity control in digital
preservation: it lets you *prove* that the file you hold today is exactly the file
you received.

## How Heratio checks it

Heratio computes a **checksum** (a cryptographic fingerprint, by default SHA-256)
for each digital file. The fingerprint is stored. Later, Heratio recomputes it and
compares:

- **Match** - the file is intact.
- **Mismatch or missing** - the file has been corrupted, truncated, or tampered
  with, and is flagged for investigation.

Storage media degrade silently over time ("bit rot"), so regular rechecking is
essential.

## Doing it in Heratio

- **Generate a checksum** for a record from its preservation view, or via the
  preservation dashboard.
- **Verify a single record** with the "Verify fixity" action on
  `/admin/preservation/object/{id}`.
- **Verify in bulk** on a schedule: Heratio runs due fixity checks automatically
  (set up under `/admin/preservation/scheduler`), and an administrator can run a
  sweep that targets files not checked recently.
- **Review results** in the fixity log at `/admin/preservation/fixity-log`. Every
  check is also recorded as a PREMIS event under `/admin/preservation/events`.

## Self-healing

If you have configured backup / replication targets, a failed fixity check can
trigger automatic repair: Heratio finds a copy on a replication target, verifies
that copy against the stored checksum, restores the damaged file, and logs the
repair. Without replicas, a failure can still be *detected* and logged, but not
automatically *healed* - so keeping verified copies matters.

## Choosing an algorithm

| Algorithm | Use |
| --- | --- |
| SHA-256 | Recommended default - good security and speed |
| SHA-512 | High-security archives |
| MD5 / SHA-1 | Legacy compatibility or quick checks only |

## See also

- Digital preservation overview (help)
- OAIS packages: SIP, AIP, DIP (help)
- Your preservation maturity score (help: "Preservation Maturity Self-Assessment")
