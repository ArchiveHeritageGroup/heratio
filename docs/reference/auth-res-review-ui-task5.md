# Authority Resolution - Task 5 Review UI

Task 5 of the AHG Authority Resolution Engine is the archivist-facing review
screen that surfaces one promoted NER mention at a time, shows its full
neighbourhood-context evidence packet, lists the ranked candidates with the
per-dimension evidence signals from Task 4, and lets the archivist pick one
of five actions: link, link-different, create-new (Task 6 stub), park, reject.

This document is the canonical reference for the screen, its routes, the
five action semantics, and the Fuseki provenance write that runs after every
decision.

## Mount point

Everything lives under `/admin/authority-resolution/` and is gated by the
Heratio `admin` middleware (same gate used by ahg-research). Anonymous users
get HTTP 403; non-admin authenticated users also get 403.

## Routes

All eight routes are registered by `AhgAuthorityResolutionServiceProvider::boot()`
loading `packages/ahg-authority-resolution/routes/admin.php`.

| Verb | Path | Name | Purpose |
|---|---|---|---|
| GET | `/admin/authority-resolution/queue` | `auth-res.queue` | Pending-mentions list, filter by entity type / state / object id |
| GET | `/admin/authority-resolution/lookup` | `auth-res.lookup` | Typeahead lookup behind the link-different modal. Params: `q`, `type` (PERSON / ORG / GPE) |
| GET | `/admin/authority-resolution/review/{mention}` | `auth-res.review.show` | Three-region review screen |
| POST | `/admin/authority-resolution/review/{mention}/link` | `auth-res.review.link` | Link to the selected ranked candidate |
| POST | `/admin/authority-resolution/review/{mention}/link-different` | `auth-res.review.linkDifferent` | Link to a different existing authority (from typeahead) |
| POST | `/admin/authority-resolution/review/{mention}/create-new` | `auth-res.review.createNew` | Task 5 stub. Records `decision_type=create_new` audit row; the real authority-creation sub-workflow lands in Task 6 (button is disabled in the UI) |
| POST | `/admin/authority-resolution/review/{mention}/park` | `auth-res.review.park` | Park with a reason |
| POST | `/admin/authority-resolution/review/{mention}/reject` | `auth-res.review.reject` | Reject as false positive |

## View file map

All views live in `packages/ahg-authority-resolution/resources/views/`,
namespaced `auth-res::`:

| File | Purpose |
|---|---|
| `queue.blade.php` | Pending-mentions queue + state summary tiles |
| `review.blade.php` | The three-region review screen |
| `_evidence-row.blade.php` | One row of a candidate's per-dimension evidence table |
| `_candidate-card.blade.php` | One ranked-candidate card (display name + score + evidence + map for places) |
| `_link-different-modal.blade.php` | Tailwind modal with typeahead lookup |
| `_park-modal.blade.php` | Tailwind modal with reason textarea |

The screen extends `theme::layouts.1col` (the shared Heratio master layout)
but the in-page content uses only Tailwind 4 utility classes.

## Three-region layout

The screen is a 12-column grid:

```
[ LEFT 3 cols ] [ MIDDLE 6 cols ] [ RIGHT 3 cols ]
mention +       ranked            five action
evidence        candidates        buttons
packet          + per-dim
                evidence
```

**Left region** carries the mention value + entity-type badge, source IO link
(jumps to the existing `/{slug}` show page), the full neighbourhood context
(surrounding text with the mention highlighted, character + paragraph
offsets, NER model version), co-occurring entities, nearby dates, nearby
places, role-language tokens. When the same string occurs more than once in
the source IO an amber ambiguity banner is rendered.

**Middle region** is the ranked-candidates list (sorted by `composite_score`
desc). Each candidate is one card with:
- display name + source badge (`Local actor` / `Local place` / `Fuseki agent`
  / `Fuseki place`)
- composite score + name-similarity score
- per-dimension evidence table (match / conflict / silent / absent badges
  in green / red / grey / dashed-grey)
- "View full authority record" link out to the existing `/actor/{id}` or
  `/taxonomy/term/{id}` show page (read-only)
- for PLACE candidates: a Leaflet map preview with OpenStreetMap tiles. The
  `term` table has no lat/long columns and the `property` table has no rows
  for place terms, so the preview degrades to a world-view map + "no
  coordinates available" hint.

**Right region** is a sticky sidebar with the five buttons. The button order
is the order in the brief: link / link-different / create-new (disabled) /
park / reject.

## Action semantics

Every action calls into `AhgAuthorityResolution\Services\DecisionRecorder`
which is the single write-path. Each method:

1. Inserts one row into `ahg_mention_decision` with a frozen JSON snapshot
   of evidence + candidates the archivist saw at decision time.
2. Updates `ahg_mention.state`.
3. For link / link_different: back-updates `ahg_ner_entity.linked_actor_id`
   (preserves the existing consumer contract used by ahg-discovery /
   ahg-actor-manage).
4. For park: writes `ahg_mention_park`.
5. Fires `DecisionProvenanceWriter::write()` to push the RDF-Star triples
   into the Fuseki decisions graph (`urn:heratio:auth-res:graph:decisions`).
   Failures are logged but never roll back the audit row / state change -
   the Task 8 `auth-res:write-provenance` artisan command can re-emit any
   decision whose `fuseki_graph_uri` is still null.

| Button | decision_type | mention.state after | Authority back-update |
|---|---|---|---|
| Link to selected | `link` | `linked` | yes (candidate's authority_id) |
| Link to different | `link_different` | `linked` | yes (chosen authority_id) |
| Create new (stub) | `create_new` | `new_record_created` | no (Task 6 will wire) |
| Park for later | `park` | `parked` | no |
| Reject as false positive | `reject` | `rejected` | no |

After any decision the controller redirects to the next `pending` mention
(by id ascending). When the queue is empty it redirects back to
`/admin/authority-resolution/queue` with a notice.

## Tailwind 4 notes

The `ahg-theme-b5` package name is a historical misnomer - the Laravel
Heratio CSS framework is Tailwind 4 (verified in `package.json`). All blade
files in this task use Tailwind utilities only (`bg-emerald-600`, `grid
grid-cols-12`, `rounded-lg`, etc.). The master layout still ships some
Bootstrap-named class wrappers (`container-xxl`, `breadcrumb`) inherited
from the early scaffolding, but the page-level content is Tailwind end to
end.

The modals are Tailwind-only too - no Bootstrap data-bs-toggle. Open / close
runs through tiny inline scripts that toggle `hidden` / `flex` on the modal
container.

## Leaflet integration

Loaded from `unpkg.com` (matches the existing Heratio CSP, which allows
`unpkg`). Loaded only on PLACE review screens (`$isPlace === true`). When a
candidate has no coordinates on file the map initialises to world-view zoom
1 over `[0, 0]` instead of leaving a dead placeholder. Coordinate enrichment
for places is out of scope for Task 5 and is tracked separately under
Authority Resolution / Task 9.

## ASCII mock

```
+------------------------------------------------------------------+
|  Mention #138 (pending)                Skip to next pending ->   |
+----------------+---------------------------+---------------------+
| [PERSON]       |  Ranked candidates (2)    | Actions             |
| Frederick      |                           |                     |
| Douglass       |  ( ) Frederick Douglass   | [Link to selected]  |
|                |      Local actor / id=...  | [Link different]   |
| Source IO: ... |      Composite 1.000      | [Create new] (off) |
|                |      [evidence table]     | [Park for later]   |
| Surrounding:   |                           | [Reject false pos] |
| ... 'Mark      |  (o) Different candidate  |                     |
| Twain, [Frede- |      Local actor / id=...  |                     |
| rick Douglass] |      Composite 0.84       |                     |
| , Thomas...'   |                           |                     |
|                |                           |                     |
| Co-occurring:  |                           |                     |
| Mark Twain,    |                           |                     |
| Thomas Edison  |                           |                     |
+----------------+---------------------------+---------------------+
```

## Known gaps

- **Create new (button 3)**: stub only. The button is disabled in the UI
  with a tooltip pointing at Task 6. The POST route exists and writes a
  `create_new` audit row but no authority record is created.
- **Reject + feedback table (button 5)**: Task 9 will design
  `ahg_ner_feedback` properly. For Task 5 the rejection is captured by the
  `ahg_mention_decision` audit row + `mention.state='rejected'` flip only.
- **Place map coordinates**: the `term` schema has no lat/long columns and
  the `property` table is empty for place terms, so the Leaflet preview
  falls back to a world-view. Coordinate enrichment is out of scope here.
- **Async provenance writer**: `DecisionRecorder::writeProvenance()` runs
  inline (synchronous). Heratio has a queue (`fuseki_queue_enabled=1`) that
  Task 8 can wire later for high-throughput batches - the existing inline
  call is correct for archivist-driven single-mention decisions and adds
  ~50ms latency.

## Smoke test commands

```bash
# Routes registered?
sudo -u www-data php artisan route:list | grep authority-resolution
# expect 8 rows

# Render the queue (admin session via tinker - the screen is admin-only)
sudo -u www-data php artisan tinker --execute='
use Illuminate\Support\Facades\Auth;
Auth::loginUsingId(<admin_user_id>);
$req = \Illuminate\Http\Request::create("/admin/authority-resolution/queue", "GET");
$resp = app(\Illuminate\Contracts\Http\Kernel::class)->handle($req);
echo $resp->getStatusCode() . " " . preg_match("/<title>([^<]+)/i", $resp->getContent(), $m) . " " . ($m[1] ?? "n/a") . "\n";
'

# Drive one decision end-to-end (DecisionRecorder is the supported public API)
sudo -u www-data php artisan tinker --execute='
$r = app(\AhgAuthorityResolution\Services\DecisionRecorder::class);
$decisionId = $r->recordLink(<mention_id>, <user_id>, <candidate_id>);
echo "decision #" . $decisionId . "\n";
'
```

## Related

- Tasks 1-4: `docs/reference/auth-res-*` (promotion, candidate generation,
  evidence scoring).
- Task 8: `DecisionProvenanceWriter` + `auth-res:write-provenance` artisan.
- Task 6: new-authority sub-workflow (button is wired to a stub here).
- Task 9: `ahg_ner_feedback` table (Reject button is a stub here).
- Lock policy: `feedback_lock_io_show_tree.md`, `feedback_lock_all_pages.md`.
- Tailwind reference: `feedback_heratio_tailwind.md`.
