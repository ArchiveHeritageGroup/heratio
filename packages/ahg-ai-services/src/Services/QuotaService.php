<?php
/**
 * QuotaService - per-tenant daily/monthly quota gate for AI services.
 *
 * Issue #667 Phase 1.
 *
 * The gate runs INSIDE each AI service call site (LlmService::complete,
 * HtrService::extract, NerService::extract, DonutService::extract,
 * LlmService::translate, LlmService::spellcheck, FaceDetector::detect).
 * It does two things in one transaction:
 *
 *   1. resolve the tenant row (auto-seeds the global row on first hit)
 *   2. roll the used_today / used_this_month counters when the calendar
 *      day or reset_day boundary has passed, then check the limit and
 *      either throw QuotaExceededException or increment + return.
 *
 * Tenant resolution uses, in order: explicit `tenant_id` option passed by
 * the caller, `config('ahg.tenant_id')`, the authenticated user's
 * `tenant_id` attribute, otherwise 0 (global default).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.
 */

declare(strict_types=1);

namespace AhgAiServices\Services;

use AhgAiServices\Exceptions\QuotaExceededException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class QuotaService
{
    /** Services that participate in the quota system. */
    public const SERVICES = [
        'llm',
        'ner',
        'htr',
        'donut',
        'translate',
        'spellcheck',
        'face_detect',
    ];

    /**
     * Check + reserve one unit of quota for the given service.
     *
     * Returns silently when the call is allowed (and increments counters).
     * Throws QuotaExceededException when the limit has been reached. Fails
     * soft when the schema does not yet exist (CI / pre-install): logs and
     * lets the call through.
     *
     * @throws QuotaExceededException when daily or monthly limit reached.
     */
    public function consume(string $service, ?int $tenantId = null): void
    {
        if (!in_array($service, self::SERVICES, true)) {
            return; // unknown service - no enforcement
        }

        try {
            if (!Schema::hasTable('ahg_ai_quota')) {
                return;
            }

            $tenantId = $tenantId ?? $this->resolveTenantId();
            $row = $this->loadOrSeed($service, $tenantId);
            $row = $this->rollIfNeeded($row);

            // limit=0 == unlimited
            if ($row->daily_limit > 0 && $row->used_today >= $row->daily_limit) {
                throw new QuotaExceededException(
                    $tenantId,
                    $service,
                    'daily',
                    (int) $row->used_today,
                    (int) $row->daily_limit,
                );
            }
            if ($row->monthly_limit > 0 && $row->used_this_month >= $row->monthly_limit) {
                throw new QuotaExceededException(
                    $tenantId,
                    $service,
                    'monthly',
                    (int) $row->used_this_month,
                    (int) $row->monthly_limit,
                );
            }

            DB::table('ahg_ai_quota')
                ->where('id', $row->id)
                ->update([
                    'used_today'      => DB::raw('used_today + 1'),
                    'used_this_month' => DB::raw('used_this_month + 1'),
                ]);
        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('[ahg-ai] QuotaService::consume failed soft: ' . $e->getMessage());
        }
    }

    /**
     * Resolve current tenant. Mirrors the InferenceLogger logic so the
     * quota ledger, the cost ledger and the receipt chain all agree on
     * tenant identity.
     */
    public function resolveTenantId(): int
    {
        if (function_exists('config')) {
            $explicit = config('ahg.tenant_id');
            if ($explicit !== null) {
                return (int) $explicit;
            }
        }
        try {
            if (Auth::check()) {
                $user = Auth::user();
                $tid = $user->tenant_id ?? null;
                if ($tid !== null) {
                    return (int) $tid;
                }
                // Heratio convention: repository_id is the closest
                // tenant proxy on the staff user model when no explicit
                // tenant column exists.
                $rid = $user->repository_id ?? null;
                if ($rid !== null) {
                    return (int) $rid;
                }
            }
        } catch (Throwable) {
            // unauthenticated request / driver missing - fall through to 0
        }
        return 0;
    }

    /**
     * Get current usage snapshot for an operator dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    public function snapshot(?int $tenantId = null): array
    {
        try {
            if (!Schema::hasTable('ahg_ai_quota')) {
                return [];
            }
            $q = DB::table('ahg_ai_quota');
            if ($tenantId !== null) {
                $q->where('tenant_id', $tenantId);
            }
            return $q->orderBy('tenant_id')->orderBy('service')->get()->map(fn ($r) => (array) $r)->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function loadOrSeed(string $service, int $tenantId): object
    {
        $row = DB::table('ahg_ai_quota')
            ->where('tenant_id', $tenantId)
            ->where('service', $service)
            ->first();
        if ($row !== null) {
            return $row;
        }
        // Auto-seed - the global tenant=0 baseline is created by install.sql,
        // but a brand-new tenant calling for the first time also gets a row.
        DB::table('ahg_ai_quota')->insertOrIgnore([
            'tenant_id'       => $tenantId,
            'service'         => $service,
            'daily_limit'     => 0,
            'monthly_limit'   => 0,
            'used_today'      => 0,
            'used_this_month' => 0,
            'reset_day'       => 1,
            'last_reset_at'   => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        return (object) DB::table('ahg_ai_quota')
            ->where('tenant_id', $tenantId)
            ->where('service', $service)
            ->first(['id', 'tenant_id', 'service', 'daily_limit', 'monthly_limit', 'used_today', 'used_this_month', 'reset_day', 'last_reset_at']);
    }

    /**
     * Roll daily + monthly counters when the wall clock has crossed the
     * matching boundary since last_reset_at. Returns the (possibly
     * updated) row.
     */
    private function rollIfNeeded(object $row): object
    {
        $now = now();
        $last = $row->last_reset_at ? \Carbon\Carbon::parse($row->last_reset_at) : null;
        $changed = [];

        // Daily roll - any wall-clock day change zeroes used_today.
        if ($last === null || !$last->isSameDay($now)) {
            $changed['used_today'] = 0;
        }

        // Monthly roll - when reset_day = 1 we use calendar months;
        // otherwise we anchor the boundary to that day-of-month.
        $resetDay = max(1, (int) ($row->reset_day ?? 1));
        $monthRolled = false;
        if ($last === null) {
            $monthRolled = true;
        } else {
            $anchorThis = $now->copy()->startOfMonth()->addDays($resetDay - 1);
            if ($now->lessThan($anchorThis)) {
                $anchorThis->subMonth();
            }
            if ($last->lessThan($anchorThis)) {
                $monthRolled = true;
            }
        }
        if ($monthRolled) {
            $changed['used_this_month'] = 0;
        }

        if (!empty($changed)) {
            $changed['last_reset_at'] = $now;
            DB::table('ahg_ai_quota')->where('id', $row->id)->update($changed);
            foreach ($changed as $k => $v) {
                $row->{$k} = $v;
            }
        }

        return $row;
    }
}
