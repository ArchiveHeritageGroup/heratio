<?php

/**
 * FusekiReplayCommand - Heratio
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

namespace AhgProvenanceAi\Console\Commands;

use AhgProvenanceAi\DTO\InferenceRecord;
use AhgProvenanceAi\Services\InferenceService;
use AhgRic\Services\FusekiSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Closes #62. ADR-0002 sec 1: dual-store with SQL-first then Fuseki-replay.
 * Inference + override rows that fail the synchronous Fuseki write at
 * record() time persist with `fuseki_graph_uri` (or `fuseki_override_uri`)
 * NULL. This command picks them up and retries the Fuseki INSERT.
 *
 * Two passes:
 *   1. ahg_ai_inference WHERE fuseki_graph_uri IS NULL
 *   2. ahg_ai_override  WHERE fuseki_override_uri IS NULL
 *
 * Idempotent: each row is uniquely identified by its uuid; the graph URI
 * derived from the uuid is the same across retries, so an INSERT DATA
 * that already landed in Fuseki is a no-op there. We update the local
 * URI column on success.
 *
 * Cron: every 5 minutes is the recommended cadence per the issue body.
 * The schedule registration in AhgProvenanceAiServiceProvider self-gates
 * on ahg_settings.fuseki_sync_enabled so flipping the toggle off
 * silences the replay loop without needing an artisan re-cache.
 */
class FusekiReplayCommand extends Command
{
    protected $signature = 'ahg:provenance-ai:replay
                            {--batch=200 : max rows to attempt per run}
                            {--dry-run : count pending without writing to Fuseki}';
    protected $description = 'Replay queued AI inference + override Fuseki writes (rows with NULL URIs).';

    public function handle(InferenceService $inference, FusekiSyncService $sync): int
    {
        if (!Schema::hasTable('ahg_ai_inference')) {
            $this->warn('ahg_ai_inference table missing - nothing to replay.');
            return self::SUCCESS;
        }

        $batch = max(1, (int) $this->option('batch'));
        $dry = (bool) $this->option('dry-run');

        $infReplayed = 0;
        $infFailed = 0;
        $ovReplayed = 0;
        $ovFailed = 0;

        // ── Pass 1: inference rows ────────────────────────────────────
        $pending = DB::table('ahg_ai_inference')
            ->whereNull('fuseki_graph_uri')
            ->orderBy('id')
            ->limit($batch)
            ->get();

        $this->line(sprintf('[provenance-ai-replay] inference pending=%d (batch=%d)', $pending->count(), $batch));

        foreach ($pending as $row) {
            if ($dry) { $infReplayed++; continue; }
            try {
                $r = $this->rowToInferenceRecord($row);
                if ($r === null) { $infFailed++; continue; }

                $graphUri = 'urn:ahg:provenance-ai:inference:' . $row->uuid;
                // Use the protected buildInferenceTurtle via a thin
                // public wrapper (added as a sibling to record()) - or
                // call the Fuseki sync directly with a minimal turtle
                // body when the wrapper isn't available. Reflection
                // here keeps the replay self-contained.
                $turtle = $this->callBuildTurtle($inference, $row->uuid, $r);

                $result = $sync->insertRdfStar($graphUri, $turtle);
                if (!empty($result['ok'])) {
                    DB::table('ahg_ai_inference')->where('id', $row->id)
                        ->update(['fuseki_graph_uri' => $graphUri]);
                    $infReplayed++;
                } else {
                    $infFailed++;
                    Log::info('[provenance-ai-replay] inference still failing', [
                        'id' => $row->id, 'http' => $result['status'] ?? null, 'err' => $result['error'] ?? null,
                    ]);
                }
            } catch (\Throwable $e) {
                $infFailed++;
                Log::warning('[provenance-ai-replay] inference replay threw', [
                    'id' => $row->id, 'err' => $e->getMessage(),
                ]);
            }
        }

        // ── Pass 2: override rows ─────────────────────────────────────
        if (Schema::hasTable('ahg_ai_override') && Schema::hasColumn('ahg_ai_override', 'fuseki_override_uri')) {
            $pending = DB::table('ahg_ai_override')
                ->whereNull('fuseki_override_uri')
                ->orderBy('id')
                ->limit($batch)
                ->get();

            $this->line(sprintf('[provenance-ai-replay] override pending=%d', $pending->count()));

            foreach ($pending as $row) {
                if ($dry) { $ovReplayed++; continue; }
                try {
                    $graphUri = 'urn:ahg:provenance-ai:override:' . $row->uuid;
                    $turtle = $this->buildOverrideTurtle($row);

                    $result = $sync->insertRdfStar($graphUri, $turtle);
                    if (!empty($result['ok'])) {
                        DB::table('ahg_ai_override')->where('id', $row->id)
                            ->update(['fuseki_override_uri' => $graphUri]);
                        $ovReplayed++;
                    } else {
                        $ovFailed++;
                    }
                } catch (\Throwable $e) {
                    $ovFailed++;
                    Log::warning('[provenance-ai-replay] override replay threw', [
                        'id' => $row->id, 'err' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->line(sprintf(
            '[provenance-ai-replay] %s inference: replayed=%d failed=%d | override: replayed=%d failed=%d',
            $dry ? 'DRY-RUN' : 'live',
            $infReplayed, $infFailed, $ovReplayed, $ovFailed
        ));

        return self::SUCCESS;
    }

    /**
     * Map an ahg_ai_inference row back to an InferenceRecord DTO so we
     * can re-run the same buildInferenceTurtle path the original write
     * used. Returns null when required fields are missing (skip + warn).
     */
    private function rowToInferenceRecord(object $row): ?InferenceRecord
    {
        $required = ['service_name','model_name','model_version','input_hash','output_hash','target_entity_type','target_entity_id','target_field'];
        foreach ($required as $f) {
            if (!isset($row->$f) || $row->$f === '' || $row->$f === null) {
                return null;
            }
        }
        return new InferenceRecord(
            serviceName:     (string) $row->service_name,
            modelName:       (string) $row->model_name,
            modelVersion:    (string) $row->model_version,
            inputHash:       (string) $row->input_hash,
            outputHash:      (string) $row->output_hash,
            confidence:      isset($row->confidence) && $row->confidence !== null ? (float) $row->confidence : null,
            standard:        $row->standard ?? null,
            endpoint:        $row->endpoint ?? null,
            targetEntityType:(string) $row->target_entity_type,
            targetEntityId:  (int)    $row->target_entity_id,
            targetField:     (string) $row->target_field,
            inputExcerpt:    $row->input_excerpt ?? null,
            outputExcerpt:   $row->output_excerpt ?? null,
            elapsedMs:       isset($row->elapsed_ms) ? (int) $row->elapsed_ms : null,
            userId:          isset($row->user_id) ? (int) $row->user_id : null,
        );
    }

    private function callBuildTurtle(InferenceService $inference, string $uuid, InferenceRecord $r): string
    {
        // buildInferenceTurtle is protected; reflect to call without
        // changing the visibility of the production write path.
        $ref = new \ReflectionMethod($inference, 'buildInferenceTurtle');
        $ref->setAccessible(true);
        return (string) $ref->invoke($inference, $uuid, $r);
    }

    /**
     * Minimal turtle body for an override row. The override DTO + builder
     * live on OverrideService; if available, defer to it. Otherwise emit
     * a small "this override happened" provenance triple keyed by the
     * row's uuid + reviewed_at.
     */
    private function buildOverrideTurtle(object $row): string
    {
        // Prefer the OverrideService::buildOverrideTurtle path when it
        // exists (Phase 3a shipped the override-as-PROV-O reified shape).
        if (class_exists(\AhgProvenanceAi\Services\OverrideService::class)) {
            $svc = app(\AhgProvenanceAi\Services\OverrideService::class);
            if (method_exists($svc, 'buildOverrideTurtle')) {
                $ref = new \ReflectionMethod($svc, 'buildOverrideTurtle');
                $ref->setAccessible(true);
                return (string) $ref->invoke($svc, $row);
            }
        }
        // Conservative fallback - just enough provenance for the replay
        // to produce a valid graph the operator can later enrich.
        $uuid = (string) $row->uuid;
        $reviewedAt = $row->reviewed_at ?? now()->toIso8601ZuluString();
        return "@prefix prov: <http://www.w3.org/ns/prov#> .\n"
             . "@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .\n"
             . "@prefix ex: <https://heratio.theahg.co.za/ns/provenance-ai#> .\n"
             . "<urn:ahg:provenance-ai:override:{$uuid}> a prov:Activity ;\n"
             . "    prov:atTime \"" . addslashes((string) $reviewedAt) . "\"^^xsd:dateTime ;\n"
             . "    ex:reviewed_by \"" . addslashes((string) ($row->reviewer_id ?? '')) . "\" .\n";
    }
}
