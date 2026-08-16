<?php

/**
 * Disable schedules that cannot run unattended on this host.
 *
 * Four kinds of entry, all of which failed on every run overnight:
 *
 * 1. Commands that REQUIRE a per-invocation argument and have no batch mode:
 *    `3d-multiangle` needs --id/--object-id, `ai-translate` needs --to, and
 *    `preservation-package` needs --object-id. Requiring the argument
 *    interactively is correct; a nightly run with none is meaningless. The seed
 *    already disables the first two as of v1.154.615 - but the SEED only affects
 *    fresh installs, and the stored row is what actually runs, which is why they
 *    kept failing on all three instances afterwards. Third time that distinction
 *    has bitten; this repairs the rows.
 *
 * 2. `refresh-facet-cache`, which is ALSO driven by /etc/cron.d/ahg-facet-cache
 *    at the same minute. That system cron does more (it loops over the dam and
 *    heratio databases) and holds its own flock, but the two schedulers cannot
 *    see each other: every hour the managed run and the cron run collided and
 *    one died on "Lock wait timeout exceeded". withoutOverlapping(120), added in
 *    v1.154.617, guards the Laravel scheduler against ITSELF and was never going
 *    to help here. The system cron owns this job, so the managed row stands down
 *    - but ONLY where that file is present, or an instance without it would stop
 *    refreshing facets altogether.
 *
 * 3. Exports pointed at directories that exist on no instance - /exports/weekly,
 *    /backups/ead. An operator who wants these should create the directory and
 *    re-enable the schedule; failing every night at a path nobody has made is
 *    noise, not a warning.
 *
 * Nothing is deleted. Every row stays visible in the cron admin with its
 * schedule intact, and re-enabling is one click once the prerequisite exists.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cron_schedule')) {
            return;
        }

        $disabled = [];

        // 1. Needs an argument no schedule can supply.
        foreach (['3d-multiangle', 'ai-translate', 'preservation-package'] as $slug) {
            $n = DB::table('cron_schedule')
                ->where('slug', $slug)->where('is_enabled', 1)->update(['is_enabled' => 0]);
            if ($n) {
                $disabled[] = $slug.' (requires a per-run argument)';
            }
        }

        // 1b. heritage-build-graph writes entity_id / repository_id / level_id /
        // cached_at into heritage_entity_cache, which has none of those columns -
        // it is an NER-style entity cache (entity_value, normalized_value,
        // confidence_score, extraction_method) keyed by object_id. The command
        // targets the wrong table, so this is a design question rather than a
        // rename, and it is disabled until that is answered rather than left
        // failing nightly. Its join was corrected alongside this so whoever picks
        // it up starts from working SQL.
        $n = DB::table('cron_schedule')
            ->where('slug', 'heritage-build-graph')->where('is_enabled', 1)->update(['is_enabled' => 0]);
        if ($n) {
            $disabled[] = 'heritage-build-graph (writes columns heritage_entity_cache does not have)';
        }

        // 2. Owned by a system cron on this host, so the managed copy collides.
        if (file_exists('/etc/cron.d/ahg-facet-cache')) {
            $n = DB::table('cron_schedule')
                ->where('slug', 'refresh-facet-cache')->where('is_enabled', 1)->update(['is_enabled' => 0]);
            if ($n) {
                $disabled[] = 'refresh-facet-cache (driven by /etc/cron.d/ahg-facet-cache)';
            }
        }

        // 3. Writes to a directory that does not exist.
        foreach ([
            'metadata-export' => '/exports/weekly',
            'export-bulk-ead' => '/backups/ead',
        ] as $slug => $path) {
            if (is_dir($path)) {
                continue;
            }
            $n = DB::table('cron_schedule')
                ->where('slug', $slug)->where('is_enabled', 1)->update(['is_enabled' => 0]);
            if ($n) {
                $disabled[] = $slug.' (output path '.$path.' does not exist)';
            }
        }

        if ($disabled !== []) {
            Log::info('Disabled '.count($disabled).' schedule(s) that cannot run unattended: '.implode('; ', $disabled));
        }
    }

    public function down(): void
    {
        // Not reversed: re-enabling would restore a schedule that fails every run.
    }
};
