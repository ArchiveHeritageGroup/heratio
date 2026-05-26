<?php
/**
 * CostService - record per-call inference cost.
 *
 * Issue #667 Phase 1.
 *
 * Each AI service call site logs one ahg_ai_call_cost row. Costs are
 * computed from ahg_ai_pricing using the published per-1k-token rate; when
 * tokens are unknown (e.g. HTR / Donut endpoints that bill per page) we
 * record duration_ms only and leave cost_usd NULL.
 *
 * Cross-links to the inference-receipt chain via the same X-Request-Id
 * header used by AhgAiCompliance\Services\InferenceLogger.
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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class CostService
{
    public function __construct(private QuotaService $quotaService)
    {
    }

    /**
     * Record one inference call.
     *
     * @param array{tokens_in?:int,tokens_out?:int,duration_ms?:int,request_id?:string|null,tenant_id?:int} $meta
     */
    public function record(string $service, string $modelId, array $meta = []): void
    {
        try {
            if (!Schema::hasTable('ahg_ai_call_cost')) {
                return;
            }

            $tokensIn  = (int) ($meta['tokens_in']  ?? 0);
            $tokensOut = (int) ($meta['tokens_out'] ?? 0);
            $tenantId  = (int) ($meta['tenant_id']  ?? $this->quotaService->resolveTenantId());

            $cost = $this->lookupCost($modelId, $tokensIn, $tokensOut);

            DB::table('ahg_ai_call_cost')->insert([
                'tenant_id'   => $tenantId,
                'service'     => $service,
                'model_id'    => $modelId,
                'tokens_in'   => $tokensIn,
                'tokens_out'  => $tokensOut,
                'cost_usd'    => $cost,
                'duration_ms' => $meta['duration_ms'] ?? null,
                'request_id'  => $meta['request_id'] ?? $this->currentRequestId(),
                'called_at'   => now(),
            ]);
        } catch (Throwable $e) {
            // Cost logging must never block inference.
            Log::warning('[ahg-ai] CostService::record failed soft: ' . $e->getMessage());
        }
    }

    /**
     * Sum cost over a window. Used by the admin dashboard.
     *
     * @return array{total_usd:float,calls:int,tokens_in:int,tokens_out:int}
     */
    public function totals(?int $tenantId = null, ?string $service = null, ?string $since = null): array
    {
        try {
            if (!Schema::hasTable('ahg_ai_call_cost')) {
                return ['total_usd' => 0.0, 'calls' => 0, 'tokens_in' => 0, 'tokens_out' => 0];
            }
            $q = DB::table('ahg_ai_call_cost');
            if ($tenantId !== null) { $q->where('tenant_id', $tenantId); }
            if ($service  !== null) { $q->where('service',   $service); }
            if ($since    !== null) { $q->where('called_at', '>=', $since); }
            $row = $q->selectRaw('
                COALESCE(SUM(cost_usd),0) AS total_usd,
                COUNT(*) AS calls,
                COALESCE(SUM(tokens_in),0) AS tokens_in,
                COALESCE(SUM(tokens_out),0) AS tokens_out
            ')->first();
            return [
                'total_usd' => (float) ($row->total_usd ?? 0),
                'calls'     => (int) ($row->calls ?? 0),
                'tokens_in' => (int) ($row->tokens_in ?? 0),
                'tokens_out'=> (int) ($row->tokens_out ?? 0),
            ];
        } catch (Throwable) {
            return ['total_usd' => 0.0, 'calls' => 0, 'tokens_in' => 0, 'tokens_out' => 0];
        }
    }

    /**
     * Look up cost in USD for a model + token count. Returns null when
     * pricing is unknown (e.g. a brand-new gateway model not yet seeded).
     */
    public function lookupCost(string $modelId, int $tokensIn, int $tokensOut): ?float
    {
        try {
            $row = DB::table('ahg_ai_pricing')->where('model_id', $modelId)->first();
            if ($row === null) {
                return null;
            }
            $in  = (float) $row->input_cost_per_1k_tokens;
            $out = (float) $row->output_cost_per_1k_tokens;
            return round(($tokensIn / 1000.0) * $in + ($tokensOut / 1000.0) * $out, 6);
        } catch (Throwable) {
            return null;
        }
    }

    private function currentRequestId(): ?string
    {
        try {
            if (function_exists('app') && app()->bound('request')) {
                $req = app('request');
                if ($req !== null) {
                    $rid = $req->header('X-Request-Id') ?: $req->attributes->get('request_id');
                    return $rid !== null ? (string) $rid : null;
                }
            }
        } catch (Throwable) {
            // ignore
        }
        return null;
    }
}
