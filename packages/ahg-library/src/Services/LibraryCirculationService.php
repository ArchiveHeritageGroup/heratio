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

use AhgLibrary\Support\LibraryBranch;
use AhgLibrary\Support\LibrarySettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LibraryCirculationService
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_ON_HOLD = 'on_hold';

    /**
     * Per-table branch_id availability, keyed by connection then table. Not a
     * flat per-process cache: this process can serve more than one database -
     * see the note in LibraryBranch::available().
     */
    private static array $branchColumns = [];

    /**
     * Find the loan rule governing a transaction. Resolution runs most
     * specific first: a rule for this exact branch beats an all-branches rule
     * (branch_id = 0), and within that a rule naming this patron type beats
     * the '*' wildcard. Returns null when nothing covers the combination and
     * the caller should fall back to the global setting.
     *
     * All three rule consumers go through here. They used to carry three
     * separate copies of this query, which is why the branch axis had to be
     * added in three places to be added at all - and how two of them came to
     * differ from the third on is_loanable.
     */
    protected function matchRule(
        string $materialType,
        string $patronType,
        ?int $branchId = null,
        bool $loanableOnly = false
    ): ?object {
        $query = DB::table('library_loan_rule')
            ->where('material_type', $materialType)
            ->whereIn('patron_type', [$patronType, '*']);

        if ($loanableOnly) {
            $query->where('is_loanable', 1);
        }

        if (LibraryBranch::available()) {
            $exact = $branchId !== null && $branchId !== LibraryBranch::ALL ? $branchId : null;

            if ($exact !== null) {
                $query->whereIn('branch_id', [$exact, LibraryBranch::ALL])
                    ->orderByRaw('CASE WHEN branch_id = ? THEN 0 ELSE 1 END', [$exact]);
            } else {
                $query->where('branch_id', LibraryBranch::ALL);
            }
        }

        return $query
            ->orderByRaw('CASE WHEN patron_type = ? THEN 0 ELSE 1 END', [$patronType])
            ->first();
    }

    /**
     * The loan rule governing a (branch, material, patron) combination, for
     * callers outside this service - #1473.
     *
     * LibraryOverdueNoticeService carried a FOURTH copy of this lookup, which
     * ignored the branch axis entirely, so an overdue notice quoted the
     * system-wide fine rate even where the lending branch had its own rule.
     * v1.154.634 consolidated three copies; this is the one that was missed.
     */
    public function ruleFor(string $materialType, string $patronType, ?int $branchId = null): ?object
    {
        return $this->matchRule($materialType, $patronType, $branchId);
    }

    /**
     * Resolve the loan period for a (branch, material_type, patron_type)
     * combination via library_loan_rule, falling back to the branch's
     * library_default_loan_days when no matching rule exists.
     */
    public function resolveLoanDays(string $materialType, string $patronType, ?int $branchId = null): int
    {
        $rule = $this->matchRule($materialType, $patronType, $branchId, true);

        return $rule
            ? (int) $rule->loan_period_days
            : LibrarySettings::defaultLoanDays($branchId);
    }

    public function resolveMaxRenewals(
        string $materialType,
        string $patronType,
        int $patronCap,
        ?int $branchId = null
    ): int {
        $rule = $this->matchRule($materialType, $patronType, $branchId);

        $ruleMax = $rule ? (int) $rule->max_renewals : LibrarySettings::maxRenewals($branchId);
        return min($ruleMax, $patronCap);
    }

    /**
     * Check out a copy to a patron. Returns the new checkout id on success
     * or null when blocked (copy unavailable / patron over-limit / patron
     * suspended / fine threshold exceeded).
     */
    public function checkout(int $copyId, int $patronId, ?int $userId = null, ?int $branchId = null): ?int
    {
        return DB::transaction(function () use ($copyId, $patronId, $userId, $branchId) {
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

            // Resolution order is the desk's explicit choice, then the copy's
            // own branch, then the patron's home branch, then the configured
            // default. $copy is already loaded and locked here, so its branch
            // is handed over directly rather than re-read inside the
            // transaction.
            $branchId = LibraryBranch::resolve($branchId ?? ($copy->branch_id ?? null), null, $patronId);
            $loanDays = $this->resolveLoanDays($materialType, $patron->patron_type, $branchId);

            $row = [
                'copy_id' => $copyId,
                'patron_id' => $patronId,
                'checkout_date' => now(),
                'due_date' => date('Y-m-d', strtotime("+{$loanDays} days")),
                'renewed_count' => 0,
                'status' => 'active',
                'checked_out_by' => $userId,
                'created_at' => now(),
            ];
            if ($this->hasBranchColumn('library_checkout')) {
                $row['branch_id'] = $branchId;
            }

            $newId = DB::table('library_checkout')->insertGetId($row);

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
            $promoted = $copy
                ? $this->promoteNextHold(
                    (int) $copy->library_item_id,
                    isset($copy->branch_id) && $copy->branch_id !== null ? (int) $copy->branch_id : null
                )
                : false;

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
            // The loan already records where it was made; fall back to the
            // copy only for loans predating the branch column. $copy can be
            // null here, so it is guarded rather than chained.
            $branchId = $c->branch_id ?? $copy?->branch_id ?? null;
            $branchId = $branchId !== null ? (int) $branchId : null;
            $maxRenewals = $this->resolveMaxRenewals(
                $materialType,
                $patron->patron_type,
                (int) $patron->max_renewals,
                $branchId
            );

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

            $loanDays = $this->resolveLoanDays($materialType, $patron->patron_type, $branchId);
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
    public function placeHold(int $itemId, int $patronId, ?int $pickupBranchId = null): ?int
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

        // The queue cap is a property of the item across the whole service: a
        // consortium should not be able to exceed it by spreading holds over
        // branches.
        $itemHolds = DB::table('library_hold')
            ->where('library_item_id', $itemId)
            ->whereIn('status', ['pending', 'ready'])
            ->count();
        if ($itemHolds >= LibrarySettings::holdMaxQueue()) {
            return null;
        }

        // Queue POSITION, by contrast, is per pickup branch - #1473. A patron
        // collecting at branch B is not behind the people waiting at branch A;
        // they are waiting for a copy that will be routed to B. Numbering them
        // globally told them they were fifth in a queue they were not in.
        $pickup = LibraryBranch::resolve($pickupBranchId, null, $patronId);
        $position = $this->hasBranchColumn('library_hold') && $pickup !== null
            ? DB::table('library_hold')
                ->where('library_item_id', $itemId)
                ->where('branch_id', $pickup)
                ->whereIn('status', ['pending', 'ready'])
                ->count() + 1
            : $itemHolds + 1;

        $expiryDays = LibrarySettings::holdExpiryDays();
        $row = [
            'library_item_id' => $itemId,
            'patron_id' => $patronId,
            'hold_date' => now(),
            'expiry_date' => date('Y-m-d', strtotime("+{$expiryDays} days")),
            'queue_position' => $position,
            'status' => 'pending',
            'created_at' => now(),
        ];

        // A hold is collected somewhere; absent an explicit pickup point, the
        // patron's home branch is the only defensible default.
        if ($this->hasBranchColumn('library_hold')) {
            $row['branch_id'] = $pickup;
        }

        return (int) DB::table('library_hold')->insertGetId($row);
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
    protected function promoteNextHold(int $itemId, ?int $branchId = null): bool
    {
        // A copy returned at branch B satisfies the queue at B first. Only when
        // nobody is waiting there does it go to the service-wide queue - which
        // is what routing a hold to a pickup point actually means.
        $query = DB::table('library_hold')
            ->where('library_item_id', $itemId)
            ->where('status', 'pending');

        if ($branchId !== null && $this->hasBranchColumn('library_hold')) {
            $atBranch = (clone $query)->where('branch_id', $branchId)
                ->orderBy('queue_position')->first();
            if ($atBranch) {
                return $this->markHoldReady((int) $atBranch->id);
            }
        }

        $next = $query->orderBy('queue_position')->first();
        if (!$next) {
            return false;
        }

        return $this->markHoldReady((int) $next->id);
    }

    /** Flip one hold to ready so the desk sees a patron is waiting. */
    protected function markHoldReady(int $holdId): bool
    {
        DB::table('library_hold')->where('id', $holdId)->update([
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

        // Fines accrue under the rules of the branch that lent the copy, not
        // the branch reading the report.
        $branchId = LibraryBranch::available()
            ? ($checkout->branch_id ?? ($copy->branch_id ?? null))
            : null;

        $rule = $this->matchRule(
            $item->material_type ?? 'monograph',
            $patron->patron_type,
            $branchId !== null ? (int) $branchId : null
        );
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
            // #74 encryption_field_financial_data: encrypt-on-write so
            // direct inserts don't have to wait for the daily bulk-apply
            // safety net. Short-circuits to plaintext when the category
            // is off (or encryption_enabled is off); the column happily
            // round-trips either form.
            $enc = new \AhgCore\Services\EncryptionService();
            $description = $enc->encrypt(
                \AhgCore\Services\EncryptionService::CATEGORY_FINANCIAL_DATA,
                "Overdue: $overdueDays day(s) past due",
                'library_fine',
                'description',
                null
            );
            DB::table('library_fine')->insert([
                'patron_id' => $checkout->patron_id,
                'checkout_id' => $checkout->id,
                'fine_type' => 'overdue',
                'amount' => $amount,
                'paid_amount' => 0.00,
                'currency' => LibrarySettings::currency(),
                'status' => 'outstanding',
                'description' => $description,
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

    /**
     * Overdue loans, optionally only those made at one branch. Null means
     * every branch - the consortium view, and also the right answer for a
     * single-outlet service, so it must not be treated as "no access".
     */
    public function listOverdue(?int $branchId = null): array
    {
        $q = DB::table('library_checkout as c')
            ->join('library_patron as p', 'c.patron_id', '=', 'p.id')
            ->leftJoin('library_copy as cp', 'c.copy_id', '=', 'cp.id')
            ->leftJoin('library_item as li', 'cp.library_item_id', '=', 'li.id')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('li.information_object_id', '=', 'i18n.id')
                  ->where('i18n.culture', '=', app()->getLocale());
            })
            ->where('c.status', 'active')
            ->whereDate('c.due_date', '<', date('Y-m-d'));

        $this->scopeToBranch($q, $branchId);

        $rows = $q->select(
            'c.id', 'c.due_date', 'c.checkout_date', 'c.patron_id',
            'p.first_name', 'p.last_name', 'p.email', 'p.card_number',
            'cp.barcode', 'li.call_number', 'i18n.title',
        )
            ->orderBy('c.due_date')
            ->get()
            ->all();

        if (empty($rows)) {
            return [];
        }

        // The overdue view binds patron_name, days_overdue and fine_amount, none
        // of which this query ever selected - so it showed a blank patron, 0 days
        // and 0.00 for every row. Same defect shape as #1477 on the loan-rules
        // screen: a plausible-looking table contradicting the real data.
        //
        // Derived here rather than in SQL because the library package's tests run
        // on SQLite, where DATEDIFF and CONCAT are not available.
        $fines = DB::table('library_fine')
            ->whereIn('checkout_id', array_map(static fn ($r) => $r->id, $rows))
            ->where('fine_type', 'overdue')
            ->where('status', 'outstanding')
            ->pluck('amount', 'checkout_id');

        $today = strtotime(date('Y-m-d'));

        foreach ($rows as $row) {
            $name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
            $row->patron_name = $name !== '' ? $name : ($row->card_number ?? '');

            $due = strtotime((string) $row->due_date);
            $row->days_overdue = $due === false ? 0 : max(0, (int) floor(($today - $due) / 86400));

            // Null rather than 0 where no fine row exists yet: a fine accrues on
            // return or on the nightly run, so "not yet calculated" is a real
            // state and is not the same as "nothing owed".
            $row->fine_amount = isset($fines[$row->id]) ? (float) $fines[$row->id] : null;
        }

        return $rows;
    }

    /**
     * Restrict a checkout query to one branch. A loan made before the branch
     * axis existed has branch_id NULL; it is matched by falling back to the
     * branch of the copy, so history does not vanish from a branch's list the
     * moment scoping is switched on.
     */
    protected function scopeToBranch($query, ?int $branchId): void
    {
        if ($branchId === null || ! $this->hasBranchColumn('library_checkout')) {
            return;
        }

        $copyScoped = $this->hasBranchColumn('library_copy');

        $query->where(function ($w) use ($branchId, $copyScoped) {
            $w->where('c.branch_id', $branchId);
            if ($copyScoped) {
                $w->orWhere(function ($legacy) use ($branchId) {
                    $legacy->whereNull('c.branch_id')->where('cp.branch_id', $branchId);
                });
            }
        });
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
        // array_key_exists, not !empty: a branch_id of null is a meaningful
        // "every branch" and must not be confused with "filter not supplied".
        if (array_key_exists('branch_id', $filters)) {
            $this->scopeToBranch($q, $filters['branch_id'] !== null ? (int) $filters['branch_id'] : null);
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

    /**
     * Whether a given circulation table can store a branch yet. Checked per
     * table rather than once, because an instance can be mid-deploy: new code
     * is serving requests before its migration has run.
     */
    /** Test seam - schema changes within a process are otherwise invisible. */
    public static function forgetSchemaCache(): void
    {
        self::$branchColumns = [];
        LibraryBranch::forgetSchemaCache();
    }

    protected function hasBranchColumn(string $table): bool
    {
        $key = LibraryBranch::connectionKey();

        if (! isset(self::$branchColumns[$key][$table])) {
            self::$branchColumns[$key][$table] = Schema::hasTable($table)
                && Schema::hasColumn($table, 'branch_id');
        }

        return self::$branchColumns[$key][$table];
    }

    /**
     * Create or update one loan rule - #1473.
     *
     * There has never been a create, update or delete for library_loan_rule
     * anywhere in the codebase: the only surface was a read-only list, so rules
     * could be changed by hand in SQL or not at all. That is a poor position for
     * a table that decides loan periods and fine rates.
     *
     * Keyed on (branch_id, material_type, patron_type), which is the table's own
     * unique constraint - so saving the same combination twice edits the rule
     * rather than failing on a duplicate key.
     */
    public function saveLoanRule(array $data): int
    {
        $branchId = isset($data['branch_id']) && (int) $data['branch_id'] > 0
            ? (int) $data['branch_id']
            : LibraryBranch::ALL;

        $key = [
            'material_type' => (string) $data['material_type'],
            'patron_type'   => (string) ($data['patron_type'] ?? '*'),
        ];
        if (LibraryBranch::available()) {
            $key['branch_id'] = $branchId;
        }

        $values = [
            'loan_period_days'    => (int) ($data['loan_period_days'] ?? 14),
            'renewal_period_days' => (int) ($data['renewal_period_days'] ?? ($data['loan_period_days'] ?? 14)),
            'max_renewals'        => (int) ($data['max_renewals'] ?? 2),
            'fine_per_day'        => (float) ($data['fine_per_day'] ?? 0),
            'fine_cap'            => ($data['fine_cap'] ?? null) !== null && $data['fine_cap'] !== ''
                ? (float) $data['fine_cap'] : null,
            'grace_period_days'   => (int) ($data['grace_period_days'] ?? 0),
            'is_loanable'         => ! empty($data['is_loanable']) ? 1 : 0,
            'notes'               => ($data['notes'] ?? null) ?: null,
        ];

        DB::table('library_loan_rule')->updateOrInsert($key, $values);

        return (int) DB::table('library_loan_rule')->where($key)->value('id');
    }

    /** Delete one loan rule. */
    public function deleteLoanRule(int $id): bool
    {
        return DB::table('library_loan_rule')->where('id', $id)->delete() > 0;
    }

    public function getLoanRules(): array
    {
        $query = DB::table('library_loan_rule');

        if (LibraryBranch::available()) {
            // All-branches rules first, then each branch's overrides, so the
            // screen reads as "the default, and what departs from it".
            $query->orderBy('branch_id');
        }

        return $query
            ->orderBy('material_type')
            ->orderBy('patron_type')
            ->get()
            ->all();
    }
}
