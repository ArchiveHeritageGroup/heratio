<?php

/**
 * HeritageAccountingService - heritage asset accounting (multi-standard)
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

namespace AhgHeritageManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads and writes the heritage_asset register and its four sub-ledgers
 * (valuation history, impairment assessments, movement register, journal
 * entries). Valuations are recorded by HeritageValuationService, which owns
 * the revaluation arithmetic; everything else lives here.
 */
class HeritageAccountingService
{
    public function available(): bool
    {
        return Schema::hasTable('heritage_asset');
    }

    /** Active accounting standards, in configured order. */
    public function getAccountingStandards(bool $activeOnly = true)
    {
        if (! Schema::hasTable('heritage_accounting_standard')) {
            return collect();
        }

        return DB::table('heritage_accounting_standard')
            ->when($activeOnly, fn ($q) => $q->where('is_active', 1))
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
    }

    /** Active asset classes, in configured order. */
    public function getAssetClasses()
    {
        if (! Schema::hasTable('heritage_asset_class')) {
            return collect();
        }

        return DB::table('heritage_asset_class')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Base query: asset + standard + class + linked record (title, identifier,
     * slug). Both information_object_id and the older object_id are honoured.
     */
    private function baseQuery()
    {
        $culture = app()->getLocale();

        return DB::table('heritage_asset as ha')
            ->leftJoin('heritage_accounting_standard as hs', 'ha.accounting_standard_id', '=', 'hs.id')
            ->leftJoin('heritage_asset_class as hc', 'ha.asset_class_id', '=', 'hc.id')
            ->leftJoin('information_object as io', 'io.id', '=', DB::raw('COALESCE(ha.information_object_id, ha.object_id)'))
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('ioi.id', '=', 'io.id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id');
    }

    private function baseSelect(): array
    {
        return [
            'ha.*',
            'hs.code as standard_code',
            'hs.name as standard_name',
            'hc.code as class_code',
            'hc.name as class_name',
            'io.identifier as object_identifier',
            'ioi.title as object_title',
            's.slug as object_slug',
        ];
    }

    public function getAsset(int $id): ?object
    {
        if (! $this->available()) {
            return null;
        }

        return $this->baseQuery()->select($this->baseSelect())->where('ha.id', $id)->first();
    }

    /**
     * Filtered asset list. Filters: standard_id, class_id, recognition_status,
     * repository_id, information_object_id, search.
     *
     * @return array{items: \Illuminate\Support\Collection, total: int}
     */
    public function browse(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        if (! $this->available()) {
            return ['items' => collect(), 'total' => 0];
        }

        $q = $this->baseQuery()->select($this->baseSelect());
        $this->applyFilters($q, $filters);

        $total = (clone $q)->count();
        $items = $q->orderByDesc('ha.created_at')->orderByDesc('ha.id')->limit($limit)->offset($offset)->get();

        return ['items' => $items, 'total' => $total];
    }

    /** Same filters as browse(), as a paginator for the register pages. */
    public function paginate(array $filters = [], int $perPage = 25)
    {
        $q = $this->baseQuery()->select($this->baseSelect());
        $this->applyFilters($q, $filters);

        return $q->orderByDesc('ha.created_at')->orderByDesc('ha.id')->paginate($perPage);
    }

    private function applyFilters($q, array $filters): void
    {
        if (! empty($filters['standard_id'])) {
            $q->where('ha.accounting_standard_id', (int) $filters['standard_id']);
        }
        if (! empty($filters['class_id'])) {
            $q->where('ha.asset_class_id', (int) $filters['class_id']);
        }
        if (! empty($filters['recognition_status'])) {
            $q->where('ha.recognition_status', $filters['recognition_status']);
        }
        if (! empty($filters['repository_id'])) {
            $q->where('io.repository_id', (int) $filters['repository_id']);
        }
        if (! empty($filters['information_object_id'])) {
            $ioId = (int) $filters['information_object_id'];
            $q->where(function ($w) use ($ioId) {
                $w->where('ha.information_object_id', $ioId)->orWhere('ha.object_id', $ioId);
            });
        }
        if (! empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $q->where(function ($w) use ($like) {
                $w->where('ioi.title', 'like', $like)
                    ->orWhere('io.identifier', 'like', $like)
                    ->orWhere('ha.donor_name', 'like', $like);
            });
        }
    }

    /** Dashboard figures, optionally scoped to a repository and/or standard. */
    public function getDashboardStats(?int $repositoryId = null, ?int $standardId = null): array
    {
        $empty = ['total' => 0, 'recognised' => 0, 'not_recognised' => 0, 'pending' => 0, 'total_value' => 0.0,
            'total_impairment' => 0.0, 'total_revaluation_surplus' => 0.0, 'by_class' => collect()];
        if (! $this->available()) {
            return $empty;
        }

        $q = DB::table('heritage_asset as ha')
            ->leftJoin('information_object as io', 'io.id', '=', DB::raw('COALESCE(ha.information_object_id, ha.object_id)'))
            ->when($repositoryId, fn ($w) => $w->where('io.repository_id', $repositoryId))
            ->when($standardId, fn ($w) => $w->where('ha.accounting_standard_id', $standardId));

        $byClass = (clone $q)
            ->leftJoin('heritage_asset_class as hc', 'ha.asset_class_id', '=', 'hc.id')
            ->groupBy('hc.id', 'hc.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get(['hc.name as class_name', DB::raw('COUNT(*) as count'), DB::raw('SUM(ha.current_carrying_amount) as total_value')]);

        return [
            'total' => (clone $q)->count(),
            'recognised' => (clone $q)->where('ha.recognition_status', 'recognised')->count(),
            'not_recognised' => (clone $q)->where('ha.recognition_status', 'not_recognised')->count(),
            'pending' => (clone $q)->where('ha.recognition_status', 'pending')->count(),
            'total_value' => (float) (clone $q)->where('ha.recognition_status', 'recognised')->sum('ha.current_carrying_amount'),
            'total_impairment' => (float) (clone $q)->sum('ha.impairment_loss'),
            'total_revaluation_surplus' => (float) (clone $q)->sum('ha.revaluation_surplus'),
            'by_class' => $byClass,
        ];
    }

    // ------------------------------------------------------------------
    // Sub-ledgers (read)
    // ------------------------------------------------------------------

    public function getValuationHistory(int $assetId)
    {
        return $this->ledger('heritage_valuation_history', $assetId, 'valuation_date');
    }

    public function getImpairmentAssessments(int $assetId)
    {
        return $this->ledger('heritage_impairment_assessment', $assetId, 'assessment_date');
    }

    public function getMovements(int $assetId)
    {
        return $this->ledger('heritage_movement_register', $assetId, 'movement_date');
    }

    public function getJournalEntries(int $assetId)
    {
        return $this->ledger('heritage_journal_entry', $assetId, 'journal_date');
    }

    private function ledger(string $table, int $assetId, string $dateCol)
    {
        if (! Schema::hasTable($table)) {
            return collect();
        }

        return DB::table($table)->where('heritage_asset_id', $assetId)
            ->orderByDesc($dateCol)->orderByDesc('id')->get();
    }

    // ------------------------------------------------------------------
    // Writes
    // ------------------------------------------------------------------

    /**
     * Capitalisation rule: a standard flagged capitalisation_required needs at
     * least one monetary value on the asset. Returns an error message or null.
     */
    public function validateCapitalisation(array $data): ?string
    {
        if (empty($data['accounting_standard_id'])) {
            return null;
        }
        $standard = DB::table('heritage_accounting_standard')->where('id', $data['accounting_standard_id'])->first();
        if (! $standard || ! $standard->capitalisation_required) {
            return null;
        }
        foreach (['acquisition_cost', 'fair_value_at_acquisition', 'current_carrying_amount', 'initial_carrying_amount', 'insurance_value'] as $f) {
            if (! empty($data[$f]) && (float) $data[$f] > 0) {
                return null;
            }
        }

        return __('The :code standard requires capitalisation. Please provide a monetary value (acquisition cost, fair value, or carrying amount).', ['code' => $standard->code]);
    }

    /**
     * Impairment assessment (GRAP 103.61-67 / IPSAS 21). When an impairment is
     * identified and no loss is entered, the loss is the carrying amount less
     * the recoverable service amount, never below zero; the asset's carrying
     * amount, cumulative impairment loss and last-impairment date follow.
     */
    public function addImpairment(int $assetId, array $data, ?int $userId): int
    {
        if (! DB::table('heritage_asset')->where('id', $assetId)->exists()) {
            throw new \RuntimeException('Asset not found');
        }

        return DB::transaction(function () use ($assetId, $data, $userId) {
            $asset = DB::table('heritage_asset')->where('id', $assetId)->lockForUpdate()->first();
            $data['heritage_asset_id'] = $assetId;
            $data['carrying_amount_before'] = (float) $asset->current_carrying_amount;
            $data['created_by'] = $userId;
            $data['created_at'] = now();

            if (! empty($data['impairment_identified'])) {
                if (($data['impairment_loss'] ?? null) === null && ($data['recoverable_amount'] ?? null) !== null) {
                    $data['impairment_loss'] = max(0.0, $data['carrying_amount_before'] - (float) $data['recoverable_amount']);
                }
                if ((float) ($data['impairment_loss'] ?? 0) > $data['carrying_amount_before']) {
                    throw new \InvalidArgumentException('Impairment loss exceeds the carrying amount');
                }
                $data['carrying_amount_after'] = $data['carrying_amount_before'] - (float) ($data['impairment_loss'] ?? 0);
            }

            $id = (int) DB::table('heritage_impairment_assessment')->insertGetId($data);

            if (! empty($data['impairment_identified'])) {
                $details = collect(['physical_damage', 'obsolescence', 'change_in_use', 'external_factors'])
                    ->filter(fn ($k) => ! empty($data[$k]))
                    ->map(fn ($k) => trim(str_replace('_', ' ', $k) . (! empty($data[$k . '_details']) ? ': ' . $data[$k . '_details'] : '')))
                    ->implode('; ');

                // Post the reserve-first split to the ledger; the asset's reserve
                // falls by exactly what the ledger took from OCI.
                $reserve = (float) $asset->revaluation_surplus;
                if (Schema::hasTable('ahg_heritage_oci_movement')) {
                    $oci = app(OciMovementService::class);
                    $before = $oci->getAccumulatedOciSurplus($assetId);
                    $oci->recordImpairment(
                        $assetId,
                        (float) ($data['impairment_loss'] ?? 0),
                        $data['assessment_date'],
                        $data['notes'] ?? null,
                        $asset->information_object_id ?? $asset->object_id ?? null,
                        $userId,
                        $this->currency() ?? 'USD'
                    );
                    $reserve = max(0.0, round($reserve - ($before - $oci->getAccumulatedOciSurplus($assetId)), 2));
                }

                DB::table('heritage_asset')->where('id', $assetId)->update([
                    'revaluation_surplus' => $reserve,
                    'last_impairment_date' => $data['assessment_date'],
                    'impairment_indicators' => 1,
                    'impairment_indicators_details' => $details ?: ($data['notes'] ?? null),
                    'impairment_loss' => (float) $asset->impairment_loss + (float) ($data['impairment_loss'] ?? 0),
                    'recoverable_amount' => $data['recoverable_amount'] ?? $asset->recoverable_amount,
                    'current_carrying_amount' => $data['carrying_amount_after'],
                    'updated_at' => now(),
                ]);
            }

            $this->logTransaction($assetId, $asset, 'impairment', (float) ($data['impairment_loss'] ?? 0), $data, $userId);

            return $id;
        });
    }

    /** Movement register entry; the asset's current location follows the move. */
    public function addMovement(int $assetId, array $data, ?int $userId): int
    {
        $asset = DB::table('heritage_asset')->where('id', $assetId)->first();
        if (! $asset) {
            throw new \RuntimeException('Asset not found');
        }

        return DB::transaction(function () use ($asset, $assetId, $data, $userId) {
            $data['heritage_asset_id'] = $assetId;
            $data['from_location'] = ($data['from_location'] ?? null) ?: $asset->current_location;
            $data['created_by'] = $userId;
            $data['created_at'] = now();

            $id = (int) DB::table('heritage_movement_register')->insertGetId($data);

            if (! empty($data['to_location'])) {
                DB::table('heritage_asset')->where('id', $assetId)->update([
                    'current_location' => $data['to_location'],
                    'updated_at' => now(),
                ]);
            }

            $this->logTransaction($assetId, $asset, 'movement', null, $data, $userId);

            return $id;
        });
    }

    /** Journal entry against the asset. */
    public function addJournal(int $assetId, array $data, ?int $userId): int
    {
        $asset = DB::table('heritage_asset')->where('id', $assetId)->first();
        if (! $asset) {
            throw new \RuntimeException('Asset not found');
        }

        return DB::transaction(function () use ($asset, $assetId, $data, $userId) {
            $data['heritage_asset_id'] = $assetId;
            $data['fiscal_year'] = ($data['fiscal_year'] ?? null) ?: (int) date('Y');
            $data['created_by'] = $userId;
            $data['created_at'] = now();

            $id = (int) DB::table('heritage_journal_entry')->insertGetId($data);

            $this->logTransaction($assetId, $asset, 'journal_' . $data['journal_type'], (float) $data['debit_amount'], $data, $userId);

            return $id;
        });
    }

    /** Settings page: the ticked standards become active, the rest inactive. */
    public function setActiveStandards(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        DB::transaction(function () use ($ids) {
            DB::table('heritage_accounting_standard')->update(['is_active' => 0]);
            if ($ids) {
                DB::table('heritage_accounting_standard')->whereIn('id', $ids)->update(['is_active' => 1]);
            }
        });
    }

    /** Audit row in heritage_transaction_log for every create/update/sub-ledger write. */
    public function logTransaction(?int $assetId, ?object $asset, string $type, ?float $amount, ?array $data, ?int $userId): void
    {
        if (! Schema::hasTable('heritage_transaction_log')) {
            return;
        }
        DB::table('heritage_transaction_log')->insert([
            'heritage_asset_id' => $assetId,
            'object_id' => $asset ? ($asset->information_object_id ?? $asset->object_id ?? null) : null,
            'transaction_type' => substr($type, 0, 50),
            'transaction_date' => date('Y-m-d'),
            'amount' => $amount,
            'transaction_data' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    /** Currency of the active region (institution config), for labels only. */
    public function currency(): ?string
    {
        try {
            if (Schema::hasTable('heritage_institution_config')) {
                return DB::table('heritage_institution_config')->whereNull('repository_id')->value('currency') ?: null;
            }
        } catch (\Throwable $e) {
        }

        return null;
    }
}
