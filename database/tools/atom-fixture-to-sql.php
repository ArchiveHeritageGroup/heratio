<?php
/*
 * Heratio — atom-fixture-to-sql
 *
 * Phase 2 of the standalone install plan. One-shot converter that turns
 * an AtoM Symfony 1 / Propel YAML fixture into a re-runnable SQL file of
 * `INSERT IGNORE` statements suitable for `mysql heratio < <file>.sql`.
 *
 * Usage:
 *   php database/tools/atom-fixture-to-sql.php \
 *       /usr/share/nginx/archive/data/fixtures/taxonomyTerms.yml \
 *       database/seeds/00_taxonomies.sql
 *
 * Approach (two passes):
 *   1) Build a symbol-table:  symbolic_id  ->  numeric id
 *      (e.g. QubitTaxonomy_root -> 30) by walking every top-level entry
 *      and recording its `id` field. Symbolic refs in `parent_id`,
 *      `source_id`, etc. are resolved against this table on emit.
 *   2) Emit INSERT IGNORE rows. For each top-level entry under a model:
 *        - scalar fields  -> INSERT INTO <base_table>
 *        - nested-array fields whose keys look like culture codes ->
 *          INSERT INTO <base_table>_i18n one row per culture
 *
 * Model -> base table is resolved via the MODEL_MAP below (drop "Qubit"
 * prefix and snake_case; some models map to differently-named tables).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing iSystems
 * AGPL-3.0-or-later.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

// ----- model -> base table mapping ------------------------------------------
// Most map by "drop Qubit prefix, snake_case" but we list explicitly so the
// converter is total: any unknown top-level model causes a hard error.
const MODEL_MAP = [
    'QubitTaxonomy'      => 'taxonomy',
    'QubitTerm'          => 'term',
    'QubitMenu'          => 'menu',
    'QubitSetting'       => 'setting',
    'QubitAclGroup'      => 'acl_group',
    'QubitAclPermission' => 'acl_permission',
    'QubitStaticPage'    => 'static_page',
    'QubitNote'          => 'note',
    'QubitRelation'      => 'relation',
    'QubitStatus'        => 'status',
    'QubitUser'          => 'user',
    'QubitContactInformation' => 'contact_information',
    'QubitRepository'    => 'repository',
];

// Recognised culture codes — present as keys under an i18n field.
const CULTURE_CODES = [
    'ar', 'bs', 'ca', 'ca@valencia', 'cy', 'de', 'de-CH', 'el',
    'en', 'es', 'eu', 'fa', 'fi', 'fr', 'gl', 'hr', 'hu', 'id',
    'it', 'ja', 'ka', 'ko', 'lo', 'mi', 'mk', 'nb', 'nl', 'no',
    'pl', 'pt', 'pt_BR', 'ro', 'ru', 'sl', 'sr', 'sv', 'th',
    'tr', 'uk', 'uz', 'vi', 'zh', 'zh_TW',
];

// ----- argv -----------------------------------------------------------------
[$_, $inFile, $outFile] = array_pad($argv, 3, null);
if (!$inFile || !$outFile) {
    fwrite(STDERR, "Usage: php atom-fixture-to-sql.php <input.yml> <output.sql>\n");
    exit(2);
}
if (!is_readable($inFile)) {
    fwrite(STDERR, "ERROR: input not readable: $inFile\n");
    exit(2);
}

$data = Yaml::parseFile($inFile);
if (!is_array($data) || empty($data)) {
    file_put_contents($outFile, headerComment($inFile) . "-- (input file had no fixture data — empty stub)\n");
    fwrite(STDERR, "wrote empty stub to $outFile (input parsed empty)\n");
    exit(0);
}

// Empty model wrappers (e.g. fixtures.yml is all comments) → write a stub.
$modelCount = count(array_filter($data, fn($v) => is_array($v) && !empty($v)));
if ($modelCount === 0) {
    file_put_contents($outFile, headerComment($inFile) . "-- (input file had no fixture data — empty stub)\n");
    fwrite(STDERR, "wrote empty stub to $outFile\n");
    exit(0);
}

// ----- pass 1: build symbol table -------------------------------------------
// First sub-pass: record explicit ids and find max per model.
$symbolToId = [];           // symbol -> numeric id
$nextSyntheticId = [];      // model -> next id to assign for entries lacking one

foreach ($data as $model => $entries) {
    if (!is_array($entries)) continue;
    if (!isset(MODEL_MAP[$model])) {
        fwrite(STDERR, "WARN: unknown model $model — skipping\n");
        continue;
    }
    $maxId = 0;
    foreach ($entries as $symbol => $row) {
        if (!is_array($row)) continue;
        if (isset($row['id']) && is_int($row['id'])) {
            $symbolToId[$symbol] = $row['id'];
            if ($row['id'] > $maxId) $maxId = $row['id'];
        }
    }
    // Reserve a high range above the max for synthetic IDs so they don't
    // collide with anything else seeded.
    $nextSyntheticId[$model] = max($maxId + 1, 10000);
}

// Second sub-pass: assign synthetic ids to entries that have a name-i18n
// payload but no explicit id. AtoM's symfony loader auto-increments; we
// pre-assign deterministically so re-runs are stable.
foreach ($data as $model => $entries) {
    if (!is_array($entries) || !isset(MODEL_MAP[$model])) continue;
    foreach ($entries as $symbol => $row) {
        if (!is_array($row)) continue;
        if (isset($row['id'])) continue;
        // Only assign if the row has any payload to insert.
        $hasPayload = false;
        foreach ($row as $k => $v) {
            if ($k === 'id') continue;
            $hasPayload = true; break;
        }
        if (!$hasPayload) continue;
        $assigned = $nextSyntheticId[$model]++;
        $symbolToId[$symbol] = $assigned;
        $data[$model][$symbol]['id'] = $assigned;
    }
}

// ----- model-specific column overrides --------------------------------------
// Some YAML fields don't map 1-1 to base-table columns:
//   - QubitSetting.value is a scalar in YAML but lives in setting_i18n.value
//   - QubitStaticPage.slug isn't on static_page; the slug table holds it
// Returns: ['drop' => [fields to drop from base], 'forceI18n' => [field => col]]
function modelOverrides(string $model): array {
    return match ($model) {
        'QubitSetting'    => ['drop' => [], 'forceI18n' => ['value' => 'value']],
        'QubitStaticPage' => ['drop' => ['slug', 'user_id'], 'forceI18n' => []],
        default           => ['drop' => [], 'forceI18n' => []],
    };
}

// Models whose rows must also have a row inserted into `object` table first
// (class-table inheritance — base class is QubitObject).
const OBJECT_BACKED_MODELS = [
    'QubitStaticPage',
    'QubitTaxonomy',
    'QubitTerm',
    'QubitMenu',
    'QubitRepository',
    'QubitContactInformation',
];

// ----- pass 2: emit SQL -----------------------------------------------------
$out = fopen($outFile, 'w');
fwrite($out, headerComment($inFile));
fwrite($out, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

$totalRows = 0;
$totalI18n = 0;

foreach ($data as $model => $entries) {
    if (!is_array($entries) || !isset(MODEL_MAP[$model])) continue;
    $baseTable = MODEL_MAP[$model];
    $i18nTable = $baseTable . '_i18n';

    fwrite($out, "-- =========================================================\n");
    fwrite($out, "-- $model -> $baseTable\n");
    fwrite($out, "-- =========================================================\n");

    $overrides = modelOverrides($model);

    foreach ($entries as $symbol => $row) {
        if (!is_array($row)) continue;

        $baseFields = [];
        $i18nByCulture = [];
        $sourceCulture = $row['source_culture'] ?? 'en';

        foreach ($row as $field => $value) {
            if (in_array($field, $overrides['drop'], true)) {
                continue;
            }
            if (isset($overrides['forceI18n'][$field])) {
                // Scalar that belongs in i18n under source_culture.
                $i18nCol = $overrides['forceI18n'][$field];
                $i18nByCulture[$sourceCulture][$i18nCol] = is_array($value) ? json_encode($value) : $value;
                continue;
            }
            if (is_array($value) && isI18nValue($value)) {
                foreach ($value as $culture => $localised) {
                    $i18nByCulture[$culture][$field] = $localised;
                }
            } else {
                $baseFields[$field] = resolveSymbolRef($value, $symbolToId);
            }
        }

        // -- if this is an object-backed model, emit `object` row first
        if (in_array($model, OBJECT_BACKED_MODELS, true) && isset($baseFields['id'])) {
            fwrite($out, sqlInsertOne('object', [
                'id'         => $baseFields['id'],
                'class_name' => $model,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
                'serial_number' => 0,
            ]));
            fwrite($out, "\n");
            $totalRows++;
        }

        // -- emit base row
        if (!empty($baseFields)) {
            fwrite($out, sqlInsertOne($baseTable, $baseFields));
            fwrite($out, "\n");
            $totalRows++;
        }

        // -- emit i18n rows (one per culture) --
        if (!empty($i18nByCulture)) {
            $idValue = $baseFields['id'] ?? ($symbolToId[$symbol] ?? null);
            if ($idValue === null) {
                fwrite(STDERR, "WARN: $symbol has i18n but no id — skipping i18n emit\n");
                continue;
            }
            foreach ($i18nByCulture as $culture => $localFields) {
                $localFields['id']      = $idValue;
                $localFields['culture'] = $culture;
                fwrite($out, sqlInsertOne($i18nTable, $localFields));
                fwrite($out, "\n");
                $totalI18n++;
            }
        }
    }
    fwrite($out, "\n");
}

fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
fwrite($out, "-- emitted $totalRows base rows, $totalI18n i18n rows\n");
fclose($out);

fwrite(STDERR, "wrote $outFile  ($totalRows base + $totalI18n i18n rows)\n");
exit(0);


// ============================================================================
// helpers
// ============================================================================

function headerComment(string $inFile): string {
    $bn = basename($inFile);
    $now = date('Y-m-d');
    return <<<HDR
    -- ============================================================================
    -- Heratio standalone install — seed data
    -- ============================================================================
    -- Generated $now from $inFile by database/tools/atom-fixture-to-sql.php
    -- Phase 2 of the standalone install plan (docs/standalone-install-plan.md).
    --
    -- Idempotent: every row is INSERT IGNORE. Re-runnable without side-effects.
    -- ============================================================================


    HDR;
}

/** True when an associative array's keys look like culture codes. */
function isI18nValue(array $value): bool {
    if (empty($value)) return false;
    $keys = array_keys($value);
    if (count(array_filter($keys, 'is_string')) !== count($keys)) return false;
    $hits = array_intersect($keys, CULTURE_CODES);
    return count($hits) > 0;
}

/** Resolve a symbolic ref like `QubitTaxonomy_root` to its numeric id; otherwise return $value as-is. */
function resolveSymbolRef($value, array $symbolToId) {
    if (is_string($value) && isset($symbolToId[$value])) {
        return $symbolToId[$value];
    }
    return $value;
}

function sqlInsertOne(string $table, array $fields): string {
    $cols   = array_keys($fields);
    $values = array_map('sqlValue', array_values($fields));
    $colSql = '`' . implode('`, `', $cols) . '`';
    $valSql = implode(', ', $values);
    return "INSERT IGNORE INTO `$table` ($colSql) VALUES ($valSql);";
}

function sqlValue($v): string {
    if ($v === null)   return 'NULL';
    if (is_bool($v))   return $v ? '1' : '0';
    if (is_int($v))    return (string) $v;
    if (is_float($v))  return (string) $v;
    if (is_array($v))  return "'" . addslashes(json_encode($v, JSON_UNESCAPED_UNICODE)) . "'";
    return "'" . str_replace(['\\', "'", "\0", "\n", "\r"], ['\\\\', "''", '\\0', '\\n', '\\r'], (string) $v) . "'";
}
