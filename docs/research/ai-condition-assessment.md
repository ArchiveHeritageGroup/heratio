# AI Condition Assessment

This document summarises the current AI-based condition-assessment features, gaps, incomplete code, and enhancements for the Research module.

1. Gaps
- No central AI provenance enforcement across all condition-assessment endpoints.
- Annotation acceptance workflows missing; AI suggestions can be applied without human review in some places.
- No model-versioning stored with AI results; cannot trace which model produced a suggestion.

2. Incomplete code
- `ai_provenance` writes are present in only some AI call sites (investigate: writing studio, analysis bridge, question builder).
- Condition-assessment pipeline has partial steps: image pre-processing exists; AI tagging code uses local prompt templates but lacks consistent error handling.

3. Enhancements suggested
- Enforce ai_provenance recording: prompt, model, response, confidence, user_id, request_id.
- Add explicit Accept/Reject UI for AI-suggested annotations before writing to canonical annotation stores.
- Add model-version metadata and allow rollbacks or replays of suggestions.
- Add a jobs queue for bulk AI runs with progress and retry.

4. File path
- docs/research/ai-condition-assessment.md


Status: very good
