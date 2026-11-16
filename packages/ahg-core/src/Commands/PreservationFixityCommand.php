<?php

namespace AhgCore\Commands;

use AhgPreservation\Services\PreservationService;
use Illuminate\Console\Command;

class PreservationFixityCommand extends Command
{
    protected $signature = 'ahg:preservation-fixity
        {--algorithm=sha256 : Hash algorithm to use}
        {--limit=100 : Maximum files to verify in this run}
        {--age=90 : Only digital_objects whose last verification is older than N days}
        {--digital-object-id= : Verify a single digital_object}
        {--report : Print summary report and exit}
        {--baseline : Record a first checksum for files that have none, instead of verifying}';

    protected $description = 'Verify file integrity checksums (delegates to PreservationService::verifyFixity)';

    public function handle(PreservationService $svc): int
    {
        if ($this->option('report')) {
            $stats = $svc->getStatistics();
            $this->info('=== fixity report ===');
            foreach ((array) $stats as $k => $v) {
                $this->line("  {$k}: ".(is_scalar($v) ? $v : json_encode($v)));
            }

            return self::SUCCESS;
        }

        if ($doId = $this->option('digital-object-id')) {
            $result = $svc->verifyFixity((int) $doId);
            $this->info($result ? "verified do={$doId} status={$result->status}" : "no checksum recorded for do={$doId}");

            return self::SUCCESS;
        }

        // Files ingested before checksums were taken on upload have no baseline,
        // and verifyFixity() skips them, so coverage never grew past new uploads.
        if ($this->option('baseline')) {
            $todo = $svc->getObjectsWithoutChecksums((int) $this->option('limit'));
            $made = 0;
            $missing = 0;
            foreach ($todo as $row) {
                $svc->generateChecksum((int) $row->id, (string) $this->option('algorithm')) ? $made++ : $missing++;
            }
            $this->info("baseline: {$made} checksum(s) recorded, {$missing} file(s) not found on disk");

            return self::SUCCESS;
        }

        $stale = $svc->getStaleFixityObjects((int) $this->option('age'), (int) $this->option('limit'));
        $this->info('verifying '.$stale->count().' stale objects (age>'.$this->option('age').'d)');
        $ok = 0;
        $fail = 0;
        foreach ($stale as $row) {
            $r = $svc->verifyFixity((int) $row->digital_object_id);
            // verifyFixity() records 'pass' / 'fail' (never 'verified'), so every
            // good file used to be reported as a failure.
            if ($r && ($r->status ?? '') === 'pass') {
                $ok++;
            } else {
                $fail++;
                $this->line('  '.($r ? 'mismatch' : 'no checksum')." do={$row->digital_object_id}".($r && $r->error_message ? ' - '.$r->error_message : ''));
            }
        }
        $this->info("done; ok={$ok} fail={$fail}");

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }
}
