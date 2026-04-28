#!/usr/bin/env php
<?php
/**
 * discovery-qdrant-validate — sample N points from each Qdrant collection and
 * validate them against packages/ahg-discovery/schemas/{collection}.json.
 *
 * Implements the validation half of GitHub issue #23 — the schemas themselves
 * live next to this script. Run before / after a re-index to catch payload
 * drift early.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * AGPL-3.0-or-later. See <https://www.gnu.org/licenses/>.
 *
 * Usage:
 *   php bin/discovery-qdrant-validate.php                 # all 3 collections, 5 samples each
 *   php bin/discovery-qdrant-validate.php --sample=20     # more samples
 *   php bin/discovery-qdrant-validate.php --collection=archive_images
 *
 * Exits non-zero if any sample violates required-field or extra-field rules.
 */

const QDRANT_HOST = 'http://localhost:6333';
const SCHEMA_DIR  = __DIR__ . '/../packages/ahg-discovery/schemas';

$opts = parseArgs($argv);
$collections = $opts['collection']
    ? [$opts['collection']]
    : ['anc_records', 'archive_records', 'archive_images'];

$totalErrors = 0;
foreach ($collections as $name) {
    $schemaPath = SCHEMA_DIR . "/{$name}.json";
    if (! is_readable($schemaPath)) {
        fwrite(STDERR, "WARN  no schema for {$name} at {$schemaPath}\n");
        continue;
    }
    $schema = json_decode(file_get_contents($schemaPath), true);
    if (! is_array($schema)) {
        fwrite(STDERR, "FAIL  cannot parse schema for {$name}\n");
        $totalErrors++;
        continue;
    }

    $body = json_encode(['limit' => $opts['sample'], 'with_payload' => true, 'with_vector' => false]);
    $cmd = sprintf(
        'curl -s --max-time 10 -X POST %s -H %s -d %s 2>/dev/null',
        escapeshellarg(QDRANT_HOST . "/collections/{$name}/points/scroll"),
        escapeshellarg('Content-Type: application/json'),
        escapeshellarg($body)
    );
    $resp = shell_exec($cmd);
    $j = $resp ? json_decode($resp, true) : null;
    if (! is_array($j) || ($j['status'] ?? '') !== 'ok') {
        fwrite(STDERR, "FAIL  Qdrant request failed for {$name}\n");
        $totalErrors++;
        continue;
    }

    $points = $j['result']['points'] ?? [];
    if (empty($points)) {
        fwrite(STDERR, "WARN  {$name}: no points returned\n");
        continue;
    }

    $required   = $schema['required']   ?? [];
    $properties = $schema['properties'] ?? [];
    $extraOK    = ($schema['additionalProperties'] ?? true) === true;
    $errors = 0;

    foreach ($points as $pt) {
        $payload = $pt['payload'] ?? [];
        $id = $pt['id'] ?? '?';

        foreach ($required as $field) {
            if (! array_key_exists($field, $payload)) {
                fwrite(STDERR, "FAIL  {$name}/{$id}: missing required field '{$field}'\n");
                $errors++;
            }
        }
        if (! $extraOK) {
            foreach (array_keys($payload) as $field) {
                if (! array_key_exists($field, $properties)) {
                    fwrite(STDERR, "FAIL  {$name}/{$id}: unknown field '{$field}'\n");
                    $errors++;
                }
            }
        }
        foreach ($payload as $field => $value) {
            $spec = $properties[$field] ?? null;
            if (! $spec) continue;
            $expected = $spec['type'] ?? null;
            if (! $expected) continue;
            $actual = match (true) {
                is_int($value)    => 'integer',
                is_bool($value)   => 'boolean',
                is_float($value)  => 'number',
                is_string($value) => 'string',
                is_array($value)  => array_is_list($value) ? 'array' : 'object',
                is_null($value)   => 'null',
                default           => gettype($value),
            };
            if ($expected !== $actual) {
                fwrite(STDERR, "FAIL  {$name}/{$id}: field '{$field}' expected {$expected}, got {$actual}\n");
                $errors++;
            }
            if (isset($spec['enum']) && ! in_array($value, $spec['enum'], true)) {
                fwrite(STDERR, "FAIL  {$name}/{$id}: field '{$field}'={$value} not in enum " . json_encode($spec['enum']) . "\n");
                $errors++;
            }
        }
    }

    $verdict = $errors === 0 ? 'OK' : 'FAIL';
    echo "{$verdict}  {$name}: " . count($points) . " sample(s), {$errors} violation(s)\n";
    $totalErrors += $errors;
}

exit($totalErrors === 0 ? 0 : 1);

function parseArgs(array $argv): array
{
    $opts = ['sample' => 5, 'collection' => null];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '-h' || $arg === '--help') {
            echo "Usage: php bin/discovery-qdrant-validate.php [--sample=N] [--collection=NAME]\n";
            exit(0);
        }
        if (preg_match('/^--([a-z-]+)=(.*)$/', $arg, $m) && array_key_exists($m[1], $opts)) {
            $opts[$m[1]] = is_int($opts[$m[1]]) ? (int) $m[2] : $m[2];
            continue;
        }
        fwrite(STDERR, "ERROR: bad argument: {$arg}\n");
        exit(2);
    }
    return $opts;
}
