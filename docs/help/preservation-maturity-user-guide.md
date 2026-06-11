> Heratio Help Center article. Category: Digital Preservation.

# Preservation Maturity Self-Assessment

## A Guide for Repository Administrators

---

## What is the Preservation Maturity dashboard?

The Preservation Maturity dashboard scores your repository, from concrete
evidence in its own records, against the five functional areas of the
**NDSA Levels of Digital Preservation**. It tells you, at a glance, how mature
your digital preservation practice is and what to do next.

It lives at **Admin -> Preservation maturity** (`/admin/preservation-maturity`)
and is available to administrators only. It is read-only: it never changes a
record and runs no background jobs.

---

## The NDSA Levels

The NDSA (National Digital Stewardship Alliance) Levels of Digital Preservation
are a widely used, jurisdiction-neutral self-assessment grid. They group
preservation practice into five functional areas, each graded from Level 1
(know your content) through Level 4 (repair your content). This dashboard adds
a "Not yet" grade for an area where the repository holds no qualifying evidence
at all, so an empty instance reads honestly rather than claiming a phantom
Level 1.

The five areas scored:

1. **Storage and geographic location** - multiple copies of every file, held in
   geographically separate locations and across different storage providers or
   systems, with managed replication.
2. **Integrity (fixity and write protection)** - cryptographic checksums
   recorded for every file, verified on a cadence, with content protected from
   accidental change or deletion, and corrupted content repaired from a
   known-good copy.
3. **Information security and access control** - explicit control over who can
   read or change content, with an audit trail of who did what, and regular
   review of those logs.
4. **Metadata** - descriptive, administrative, technical, and standard
   preservation (PREMIS) metadata held for content.
5. **Content and file formats** - knowing what formats you hold, identifying
   them to the PRONOM standard (PUID), and monitoring formats for obsolescence
   so at-risk content can be migrated.

---

## How the scoring works

The score for each area is **evidence-based and deliberately conservative**.
The dashboard only ever counts what the platform actually tracks:

- **Storage** reads your configured replication targets and how diverse they
  are (local, SFTP, S3, and so on), plus whether replication has actually run.
- **Integrity** reads recorded checksums (including the algorithm used),
  whether fixity verification has been run, and whether retention policies or
  legal holds protect content from change.
- **Information security** reads your permission groups, security
  classifications, and the audit logs for change actions, read access, and
  authentication.
- **Metadata** reads descriptive titles, administrative and provenance events,
  technical metadata captured for digital files, and PREMIS preservation
  events.
- **File formats** reads recorded MIME types, PRONOM (PUID) identifications,
  the diversity of formats held, and a monitored format risk registry.

Where the platform holds no evidence for a practice, the area is graded lower
and a clear recommendation is shown. Absence is never inflated into a higher
score.

The **overall maturity** is the lowest level achieved across the five areas - a
preservation programme is only as strong as its weakest area.

---

## Reading the dashboard

- The summary card at the top shows your overall level and the framework note.
- Each of the five areas has its own card with a big level badge, a progress
  bar, an **Evidence** line (what was found), and a **Next step** line (the gap
  to close to reach the next level).

Work through the "Next step" recommendations area by area to raise your scores.

---

## Frequently asked questions

**Does this change any records?**
No. The dashboard is entirely read-only.

**Why does an area say "Not yet"?**
Because the repository holds no qualifying evidence for that area yet (for
example, no checksums recorded, or no format identification performed). Follow
the "Next step" recommendation to establish Level 1.

**Is this specific to one country's regulations?**
No. The NDSA Levels are a generic, jurisdiction-neutral framework. The
dashboard uses them without any country-specific framing.
