# Research Enhancements - User Guide

> **Audience:** registered researchers using the Heratio research portal at `/research/`.
>
> **Released:** 2026-05-16 (see `docs/whats-new-2026-05.md` for the changelog entry).
>
> This guide covers the 13 features that landed together as the
> "Research Enhancements Roadmap" - NotebookLM-style Studio pane,
> private notebooks, cross-fonds queries, citation managers, analytics,
> real-time collaboration, ORCID, GraphQL, mobile / offline.
> Feature-by-feature; skim the table of contents and jump.

## Contents

1. [Studio - generate briefings, study guides, audio overviews](#studio)
2. [Citation popovers and copy-to-citation-manager](#citations)
3. [Private notebooks (and promoting to a public project)](#notebooks)
4. [Cross-fonds reasoning queries](#cross-fonds)
5. [Research analytics dashboard](#analytics)
6. [Real-time collaboration on a project](#collab)
7. [ORCID iD linking](#orcid)
8. [GraphQL API for external tools (Zotero, Tropy, LMS)](#graphql)
9. [Mobile / PWA reading-and-annotate flow](#mobile)
10. [Offline mode](#offline)

---

## <a name="studio"></a>1. Studio - generate briefings, study guides, audio overviews

The Studio pane sits on every research project show page. Drop in source
items from your evidence sets, pick an output type, and the LLM produces
a grounded artefact with `[N]` citation markers.

**How to find it:**

1. Open any of your research projects: `/research/projects` → click a project.
2. In the right-hand "Research Output" card, click **Studio**.

**The form:**

- **Output type** - one of: Briefing, Study Guide, FAQ, Timeline, Diagram (Mermaid), Video Script, Spreadsheet, Audio Overview.
- **Title (optional)** - leave blank to use the default for the type.
- **Sources** - tick the IO descriptions you want the artefact to be grounded in. The list is populated from any collection / evidence set you've created under this project. Add items to a collection on the project page first if the list is empty.
- **Generate** - click and wait. Most artefacts take 10-60s depending on the configured LLM.

**Reading the result:**

- Body renders as markdown (or Mermaid for diagrams, JSON for spreadsheet preview).
- Every fact is tagged with `[1]`, `[2]`, ... that map back to the source items in the "Sources" card at the bottom.
- Hover any `[N]` → popover with the source title and a 220-character snippet.
- Click `[N]` → smooth-scrolls to the matching source in the list.
- For Spreadsheets, click **Download** to get a real .xlsx.
- For Audio Overviews, the audio player appears at the top of the page. If no TTS endpoint is configured on the server (`HERATIO_TTS_ENDPOINT`), the page shows the generated script as text - hand it to any TTS pipeline you have.

**What sources are supported:** any `information_object` you've added to a `research_collection` under this project. Donor agreements, attached PDFs (when wired into a collection), finding aids - anything that lives in the archive and you've grouped together as evidence.

**Where the artefacts live:** the "Recent artefacts" pane on the right of the Studio page. They persist as `research_studio_artefact` rows and survive logout / browser restart. Click any to re-open, download, or delete.

---

## <a name="citations"></a>2. Citation popovers and copy-to-citation-manager

Two changes to the citation page (`/research/cite/{slug}` - reachable from any record's action menu):

**(a) Copy in citation manager format** - new card above the styled citations. Six download buttons:

- **RIS** - paste into Zotero, Mendeley, EndNote, almost any reference manager.
- **BibTeX** - paste into LaTeX / JabRef.
- **EndNote XML** - alternative format for EndNote.
- **APA 7** - plain-text APA 7 archival format.
- **MLA 9** - plain-text MLA 9 archival format.
- **Chicago 17** - Chicago Manual of Style 17 Notes-Bibliography.

Each button downloads a file named after the record slug with the appropriate extension. You can also right-click → "Copy link address" and paste the URL into any citation-manager browser extension that supports remote import.

**(b) Citation `[N]` popovers** - on Studio show pages, any inline `[N]` marker shows a hover popover with the source title + snippet and a click-to-scroll to the source in the list. Works on Studio briefings, study guides, video scripts, etc.

---

## <a name="notebooks"></a>3. Private notebooks (and promoting to a public project)

Notebooks are your private scratchpad for half-formed research:
saved queries, AI outputs you want to keep, pinned source items,
freeform notes. Nobody else sees them. When the notebook is ready,
promote it to a public research project in one click.

**How to find:** left sidebar under "Research" → **Notebooks**.

**Creating a notebook:** title + optional summary → **Create**.

**Adding items:** open a notebook → form on the right:

- **Type** - Saved query / AI output / Source pin / Note.
- **Title + Body** - the content.
- **Source object id (optional)** - if the item is anchored to an archival record, fill in the IO id (visible in the URL of any record show page).
- **Pin to top** - sticky-pin to the top of the notebook.

**Items can be pinned (thumbtack icon) and removed (x icon) inline.**

**Promote to project:** the **Promote to project** button (only shown if the notebook hasn't been promoted yet) does three things:

1. Creates a new `research_project` named after the notebook, with you as owner.
2. Creates a fresh collection under the new project called "Promoted from notebook: <title>" and adds every `Source pin` item in the notebook as a collection item.
3. Marks the notebook as `promoted_to_project_id=<new>` so subsequent visits link to the project instead of re-promoting.

This is irreversible (you can delete the new project manually if you change your mind, but the notebook stays in the "promoted" state). The confirm dialog flags this.

---

## <a name="cross-fonds"></a>4. Cross-fonds reasoning queries

Ask one question across multiple fonds in one shot.

**How to find:** left sidebar under "Research" → **Cross-fonds Query**.

**The form:**

- **Query** - your single research question.
- **Fonds** - tick the fonds (or top-level collections) to search across. If you leave all unticked, Heratio runs the query across the first 50 named fonds in the archive.
- **Expand with thesaurus synonyms (semantic search)** - if you have the `ahg-semantic-search` package thesaurus loaded, this expands the query through the synonym graph before fan-out.

**Results** are merged from all selected fonds, reranked by Elasticsearch score, top 30 shown. Each hit displays:

- `[N]` ranked-position marker (these work with the citation popover JS too).
- Title (linked to the description show page).
- Fonds badge (which fonds the hit came from).
- Snippet (Elasticsearch highlight or first 220 chars of scope-and-content).
- Match score.

The query, fonds list, and result count are recorded in `research_cross_fonds_query` for your own future reference and for the analytics dashboard.

---

## <a name="analytics"></a>5. Research analytics dashboard

`/research/analytics` - aggregates the existing `research_activity_log` and `research_citation_log` into the metrics you'd put in a funding application.

**Date range:** `?from=YYYY-MM-DD&to=YYYY-MM-DD` query params, or use the date pickers at the top of the page. Default: last 30 days.

**What it shows:**

- 8 KPI tiles: Total events, Researchers, Distinct objects, Views, Searches, Citations, Downloads, Annotations.
- **Top researchers** (most-active 10).
- **Popular descriptions** (most-viewed/cited/annotated 10).
- **Popular collections** (10).
- **Top search terms** (15) - useful for understanding what researchers are actually looking for.
- **Weekly volume** bar chart.

**No new audit tables were added** - all of this comes from data already being logged.

---

## <a name="collab"></a>6. Real-time collaboration on a project

Comment threads on evidence sets, presence indicators ("who's online right now"), shared annotation layers. Updates every 3 seconds (polling - no WebSocket broker on the AHG host today).

**How to find:** project show page → right-hand "Research Output" card → **Live Collaboration**.

**What's there:**

- **Online now** card on the left - every collaborator with a heartbeat in the last 90 seconds, each with a colour swatch.
- **Evidence comments** card on the right - threaded comments anchored to the project (and optionally to a specific collection / item). Type into the textarea → Post → everyone on the project sees it within 3s.
- **Mark resolved** on any open comment.

**Shared annotation layers (IIIF):** the W3C Web Annotations table (`ahg_iiif_annotation`) gained `project_id` + `visibility` columns. Set `visibility='project'` on an annotation and every project collaborator sees it via Mirador's annotations companion window. Default stays `private` so existing annotations are not silently exposed.

---

## <a name="orcid"></a>7. ORCID iD linking

Link your researcher profile to your ORCID iD, pull your publications list, and push citations from this archive back to your ORCID Works.

**How to find:** left sidebar under "Research" → **ORCID Link**.

**First connection:**

1. If the system has ORCID credentials configured (operator action - see below), click **Connect with ORCID**.
2. ORCID's standard OAuth flow takes over. Approve the requested scope (`/authenticate /read-limited /activities/update`).
3. ORCID redirects you back. Your link is stored.

**Operations available once linked:**

- **Pull Works from ORCID** - fetches your full Works list and records `last_synced_at` + `last_works_count` on the link row.
- **Unlink** - removes the stored tokens (you can re-link at any time).

**Operator setup (one-time, in `.env`):**

```
ORCID_CLIENT_ID=...
ORCID_CLIENT_SECRET=...
ORCID_REDIRECT_URI=https://your-host/research/orcid/callback
ORCID_BASE=https://orcid.org           # https://sandbox.orcid.org for testing
ORCID_API_BASE=https://api.orcid.org   # https://pub.orcid.org for Public API only
```

Register the app at [orcid.org/developer-tools](https://orcid.org/developer-tools).

**If not configured:** the page shows a clean "ORCID not configured" alert listing the exact ENV keys to set. Nothing returns 500.

---

## <a name="graphql"></a>8. GraphQL API for external tools (Zotero, Tropy, LMS)

The existing GraphQL endpoint at `POST /admin/graphql` gained five researcher-focused queries:

- `researchProject(id: Int!)` - title, description, status, collections, studio_artefacts.
- `researchProjects(limit: Int)` - paginated list of projects.
- `researchAnnotations(targetIri: String!)` - W3C Web Annotations for a canvas IRI.
- `researchCollections(projectId: Int!)` - collections with items joined.
- `researcherView(researcherId: Int!)` - single round-trip combined query: researcher profile + their projects + recent annotations + ORCID link summary. This is the "compose one query for a typical researcher view" entry point external tools should use.

Try it at the playground: `GET /admin/graphql/playground`.

Example query:

```graphql
{
  researcherView(researcherId: 42) {
    researcher
    projects
    annotations
    orcid
  }
}
```

---

## <a name="mobile"></a>9. Mobile / PWA reading-and-annotate flow

`/research/mobile` is a phone-first version of the researcher home, plus a PWA manifest so you can "Add to home screen" and run it standalone.

**What's on it:**

- Your name + email at the top.
- Online/offline badge (right side).
- Reading list - your most-recently-added collection items, 50 max, each linking to the description show page.
- Four-button grid: Search (cross-fonds), Notes (notebooks), Bibliographies, Journal.
- Quick journal entry form at the bottom - title + body, save. If you're offline, the entry queues; it syncs the moment the browser comes back online.

**Installing as an app:**

- Chrome / Edge: kebab menu → "Install Heratio".
- iOS Safari: Share → "Add to Home Screen".

The shortcut launches in standalone mode (no browser chrome), starts at `/research/mobile`, uses the configured theme colour.

---

## <a name="offline"></a>10. Offline mode

The mobile shell registers a service worker (`/sw.js`) that caches `/research/mobile`, the manifest, and the favicon. Once the shell has loaded once, it opens offline too.

**Behaviour offline:**

- The mobile home page opens from cache.
- The "online" badge in the top-right flips to "offline" automatically.
- Quick journal entries you type while offline are queued in browser-local storage.
- The moment the browser detects it's back online, the queue posts to `/research/sync/offline` and applies.

**What syncs:** journal entries, IIIF annotations. (Currently the mobile shell only writes journal entries; annotations can be queued by anything that follows the same `{kind: 'annotation', ...}` shape.)

**Audit:** every sync run is recorded in `research_offline_sync_log` (researcher_id, queued_count, applied_count, conflict_count) so operators can track sync health.

---

## Where things live (quick reference)

| Feature | Service | Routes prefix | Main view |
|---|---|---|---|
| Studio | `ResearchStudioService` | `/research/studio/*` | `studio.blade.php`, `studio-show.blade.php` |
| Citation export | `CitationService` | `/research/cite/*` | `cite.blade.php` |
| Notebooks | `NotebookService` | `/research/notebooks/*` | `notebooks.blade.php`, `notebook-show.blade.php` |
| Cross-fonds | `CrossFondsQueryService` | `/research/cross-fonds-query` | `cross-fonds-query.blade.php` |
| Analytics | `ResearchAnalyticsService` | `/research/analytics` | `analytics.blade.php` |
| Real-time collab | `CollaborationRealtimeService` | `/research/projects/{id}/realtime/*` | `collab-panel.blade.php` |
| ORCID | `OrcidService` | `/research/orcid/*` | `orcid-link.blade.php` |
| GraphQL | `GraphqlController` (in ahg-graphql) | `/admin/graphql` | `playground.blade.php` |
| Mobile + offline | (n/a - mobile is HTML+JS) | `/research/mobile`, `/research/sync/offline`, `/sw.js`, `/manifest.webmanifest` | `mobile-home.blade.php` |

Source roadmap: `docs/research-enhancements-roadmap.md`. KM reference for the same content: `docs/reference/research-roadmap-2026-05-features.md`.
