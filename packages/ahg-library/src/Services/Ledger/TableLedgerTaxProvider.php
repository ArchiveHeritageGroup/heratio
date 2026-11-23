<?php

namespace AhgLibrary\Services\Ledger;

use Illuminate\Support\Facades\DB;

/**
 * Default provider: rates the institution loads into library_tax_rate, with
 * the rounding rule from library_settings. Ships with no rates, because which
 * rate applies is a decision for the market, not a default of the software.
 */
class TableLedgerTaxProvider implements LedgerTaxProvider
{
    public const ROUNDING_SETTING = 'ledger_tax_rounding';

    public function key(): string
    {
        return 'table';
    }

    public function calculate(array $lines, string $documentDate): array
    {
        $out = [];
        $groups = [];
        foreach ($lines as $i => $line) {
            $rate = $this->rate($line['tax_code'], $documentDate);
            $out[$i] = ['rate' => $rate, 'tax_cents' => LedgerMoney::tax($line['net_cents'], $rate)];
            $groups[$rate][] = $i;
        }

        if ($this->rounding() !== 'document') {
            return $out;
        }

        // Round each rate's total once, then share it across its lines by
        // largest remainder so the lines still add up to the document tax.
        foreach ($groups as $rate => $indexes) {
            $net = 0;
            $remainders = [];
            $allocated = 0;
            foreach ($indexes as $i) {
                $product = $lines[$i]['net_cents'] * $rate;
                $net += $lines[$i]['net_cents'];
                $out[$i]['tax_cents'] = intdiv($product, LedgerMoney::RATE_SCALE);
                $allocated += $out[$i]['tax_cents'];
                $remainders[$i] = $product % LedgerMoney::RATE_SCALE;
            }
            arsort($remainders);
            $extra = LedgerMoney::tax($net, $rate) - $allocated;
            foreach (array_keys($remainders) as $i) {
                if ($extra <= 0) {
                    break;
                }
                $out[$i]['tax_cents']++;
                $extra--;
            }
        }

        return $out;
    }

    public function nextNumber(string $docType, string $documentDate): string
    {
        $prefix = $docType === LibraryLedgerService::TYPE_CREDIT ? 'SCN' : 'SINV';
        $year = substr($documentDate, 0, 4);
        $key = $prefix . '-' . $year;

        DB::table('library_ledger_sequence')->insertOrIgnore(['seq_key' => $key, 'current_value' => 0]);
        $last = (int) DB::table('library_ledger_sequence')->where('seq_key', $key)->lockForUpdate()->value('current_value');
        $next = $last + 1;
        DB::table('library_ledger_sequence')->where('seq_key', $key)->update(['current_value' => $next]);

        return $key . '-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    public function requiredFields(string $docType): array
    {
        return ['supplier_reference', 'document_date', 'vendor_id'];
    }

    private function rounding(): string
    {
        return (string) (DB::table('library_settings')->where('setting_key', self::ROUNDING_SETTING)->value('setting_value') ?: 'line');
    }

    private function rate(string $code, string $date): int
    {
        $rows = DB::table('library_tax_rate')
            ->where('tax_code', $code)
            ->where('is_active', 1)
            ->where('valid_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date))
            ->limit(2)
            ->pluck('rate_percent');

        if ($rows->count() !== 1) {
            throw new LedgerException($rows->isEmpty()
                ? "No tax rate for code {$code} on {$date}."
                : "More than one tax rate for code {$code} on {$date}.");
        }

        return LedgerMoney::rateToInt($rows->first());
    }
}
