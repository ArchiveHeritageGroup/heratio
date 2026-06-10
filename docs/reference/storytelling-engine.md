# Storytelling engine (collection -> public narrative)

heratio#1202 first slice. Admin page **/admin/stories** (Story Generator). Enter a theme and
the AI writes a short, engaging public "story of the collection" (~180-220 words) weaving in
real catalogue objects on that theme - for a website post, newsletter, school pack or label.
Review/edit/copy; saving + publishing is the next slice. Distinct from the exhibition designer
(#1186, which lays objects out in rooms) - this outputs prose.

## Pipeline
Theme -> on-theme objects (prefer objects placed in exhibition rooms for context, else the
catalogue; keyword-scored, only score>0 so the story stays on-theme) -> the AI gateway writes
the narrative grounded in those objects (no invented dates/people/events).

## Where it lives
- `packages/ahg-core/src/Services/StorytellingService::generate($theme,$max)`.
- `StorytellingController` (index, generateAjax); routes `stories.index` + `stories.generate`
  (auth); view `ahg-core::stories`.

## Save + publish (shipped)
The generated story can now be saved as a draft or published to a permanent, shareable page.
- `StorytellingService::save($data)` upserts an `ahg_story` row (insert, or update by id so
  re-saving keeps the same slug), derives a unique slug from the title on first save, and stores
  the featured object ids. `getBySlug()`, `storyObjects()` (resolves featured objects to
  title+slug, authored order), `listSaved()` for the admin list.
- `StorytellingController::saveAjax` (route `stories.save`, auth) returns the public URL on
  publish; `show($slug)` (route `stories.show`) renders the public page - published stories are
  world-readable, drafts are staff-only (404 otherwise).
- The Story Generator page gained Save draft / Publish buttons + a shareable link, an editable
  title, and a "Saved stories" table.

`ahg_story` (auto-created by `AhgCoreServiceProvider` from `database/install_story.sql`):
`id, slug (unique), title, theme, body, object_ids (json), status (draft|published), timestamps`.

## Routing note
The public page lives at the two-segment path `/stories/{slug}` so it is never intercepted by
the single-segment `/{slug}` archival-record catch-all (whose exclusion list lives in a locked
route file). The admin index stays under the already-excluded `/admin/stories`.

## Grounding sources (shipped)
Beyond theme-matched catalogue objects, the curator can ground the story in extra material, all
optional and combinable (a collapsible "Add sources" panel on the generator):
- **Background notes** - pasted free text, added to the prompt as additional context.
- **Source URLs (multiple)** - add several pages; each is fetched by
  `StorySourceService::fetchUrlText()` server-side and stripped to readable text. SSRF-guarded:
  http/https only, public hosts only (loopback / private / reserved / link-local IPs refused,
  names resolved and every record checked), 8s timeout, 600 KB cap. Verified: `http://127.0.0.1/`
  and `file://` are rejected. Up to 5 URLs.
- **Uploaded documents (multiple)** - `extractUploadText()`: text/* read directly, PDF via
  `AhgPdfTools\Services\PdfTextExtractService` (pdftotext), images via
  `AhgAiServices\Services\HtrService` - both resolved softly (`class_exists` + `app()`) so
  ahg-core keeps no hard composer dependency on those packages. Max 8 MB each, up to 5
  (multi-file input); mimes pdf/txt/png/jpg.
- **Hand-picked records (multiple)** - typeahead (`stories.search` -> `searchRecords()`) to add
  specific catalogue records, woven in for certain (they lead the object list, guaranteed
  inclusion).

Sources are processed per-item: a URL that fails to fetch or a document with no extractable
text becomes a non-fatal `source_warning` (shown to the curator) rather than aborting the whole
run - the story is still written from whatever sources did work. Request fields are arrays:
`urls[]`, `documents[]`, `record_ids[]`, plus a single `notes`.

All extra context is bounded (per-source 6 KB, assembled 8 KB) before the prompt. The prompt
tells the model to use the background to inform - not contradict the objects, not copy verbatim.
With sources present a story can be generated even with no theme/objects (context-only).

### Source attribution (provenance)
Each external source is recorded as `{type: note|url|upload|record, label, url?}` and stored in
`ahg_story.sources_json`. The public page renders a **Sources** list (URLs linked
`rel="noopener nofollow"`, uploads by filename, notes/records labelled) so a published story
stays defensible. Fits the AI-provenance roadmap (#61).

## On this day (shipped)
An **On this day** button writes a story from records dated to today, with a graceful fallback:
`StorytellingService::onThisDay()` -> `datedObjects()` queries the `event` table for catalogue
records whose `start_date` falls on today's month + day (any year). If none match exactly it
falls back to records dated anywhere in the current month; if there are none of those either it
returns `scope=none` and the UI shows a friendly "nothing dated to today or this month yet"
message. The response carries `scope` (`day` | `month` | `none`) so the page can note when it
fell back to the whole month. Route `stories.on-this-day` (POST, auth) -> `onThisDayAjax`.

Year-only AtoM dates store the day/month as `00`, so they never match a real 1-12 / 1-31 value
and are naturally excluded - the feature only surfaces records with genuine calendar dates.
Verified with `Carbon::setTestNow()`: an exact-day match writes a "6th of May" story; a same-month
non-matching day falls back to a month story.

## Notes
- AI via the gateway (LlmService) - no direct node calls.
- Reachable from the admin menu + exhibition browse "AI Tools" (Story Generator), with a back
  button to Exhibition spaces.
- Next slices: per-object deep-dive; multilingual via the translation service; audio via TTS.
