<?php

namespace AhgCore\Commands;

use AhgPreservation\Services\PreservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PreservationVirusScanCommand extends Command
{
    protected $signature = 'ahg:preservation-virus-scan
        {--limit=200 : Max digital_objects to scan in this run}
        {--unscanned : Only scan digital_objects with no prior scan record}
        {--report : Show recent virus scan history and exit}
        {--clamav-binary=clamscan : Path to ClamAV CLI}';

    protected $description = 'Scan files for malware via ClamAV; logs to preservation_event';

    public function handle(PreservationService $svc): int
    {
        if ($this->option('report')) {
            $rows = $svc->getVirusScans(50);
            $this->info('=== recent virus scans (50) ===');
            foreach ($rows as $r) $this->line(sprintf("  %s do=%-7s status=%-10s detail=%s",
                $r->scanned_at ?? '-', $r->digital_object_id ?? '-', $r->status ?? '-', mb_strimwidth((string) ($r->detail ?? ''), 0, 80, '...')));
            return self::SUCCESS;
        }

        $bin = (string) $this->option('clamav-binary');
        $check = @shell_exec("{$bin} --version 2>/dev/null");
        if (! $check) {
            $this->error("ClamAV binary '{$bin}' not found in PATH. Install clamav or pass --clamav-binary=/full/path.");
            return self::FAILURE;
        }
        $this->line("ClamAV: " . trim($check));

        // Pick targets: digital_objects with no scan event yet (when --unscanned), capped to --limit.
        $limit = max(1, (int) $this->option('limit'));
        $q = DB::table('digital_object as do')->select('do.id', 'do.path', 'do.name');
        if ($this->option('unscanned')) {
            $q->leftJoin('preservation_event as pe', function ($j) {
                $j->on('pe.digital_object_id', '=', 'do.id')->where('pe.event_type', '=', 'virus_scan');
            })->whereNull('pe.id');
        }
        $rows = $q->limit($limit)->get();
        $this->info("scanning {$rows->count()} digital_objects");

        $clean = 0; $infected = 0; $skipped = 0;
        foreach ($rows as $r) {
            $full = rtrim((string) config('heratio.uploads_path', ''), '/') . $r->path . $r->name;
            if (! is_file($full)) { $skipped++; continue; }
            $out = @shell_exec(escapeshellcmd($bin) . ' --no-summary ' . escapeshellarg($full) . ' 2>&1');
            $ok = $out !== null && str_contains((string) $out, ': OK');
            $svc->logEvent((int) $r->id, null, 'virus_scan', trim((string) $out), $ok ? 'clean' : 'infected');
            $ok ? $clean++ : $infected++;
            if (! $ok) $this->line("  INFECTED do={$r->id} path={$full}");
        }
        $this->info("done; clean={$clean} infected={$infected} skipped={$skipped}");
        return $infected === 0 ? self::SUCCESS : self::FAILURE;
    }
}
