# Heratio Research Enhancements — Roadmap

> **Scope.** This document plans NotebookLM-Studio-style additions and audit
> gaps for Heratio's research surface. Workbench-side (`ai.theahg.co.za`)
> features are tracked separately in the workbench repo. Heratio consumes
> workbench's AI gateway via `ahg-ai-services` — no provider keys live in
> Heratio.
>
> _Last updated: 2026-05-16._

---

## Audit baseline (2026-05-16) — what's already shipped

These packages already implement most of the research surface — the plan
below deliberately omits items that overlap with what's done.

| Package | Status |
|---|---|
| `ahg-research` — projects, hypotheses, evidence sets, snapshots, journal, bibliographies, citation tool (`cite.blade.php`), report generator, finding-aid generator, knowledge graph, network graph, timeline builder, map builder | ✅ |
| `ahg-researcher-manage` — registration, profiles, researcher types, reading-room bookings, seat assignment, equipment booking, walk-ins | ✅ |
| `ahg-search` — Elasticsearch full-text + faceted nav + autocomplete + saved searches + history + global S&R | ✅ |
| `ahg-semantic-search` — thesaurus, query expansion, bidirectional synonyms, search templates, semantic weights, AtoM term sync | ✅ |
| `ahg-ai-services` — NER, LLM, HTR, DONUT, translation, summarisation, spellcheck, condition scanning | ✅ |
| `ahg-annotations` — annotation studio, research-scoped annotations | ✅ |

---

## §1. NotebookLM-Studio additions (on top of `ahg-research`)

Inspiration: NotebookLM's "drop sources → pick output type → grounded answer
with clickable citations" workflow — see
[xda-developers article (2026-05-16)](https://www.xda-developers.com/notebooklm-solved-my-research-paralysis-in-a-way-no-other-productivity-tool-could/).

| Capability | What it gives the user | Effort | Done |
|---|---|---|:---:|
| **Studio pane on the Research-Project show route** — drop sources from the existing project (evidence-set items, attached PDFs, finding aids, donor agreements), pick output type (Briefing / Study Guide / FAQ / Timeline / Diagram / Video Script / Spreadsheet / Audio Overview), generate. Outputs land as project artefacts with `source_object_ids` provenance. Calls workbench AI gateway via `ahg-ai-services`. | Reuses the workbench doc-generator pipeline; archivists stop hand-writing summaries and research guides. | 2 wk | ⬜ |
| **AI-filled spreadsheet artifact** — JSON-doc shape (`{header, intro, columns, rows}`) AI-populated from archive metadata. Examples: "items by donor by decade", "deposits by series with linear metres", "objects with rights-review due". Exports to .xlsx via workbench `xlsxBuilder`. Distinct from the existing report generator — AI synthesis rather than SQL dumps. | SQL-dump + hand-clean today; AI synthesises directly off `information_object` joins. | 4 days | ⬜ |
| **Audio Overview of a fonds / research project** — two-voice podcast walkthrough via workbench `audioGenerator`. F5-TTS cloned voice optional (`f5:<voice_id>`). | Pure addition — projects produce finding aids and reports, never audio. | 4 days | ⬜ |
| **Citation hover popovers** — augment `cite.blade.php`: hover any `[N]` to see source title + snippet + scroll-to-source + "Open URL ↗". | Researchers verify sources without re-opening tabs. | 2 days | ⬜ |
| **Researcher private notebooks** — saved queries + AI outputs + pinned source items in a researcher-owned workspace, separate from public research projects. Notebooks can be promoted to a public research guide on click. | Existing research projects are first-class artefacts; notebooks are the ephemeral scratchpad NotebookLM models well. | 1 wk | ⬜ |
| **Cross-fonds reasoning queries** — query planner spanning multiple fonds: "every mention of the 1972 strike across the Mogalakwena, NaLHISA and PSIS deposits". Pairs with workbench Federated KM. | Existing semantic search expands a single query within one corpus; this returns ranked passages across deposits in one shot, with citations. | 1 wk | ⬜ |

---

## §2. Audit gaps — citation export, analytics, collaboration, identity, API, mobile

| Capability | What it gives the user | Effort | Done |
|---|---|---|:---:|
| **`CitationService` export formats** — `toRis()` / `toBibTeX()` / `toEndNote()` / `toApa()` / `toMla()` / `toChicago()` on top of the existing `cite.blade.php`. UI: "Copy in citation manager format" picker. | Researchers need exportable citations for academic publication; current cite tool produces basic only. | 4 days | ⬜ |
| **`ResearchAnalyticsService` + dashboard** — collection usage statistics, search pattern analysis, researcher activity metrics, popular collections, date-range analysis. Mounts inside `ahg-research`. | Institutions need impact evidence for funding applications; the data exists but isn't surfaced. | 1 wk | ⬜ |
| **Real-time collaboration** — shared annotation layers, research-team workspaces, peer-review workflow, comment threads on evidence sets. Pairs with existing `ahg-annotations`. | Project sharing is static today (re-open to see changes); teams want live co-editing. | 2 wk | ⬜ |
| **ORCID integration** — link `ahg-researcher-manage` profiles to ORCID iD, pull publication lists, push citations from this archive back to ORCID Works. | Modern academic identity layer; no plumbing today. | 4 days | ⬜ |
| **GraphQL API for researchers** — adjacent to the existing REST API. Lets external tools (Zotero, Tropy, university LMSs) compose queries across `information_object` / `research_project` / `annotation` / `actor` in one round-trip. | REST currently forces N round-trips to assemble a typical researcher view. | 1 wk | ⬜ |
| **Mobile / responsive researcher view** — dedicated reading-list + annotate-on-mobile flow. Reuse PWA patterns (manifest + service worker + responsive Tailwind) from the workbench. | Existing UI is desktop-shaped; reading-room walk-ins increasingly want phone-first. | 1–2 wk | ⬜ |
| **Offline research mode** — service-worker queues annotations + journal entries while offline, syncs when reconnected. Useful for off-site archives and field research. | None today; an archive trip to a remote collection loses notes if the connection drops. | 2 wk | ⬜ |

---

## How a Heratio package calls the workbench AI gateway

* `ahg-ai-services` already has the provider abstraction layer. Add a new
  driver `workbench-gateway` that POSTs to `https://ai.theahg.co.za/api/`
  with the gateway's per-client bearer token (stored in `config/heratio.php`,
  sealed at rest).
* All Studio-pane generations go through that one driver — no direct
  Anthropic / OpenAI / MiniMax keys in Heratio.
* For audio: the gateway exposes `/api/projects/:id/audio` (SSE); Heratio's
  Studio pane streams the events to the project's progress bar and persists
  the final mp3 as an `information_object` digital object.

---

## Update log

- **2026-05-16** — initial draft. Captures the audit table from the
  research-functionality review + adds the NotebookLM-Studio additions
  that don't overlap with already-shipped functionality.
