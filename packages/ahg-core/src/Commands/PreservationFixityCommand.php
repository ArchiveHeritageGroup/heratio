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
        {--baseline : Record a first checksum for files that have none, instead of verifying}
        {--rebaseline : Accept the current file as the new baseline for files whose latest check failed (deliberate changes only)}
        {--notify : Tell administrators (and preservation_alert_email) about files found missing or changed}';

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

        if (! $this->option('rebaseline') && ($doId = $this->option('digital-object-id'))) {
            $result = $svc->verifyFixity((int) $doId);
            $this->info($result ? "verified do={$doId} status={$result->status}" : "no checksum recorded for do={$doId}");

            return self::SUCCESS;
        }

        // A deliberate change (e.g. a 3D model re-optimised after enrolment) fails
        // fixity forever until someone accepts it. --rebaseline appends a new
        // checksum (verifyFixity() reads the latest), logs why, and re-verifies.
        // The old checksum and the failed checks stay on record.
        if ($this->option('rebaseline')) {
            $latest = \Illuminate\Support\Facades\DB::table('preservation_fixity_check')
                ->select('digital_object_id', \Illuminate\Support\Facades\DB::raw('MAX(id) as max_id'))->groupBy('digital_object_id');
            $ids = \Illuminate\Support\Facades\DB::table('preservation_fixity_check as f')
                ->joinSub($latest, 'l', fn ($j) => $j->on('f.id', '=', 'l.max_id'))
                ->where('f.status', 'fail')
                ->when($this->option('digital-object-id'), fn ($q, $id) => $q->where('f.digital_object_id', (int) $id))
                ->pluck('f.digital_object_id');
            $done = 0;
            foreach ($ids as $id) {
                // preservation_checksum is unique per (object, algorithm): replace
                // the stored value in place and keep the old one in the event.
                $row = \Illuminate\Support\Facades\DB::table('preservation_checksum')->where('digital_object_id', $id)->orderByDesc('generated_at')->first();
                $do = \Illuminate\Support\Facades\DB::table('digital_object')->where('id', $id)->first();
                $file = $do ? \AhgCore\Services\DigitalObjectService::resolveDiskPath($do) : null;
                if (! $row || ! $file || ! is_file($file)) {
                    $this->line("  skipped do={$id} - no checksum or file not found");

                    continue;
                }
                $new = hash_file(str_replace('-', '', strtolower($row->algorithm)), $file);
                \Illuminate\Support\Facades\DB::table('preservation_checksum')->where('id', $row->id)->update([
                    'checksum_value' => $new, 'file_size' => filesize($file),
                    'generated_at' => now()->format('Y-m-d H:i:s'), 'verification_status' => 'pending',
                ]);
                $svc->logEvent((int) $id, null, 'fixity_rebaseline',
                    "Changed file accepted as the new fixity baseline (operator decision): {$row->algorithm} {$row->checksum_value} -> {$new}", 'success');
                $r = $svc->verifyFixity((int) $id);
                $this->line("  rebaselined do={$id} now ".($r->status ?? '?'));
                $done++;
            }
            $this->info("rebaseline: {$done} file(s) accepted");

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
        $failed = [];
        foreach ($stale as $row) {
            $r = $svc->verifyFixity((int) $row->digital_object_id);
            // verifyFixity() records 'pass' / 'fail' (never 'verified'), so every
            // good file used to be reported as a failure.
            if ($r && ($r->status ?? '') === 'pass') {
                $ok++;
            } else {
                $fail++;
                if ($r) {
                    $failed[] = ['id' => (int) $row->digital_object_id, 'name' => (string) ($row->file_name ?? ''), 'error' => (string) ($r->error_message ?? '')];
                }
                $this->line('  '.($r ? 'mismatch' : 'no checksum')." do={$row->digital_object_id}".($r && $r->error_message ? ' - '.$r->error_message : ''));
            }
        }
        $this->info("done; ok={$ok} fail={$fail}");
        if ($failed && $this->option('notify')) {
            $this->notifyFailures($failed);
        }

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * heratio#1735: a missing or changed file is noticed the same day - while
     * a backup can still bring it back - instead of only on the fixity pages.
     */
    private function notifyFailures(array $failed): void
    {
        $db = \Illuminate\Support\Facades\DB::class;
        $lines = [];
        foreach (array_slice($failed, 0, 50) as $f) {
            $do = $db::table('digital_object')->where('id', $f['id'])->first(['object_id', 'parent_id']);
            $ioId = $do->object_id ?? ($do && $do->parent_id ? $db::table('digital_object')->where('id', $do->parent_id)->value('object_id') : null);
            $slug = $ioId ? $db::table('slug')->where('object_id', $ioId)->value('slug') : null;
            $lastGood = $db::table('preservation_fixity_check')->where('digital_object_id', $f['id'])->where('status', 'pass')->max('checked_at');
            $lines[] = '- '.($f['name'] ?: 'object '.$f['id']).($slug ? ' ('.url('/'.$slug).')' : '')
                .': '.($f['error'] ?: __('checksum does not match')).'; '.($lastGood ? __('last good check :date', ['date' => $lastGood]) : __('never verified good'));
        }
        if (count($failed) > 50) {
            $lines[] = __('... and :count more', ['count' => count($failed) - 50]);
        }
        $link = \Illuminate\Support\Facades\Route::has('preservation.fixity-log') ? route('preservation.fixity-log', ['status' => 'fail']) : null;
        $title = __('Fixity: :count file(s) missing or changed', ['count' => count($failed)]);
        $body = implode("\n", $lines);

        $n = app(\AhgCore\Services\NotificationService::class)->notifyAdmins('preservation_fixity', $title, $body, $link, 'digital_object', $failed[0]['id']);
        $to = array_filter(array_map('trim', explode(',', (string) \AhgCore\Services\AhgSettingsService::get('preservation_alert_email', ''))));
        foreach ($to as $email) {
            try {
                \Illuminate\Support\Facades\Mail::raw($title."\n\n".$body.($link ? "\n\n".__('Fixity report').': '.$link : ''), fn ($m) => $m->to($email)->subject($title));
            } catch (\Throwable $e) {
                $this->warn('alert mail to '.$email.' failed: '.$e->getMessage());
            }
        }
        $this->info("alerted {$n} administrator(s) and ".count($to).' address(es)');
    }
}
