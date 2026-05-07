<?php

/**
 * OverrideService - Service for Heratio
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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Records reviewer corrections to AI inferences as new record events.
 *
 * Issue #61 / ADR-0002 Phase 3b. The original inference triple is NEVER
 * overwritten. When a reviewer changes a field that was AI-suggested, this
 * service:
 *   1. Writes a row to ahg_ai_override (FK -> ahg_ai_inference) capturing
 *      reviewer + reason + before/after values + lifecycle status.
 *   2. Writes a reified prov:Activity into Fuseki (Shape B per ADR-0002
 *      sec 2 - PROV-O reification, NOT RDF-Star meta-assertion, because
 *      auditors read PROV-O activities more naturally than turtle-star).
 *
 * Lookup helpers:
 *   - findLatestInferenceForField(): used by the entity edit form hook
 *     (Phase 3c) to discover whether the field being edited has a recent
 *     AI inference attached.
 *   - listForInference(): used by the trace endpoint (Phase 4) to assemble
 *     the override chain for a given inference.
 */
class OverrideService
{
    /**
     * Record a reviewer override on an inference.
     *
     * Returns ['id' => int, 'uuid' => string, 'created' => bool]. The
     * 'created' flag is false when the same reviewer applies the same
     * override_value to the same inference in the same minute - we
     * collapse duplicates rather than re-insert (idempotency).
     *
     * @return array{id:int,uuid:string,created:bool}
     */
    public function record(
        int $inferenceId,
        string $originalValue,
        string $overrideValue,
        int $reviewerUserId,
        ?string $reason = null
    ): array {
        // Idempotency window: same inference + same override_value applied
        // within the last 60s (e.g. user clicks Save twice). Deduplicate
        // before inserting.
        $existing = DB::table('ahg_ai_override')
            ->where('inference_id', $inferenceId)
            ->where('reviewer_user_id', $reviewerUserId)
            ->where('override_value', $overrideValue)
            ->where('created_at', '>=', now()->subMinute())
            ->orderByDesc('id')
            ->first();
        if ($existing) {
            return ['id' => (int) $existing->id, 'uuid' => $existing->uuid, 'created' => false];
        }

        $uuid = (string) Str::uuid();
        $now = now();
        $id = DB::table('ahg_ai_override')->insertGetId([
            'uuid'                => $uuid,
            'inference_id'        => $inferenceId,
            'reviewer_user_id'    => $reviewerUserId,
            'reason'              => $reason,
            'original_value'      => $originalValue,
            'override_value'      => $overrideValue,
            'status'              => 'applied',
            'fuseki_override_uri' => null,
            'occurred_at'         => $now,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        $this->writeProvActivity($id, $uuid, $inferenceId, $originalValue, $overrideValue, $reviewerUserId, $reason);

        return ['id' => $id, 'uuid' => $uuid, 'created' => true];
    }

    /**
     * Bulk-detect overrides from a form submission.
     *
     * Each entity edit controller passes:
     *   - $entityType, $entityId  (e.g. 'information_object', 12345)
     *   - $before  field-key => previous-value map (snapshot from a SELECT
     *               before the update runs)
     *   - $after   field-key => new-value map (typically from $request->only())
     *   - $reviewerUserId  the user who clicked Save
     *
     * For every field where (a) before !== after, (b) the field has at
     * least one inference, and (c) the new value differs from the
     * inference's output_excerpt (or the original_value snapshot), an
     * override row is created. Returns count of overrides recorded.
     *
     * Per ADR-0002 the original triple is never overwritten - we only
     * record the correction event. The actual MySQL field write is
     * done by the caller as before; this helper is purely additive.
     *
     * Field-name suffix: callers may pass e.g. 'history@af' to match
     * inferences recorded with that compound target_field. When present,
     * we look up against the suffixed key directly.
     */
    public function detectOverridesFromForm(
        string $entityType,
        int $entityId,
        array $before,
        array $after,
        int $reviewerUserId
    ): int {
        $count = 0;
        foreach ($after as $field => $newValue) {
            $newValue = (string) ($newValue ?? '');
            $oldValue = (string) ($before[$field] ?? '');
            if ($newValue === $oldValue) {
                continue;
            }
            $inference = $this->findLatestInferenceForField($entityType, $entityId, $field);
            if (!$inference) {
                continue;
            }
            // Reviewer changed an AI-touched field. Record the override.
            // original_value is the snapshot we had before this save (which
            // may already be the AI's output OR a previous reviewer's
            // override - the chain is preserved through inference_id FK).
            $this->record(
                inferenceId:    (int) $inference->id,
                originalValue:  $oldValue,
                overrideValue:  $newValue,
                reviewerUserId: $reviewerUserId,
                reason:         null,
            );
            $count++;
        }
        return $count;
    }

    /**
     * Find the most-recent inference targeting a specific (entity, field).
     * Returns null when no inference exists or the field is not AI-touched.
     *
     * Used by the entity edit form hook to decide "should I create an
     * override row?" - if this returns null, the user's change is a plain
     * manual edit (not an override of any AI suggestion).
     */
    public function findLatestInferenceForField(string $entityType, int $entityId, string $field): ?object
    {
        return DB::table('ahg_ai_inference')
            ->where('target_entity_type', $entityType)
            ->where('target_entity_id', $entityId)
            ->where('target_field', $field)
            ->orderByDesc('occurred_at')
            ->first() ?: null;
    }

    /**
     * All overrides recorded against a given inference, oldest first.
     * Used by the trace endpoint (Phase 4) to assemble the lineage view.
     */
    public function listForInference(int $inferenceId): array
    {
        return DB::table('ahg_ai_override')
            ->where('inference_id', $inferenceId)
            ->orderBy('occurred_at')
            ->get()
            ->all();
    }

    /**
     * Build and post the reified PROV-O Activity for the override.
     *
     * Shape B from ADR-0002 sec 2 (verbose, FOIA-officer-legible):
     *   :override a prov:Activity ;
     *       prov:used :inference ;
     *       prov:wasAssociatedWith :user ;
     *       prov:atTime "..."^^xsd:dateTime ;
     *       ex:originalValue "..." ;
     *       ex:newValue "..." ;
     *       ex:reason "..." .
     *
     * On success: UPDATE ahg_ai_override SET fuseki_override_uri = <uri>.
     * On failure: log + leave NULL for the replay job (same pattern as
     * InferenceService::writeRdfStarAnnotation in Phase 3a).
     */
    protected function writeProvActivity(
        int $overrideId,
        string $overrideUuid,
        int $inferenceId,
        string $originalValue,
        string $overrideValue,
        int $reviewerUserId,
        ?string $reason
    ): void {
        try {
            $inference = DB::table('ahg_ai_inference')->where('id', $inferenceId)->first();
            if (!$inference) {
                Log::warning('[ahg-provenance-ai] override write skipped: inference id not found', [
                    'override_id' => $overrideId,
                    'inference_id' => $inferenceId,
                ]);
                return;
            }

            $tenant   = config('heratio.ld.tenant', 'ahg');
            $graphUri = "urn:{$tenant}:provenance-ai:override:" . $overrideUuid;
            $turtle   = $this->buildOverrideTurtle($overrideUuid, $inference->uuid, $originalValue, $overrideValue, $reviewerUserId, $reason);

            $upd    = app(\AhgRic\Services\SparqlUpdateService::class);
            $result = $upd->insertData($graphUri, $turtle);

            if (!empty($result['ok'])) {
                DB::table('ahg_ai_override')->where('id', $overrideId)
                    ->update(['fuseki_override_uri' => $graphUri]);
            } else {
                Log::warning('[ahg-provenance-ai] Fuseki override write deferred for replay', [
                    'override_id' => $overrideId,
                    'http_status' => $result['status'] ?? null,
                    'error'       => $result['error']  ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-provenance-ai] Fuseki override write threw, queued for replay: ' . $e->getMessage(), [
                'override_id' => $overrideId,
            ]);
        }
    }

    protected function buildOverrideTurtle(
        string $overrideUuid,
        string $inferenceUuid,
        string $originalValue,
        string $overrideValue,
        int $reviewerUserId,
        ?string $reason
    ): string {
        $tenant = config('heratio.ld.tenant', 'ahg');
        $provNs = config('heratio.ld.provenance_ns');
        $prefixes = "@prefix prov: <http://www.w3.org/ns/prov#> .\n"
                  . "@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .\n"
                  . "@prefix ex: <{$provNs}> .\n";

        $override  = "<urn:{$tenant}:provenance-ai:override:{$overrideUuid}>";
        $inference = "<urn:{$tenant}:provenance-ai:inference:{$inferenceUuid}>";
        $user      = "<urn:{$tenant}:user:{$reviewerUserId}>";
        $when      = now()->toIso8601ZuluString();

        $body = $prefixes
              . "{$override} a prov:Activity ;\n"
              . "    prov:used {$inference} ;\n"
              . "    prov:wasAssociatedWith {$user} ;\n"
              . "    prov:atTime \"{$when}\"^^xsd:dateTime ;\n"
              . "    ex:originalValue \"" . $this->esc($originalValue) . "\" ;\n"
              . "    ex:newValue \"" . $this->esc($overrideValue) . "\" ;\n"
              . ($reason !== null && $reason !== ''
                  ? "    ex:reason \"" . $this->esc($reason) . "\" ;\n"
                  : '')
              . "    ex:status \"applied\" .\n";

        return $body;
    }

    protected function esc(string $s): string
    {
        return strtr($s, [
            '\\' => '\\\\',
            '"'  => '\\"',
            "\n" => '\\n',
            "\r" => '\\r',
        ]);
    }
}
