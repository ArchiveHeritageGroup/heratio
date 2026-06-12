# Preservation health report

The preservation health report (Administration, `/admin/preservation-health`) is a read-only operator dashboard showing the operational state of the digital collection's integrity. It surfaces what needs attention - it makes no changes to any record or file.

It is distinct from its report siblings: the data-quality report measures how completely records are described, the AI-usage report measures where AI assisted, and the catalogue-growth report measures size, growth and composition. This report measures preservation integrity.

It reads the same canonical preservation stores that the dedicated preservation surfaces (the fixity dashboard, the format and virus-scan logs, the event log) read, so the numbers agree. It writes to none of them.

## What it shows

- **Headline** - the number of digital objects, the count that needs attention (failed fixity, missing files and flagged virus scans combined), and the fixity pass rate of the objects that have been checked.
- **Integrity (fixity checks)** - the latest fixity result per object: how many passed, how many failed, and how many have never been checked. A pass means the stored checksum still matches the file; a failure means it does not and the file may have changed or been damaged.
- **Format identification** - how many digital objects carry a recorded file format (a PUID or a format name) versus how many have none yet. Identified formats let preservation planning spot obsolescence risk.
- **Missing files** - objects whose file could not be found when last looked for, recorded as a "file missing" preservation event, with a short sample list of the most recent ones to follow up.
- **Virus scan** - the latest virus-scan result per object: clean versus flagged. "Flagged" includes any object where a threat was named or where the most recent scan could not confirm the file is clean (so it warrants a re-scan). This card appears only when virus-scan data exists.
- **Recent preservation failures and warnings** - the most recent preservation events whose outcome was a failure or a warning, newest first, each with its type, when it happened, the affected digital object, and a short detail.

## How to use it

Use it as a daily operational check on the digital collection. The "needs attention" figure and the recent failures list tell you where to look first. Open the linked fixity dashboard, format log, virus-scan log or event log to act on a specific object - this report points; it does not change anything itself.

## Notes

- The report is read-only and never edits a record or a file.
- It is built from cheap aggregate counts plus one short recent list, so it is safe to open on a large collection.
- It is jurisdiction-neutral and makes no country-specific assumptions.
- With no digital objects yet it shows a calm "No digital collection yet" state; with digital objects but no preservation activity it shows "No preservation data yet" rather than an error.
- The links to the fixity dashboard, the preservation maturity assessment, the format and virus-scan logs and the event log appear only when those surfaces are installed.
