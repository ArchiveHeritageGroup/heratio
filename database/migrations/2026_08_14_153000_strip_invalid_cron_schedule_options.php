<?php

/**
 * Remove options from stored schedules that the command does not define.
 *
 * Twelve seeded schedules invoked their command with an option it has never
 * had - `ahg:backup --components=... --retention=...`, `--db-user`,
 * `--threshold`, `--depth`, `--jurisdiction`, `--quarantine`, `--risk-level`,
 * `queue:retry --all` - so each died on
 * `The "--X" option does not exist.` on EVERY run. Backups, virus scanning,
 * replication, obsolescence review, Qdrant indexing, duplicate detection, AAT
 * sync, PII scanning and queue retries were all scheduled and none of them had
 * ever executed.
 *
 * The seed is corrected alongside this, which fixes fresh installs. Existing
 * `cron_schedule` rows are what actually run, so they need repairing too - the
 * same lesson as the workflow-process fix immediately before this one, where
 * correcting only the seed left all three instances still failing.
 *
 * This validates against the command's REAL definition at migration time rather
 * than a hardcoded list, so it also catches any other row that has drifted, and
 * it is conservative: it strips only the unknown option and leaves the valid
 * ones, so a command runs with its own defaults instead of not running at all.
 * `queue:retry --all` is special-cased, since Laravel takes `all` as an
 * argument rather than a flag.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
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

        try {
            $all = Artisan::all();
        } catch (\Throwable $e) {
            // Console kernel unavailable - leave the rows alone rather than guess.
            return;
        }

        $repaired = [];

        foreach (DB::table('cron_schedule')->get(['id', 'slug', 'artisan_command']) as $row) {
            $cmd = trim((string) $row->artisan_command);
            if ($cmd === '') {
                continue;
            }

            if ($cmd === 'queue:retry --all') {
                DB::table('cron_schedule')->where('id', $row->id)
                    ->update(['artisan_command' => 'queue:retry all']);
                $repaired[] = "{$row->slug}: --all -> all";

                continue;
            }

            $parts = preg_split('/\s+/', $cmd) ?: [];
            $name = array_shift($parts);
            $def = $all[$name] ?? null;
            if (! $def) {
                continue;   // unknown command is a different problem; do not touch it
            }

            $kept = [];
            $dropped = [];
            foreach ($parts as $part) {
                if (! str_starts_with($part, '--')) {
                    $kept[] = $part;

                    continue;
                }
                $opt = ltrim(explode('=', $part)[0], '-');
                if ($def->getDefinition()->hasOption($opt)) {
                    $kept[] = $part;
                } else {
                    $dropped[] = $part;
                }
            }

            if ($dropped === []) {
                continue;
            }

            $new = trim($name.' '.implode(' ', $kept));
            DB::table('cron_schedule')->where('id', $row->id)->update(['artisan_command' => $new]);
            $repaired[] = "{$row->slug}: dropped ".implode(' ', $dropped);
        }

        if ($repaired !== []) {
            Log::info('Repaired '.count($repaired).' cron_schedule row(s) invoking options their command does not define: '.implode('; ', $repaired));
        }
    }

    public function down(): void
    {
        // Not reversed: restoring an option the command rejects only re-breaks it.
    }
};
