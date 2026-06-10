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
- Next slices: status filter (published only); date/era awareness.

## Build this exhibition (shipped)
The reviewed draft now builds into a real Exhibition Space in one click.
`GenerativeExhibitionService::buildExhibition($draft)` creates one room per draft card as a
sibling `ahg_exhibition_space` sharing a `building_id` (first room seeded with a unit-rectangle
`shape_json` + `bld_x/bld_y`, the rest appended via `addBuildingRoom()`), lays each chosen
object out along the room walls with `createPlacementAt()` (back wall first, wrapping to the
front wall past six), and returns the first room's slug. The controller (`buildAjax`, route
`exhibition-space.generate.build`, acl:create) hands back the builder URL; the page redirects
the curator straight into the builder to fine-tune positions, then walkthrough.
