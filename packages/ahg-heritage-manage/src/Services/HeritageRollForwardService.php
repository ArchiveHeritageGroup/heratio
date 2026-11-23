<?php

namespace AhgHeritageManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opening-to-closing reconciliation of heritage asset carrying amounts,
 * replayed from the dated sub-ledgers. Read-only. Amounts are summed in cents.
 *
 * ponytail: depreciation is not a line because no dated depreciation ledger is
 * written anywhere. An asset carrying depreciation shows up as unreconciled
 * rather than being silently absorbed. Add a line once a ledger exists.
 */
class HeritageRollForwardService
{
    private const LINES = ['opening', 'additions', 'revaluation_increase', 'revaluation_decrease', 'impairment', 'reversal', 'disposals', 'closing'];

    public function compute(string $start, string $end, ?int $standardId = null, ?int $classId = null): array
    {
        $assets = $this->assets($standardId, $classId);
        $events = $this->events($assets->pluck('id')->all());
        $classes = Schema::hasTable('heritage_asset_class')
            ? DB::table('heritage_asset_class')->pluck('name', 'id')
            : collect();

        $ledgerOnly = $this->ledgerOnly($end, $standardId, $classId, $assets, $events);
        $totals = array_fill_keys(self::LINES, 0);
        $byClass = [];
        $unreconciled = [];
        $closingCount = 0;
        $dayBefore = date('Y-m-d', strtotime($start.' -1 day'));

        foreach ($assets as $asset) {
            $derecognised = $asset->derecognition_date ? substr((string) $asset->derecognition_date, 0, 10) : null;
            $recognised = substr((string) ($asset->recognition_date ?? $asset->acquisition_date ?? $asset->created_at ?? '0001-01-01'), 0, 10);
            if ($derecognised && $recognised > $derecognised) {
                $recognised = $derecognised;
            }
            $initial = $this->initialCents($asset);
            $own = $events[$asset->id] ?? [];

            $at = function (string $date) use ($recognised, $derecognised, $initial, $own): int {
                $limit = $derecognised && $derecognised < $date ? $derecognised : $date;
                $sum = $recognised <= $date ? $initial : 0;
                foreach ($own as $e) {
                    if ($e['date'] <= $limit) {
                        $sum += $e['cents'];
                    }
                }

                return $sum;
            };

            $line = array_fill_keys(self::LINES, 0);
            if (! $derecognised || $derecognised >= $start) {
                $line['opening'] = $at($dayBefore);
                if ($recognised >= $start && $recognised <= $end) {
                    $line['additions'] = $initial;
                }
                $to = $derecognised && $derecognised < $end ? $derecognised : $end;
                foreach ($own as $e) {
                    if ($e['date'] >= $start && $e['date'] <= $to) {
                        $line[$e['line']] += $e['cents'];
                    }
                }
                if ($derecognised && $derecognised <= $end) {
                    $line['disposals'] = -$at($derecognised);
                } else {
                    $line['closing'] = $at($end);
                    if ($recognised <= $end) {
                        $closingCount++;
                    }
                }
            }

            $classKey = (int) ($asset->asset_class_id ?? 0);
            $byClass[$classKey] ??= ['class' => $classes[$classKey] ?? 'Unclassified'] + array_fill_keys(self::LINES, 0);
            foreach (self::LINES as $k) {
                $totals[$k] += $line[$k];
                $byClass[$classKey][$k] += $line[$k];
            }

            // The register holds today's figure, so compare it with the full replay.
            $expected = $at('9999-12-31');
            $register = $this->cents($asset->current_carrying_amount ?? 0);
            if ($register !== $expected) {
                $unreconciled[] = [
                    'asset_id' => (int) $asset->id,
                    'register' => $register / 100,
                    'ledger' => $expected / 100,
                    'difference' => ($register - $expected) / 100,
                ];
            }
        }

        return [
            'period_start' => $start,
            'period_end' => $end,
            'lines' => array_map(fn ($c) => $c / 100, $totals),
            'by_class' => array_values(array_map(function ($row) {
                foreach (self::LINES as $k) {
                    $row[$k] /= 100;
                }

                return $row;
            }, $byClass)),
            'asset_count' => $closingCount,
            'reconciled' => $unreconciled === [] && $ledgerOnly === [],
            'unreconciled' => $unreconciled,
            'ledger_only' => $ledgerOnly,
        ];
    }

    /** Markdown table for the disclosure note. */
    public function markdown(array $result, \AhgHeritageManage\Contracts\RollForwardProfile $profile): string
    {
        $out = "**".$profile->title()."**\n\n| Item | Amount |\n|---|---:|\n";
        foreach ($profile->lineLabels() as $key => $label) {
            $out .= '| '.$label.' | '.number_format($result['lines'][$key] ?? 0, 2)." |\n";
        }
        if ($result['unreconciled'] !== []) {
            $out .= "\n**Not reconciled:** ".count($result['unreconciled'])
                ." asset(s) have a register carrying amount that the dated ledgers do not explain. Resolve these before signing.\n";
        }
        if ($result['ledger_only'] !== []) {
            $out .= "\n**Not reconciled:** ".count($result['ledger_only'])
                ." movement(s) on the reserve ledger have no matching register entry and are not in the table above. Resolve these before signing.\n";
        }

        return $out;
    }

    private function assets(?int $standardId, ?int $classId)
    {
        if (! Schema::hasTable('heritage_asset')) {
            return collect();
        }

        return DB::table('heritage_asset')
            ->where(fn ($q) => $q->whereIn('recognition_status', ['recognised', 'derecognised'])->orWhereNotNull('derecognition_date'))
            ->when($standardId, fn ($q) => $q->where('accounting_standard_id', $standardId))
            ->when($classId, fn ($q) => $q->where('asset_class_id', $classId))
            ->get(['id', 'asset_class_id', 'recognition_date', 'acquisition_date', 'created_at', 'derecognition_date',
                'initial_carrying_amount', 'acquisition_cost', 'fair_value_at_acquisition', 'nominal_value', 'current_carrying_amount']);
    }

    /** @return array<int, array<int, array{date:string, cents:int, line:string}>> */
    private function events(array $ids): array
    {
        $out = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (DB::table('heritage_valuation_history')->whereIn('heritage_asset_id', $chunk)->get(['heritage_asset_id', 'valuation_date', 'valuation_change', 'previous_value', 'new_value']) as $r) {
                $cents = $this->cents($r->valuation_change ?? ((float) $r->new_value - (float) $r->previous_value));
                if ($cents !== 0) {
                    $out[$r->heritage_asset_id][] = ['date' => substr((string) $r->valuation_date, 0, 10), 'cents' => $cents, 'line' => $cents > 0 ? 'revaluation_increase' : 'revaluation_decrease'];
                }
            }
            foreach (DB::table('heritage_impairment_assessment')->whereIn('heritage_asset_id', $chunk)->get(['heritage_asset_id', 'assessment_date', 'impairment_identified', 'impairment_loss', 'reversal_amount', 'reversal_date']) as $r) {
                if ($r->impairment_identified && (float) $r->impairment_loss > 0) {
                    $out[$r->heritage_asset_id][] = ['date' => substr((string) $r->assessment_date, 0, 10), 'cents' => -$this->cents($r->impairment_loss), 'line' => 'impairment'];
                }
                if ((float) $r->reversal_amount > 0) {
                    $out[$r->heritage_asset_id][] = ['date' => substr((string) ($r->reversal_date ?? $r->assessment_date), 0, 10), 'cents' => $this->cents($r->reversal_amount), 'line' => 'reversal'];
                }
            }
        }

        return $out;
    }

    /**
     * Reserve-ledger movements up to $end that no register event explains, such
     * as entries keyed straight on the OCI screen. Compared per asset, day and
     * type. Register events with no ledger row are older history and are fine.
     */
    private function ledgerOnly(string $end, ?int $standardId, ?int $classId, $assets, array $events): array
    {
        if (! Schema::hasTable('ahg_heritage_oci_movement')) {
            return [];
        }

        $registered = [];
        foreach ($events as $assetId => $own) {
            foreach ($own as $e) {
                $key = $assetId.'|'.$e['date'].'|'.$e['line'];
                $registered[$key] = ($registered[$key] ?? 0) + $e['cents'];
            }
        }
        $disposedOn = $assets->filter(fn ($a) => $a->derecognition_date)
            ->mapWithKeys(fn ($a) => [$a->id => substr((string) $a->derecognition_date, 0, 10)]);
        $lines = ['revaluation_up' => 'revaluation_increase', 'revaluation_down' => 'revaluation_decrease', 'impairment' => 'impairment', 'reversal' => 'reversal'];

        $rows = DB::table('ahg_heritage_oci_movement')
            ->where('valuation_date', '<=', $end)
            ->when($standardId || $classId, fn ($q) => $q->whereIn('heritage_asset_id', DB::table('heritage_asset')
                ->when($standardId, fn ($s) => $s->where('accounting_standard_id', $standardId))
                ->when($classId, fn ($s) => $s->where('asset_class_id', $classId))
                ->select('id')))
            ->selectRaw('heritage_asset_id, valuation_date, movement_type, SUM(amount) AS total')
            ->groupBy('heritage_asset_id', 'valuation_date', 'movement_type')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $day = substr((string) $r->valuation_date, 0, 10);
            $matched = $r->movement_type === 'disposal'
                ? ($disposedOn[$r->heritage_asset_id] ?? null) === $day
                : isset($lines[$r->movement_type])
                    && ($registered[$r->heritage_asset_id.'|'.$day.'|'.$lines[$r->movement_type]] ?? null) === $this->cents($r->total);
            if (! $matched) {
                $out[] = ['asset_id' => (int) $r->heritage_asset_id, 'date' => $day, 'type' => $r->movement_type, 'amount' => (float) $r->total];
            }
        }

        return $out;
    }

    /** First populated measurement; the initial amount column defaults to 0, meaning unset. */
    private function initialCents(object $asset): int
    {
        foreach (['initial_carrying_amount', 'acquisition_cost', 'fair_value_at_acquisition', 'nominal_value'] as $field) {
            if ((float) ($asset->{$field} ?? 0) > 0) {
                return $this->cents($asset->{$field});
            }
        }

        return 0;
    }

    private function cents($amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
