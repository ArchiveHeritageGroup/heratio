<?php

/**
 * DiscoverySimulateCommand — generate the simulated query corpus, write a
 * qrels CSV that matches issue #16's schema, and (optionally) run the
 * resulting CSV through bin/discovery-eval.php for each ablation config.
 *
 * Implements the "simulated query corpus" deliverable in issue #11.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgDiscovery\Console;

use AhgDiscovery\Services\DiscoverySimulatedQueryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiscoverySimulateCommand extends Command
{
    protected $signature = 'ahg:discovery-simulate
        {--n=100         : Total queries to generate (per-type ratios preserved from defaults)}
        {--seed=42       : RNG seed for reproducible runs}
        {--qrels-out=tests/discovery/qrels-simulated.csv : Path to write the qrels CSV}
        {--run-id=       : Run identifier (default: ts-<unix-timestamp>); also tags ahg_discovery_simulated_run rows}
        {--persist       : Insert one row per query into ahg_discovery_simulated_run}
        {--print         : Print the queries to stdout for inspection}';

    protected $description = 'Generate a 100-query reproducible corpus for ablation runs (issue #11)';

    public function handle(DiscoverySimulatedQueryService $svc): int
    {
        $n      = max(1, (int) $this->option('n'));
        $seed   = (int) $this->option('seed');
        $runId  = (string) ($this->option('run-id') ?: 'ts-' . time());
        $qPath  = (string) $this->option('qrels-out');
        $persist = (bool) $this->option('persist');
        $print   = (bool) $this->option('print');

        // Scale per-type counts to total $n while preserving the 30/40/20/10 ratio.
        $base = ['title' => 30, 'subject' => 40, 'scope_np' => 20, 'typo' => 10];
        $factor = $n / array_sum($base);
        $counts = array_map(fn($v) => max(1, (int) round($v * $factor)), $base);

        $this->info("[discovery-simulate] generating " . array_sum($counts) . " queries (seed={$seed}, run_id={$runId})");
        $records = $svc->generate($counts, $seed);

        // --- write qrels CSV (matches issue #16 schema) ---
        $this->writeQrelsCsv($qPath, $records);
        $this->info('  wrote qrels: ' . realpath($qPath));

        // --- persist one row per query into the side table ---
        if ($persist) {
            $rows = [];
            foreach ($records as $r) {
                $rows[] = [
                    'run_id'              => $runId,
                    'query_id'            => $r['query_id'],
                    'query_text'          => mb_substr($r['query_text'], 0, 500),
                    'query_type'          => $r['query_type'],
                    'expected_object_ids' => json_encode($r['expected_object_ids']),
                ];
            }
            DB::table('ahg_discovery_simulated_run')->insert($rows);
            $this->info('  persisted ' . count($rows) . ' rows to ahg_discovery_simulated_run (run_id=' . $runId . ')');
        }

        // --- print samples to stdout ---
        if ($print) {
            $this->line('');
            foreach (['title', 'subject', 'scope_np', 'typo'] as $type) {
                $this->line("[{$type}]");
                foreach (array_filter($records, fn($r) => $r['query_type'] === $type) as $r) {
                    $gt = count($r['expected_object_ids']);
                    $this->line(sprintf('  %s  %-40s  ground_truth=%d', $r['query_id'], $r['query_text'], $gt));
                }
            }
        }

        return self::SUCCESS;
    }

    private function writeQrelsCsv(string $path, array $records): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) mkdir($dir, 0775, true);
        $fh = fopen($path, 'w');
        fputcsv($fh, ['query_id', 'query_text', 'query_type', 'object_id', 'relevance']);
        foreach ($records as $r) {
            $expected = $r['expected_object_ids'];
            if (empty($expected)) {
                // Per issue #16 rule 4 — every query_id must have at least one
                // relevance=2 row. Skip queries whose generator produced no
                // ground truth (e.g. typo queries where the canonical form
                // doesn't appear in title or scope).
                continue;
            }
            // First expected_object_id = primary target → relevance 2.
            // Remaining = also relevant but secondary → relevance 1.
            // Canonicalise the query_text for downstream parsing safety.
            $primary = (int) array_shift($expected);
            fputcsv($fh, [$r['query_id'], $r['query_text'], $r['query_type'], $primary, 2]);
            foreach (array_slice($expected, 0, 49) as $oid) {
                fputcsv($fh, [$r['query_id'], $r['query_text'], $r['query_type'], (int) $oid, 1]);
            }
        }
        fclose($fh);
    }
}
