<?php

/**
 * LibraryFineService - staff payment and waiver of library fines (#1474).
 *
 * A part-paid fine deliberately stays 'outstanding': both accrual paths find
 * the row to top up by that status, so a new status would make them raise a
 * second fine for the same loan.
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use AhgCore\Services\EncryptionService;
use AhgCore\Support\DropdownOptions;
use AhgLibrary\Support\LibraryBranch;
use Illuminate\Support\Facades\DB;

class LibraryFineService
{
    public const OUTSTANDING = 'outstanding';
    public const PAID = 'paid';
    public const WAIVED = 'waived';

    /** True when the last pay() returned an earlier receipt and took no money. */
    public bool $replayed = false;

    /** Money is compared in whole cents, never as floats. */
    public static function cents($value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private static function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * What may still be charged for a loan once earlier settled fines are
     * counted. Without this, paying a fine on a book still out makes the next
     * accrual run raise a new fine for the whole overdue period again.
     */
    public static function chargeableOverdue(int $checkoutId, float $accrued): float
    {
        $settled = (string) DB::table('library_fine')
            ->where('checkout_id', $checkoutId)
            ->where('fine_type', 'overdue')
            ->whereIn('status', [self::PAID, self::WAIVED])
            ->sum('amount');

        return max(0, self::cents($accrued) - self::cents($settled)) / 100;
    }

    /** Recompute both patron running totals from the fine rows. */
    public static function refreshPatronTotals(int $patronId): void
    {
        $owed = DB::table('library_fine')->where('patron_id', $patronId)
            ->where('status', self::OUTSTANDING)
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as t')->value('t');
        $paid = DB::table('library_fine')->where('patron_id', $patronId)->sum('paid_amount');

        DB::table('library_patron')->where('id', $patronId)->update([
            'total_fines_owed' => self::money(max(0, self::cents($owed))),
            'total_fines_paid' => self::money(self::cents($paid)),
            'updated_at' => now(),
        ]);
    }

    /** Stored descriptions may be encrypted; a bad key must not break the page. */
    public static function description(?string $stored): string
    {
        try {
            return (string) (new EncryptionService())->decrypt(
                EncryptionService::CATEGORY_FINANCIAL_DATA, $stored, 'library_fine', 'description'
            );
        } catch (\Throwable) {
            return '';
        }
    }

    public function outstanding(int $patronId): array
    {
        return DB::table('library_fine')
            ->where('patron_id', $patronId)
            ->where('status', self::OUTSTANDING)
            ->whereColumn('amount', '>', 'paid_amount')
            ->orderBy('fine_date')->orderBy('id')
            ->get()
            ->each(function ($f) {
                $f->balance = (self::cents($f->amount) - self::cents($f->paid_amount)) / 100;
                $f->description = self::description($f->description);
            })
            ->all();
    }

    /**
     * Active tenders from the Dropdown Manager. is_cash is null when nobody has
     * said whether the tender goes in the drawer; such a tender cannot be used,
     * because guessing either way misstates the expected cash.
     */
    public function tenders(): array
    {
        $labels = DropdownOptions::resolve('library_payment_method', []);
        if ($labels === []) {
            return [];
        }
        $meta = DB::table('ahg_dropdown')->where('taxonomy', 'library_payment_method')
            ->whereIn('code', array_keys($labels))->pluck('metadata', 'code');

        $out = [];
        foreach ($labels as $code => $label) {
            $m = json_decode((string) ($meta[$code] ?? ''), true);
            $isCash = is_array($m) && is_bool($m['is_cash'] ?? null) ? $m['is_cash'] : null;
            $out[$code] = ['label' => $label, 'is_cash' => $isCash];
        }

        return $out;
    }

    /**
     * Whether a payment reference holds a card number: 13 to 19 digits passing
     * the Luhn check, in one run or across groups split by punctuation or
     * spaces. Ordinary long bank references fail Luhn nine times in ten.
     * ponytail: about one bank reference in ten passes Luhn and is refused. The
     * upgrade path is a per-tender reference format in the Dropdown Manager.
     */
    public static function looksLikeCardNumber(string $reference): bool
    {
        // Non-ASCII digits or invalid UTF-8 cannot be checked, so they are refused.
        $odd = preg_match('/(?![0-9])\p{Nd}/u', $reference);
        if ($odd !== 0) {
            return true;
        }

        preg_match_all('/[0-9]+(?:[^\p{L}0-9]{1,3}[0-9]+)*/u', $reference, $runs);
        foreach ($runs[0] as $run) {
            preg_match_all('/[0-9]+/', $run, $m);
            $groups = $m[0];
            foreach ($groups as $i => $_) {
                $digits = '';
                for ($j = $i; $j < count($groups); $j++) {
                    $digits .= $groups[$j];
                    if (strlen($digits) > 19) {
                        break;
                    }
                    if (strlen($digits) >= 13 && self::luhn($digits)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private static function luhn(string $digits): bool
    {
        $sum = 0;
        $double = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];
            if ($double && ($d *= 2) > 9) {
                $d -= 9;
            }
            $sum += $d;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }

    /** The branch a fine belongs to: its loan, then the copy, then the patron. */
    public function fineBranch(object $fine): ?int
    {
        $checkout = ! empty($fine->checkout_id)
            ? DB::table('library_checkout')->where('id', $fine->checkout_id)->first()
            : null;
        $explicit = isset($checkout->branch_id) ? (int) $checkout->branch_id : null;

        return LibraryBranch::resolve($explicit, isset($checkout->copy_id) ? (int) $checkout->copy_id : null, (int) $fine->patron_id);
    }

    /**
     * Take a payment against selected fines, oldest first, in the operator's
     * open cash session. Returns the receipt id. Resubmitting the same payment
     * returns the original receipt and sets $replayed; the same form key with
     * different details is refused, because that is a new payment on a stale form.
     */
    public function pay(int $patronId, array $fineIds, float $amount, string $method, ?string $reference, ?float $tendered, int $userId, string $formKey): int
    {
        $this->replayed = false;
        $tenders = $this->tenders();
        if (! isset($tenders[$method])) {
            throw new LibraryFineException(__('Choose a valid payment method.'));
        }
        $isCash = $tenders[$method]['is_cash'];
        if ($isCash === null) {
            throw new LibraryFineException(__('This payment method is not marked as cash or non-cash. Set it in the Dropdown Manager first.'));
        }
        $reference = trim((string) $reference) ?: null;
        if (! $isCash && $reference === null) {
            throw new LibraryFineException(__('A payment reference is required for this payment method.'));
        }
        // The reference is stored in clear, so a card number must never land in it.
        if ($reference !== null && self::looksLikeCardNumber($reference)) {
            throw new LibraryFineException(__('Do not enter a card number. Use the terminal or transaction reference.'));
        }
        $amountCents = self::cents($amount);
        if ($amountCents <= 0) {
            throw new LibraryFineException(__('Enter an amount greater than zero.'));
        }

        $ids = array_values(array_unique(array_map('intval', $fineIds)));
        sort($ids);
        $tenderedCents = $isCash && $tendered !== null ? self::cents($tendered) : null;
        // Half form identity, half payment details, so one indexed column holds both.
        $formPart = substr(hash('sha256', $userId . ':' . $formKey), 0, 32);
        $key = $formPart . substr(hash('sha256', json_encode([$patronId, $ids, $amountCents, $method, $reference, $tenderedCents])), 0, 32);

        return DB::transaction(function () use ($patronId, $ids, $amountCents, $method, $reference, $tenderedCents, $userId, $key, $formPart, $isCash) {
            // Serialises concurrent desks on one patron, so the repeat check holds.
            if (! DB::table('library_patron')->where('id', $patronId)->lockForUpdate()->exists()) {
                throw new LibraryFineException(__('Patron not found.'));
            }
            $repeat = DB::table('library_fine_receipt')->where('idempotency_key', 'like', $formPart . '%')->first(['id', 'idempotency_key']);
            if ($repeat) {
                if (! hash_equals($repeat->idempotency_key, $key)) {
                    throw new LibraryFineException(__('This form was already used for a different payment, so nothing was recorded. Check the details and submit again.'));
                }
                $this->replayed = true;

                return (int) $repeat->id;
            }

            $session = DB::table('library_cash_session')
                ->where('open_key', LibraryCashDeskService::openKey($userId))
                ->lockForUpdate()->first();
            if (! $session) {
                throw new LibraryFineException(__('Open a cash desk session before taking a payment.'));
            }
            if ((int) auth()->id() === $userId && ! LibraryCashDeskService::atSessionBranch($session, LibraryBranch::operatorBranchId())) {
                throw new LibraryFineException(__('Your cash session belongs to :b. Go back to that branch to take payments, or close the session first.', [
                    'b' => LibraryBranch::name($session->branch_id !== null ? (int) $session->branch_id : null) ?? __('All branches'),
                ]));
            }

            $fines = DB::table('library_fine')->whereIn('id', $ids)
                ->where('patron_id', $patronId)->where('status', self::OUTSTANDING)
                ->orderBy('fine_date')->orderBy('id')->lockForUpdate()->get();
            if ($ids === [] || $fines->count() !== count($ids)) {
                throw new LibraryFineException(__('One of the selected fines is no longer outstanding. Reload and try again.'));
            }
            if ($fines->pluck('currency')->unique()->count() !== 1 || $fines->first()->currency !== $session->currency) {
                throw new LibraryFineException(__('The selected fines are not all in the cash session currency (:c).', ['c' => $session->currency]));
            }

            $balance = $fines->sum(fn ($f) => self::cents($f->amount) - self::cents($f->paid_amount));
            if ($amountCents > $balance) {
                throw new LibraryFineException(__('The amount is more than the :b owed on the selected fines.', ['b' => self::money($balance)]));
            }

            if ($tenderedCents !== null && $tenderedCents < $amountCents) {
                throw new LibraryFineException(__('The cash tendered is less than the amount being paid.'));
            }

            $now = now();
            $receiptId = (int) DB::table('library_fine_receipt')->insertGetId([
                'idempotency_key' => $key,
                'session_id' => $session->id,
                'patron_id' => $patronId,
                'currency' => $session->currency,
                'total_amount' => self::money($amountCents),
                'payment_method' => $method,
                'is_cash' => $isCash ? 1 : 0,
                'payment_reference' => $reference,
                'tendered_amount' => $tenderedCents !== null ? self::money($tenderedCents) : null,
                'change_given' => $tenderedCents !== null ? self::money($tenderedCents - $amountCents) : null,
                'issued_by' => $userId,
                'issued_at' => $now,
                'created_at' => $now,
            ]);
            DB::table('library_fine_receipt')->where('id', $receiptId)->update([
                'receipt_number' => sprintf('FR-%s-%06d', $now->format('Y'), $receiptId),
            ]);

            $left = $amountCents;
            foreach ($fines as $f) {
                if ($left <= 0) {
                    break;
                }
                $total = self::cents($f->amount);
                $paid = self::cents($f->paid_amount);
                $apply = min($left, $total - $paid);
                if ($apply <= 0) {
                    continue;
                }
                $left -= $apply;
                $paid += $apply;

                DB::table('library_fine')->where('id', $f->id)->update([
                    'paid_amount' => self::money($paid),
                    'status' => $paid >= $total ? self::PAID : self::OUTSTANDING,
                    'payment_date' => $now,
                    'payment_method' => $method,
                    'payment_reference' => $reference,
                    'updated_at' => $now,
                ]);
                DB::table('library_fine_receipt_line')->insert([
                    'receipt_id' => $receiptId,
                    'fine_id' => $f->id,
                    'amount' => self::money($apply),
                    'balance_after' => self::money($total - $paid),
                ]);
            }

            self::refreshPatronTotals($patronId);

            return $receiptId;
        });
    }

    /** Write off what is left on a fine. Anything already paid stays paid. */
    public function waive(int $fineId, string $reason, int $userId): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new LibraryFineException(__('A reason is required to waive a fine.'));
        }

        DB::transaction(function () use ($fineId, $reason, $userId) {
            $fine = DB::table('library_fine')->where('id', $fineId)->lockForUpdate()->first();
            if (! $fine || $fine->status !== self::OUTSTANDING) {
                throw new LibraryFineException(__('This fine is no longer outstanding.'));
            }
            $balance = self::cents($fine->amount) - self::cents($fine->paid_amount);
            if ($balance <= 0) {
                throw new LibraryFineException(__('This fine has nothing left to waive.'));
            }

            $now = now();
            DB::table('library_fine')->where('id', $fineId)->update([
                'status' => self::WAIVED,
                'waived_by' => $userId,
                'waived_date' => $now,
                'waive_reason' => $reason,
                'updated_at' => $now,
            ]);
            DB::table('library_fine_waiver')->insert([
                'fine_id' => $fineId,
                'patron_id' => $fine->patron_id,
                'branch_id' => $this->fineBranch($fine),
                'currency' => $fine->currency,
                'amount' => self::money($balance),
                'reason' => $reason,
                'waived_by' => $userId,
                'waived_at' => $now,
            ]);

            self::refreshPatronTotals((int) $fine->patron_id);
        });
    }

    public function receipt(int $id): ?object
    {
        $r = DB::table('library_fine_receipt as r')
            ->join('library_patron as p', 'p.id', '=', 'r.patron_id')
            ->join('library_cash_session as s', 's.id', '=', 'r.session_id')
            ->where('r.id', $id)
            ->select('r.*', 'p.first_name', 'p.last_name', 'p.card_number', 's.branch_id')
            ->first();
        if ($r) {
            $r->lines = DB::table('library_fine_receipt_line as l')
                ->join('library_fine as f', 'f.id', '=', 'l.fine_id')
                ->where('l.receipt_id', $id)
                ->select('l.*', 'f.fine_type', 'f.fine_date', 'f.description')
                ->orderBy('l.id')->get()
                ->each(fn ($l) => $l->description = self::description($l->description));
        }

        return $r;
    }
}
