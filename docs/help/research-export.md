# Export your research project to open formats

> No lock-in. The exit door is always open.

Heratio Research OS lets you download a faithful, full-fidelity copy of any
research project at any time, in open, non-proprietary formats. Nothing about
your work is trapped in the platform.

## Where to find it

Open a project and choose **Export** (`/research/projects/{id}/export`). The
export page shows exactly what the bundle will contain and offers one-click
downloads.

## What is included

The export gathers your whole project record:

- **Project overview** - title, type, status, institution, supervisor, funding,
  ethics approval and dates.
- **Research Design Brief** - every immutable version, with the reason for each
  change.
- **Claims, evidence and Claim Ledger** - each claim with its evidence links and
  the full Claim Ledger meta (confidence, provenance, supporting/opposing
  sources, quotations, weaknesses, ethical concerns).
- **Decision Log** - every recorded decision and its reason, in order.
- **Argument scaffold** - your central thesis and ordered argument steps.
- **Method protocol** - your chosen method template and your answers per area.
- **Research Memory** - the items you captured for carry-forward.
- **Sources** - every bibliography entry, exported as citation files.

If a part of the system is not installed, that section is simply left out and the
omission is noted in the bundle's `manifest.json`. The rest of the export is
always complete.

## The formats

| File | Format | What it is |
|---|---|---|
| `project.md` | Markdown | The whole project, human-readable. |
| `project.json` | JSON | The same data, machine-readable. |
| `sources.bib` | BibTeX | Your sources, for LaTeX / reference managers. |
| `sources.ris` | RIS | Your sources, for EndNote / Zotero / Mendeley. |
| `sources.json` | CSL-JSON | Your sources, for any CSL-aware tool. |
| `manifest.json` | JSON | What was included, what was omitted, and counts. |
| `README.md` | Markdown | A short guide to the bundle. |

## How to download

- **Everything as one ZIP** - the recommended one-click option. Bundles all of
  the files above.
- **Any single format** - download just the Markdown, JSON, BibTeX, RIS or
  CSL-JSON on its own.

## Who can export

The project owner, its collaborators, and administrators. The export is
read-only: producing it never changes your project in any way.
