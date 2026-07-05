<?php

namespace AhgCore\Commands;

use AhgDisplay\Services\DisplayTypeDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * heratio#1399 — classify information objects into GLAM object types
 * (archive/library/museum/gallery/dam/universal), stored in
 * `display_object_config.object_type`. Records with no explicit signal default
 * to `archive` (an archive with no type IS archive).
 *
 * This replaces the previous broken implementation, which queried and wrote a
 * phantom `information_object.display_type` column that exists on no schema. It
 * now delegates to the real DisplayTypeDetector (display standard → level →
 * parent → events → media type → archive default) which writes the correct
 * table, and adds a fast set-based `--bulk-archive` path for homogeneous
 * archives / very large corpora.
 */
class DisplayAutoDetectCommand extends Command
{
    protected $signature = 'ahg:display-auto-detect
        {--repository= : Only process IOs in this repository_id}
        {--limit=0 : Max IOs to process this run (0 = all unclassified)}
        {--bulk-archive : Fast path — classify every unclassified IO as "archive" in one query, skipping per-record detection (homogeneous archives / large corpora)}
        {--dry-run : Report how many IOs are unclassified without writing}';

    protected $description = 'Auto-detect GLAM object types for IOs with no display_object_config classification (defaults to archive)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $repo = $this->option('repository') ? (int) $this->option('repository') : null;
        $limit = max(0, (int) $this->option('limit'));

        // IOs that have no classification row yet.
        $base = DB::table('information_object as io')
            ->leftJoin('display_object_config as d', 'd.object_id', '=', 'io.id')
            ->whereNull('d.id')
            ->where('io.id', '!=', 1);
        if ($repo !== null) {
            $base->where('io.repository_id', $repo);
        }

        $pending = (clone $base)->count();
        $this->info("{$pending} information object(s) unclassified".($dry ? ' (dry-run)' : ''));

        if ($pending === 0) {
            return self::SUCCESS;
        }

        if ($dry) {
            $this->line('  --bulk-archive would classify all of them as "archive".');
            $this->line('  per-record detection would type each (display standard → level → parent → events → media, defaulting to archive).');

            return self::SUCCESS;
        }

        // ── Fast set-based path ──────────────────────────────────────────────
        if ($this->option('bulk-archive')) {
            $sql = "INSERT INTO display_object_config (object_id, object_type, created_at, updated_at)
                    SELECT io.id, 'archive', NOW(), NOW()
                    FROM information_object io
                    LEFT JOIN display_object_config d ON d.object_id = io.id
                    WHERE d.id IS NULL AND io.id != 1";
            if ($repo !== null) {
                $sql .= ' AND io.repository_id = '.$repo;
            }
            if ($limit > 0) {
                $sql .= ' LIMIT '.$limit;
            }
            $inserted = DB::affectingStatement($sql);
            $this->info("bulk-archive: classified {$inserted} IO(s) as archive.");

            return self::SUCCESS;
        }

        // ── Per-record detection (accurate) ──────────────────────────────────
        $q = (clone $base)->select('io.id')->orderBy('io.id');
        if ($limit > 0) {
            $q->limit($limit);
        }
        $ids = $q->pluck('io.id');

        $bar = $this->output->createProgressBar($ids->count());
        $byType = [];
        foreach ($ids as $id) {
            $type = DisplayTypeDetector::detectAndSave((int) $id);
            $byType[$type] = ($byType[$type] ?? 0) + 1;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        arsort($byType);
        foreach ($byType as $t => $n) {
            $this->line(sprintf('  %-12s %d', $t, $n));
        }

        return self::SUCCESS;
    }
}
