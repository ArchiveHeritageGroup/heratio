#!/usr/bin/env php
<?php
/**
 * discovery-ner-reindex — denormalise atom.ahg_ner_entity into the
 * heratio_qubitinformationobject ES index so the entity strategy can move
 * from MySQL LIKE (28 s on 9.79M rows) to ES BM25 (~ms warm).
 *
 * Implements the data-plane half of GitHub issue #24's Option B.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * AGPL-3.0-or-later. See <https://www.gnu.org/licenses/>.
 *
 * Strategy:
 *   - Walk distinct object_ids in atom.ahg_ner_entity in $batch chunks.
 *   - For each batch, pull all entity rows for those IOs in one SELECT
 *     (ORDER BY object_id, confidence DESC) and aggregate per-IO in PHP.
 *   - Cap entities per IO at MAX_PER_IO so a hub-IO can't blow up the doc.
 *   - Bulk update each ES doc with nerEntityValues / nerEntityTypes / nerEntityCount.
 *
 * Usage:
 *   php bin/discovery-ner-reindex.php                    # full run
 *   php bin/discovery-ner-reindex.php --batch=2000       # tune batch size
 *   php bin/discovery-ner-reindex.php --since-id=NNN     # resume from object_id
 *   php bin/discovery-ner-reindex.php --dry-run          # plan only, no writes
 *   php bin/discovery-ner-reindex.php --object-id=91126  # one IO (debug)
 */

const ES_HOST    = 'http://localhost:9200';
const ES_INDEX   = 'heratio_qubitinformationobject';
const MAX_PER_IO = 200;  // cap multi-valued field length per IO

require_once __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$opts = parseArgs($argv);
$conn = DB::connection('atom');

if ($opts['object-id'] > 0) {
    $row = fetchOne($conn, $opts['object-id']);
    if ($row === null) {
        echo "no entities for object_id={$opts['object-id']}\n";
        exit(0);
    }
    echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
    if (! $opts['dry-run']) {
        $resp = bulkUpdate([$row]);
        echo "ES update: " . (empty($resp['errors']) ? 'ok' : 'errors=' . countErrors($resp)) . "\n";
    }
    exit(0);
}

$startId       = (int) $opts['since-id'];
$batchSize     = (int) $opts['batch'];
$totalIos      = 0;
$totalEntities = 0;
$totalBatches  = 0;
$totalErrors   = 0;
$tStart        = microtime(true);

while (true) {
    $batch = fetchBatch($conn, $startId, $batchSize);
    if (empty($batch)) break;

    $totalBatches++;
    $totalIos += count($batch);
    foreach ($batch as $r) {
        $totalEntities += $r['nerEntityCount'];
        if ($r['object_id'] > $startId) $startId = $r['object_id'];
    }

    if ($opts['dry-run']) {
        $sample = $batch[0];
        printf("  batch %d: ios=%d, last_id=%d, sample id=%d (%d entities, types=%s)\n",
            $totalBatches, count($batch), $startId, $sample['object_id'],
            $sample['nerEntityCount'], implode(',', $sample['nerEntityTypes']));
    } else {
        $resp = bulkUpdate($batch);
        $errs = countErrors($resp);
        $totalErrors += $errs;
        if ($errs > 0) {
            printf("  batch %d: ios=%d, ES errors=%d (last_id=%d)\n",
                $totalBatches, count($batch), $errs, $startId);
        }
    }

    if ($totalBatches % 10 === 0 || $opts['dry-run']) {
        $elapsed = microtime(true) - $tStart;
        $rate = $totalIos / max($elapsed, 0.001);
        printf("[progress] ios=%d entities=%d batches=%d errors=%d %.1f ios/sec elapsed=%.0fs last_id=%d\n",
            $totalIos, $totalEntities, $totalBatches, $totalErrors, $rate, $elapsed, $startId);
    }

    if ($opts['dry-run'] && $totalBatches >= 3) {
        echo "[dry-run] stopping after 3 batches\n";
        break;
    }
}

$elapsed = microtime(true) - $tStart;
printf("\nDONE: %d IOs, %d entities, %d batches, %d errors, %.0f s wall, %.1f ios/sec\n",
    $totalIos, $totalEntities, $totalBatches, $totalErrors, $elapsed, $totalIos / max($elapsed, 0.001));
exit($totalErrors > 0 ? 1 : 0);


function fetchBatch($conn, int $sinceId, int $limit): array
{
    // Step 1 — pull next $limit distinct object_ids strictly greater than cursor.
    $idRows = $conn->select(
        'SELECT DISTINCT object_id FROM ahg_ner_entity
         WHERE status IN ("approved","pending") AND object_id > ?
         ORDER BY object_id ASC
         LIMIT ' . (int) $limit,
        [$sinceId]
    );
    if (empty($idRows)) return [];
    $ids = array_map(fn($r) => (int) $r->object_id, $idRows);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Step 2 — pull all entities for those IDs, ordered for stable aggregation.
    $entityRows = $conn->select(
        "SELECT object_id, entity_type, entity_value, confidence
         FROM ahg_ner_entity
         WHERE status IN ('approved','pending')
           AND object_id IN ({$placeholders})
         ORDER BY object_id ASC, confidence DESC, id ASC",
        $ids
    );

    // Step 3 — aggregate per-IO with cap.
    $byIo = [];
    foreach ($entityRows as $r) {
        $oid = (int) $r->object_id;
        if (! isset($byIo[$oid])) {
            $byIo[$oid] = ['values' => [], 'types' => [], 'cnt' => 0];
        }
        $byIo[$oid]['cnt']++;
        $v = trim((string) $r->entity_value);
        $t = trim((string) $r->entity_type);
        if (count($byIo[$oid]['values']) < MAX_PER_IO && $v !== '' && ! in_array($v, $byIo[$oid]['values'], true)) {
            $byIo[$oid]['values'][] = $v;
        }
        if ($t !== '' && ! in_array($t, $byIo[$oid]['types'], true)) {
            $byIo[$oid]['types'][] = $t;
        }
    }

    $out = [];
    foreach ($ids as $oid) {
        if (! isset($byIo[$oid])) continue;
        $out[] = [
            'object_id'       => $oid,
            'nerEntityValues' => $byIo[$oid]['values'],
            'nerEntityTypes'  => $byIo[$oid]['types'],
            'nerEntityCount'  => $byIo[$oid]['cnt'],
        ];
    }
    return $out;
}

function fetchOne($conn, int $objectId): ?array
{
    $rows = $conn->select(
        "SELECT entity_type, entity_value, confidence
         FROM ahg_ner_entity
         WHERE object_id = ? AND status IN ('approved','pending')
         ORDER BY confidence DESC, id ASC
         LIMIT " . (MAX_PER_IO * 4),
        [$objectId]
    );
    if (empty($rows)) return null;
    $values = []; $types = [];
    foreach ($rows as $r) {
        $v = trim((string) $r->entity_value);
        $t = trim((string) $r->entity_type);
        if ($v !== '' && count($values) < MAX_PER_IO && ! in_array($v, $values, true)) $values[] = $v;
        if ($t !== '' && ! in_array($t, $types, true)) $types[] = $t;
    }
    return [
        'object_id'       => $objectId,
        'nerEntityValues' => $values,
        'nerEntityTypes'  => $types,
        'nerEntityCount'  => count($rows),
    ];
}

function bulkUpdate(array $batch): array
{
    $body = '';
    foreach ($batch as $r) {
        $action = ['update' => ['_index' => ES_INDEX, '_id' => (string) $r['object_id'], 'retry_on_conflict' => 3]];
        $doc    = ['doc' => [
            'nerEntityValues' => $r['nerEntityValues'],
            'nerEntityTypes'  => $r['nerEntityTypes'],
            'nerEntityCount'  => $r['nerEntityCount'],
        ]];
        $body .= json_encode($action) . "\n" . json_encode($doc) . "\n";
    }
    $cmd = sprintf(
        'curl -s --max-time 60 -X POST %s -H %s --data-binary @- 2>/dev/null',
        escapeshellarg(ES_HOST . '/_bulk'),
        escapeshellarg('Content-Type: application/x-ndjson')
    );
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fwrite($pipes[0], $body);
    fclose($pipes[0]);
    $resp = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return json_decode((string) $resp, true) ?: ['errors' => true, 'items' => []];
}

function countErrors(array $resp): int
{
    if (empty($resp['errors'])) return 0;
    $n = 0;
    foreach (($resp['items'] ?? []) as $item) {
        if (! empty($item['update']['error'])) $n++;
    }
    return $n;
}

function parseArgs(array $argv): array
{
    $opts = [
        'batch'     => 1000,
        'since-id'  => 0,
        'object-id' => 0,
        'dry-run'   => false,
    ];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            echo "Usage: php bin/discovery-ner-reindex.php [--batch=N] [--since-id=N] [--object-id=N] [--dry-run]\n";
            exit(0);
        }
        if ($arg === '--dry-run') { $opts['dry-run'] = true; continue; }
        if (preg_match('/^--([a-z-]+)=(.*)$/', $arg, $m) && array_key_exists($m[1], $opts)) {
            $opts[$m[1]] = is_int($opts[$m[1]]) ? (int) $m[2] : $m[2];
            continue;
        }
        fwrite(STDERR, "ERROR: bad argument: {$arg}\n");
        exit(2);
    }
    return $opts;
}
