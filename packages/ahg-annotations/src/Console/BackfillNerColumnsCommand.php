<?php

/**
 * BackfillNerColumnsCommand - issue #697 finishing pass.
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

namespace AhgAnnotations\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill the denormalised NER provenance columns on ahg_iiif_annotation
 * from body_json._heratio.* payloads written by the bridge before the
 * columns existed.
 *
 * Idempotent: rows that already have ner_run_id set are skipped (unless
 * --force is passed, in which case the columns are overwritten from
 * body_json so a corrupted column value can be repaired without first
 * blanking it by hand). --dry-run prints what would change without
 * touching the table.
 *
 * Confidence: the bridge does not currently embed a per-entity NER
 * confidence inside body_json (NerService output is bucketed, not
 * scored). The column stays NULL for backfilled rows and is populated
 * going forward only when the API ingestion endpoint receives a
 * confidence value with the entity.
 *
 * Issue #697.
 */
class BackfillNerColumnsCommand extends Command
{
    protected $signature = 'ahg:annotations:backfill-ner-columns
        {--dry-run : Report what would change without writing}
        {--force : Overwrite ner_* columns even if already populated}
        {--chunk=500 : Rows per chunk - keeps memory bounded on large installs}';

    protected $description = 'Backfill ner_entity_type / ner_confidence / ner_run_id on ahg_iiif_annotation from body_json._heratio.* provenance (#697).';

    public function handle(): int
    {
        if (! Schema::hasTable('ahg_iiif_annotation')) {
            $this->error('ahg_iiif_annotation table missing - run the package install first.');

            return self::FAILURE;
        }
        foreach (['ner_entity_type', 'ner_confidence', 'ner_run_id'] as $col) {
            if (! Schema::hasColumn('ahg_iiif_annotation', $col)) {
                $this->error("Column {$col} missing - re-run the ahg-annotations service provider boot to install columns.");

                return self::FAILURE;
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $chunk = max(1, (int) $this->option('chunk'));

        $query = DB::table('ahg_iiif_annotation')
            ->select(['id', 'body_json', 'ner_entity_type', 'ner_confidence', 'ner_run_id']);
        if (! $force) {
            // Default mode: only touch rows that are still missing the
            // run id. That keeps the command cheap to re-run.
            $query->whereNull('ner_run_id');
        }

        $touched = 0;
        $skipped = 0;
        $scanned = 0;
        $offset = 0;

        do {
            $rows = (clone $query)->orderBy('id')->offset($offset)->limit($chunk)->get();
            if ($rows->isEmpty()) {
                break;
            }
            foreach ($rows as $row) {
                $scanned++;
                $body = json_decode((string) $row->body_json, true);
                if (! is_array($body)) {
                    $skipped++;
                    continue;
                }
                $prov = $body['_heratio'] ?? null;
                if (! is_array($prov) || ($prov['source'] ?? null) !== 'ner') {
                    // Not an NER annotation - leave the columns NULL.
                    $skipped++;
                    continue;
                }

                $runId = isset($prov['run_id']) ? substr((string) $prov['run_id'], 0, 64) : null;
                $entityType = isset($prov['entity_type']) ? substr((string) $prov['entity_type'], 0, 64) : null;
                // Confidence may live under _heratio.confidence (POSTed
                // payloads from the from-ner endpoint) or be absent
                // (rows the bridge wrote before the column existed).
                $confidence = null;
                if (isset($prov['confidence']) && is_numeric($prov['confidence'])) {
                    $confidence = max(0.0, min(1.0, (float) $prov['confidence']));
                }

                if (! $force && $row->ner_run_id !== null) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        '[dry-run] id=%d -> run_id=%s entity_type=%s confidence=%s',
                        $row->id,
                        $runId ?? 'null',
                        $entityType ?? 'null',
                        $confidence === null ? 'null' : sprintf('%.4f', $confidence)
                    ));
                } else {
                    DB::table('ahg_iiif_annotation')
                        ->where('id', $row->id)
                        ->update([
                            'ner_run_id' => $runId,
                            'ner_entity_type' => $entityType,
                            'ner_confidence' => $confidence,
                        ]);
                }
                $touched++;
            }
            $offset += $chunk;
        } while (true);

        $this->info(sprintf(
            'Scanned %d rows; %s %d; skipped %d.',
            $scanned,
            $dryRun ? 'would touch' : 'updated',
            $touched,
            $skipped
        ));

        return self::SUCCESS;
    }
}
