<?php

/**
 * InferenceService - Service for Heratio
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

namespace AhgProvenanceAi\Services;

use AhgProvenanceAi\DTO\InferenceRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Single entry point every AI service must use to record an inference.
 *
 * The MySQL row is the source of truth for the operational store
 * (filtering, dashboards, review queues). The Fuseki RDF-Star annotation
 * is the canonical defensible semantic record. We write SQL first so an
 * inference is never lost - if Fuseki is down, the row still lands and
 * gets replayed (a future cron picks up rows with fuseki_graph_uri IS NULL
 * and retries the insert).
 *
 * See ADR-0002 for design rationale.
 */
class InferenceService
{
    /**
     * Persist an inference. Returns [id, uuid].
     *
     * Phase 1: writes the SQL row only. The Fuseki RDF-Star write is wired
     * in Phase 1c via SparqlUpdateService and called from here once that
     * service is available; until then, rows are written with
     * fuseki_graph_uri = NULL and a future replay job catches up.
     *
     * @return array{id:int,uuid:string}
     */
    public function record(InferenceRecord $r): array
    {
        $uuid = (string) Str::uuid();
        $now = now();

        $id = DB::table('ahg_ai_inference')->insertGetId([
            'uuid'                => $uuid,
            'service_name'        => $r->serviceName,
            'model_name'          => $r->modelName,
            'model_version'       => $r->modelVersion,
            'endpoint'            => $r->endpoint,
            'input_hash'          => $r->inputHash,
            'input_excerpt'       => $r->inputExcerpt,
            'output_hash'         => $r->outputHash,
            'output_excerpt'      => $r->outputExcerpt,
            'confidence'          => $r->confidence,
            'standard'            => $r->standard,
            'target_entity_type'  => $r->targetEntityType,
            'target_entity_id'    => $r->targetEntityId,
            'target_field'        => $r->targetField,
            'elapsed_ms'          => $r->elapsedMs,
            'fuseki_graph_uri'    => null,
            'user_id'             => $r->userId,
            'occurred_at'         => $now,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        // Phase 1c hook-point: enqueue the Fuseki RDF-Star insert.
        // Implementation lands when SparqlUpdateService is wired; see ADR-0002 sec 7.
        $this->writeRdfStarAnnotation($id, $uuid, $r);

        return ['id' => $id, 'uuid' => $uuid];
    }

    /**
     * Look up an inference by uuid. Returns null when not found.
     */
    public function findByUuid(string $uuid): ?object
    {
        return DB::table('ahg_ai_inference')->where('uuid', $uuid)->first() ?: null;
    }

    /**
     * All inferences targeting a specific entity field, newest first.
     *
     * Used by the trace endpoint (Phase 4) to assemble the per-field
     * inference + override chain for a record.
     */
    public function listForField(string $entityType, int $entityId, ?string $field = null): array
    {
        $q = DB::table('ahg_ai_inference')
            ->where('target_entity_type', $entityType)
            ->where('target_entity_id', $entityId);
        if ($field !== null) {
            $q->where('target_field', $field);
        }
        return $q->orderByDesc('occurred_at')->get()->all();
    }

    /**
     * Phase 1 stub for the Fuseki write half of the contract.
     *
     * Once SparqlUpdateService lands in ahg-ric, this method will:
     *   - build the RDF-Star turtle for the inference (per ADR-0002 sec 2)
     *   - call SparqlUpdateService::insertRdfStar($graph, $turtle)
     *   - on success, UPDATE ahg_ai_inference SET fuseki_graph_uri = $graph
     *
     * For Phase 1, this is a no-op so the SQL half ships standalone and
     * the AI services can start logging today. The replay job (separate
     * Phase 1.5 task) will back-fill Fuseki once the update endpoint is wired.
     */
    protected function writeRdfStarAnnotation(int $inferenceId, string $uuid, InferenceRecord $r): void
    {
        // Intentionally empty in Phase 1. Implemented in Phase 1c follow-up.
        // Logging at debug level so ops can confirm the call site fires.
        if (config('app.debug')) {
            Log::debug('[ahg-provenance-ai] inference recorded (Fuseki write deferred)', [
                'inference_id' => $inferenceId,
                'uuid'         => $uuid,
                'service'      => $r->serviceName,
                'target'       => "{$r->targetEntityType}/{$r->targetEntityId}/{$r->targetField}",
            ]);
        }
    }
}
