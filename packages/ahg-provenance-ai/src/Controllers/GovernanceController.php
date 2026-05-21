<?php

/**
 * GovernanceController - Controller for Heratio
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

namespace AhgProvenanceAi\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * AI Inventory & Governance dashboard (heratio#137).
 *
 * Operator-facing visibility into the configured LLMs (ahg_llm_config) and
 * recent AI inference activity (ahg_ai_inference). The page is server-rendered;
 * the JSON endpoints back future JS enhancements.
 *
 * Two columns degrade gracefully until their sibling issues land:
 *   - model_manifest summary -> heratio#135 (no model_manifest column yet)
 *   - signed boolean         -> heratio#136 (Ed25519 signing not yet wired)
 */
class GovernanceController extends Controller
{
    /** Recent-inference rows shown on the dashboard / returned by the API. */
    private const RECENT_LIMIT = 50;

    /**
     * GET /admin/governance - the dashboard page.
     */
    public function index()
    {
        return view('ahg-provenance-ai::governance.index', [
            'stats'      => $this->stats(),
            'models'     => $this->modelRows(),
            'inferences' => $this->inferenceRows(self::RECENT_LIMIT),
        ]);
    }

    /**
     * GET /admin/governance/models - LLM configs as JSON.
     */
    public function models(): JsonResponse
    {
        return response()->json(['data' => $this->modelRows()]);
    }

    /**
     * GET /admin/governance/inferences - recent inferences as JSON.
     */
    public function inferences(): JsonResponse
    {
        return response()->json(['data' => $this->inferenceRows(self::RECENT_LIMIT)]);
    }

    /* ------------------------------------------------------------------ */

    /**
     * Configured LLMs. api_key_encrypted is deliberately never selected -
     * the dashboard must not surface secrets. last_used is best-effort:
     * ahg_ai_inference has no FK to ahg_llm_config, so it is matched on
     * model name.
     */
    private function modelRows(): array
    {
        return DB::table('ahg_llm_config')
            ->orderByDesc('is_default')
            ->orderBy('provider')
            ->orderBy('name')
            ->get([
                'id', 'provider', 'name', 'model', 'is_active', 'is_default',
                'endpoint_url', 'max_tokens', 'temperature', 'timeout_seconds',
                'created_at', 'updated_at',
            ])
            ->map(function ($row) {
                $row->last_used = DB::table('ahg_ai_inference')
                    ->where('model_name', $row->model)
                    ->max('occurred_at');
                $row->inference_count = DB::table('ahg_ai_inference')
                    ->where('model_name', $row->model)
                    ->count();
                // heratio#135 - model_manifest column does not exist yet.
                $row->model_manifest = null;
                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * Most-recent inference records. input/output excerpts and hashes are
     * omitted - the dashboard is a summary, the full record lives behind
     * the provenance trace endpoint.
     */
    private function inferenceRows(int $limit): array
    {
        return DB::table('ahg_ai_inference')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id', 'uuid', 'service_name', 'model_name', 'model_version',
                'confidence', 'standard', 'target_entity_type', 'target_entity_id',
                'target_field', 'elapsed_ms', 'occurred_at', 'signer_key_id',
            ])
            ->map(function ($row) {
                // heratio#136 - signed once an Ed25519 signature was recorded.
                $row->signed = !empty($row->signer_key_id);
                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * Headline counts for the dashboard stat cards.
     */
    private function stats(): array
    {
        return [
            'models_total'     => (int) DB::table('ahg_llm_config')->count(),
            'models_active'    => (int) DB::table('ahg_llm_config')->where('is_active', 1)->count(),
            'inferences_total' => (int) DB::table('ahg_ai_inference')->count(),
            'inferences_7d'    => (int) DB::table('ahg_ai_inference')
                ->where('occurred_at', '>=', now()->subDays(7))
                ->count(),
            'avg_confidence'   => DB::table('ahg_ai_inference')
                ->whereNotNull('confidence')
                ->avg('confidence'),
        ];
    }
}
