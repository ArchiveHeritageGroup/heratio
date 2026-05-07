<?php

/**
 * RepositoryQuotaService - Heratio
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

namespace AhgCore\Services;

use AhgCore\Support\GlobalSettings;
use Illuminate\Support\Facades\DB;

/**
 * Per-repository upload-quota gate. Closes #115.
 *
 * Pattern: every upload entry point (DigitalObjectController::upload and
 * ::bulkUpload, IngestService::ingestFile, ConditionService::uploadPhoto)
 * calls canAccept($repoId, $proposedBytes) BEFORE persisting the file.
 * When false, the caller surfaces a friendly "quota reached" error.
 *
 * Cheap-when-disabled: when GlobalSettings::enableRepositoryQuotas() is off
 * (the default), canAccept() returns true without running the SUM query, so
 * sites that bolt the gate in pay zero cost in the common case.
 */
class RepositoryQuotaService
{
    /**
     * Returns true when a $proposedBytes upload should be allowed under
     * repository $repoId. Returns false only when the repository quota
     * would be exceeded.
     *
     * Special cases:
     *   - master gate off  -> true (no enforcement)
     *   - quota = -1 (GB)  -> true (unlimited)
     *   - quota =  0 (GB)  -> false for any positive proposedBytes
     *                          (the form's own help text reads "0 disables
     *                          file upload")
     *   - $repoId = null|0 -> true (orphan uploads aren't quota-counted -
     *                          they aren't attached to any repository)
     */
    public static function canAccept(?int $repoId, int $proposedBytes): bool
    {
        if (!GlobalSettings::enableRepositoryQuotas()) {
            return true;
        }
        if (!$repoId) {
            return true;
        }

        $cap = GlobalSettings::repositoryQuotaBytes();
        if ($cap === null) {
            return true; // unlimited
        }
        if ($cap === 0) {
            // Form's "0 = disables uploads" rule. Refuse anything > 0 bytes;
            // a 0-byte upload (improbable but possible) is allowed since it
            // wouldn't consume any quota.
            return $proposedBytes <= 0;
        }

        return (self::currentUsageBytes($repoId) + max(0, $proposedBytes)) <= $cap;
    }

    /**
     * Sum of digital_object.byte_size for every digital object whose owning
     * information_object lives under $repoId. Includes every usage tier
     * (master + reference + thumbnail + transcript etc.) since every byte
     * on disk counts toward the cap.
     *
     * Returns 0 when the join finds nothing (fresh repo, deleted records).
     */
    public static function currentUsageBytes(int $repoId): int
    {
        return (int) DB::table('digital_object as do')
            ->join('information_object as io', 'do.information_object_id', '=', 'io.id')
            ->where('io.repository_id', $repoId)
            ->sum('do.byte_size');
    }

    /**
     * Operator-facing error string for a refused upload. Surfaces the cap +
     * the current usage so the operator knows how much room is left.
     */
    public static function rejectionMessage(int $repoId, int $proposedBytes): string
    {
        $capGb = GlobalSettings::repositoryQuotaGb();
        if ($capGb === 0.0) {
            return 'Repository quota is set to 0 GB - file uploads are disabled for this repository.';
        }
        $usedGb = self::currentUsageBytes($repoId) / 1073741824;
        $proposedGb = $proposedBytes / 1073741824;
        return sprintf(
            'Repository quota reached. Cap: %.2f GB. In use: %.2f GB. This upload (%.2f GB) would exceed the cap. Operator: raise the cap on /admin/ahgSettings/uploads or remove existing files.',
            $capGb,
            $usedGb,
            $proposedGb,
        );
    }
}
