<?php

/**
 * PremisRightsService - PREMIS 3.0 rightsStatement projector.
 *
 * Reads the existing ODRL policy layer (research_rights_policy) for the
 * given information object and writes one ahg_premis_rights row per
 * granted-act / basis pairing. Idempotent: existing rows are refreshed,
 * stale rows removed.
 *
 * The ODRL -> PREMIS mapping is intentionally narrow:
 *   ODRL policy_type=permission  -> PREMIS rightsBasis (heuristic on constraints)
 *   ODRL action_type             -> PREMIS rightsGranted/act
 *   ODRL constraints_json/policy_json -> PREMIS rightsGranted/restriction
 *
 * Issue #653 Phase 1.
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

namespace AhgPreservation\Services;

use AhgPreservation\Models\AhgPremisRights;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PremisRightsService
{
    /**
     * Map ODRL action_type -> PREMIS rightsGranted/act vocabulary.
     */
    protected array $actMap = [
        'use'         => 'use',
        'reproduce'   => 'replicate',
        'distribute'  => 'disseminate',
        'modify'      => 'modify',
        'archive'     => 'migrate',
        'display'     => 'disseminate',
    ];

    /**
     * Project ODRL policies attached to an IO into PREMIS rights rows.
     *
     * Returns the freshly-written collection of AhgPremisRights records.
     * Removes any existing rows whose (basis, act) pair no longer appears
     * in the ODRL source so the table stays in sync.
     */
    public function createFromOdrl(int $informationObjectId): Collection
    {
        if (! Schema::hasTable('research_rights_policy') || ! Schema::hasTable('ahg_premis_rights')) {
            return collect();
        }

        $odrlRows = DB::table('research_rights_policy')
            ->where('target_type', 'informationObject')
            ->where('target_id', $informationObjectId)
            ->get();

        // Capture existing rows for diff-and-purge.
        $existing = AhgPremisRights::where('information_object_id', $informationObjectId)->get();
        $existingByKey = $existing->keyBy(function ($r) {
            return $r->rights_basis . '|' . $r->rights_granted_act;
        });

        $written = collect();
        $seenKeys = [];

        foreach ($odrlRows as $row) {
            $act = $this->mapAct((string) ($row->action_type ?? ''));
            if ($act === null) {
                continue;
            }
            $basis = $this->mapBasis($row);
            $key = $basis . '|' . $act;
            $seenKeys[$key] = true;

            $constraints = $this->decodeJson($row->constraints_json ?? null);
            $restriction = $this->summariseConstraints($row->policy_type ?? 'permission', $constraints);
            [$start, $end] = $this->extractDates($constraints);

            $payload = [
                'information_object_id'      => $informationObjectId,
                'rights_basis'               => $basis,
                'rights_granted_act'         => $act,
                'rights_granted_restriction' => $restriction,
                'applicable_dates_start'     => $start,
                'applicable_dates_end'       => $end,
                'source_xml'                 => $this->renderOdrlSnippet($row),
                'created_at'                 => now()->format('Y-m-d H:i:s'),
            ];

            if (isset($existingByKey[$key])) {
                $existingByKey[$key]->fill($payload)->save();
                $written->push($existingByKey[$key]->refresh());
            } else {
                $written->push(AhgPremisRights::create($payload));
            }
        }

        // Purge stale rows.
        foreach ($existing as $row) {
            $key = $row->rights_basis . '|' . $row->rights_granted_act;
            if (! isset($seenKeys[$key])) {
                $row->delete();
            }
        }

        return $written;
    }

    /**
     * Get the PREMIS rights rows for an IO (read accessor for the serializer).
     */
    public function getForIo(int $informationObjectId): Collection
    {
        if (! Schema::hasTable('ahg_premis_rights')) {
            return collect();
        }
        return AhgPremisRights::where('information_object_id', $informationObjectId)
            ->orderBy('rights_basis')
            ->orderBy('rights_granted_act')
            ->get();
    }

    protected function mapAct(string $action): ?string
    {
        $key = strtolower($action);
        if (isset($this->actMap[$key])) {
            return $this->actMap[$key];
        }
        // If the action already matches a PREMIS act, accept it.
        if (in_array($key, AhgPremisRights::ACTS, true)) {
            return $key;
        }
        return null;
    }

    /**
     * Heuristic ODRL -> PREMIS rightsBasis mapping. ODRL doesn't model the
     * "why" (basis) explicitly; we derive it from constraints + policy_type.
     */
    protected function mapBasis(object $odrlRow): string
    {
        $type = strtolower((string) ($odrlRow->policy_type ?? 'permission'));
        $constraints = $this->decodeJson($odrlRow->constraints_json ?? null);
        $policy = $this->decodeJson($odrlRow->policy_json ?? null);

        // Explicit basis hint in either JSON column wins.
        foreach (['policy' => $policy, 'constraints' => $constraints] as $src) {
            if (is_array($src) && isset($src['rightsBasis']) && in_array($src['rightsBasis'], AhgPremisRights::BASES, true)) {
                return $src['rightsBasis'];
            }
        }

        // Donor agreements often surface via the constraints assignee.
        if (is_array($constraints) && (isset($constraints['donor']) || isset($constraints['assigner_type']) && $constraints['assigner_type'] === 'donor')) {
            return 'donor';
        }

        if ($type === 'prohibition') {
            return 'statute';
        }
        if ($type === 'obligation') {
            return 'license';
        }

        // Default: institutional policy.
        return 'policy';
    }

    protected function decodeJson($raw): ?array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    protected function summariseConstraints(string $type, ?array $constraints): ?string
    {
        if (! $constraints) {
            return ucfirst(strtolower($type));
        }
        $bits = [ucfirst(strtolower($type))];
        foreach ($constraints as $k => $v) {
            if (is_scalar($v)) {
                $bits[] = sprintf('%s=%s', $k, (string) $v);
            }
        }
        return implode('; ', $bits);
    }

    protected function extractDates(?array $constraints): array
    {
        if (! $constraints) {
            return [null, null];
        }
        $start = $constraints['dateStart'] ?? $constraints['start_date'] ?? null;
        $end   = $constraints['dateEnd']   ?? $constraints['end_date']   ?? null;
        $start = $start ? substr((string) $start, 0, 10) : null;
        $end   = $end   ? substr((string) $end,   0, 10) : null;
        return [$start, $end];
    }

    protected function renderOdrlSnippet(object $row): string
    {
        return json_encode([
            'odrl_policy_id' => $row->id ?? null,
            'policy_type'    => $row->policy_type ?? null,
            'action_type'    => $row->action_type ?? null,
            'constraints'    => $this->decodeJson($row->constraints_json ?? null),
            'policy'         => $this->decodeJson($row->policy_json ?? null),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
