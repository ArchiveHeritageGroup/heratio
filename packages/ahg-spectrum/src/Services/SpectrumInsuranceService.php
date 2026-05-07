<?php

/**
 * SpectrumInsuranceService - Heratio
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
 * Per-object insurance management for #122.
 *
 * Backed by the spectrum_object_insurance table (created in install.sql,
 * lazy-created here for installs upgrading without re-running the SQL).
 * Existing gallery_insurance_policy stays as the institution-level
 * blanket-policy registry; this service handles object-specific coverage.
 */
class SpectrumInsuranceService
{
    public const TABLE = 'spectrum_object_insurance';

    /**
     * Lazily ensure the table exists. Idempotent. Called by every read /
     * write so installs that never re-ran install.sql still work.
     */
    public static function ensureTable(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }
        Schema::create(self::TABLE, function ($t) {
            $t->id();
            $t->integer('object_id');
            $t->string('policy_number', 100);
            $t->string('insurer', 255);
            $t->string('policy_type', 60)->default('all_risk');
            $t->decimal('coverage_amount', 15, 2)->nullable();
            $t->string('currency', 3)->default('USD');
            $t->date('start_date');
            $t->date('end_date');
            $t->boolean('is_active')->default(true);
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->integer('created_by')->nullable();
            $t->index('object_id');
            $t->index(['is_active', 'start_date', 'end_date'], 'idx_active_dates');
        });
    }

    /**
     * True when at least one row exists for this object whose date range
     * covers today AND is_active=1. The strict semantic for #122.
     */
    public static function hasCurrentForObject(int $objectId): bool
    {
        self::ensureTable();
        $today = now()->toDateString();
        return DB::table(self::TABLE)
            ->where('object_id', $objectId)
            ->where('is_active', 1)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->exists();
    }

    /**
     * Every active policy for this object (including future-dated and
     * past). Caller filters by date range as needed.
     */
    public static function listForObject(int $objectId): \Illuminate\Support\Collection
    {
        self::ensureTable();
        return DB::table(self::TABLE)
            ->where('object_id', $objectId)
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * Insert a new per-object policy. Returns the new row's id.
     */
    public static function recordPolicy(int $objectId, array $data, ?int $userId = null): int
    {
        self::ensureTable();
        $payload = array_merge([
            'object_id'       => $objectId,
            'policy_type'     => 'all_risk',
            'currency'        => 'USD',
            'is_active'       => 1,
            'created_at'      => now(),
            'created_by'      => $userId,
        ], $data);

        // Required fields - throw early if missing rather than letting MySQL
        // surface an unhelpful NOT-NULL violation.
        foreach (['policy_number', 'insurer', 'start_date', 'end_date'] as $f) {
            if (empty($payload[$f])) {
                throw new \InvalidArgumentException("recordPolicy: missing required field '{$f}'.");
            }
        }
        if ($payload['end_date'] < $payload['start_date']) {
            throw new \InvalidArgumentException('recordPolicy: end_date must be on or after start_date.');
        }

        $newId = (int) DB::table(self::TABLE)->insertGetId($payload);

        \AhgCore\Support\AuditLog::captureMutation($newId, self::TABLE, 'create', [
            'data' => [
                'object_id' => $objectId,
                'policy_number' => $payload['policy_number'],
                'insurer' => $payload['insurer'],
                'start_date' => $payload['start_date'],
                'end_date' => $payload['end_date'],
            ],
        ]);

        return $newId;
    }

    /**
     * Soft-deactivate a policy (sets is_active=0). Returns true on hit.
     */
    public static function expirePolicy(int $policyId, ?int $userId = null): bool
    {
        self::ensureTable();
        $affected = DB::table(self::TABLE)
            ->where('id', $policyId)
            ->where('is_active', 1)
            ->update(['is_active' => 0]);

        if ($affected > 0) {
            \AhgCore\Support\AuditLog::captureMutation($policyId, self::TABLE, 'expire', [
                'data' => ['expired_by' => $userId],
            ]);
        }

        return $affected > 0;
    }
}
