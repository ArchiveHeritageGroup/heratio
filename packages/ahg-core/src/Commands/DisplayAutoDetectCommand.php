<?php

namespace AhgCore\Commands;

use AhgDisplay\Services\DisplayTypeDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DisplayAutoDetectCommand extends Command
{
    protected $signature = 'ahg:display-auto-detect
        {--repository= : Only process IOs in this repository_id}
        {--limit=1000 : Max IOs to inspect}
        {--connection=atom : Source DB}
        {--dry-run : Show detections without saving display_type}';

    protected $description = 'Auto-detect GLAM object types (archive/library/gallery/museum/dam) for IOs without explicit display_type';

    public function handle(DisplayTypeDetector $detector): int
    {
        $conn = (string) $this->option('connection');
        $limit = max(1, (int) $this->option('limit'));
        $dry = (bool) $this->option('dry-run');

        $q = DB::connection($conn)->table('information_object as i')
            ->whereNull('i.display_type')
            ->select('i.id', 'i.repository_id', 'i.level_of_description_id', 'i.material_type_id');
        if ($repo = $this->option('repository')) $q->where('i.repository_id', (int) $repo);
        $rows = $q->limit($limit)->get();
        $this->info("scanning {$rows->count()} IOs without display_type" . ($dry ? ' (dry-run)' : ''));

        $byType = []; $written = 0;
        foreach ($rows as $r) {
            $type = $detector->detect((array) $r);
            $byType[$type] = ($byType[$type] ?? 0) + 1;
            if (! $dry && $type !== 'unknown') {
                DB::connection($conn)->table('information_object')->where('id', $r->id)->update(['display_type' => $type]);
                $written++;
            }
        }

        foreach ($byType as $t => $n) $this->line(sprintf("  %-15s %d", $t, $n));
        $this->info("wrote {$written}");
        return self::SUCCESS;
    }
}
