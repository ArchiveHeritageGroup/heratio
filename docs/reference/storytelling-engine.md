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

## Notes
- AI via the gateway (LlmService) - no direct node calls.
- Not yet linked from a menu - reach it at /admin/stories (discoverability link is a follow-up;
  the AI Tools menu lives in the locked ahg-ai-services).
- Next slices: save + publish to a public story page; "On this day" (date-driven); per-object
  deep-dive; multilingual via the translation service; audio version via TTS.
