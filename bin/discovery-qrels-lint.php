#!/usr/bin/env php
<?php
/**
 * discovery-qrels-lint — validate a discovery qrels CSV against the schema
 * defined in GitHub issue #16.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Heratio is free software: you can redistribute it and/or modify it under
 * the terms of the GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 *
 * Schema (CSV, header row required):
 *   query_id,query_text,query_type,object_id,relevance
 *
 * Validation rules (all hard-fail unless flagged WARN):
 *   1. Header row matches column list exactly.
 *   2. query_type ∈ {known_item, person, place, topical, hierarchical}.
 *   3. relevance  ∈ {0, 1, 2}.
 *   4. Every query_id has at least one row with relevance = 2.
 *   5. Every (query_id, object_id) pair is unique.
 *   6. query_text is identical across all rows that share a query_id.
 *   7. WARN: object_id exists in atom.information_object (cross-DB, optional).
 *
 * Usage:
 *   php bin/discovery-qrels-lint.php tests/discovery/qrels.csv
 *   php bin/discovery-qrels-lint.php tests/discovery/qrels.csv --no-db
 *   php bin/discovery-qrels-lint.php --help
 *
 * Exit: 0 = clean. 1 = hard-fail. 2 = usage error.
 */

const EXPECTED_HEADER = ['query_id', 'query_text', 'query_type', 'object_id', 'relevance'];
const VALID_QUERY_TYPES = ['known_item', 'person', 'place', 'topical', 'hierarchical'];
const VALID_RELEVANCES = [0, 1, 2];

[$path, $checkDb] = parseArgs($argv);

if (! is_readable($path)) {
    fwrite(STDERR, "ERROR: cannot read {$path}\n");
    exit(2);
}

$fh = fopen($path, 'r');
$header = fgetcsv($fh);
$errors = [];
$warnings = [];

// Rule 1 — header row matches exactly.
if ($header !== EXPECTED_HEADER) {
    $errors[] = sprintf(
        "Rule 1: header mismatch. expected=%s got=%s",
        json_encode(EXPECTED_HEADER),
        json_encode($header)
    );
    fclose($fh);
    report($path, $errors, $warnings);
    exit(1);
}

$rowsByQuery = [];
$pairs = [];
$lineNo = 1;
while (($row = fgetcsv($fh)) !== false) {
    $lineNo++;
    if (count($row) !== 5) {
        $errors[] = "line {$lineNo}: expected 5 columns, got " . count($row);
        continue;
    }
    [$qid, $qtext, $qtype, $oid, $rel] = $row;

    if (! in_array($qtype, VALID_QUERY_TYPES, true)) {
        $errors[] = "line {$lineNo}: Rule 2: query_type '{$qtype}' not in " . json_encode(VALID_QUERY_TYPES);
    }

    if (! ctype_digit((string) $rel) || ! in_array((int) $rel, VALID_RELEVANCES, true)) {
        $errors[] = "line {$lineNo}: Rule 3: relevance '{$rel}' not in " . json_encode(VALID_RELEVANCES);
    }

    if (! ctype_digit((string) $oid)) {
        $errors[] = "line {$lineNo}: object_id '{$oid}' is not a positive integer";
    }

    $pairKey = $qid . '|' . $oid;
    if (isset($pairs[$pairKey])) {
        $errors[] = "line {$lineNo}: Rule 5: duplicate (query_id={$qid}, object_id={$oid}) — first seen on line {$pairs[$pairKey]}";
    } else {
        $pairs[$pairKey] = $lineNo;
    }

    if (! isset($rowsByQuery[$qid])) {
        $rowsByQuery[$qid] = [];
    }
    $rowsByQuery[$qid][] = [
        'line' => $lineNo,
        'qtext' => $qtext,
        'oid' => (int) $oid,
        'rel' => is_numeric($rel) ? (int) $rel : null,
    ];
}
fclose($fh);

// Rule 4 — every query_id has at least one relevance=2.
// Rule 6 — query_text identical within each query_id.
foreach ($rowsByQuery as $qid => $rows) {
    $hasPositive = false;
    $firstText = $rows[0]['qtext'];
    foreach ($rows as $r) {
        if ($r['rel'] === 2) {
            $hasPositive = true;
        }
        if ($r['qtext'] !== $firstText) {
            $errors[] = "line {$r['line']}: Rule 6: query_text differs from first occurrence of '{$qid}' on line {$rows[0]['line']}";
        }
    }
    if (! $hasPositive) {
        $errors[] = "Rule 4: query_id '{$qid}' has no row with relevance=2";
    }
}

// Rule 7 — WARN: object_ids exist in atom.information_object.
if ($checkDb) {
    $allOids = [];
    foreach ($rowsByQuery as $qid => $rows) {
        foreach ($rows as $r) {
            $allOids[$r['oid']] = $r['line'];
        }
    }
    if (! empty($allOids)) {
        $missing = checkAtomIds(array_keys($allOids));
        foreach ($missing as $missingOid) {
            $line = $allOids[$missingOid] ?? '?';
            $warnings[] = "line {$line}: Rule 7: object_id={$missingOid} not found in atom.information_object";
        }
    }
}

report($path, $errors, $warnings);
exit($errors ? 1 : 0);


function parseArgs(array $argv): array
{
    $path = null;
    $checkDb = true;
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            echo "Usage: php bin/discovery-qrels-lint.php <path> [--no-db]\n";
            exit(0);
        }
        if ($arg === '--no-db') { $checkDb = false; continue; }
        if ($path === null) { $path = $arg; continue; }
        fwrite(STDERR, "ERROR: unexpected argument: {$arg}\n");
        exit(2);
    }
    if ($path === null) {
        fwrite(STDERR, "Usage: php bin/discovery-qrels-lint.php <path> [--no-db]\n");
        exit(2);
    }
    return [$path, $checkDb];
}

function checkAtomIds(array $oids): array
{
    $oids = array_unique(array_filter($oids, fn($o) => $o > 0));
    if (empty($oids)) {
        return [];
    }
    $list = implode(',', array_map('intval', $oids));
    // Prefix sentinel UNION row so we can distinguish "DB returned 0 rows" from
    // "DB unreachable" — shell_exec returns null in both cases.
    $sql = "SELECT 'OK_PROBE' AS id UNION ALL SELECT CAST(id AS CHAR) FROM information_object WHERE id IN ({$list})";
    $cmd = "mysql -u root atom -N -e " . escapeshellarg($sql) . " 2>/dev/null";
    $out = shell_exec($cmd);
    if ($out === null || strpos($out, 'OK_PROBE') === false) {
        // DB unreachable — skip silently. Issue #16 marks Rule 7 as warn-only.
        return [];
    }
    $tokens = preg_split('/\s+/', trim($out)) ?: [];
    $present = [];
    foreach ($tokens as $tok) {
        if ($tok === '' || $tok === 'OK_PROBE') continue;
        $present[] = (int) $tok;
    }
    return array_values(array_diff($oids, $present));
}

function report(string $path, array $errors, array $warnings): void
{
    $rel = realpath($path) ?: $path;
    if (empty($errors) && empty($warnings)) {
        echo "OK  {$rel}: no issues\n";
        return;
    }
    foreach ($warnings as $w) {
        echo "WARN  {$w}\n";
    }
    foreach ($errors as $e) {
        echo "FAIL  {$e}\n";
    }
    $sum = sprintf("%d error(s), %d warning(s)", count($errors), count($warnings));
    echo ($errors ? "FAIL" : "OK") . "  {$rel}: {$sum}\n";
}
