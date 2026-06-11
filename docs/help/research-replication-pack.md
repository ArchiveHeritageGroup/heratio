# Replication Pack

The Replication Pack assembles, in one click, everything an external reader needs to understand and replicate your study. It is built read-only from records you have already captured elsewhere in the research portal - nothing is re-derived or recomputed.

Open it from a project: **Research -> your project -> Replication Pack** (`/research/projects/{id}/replication`).

## What goes into a pack

| Section | Source | What it contributes |
|---|---|---|
| Method Protocol | Method Studio | The research method as recorded for the project. |
| Analysis results + provenance | Analysis Bridge | Each registered result with its source data and version, method, code reference, the researcher's decision, and the project claims it supports, weakens or contextualises. |
| Decision Log | Decision Log | The reasoning trail - every scope change, exclusion, pivot and reformulation (the "why"). Included as JSON and CSV. |
| Claims + evidence | Claim Ledger | The project claims with their supporting and refuting evidence rows. |
| Data + code references | Analysis Bridge provenance | Pointers (paths, repository URLs, versions) to the underlying data and code. The files themselves are referenced only, never bundled. |

Each section is independent. If a section has nothing recorded, or its underlying feature is not installed on your instance, it is listed in the manifest under **omitted** with the reason rather than failing the build.

## Building and downloading

1. Open the Replication Pack page for the project. It shows a summary of what the pack will contain and a count for each section.
2. Click **Build & download**. The portal assembles the bundle and streams a ZIP to your browser.
3. The ZIP contains:
   - `README.md` - a plain-language overview of what is included and what is withheld.
   - `manifest.json` - the machine-readable index (included / omitted / ethics).
   - `method-protocol.json`, `analysis-results.json`, `decision-log.json` and `.csv`, `claims-and-evidence.json`, `data-code-references.json` - the section data.

Each build is fresh, so the pack always reflects the current state of the project.

## Ethics and access

The pack bundles metadata, provenance and the reasoning trail of the study. It does **not** include the underlying data files or code bytes - those are referenced by path or repository only.

Restricted, embargoed, personal or consent-limited material is intentionally withheld and listed under **omitted** in the manifest. Request such material through the channel named in the relevant data/code reference, and share it only where your project's ethics approval and the data subjects' consent permit.

Heratio is jurisdiction-neutral. Apply your own institution's ethics, data-protection and consent regime before redistributing any referenced material.
