<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use AhgIntegrity\Services\RetentionService;
use Carbon\Carbon;

class IntegrityRetentionCommand extends Command
{
    protected $signature = 'ahg:integrity-retention
        {--scan-eligible : Scan for eligible records}
        {--policy-id= : Apply specific policy}
        {--list : List retention policies}
        {--status : Show retention status}
        {--process-queue : Process disposition queue}
        {--hold= : Place legal hold on object ID}
        {--release= : Release legal hold on object ID}
        {--reason= : Reason for hold/release}';

    protected $description = 'Retention scan, disposition, legal holds';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listPolicies();
        }

        if ($this->option('status')) {
            return $this->showStatus();
        }

        if ($this->option('scan-eligible')) {
            return $this->scanEligible();
        }

        if ($this->option('process-queue')) {
            return $this->processQueue();
        }

        if ($this->option('hold')) {
            return $this->placeHold();
        }

        if ($this->option('release')) {
            return $this->releaseHold();
        }

        $this->info('Use --list, --status, --scan-eligible, --process-queue, --hold=N, or --release=N');
        return 0;
    }

    private function listPolicies(): int
    {
        if (!Schema::hasTable('integrity_retention_policy')) {
            $this->error('Table integrity_retention_policy does not exist.');
            return 1;
        }

        $policies = DB::table('integrity_retention_policy')->orderBy('name')->get();

        if ($policies->isEmpty()) {
            $this->info('No retention policies found.');
            return 0;
        }

        $rows = [];
        foreach ($policies as $p) {
            $scope = 'Global';
            if ($p->repository_id) {
                $scope = 'Repository #' . $p->repository_id;
            } elseif ($p->information_object_id) {
                $scope = 'IO #' . $p->information_object_id;
            }

            $rows[] = [
                $p->id,
                $p->name,
                $p->retention_period_days . ' days',
                $p->trigger_type,
                $scope,
                $p->is_enabled ? 'Yes' : 'No',
            ];
        }

        $this->table(['ID', 'Name', 'Retention Period', 'Trigger Type', 'Scope', 'Enabled'], $rows);
        $this->info('Total: ' . count($rows) . ' policies');
        return 0;
    }

    private function showStatus(): int
    {
        $this->info('=== Retention Status ===');

        if (!Schema::hasTable('integrity_retention_policy')) {
            $this->error('Table integrity_retention_policy does not exist.');
            return 1;
        }

        $policies = DB::table('integrity_retention_policy')->where('is_enabled', 1)->get();
        $this->info('Active policies: ' . $policies->count());

        if (Schema::hasTable('integrity_disposition_queue')) {
            $queueTotal = DB::table('integrity_disposition_queue')->count();
            $eligible = DB::table('integrity_disposition_queue')->where('status', 'eligible')->count();
            $ready = DB::table('integrity_disposition_queue')->where('status', 'ready')->count();
            $disposed = DB::table('integrity_disposition_queue')->where('status', 'disposed')->count();

            $this->info('Disposition queue total: ' . $queueTotal);
            $this->info('  Eligible: ' . $eligible);
            $this->info('  Ready: ' . $ready);
            $this->info('  Disposed: ' . $disposed);

            // Per-policy breakdown
            foreach ($policies as $p) {
                $pEligible = DB::table('integrity_disposition_queue')
                    ->where('policy_id', $p->id)->where('status', 'eligible')->count();
                $pReady = DB::table('integrity_disposition_queue')
                    ->where('policy_id', $p->id)->where('status', 'ready')->count();
                $pDisposed = DB::table('integrity_disposition_queue')
                    ->where('policy_id', $p->id)->where('status', 'disposed')->count();
                $this->info("  Policy \"{$p->name}\": eligible={$pEligible}, ready={$pReady}, disposed={$pDisposed}");
            }
        } else {
            $this->warn('Table integrity_disposition_queue does not exist.');
        }

        if (Schema::hasTable('integrity_legal_hold')) {
            $activeHolds = DB::table('integrity_legal_hold')->where('status', 'active')->count();
            $this->info('Active legal holds: ' . $activeHolds);
        }

        return 0;
    }

    private function scanEligible(): int
    {
        $this->info('Scanning for eligible records...');

        $policyId = $this->option('policy-id') ? (int) $this->option('policy-id') : null;

        try {
            $service = app(RetentionService::class);
            $count = $service->scanEligible($policyId);
            $this->info("Scan complete. {$count} new records added to disposition queue.");
        } catch (\Throwable $e) {
            $this->error('Scan failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function processQueue(): int
    {
        if (!Schema::hasTable('integrity_disposition_queue') || !Schema::hasTable('integrity_legal_hold')) {
            $this->error('Required tables do not exist.');
            return 1;
        }

        $this->info('Processing disposition queue...');

        $eligibleItems = DB::table('integrity_disposition_queue')
            ->where('status', 'eligible')
            ->get();

        $movedCount = 0;
        $heldCount = 0;

        foreach ($eligibleItems as $item) {
            // Check for active legal holds
            $hasHold = DB::table('integrity_legal_hold')
                ->where('information_object_id', $item->information_object_id)
                ->where('status', 'active')
                ->exists();

            if ($hasHold) {
                $heldCount++;
                continue;
            }

            DB::table('integrity_disposition_queue')
                ->where('id', $item->id)
                ->update([
                    'status' => 'ready',
                    'updated_at' => Carbon::now(),
                ]);
            $movedCount++;
        }

        $this->info("Processed {$eligibleItems->count()} items: {$movedCount} moved to ready, {$heldCount} skipped (held).");
        return 0;
    }

    private function placeHold(): int
    {
        $ioId = (int) $this->option('hold');
        $reason = $this->option('reason') ?? 'CLI hold';

        if (!Schema::hasTable('integrity_legal_hold')) {
            $this->error('Table integrity_legal_hold does not exist.');
            return 1;
        }

        // Check if already held
        $existing = DB::table('integrity_legal_hold')
            ->where('information_object_id', $ioId)
            ->where('status', 'active')
            ->exists();

        if ($existing) {
            $this->warn("IO #{$ioId} is already under an active legal hold.");
            return 1;
        }

        DB::table('integrity_legal_hold')->insert([
            'information_object_id' => $ioId,
            'reason' => $reason,
            'placed_by' => 0,
            'placed_at' => Carbon::now(),
            'status' => 'active',
            'created_at' => Carbon::now(),
        ]);

        $this->info("Legal hold placed on IO #{$ioId}. Reason: {$reason}");
        return 0;
    }

    private function releaseHold(): int
    {
        $ioId = (int) $this->option('release');

        if (!Schema::hasTable('integrity_legal_hold')) {
            $this->error('Table integrity_legal_hold does not exist.');
            return 1;
        }

        $affected = DB::table('integrity_legal_hold')
            ->where('information_object_id', $ioId)
            ->where('status', 'active')
            ->update([
                'status' => 'released',
                'released_by' => 0,
                'released_at' => Carbon::now(),
            ]);

        if ($affected > 0) {
            $this->info("Legal hold released on IO #{$ioId}. {$affected} hold(s) released.");
        } else {
            $this->warn("No active legal hold found for IO #{$ioId}.");
        }

        return 0;
    }
}
