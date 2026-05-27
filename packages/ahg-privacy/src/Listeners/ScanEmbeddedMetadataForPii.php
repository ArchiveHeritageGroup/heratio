<?php

/**
 * ScanEmbeddedMetadataForPii - queued listener that runs PiiScanService
 * against the sidecar tables populated by ahg-metadata-extraction whenever
 * EmbeddedMetadataExtracted is dispatched.
 *
 * Heratio Issue #751. The listener is queued so the upload path stays fast:
 * the extractor returns to the caller as soon as the sidecar rows are
 * persisted, and the PII scan + finding writes happen on the queue.
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

namespace AhgPrivacy\Listeners;

use AhgMetadataExtraction\Events\EmbeddedMetadataExtracted;
use AhgPrivacy\Services\PiiScanService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

final class ScanEmbeddedMetadataForPii implements ShouldQueue
{
    use InteractsWithQueue;

    /** Number of attempts before the job is marked failed. */
    public int $tries = 3;

    /** Seconds to wait between retries. */
    public int $backoff = 30;

    /**
     * Handle the event by walking the sidecar tables for the digital_object
     * and persisting any findings to ahg_pii_finding_embedded.
     */
    public function handle(EmbeddedMetadataExtracted $event): void
    {
        try {
            $scanner = new PiiScanService();
            $findings = $scanner->scanEmbeddedMetadata($event->digitalObjectId);
            if ($findings === []) {
                return;
            }
            $inserted = $scanner->persistEmbeddedFindings($event->digitalObjectId, $findings);
            if ($inserted > 0) {
                Log::info('ahg-privacy: embedded PII findings persisted', [
                    'digital_object_id' => $event->digitalObjectId,
                    'inserted'          => $inserted,
                    'total_findings'    => count($findings),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('ahg-privacy: ScanEmbeddedMetadataForPii listener failed', [
                'digital_object_id' => $event->digitalObjectId,
                'error'             => $e->getMessage(),
            ]);
            // Let the queue worker retry by re-throwing once we've logged.
            throw $e;
        }
    }
}
