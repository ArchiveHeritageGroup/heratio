<?php

/**
 * SpectrumPublishGuardService - Heratio
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

namespace AhgSpectrum\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Returns the list of spectrum-side rules that block publishing an object
 * to the public catalogue. Closes #121 (spectrum_require_valuation) and
 * #122 (spectrum_require_insurance) by giving InformationObjectService a
 * single hook to call before transitioning publication_status to PUBLISHED.
 *
 * Empty result = OK to publish. Non-empty = each entry is an
 * operator-readable reason that should be surfaced as a flash error /
 * validation failure (caller's choice).
 *
 * Caller pattern:
 *
 *   $reasons = (new SpectrumPublishGuardService())->canPublish($objectId);
 *   if (!empty($reasons)) {
 *       throw new \DomainException(implode("\n", $reasons));
 *   }
 *
 * Cheap-when-disabled: when neither setting is on, returns an empty array
 * without running any DB lookups.
 */
class SpectrumPublishGuardService
{
    /**
     * @return string[] Blocking reasons; empty = no spectrum-side blockers.
     */
    public function canPublish(int $objectId): array
    {
        $settings = new SpectrumSettings();
        $reasons = [];

        if ($settings->requireValuation()) {
            if (!$this->hasCurrentValuation($objectId)) {
                $reasons[] = 'A current valuation is required before publishing this object '
                    . '(spectrum_require_valuation is enabled on /admin/ahgSettings/spectrum). '
                    . 'Add a valuation row with is_current=1 and try again.';
            }
        }

        if ($settings->requireInsurance()) {
            if (!$this->hasActiveInsurance($objectId)) {
                $reasons[] = 'An active insurance policy is required before publishing this object '
                    . '(spectrum_require_insurance is enabled on /admin/ahgSettings/spectrum). '
                    . 'Record a per-object policy via SpectrumInsuranceService::recordPolicy() with '
                    . 'start_date <= today <= end_date AND is_active=1.';
            }
        }

        return $reasons;
    }

    /**
     * True when spectrum_valuation has at least one row for this object
     * marked is_current=1. The table is the canonical per-object valuation
     * store (audit/cataloguing-managed).
     */
    private function hasCurrentValuation(int $objectId): bool
    {
        if (!Schema::hasTable('spectrum_valuation')) {
            return false;
        }
        return DB::table('spectrum_valuation')
            ->where('object_id', $objectId)
            ->where('is_current', 1)
            ->exists();
    }

    /**
     * Per-object insurance check via spectrum_object_insurance (lazily
     * created by SpectrumInsuranceService on first read). Strict semantic:
     * the object itself must have at least one active row whose date range
     * covers today.
     *
     * The earlier loose institution-level fallback against
     * gallery_insurance_policy is gone - per-object enforcement is now
     * the only contract, matching what spectrum_require_insurance is
     * supposed to mean.
     */
    private function hasActiveInsurance(int $objectId): bool
    {
        return SpectrumInsuranceService::hasCurrentForObject($objectId);
    }
}
