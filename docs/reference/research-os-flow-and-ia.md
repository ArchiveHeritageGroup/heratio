# Research OS - flow and information architecture (epic #1222, #1225)

The plan that re-spines the `ahg-research` module around the researcher's journey instead of the reading-room's operations. Decided 2026-06-11 (Johan): option B - researcher-focused - implemented as role-based landing.

## The problem
`ahg-research` had ~25 flat sidebar destinations mixing the researcher's scholarly journey with the institution's reading-room operations (bookings, seats, rooms, equipment, retrieval, walk-ins). The research flow was buried and felt lost. The Research Operating System vision is spined by the research question/claim, looped.

## The decision: role-based B
There are two audiences in one module:

- **The researcher** (scholar / student) - wants the journey.
- **Reading-room / archives staff** - want the operations console (managing other people's bookings, seats, walk-ins, retrievals, approvals, verification).

So the journey is the researcher's primary experience, and operations move to the role that uses them - not deleted, relocated by role.

- **Researcher** logs in -> workspace + projects; each project opens a **Command Centre** whose structure IS the journey.
- **Staff/admin** -> the reading-room ops console (the admin-gated dashboard widgets + the Administration sidebar group), unchanged.

The handful of researcher-facing ops actions (book a visit, request material, request a reproduction) stay available to the researcher as actions, while the management of all visits stays staff-side.

## The journey (per-project Command Centre)
Order: **Intent -> Question -> Capture -> Evidence & Triage -> Reading -> Claims -> Decision Log -> Writing -> Review -> Publish**, with the Decision Log as the cross-cutting memory and a progress bar + "what's next" CTA.

| Phase | Built from |
|---|---|
| Intent | `research_project` + mission fields |
| Question | #1226 Question Builder (versioned brief) |
| Capture | #1228 Quick Capture Inbox |
| Evidence & Triage | bibliographies, collections + #1227 Source Triage |
| Reading | annotations |
| Claims (spine) | #1223 Claim Ledger (research_assertion + sidecar) |
| Decision Log | #1224 Decision Log |
| Writing / Review / Publish | journal/lecture/report builders, source assessment, target-journal directory |

## What shipped in this release
- `CommandCentreService::journey()` + `progress()` - per-project, fully guarded (never 500s), Route::has-gated links.
- A journey panel (`_command-centre.blade.php`) at the top of every project page: phase tracker with status + counts + a "Continue: <next phase>" CTA; the Claims phase flags claims with no evidence.
- Five backend slices integrated and live: Claim Ledger (#1223), Decision Log (#1224), Question Builder (#1226), Source Triage (#1227), Quick Capture Inbox (#1228) - each per-project, own routes file, sidecar/new tables auto-installed on boot, no existing table altered.
- Sidebar regrouped: top group relabelled "My Research Journey" + a Quick Capture Inbox entry; the reading-room Administration group remains admin-only.
- Reading-room ops widgets remain admin-gated on the dashboard, so researchers get a clean, journey-focused home.

## Deferred (still in #1222)
Argument Builder, Living Field Map/Alerts (retraction warnings via OpenAlex/Crossref), Method Studio, Analysis Bridge, Review Studio (adversarial reviewer twin), Publication Studio submission workflow, Research Memory, and the moonshots (Contradiction Engine, Provenance Chain, Time Machine, Reviewer Twin, Grant Engine, Impact Tracking). C2PA provenance and the #1203 federation are building blocks for the moonshots.
