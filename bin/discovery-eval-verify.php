#!/usr/bin/env php
<?php
/**
 * discovery-eval-verify — run the same eval config N times and assert
 * identical metrics + per-query top-10 IDs across runs. Catches sources of
 * nondeterminism that would invalidate the paper's reported numbers.
 *
 * Implements GitHub issue #20 Part B.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * AGPL-3.0-or-later. See <https://www.gnu.org/licenses/>.
 *
 * Asserts (exit non-zero on any failure):
 *   - aggregate metrics (ndcg10, mrr, recall10/50/100) match across runs to
 *     6 decimal places (mean, ci95_low, ci95_high)
 *   - per-query top-10 retrieved IDs match exactly (same IDs, same order)
 *   - per-query log_ids differ across runs (sanity — confirms separate execs)
 *
 * Latency is excluded from the determinism check by design (it's a measurement
 * of the system, not a result of the system).
 *
 * On failure: dumps per-query diff to <output_dir>/diff.json.
 *
 * Usage:
 *   php bin/discovery-eval-verify.php \
 *     --qrels=tests/discovery/qrels.csv \
 *     --config=full_hybrid \
 *     --runs=2 \
 *     --output-dir=storage/discovery-eval/verify-<timestamp>/
 */

$opts = parseArgs($argv);
$evalScript = realpath(__DIR__ . '/discovery-eval.php');

@mkdir($opts['output-dir'], 0775, true);

$runs = [];
for ($i = 1; $i <= $opts['runs']; $i++) {
    $out = $opts['output-dir'] . "/run-{$i}.json";
    $cmd = sprintf(
        'php %s --qrels=%s --config=%s --output=%s --quiet 2>&1',
        escapeshellarg($evalScript),
        escapeshellarg($opts['qrels']),
        escapeshellarg($opts['config']),
        escapeshellarg($out)
    );
    fwrite(STDERR, "verify: run {$i}/{$opts['runs']} ... ");
    $t0 = microtime(true);
    $stdout = shell_exec($cmd);
    $wall = (int) ((microtime(true) - $t0) * 1000);
    if (! is_readable($out)) {
        fwrite(STDERR, "FAILED — eval script did not produce {$out}\n");
        fwrite(STDERR, ($stdout ?: '(no output)') . "\n");
        exit(1);
    }
    fwrite(STDERR, "{$wall} ms\n");
    $runs[] = json_decode((string) file_get_contents($out), true);
}

$diffs = compareRuns($runs);
file_put_contents(
    $opts['output-dir'] . '/diff.json',
    json_encode([
        'config'   => $opts['config'],
        'runs'     => $opts['runs'],
        'verdict'  => empty($diffs) ? 'PASS' : 'FAIL',
        'diffs'    => $diffs,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

if (empty($diffs)) {
    echo "PASS  {$opts['runs']} runs of config={$opts['config']} produced identical metrics and top-10 ordering.\n";
    exit(0);
}

echo "FAIL  determinism check failed; " . count($diffs) . " divergence(s).\n";
foreach ($diffs as $d) {
    echo "  - {$d['kind']}: {$d['detail']}\n";
}
echo "Full diff written to {$opts['output-dir']}/diff.json\n";
exit(1);


function parseArgs(array $argv): array
{
    $opts = [
        'qrels'      => null,
        'config'     => 'full_hybrid',
        'runs'       => 2,
        'output-dir' => null,
    ];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '-h' || $arg === '--help') {
            echo "Usage: php bin/discovery-eval-verify.php --qrels=PATH [--config=NAME] [--runs=N] --output-dir=DIR\n";
            exit(0);
        }
        if (preg_match('/^--([a-z-]+)=(.*)$/', $arg, $m) && array_key_exists($m[1], $opts)) {
            $opts[$m[1]] = is_int($opts[$m[1]]) ? (int) $m[2] : $m[2];
            continue;
        }
        fwrite(STDERR, "ERROR: bad argument: {$arg}\n");
        exit(2);
    }
    foreach (['qrels', 'output-dir'] as $k) {
        if (empty($opts[$k])) {
            fwrite(STDERR, "ERROR: --{$k}= is required\n");
            exit(2);
        }
    }
    if ($opts['runs'] < 2) {
        fwrite(STDERR, "ERROR: --runs must be >= 2\n");
        exit(2);
    }
    return $opts;
}

function compareRuns(array $runs): array
{
    $diffs = [];
    $base = $runs[0];

    // Aggregate-metric check. Compare each run against run 1 to 6 decimal places.
    $metricKeys = ['ndcg10', 'mrr', 'recall10', 'recall50', 'recall100'];
    for ($i = 1; $i < count($runs); $i++) {
        foreach ($metricKeys as $mk) {
            foreach (['mean', 'ci95_low', 'ci95_high'] as $field) {
                $a = round((float) ($base['metrics'][$mk][$field] ?? 0), 6);
                $b = round((float) ($runs[$i]['metrics'][$mk][$field] ?? 0), 6);
                if ($a !== $b) {
                    $diffs[] = [
                        'kind'   => 'metric_aggregate',
                        'detail' => "run1 vs run" . ($i + 1) . " differ on metrics.{$mk}.{$field}: {$a} vs {$b}",
                    ];
                }
            }
        }
    }

    // Per-query top-10 ID + order check.
    $byQuery = [];
    foreach ($runs as $idx => $r) {
        foreach ($r['per_query'] ?? [] as $pq) {
            $byQuery[$pq['query_id']][$idx + 1] = $pq;
        }
    }
    foreach ($byQuery as $qid => $perRun) {
        if (count($perRun) !== count($runs)) {
            $diffs[] = ['kind' => 'missing_query', 'detail' => "{$qid} not present in all runs"];
            continue;
        }
        $baseIds = array_column($perRun[1]['retrieved_top10'] ?? [], 'object_id');
        $baseLog = $perRun[1]['log_id'] ?? null;
        for ($i = 2; $i <= count($runs); $i++) {
            $thisIds = array_column($perRun[$i]['retrieved_top10'] ?? [], 'object_id');
            if ($thisIds !== $baseIds) {
                $diffs[] = [
                    'kind'   => 'top10_mismatch',
                    'detail' => "{$qid}: run1 vs run{$i}",
                    'run1'   => $baseIds,
                    "run{$i}" => $thisIds,
                ];
            }
            $thisLog = $perRun[$i]['log_id'] ?? null;
            if ($baseLog !== null && $thisLog !== null && $baseLog === $thisLog) {
                $diffs[] = [
                    'kind'   => 'log_id_collision',
                    'detail' => "{$qid}: run1 and run{$i} share log_id={$baseLog} (sanity — should be separate execs)",
                ];
            }
        }
    }

    return $diffs;
}
