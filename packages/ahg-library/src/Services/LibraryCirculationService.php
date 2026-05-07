<?php

/**
 * LibraryCirculationService - checkouts, returns, renewals, holds, fines
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgLibrary\Services;

use AhgLibrary\Support\LibrarySettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LibraryCirculationService
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_ON_HOLD = 'on_hold';

    /**
     * Resolve the loan period for a (material_type, patron_type) pair via
     * library_loan_rule, falling back to library_default_loan_days when no
     * matching rule exists. Wildcards: rules with patron_type='*' apply to
     * all patron types.
     */
    public function resolveLoanDays(string $materialType, string $patronType): int
    {
        $rule = DB::table('library_loan_rule')
            ->where('material_type', $materialType)
            ->whereIn('patron_type', [$patronType, '*'])
            ->where('is_loanable', 1)
            ->orderByRaw("CASE WHEN patron_type = ? THEN 0 ELSE 1 END", [$patronType])
            ->first();

        return $rule
            ? (int) $rule->loan_period_days
            : LibrarySettings::defaultLoanDays();
    }

    public function resolveMaxRenewals(string $materialType, string $patronType, int $patronCap): int
    {
        $rule = DB::table('library_loan_rule')
            ->where('material_type', $materialType)
            ->whereIn('patron_type', [$patronType, '*'])
            ->orderByRaw("CASE WHEN patron_type = ? THEN 0 ELSE 1 END", [$patronType])
            ->first();

        $ruleMax = $rule ? (int) $rule->max_renewals : LibrarySettings::maxRenewals();
        return min($ruleMax, $patronCap);
    }

    /**
     * Check out a copy to a patron. Returns the new checkout id on success
     * or null when blocked (copy unavailable / patron over-limit / patron
     * suspended / fine threshold exceeded).
     */
    public function checkout(int $copyId, int $patronId, ?int $userId = null): ?int
    {
        return DB::transaction(function () use ($copyId, $patronId, $userId) {
            $copy = DB::table('library_copy')->where('id', $copyId)->lockForUpdate()->first();
            if (!$copy || $copy->status !== self::STATUS_AVAILABLE) {
                return null;
            }

            $patron = DB::table('library_patron')->where('id', $patronId)->first();
            if (!$patron || $patron->borrowing_status !== 'active') {
                return null;
            }

            // Patron checkout cap
            $activeCount = DB::table('library_checkout')
                ->where('patron_id', $patronId)
                ->where('status', 'active')
                ->count();
            if ($activeCount >= (int) $patron->max_checkouts) {
                return null;
            }

            // Fine threshold gate: block if outstanding fines exceed setting cap
            if ($patron->total_fines_owed >= LibrarySettings::patronFineThreshold()) {
                return null;
            }

            $item = DB::table('library_item')->where('id', $copy->library_item_id)->first();
            $materialType = $item->material_type ?? 'monograph';
            $loanDays = $this->resolveLoanDays($materialType, $patron->patron_type);

            $newId = DB::table('library_checkout')->insertGetId([
                'copy_id' => $copyId,
                'patron_id' => $patronId,
                'checkout_date' => now(),
                'due_date' => date('Y-m-d', strtotime("+{$loanDays} days")),
                'renewed_count' => 0,
                'status' => 'active',
                'checked_out_by' => $userId,
                'created_at' => now(),
            ]);

            DB::table('library_copy')->where('id', $copyId)->update([
                'status' => self::STATUS_CHECKED_OUT,
                'updated_at' => now(),
            ]);

            DB::table('library_patron')->where('id', $patronId)->increment('total_checkouts');

            return (int) $newId;
        });
    }

    public function return(int $checkoutId, ?int $userId = null, ?string $condition = null, ?string $notes = null): bool
    {
        return DB::transaction(function () use ($checkoutId, $userId, $condition, $notes) {
            $checkout = DB::table('library_checkout')->where('id', $checkoutId)->lockForUpdate()->first();
            if (!$checkout || $checkout->status !== 'active') {
                return false;
            }

            // Generate fine before flipping status so calc sees due_date.
            if (LibrarySettings::autoFine()) {
                $this->generateOverdueFine($checkout);
            }

            DB::table('library_checkout')->where('id', $checkoutId)->update([
                'status' => 'returned',
                'return_date' => now(),
                'return_condition' => $condition,
                'return_notes' => $notes,
                'checked_in_by' => $userId,
                'updated_at' => now(),
            ]);

            // Promote a held copy if anyone's queued for this item.
            $copy = DB::table('library_copy')->where('id', $checkout->copy_id)->first();
            $promoted = $copy ? $this->promoteNextHold((int) $copy->library_item_id) : false;

            DB::table('library_copy')->where('id', $checkout->copy_id)->update([
                'status' => $promoted ? self::STATUS_ON_HOLD : self::STATUS_AVAILABLE,
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    public function renew(int $checkoutId): bool
    {
        return DB::transaction(function () use ($checkoutId) {
            $c = DB::table('library_checkout')->where('id', $checkoutId)->lockForUpdate()->first();
            if (!$c || $c->status !== 'active') {
                return false;
            }

            $patron = DB::table('library_patron')->where('id', $c->patron_id)->first();
            if (!$patron) {
                return false;
            }

            $copy = DB::table('library_copy')->where('id', $c->copy_id)->first();
            $item = $copy ? DB::table('library_item')->where('id', $copy->library_item_id)->first() : null;
            $materialType = $item->material_type ?? 'monograph';
            $maxRenewals = $this->resolveMaxRenewals($materialType, $patron->patron_type, (int) $patron->max_renewals);

            if ($c->renewed_count >= $maxRenewals) {
                return false;
            }

            // Block renewal if anyone else is waiting for this item.
            if ($copy) {
                $waiting = DB::table('library_hold')
                    ->where('library_item_id', $copy->library_item_id)
                    ->where('status', 'pending')
                    ->where('patron_id', '!=', $c->patron_id)
                    ->exists();
                if ($waiting) {
                    return false;
                }
            }

            $loanDays = $this->resolveLoanDays($materialType, $patron->patron_type);
            $newDue = date('Y-m-d', strtotime($c->due_date . " +{$loanDays} days"));

            DB::table('library_checkout')->where('id', $checkoutId)->update([
                'due_date' => $newDue,
                'renewed_count' => $c->renewed_count + 1,
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * Place a hold on a library_item (bibliographic record). queue_position
     * is the count of pending holds + 1, capped by library_hold_max_queue.
     */
    public function placeHold(int $itemId, int $patronId): ?int
    {
        $patron = DB::table('library_patron')->where('id', $patronId)->first();
        if (!$patron || $patron->borrowing_status !== 'active') {
            return null;
        }

        $patronHolds = DB::table('library_hold')
            ->where('patron_id', $patronId)
            ->whereIn('status', ['pending', 'ready'])
            ->count();
        if ($patronHolds >= (int) $patron->max_holds) {
            return null;
        }

        $itemHolds = DB::table('library_hold')
            ->where('library_item_id', $itemId)
            ->whereIn('status', ['pending', 'ready'])
            ->count();
        if ($itemHolds >= LibrarySettings::holdMaxQueue()) {
            return null;
        }

        $expiryDays = LibrarySettings::holdExpiryDays();
        return (int) DB::table('library_hold')->insertGetId([
            'library_item_id' => $itemId,
            'patron_id' => $patronId,
            'hold_date' => now(),
            'expiry_date' => date('Y-m-d', strtotime("+{$expiryDays} days")),
            'queue_position' => $itemHolds + 1,
            'status' => 'pending',
            'created_at' => now(),
        ]);
    }

    public function cancelHold(int $holdId, ?string $reason = null): bool
    {
        return DB::table('library_hold')->where('id', $holdId)->update([
            'status' => 'cancelled',
            'cancelled_date' => now(),
            'cancel_reason' => $reason,
            'updated_at' => now(),
        ]) > 0;
    }

    /**
     * Promote the longest-pending hold for an item to status='ready' so the
     * librarian sees there's a patron waiting. Returns true when something
     * was promoted (caller flips the copy to on_hold status).
     */
    protected function promoteNextHold(int $itemId): bool
    {
        $next = DB::table('library_hold')
            ->where('library_item_id', $itemId)
            ->where('status', 'pending')
            ->orderBy('queue_position')
            ->first();
        if (!$next) {
            return false;
        }
        DB::table('library_hold')->where('id', $next->id)->update([
            'status' => 'ready',
            'notification_sent' => 0,
            'updated_at' => now(),
        ]);
        return true;
    }

    /**
     * Auto-expire pending holds whose expiry_date is in the past. Honoured
     * by AutoExpireHoldsCommand cron when library_auto_expire_holds is on.
     */
    public function autoExpireHolds(): int
    {
        return DB::table('library_hold')
            ->where('status', 'pending')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', date('Y-m-d'))
            ->update([
                'status' => 'expired',
                'cancelled_date' => now(),
                'cancel_reason' => 'Auto-expired (hold pickup window passed)',
                'updated_at' => now(),
            ]);
    }

    /**
     * Calculate + persist an overdue fine for a checkout based on the
     * matching loan rule's fine_per_day. Idempotent for a given checkout
     * (only one outstanding overdue fine per checkout at a time).
     */
    protected function generateOverdueFine(object $checkout): void
    {
        $dueTs = strtotime($checkout->due_date);
        if ($dueTs === false || $dueTs >= time()) {
            return;
        }

        $copy = DB::table('library_copy')->where('id', $checkout->copy_id)->first();
        $item = $copy ? DB::table('library_item')->where('id', $copy->library_item_id)->first() : null;
        $patron = DB::table('library_patron')->where('id', $checkout->patron_id)->first();
        if (!$patron) {
            return;
        }

        $rule = DB::table('library_loan_rule')
            ->where('material_type', $item->material_type ?? 'monograph')
            ->whereIn('patron_type', [$patron->patron_type, '*'])
            ->orderByRaw("CASE WHEN patron_type = ? THEN 0 ELSE 1 END", [$patron->patron_type])
            ->first();
        $perDay = $rule ? (float) $rule->fine_per_day : 1.00;
        $cap = $rule ? ($rule->fine_cap !== null ? (float) $rule->fine_cap : null) : null;

        // Subtract grace period from the overdue duration. Defaults to 0 when
        // no rule covers the (material, patron) pair, which mirrors AtoM.
        $grace = $rule ? (int) $rule->grace_period_days : 0;
        $overdueDays = (int) floor((time() - $dueTs) / 86400) - $grace;
        if ($overdueDays <= 0) {
            return;
        }

        $amount = $perDay * $overdueDays;
        if ($cap !== null && $amount > $cap) {
            $amount = $cap;
        }

        $existing = DB::table('library_fine')
            ->where('checkout_id', $checkout->id)
            ->where('fine_type', 'overdue')
            ->where('status', 'outstanding')
            ->first();

        if ($existing) {
            DB::table('library_fine')->where('id', $existing->id)->update([
                'amount' => $amount,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('library_fine')->insert([
                'patron_id' => $checkout->patron_id,
                'checkout_id' => $checkout->id,
                'fine_type' => 'overdue',
                'amount' => $amount,
                'paid_amount' => 0.00,
                'currency' => LibrarySettings::currency(),
                'status' => 'outstanding',
                'description' => "Overdue: $overdueDays day(s) past due",
                'fine_date' => date('Y-m-d'),
                'created_at' => now(),
            ]);
        }

        // Refresh patron's running total.
        $total = (float) DB::table('library_fine')
            ->where('patron_id', $checkout->patron_id)
            ->where('status', 'outstanding')
            ->sum('amount');
        DB::table('library_patron')->where('id', $checkout->patron_id)->update([
            'total_fines_owed' => $total,
            'updated_at' => now(),
        ]);
    }

    /**
     * Sweep all overdue active checkouts and ensure each has a current fine
     * row. Called from CalculateFinesCommand cron.
     */
    public function calculateAllOverdueFines(): int
    {
        if (!LibrarySettings::autoFine()) {
            return 0;
        }
        $count = 0;
        DB::table('library_checkout')
            ->where('status', 'active')
            ->whereDate('due_date', '<', date('Y-m-d'))
            ->orderBy('id')
            ->chunk(200, function ($rows) use (&$count) {
                foreach ($rows as $c) {
                    $this->generateOverdueFine($c);
                    $count++;
                }
            });
        return $count;
    }

    public function listOverdue(): array
    {
        return DB::table('library_checkout as c')
            ->join('library_patron as p', 'c.patron_id', '=', 'p.id')
            ->leftJoin('library_copy as cp', 'c.copy_id', '=', 'cp.id')
            ->leftJoin('library_item as li', 'cp.library_item_id', '=', 'li.id')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('li.information_object_id', '=', 'i18n.id')
                  ->where('i18n.culture', '=', app()->getLocale());
            })
            ->where('c.status', 'active')
            ->whereDate('c.due_date', '<', date('Y-m-d'))
            ->select(
                'c.id', 'c.due_date', 'c.checkout_date',
                'p.first_name', 'p.last_name', 'p.email', 'p.card_number',
                'cp.barcode', 'li.call_number', 'i18n.title',
            )
            ->orderBy('c.due_date')
            ->get()
            ->all();
    }

    public function listCheckouts(array $filters = []): array
    {
        $q = DB::table('library_checkout as c')
            ->leftJoin('library_patron as p', 'c.patron_id', '=', 'p.id')
            ->leftJoin('library_copy as cp', 'c.copy_id', '=', 'cp.id')
            ->leftJoin('library_item as li', 'cp.library_item_id', '=', 'li.id')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('li.information_object_id', '=', 'i18n.id')
                  ->where('i18n.culture', '=', app()->getLocale());
            });

        if (!empty($filters['status'])) {
            $q->where('c.status', $filters['status']);
        } else {
            $q->where('c.status', 'active');
        }
        if (!empty($filters['patron_id'])) {
            $q->where('c.patron_id', $filters['patron_id']);
        }

        return $q->select(
            'c.id', 'c.checkout_date', 'c.due_date', 'c.status', 'c.renewed_count',
            'p.first_name', 'p.last_name', 'p.card_number',
            'cp.barcode', 'li.call_number', 'i18n.title',
        )
            ->orderByDesc('c.checkout_date')
            ->limit($filters['limit'] ?? 200)
            ->get()
            ->all();
    }

    public function getLoanRules(): array
    {
        return DB::table('library_loan_rule')
            ->orderBy('material_type')
            ->orderBy('patron_type')
            ->get()
            ->all();
    }
}
