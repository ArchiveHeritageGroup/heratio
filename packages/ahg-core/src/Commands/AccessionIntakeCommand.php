<?php

/**
 * AccessionIntakeCommand — manage accession intake queue from CLI.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccessionIntakeCommand extends Command
{
    protected $signature = 'ahg:accession-intake
        {--stats : Show intake statistics}
        {--queue : Show intake queue}
        {--status= : Filter by status}
        {--priority= : Filter by priority}
        {--assign= : Assign accession to user}
        {--user= : User ID for assignment}
        {--accept= : Accept accession by ID}
        {--reject= : Reject accession by ID}
        {--reason= : Reason for accept/reject}
        {--checklist= : Run checklist for accession ID}
        {--timeline= : Show timeline for accession ID}';

    protected $description = 'Manage accession intake queue';

    public function handle(): int
    {
        if (! Schema::hasTable('accession')) {
            $this->warn('accession table not found.');
            return self::SUCCESS;
        }

        if ($id = $this->option('accept'))   return $this->setStatus((int) $id, 'accepted', (string) $this->option('reason'));
        if ($id = $this->option('reject'))   return $this->setStatus((int) $id, 'rejected', (string) $this->option('reason'));
        if ($id = $this->option('assign'))   return $this->assign((int) $id, (int) $this->option('user'));
        if ($id = $this->option('checklist'))return $this->runChecklist((int) $id);
        if ($id = $this->option('timeline')) return $this->showTimeline((int) $id);
        if ($this->option('stats'))          return $this->showStats();
        return $this->showQueue();
    }

    protected function showStats(): int
    {
        $byStatus = DB::table('accession')
            ->leftJoin('term as t', 't.id', '=', 'accession.processing_status_id')
            ->selectRaw('COALESCE(t.code, "unset") as label, COUNT(*) AS n')
            ->groupBy('label')->pluck('n', 'label')->toArray();
        $this->info('=== accession intake stats ===');
        foreach ($byStatus as $s => $n) $this->line(sprintf('  %-20s %d', $s, $n));
        return self::SUCCESS;
    }

    protected function showQueue(): int
    {
        $q = DB::table('accession')
            ->leftJoin('accession_i18n as ai', function ($j) {
                $j->on('ai.id', '=', 'accession.id')->where('ai.culture', '=', 'en');
            })
            ->leftJoin('term as st', 'st.id', '=', 'accession.processing_status_id')
            ->leftJoin('term as pt', 'pt.id', '=', 'accession.processing_priority_id')
            ->select('accession.id', 'accession.identifier', 'ai.title', 'st.code as status', 'pt.code as priority', 'accession.date');
        if ($s = $this->option('status'))   $q->where('st.code', $s);
        if ($p = $this->option('priority')) $q->where('pt.code', $p);
        $rows = $q->orderByDesc('accession.id')->limit(50)->get();
        $this->info("=== intake queue ({$rows->count()}) ===");
        foreach ($rows as $r) {
            $this->line(sprintf('  #%-6d %-15s %-20s %-12s %s',
                $r->id, $r->identifier ?? '-',
                mb_strimwidth((string) ($r->status ?? '-'), 0, 18, '..'),
                mb_strimwidth((string) ($r->priority ?? '-'), 0, 11, '..'),
                mb_strimwidth((string) ($r->title ?? ''), 0, 60, '..')));
        }
        return self::SUCCESS;
    }

    protected function setStatus(int $id, string $code, string $reason): int
    {
        $statusTermId = DB::table('term')
            ->where('taxonomy_id', function ($q) {
                $q->select('id')->from('taxonomy')->whereIn('id', [function ($q2) {
                    $q2->select('taxonomy_id')->from('term')->where('code', 'accession_processing_status')->limit(1);
                }]);
            })
            ->where('code', $code)
            ->value('id');
        if (! $statusTermId) {
            $this->warn("status '{$code}' not in dropdown — recording without term link");
        }
        DB::table('accession')->where('id', $id)->update([
            'processing_status_id' => $statusTermId,
            'updated_at' => now(),
        ]);
        if (Schema::hasTable('accession_timeline')) {
            DB::table('accession_timeline')->insert([
                'accession_id' => $id,
                'event_type' => $code,
                'notes' => $reason ?: null,
                'occurred_at' => now(),
                'created_at' => now(),
            ]);
        }
        $this->info("accession #{$id}: status -> {$code}");
        return self::SUCCESS;
    }

    protected function assign(int $id, int $userId): int
    {
        if ($userId <= 0) { $this->error('--user is required for --assign'); return self::FAILURE; }
        if (! Schema::hasColumn('accession', 'assigned_user_id')) {
            $this->warn('accession.assigned_user_id column not present.');
            return self::SUCCESS;
        }
        DB::table('accession')->where('id', $id)->update(['assigned_user_id' => $userId, 'updated_at' => now()]);
        $this->info("accession #{$id} assigned to user {$userId}");
        return self::SUCCESS;
    }

    protected function runChecklist(int $id): int
    {
        if (! Schema::hasTable('accession_intake_checklist')) {
            $this->warn('accession_intake_checklist not found.');
            return self::SUCCESS;
        }
        $items = DB::table('accession_intake_checklist')->where('accession_id', $id)->get();
        $this->info("=== checklist for #{$id} ({$items->count()}) ===");
        foreach ($items as $i) {
            $mark = ! empty($i->is_complete) ? '[x]' : '[ ]';
            $this->line(sprintf('  %s %s', $mark, $i->item_text ?? ($i->item_key ?? '?')));
        }
        return self::SUCCESS;
    }

    protected function showTimeline(int $id): int
    {
        if (! Schema::hasTable('accession_timeline')) {
            $this->warn('accession_timeline not found.');
            return self::SUCCESS;
        }
        $rows = DB::table('accession_timeline')->where('accession_id', $id)->orderBy('occurred_at')->get();
        $this->info("=== timeline #{$id} ({$rows->count()}) ===");
        foreach ($rows as $r) {
            $this->line(sprintf('  %s  %-15s  %s', $r->occurred_at, $r->event_type, mb_strimwidth((string) ($r->notes ?? ''), 0, 60, '..')));
        }
        return self::SUCCESS;
    }
}
