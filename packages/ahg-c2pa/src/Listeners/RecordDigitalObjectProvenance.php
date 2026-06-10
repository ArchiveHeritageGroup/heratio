<?php
/**
 * Heratio - auto-provenance listener for digital-object ingest (issue #1201).
 *
 * Hooks the Eloquent `created` event on AhgCore\Models\DigitalObject and, for a
 * freshly-created master, best-effort records a digitisation-provenance entry
 * (sign + sidecar + native embed when c2patool is present). It NEVER breaks an
 * upload and no-ops cleanly when signing is unavailable.
 *
 * NOTE ON COVERAGE: every *live* upload path in Heratio (HTTP upload via
 * DigitalObjectService::upload(), the ingest wizard IngestService, and the
 * scanner ProcessScanFile job) writes the digital_object row with a raw
 * DB::table('digital_object')->insert() / insertGetId() - which does NOT fire
 * an Eloquent model event. So this listener is a forward-compatible safety net
 * that catches any code path (present or future) that creates the row through
 * the Eloquent model. The authoritative coverage for the raw-insert paths is
 * the `ahg:c2pa-provenance-backfill` command, which scans masters that have no
 * provenance record yet. The two share DigitalObjectProvenanceService and are
 * idempotent, so they can never double-record the same digital object.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Listeners;

use AhgC2pa\Services\DigitalObjectProvenanceService;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RecordDigitalObjectProvenance
{
    public function __construct(private DigitalObjectProvenanceService $service)
    {
    }

    /**
     * Eloquent fires `eloquent.created: AhgCore\Models\DigitalObject` with the
     * model instance as the sole payload argument.
     *
     * @param object $digitalObject the just-created AhgCore\Models\DigitalObject
     */
    public function handle(object $digitalObject): void
    {
        try {
            $id = isset($digitalObject->id) ? (int) $digitalObject->id : 0;
            if ($id <= 0) {
                return;
            }
            $this->service->recordForDigitalObject(
                $id,
                'Auto-recorded on digital-object create (Eloquent ingest)',
            );
        } catch (Throwable $e) {
            // Never let provenance recording break an upload.
            Log::warning('c2pa: RecordDigitalObjectProvenance listener failed', [
                'err' => $e->getMessage(),
            ]);
        }
    }
}
