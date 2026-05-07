Issue 77 — Fuseki / RIC Triplestore sync settings — status update

Summary of work done:
- Added coordinator and async job to honor fuseki settings:
  - packages/ahg-ric/src/Services/FusekiSyncService.php (new)
  - packages/ahg-ric/src/Jobs/FusekiSyncJob.php (new)
- Wired callers:
  - packages/ahg-provenance-ai/src/Services/InferenceService.php now uses FusekiSyncService
  - packages/ahg-provenance-ai/src/Services/OverrideService.php updated to use FusekiSyncService
- Fixed job dispatch signature so enqueueing works correctly.
- Branch: feat/ric/fuseki-sync-settings pushed to origin (includes the above changes).

Outstanding items (not yet complete):
- Search & replace remaining direct SparqlUpdateService callers across the codebase and update them to use FusekiSyncService.
- Ensure batch/chunking behavior honors `fuseki_batch_size` for bulk sync operations; add robust chunking where needed.
- Implement integrity and orphan cleanup commands (fuseki_integrity_schedule, fuseki_orphan_retention_days) and scheduling hooks.
- Add unit/integration tests for FusekiSyncService behavior and job execution.
- Documentation/README for the feature and deployment notes.

Next steps (recommended):
1. Finish replacing any remaining direct SparqlUpdateService usages with FusekiSyncService (I can do this and prepare a follow-up commit).
2. Implement batch chunking and add basic tests.
3. Implement integrity/orphan commands and schedule registration.
4. Create a PR from branch `feat/ric/fuseki-sync-settings` for review; merge after verification in staging.

How to smoke-test (quick):
- Deploy branch, clear caches and reload PHP-FPM, restart queue worker.
- Toggle settings and observe logs/queue as described in the branch README.

Note: I will NOT close this issue. I’ve left it open for tracking remaining work.

If you want me to proceed and finish the remaining edits and tests, reply: `Proceed and commit` and I will finish and push the follow-up commits.