<?php

namespace AhgCore\Commands;

use AhgDoiManage\Services\DoiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DoiMintCommand extends Command
{
    protected $signature = 'ahg:doi-mint
        {--object-id= : Information object ID (single mint)}
        {--repository= : Repository ID for batch mint}
        {--level= : Restrict to a level_of_description name (Fonds, Series, Item, ...)}
        {--limit=10 : Maximum DOIs to mint in batch}
        {--dry-run : Simulate without minting}';

    protected $description = 'Mint DOIs via DataCite';

    public function handle(DoiService $svc): int
    {
        $dry = (bool) $this->option('dry-run');
        $oid = $this->option('object-id');

        // Single-record path.
        if ($oid) {
            $r = $svc->mint((int) $oid, null, $dry);
            $this->info($r['success']
                ? sprintf("ok  oid=%s doi=%s%s", $oid, $r['doi'], $dry ? ' (dry-run)' : '')
                : sprintf("FAIL oid=%s error=%s", $oid, $r['error']));
            return $r['success'] ? self::SUCCESS : self::FAILURE;
        }

        // Batch path: pull candidates from atom DB, skip those already with a DOI row.
        $repoId = $this->option('repository');
        $level  = $this->option('level');
        $limit  = max(1, (int) $this->option('limit'));

        $q = DB::connection('atom')->table('information_object as i')
            ->leftJoin('ahg_doi_config as cfg', function ($j) {
                $j->on('cfg.is_active', '=', DB::raw('1'));
            })
            ->select('i.id');
        if ($repoId) $q->where('i.repository_id', (int) $repoId);
        if ($level) {
            $q->join('term_i18n as ti', function ($j) use ($level) {
                $j->on('i.level_of_description_id', '=', 'ti.id')->where('ti.culture', '=', 'en')->where('ti.name', '=', $level);
            });
        }
        $existingDois = DB::table('ahg_doi')->pluck('information_object_id')->all();
        if (! empty($existingDois)) $q->whereNotIn('i.id', $existingDois);
        $candidates = $q->orderBy('i.id')->limit($limit)->pluck('i.id')->all();

        $this->info("found " . count($candidates) . " candidates" . ($dry ? ' (dry-run)' : ''));
        $ok = 0; $fail = 0;
        foreach ($candidates as $cid) {
            $r = $svc->mint((int) $cid, $repoId ? (int) $repoId : null, $dry);
            if ($r['success']) { $ok++; $this->line(sprintf("  ok  oid=%s doi=%s", $cid, $r['doi'])); }
            else { $fail++; $this->line(sprintf("  fail oid=%s error=%s", $cid, $r['error'])); }
        }
        $this->info("done; ok={$ok} fail={$fail}");
        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }
}
