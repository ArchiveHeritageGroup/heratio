# Research Outputs register

The Research Outputs register records the scholarly outputs a research project produces - journal articles, datasets, software, presentations, theses, reports and book chapters - so a project's full footprint is captured in one place and can be reported, shared and preserved. It is part of the Research Operating System and appears on the researcher journey alongside the Data Management Plan and Grant Engine.

## What it is for

Funders, institutions and researchers increasingly need a single, machine-readable list of what a project produced and where each item lives. The register is a lightweight CRIS/RIM (research information management) surface: every output carries a resolvable identifier, so a reader can follow it straight to the published item.

## Recording an output

Open a research project and choose Outputs. For each output you capture:

- Type - journal article, dataset, software, presentation, thesis, report, chapter or other.
- Title, authors and venue (the journal, repository, conference or publisher).
- Identifier - a DOI, handle, ISBN or URL. The register turns this into a resolvable link automatically: a DOI becomes `https://doi.org/...`, a handle becomes `https://hdl.handle.net/...`, an ISBN links to a catalogue search, and a URL is used as given. You can also set an explicit link that overrides the derived one.
- Output date and status (planned, in progress, or published).
- Optional notes or abstract.
- An optional link to the project's Data Management Plan, so the data behind a publication is connected to the publication itself.

All of the type, identifier-type and status choices are drawn from the Dropdown Manager, so an administrator can extend them without code changes.

## Per-project summary

The Outputs index shows a running total plus counts by type and by status, giving an at-a-glance picture of what the project has produced and what is still planned.

## Machine-readable export

Each project exposes a JSON export of its outputs (type, title, identifier, resolvable URL and date). This lets the list be reused in annual reports, funder returns, or an institutional repository without re-keying.

## Notes

- Outputs are scoped to a project and to the researcher; you see and edit the outputs of projects you belong to.
- The DOI and other identifiers are rendered as links only - the register does not call any external service.
- The register is international and funder-neutral; no jurisdiction-specific assumptions are made.
