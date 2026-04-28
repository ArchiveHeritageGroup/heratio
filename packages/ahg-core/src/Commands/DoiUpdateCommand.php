<?php

namespace AhgCore\Commands;

use AhgDoiManage\Services\DoiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DoiUpdateCommand extends Command
{
    protected $signature = 'ahg:doi-update
        {--doi= : Specific DOI to update}
        {--object-id= : Specific information_object_id to update}
        {--modified-since= : Update DOIs whose IO was modified since DATE (Y-m-d or relative)}
        {--all : Update all DOIs}
        {--limit=100 : Maximum to update in one run}
        {--dry-run : Report without writing}';

    protected $description = 'Push fresh metadata to DataCite for existing DOIs';

    public function handle(DoiService $svc): int
    {
        if ($d = $this->option('doi')) {
            $r = $this->option('dry-run') ? ['success' => true, 'error' => 'dry-run'] : $svc->update((string) $d);
            $this->info($r['success'] ? "ok  {$d}" . (isset($r['error']) ? " ({$r['error']})" : '') : "fail {$d} {$r['error']}");
            return $r['success'] ? self::SUCCESS : self::FAILURE;
        }
        if ($oid = $this->option('object-id')) {
            $r = $svc->updateByObject((int) $oid);
            $this->info($r['success'] ? "ok oid={$oid}" : "fail oid={$oid} {$r['error']}");
            return $r['success'] ? self::SUCCESS : self::FAILURE;
        }

        $q = DB::table('ahg_doi');
        if ($since = $this->option('modified-since')) {
            $cutoff = is_numeric($since) ? now()->subDays((int) $since) : \Carbon\Carbon::parse((string) $since);
            $q->where('last_sync_at', '<', $cutoff);
        } elseif (! $this->option('all')) {
            $q->where('last_sync_at', '<', now()->subDays(7));
        }
        $limit = max(1, (int) $this->option('limit'));
        $rows = $q->orderBy('last_sync_at')->limit($limit)->pluck('doi');
        $this->info("updating " . $rows->count() . " DOIs" . ($this->option('dry-run') ? ' (dry-run)' : ''));
        $ok = 0; $fail = 0;
        foreach ($rows as $doi) {
            if ($this->option('dry-run')) { $ok++; continue; }
            $r = $svc->update($doi);
            if ($r['success']) $ok++;
            else { $fail++; $this->line("  fail {$doi}: {$r['error']}"); }
        }
        $this->info("done; ok={$ok} fail={$fail}");
        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }
}
