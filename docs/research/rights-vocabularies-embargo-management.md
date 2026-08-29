# Rights vocabularies & Embargo management — gaps, incomplete code, and suggested enhancements

This note summarises the current state (gaps and incomplete code) for rights vocabularies and embargo management in the Research area, and proposes concrete enhancements and a phased implementation plan. The aim is to make rights metadata authoritative, machine-actionable (for access decisions and exports), and auditable.

1) First-look: gaps (what is missing now)

- Centralised rights vocabulary service
  - There is no single, discoverable service that publishes the canonical rights vocabulary terms (licenses, embargo statuses, donor restrictions, ODRL fragments) to the rest of the application. Multiple packages reference rights terms, but they pull from different sources or inline lists.

- Embargo lifecycle enforcement
  - Embargoes can be stored as attributes or restrictions, but there is no consistent automatic enforcement path (scheduled unblocking / notification / RiC Event on embargo expiry).

- Provenanced rights assertions
  - Rights decisions (who authorised an embargo, under what mandate, which document supports it) are not consistently captured as provenance records tied to the RiC/Event model.

- Granular access-control mapping
  - The current middleware enforces coarse ODRL-like rules in some controllers, but there's no general engine that maps vocabulary terms to access-control policies (e.g. embargo -> deny public access until date X; donor restriction -> restrict to researcher group Y).

- Export coverage
  - Rights vocabulary and embargo metadata are not fully surfaced in exports (RiC JSON-LD, IIIF manifests) for downstream consumers; exports often include only human-readable notes.

2) Incomplete code (concrete files & locations to inspect / that I found)

- Vocabulary storage and lookup
  - Search the repo for `rights` / `license` / `embargo` keys. Look at:
    - packages/ahg-extended-rights/ (expected host for rights helpers)
    - packages/ahg-vocabulary-manage/ (if present) — verify if a `rights` vocabulary exists.
  - Evidence: multiple ad-hoc lists in controller/view code and occasional `config/rights.php`-style constants instead of a shared service.

- Embargo persistence & cron hooks
  - packages/ahg-research/src/Services/ResearchService.php and related controllers may attach embargo metadata to research outputs; check:
    - packages/ahg-research/database/install.sql (any embargo fields?)
    - packages/ahg-research/src/Controllers/* (look for `embargo` or `access_end` fields)
  - Evidence: a scheduled `reminders` command exists for donors; a similar scheduled release job for embargo expiry is not present or incomplete.

- Enforcement middleware
  - Look at packages/ahg-research/src/Middleware; ODRL middleware exists in other packages (we saw `OdrlPolicyMiddleware`), but mapping from vocabulary term -> enforcement decision (deny/allow/conditional) is not centralised.

- Export mapping
  - packages/ahg-ric (or export services) produce RiC fragments but do not always include embargo nodes or rights vocabulary URIs for each restriction.

3) Suggested enhancements (concrete changes, priority, and acceptance criteria)

High priority (must-have)

- Create a RightsVocabularyService (central source of truth)
  - Responsibilities: store canonical term URIs, human labels, description, a machine-readable policy mapping (e.g. embargo -> { effect: "deny_public", until: date-field }), and crosswalks to ODRL fragments and SPDX/Creative Commons where appropriate.
  - Implementation: `packages/ahg-rights/src/Services/RightsVocabularyService.php` backed by a small `rights_vocabulary` table and optional manifest JSON seed. Provide API: `get($term)`, `search($q)`, `resolvePolicy($term)`.
  - Acceptance: all lookups in research, exports, and middleware call RightsVocabularyService instead of inline term lists.

- Add Embargo lifecycle job and audit events
  - Implement a scheduled job `php artisan research:embargo:check` that:
    - Finds embargoed items with expiry <= now and not yet released.
    - Emits a RiC `Event` (or an application event) `embargo.released` and records it in the `research_activity_log`.
    - Optionally notifies owners and admins via the inbox/notification system.
  - Acceptance: running the job marks embargoed items as released, RiC Event created, and notifications enqueued.

Medium priority (very useful)

- Centralised Enforcement Adapter
  - Create an `AccessPolicyEngine` which accepts an item and an actor and consults:
    1. Item-level rights (embargo, donor restrictions)
    2. RightsVocabularyService.resolvePolicy()
    3. External rules (site-wide retention/POPIA)
  - The engine returns a decision object { allowed:boolean, reason_code, evidence }. Plug the engine into middleware (replace ad-hoc ODRL checks) and export endpoints.
  - Acceptance: middleware uses the engine and unit tests cover representative cases (public request during embargo blocked; researcher group allowed with justification).

- Provenanced rights assertions
  - Whenever a rights decision is made (embargo set/released, donor restriction added, licence assigned), create an auditable provenance record: `provenance_type = 'rights_assertion'`, payload includes policy term, mandate reference, actor_id, and evidence reference (document id). Store in RiC `Event` model (or `ai_provenance` style table for non-AI actions). Link to the item node.
  - Acceptance: UI shows provenance card on an item showing who set the embargo and which document justified it.

Low priority (nice-to-have)

- Rights vocabulary federation
  - Support mapping to external vocabularies (e.g. SPDX, CC, ODRL registries) and allow import of term lists.

- Export enhancements
  - Ensure RiC / JSON-LD / IIIF engine includes embargo nodes, rights URIs, and provenance references.

4) Concrete file/patch plan (small, reviewable PRs)

PR A — RightsVocabularyService + table + seed
- Files:
  - packages/ahg-rights/database/migrations/2026_06_XX_create_rights_vocabulary_table.php
  - packages/ahg-rights/src/Services/RightsVocabularyService.php
  - packages/ahg-rights/src/Providers/RightsServiceProvider.php (bind singleton)
  - seed: packages/ahg-rights/data/seed-rights.json
- Acceptance: service returns term metadata; tests for get/search pass.

PR B — AccessPolicyEngine + middleware adapter
- Files:
  - packages/ahg-rights/src/AccessPolicy/AccessPolicyEngine.php
  - packages/ahg-research/src/Middleware/ResearchAccessMiddleware.php (use engine)
- Acceptance: requests for embargoed item return 403 for anonymous users and 200 for allowed actors; integration tests added.

PR C — Embargo expiry job + RiC Event emission
- Files:
  - packages/ahg-research/src/Jobs/CheckEmbargoes.php
  - packages/ahg-research/src/Services/EmbargoService.php
  - tests: packages/ahg-research/tests/Feature/EmbargoReleaseTest.php
- Acceptance: job marks items released and adds activity log entry + RiC Event.

PR D — Export mapping
- Files:
  - packages/ahg-ric/src/Services/RiCExporter.php (include embargo nodes)
  - tests: riC export includes embargo nodes
- Acceptance: sample export contains embargo node with provenance link.

5) Acceptance criteria & tests (summary)

- RightsVocabularyService: unit tests for `get`, `search`, and `resolvePolicy`. Seed included. All code paths use service instead of inline lists.
- AccessPolicyEngine: unit tests for allow/deny cases; middleware enforced in integration tests.
- Embargo job: feature test that creates an embargoed item with expiry in past, runs the job, and asserts item is released + RiC Event exists + notification queued.
- Export: RiC JSON-LD includes embargo node + linked provenance id.

6) Risks & mitigations

- Policy divergence: different teams may have existing ad-hoc rights lists; mitigation: provide an import script and compatibility mode; run a repo-wide grep and convert references in small PRs.
- Performance: evaluating policies on every request may add latency; mitigation: cache policy resolution results and use ETag/HTTP caching for items.

I will now write this as a new file at `/usr/share/nginx/heratio/docs/research/rights-vocabularies-embargo-management.md` unless you want a different filename. Reply "go" to have me write the file, or say "change" and tell me the filename you prefer.

Status: very good
Next action — outstanding issue to work on
1. Create RightsVocabularyService + table + seed (PR A).  
2. Implement AccessPolicyEngine + middleware adapter (PR B).  
3. Implement Embargo expiry job + RiC Event emission (PR C).  
4. Wire exporter to include embargo nodes in RiC/JSON-LD (PR D).