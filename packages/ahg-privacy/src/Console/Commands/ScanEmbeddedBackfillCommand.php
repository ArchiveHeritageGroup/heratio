<?php

/**
 * ScanEmbeddedBackfillCommand - ahg:privacy:scan-embedded-backfill
 *
 * Walks digital_object_metadata / dam_iptc_metadata / media_metadata for
 * digital objects that have NOT been scanned for embedded PII yet (or that
 * the operator wants re-scanned), runs PiiScanService::scanEmbeddedMetadata,
 * and persists findings to ahg_pii_finding_embedded.
 *
 * Heratio Issue #751. Idempotent: rows are deduped on
 * (digital_object_id, pii_type, source_table, source_field) so repeat runs
 * only refresh scanned_at + source_value, never insert duplicates.
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

declare(strict_types=1);

namespace AhgPrivacy\Console\Commands;

use AhgPrivacy\Services\PiiScanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ScanEmbeddedBackfillCommand extends Command
{
    protected $signature = 'ahg:privacy:scan-embedded-backfill
        {--digital-object-id= : Only scan this digital_object.id}
        {--limit=500 : Maximum number of digital objects to scan in this run}';

    protected $description = 'Backfill ahg_pii_finding_embedded by scanning EXIF / IPTC / XMP sidecar tables for embedded PII.';

    public function handle(): int
    {
        if (! Schema::hasTable('ahg_pii_finding_embedded')) {
            $this->error('ahg_pii_finding_embedded table is not installed yet - boot the app once to auto-install Phase 2 schema.');
            return self::FAILURE;
        }

        $scanner = new PiiScanService();

        $singleId = $this->option('digital-object-id');
        if ($singleId !== null) {
            return $this->scanOne($scanner, (int) $singleId);
        }

        $limit = max(1, (int) $this->option('limit'));
        $ids = $this->candidateDigitalObjectIds($limit);

        if ($ids === []) {
            $this->info('No digital objects with sidecar metadata to scan.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Scanning %d digital object(s) for embedded PII...', count($ids)));
        $totalInserted = 0;
        $totalFindings = 0;
        $bar = $this->output->createProgressBar(count($ids));
        $bar->start();

        foreach ($ids as $doId) {
            $findings = $scanner->scanEmbeddedMetadata((int) $doId);
            $totalFindings += count($findings);
            if ($findings !== []) {
                $totalInserted += $scanner->persistEmbeddedFindings((int) $doId, $findings);
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $this->info(sprintf(
            'Scanned %d digital objects, recorded %d findings (%d new rows in ahg_pii_finding_embedded).',
            count($ids),
            $totalFindings,
            $totalInserted
        ));
        return self::SUCCESS;
    }

    /**
     * Scan a single digital_object and report counts inline. Useful for
     * smoke-testing with a known-good GPS-tagged sample.
     */
    private function scanOne(PiiScanService $scanner, int $digitalObjectId): int
    {
        if ($digitalObjectId <= 0) {
            $this->error('--digital-object-id must be a positive integer.');
            return self::FAILURE;
        }

        $findings = $scanner->scanEmbeddedMetadata($digitalObjectId);
        if ($findings === []) {
            $this->info("No embedded PII findings for digital_object_id={$digitalObjectId}.");
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($findings as $f) {
            $rows[] = [
                $f['pii_type'],
                $f['source_table'],
                $f['source_column'],
                mb_strimwidth((string) $f['value'], 0, 60, '...'),
                number_format($f['confidence'], 2),
            ];
        }
        $this->table(['type', 'source_table', 'source_field', 'value', 'confidence'], $rows);

        $inserted = $scanner->persistEmbeddedFindings($digitalObjectId, $findings);
        $this->info(sprintf(
            'Persisted: %d new finding(s) for digital_object_id=%d (total scan hits: %d).',
            $inserted,
            $digitalObjectId,
            count($findings)
        ));
        return self::SUCCESS;
    }

    /**
     * Return digital_object ids that have sidecar metadata in any of the
     * three feeder tables. Order is "oldest extracted first" so a slow
     * backfill makes monotonic progress. Hard-cap at $limit so a single
     * invocation can't blow up on a 10M-object archive.
     *
     * @return array<int,int>
     */
    private function candidateDigitalObjectIds(int $limit): array
    {
        $ids = [];

        if (Schema::hasTable('digital_object_metadata')) {
            $ids = array_merge($ids, DB::table('digital_object_metadata')
                ->orderBy('id')
                ->limit($limit)
                ->pluck('digital_object_id')
                ->all());
        }
        if (Schema::hasTable('media_metadata')) {
            $ids = array_merge($ids, DB::table('media_metadata')
                ->orderBy('id')
                ->limit($limit)
                ->pluck('digital_object_id')
                ->all());
        }
        // dam_iptc_metadata keys to information_object.id; resolve back to
        // its digital_objects via digital_object.object_id.
        if (Schema::hasTable('dam_iptc_metadata') && Schema::hasTable('digital_object')) {
            $ioIds = DB::table('dam_iptc_metadata')
                ->orderBy('id')
                ->limit($limit)
                ->pluck('object_id')
                ->all();
            if ($ioIds !== []) {
                $doIds = DB::table('digital_object')
                    ->whereIn('object_id', $ioIds)
                    ->pluck('id')
                    ->all();
                $ids = array_merge($ids, $doIds);
            }
        }

        $unique = array_values(array_unique(array_map('intval', $ids)));
        return array_slice($unique, 0, $limit);
    }
}
