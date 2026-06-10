# AI Curator-Docent (digital-twin walkthrough)

The exhibition digital-twin walkthrough (`ahg-exhibition`) ships a conversational
AI docent that answers visitors' questions, always grounded in real catalogue
data and routed through the AHG AI gateway (`AhgAiServices\Services\LlmService`).
The model is instructed to use only the supplied catalogue facts - it never
invents dates, names, places or provenance. GitHub issue: heratio#1185.

## Two scopes

### Object scope (existing)
Open an object panel in the walkthrough and use "Ask the docent". The question
is answered from that single object's catalogue record (title, reference code,
parent collection, scope_and_content).

- Service: `ExhibitionSpaceService::aiAnswerAboutObject(int $ioId, string $question)`
- Route: `GET /exhibition-space/object/{ioId}/ask?q=...` (`exhibition-space.ask`)
- "take me to / where is X" free-text is intercepted client-side and walks the
  visitor to the matched object (auto-walk).

### Room / exhibition scope (this slice)
A HUD button "Ask the docent" (top-left, user-tie icon) opens a panel that is
reachable at any time - no object needs to be open. It answers about the whole
exhibition, grounded in every object placed in the building.

- Service: `ExhibitionSpaceService::aiAnswerAboutRoom(object $space, string $question)`
  - Grounding set comes from `roomGroundingObjects($space, 60)`: each distinct
    placed object in the building once (building_id-wide via `buildingSpaceIds`),
    with its title, its room name, and a scope snippet trimmed to ~220 chars.
    Corridor placements (`wall_or_zone='corridor'`) are excluded.
  - Cached per (building, question) for 7 days.
- Suggested follow-up chips: `roomSuggestedQuestions($space, 4)` - deterministic,
  no AI. It names two real pieces on display so every chip is answerable
  ("Tell me about X", "Where can I find Y?") plus two generic openers.
  - Route: `GET /exhibition-space/{slug}/room-questions` (`exhibition-space.room-questions`)
- Route: `GET /exhibition-space/{slug}/ask-room?q=...` (`exhibition-space.ask-room`)
- The room panel also honours "take me to X" navigation (shares
  `docentTryNavigate`), so a visitor can ask the room docent to walk them to a
  piece by name.

All three routes are public + read-only (the walkthrough is visitor-facing).

## Grounding guarantee

The room prompt passes only the on-display object list (titles + short scope
snippets) and tells the model: answer using ONLY that list, point the visitor to
pieces by their exact title, and if the exhibition does not cover the question
say so and suggest the closest pieces that ARE on display. No object, date, name
or provenance outside the list may be invented.

## Files

- `packages/ahg-exhibition/src/Services/ExhibitionSpaceService.php`
  - `aiAnswerAboutRoom`, `roomGroundingObjects`, `roomSuggestedQuestions`, `shortTitle`
- `packages/ahg-exhibition/src/Controllers/ExhibitionSpaceController.php`
  - `askRoomAjax`, `roomQuestionsAjax`
- `packages/ahg-exhibition/routes/web.php` - the two new public GET routes
- `packages/ahg-exhibition/resources/views/exhibition-space/walkthrough.blade.php`
  - HUD "Ask the docent" button + room-docent panel + JS wiring (reuses
    `speakText` for TTS and `docentTryNavigate` for auto-walk)

## Test

Walkthrough URL (a building with placements): `/exhibition-space/ahg-collection-twin/walkthrough`
(space id 5, building_id `benson`, shares objects with `benson-collection`).
Click "Ask the docent" (top-left) and ask e.g. "What is this exhibition about?"
or "Which pieces should I not miss?". Answers name actual placed objects.
