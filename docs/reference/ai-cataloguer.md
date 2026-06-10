# AI Cataloguer (scans in, draft records out)

heratio#1196 first slice. Turns one scanned document into a review-ready draft archival
description by composing the existing AI services. Admin page: **/admin/scan/cataloguer**
(linked from the Scan dashboard).

## Pipeline
Upload one scan -> **HTR** (handwriting/typed text, gateway) -> **NER** (people / orgs /
places / dates) -> **LLM** drafts an ISAD(G) **title** + **scope-and-content** note, grounded
ONLY in the transcription (no invented facts). The draft is shown for the archivist to review
and edit. Nothing is saved yet - creating the information_object from the accepted draft is
the next slice.

## Where it lives
- `packages/ahg-scan/src/Services/CataloguerService::draftFromImage($path)` - the orchestrator.
  Reads the locked `ahg-ai-services` (HtrService / NerService / LlmService) via the container;
  does not modify it.
- `packages/ahg-scan/src/Controllers/CataloguerController` (`index`, `draftAjax`).
- Routes: `scan.cataloguer` (GET page) + `scan.cataloguer.draft` (POST upload -> JSON).
- View: `admin/scan/cataloguer.blade.php`.

## Notes
- All AI calls route through the AHG gateway (via the existing services) - no direct node calls.
- HTR text is read flexibly from the gateway response (text / transcription / pages / lines).
- Next slices: accept-draft -> create IO (+ NER access points via NerService::createAccessPoints),
  batch a folder, and dates -> ISAD date range.
