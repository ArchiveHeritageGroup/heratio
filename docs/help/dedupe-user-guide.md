> Heratio Help Center article. Category: Data Quality.

# Duplicate Detection (Dedupe)

Duplicate Detection helps you find, compare, and resolve duplicate archival descriptions in your catalogue. It scans information objects for similar titles, matching or near-matching identifiers, shared dates and creators, and identical file checksums, then records each suspected pair so a reviewer can compare the two records side by side and decide whether to merge them, confirm them, or dismiss the match. Detection behaviour is driven by configurable rules with adjustable similarity thresholds, and a real-time check can warn data-entry staff about possible duplicates while they are still typing a title. The whole module lives under the admin area and is restricted to staff with administrator access.

## Overview

Large catalogues accumulate duplicate descriptions over time: the same item described twice during separate accessions, a record re-imported during a migration, or two descriptions that share an identifier by mistake. The Dedupe module gives you a single place to surface those overlaps and work through them methodically. Each suspected duplicate is stored as a pair of records (Record A and Record B) with a similarity score between 0 and 1, the detection method that flagged it, and a status of pending, confirmed, merged, or dismissed. Reviewers triage the pending queue, open a comparison view, and act on each pair without leaving the admin interface.

Detection never deletes or changes your descriptions on its own. It only records candidate matches; a person decides what happens next.

## Key features

- **Dashboard** with counts of total, pending, confirmed, merged, and dismissed duplicates, the number of active rules, the highest-scoring pending pairs, recent scan jobs, and a breakdown by detection method.
- **Detection methods**: title similarity, exact identifier match, fuzzy identifier match, date plus creator match, file checksum match, and a combined multi-factor analysis.
- **Browse and filter** the full duplicate list by status, by detection method, and by minimum similarity score, with pagination.
- **Side-by-side compare** of two candidate records showing title, identifier, level of description, repository, extent, and scope and content, with matching fields highlighted.
- **Merge** workflow where you choose which record stays as the primary.
- **Dismiss** to mark a pair as not a duplicate.
- **Detection rules** you can create, edit, enable or disable, prioritise, set a similarity threshold on, mark as blocking, and optionally scope to a single repository.
- **Scan jobs** that queue a catalogue sweep across all repositories or a single repository.
- **Reports** covering monthly trends, a per-method breakdown with average scores, a false-positive rate, and the records involved in the most duplicate pairs.
- **Real-time duplicate check** API used by data-entry widgets to warn about similar existing titles as a record is being typed.

## How to use

All Dedupe screens sit under **Admin -> Duplicate Detection** and require administrator access.

### Open the dashboard

1. Go to **/admin/dedupe**.
2. Review the headline counts and the list of top pending pairs, ordered by similarity score.
3. Use the quick links to jump to Browse, the pending queue, rules, or the report.

### Run a scan

1. From the dashboard, click **Start Duplicate Scan**, or go to **/admin/dedupe/scan**.
2. Choose the scan scope: all repositories, or a single repository selected from the list.
3. Submit. This queues a scan job with a status of pending. The job is processed by a background command, so newly found pairs appear in the queue once that run completes.

### Triage suspected duplicates

1. Go to **/admin/dedupe/browse**.
2. Filter by status (for example, pending), by detection method, or by a minimum similarity score.
3. For any pair, choose one of the actions:
   - **Compare** (**/admin/dedupe/compare/{id}**): see both records field by field, with matching values highlighted, before deciding.
   - **Merge** (**/admin/dedupe/merge/{id}**): open the merge form.
   - **Dismiss**: mark the pair as not a duplicate. The pair's status becomes dismissed and the reviewer and timestamp are recorded.

### Merge two records

1. From Browse or Compare, click **Merge** to open **/admin/dedupe/merge/{id}**.
2. Choose which of the two records should remain as the **primary**.
3. Submit. The pair is flagged with a status of merged, and the reviewer and timestamp are recorded. The underlying data transfer (slugs, digital objects, and field choices) is completed by a background task.

### Manage detection rules

1. Go to **/admin/dedupe/rules** to see every rule ordered by priority, including which repository (if any) each rule is scoped to.
2. Click **Create** (**/admin/dedupe/rule/create**) to add a rule, or **Edit** (**/admin/dedupe/rule/{id}/edit**) to change one.
3. Set the rule name, the rule type (title similarity, identifier exact, identifier fuzzy, date plus creator, file checksum, combined, or custom), the similarity threshold (0 to 1), the priority (higher runs first), an optional repository, optional JSON configuration, and the enabled and blocking flags.
4. A blocking rule is intended to stop a save when a duplicate is found; a non-blocking rule only records the candidate. Delete a rule from the rules list when it is no longer needed.

### View reports

1. Go to **/admin/dedupe/report**.
2. Review the monthly detection trend, the per-method breakdown with average similarity scores, the false-positive rate (the share of detections that were dismissed), and the records that appear in the most pending pairs.

### Real-time check during data entry

Data-entry widgets can call the **/api/dedupe/realtime** endpoint with a title as you type. When the title is at least five characters long, it returns up to ten existing records with similar titles and a similarity percentage, so staff can spot a likely duplicate before saving a new description. This endpoint is available to any authenticated user.

## Configuration

- **Detection rules** are the primary configuration surface. They are stored per rule and seeded with sensible defaults on first install: Title Similarity, Identifier Exact Match, Identifier Fuzzy Match, Date Range plus Creator, File Checksum Match, and Combined Analysis. Each rule carries its own threshold, priority, enabled flag, blocking flag, optional repository scope, and a JSON config block for method-specific options.
- **Default similarity threshold**: the rule-create form pre-fills its threshold from the `authority_dedup_threshold` setting (default `0.80`), managed through AHG Settings.
- **Per-rule threshold**: each rule overrides the default with its own threshold value between 0 and 1.
- **Repository scope**: leave the repository blank for a global rule, or select a repository to limit a rule to one collection.
- **Tables**: the module uses `ahg_duplicate_detection` (candidate pairs), `ahg_duplicate_rule` (rules), `ahg_merge_log` (merge audit trail), `ahg_file_checksum` (file fingerprints for exact duplicate detection), and `ahg_dedupe_scan` (scan jobs). If these tables are missing, every screen shows a "not configured" page instead of an error; run the package install SQL to create them.

## References

- Source package: `packages/ahg-dedupe/`
- GH issue: [#559](https://github.com/ArchiveHeritageGroup/heratio/issues/559)
