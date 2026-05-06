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

        // Phase 3a: write the canonical RDF-Star annotation to Fuseki.
        $this->writeRdfStarAnnotation($id, $uuid, $r);

        // Phase 3d: if confidence is below the per-service threshold,
        // enqueue a workflow review task. No-op when threshold is unset
        // (NULL confidence cannot be compared) or workflow not configured.
        $this->maybeEnqueueReview($id, $r);

        return ['id' => $id, 'uuid' => $uuid];
    }

    /**
     * If the inference's confidence is below the configured threshold for
     * its service, queue a workflow task for human review (ADR-0002 sec 5).
     *
     * Threshold lookup: `ahg_settings.setting_key = ai_provenance.<service>.confidence_review_threshold`,
     * service name lowercased. Missing setting = no auto-review (rely on
     * ad-hoc reviewer browsing instead).
     *
     * Workflow + step ids: `ahg_settings.setting_key = ai_provenance.review_workflow_id`
     * and `ai_provenance.review_step_id`. When unset, we log + skip (the
     * deployment can configure these later without breaking AI writes).
     */
    protected function maybeEnqueueReview(int $inferenceId, InferenceRecord $r): void
    {
        if ($r->confidence === null) {
            return; // can't compare without a score
        }

        $threshold = $this->setting('ai_provenance.' . strtolower($r->serviceName) . '.confidence_review_threshold');
        if ($threshold === null) {
            return; // not configured; deployment opts in by setting the key
        }
        $threshold = (float) $threshold;
        if ($r->confidence >= $threshold) {
            return; // above threshold = auto-applies, no review needed
        }

        $workflowId = $this->setting('ai_provenance.review_workflow_id');
        $stepId     = $this->setting('ai_provenance.review_step_id');
        if ($workflowId === null || $stepId === null) {
            Log::info('[ahg-provenance-ai] inference below threshold but no review workflow configured', [
                'inference_id' => $inferenceId,
                'service' => $r->serviceName,
                'confidence' => $r->confidence,
                'threshold' => $threshold,
            ]);
            return;
        }

        try {
            DB::table('ahg_workflow_task')->insert([
                'workflow_id'      => (int) $workflowId,
                'workflow_step_id' => (int) $stepId,
                'object_id'        => $r->targetEntityId,
                'object_type'      => $r->targetEntityType,
                'status'           => 'pending',
                'priority'         => 'normal',
                'submitted_by'     => $r->userId ?? 0,
                'metadata'         => json_encode([
                    'kind'            => 'ai_inference_review',
                    'inference_id'    => $inferenceId,
                    'service'         => $r->serviceName,
                    'model'           => $r->modelName,
                    'model_version'   => $r->modelVersion,
                    'confidence'      => $r->confidence,
                    'threshold'       => $threshold,
                    'standard'        => $r->standard,
                    'target_field'    => $r->targetField,
                    'output_excerpt'  => $r->outputExcerpt,
                ], JSON_UNESCAPED_UNICODE),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-provenance-ai] failed to enqueue review task: ' . $e->getMessage(), [
                'inference_id' => $inferenceId,
            ]);
        }
    }

    /**
     * Read an ahg_settings value or return null if absent / empty.
     */
    private function setting(string $key)
    {
        try {
            $v = DB::table('ahg_settings')->where('setting_key', $key)->value('setting_value');
            return ($v !== null && $v !== '') ? $v : null;
        } catch (\Throwable $e) {
            return null;
        }
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
     * Phase 3a: build the RDF-Star annotation for an inference and write it
     * to Fuseki via SparqlUpdateService.
     *
     * Turtle shape (per ADR-0002 sec 2 — RDF-Star meta-assertion on the
     * generated triple):
     *
     *   <<:target :field "<output-hash>">> prov:wasGeneratedBy :inference ;
     *                                       prov:generatedAtTime "..."^^xsd:dateTime ;
     *                                       ex:confidence "..."^^xsd:decimal ;
     *                                       ex:model "spaCy en_core_web_sm 3.8.0" ;
     *                                       ex:standard "ICIP-name-access-points" .
     *
     * On success: UPDATE ahg_ai_inference SET fuseki_graph_uri = <graph-uri>.
     * On failure: log warning, leave fuseki_graph_uri NULL so the future
     * replay job picks the row up (per ADR-0002 sec 1, dual-store with
     * SQL-first then Fuseki-replay).
     */
    protected function writeRdfStarAnnotation(int $inferenceId, string $uuid, InferenceRecord $r): void
    {
        try {
            $graphUri = 'urn:ahg:provenance-ai:inference:' . $uuid;
            $turtle   = $this->buildInferenceTurtle($uuid, $r);

-            $upd    = app(\AhgRic\Services\SparqlUpdateService::class);
-            $result = $upd->insertRdfStar($graphUri, $turtle);
+            // Delegate to FusekiSyncService so settings (enable/queue) are honoured.
+            $sync = app(\AhgRic\Services\FusekiSyncService::class);
+            $result = $sync->insertRdfStar($graphUri, $turtle);

            if (!empty($result['ok'])) {
                DB::table('ahg_ai_inference')->where('id', $inferenceId)
                    ->update(['fuseki_graph_uri' => $graphUri]);
            } else {
                Log::warning('[ahg-provenance-ai] Fuseki RDF-Star write deferred for replay', [
                    'inference_id' => $inferenceId,
                    'uuid'         => $uuid,
                    'http_status'  => $result['status']  ?? null,
                    'error'        => $result['error']   ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            // The SQL row is already committed; Fuseki failure must never
            // poison the AI service's caller. Replay job will retry.
            Log::warning('[ahg-provenance-ai] Fuseki write threw, queued for replay: ' . $e->getMessage(), [
                'inference_id' => $inferenceId,
            ]);
        }
    }

    /**
     * Build the turtle-star body for one inference. Pure-string output
     * (no SPARQL wrapping); SparqlUpdateService::insertRdfStar handles
     * the INSERT DATA + GRAPH wrapping.
     *
     * Prefixes are declared inline so the body is portable to other
     * SPARQL endpoints if the deployment ever splits the store.
     */
