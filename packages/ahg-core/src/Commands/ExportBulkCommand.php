<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ExportBulkCommand extends Command
{
    protected $signature = 'ahg:export-bulk
        {--criteria= : JSON criteria (e.g. {"repository_id":1,"level":"Fonds"})}
        {--format=ead3 : Export format (ead, ead3, dc, mods, marc, lido, ric, csv)}
        {--path=storage/exports : Output directory}
        {--limit=1000 : Max IOs to export}';

    protected $description = 'Bulk export information_object descriptions in a chosen GLAM standard';

    public function handle(): int
    {
        $criteria = json_decode((string) ($this->option('criteria') ?? '{}'), true) ?: [];
        $format = strtolower((string) $this->option('format'));
        $path = base_path((string) $this->option('path'));
        @mkdir($path, 0775, true);

        $q = DB::connection('atom')->table('information_object as i')->select('i.id');
        if (! empty($criteria['repository_id'])) $q->where('i.repository_id', (int) $criteria['repository_id']);
        if (! empty($criteria['level'])) {
            $q->join('term_i18n as ti', function ($j) use ($criteria) {
                $j->on('ti.id', '=', 'i.level_of_description_id')->where('ti.culture', '=', 'en')->where('ti.name', '=', $criteria['level']);
            });
        }
        $ids = $q->limit((int) $this->option('limit'))->pluck('i.id');
        $this->info("matched IOs: {$ids->count()}");

        // Delegate to per-format exporter via the existing metadata-export package.
        // We dispatch each as a single-IO command and collate; cheap because the IDs are small.
        $written = 0;
        foreach ($ids as $oid) {
            $rc = Artisan::call('ahg:metadata-export', [
                '--object-id' => (int) $oid,
                '--format'    => $format,
                '--output'    => $path . "/{$oid}.{$format}.xml",
            ]);
            if ($rc === 0) $written++;
            if ($written % 100 === 0 && $written > 0) $this->line("  exported {$written}/{$ids->count()}");
        }
        $this->info("done; written={$written} dir={$path}");
        return self::SUCCESS;
    }
}
