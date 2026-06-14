Provenance checks for Heratio Research

This checklist collects concrete, automatable provenance checks for the Research module and adjacent services.

1. ai_provenance table present
   - Confirm table exists and columns: id, provider, prompt, response, model, confidence, user_id, request_id, accepted_at, created_at.

2. AI endpoints write ai_provenance
   - For each LLM/TTS endpoint (writing studio, question-builder, analysis-bridge, exhibition docent), confirm an ai_provenance row is created with prompt/model/response/request_id and the returned id is logged.

3. RiC Event entries for curator decisions
   - Curator actions that accept/reject AI suggestions or perform appraisal must create a RiC Event with agent (user id), target (record id), event type, timestamp, and evidence link to ai_provenance.

4. Access to provenance data is auditable
   - The provenance query endpoints are permissioned and access queries are logged to the audit trail.

5. Redaction support
   - Verify there is an admin flow to redact PII from ai_provenance and RiC events while preserving relational integrity (replace personal fields with hashed placeholders).

6. Export & SPARQL diagnostics
   - Exports (RiC JSON-LD) include provenance relations. Include sample SPARQL diagnostic queries under docs/diagnostics/provenance/.

7. Metric instrumentation
   - AI call counts, accept/reject ratios, failed-call rates are emitted to metrics (Prometheus/Grafana) for the research namespace.

8. Tests
   - Feature tests assert ai_provenance writes and RiC Event creation for one example per AI-enabled slice.

How to run checks (suggested commands)
- Run a quick DB check (SQL): SELECT column_name FROM information_schema.columns WHERE table_name='ai_provenance';
- Run the automated provenance test suite: vendor/bin/phpunit --filter ProvenanceTest

Status: very good
Next action: 1) Add automated provenance tests to packages/ahg-research/tests/Feature (list of tests to add)
