#!/usr/bin/env php
<?php
/**
 * discovery-eval — replay a qrels CSV through /discovery/search and compute
 * nDCG@10, MRR, Recall@{10,50,100} per query, then aggregate with bootstrap
 * CI95 across queries and per query_type.
 *
 * Implements GitHub issue #17. Consumes #16 (qrels CSV + linter) and #28
 * (controller `?strategies=` param).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * AGPL-3.0-or-later. See <https://www.gnu.org/licenses/>.
 *
 * Usage:
 *   php bin/discovery-eval.php \
 *     --qrels=tests/discovery/qrels.csv \
 *     --config=full_hybrid \
 *     --output=storage/discovery-eval/<timestamp>.json
 *
 * Configs:
 *   baseline      → keyword
 *   kw_entity     → keyword,entity
 *   kw_hierarchy  → keyword,hierarchical
 *   kw_vector     → keyword,vector
 *   full_hybrid   → keyword,entity,hierarchical,vector
 *
 * Optional flags:
 *   --base-url=https://heratio.theahg.co.za   override the search endpoint host
 *   --host-header=heratio.theahg.co.za        Host header (default = base-url host)
 *   --bootstrap-samples=1000                  number of bootstrap resamples
 *   --seed=42                                 RNG seed for reproducibility
 *   --quiet                                   only print FAIL / final path
 *
 * Exit: 0 = ran clean, JSON written. 1 = fatal. 2 = usage error.
 */

const CONFIG_MAP = [
    'baseline'     => ['keyword'],
    'kw_entity'    => ['keyword', 'entity'],
    'kw_hierarchy' => ['keyword', 'hierarchical'],
    'kw_vector'    => ['keyword', 'vector'],
    'full_hybrid'  => ['keyword', 'entity', 'hierarchical', 'vector'],
];

$opts = parseArgs($argv);
$verbose = ! $opts['quiet'];

if (! is_readable($opts['qrels'])) {
    fwrite(STDERR, "ERROR: cannot read qrels file: {$opts['qrels']}\n");
    exit(2);
}
if (! isset(CONFIG_MAP[$opts['config']])) {
    fwrite(STDERR, "ERROR: unknown --config '{$opts['config']}'. Valid: " . implode(', ', array_keys(CONFIG_MAP)) . "\n");
    exit(2);
}

$qrels = loadQrels($opts['qrels']);
if (empty($qrels)) {
    fwrite(STDERR, "ERROR: qrels file is empty or invalid\n");
    exit(1);
}

$strategiesCsv = implode(',', CONFIG_MAP[$opts['config']]);
$qrelsHash = 'sha256:' . hash_file('sha256', $opts['qrels']);
$gitSha = trim(shell_exec('cd ' . escapeshellarg(dirname(__DIR__)) . ' && git rev-parse --short HEAD 2>/dev/null') ?? '');
$startedAt = gmdate('c');

if ($verbose) {
    fwrite(STDERR, "discovery-eval: config={$opts['config']} strategies=[{$strategiesCsv}] queries=" . count($qrels) . "\n");
}

$perQuery = [];
$strategyMs = [
    'keyword' => [], 'entity' => [], 'hierarchical' => [], 'vector' => [], 'image' => [],
];
$totalMs = [];

foreach ($qrels as $qid => $q) {
    // nocache=1 bypasses ahg_discovery_cache so the harness measures the
    // actual retrieval pipeline rather than a previous run's cached response.
    $url = sprintf(
        '%s/discovery/search?q=%s&strategies=%s&limit=100&nocache=1',
        rtrim($opts['base-url'], '/'),
        urlencode($q['query_text']),
        urlencode($strategiesCsv)
    );
    $t0 = microtime(true);
    $resp = httpGetJson($url, $opts['host-header']);
    $wallMs = (int) ((microtime(true) - $t0) * 1000);

    if ($resp === null) {
        fwrite(STDERR, "FAIL  {$qid}: search request failed\n");
        continue;
    }

    $retrieved = [];
    foreach (($resp['results'] ?? []) as $rank => $r) {
        $retrieved[] = [
            'object_id' => (int) ($r['object_id'] ?? $r['id'] ?? 0),
            'rank'      => $rank + 1,
            'score'     => (float) ($r['score'] ?? 0),
            'sources'   => array_values(array_filter([
                ($r['source'] ?? null),
                ...(array_keys($r['sources'] ?? []) ?: []),
            ])),
        ];
    }

    $logId = $resp['log_id'] ?? null;
    $perStrategy = $logId ? fetchStrategyMs((int) $logId) : [];

    $rels = $q['relevance'];
    $metrics = [
        'ndcg10'    => ndcg($retrieved, $rels, 10),
        'mrr'       => mrr($retrieved, $rels),
        'recall10'  => recallAt($retrieved, $rels, 10),
        'recall50'  => recallAt($retrieved, $rels, 50),
        'recall100' => recallAt($retrieved, $rels, 100),
    ];

    $perQuery[] = [
        'query_id'         => $qid,
        'query_type'       => $q['query_type'],
        'log_id'           => $logId ? (int) $logId : null,
        'latency_ms_total' => $wallMs,
        'ndcg10'           => $metrics['ndcg10'],
        'mrr'              => $metrics['mrr'],
        'recall10'         => $metrics['recall10'],
        'recall50'         => $metrics['recall50'],
        'recall100'        => $metrics['recall100'],
        'retrieved_top10'  => array_slice($retrieved, 0, 10),
    ];

    $totalMs[] = $wallMs;
    foreach (['keyword','entity','hierarchical','vector','image'] as $s) {
        if (isset($perStrategy[$s])) {
            $strategyMs[$s][] = (int) $perStrategy[$s];
        }
    }

    if ($verbose) {
        fwrite(STDERR, sprintf(
            "  %s [%s] nDCG@10=%.3f MRR=%.3f R@10=%.3f R@100=%.3f wall=%dms\n",
            $qid, $q['query_type'],
            $metrics['ndcg10'], $metrics['mrr'],
            $metrics['recall10'], $metrics['recall100'],
            $wallMs
        ));
    }
}

if (empty($perQuery)) {
    fwrite(STDERR, "FAIL  no queries succeeded\n");
    exit(1);
}

$rng = mt_srand($opts['seed']);
$result = [
    'config'           => $opts['config'],
    'strategies'       => CONFIG_MAP[$opts['config']],
    'run_timestamp'    => $startedAt,
    'qrels_file'       => realpath($opts['qrels']) ?: $opts['qrels'],
    'qrels_file_hash'  => $qrelsHash,
    'code_git_sha'     => $gitSha ?: 'unknown',
    'n_queries'        => count($perQuery),
    'metrics'          => aggregateMetrics($perQuery, $opts['bootstrap-samples'], $opts['seed']),
    'by_query_type'    => aggregateByType($perQuery, $opts['bootstrap-samples'], $opts['seed']),
    'latency_ms'       => latencyStats($totalMs, $strategyMs),
    'per_query'        => $perQuery,
];

@mkdir(dirname($opts['output']), 0775, true);
file_put_contents($opts['output'], json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo realpath($opts['output']) ?: $opts['output'];
echo "\n";
exit(0);


// ---------------------------------------------------------------------------
// CLI parsing & qrels loading
// ---------------------------------------------------------------------------

function parseArgs(array $argv): array
{
    $defaults = [
        'qrels'             => null,
        'config'            => null,
        'output'            => null,
        'base-url'          => 'https://localhost',
        'host-header'       => 'heratio.theahg.co.za',
        'bootstrap-samples' => 1000,
        'seed'              => 42,
        'quiet'             => false,
    ];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            echo "Usage: php bin/discovery-eval.php --qrels=PATH --config=NAME --output=PATH\n";
            echo "       configs: " . implode(', ', array_keys(CONFIG_MAP)) . "\n";
            exit(0);
        }
        if ($arg === '--quiet') { $defaults['quiet'] = true; continue; }
        if (preg_match('/^--([a-z-]+)=(.*)$/', $arg, $m)) {
            if (! array_key_exists($m[1], $defaults)) {
                fwrite(STDERR, "ERROR: unknown flag --{$m[1]}\n");
                exit(2);
            }
            $defaults[$m[1]] = is_int($defaults[$m[1]]) ? (int) $m[2] : $m[2];
            continue;
        }
        fwrite(STDERR, "ERROR: bad argument: {$arg}\n");
        exit(2);
    }
    foreach (['qrels', 'config', 'output'] as $req) {
        if (empty($defaults[$req])) {
            fwrite(STDERR, "ERROR: --{$req}= is required\n");
            exit(2);
        }
    }
    return $defaults;
}

function loadQrels(string $path): array
{
    $fh = fopen($path, 'r');
    $header = fgetcsv($fh);
    $expected = ['query_id','query_text','query_type','object_id','relevance'];
    if ($header !== $expected) {
        fwrite(STDERR, "ERROR: qrels header malformed; run bin/discovery-qrels-lint.php first\n");
        return [];
    }
    $by = [];
    while (($row = fgetcsv($fh)) !== false) {
        if (count($row) !== 5) continue;
        [$qid, $qtext, $qtype, $oid, $rel] = $row;
        if (! isset($by[$qid])) {
            $by[$qid] = ['query_text' => $qtext, 'query_type' => $qtype, 'relevance' => []];
        }
        $by[$qid]['relevance'][(int) $oid] = (int) $rel;
    }
    fclose($fh);
    return $by;
}

// ---------------------------------------------------------------------------
// HTTP + DB helpers
// ---------------------------------------------------------------------------

function httpGetJson(string $url, string $hostHeader): ?array
{
    // Shell out to system curl. PHP curl + nginx HTTP/2 + Host-header trick
    // gives PROTOCOL_ERROR on this stack; system curl works cleanly.
    $cmd = sprintf(
        'curl -skL --max-time 60 -H %s -H %s %s 2>/dev/null',
        escapeshellarg('Host: ' . $hostHeader),
        escapeshellarg('Accept: application/json'),
        escapeshellarg($url)
    );
    $body = shell_exec($cmd);
    if ($body === null || $body === '') {
        return null;
    }
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}

function fetchStrategyMs(int $logId): array
{
    $sql = "SELECT strategy_breakdown FROM ahg_discovery_log WHERE id = " . (int) $logId;
    $cmd = "mysql -u root heratio -N -e " . escapeshellarg($sql) . " 2>/dev/null";
    $out = shell_exec($cmd);
    if ($out === null || trim($out) === '' || trim($out) === 'NULL') {
        return [];
    }
    $j = json_decode(trim($out), true);
    if (! is_array($j)) {
        return [];
    }
    $ms = [];
    foreach (['keyword','entity','hierarchical','vector','image'] as $k) {
        if (isset($j[$k]['ms'])) {
            $ms[$k] = (int) $j[$k]['ms'];
        }
    }
    return $ms;
}

// ---------------------------------------------------------------------------
// Metrics
// ---------------------------------------------------------------------------

function ndcg(array $retrieved, array $rels, int $k): float
{
    $dcg = 0.0;
    $i = 1;
    foreach (array_slice($retrieved, 0, $k) as $r) {
        $rel = $rels[$r['object_id']] ?? 0;
        $dcg += (pow(2, $rel) - 1) / log($i + 1, 2);
        $i++;
    }
    $ideal = $rels;
    rsort($ideal);
    $idcg = 0.0;
    $i = 1;
    foreach (array_slice($ideal, 0, $k) as $rel) {
        $idcg += (pow(2, $rel) - 1) / log($i + 1, 2);
        $i++;
    }
    return $idcg > 0 ? $dcg / $idcg : 0.0;
}

function mrr(array $retrieved, array $rels): float
{
    $i = 1;
    foreach ($retrieved as $r) {
        if (($rels[$r['object_id']] ?? 0) > 0) {
            return 1.0 / $i;
        }
        $i++;
    }
    return 0.0;
}

function recallAt(array $retrieved, array $rels, int $k): float
{
    $relevantTotal = 0;
    foreach ($rels as $rel) {
        if ($rel > 0) $relevantTotal++;
    }
    if ($relevantTotal === 0) return 0.0;
    $hits = 0;
    foreach (array_slice($retrieved, 0, $k) as $r) {
        if (($rels[$r['object_id']] ?? 0) > 0) {
            $hits++;
        }
    }
    return $hits / $relevantTotal;
}

// ---------------------------------------------------------------------------
// Aggregation: mean + bootstrap CI95
// ---------------------------------------------------------------------------

function aggregateMetrics(array $perQuery, int $samples, int $seed): array
{
    return [
        'ndcg10'    => bootstrapCi(array_column($perQuery, 'ndcg10'),    $samples, $seed),
        'mrr'       => bootstrapCi(array_column($perQuery, 'mrr'),       $samples, $seed + 1),
        'recall10'  => bootstrapCi(array_column($perQuery, 'recall10'),  $samples, $seed + 2),
        'recall50'  => bootstrapCi(array_column($perQuery, 'recall50'),  $samples, $seed + 3),
        'recall100' => bootstrapCi(array_column($perQuery, 'recall100'), $samples, $seed + 4),
    ];
}

function aggregateByType(array $perQuery, int $samples, int $seed): array
{
    $byType = [];
    foreach ($perQuery as $r) {
        $byType[$r['query_type']][] = $r;
    }
    $out = [];
    foreach ($byType as $type => $rows) {
        $out[$type] = ['n' => count($rows)] + aggregateMetrics($rows, $samples, $seed);
    }
    return $out;
}

function bootstrapCi(array $values, int $samples, int $seed): array
{
    $values = array_values($values);
    $n = count($values);
    if ($n === 0) {
        return ['mean' => 0.0, 'ci95_low' => 0.0, 'ci95_high' => 0.0];
    }
    $mean = array_sum($values) / $n;
    if ($n < 2) {
        return ['mean' => $mean, 'ci95_low' => $mean, 'ci95_high' => $mean];
    }
    mt_srand($seed);
    $means = [];
    for ($i = 0; $i < $samples; $i++) {
        $sum = 0.0;
        for ($j = 0; $j < $n; $j++) {
            $sum += $values[mt_rand(0, $n - 1)];
        }
        $means[] = $sum / $n;
    }
    sort($means);
    $low  = $means[(int) floor($samples * 0.025)];
    $high = $means[(int) floor($samples * 0.975)];
    return [
        'mean'      => round($mean, 6),
        'ci95_low'  => round($low,  6),
        'ci95_high' => round($high, 6),
    ];
}

function latencyStats(array $total, array $perStrategy): array
{
    $byStrategy = [];
    foreach ($perStrategy as $name => $samples) {
        $byStrategy[$name] = $samples ? (int) round(array_sum($samples) / count($samples)) : 0;
    }
    return [
        'mean_total'  => $total ? (int) round(array_sum($total) / count($total)) : 0,
        'p50_total'   => percentile($total, 50),
        'p95_total'   => percentile($total, 95),
        'by_strategy' => $byStrategy,
    ];
}

function percentile(array $values, int $p): int
{
    if (empty($values)) return 0;
    sort($values);
    $idx = (int) floor((count($values) - 1) * ($p / 100));
    return (int) $values[$idx];
}
