<?php

/**
 * FindingAidDeleteCommand — remove generated finding aid files / DB rows.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FindingAidDeleteCommand extends Command
{
    protected $signature = 'ahg:finding-aid-delete
        {--slug= : Delete finding aid for specific slug}
        {--older-than= : Delete finding aids older than N days}
        {--dry-run : Show what would be deleted}';

    protected $description = 'Delete finding aids (file + DB row)';

    public function handle(): int
    {
        if (! Schema::hasTable('finding_aid')) {
            $this->warn('finding_aid table not present.');
            return self::SUCCESS;
        }
        $dry = (bool) $this->option('dry-run');

        $q = DB::table('finding_aid as fa')
            ->leftJoin('slug as s', 's.object_id', '=', 'fa.information_object_id')
            ->select('fa.id', 'fa.information_object_id', 'fa.name', 'fa.created_at', 's.slug');
        if ($slug = $this->option('slug')) {
            $q->where('s.slug', $slug);
        } elseif ($days = $this->option('older-than')) {
            $cutoff = now()->subDays(max(1, (int) $days));
            $q->where('fa.created_at', '<', $cutoff);
        } else {
            $this->error('Specify --slug or --older-than');
            return self::FAILURE;
        }

        $rows = $q->get();
        $this->info("eligible: {$rows->count()}" . ($dry ? ' (dry-run)' : ''));

        $base = config('heratio.uploads_path', storage_path('app/uploads'));
        $deleted = 0;
        foreach ($rows as $r) {
            $candidate = rtrim((string) $base, '/') . '/findingaid/' . $r->information_object_id . '.pdf';
            if (! $dry) {
                if (is_file($candidate)) @unlink($candidate);
                DB::table('finding_aid')->where('id', $r->id)->delete();
            }
            $deleted++;
            $this->line(sprintf('  %s #%d (%s)', $dry ? 'would-delete' : 'deleted', $r->id, $r->slug ?: 'no-slug'));
        }
        $this->info("deleted={$deleted}");
        return self::SUCCESS;
    }
}
