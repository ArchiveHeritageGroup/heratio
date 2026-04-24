<?php

/**
 * PremisEventService — Heratio ahg-scan (P4)
 *
 * Writes PREMIS-style preservation events into `preservation_event`. One
 * row per observable pipeline outcome (virus check, format identification,
 * fixity, ingestion, derivation, replication), so the preservation record
 * is complete at ingest time rather than reconstructed later.
 *
 * Event types follow the PREMIS vocabulary
 *   https://www.loc.gov/standards/premis/v3/premis-3-0-final.pdf §2.2.4
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PremisEventService
{
    public const TYPE_VIRUS_CHECK = 'virusCheck';
    public const TYPE_FORMAT_ID = 'formatIdentification';
    public const TYPE_FIXITY = 'messageDigestCalculation';
    public const TYPE_INGESTION = 'ingestion';
    public const TYPE_DERIVATION = 'creation (derivation)';
    public const TYPE_REPLICATION = 'replication';

    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_WARNING = 'warning';
    public const OUTCOME_FAILURE = 'failure';

    /**
     * Emit a preservation event. Designed to be fire-and-forget — logging
     * failure must never block the ingest pipeline.
     *
     * @param int|null $ioId  Linked information_object.id (null is allowed for pre-IO events)
     * @param int|null $doId  Linked digital_object.id
     * @param string   $type  PREMIS event type (see constants)
     * @param string   $outcome 'success' | 'warning' | 'failure' | 'unknown'
     * @param string|null $detail Free-form description
     * @param array    $detailExtra Structured data appended to event_outcome_detail
     */
    public static function emit(
        ?int $ioId,
        ?int $doId,
        string $type,
        string $outcome,
        ?string $detail = null,
        array $detailExtra = []
    ): ?int {
        try {
            $outcomeDetail = null;
            if ($detailExtra) {
                $outcomeDetail = json_encode($detailExtra, JSON_UNESCAPED_SLASHES);
            }
            return DB::table('preservation_event')->insertGetId([
                'digital_object_id' => $doId,
                'information_object_id' => $ioId,
                'event_type' => $type,
                'event_datetime' => now(),
                'event_detail' => $detail,
                'event_outcome' => $outcome,
                'event_outcome_detail' => $outcomeDetail,
                'linking_agent_type' => 'system',
                'linking_agent_value' => 'ahg-scan',
                'linking_object_type' => $doId ? 'digital_object' : ($ioId ? 'information_object' : null),
                'linking_object_value' => $doId ? (string) $doId : ($ioId ? (string) $ioId : null),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-scan] PREMIS event emit failed: ' . $e->getMessage());
            return null;
        }
    }
}
