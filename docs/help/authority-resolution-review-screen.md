> Heratio Help Center article. Category: AI & Automation.

# Authority Resolution - Review Screen Reference

The review screen is where one promoted NER mention is resolved into one of the five outcomes. It lives at `/admin/authority-resolution/review/{mention}` and is gated by the Heratio `admin` middleware. Anonymous users get HTTP 403; authenticated non-admin users also get 403.

This article is a region-by-region walkthrough. For the conceptual mental model, read "AHG Authority Resolution - User Guide" first.

## Layout

A 12-column responsive grid:

```
+------------------------------------------------------------------+
|  Mention #138 (pending)                Skip to next pending ->   |
+----------------+---------------------------+---------------------+
| LEFT 3 cols    | MIDDLE 6 cols             | RIGHT 3 cols        |
| mention +      | ranked candidates +       | five action         |
| evidence       | per-dimension evidence    | buttons             |
| packet         |                           |                     |
+----------------+---------------------------+---------------------+
```

## Left region: mention and context packet

The mention header carries the surface value and an entity-type badge. Underneath sit the seven elements of the neighbourhood context packet stored in `ahg_mention_context`:

| Element | Source column | Use |
|---|---|---|
| Source IO link | `ahg_mention.object_id` | Jumps to the existing `/{slug}` show page |
| Mention highlighted in context | `surrounding_text_before` + value + `surrounding_text_after` | Up to 150 chars on each side |
| Character + paragraph offsets | `character_offset_start/end`, `paragraph_offset_start/end` | Locates the mention in the original text |
| NER model version | `ner_model_version` | Tags which upstream model produced the entity |
| Co-occurring entities | `co_occurring_entities` JSON | `[{value, type, distance_tokens}]` |
| Nearby dates | `nearby_dates` JSON | `[{value, normalized?, distance_tokens}]` |
| Nearby places | `nearby_places` JSON | `[{value, term_id?, distance_tokens}]` |
| Role-language tokens | `role_language_tokens` JSON | `[{token, position_offset, kind}]` (kinship / witness / location / movement / other) |

### Ambiguity banner

When the same surface form occurs more than once in the source information object the left region shows an amber banner: "This name appears N times in this document". Treat this as a prompt to confirm you are reviewing the intended occurrence; the offsets disambiguate.

## Middle region: ranked candidates

One Bootstrap 5 card per candidate, sorted by `composite_score` descending. Sort ties are broken by name similarity, then alphabetical display name.

### Per-card elements

- **Display name** (`candidate_display_name`) and a **source badge**:
  - `Local actor` - candidate from the local `actor` table
  - `Local place` - candidate from the local `term` table
  - `Fuseki agent` - candidate from the Fuseki `agents` graph
  - `Fuseki place` - candidate from the Fuseki `places` graph
- **Composite score** (large green pill) and **name-similarity score** (small grey label).
- **Per-dimension evidence table**. One row per applicable dimension with a coloured badge:
  - green `match`
  - red `conflict`
  - grey `silent`
  - dashed-grey `absent`
  Hover the badge to see the raw `evidence_data` payload (the value the evaluator considered).
- **"View full authority record"** link to the read-only authority page (`/actor/{id}` or `/taxonomy/term/{id}`). Opens in a new tab so you do not lose the review state.
- **For PLACE candidates only**: a Leaflet map preview using OpenStreetMap tiles. When no coordinates are on file the map falls back to a world-view at zoom 1 over `[0, 0]` with a "no coordinates available" hint. Coordinate enrichment for places is tracked separately.

### Selecting a non-top candidate

Each card has a radio button. The top card is pre-selected. To link to a lower-ranked candidate, click its radio and then **Link to different** in the action sidebar. The audit row will capture the rank-1 score (not the picked card's score) so the override is auditable later.

## Right region: action buttons

A sticky sidebar with the five buttons. The button order is fixed.

| Button | POST route | decision_type | mention.state | Authority back-update |
|---|---|---|---|---|
| Link to selected | `.../link` | `link` | `linked` | yes (candidate's authority_id) |
| Link to different | `.../link-different` | `link_different` | `linked` | yes (chosen authority_id) |
| Create new | `.../create-new` | `create_new` | `new_record_created` | yes (new authority_id) |
| Park for later | `.../park` | `park` | `parked` | no |
| Reject as false positive | `.../reject` | `reject` | `rejected` | no |

### Modal dialogs

Three buttons open Bootstrap 5 modals instead of submitting immediately:

- **Link to different** - opens a typeahead lookup so you can search for any authority that is not on the candidate list. The typeahead hits `/admin/authority-resolution/lookup?q={query}&type={entity_type}`.
- **Park for later** - opens a textarea for the park reason. Reason is required.
- **Reject as false positive** - opens a textarea for the rejection reason. Reason is optional. Empty submissions still reject and still record a feedback row.

### After a decision

The controller redirects to the next `pending` mention (lowest id first). When the queue is empty it redirects to `/admin/authority-resolution/queue` with a notice.

## What gets written on each click

Every action calls into `DecisionRecorder`, the single write-path. Each method:

1. Inserts one row into `ahg_mention_decision` with a **frozen** JSON snapshot of evidence and candidates visible at decision time. This snapshot is the answer to "what did the archivist see on screen" - it does not change when the underlying evidence later changes.
2. Updates `ahg_mention.state`.
3. For link / link-different: back-updates `ahg_ner_entity.linked_actor_id` (preserves the consumer contract used by the discovery pipeline and actor manage).
4. For park: inserts into `ahg_mention_park`.
5. For reject: writes `ahg_ner_feedback` (best-effort, in a try/catch - failure never blocks the reject audit row).
6. Fires `DecisionProvenanceWriter::write()` to push RDF-Star triples into the Fuseki decisions graph. Failures are logged but never rolled back; `auth-res:write-provenance {decision_id}` can backfill any decision whose `fuseki_graph_uri` is still NULL.

## Decision immutability

A decision row in `ahg_mention_decision` is immutable. The schema does **not** enforce one-decision-per-mention; you can record additional decisions on the same mention. The newest decision wins for the `state` column. Both rows remain visible in the audit history.

This is intentional. The audit answers "who did what, when, on what evidence" - including corrections. The frozen `evidence_snapshot` on each row means you can defend the original decision later even after the underlying candidate data has drifted.

## ASCII mock

```
+------------------------------------------------------------------+
|  Mention #138 (pending)                Skip to next pending ->   |
+----------------+---------------------------+---------------------+
| [PERSON]       |  Ranked candidates (2)    | Actions             |
| Frederick      |                           |                     |
| Douglass       |  ( ) Frederick Douglass   | [Link to selected]  |
|                |      Local actor / id=... | [Link different]    |
| Source IO: ... |      Composite 1.000      | [Create new]        |
|                |      [evidence table]     | [Park for later]    |
| Surrounding:   |                           | [Reject false pos]  |
| ... 'Mark      |  (o) Different candidate  |                     |
| Twain, [Frede- |      Local actor / id=... |                     |
| rick Douglass] |      Composite 0.84       |                     |
| , Thomas...'   |                           |                     |
|                |                           |                     |
| Co-occurring:  |                           |                     |
| Mark Twain,    |                           |                     |
| Thomas Edison  |                           |                     |
+----------------+---------------------------+---------------------+
```

## CSP and external assets

The screen loads Leaflet from `unpkg.com` (allow-listed in the existing Heratio CSP). All other CSS and JS is bundled. The page extends `theme::layouts.1col` and the in-page content uses Bootstrap 5 classes (cards, `col-lg-*` grid, `alert`, `badge`, Bootstrap Icons).

## Known caveats

- **NER model version** may be `NULL` on mentions promoted before the upstream pipeline started reporting it. The header simply hides the row when missing.
- **Place coordinates** are not yet enriched. PLACE cards fall back to a world-view map. Coordinate enrichment is tracked separately.
- **Map preview** loads only when at least one PLACE candidate is on the page. Person / org reviews do not load Leaflet.
- **Real NER confidence** is currently a hardcoded constant (0.85) until the upstream NER API exposes per-mention scores end to end. The engine therefore treats `confidence` as advisory - the evidence layer is the real signal.

## Related routes

| Verb | Path | Purpose |
|---|---|---|
| GET | `/admin/authority-resolution/queue` | Pending-mentions list with filters |
| GET | `/admin/authority-resolution/review/{mention}` | The screen this article describes |
| GET | `/admin/authority-resolution/lookup?q=&type=` | Typeahead behind the link-different modal |
| POST | `/admin/authority-resolution/review/{mention}/link` | Confirm rank-1 candidate |
| POST | `/admin/authority-resolution/review/{mention}/link-different` | Pick a lower-ranked or off-list candidate |
| POST | `/admin/authority-resolution/review/{mention}/create-new` | Open / submit the new-authority sub-workflow |
| POST | `/admin/authority-resolution/review/{mention}/park` | Park with a reason |
| POST | `/admin/authority-resolution/review/{mention}/reject` | Reject as NER false positive |
