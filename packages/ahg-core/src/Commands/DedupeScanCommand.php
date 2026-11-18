<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupeScanCommand extends Command
{
    protected $signature = 'ahg:dedupe-scan
        {--limit=10000 : Max IOs to scan in this pass}
        {--repository= : Filter by repository_id}
        {--connection= : Source DB connection}';

    protected $description = 'Start a duplicate-record scan: enumerates same-title IOs into ahg_dedupe_scan';

    public function handle(): int
    {
        $conn = (string) $this->option('connection');

        // Not 'atom' by default - see AhgCore\Support\DiscoverySource. That
        // database exists only on an AtoM overlay, so a Heratio-native instance
        // was denied on every run.
        $conn = \AhgCore\Support\DiscoverySource::connectionName($conn);
        if (! \AhgCore\Support\DiscoverySource::usable($conn)) {
            $this->warn("Source connection '{$conn}' is not usable here - nothing to process.");

            return self::SUCCESS;
        }
        $repoId = $this->option('repository');
        $limit = max(100, (int) $this->option('limit'));

        // Open a scan row.
        $scanId = DB::table('ahg_dedupe_scan')->insertGetId([
            'repository_id' => $repoId ? (int) $repoId : null,
            'status' => 'in_progress',
            'started_at' => now(),
            'created_at' => now(),
            'scope' => $repoId ? "repo={$repoId}" : 'all',
        ]);
        $this->info("opened ahg_dedupe_scan id={$scanId}");

        try {
            $q = DB::connection($conn)->table('information_object_i18n')
                ->where('culture', 'en')
                ->whereNotNull('title');
            $totalScanned = 0;
            $duplicateGroups = 0;

            $sub = $q->selectRaw('LOWER(TRIM(title)) AS norm, COUNT(*) AS n, GROUP_CONCAT(id ORDER BY id) AS ids')
                ->groupBy('norm')
                ->havingRaw('COUNT(*) > 1')
                ->limit($limit)
                ->get();
            foreach ($sub as $row) {
                $totalScanned += (int) $row->n;
                $duplicateGroups++;
            }

            // heratio#1745: identical files on different records, by checksum.
            // Each new pair goes to the review queue, as on upload.
            $checksumPairs = 0;
            $dupeChecksums = DB::table('digital_object')
                ->where('usage_id', \AhgCore\Services\DigitalObjectService::USAGE_MASTER)
                ->whereNotNull('checksum')->where('checksum', '!=', '')
                ->when($repoId, fn ($q) => $q->whereIn('object_id', DB::table('information_object')->where('repository_id', (int) $repoId)->select('id')))
                ->groupBy('checksum', 'checksum_type')
                ->havingRaw('COUNT(DISTINCT object_id) > 1')
                ->limit($limit)
                ->get(['checksum', 'checksum_type']);
            foreach ($dupeChecksums as $c) {
                $doIds = DB::table('digital_object')->where('checksum', $c->checksum)->where('checksum_type', $c->checksum_type)
                    ->where('usage_id', \AhgCore\Services\DigitalObjectService::USAGE_MASTER)->orderBy('id')->pluck('id');
                foreach ($doIds as $doId) {
                    $checksumPairs += \AhgCore\Services\DigitalObjectService::flagChecksumDuplicates((int) $doId);
                }
                $duplicateGroups++;
            }
            $this->info("checksum: ".count($dupeChecksums)." identical-file groups, {$checksumPairs} new pairs flagged");

            DB::table('ahg_dedupe_scan')->where('id', $scanId)->update([
                'status' => 'completed',
                'total_records' => $totalScanned,
                'processed_records' => $totalScanned,
                'duplicates_found' => $duplicateGroups,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info("done; scanned={$totalScanned} duplicate_groups={$duplicateGroups}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::table('ahg_dedupe_scan')->where('id', $scanId)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            $this->error("scan failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
