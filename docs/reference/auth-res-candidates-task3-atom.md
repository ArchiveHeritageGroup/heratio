# Authority Resolution Engine - Task 3: Candidate Generation (AtoM side)

Mirror of `auth-res-candidates-task3.md` for the **AtoM Heratio** (Symfony 1.4) side of the engine. Same algorithm, same schema, same ranks as the Laravel-side service so any mention/value pair scores identically on both platforms.

Status: implemented 2026-05-19 in `atom-ahg-plugins/ahgAuthorityResolutionPlugin/`. Tasks 0, 1, 2, 8 already shipped on the AtoM side; Tasks 4-7 still outstanding.

## Setup

- Plugin: `/usr/share/nginx/archive/atom-ahg-plugins/ahgAuthorityResolutionPlugin/` (already installed + enabled in `atom_plugin`).
- Namespace: `AtomFramework\Services\AuthorityResolution\…` (and `AtomFramework\Services\AuthorityResolution\Adapters\…`). NOT `AtomExtensions\…` - docs that say otherwise are stale (see `feedback_atom_namespace.md`).
- DB access: `Illuminate\Database\Capsule\Manager as DB`. Database is `archive`.
- No Laravel app helpers. `date('Y-m-d H:i:s')` everywhere instead of `now()`.
- Schema: `ahg_mention_candidate` already exists (Task 0 install). No new DDL.
- Default top-N: `ahg_settings.authority_resolution.candidate_top_n` = `"5"`, seeded manually with `database/seed_candidate_config.sql`. Fallback to 5 in code if the row is missing or empty. Override via `--top=N` or the `$topN` argument on `CandidateGeneratorService::generate()`.

## File map

| Path (under `atom-ahg-plugins/ahgAuthorityResolutionPlugin/lib/`) | Purpose |
|---|---|
| `Services/Adapters/CandidateAdapterInterface.php` | `supports($entityType)` + `search($query, $entityType, $limit)` contract |
| `Services/Adapters/MysqlActorAdapter.php` | PERSON/ORG - `actor.entity_type_id IN (132,131)` JOIN `actor_i18n` ON id, `authorized_form_of_name LIKE '%query%'` |
| `Services/Adapters/MysqlTermAdapter.php` | GPE/PLACE/LOC - `term.taxonomy_id=42` JOIN `term_i18n` ON id, `name LIKE '%query%'` |
| `Services/Adapters/FusekiAgentAdapter.php` | STUB returning `[]`. Future SPARQL against rico:Agent. |
| `Services/Adapters/FusekiPlaceAdapter.php` | STUB returning `[]`. Future SPARQL against rico:Place. |
| `Services/CandidateGeneratorService.php` | Orchestrator: load mention, filter adapters, score, sort, transactional DELETE + INSERT |
| `task/authResGenerateCandidatesTask.class.php` | `php symfony auth-res:generate-candidates [mention_id] [--object-id=] [--show] [--top=]` |
| `database/seed_candidate_config.sql` | `INSERT IGNORE` for the `candidate_top_n` setting |

## SF1.4 task quirks

Two non-obvious patterns the task file follows, mirrored from `authResWriteProvenanceTask.class.php`:

1. **Explicit `require_once`** at the top of the task class file for the interface + 4 adapters + service. AtoM's Symfony 1.4 boots Capsule for plugin tasks but does NOT PSR-4-autoload our `AtomFramework\Services\…` namespace. Without the requires, the task crashes with `Class not found` even though the files exist.
2. Task discovery via `$this->namespace = 'auth-res'; $this->name = 'generate-candidates';` and class name `authResGenerateCandidatesTask` (the SF1.4 convention - file basename = class name, no PSR-4 in `lib/task/`).
3. `parent::execute($arguments, $options);` is required to bootstrap the database connection (otherwise Capsule has no PDO).

## Scoring spec

Identical (byte-for-byte) to the Laravel-side service so a given mention value scores the same against the same display name on either platform.

```php
$q = trim(mb_strtolower($mentionValue, 'UTF-8'));
$c = trim(mb_strtolower($candidateDisplayName, 'UTF-8'));
if ($q === '' || $c === '') return 0.0;
similar_text($q, $c, $percent);
$score = $percent / 100.0;
if (strpos($c, $q) !== false) $score = min(1.0, $score + 0.05);
if ($q === $c) $score = 1.0;
return round($score, 4);
```

Sort `(composite_score desc, display_name asc)` for deterministic ordering on ties.

`composite_score` at Task 3 is just the name-similarity score. `evidence_signals` and `evidence_data` stay NULL; Task 4 will populate them with the per-dimension match/conflict/silent/absent matrix.

## Persistence pattern

`DB::transaction(function () use (...) { ... })` around:

1. `DELETE FROM ahg_mention_candidate WHERE mention_id = ?` (wipe the existing list).
2. `INSERT` rank 1..N from the sorted top-N.

So re-running the task on the same mention is idempotent (no leftover stale candidates) AND the persisted list is always a rank-coherent snapshot - never a half-replaced mix.

## Demo output (5 mentions on objects 901990 / 901851)

Picked one mention per `(entity_type, entity_value)` group from objects 901990 / 901851:

```
sudo -u www-data php symfony auth-res:generate-candidates 138 --show   # PERSON Frederick Douglass
sudo -u www-data php symfony auth-res:generate-candidates 168 --show   # PERSON Mark Twain
sudo -u www-data php symfony auth-res:generate-candidates 82  --show   # ORG    FIC
sudo -u www-data php symfony auth-res:generate-candidates 25  --show   # GPE    London
sudo -u www-data php symfony auth-res:generate-candidates 834 --show   # GPE    New York
```

Ranked results persisted into `ahg_mention_candidate`:

| Mention | Type | Value | Rank 1 (source / id / score) | Other ranks |
|---|---|---|---|---|
| #138 | PERSON | Frederick Douglass | mysql_actor / id=902224 / 1.0000 | (none) |
| #168 | PERSON | Mark Twain | mysql_actor / id=902281 / 1.0000 | (none) |
| #82  | ORG    | FIC | mysql_actor / id=902122 / 1.0000 | #2 British Post Office 0.3227, #3 Asian Warehousing... (Fictional) 0.1611 |
| #25  | GPE    | London | mysql_term / id=901059 / 1.0000 | (none) |
| #834 | GPE    | New York | mysql_term / id=901918 / 1.0000 | (none) |

All five #1 ranks are the correct authority record. The AHG sample IOs (901990 / 901851 / 902434) ship with matching authority rows pre-seeded, so the exact-match boost dominates. Mention #82 (ORG "FIC") is interesting because the substring boost is what surfaces "Asian Warehousing... (Fictional)" via the "Fictional" substring at score 0.1611 - shows the algorithm IS picking up partial matches even when the human-correct answer also exists.

A more interesting ambiguous case is mention #159 (PERSON "Lloyd"), which returns 2 candidates ranked correctly:

```
#1 [mysql_actor]  score=1.0000  id=902263  "Lloyd"
#2 [mysql_actor]  score=0.4848  id=901495  "David Lloyd George"
```

`David Lloyd George` benefits from the +0.05 substring bonus (since "lloyd" appears in "david lloyd george") but is still well below the exact "Lloyd" actor - correct behaviour.

## Quirks / caveats

- The Places taxonomy (`term.taxonomy_id = 42`) on the AtoM `archive` DB has 180 rows. The MysqlTermAdapter `ORDER BY LENGTH(name)` keeps short exact matches at the top of the per-adapter result before similarity scoring re-sorts the merged blend.
- `entity_type_id` for PERSON / ORG was verified by SELECT against `term_i18n` (132 = Person, 131 = Corporate body). These are AtoM's standard taxonomy IDs - same on the Laravel side because the schema is cloned.
- Duplicate suppression in the gather step uses `source|authority_id|fuseki_uri|display_name` as the dedup key. A single authority that appears in multiple adapters (e.g. a MySQL actor that also has a Fuseki agent IRI later) WILL surface twice; Task 4 will introduce cross-source identity reconciliation.
- The task accepts either a positional `mention_id` argument OR `--object-id=N` to generate candidates for every mention on a given IO in one pass.

## Manual seed (one-off)

```bash
P=$(cd /usr/share/nginx/archive && php -r '$c=include "config/config.php"; echo $c["all"]["propel"]["param"]["password"];')
MYSQL_PWD="$P" mysql --defaults-file=/dev/null -u root archive \
  < /usr/share/nginx/archive/atom-ahg-plugins/ahgAuthorityResolutionPlugin/database/seed_candidate_config.sql
```

The `--defaults-file=/dev/null` is per `feedback_mysql_defaults_file.md` - prevents user-level `~/.my.cnf` from corrupting connection options.

## Cross-platform parity check

Same five mentions, same authority store: ranks must match between the AtoM-side `php symfony auth-res:generate-candidates` and the Laravel-side `php artisan auth-res:generate-candidates`. Differences would indicate either (a) the scoring algorithm drifted, or (b) the LIKE result sets differ between databases - which on this server they don't, since `archive` and `heratio` actor stores are not yet cross-loaded but both run the identical algorithm against their own pool.
