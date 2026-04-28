<?php

namespace AhgCore\Commands;

use AhgDoiManage\Services\DoiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DoiVerifyCommand extends Command
{
    protected $signature = 'ahg:doi-verify
        {--all : Verify all DOIs}
        {--doi= : Verify a specific DOI string}
        {--limit=100 : Maximum DOIs to verify}';

    protected $description = 'Verify DOI registrations against DataCite (round-trip check + status refresh)';

    public function handle(DoiService $svc): int
    {
        if ($d = $this->option('doi')) {
            $r = $svc->verify((string) $d);
            $this->info($r['success']
                ? "ok  {$d} state={$r['state']}"
                : "fail {$d} {$r['error']}");
            return $r['success'] ? self::SUCCESS : self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $q = DB::table('ahg_doi');
        if (! $this->option('all')) {
            $q->where('last_sync_at', '<', now()->subDays(7));
        }
        $rows = $q->orderBy('last_sync_at')->limit($limit)->pluck('doi');
        $this->info("verifying " . $rows->count() . " DOIs");
        $ok = 0; $fail = 0;
        foreach ($rows as $doi) {
            $r = $svc->verify($doi);
            if ($r['success']) $ok++;
            else { $fail++; $this->line("  fail {$doi}: {$r['error']}"); }
        }
        $this->info("done; ok={$ok} fail={$fail}");
        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }
}
