<?php

/**
 * PopulateIoFacetDenormCommand — populate the ahg_io_facet_denorm sidecar.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 *
 * Pattern C from docs/adr/0001-atom-base-schema-readonly-sidecar-pattern.md.
 *
 * Reads from AtoM base tables (information_object, object_term_relation,
 * term) and writes into the AHG sidecar (ahg_io_facet_denorm). No AtoM
 * base table is altered.
 */

namespace AhgDisplay\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PopulateIoFacetDenormCommand extends Command
{
    protected $signature = 'ahg:populate-io-facet-denorm
        {--taxonomy=35,42,78 : Comma-separated taxonomy IDs to populate (default: 35 subject, 42 place, 78 genre)}
        {--repository= : Restrict to a single repository_id (default: all)}
        {--truncate : Delete existing sidecar rows for the targeted taxonomies before populating}
        {--chunk=20000 : IO id chunk size for the INSERT … SELECT}';

    protected $description = 'Populate the ahg_io_facet_denorm sidecar from object_term_relation';

    public function handle(): int
    {
        if (! Schema::hasTable('ahg_io_facet_denorm')) {
            $this->error('ahg_io_facet_denorm does not exist. Boot ahg-display once to create it.');
            return self::FAILURE;
        }

        $taxonomies = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $this->option('taxonomy'))
        )));
        if (empty($taxonomies)) {
            $this->error('No taxonomies specified.');
            return self::FAILURE;
        }

        $repositoryId = $this->option('repository');
        $repositoryId = $repositoryId === null ? null : (int) $repositoryId;
        $chunk = max(1000, (int) $this->option('chunk'));

        if ($this->option('truncate')) {
            $deleted = DB::table('ahg_io_facet_denorm')
                ->whereIn('taxonomy_id', $taxonomies)
                ->when($repositoryId !== null, fn ($q) => $q->where('repository_id', $repositoryId))
                ->delete();
            $this->line("Cleared {$deleted} existing rows for taxonomies " . implode(',', $taxonomies)
                . ($repositoryId !== null ? " repo={$repositoryId}" : ''));
        }

        $minId = (int) DB::table('information_object')->min('id');
        $maxId = (int) DB::table('information_object')->max('id');
        if ($maxId === 0) {
            $this->warn('information_object is empty.');
            return self::SUCCESS;
        }

        $totalInserted = 0;
        $started = microtime(true);

        foreach ($taxonomies as $taxonomyId) {
            $taxInserted = 0;
            $this->info("Taxonomy {$taxonomyId}: populating from id={$minId} to id={$maxId} in chunks of {$chunk}");

            $bar = $this->output->createProgressBar(max(1, (int) ceil(($maxId - $minId + 1) / $chunk)));
            $bar->start();

            for ($lo = $minId; $lo <= $maxId; $lo += $chunk) {
                $hi = $lo + $chunk - 1;

                $bindings = [$taxonomyId, $lo, $hi];
                $repoClause = '';
                if ($repositoryId !== null) {
                    $repoClause = ' AND io.repository_id = ?';
                    $bindings[] = $repositoryId;
                }

                $sql = "INSERT IGNORE INTO ahg_io_facet_denorm
                            (io_id, term_id, taxonomy_id, repository_id, updated_at)
                        SELECT
                            otr.object_id   AS io_id,
                            otr.term_id     AS term_id,
                            t.taxonomy_id   AS taxonomy_id,
                            io.repository_id AS repository_id,
                            NOW()           AS updated_at
                        FROM object_term_relation otr
                        INNER JOIN term t ON t.id = otr.term_id AND t.taxonomy_id = ?
                        INNER JOIN information_object io ON io.id = otr.object_id
                        WHERE otr.object_id BETWEEN ? AND ?
                        {$repoClause}";

                $inserted = DB::affectingStatement($sql, $bindings);
                $taxInserted += $inserted;
                $bar->advance();
            }

            $bar->finish();
            $this->line('');
            $this->line("  taxonomy={$taxonomyId} inserted={$taxInserted}");
            $totalInserted += $taxInserted;
        }

        $elapsed = round(microtime(true) - $started, 2);

        $this->info("Done. Inserted {$totalInserted} rows across " . count($taxonomies) . " taxonomy/ies in {$elapsed}s.");

        $this->table(['taxonomy_id', 'rows_in_sidecar'], DB::table('ahg_io_facet_denorm')
            ->select('taxonomy_id', DB::raw('COUNT(*) AS rows_in_sidecar'))
            ->groupBy('taxonomy_id')
            ->orderBy('taxonomy_id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all());

        $this->line('');
        $this->line('To enable the sidecar read path, set:');
        $this->line('  ahg_settings.ahg_display_use_facet_denorm = 1');

        return self::SUCCESS;
    }
}
