<?php

/**
 * LibraryProcessFinesCommand — accrue / record overdue fines per loan_rule.
 *
 * For every overdue checkout (status=overdue, return_date NULL) compute
 * elapsed days × loan_rule.fine_per_day capped at fine_cap, then upsert
 * one outstanding library_fine row per checkout.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibraryProcessFinesCommand extends Command
{
    protected $signature = 'ahg:library-process-fines
        {--dry-run : Calculate fines without applying}';

    protected $description = 'Calculate overdue fines per loan_rule';

    public function handle(): int
    {
        if (! Schema::hasTable('library_checkout') || ! Schema::hasTable('library_fine')) {
            $this->warn('library_checkout or library_fine missing'); return self::SUCCESS;
        }
        $dry = (bool) $this->option('dry-run');
        $today = Carbon::today();

        $rules = DB::table('library_loan_rule')->get()->groupBy('material_type');
        $rows = DB::table('library_checkout as c')
            ->leftJoin('library_copy as cp', 'cp.id', '=', 'c.copy_id')
            ->leftJoin('library_item as li', 'li.id', '=', 'cp.library_item_id')
            ->where('c.status', 'overdue')
            ->whereNull('c.return_date')
            ->select('c.id', 'c.patron_id', 'c.due_date', 'li.material_type')
            ->get();

        $accrued = 0; $totalAmount = 0.0;
        foreach ($rows as $r) {
            $rule = ($rules[$r->material_type] ?? collect())->first()
                 ?? ($rules['*'] ?? collect())->first();
            $rate = (float) ($rule->fine_per_day ?? 1.0);
            $cap  = $rule->fine_cap ? (float) $rule->fine_cap : null;
            $grace = (int) ($rule->grace_period_days ?? 0);

            $due = Carbon::parse($r->due_date)->addDays($grace);
            if ($today->lessThanOrEqualTo($due)) continue;
            $days = $today->diffInDays($due);
            $amount = round($days * $rate, 2);
            if ($cap !== null) $amount = min($amount, $cap);
            if ($amount <= 0) continue;

            $totalAmount += $amount;
            if ($dry) { $accrued++; continue; }

            $existing = DB::table('library_fine')
                ->where('checkout_id', $r->id)
                ->where('fine_type', 'overdue')
                ->where('status', 'outstanding')
                ->first();
            if ($existing) {
                if ((float) $existing->amount < $amount) {
                    DB::table('library_fine')->where('id', $existing->id)->update([
                        'amount' => $amount, 'updated_at' => now(),
                    ]);
                }
            } else {
                // #74 encryption_field_financial_data: encrypt-on-write so
                // command-driven inserts pass through the same gate the
                // bulk-apply safety net uses. No-op when the category is off.
                $enc = new \AhgCore\Services\EncryptionService();
                $description = $enc->encrypt(
                    \AhgCore\Services\EncryptionService::CATEGORY_FINANCIAL_DATA,
                    "auto-accrued {$days}d × {$rate}",
                    'library_fine',
                    'description',
                    null
                );
                DB::table('library_fine')->insert([
                    'patron_id' => $r->patron_id,
                    'checkout_id' => $r->id,
                    'fine_type' => 'overdue',
                    'amount' => $amount,
                    'currency' => 'ZAR',
                    'status' => 'outstanding',
                    'fine_date' => $today,
                    'description' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('library_patron')->where('id', $r->patron_id)
                    ->increment('total_fines_owed', $amount);
            }
            $accrued++;
        }
        $this->info(sprintf('accrued=%d total=%s%s', $accrued, number_format($totalAmount, 2), $dry ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }
}
