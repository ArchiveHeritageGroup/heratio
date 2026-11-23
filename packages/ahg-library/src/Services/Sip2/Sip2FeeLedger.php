<?php

/**
 * Sip2FeeLedger - applies a terminal's fee payment to library_fine, oldest
 * first. A transaction id is honoured once per terminal, so a retry after a
 * lost reply cannot take the same payment twice (#1475).
 *
 * ponytail: no shared fine-payment service exists yet; when #1474 item 1
 * lands, this allocation should move behind it.
 *
 * Copyright (C) 2026 Johan Pieterse
 * License: AGPL-3.0-or-later
 */

namespace AhgLibrary\Services\Sip2;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class Sip2FeeLedger
{
    /** @return array{0: bool, 1: string} accepted, screen message */
    public function pay(
        object $account,
        int $patronId,
        int $cents,
        string $currency,
        string $paymentType,
        string $transactionId,
        ?int $fineId = null
    ): array {
        try {
            return DB::transaction(fn () => $this->apply($account, $patronId, $cents, $currency, $paymentType, $transactionId, $fineId));
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return [false, 'Transaction id already used'];
            }
            throw $e;
        }
    }

    private function apply(object $account, int $patronId, int $cents, string $currency, string $paymentType, string $txn, ?int $fineId): array
    {
        DB::table('library_patron')->where('id', $patronId)->lockForUpdate()->first();

        $prior = DB::table('library_sip2_payment')
            ->where('account_id', $account->id)->where('transaction_id', $txn)->first();
        if ($prior) {
            $same = (int) $prior->patron_id === $patronId
                && self::cents($prior->amount) === $cents
                && $prior->currency === $currency;

            return $same ? [true, 'Payment already recorded'] : [false, 'Transaction id already used'];
        }

        $fines = DB::table('library_fine')
            ->where('patron_id', $patronId)
            ->where('status', 'outstanding')
            ->where('currency', $currency)
            ->when($fineId !== null, fn ($q) => $q->where('id', $fineId))
            ->orderBy('fine_date')->orderBy('id')
            ->lockForUpdate()
            ->get();

        $payable = $fines->sum(fn ($f) => self::cents($f->amount) - self::cents($f->paid_amount));
        if ($payable <= 0) {
            return [false, 'Nothing to pay'];
        }
        if ($cents > $payable) {
            return [false, 'Payment exceeds the amount owed'];
        }

        $left = $cents;
        foreach ($fines as $fine) {
            if ($left <= 0) {
                break;
            }
            $amount = self::cents($fine->amount);
            $paid = self::cents($fine->paid_amount);
            $take = min($left, $amount - $paid);
            if ($take <= 0) {
                continue;
            }
            $left -= $take;
            DB::table('library_fine')->where('id', $fine->id)->update([
                'paid_amount' => ($paid + $take) / 100,
                'status' => $paid + $take >= $amount ? 'paid' : 'outstanding',
                'payment_date' => now(),
                'payment_method' => 'sip2-' . $paymentType,
                'payment_reference' => $txn,
                'updated_at' => now(),
            ]);
        }

        $owed = (float) DB::table('library_fine')
            ->where('patron_id', $patronId)->where('status', 'outstanding')
            ->sum(DB::raw('amount - paid_amount'));
        DB::table('library_patron')->where('id', $patronId)->update([
            'total_fines_owed' => $owed,
            'total_fines_paid' => DB::raw('total_fines_paid + ' . number_format($cents / 100, 2, '.', '')),
            'updated_at' => now(),
        ]);

        DB::table('library_sip2_payment')->insert([
            'account_id' => $account->id,
            'transaction_id' => $txn,
            'patron_id' => $patronId,
            'amount' => $cents / 100,
            'currency' => $currency,
            'payment_type' => $paymentType,
            'created_at' => now(),
        ]);

        return [true, 'Payment accepted'];
    }

    public static function cents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
