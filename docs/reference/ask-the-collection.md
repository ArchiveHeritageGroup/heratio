# Ask the collection (public, corpus-grounded Q&A)

**Summary:** "Ask the collection" is a PUBLIC page where a member of the public asks a plain-language question and gets a concise answer grounded ONLY in matching PUBLISHED catalogue records, cited by number with links. It is the public-engagement sibling of the researcher copilot. First slice of the North Star vision (heratio#1208: "a culture you can talk to"). Lives entirely in `packages/ahg-core`. AI goes through the gateway via `LlmService`.

## What it is

- Public URL: `/ask/collection` (GET, search-box page) + `/ask/collection/answer` (GET|POST, JSON answer).
- Two-segment paths on purpose: the single-segment `/{slug}` archival-record catch-all only matches one path segment, so `/ask/collection` is never intercepted by it.
- No auth middleware. It is an anonymous-public surface.

## Grounding model

- Keyword-scores catalogue records over title (+2) and scope_and_content (+1), same scoring as the researcher copilot (`AhgResearch\Services\ResearchCopilotService`).
- HARD restriction to PUBLISHED records only: `whereExists` on `status` where `type_id = 158` and `status_id = 160` (the same gate the public GLAM browse uses for guests). Drafts / unpublished material can never leak into a public answer.
- Top N (default 8) matched records are handed to the gateway LLM (`AhgAiServices\Services\LlmService::complete`) with a prompt that says: answer using ONLY the numbered records, cite `[n]`, say plainly when the records do not cover the question, never invent facts.
- When NO published record matches, the service short-circuits with a plain "the published collection does not appear to cover this" answer and makes NO LLM call (no invention, no cost).

## Files (all in `packages/ahg-core`)

- `src/Services/AskCollectionService.php` - `ask(string $question, int $maxSources = 8)`; published-only keyword search + grounded LLM call. Returns `{ok, question, answer, sources, covered}`.
- `src/Controllers/AskCollectionController.php` - `index()` (page, server-renders an answer when `?q=` is present) + `answer()` (JSON).
- `resources/views/ask-collection.blade.php` - search box, answer card with `[n]` citations linkified to the cited source list, record links by slug. Async submit via fetch (CSP nonce on the inline script); server-side render fallback for the no-JS `?q=` path.
- `routes/web.php` - the two routes, registered through the package loader on the `web` group.

## Citations

`[n]` markers in the answer are rewritten to anchor links pointing at the matching entry in the "Records this answer draws on" list. Each source links to its archival-record page via `/{slug}` when a slug exists.

## Out of scope for this slice (follow-ups)

- Language revival / answering in a culture's own language (the other half of the North Star). This slice is English-grounding only.
- Saving / sharing answers (the researcher copilot has `saveAnswer` into a workspace; the public page does not persist anything).
- Elasticsearch-backed relevance (this slice uses SQL keyword scoring, mirroring the copilot).
- Rate limiting / abuse controls on the public endpoint.

## Reference

Mirror of `AhgResearch\Services\ResearchCopilotService` grounding approach, adapted for a public audience and constrained to published records. Tracked under heratio#1208.
