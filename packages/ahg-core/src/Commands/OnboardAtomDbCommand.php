<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * heratio#1399 — one-shot onboarding for a fresh Heratio install over an
 * existing AtoM database.
 *
 * Heratio reads the AtoM tables directly, so descriptions appear immediately —
 * but four DERIVED layers start empty and must be built, in order, or the site
 * looks broken/empty even though every record is present:
 *
 *   1. migrate            — create Heratio's own tables against the AtoM base
 *   2. build-closure      — ancestor closure (fast hierarchy/ancestor reads)
 *   3. es-reindex         — full-text Elasticsearch index (parallel, bulk-mode)
 *   4. display-auto-detect— GLAM object-type classification (defaults archive)
 *   5. display-reindex    — GLAM browse facet caches
 *
 * This command runs them in the correct order with a single invocation, so
 * onboarding a client (or NARSSA) is one tested step instead of manual surgery.
 * Safe to re-run: every underlying step is idempotent.
 */
class OnboardAtomDbCommand extends Command
{
    protected $signature = 'ahg:onboard-atom-db
        {--workers=8 : Parallel workers for the es-reindex step}
        {--fresh-index : Drop + rebuild the ES index (default: upsert, no search downtime)}
        {--classify-archive : Classify with the fast bulk-archive path instead of per-record detection (homogeneous archives)}
        {--skip-migrate : Skip the migration step (schema already current)}
        {--dry-run : List the steps in order without running them}';

    protected $description = 'Bring a fresh Heratio-over-AtoM database fully online (migrate → closure → index → classify → facets) — heratio#1399';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $workers = max(1, (int) $this->option('workers'));

        $ioCount = (int) DB::table('information_object')->where('id', '!=', 1)->count();
        $this->info("Onboarding this database — {$ioCount} information objects.");
        $this->newLine();

        // Ordered pipeline: [artisan command, options, human label].
        $steps = [];

        if (! $this->option('skip-migrate')) {
            $steps[] = ['migrate', ['--force' => true], 'Create Heratio tables (migrate)'];
        }

        $steps[] = ['ahg:build-closure', ['--all' => true], 'Build ancestor closure'];

        $esOpts = ['--index' => 'informationobject', '--workers' => $workers, '--bulk-mode' => true, '--no-interaction' => true];
        if ($this->option('fresh-index')) {
            $esOpts['--drop'] = true;
        }
        $steps[] = ['ahg:es-reindex', $esOpts, "Full-text index — informationobject ({$workers} workers, bulk-mode)"];

        // Small indices inline (fast, single-threaded is fine).
        foreach (['actor', 'term', 'repository'] as $idx) {
            $steps[] = ['ahg:es-reindex', ['--index' => $idx, '--no-interaction' => true], "Full-text index — {$idx}"];
        }

        $classifyOpts = ['--no-interaction' => true];
        if ($this->option('classify-archive')) {
            $classifyOpts['--bulk-archive'] = true;
        }
        $steps[] = ['ahg:display-auto-detect', $classifyOpts, 'Classify GLAM object types (defaults to archive)'];

        $steps[] = ['ahg:display-reindex', ['--no-interaction' => true], 'Rebuild GLAM browse facet caches'];

        // Preview
        $this->line('Pipeline:');
        foreach ($steps as $i => [$cmd, $opts, $label]) {
            $this->line(sprintf('  %d. %s', $i + 1, $label));
        }
        $this->newLine();

        if ($dry) {
            $this->warn('Dry run — no steps executed.');

            return self::SUCCESS;
        }

        foreach ($steps as $i => [$cmd, $opts, $label]) {
            $this->info(sprintf('▶ [%d/%d] %s', $i + 1, count($steps), $label));
            $code = $this->call($cmd, $opts);
            if ($code !== 0) {
                $this->error("Step failed: {$cmd} (exit {$code}). Stopping — fix and re-run (the pipeline is idempotent).");

                return 1;
            }
            $this->newLine();
        }

        $this->info('✓ Onboarding complete — database is indexed, classified, and browsable.');
        $this->line('  Verify: open the browse page, and check `curl -s localhost:9200/_cat/indices?v`.');

        return self::SUCCESS;
    }
}
