<?php

/**
 * LibraryCashDeskService - cash-desk sessions: float, takings, count,
 * second-person reconciliation and the end-of-day summary (#1474).
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use AhgLibrary\Support\LibrarySettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibraryCashDeskService
{
    public const OPEN = 'open';
    public const CLOSED = 'closed';
    public const RECONCILED = 'reconciled';

    /** Unique while a session is open, so one operator cannot hold two drawers. */
    public static function openKey(int $userId): string
    {
        return 'user:' . $userId;
    }

    /** A branch-bound operator sees only their branch; null means every branch. */
    public static function canAccess(object $session, ?int $operatorBranch): bool
    {
        return $operatorBranch === null || (int) $session->branch_id === $operatorBranch;
    }

    /** Whether the operator is working at the exact branch the session was opened in. */
    public static function atSessionBranch(object $session, ?int $operatorBranch): bool
    {
        return ($session->branch_id === null ? null : (int) $session->branch_id) === $operatorBranch;
    }

    /** The open session that would be stranded if the operator moved to $target. */
    public function blockingBranchChange(int $userId, ?int $target): ?object
    {
        if (! Schema::hasTable('library_cash_session')) {
            return null;
        }
        $session = $this->current($userId);

        return $session && ! self::atSessionBranch($session, $target) ? $session : null;
    }

    /**
     * Currencies a session may be opened in: the configured one, plus any that
     * outstanding fines are actually recorded in, so those can be paid as they
     * stand instead of being relabelled.
     */
    public function openableCurrencies(): array
    {
        $held = DB::table('library_fine')->where('status', LibraryFineService::OUTSTANDING)
            ->whereColumn('amount', '>', 'paid_amount')
            ->distinct()->orderBy('currency')->pluck('currency')->all();

        return array_values(array_unique(array_merge([LibrarySettings::currency()], $held)));
    }

    public function current(int $userId): ?object
    {
        return DB::table('library_cash_session')->where('open_key', self::openKey($userId))->first();
    }

    public function find(int $id): ?object
    {
        return DB::table('library_cash_session')->where('id', $id)->first();
    }

    public function recent(?int $branchId, int $limit = 30): array
    {
        return DB::table('library_cash_session')
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('opened_at')->orderByDesc('id')->limit($limit)->get()->all();
    }

    public function open(int $userId, float $float, ?int $branchId, ?string $currency = null): int
    {
        if ($float < 0) {
            throw new LibraryFineException(__('The float cannot be negative.'));
        }
        $currency = $currency ?: LibrarySettings::currency();
        if (! in_array($currency, $this->openableCurrencies(), true)) {
            throw new LibraryFineException(__('Choose a valid currency for this session.'));
        }
        if ($this->current($userId)) {
            throw new LibraryFineException(__('You already have an open cash session.'));
        }

        try {
            return (int) DB::table('library_cash_session')->insertGetId([
                'branch_id' => $branchId,
                'currency' => $currency,
                'status' => self::OPEN,
                'open_key' => self::openKey($userId),
                'opening_float' => number_format($float, 2, '.', ''),
                'opened_by' => $userId,
                'opened_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            throw new LibraryFineException(__('You already have an open cash session.'));
        }
    }

    /** Takings for a session, in cents, split by tender. */
    public function takings(int $sessionId): array
    {
        $rows = DB::table('library_fine_receipt')->where('session_id', $sessionId)
            ->selectRaw('payment_method, is_cash, COUNT(*) as n, SUM(total_amount) as total')
            ->groupBy('payment_method', 'is_cash')->get();

        $cash = 0;
        $other = 0;
        foreach ($rows as $r) {
            $c = LibraryFineService::cents($r->total);
            $r->cents = $c;
            if ((int) $r->is_cash === 1) {
                $cash += $c;
            } else {
                $other += $c;
            }
        }

        return ['by_method' => $rows->all(), 'cash' => $cash, 'other' => $other];
    }

    /** Count the drawer. Any difference from expected must be explained. */
    public function close(int $sessionId, float $counted, ?string $notes, int $userId, bool $supervisor): object
    {
        if ($counted < 0) {
            throw new LibraryFineException(__('The counted cash cannot be negative.'));
        }
        $notes = trim((string) $notes) ?: null;

        return DB::transaction(function () use ($sessionId, $counted, $notes, $userId, $supervisor) {
            $s = DB::table('library_cash_session')->where('id', $sessionId)->lockForUpdate()->first();
            if (! $s || $s->status !== self::OPEN) {
                throw new LibraryFineException(__('This session is not open.'));
            }
            if ((int) $s->opened_by !== $userId && ! $supervisor) {
                throw new LibraryFineException(__('Only the operator who opened this session or a supervisor can close it.'));
            }

            $t = $this->takings($sessionId);
            $expected = LibraryFineService::cents($s->opening_float) + $t['cash'];
            $countedCents = LibraryFineService::cents($counted);
            $variance = $countedCents - $expected;
            if ($variance !== 0 && $notes === null) {
                throw new LibraryFineException(__('The count differs from the expected cash by :v. Add a note explaining it.', ['v' => number_format($variance / 100, 2, '.', '')]));
            }

            DB::table('library_cash_session')->where('id', $sessionId)->update([
                'status' => self::CLOSED,
                'open_key' => null,
                'cash_takings' => $t['cash'] / 100,
                'other_takings' => $t['other'] / 100,
                'expected_cash' => $expected / 100,
                'counted_cash' => $countedCents / 100,
                'variance' => $variance / 100,
                'close_notes' => $notes,
                'closed_by' => $userId,
                'closed_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->find($sessionId);
        });
    }

    /** Second-person sign-off: whoever counted the drawer cannot approve it. */
    public function reconcile(int $sessionId, ?string $notes, int $userId): void
    {
        DB::transaction(function () use ($sessionId, $notes, $userId) {
            $s = DB::table('library_cash_session')->where('id', $sessionId)->lockForUpdate()->first();
            if (! $s || $s->status !== self::CLOSED) {
                throw new LibraryFineException(__('Only a closed session can be reconciled.'));
            }
            if ((int) $s->closed_by === $userId || (int) $s->opened_by === $userId) {
                throw new LibraryFineException(__('A session must be reconciled by someone who did not run or count it.'));
            }
            $notes = trim((string) $notes) ?: null;
            if ((float) $s->variance != 0.0 && $notes === null) {
                throw new LibraryFineException(__('This session has a variance. Add a review note.'));
            }

            DB::table('library_cash_session')->where('id', $sessionId)->update([
                'status' => self::RECONCILED,
                'reconciled_by' => $userId,
                'reconciled_at' => now(),
                'reconcile_notes' => $notes,
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * One day's money, by the moment it moved: receipts by issue time, variance
     * by when the drawer was counted, waivers by when they were made. Totals are
     * per currency, because amounts in different currencies cannot be added.
     */
    public function endOfDay(string $date, ?int $branchId): array
    {
        $from = Carbon::parse($date)->startOfDay();
        $to = (clone $from)->addDay();
        $inBranch = fn ($q) => $q->when($branchId !== null, fn ($b) => $b->where('branch_id', $branchId));

        $sessions = $inBranch(DB::table('library_cash_session'))
            ->where('opened_at', '<', $to)
            ->where(fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>=', $from))
            ->orderBy('opened_at')->get();

        $receipts = DB::table('library_fine_receipt as r')
            ->join('library_cash_session as s', 's.id', '=', 'r.session_id')
            ->where('r.issued_at', '>=', $from)->where('r.issued_at', '<', $to)
            ->when($branchId !== null, fn ($q) => $q->where('s.branch_id', $branchId))
            ->selectRaw('r.session_id, r.currency, r.payment_method, r.is_cash, SUM(r.total_amount) as total')
            ->groupBy('r.session_id', 'r.currency', 'r.payment_method', 'r.is_cash')->get();

        $blank = ['cash' => 0, 'other' => 0, 'methods' => [], 'variance' => 0, 'waived' => 0];
        $totals = [];
        $perSession = [];
        foreach ($receipts as $r) {
            $c = LibraryFineService::cents($r->total);
            $side = (int) $r->is_cash === 1 ? 'cash' : 'other';
            $totals[$r->currency] ??= $blank;
            $totals[$r->currency][$side] += $c;
            $totals[$r->currency]['methods'][$r->payment_method] = ($totals[$r->currency]['methods'][$r->payment_method] ?? 0) + $c;
            $perSession[$r->session_id] ??= ['cash' => 0, 'other' => 0];
            $perSession[$r->session_id][$side] += $c;
        }

        foreach ($sessions as $s) {
            $s->takings = $perSession[$s->id] ?? ['cash' => 0, 'other' => 0];
            if ($s->closed_at !== null && Carbon::parse($s->closed_at)->lt($to)) {
                $totals[$s->currency] ??= $blank;
                $totals[$s->currency]['variance'] += LibraryFineService::cents($s->variance ?? 0);
            }
        }

        $waivers = $inBranch(DB::table('library_fine_waiver'))
            ->where('waived_at', '>=', $from)->where('waived_at', '<', $to)
            ->orderBy('waived_at')->get();
        foreach ($waivers as $w) {
            $totals[$w->currency] ??= $blank;
            $totals[$w->currency]['waived'] += LibraryFineService::cents($w->amount);
        }
        ksort($totals);

        // Anything not finished by now that began on or before this day.
        $unfinished = $inBranch(DB::table('library_cash_session'))
            ->whereIn('status', [self::OPEN, self::CLOSED])
            ->where('opened_at', '<', $to)->count();

        return [
            'sessions' => $sessions,
            'totals' => $totals,
            'waivers' => $waivers,
            'unfinished' => $unfinished,
        ];
    }
}
