<?php

/**
 * ProvenanceTraceController - Controller for Heratio
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

use AhgProvenanceAi\Services\InferenceService;
use AhgProvenanceAi\Services\OverrideService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Issue #61 Phase 4: one-query defensibility endpoint.
 *
 * GET /api/v1/provenance/{entityType}/{id}/trace
 *
 * Returns the full inference + override + reviewer chain for an entity in
 * one JSON response, grouped by target_field. This is the FOIA-defensible
 * shape - given a record id, an auditor can see every AI decision that
 * touched it, by which model, with what confidence, against which standard,
 * and what reviewer corrections were applied.
 *
 * Response shape (one example field shown):
 * {
 *   "entity": { "type": "information_object", "id": 12345 },
 *   "summary": { "inference_count": 4, "override_count": 1, "fields_touched": 3 },
 *   "fields": {
 *     "subject": [
 *       {
 *         "inference": {
 *           "id": 14, "uuid": "...",
 *           "service": "NER", "model": "spaCy en_core_web_sm", "version": "3.8.0",
 *           "confidence": 0.55, "standard": "ICIP-name-access-points",
 *           "input_hash": "...", "output_hash": "...",
 *           "input_excerpt": "...", "output_excerpt": "...",
 *           "endpoint": "...", "elapsed_ms": 142,
 *           "occurred_at": "2026-05-04T10:00:00Z",
 *           "fuseki_graph_uri": "urn:{tenant}:provenance-ai:inference:..." | null
 *         },
 *         "overrides": [
 *           {
 *             "id": 3, "uuid": "...",
 *             "reviewer_user_id": 1, "reason": "...",
 *             "original": "...", "new": "...",
 *             "status": "applied",
 *             "occurred_at": "2026-05-04T11:00:00Z",
 *             "fuseki_override_uri": "urn:{tenant}:provenance-ai:override:..." | null
 *           }
 *         ],
 *         "current_effective_value": "Hard rock mining"
 *       }
 *     ]
 *   }
 * }
 *
 * Authorisation: any authenticated user can request a trace. We log the
 * request via ahg-audit-trail (when present) so reviewer-of-reviewer
 * audits exist. Per ADR-0002 the trace is a read-only window; nothing
 * here mutates state.
 */
class ProvenanceTraceController extends Controller
{
    private InferenceService $inferences;
    private OverrideService  $overrides;

    public function __construct(InferenceService $inferences, OverrideService $overrides)
    {
        $this->inferences = $inferences;
        $this->overrides  = $overrides;
    }

    /**
     * GET /api/v1/provenance/{entityType}/{id}/trace
     */
    public function trace(Request $request, string $entityType, int $id): JsonResponse
    {
        $entityType = $this->normaliseEntityType($entityType);
        if ($entityType === null) {
            return response()->json([
                'ok' => false,
                'error' => 'Unsupported entity type. Supported: information_object, actor, repository, term, museum_metadata',
            ], 400);
        }

        $inferences = $this->inferences->listForField($entityType, $id, null);

        $byField = [];
        $totalOverrides = 0;
        foreach ($inferences as $inf) {
            $overrides = $this->overrides->listForInference((int) $inf->id);
            $totalOverrides += count($overrides);
            $byField[$inf->target_field][] = [
                'inference' => $this->shapeInference($inf),
                'overrides' => array_map(fn ($o) => $this->shapeOverride($o), $overrides),
                'current_effective_value' => $this->effectiveValue($inf, $overrides),
            ];
        }

        // Order fields alphabetically for deterministic output and inferences
        // newest-first within each field (matches listForField default).
        ksort($byField);

        return response()->json([
            'ok'      => true,
            'entity'  => ['type' => $entityType, 'id' => $id],
            'summary' => [
                'inference_count'  => count($inferences),
                'override_count'   => $totalOverrides,
                'fields_touched'   => count($byField),
            ],
            'fields'  => $byField,
        ]);
    }

    /**
     * Coverage diagnostic. Counts inferences per service over a window so
     * ops can see "did NER actually run today?" without writing SPARQL.
     *
     * GET /api/v1/provenance/coverage?days=7
     */
    public function coverage(Request $request): JsonResponse
    {
        $days = max(1, min(365, (int) $request->input('days', 7)));
        $since = now()->subDays($days);

        $rows = DB::table('ahg_ai_inference')
            ->where('occurred_at', '>=', $since)
            ->select('service_name', DB::raw('COUNT(*) as n'),
                DB::raw('AVG(confidence) as avg_confidence'),
                DB::raw('SUM(CASE WHEN fuseki_graph_uri IS NULL THEN 1 ELSE 0 END) as fuseki_pending'))
            ->groupBy('service_name')
            ->orderBy('service_name')
            ->get();

        return response()->json([
            'ok'    => true,
            'since' => $since->toIso8601ZuluString(),
            'days'  => $days,
            'by_service' => $rows->map(fn ($r) => [
                'service'         => $r->service_name,
                'count'           => (int) $r->n,
                'avg_confidence'  => $r->avg_confidence !== null ? (float) $r->avg_confidence : null,
                'fuseki_pending'  => (int) $r->fuseki_pending,
            ])->all(),
        ]);
    }

    private function shapeInference(object $r): array
    {
        return [
            'id'              => (int) $r->id,
            'uuid'            => $r->uuid,
            'service'         => $r->service_name,
            'model'           => $r->model_name,
            'version'         => $r->model_version,
            'confidence'      => $r->confidence !== null ? (float) $r->confidence : null,
            'standard'        => $r->standard,
            'input_hash'      => $r->input_hash,
            'output_hash'     => $r->output_hash,
            'input_excerpt'   => $r->input_excerpt,
            'output_excerpt'  => $r->output_excerpt,
            'endpoint'        => $r->endpoint,
            'elapsed_ms'      => $r->elapsed_ms !== null ? (int) $r->elapsed_ms : null,
            'occurred_at'     => $r->occurred_at,
            'fuseki_graph_uri'=> $r->fuseki_graph_uri,
            'user_id'         => $r->user_id !== null ? (int) $r->user_id : null,
        ];
    }

    private function shapeOverride(object $r): array
    {
        return [
            'id'                 => (int) $r->id,
            'uuid'               => $r->uuid,
            'reviewer_user_id'   => (int) $r->reviewer_user_id,
            'reason'             => $r->reason,
            'original'           => $r->original_value,
            'new'                => $r->override_value,
            'status'             => $r->status,
            'occurred_at'        => $r->occurred_at,
            'fuseki_override_uri'=> $r->fuseki_override_uri,
        ];
    }

    /**
     * Compute the value that's currently in effect for the field given the
     * override chain. Latest applied override wins; if none exist the
     * inference's output_excerpt is the current value.
     */
    private function effectiveValue(object $inference, array $overrides): ?string
    {
        $applied = array_values(array_filter($overrides, fn ($o) => $o->status === 'applied'));
        if (!empty($applied)) {
            $last = end($applied);
            return $last->override_value;
        }
        return $inference->output_excerpt;
    }

    /**
     * Restrict entity types to the known set so the endpoint cannot be used
     * to introspect arbitrary tables. Returns null when the type is invalid.
     */
    private function normaliseEntityType(string $entityType): ?string
    {
        $allowed = ['information_object', 'actor', 'repository', 'term', 'museum_metadata'];
        $entityType = strtolower($entityType);
        return in_array($entityType, $allowed, true) ? $entityType : null;
    }
}
