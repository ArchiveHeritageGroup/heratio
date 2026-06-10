# Generative exhibitions (theme in, draft exhibition out)

heratio#1186 first slice. Admin page **/exhibition-space/generate** (AI Designer button on the
Exhibition dashboard). Enter a theme -> a draft exhibition: 2-4 rooms, each with a title and a
short selection of catalogue objects, each with a one-line label/why. Review only - building a
real Exhibition Space from the draft (placement) is a later slice.

## Pipeline
Theme -> candidate objects from the catalogue (DB match on title / scope_and_content keywords)
-> the AI gateway curates them into grouped rooms + labels (LLM, JSON, picks ONLY from the
candidate ids - no invented objects) -> rendered as cards for review.

## Where it lives
- `packages/ahg-exhibition/src/Services/GenerativeExhibitionService::suggest($theme,$count)`.
- `GenerativeController` (`index`, `suggestAjax`); routes `exhibition-space.generate` +
  `exhibition-space.generate.suggest` (acl:create); view `exhibition-space/generate.blade.php`.

## Notes
- AI via the gateway (LlmService) - no direct node calls.
- First-slice candidate search is DB keyword match (reliable, no ES/Qdrant assumptions).
  Upgrade path: ElasticsearchService (relevance) or VectorSearchService (semantic) for recall.
- Next slices: "Build this" -> create the Exhibition Space rooms + place the objects;
  status filter (published only); date/era awareness.
