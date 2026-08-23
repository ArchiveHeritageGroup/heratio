<?php

/**
 * The one writer for `ahg_ner_extraction` (#1472).
 *
 * That table is supposed to record every NER run: what was scanned, when, by
 * which backend, and what came of it. In practice it recorded only the first
 * three. `AiController::saveExtraction()` inserted a row with `status` hardcoded
 * to 'pending', never wrote `entity_count`, and nothing anywhere updated either
 * column - one INSERT and five SELECTs across the whole repository, no UPDATE.
 * `NerService::extractAndRecord()`, the path the `ahg:ai-ner` command uses, did
 * not touch the table at all.
 *
 * So the ledger showed 192 rows stuck at 'pending' with 0 entities and nothing
 * completed since 25 January, while 171 of those rows had their entities sitting
 * in `ahg_ner_entity` all along. NER looked dead for seven months and was not.
 *
 * It was not cosmetic. `PiiScanService` derives the POPIA statistics from exactly
 * those two columns, so the privacy surface reported 24 objects containing PII
 * when 47 did, and 454 entities against 2,108 stored.
 *
 * Both extraction paths now go through here, because two implementations of the
 * same write are what produced the divergence in the first place.
 *
 * STATUS VOCABULARY - the point is that these stay distinguishable:
 *
 *   running    - opened, outcome not yet known (in-flight, or the process died)
 *   completed  - the scan finished. entity_count may legitimately be 0, which
 *                means "scanned, found nothing" - NOT the same as never having run
 *   failed     - the scan itself failed (gateway refused, model error, exception)
 *   pending    - legacy only. Rows written before this class existed, whose real
 *                outcome cannot be reconstructed. Never written by new code.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio, licensed under the GNU AGPL v3 or later.
 */

namespace AhgAiServices\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NerExtractionLedger
{
    public const RUNNING = 'running';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';

    /** @var bool|null cached table probe - this is called per extracted object */
    private static ?bool $tableReady = null;

    private static function ready(): bool
    {
        if (self::$tableReady === null) {
            try {
                self::$tableReady = Schema::hasTable('ahg_ner_extraction');
            } catch (\Throwable) {
                self::$tableReady = false;
            }
        }

        return self::$tableReady;
    }

    /**
     * Open a ledger row for a scan that is about to run.
     *
     * Returns the row id, or null when the table is absent or the write fails -
     * callers must treat the ledger as best-effort and never let it break an
     * extraction that would otherwise succeed.
     */
    public static function open(int $objectId, string $backend = 'local'): ?int
    {
        if (! self::ready() || $objectId <= 0) {
            return null;
        }

        try {
            return (int) DB::table('ahg_ner_extraction')->insertGetId([
                'object_id' => $objectId,
                'backend_used' => mb_substr($backend, 0, 50),
                'status' => self::RUNNING,
                'entity_count' => 0,
                'extracted_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('NerExtractionLedger::open failed: '.$e->getMessage());

            return null;
        }
    }

    /** The scan finished. A count of 0 means "scanned, found nothing". */
    public static function complete(?int $id, int $entityCount): void
    {
        self::finish($id, self::COMPLETED, max(0, $entityCount));
        self::warnIfUnreconciled($id, max(0, $entityCount));
    }

    /**
     * Warn when the count this row claims does not match the entities actually
     * linked to it - #1479.
     *
     * The whole purpose of the ledger is attribution: which run produced which
     * entity. Before this, both writers inserted entities without setting
     * extraction_id, so every row claimed a count of entities that could not be
     * reached from it - and nothing noticed for months. The #1472 migration
     * already logs when the ledger fails to reconcile after a backfill; doing
     * the same at WRITE time is what would have caught this on the first run.
     *
     * Warn only. A logging check must never be able to fail an extraction that
     * otherwise succeeded.
     */
    private static function warnIfUnreconciled(?int $id, int $claimed): void
    {
        if ($id === null || $id <= 0 || ! self::ready()) {
            return;
        }

        try {
            if (! Schema::hasColumn('ahg_ner_entity', 'extraction_id')) {
                return;
            }

            $linked = (int) DB::table('ahg_ner_entity')->where('extraction_id', $id)->count();

            if ($linked !== $claimed) {
                Log::warning(
                    "NER extraction {$id} does not reconcile: claims {$claimed} entit(ies), "
                    . "{$linked} carry extraction_id={$id}. Entities written without a ledger link "
                    . 'cannot be attributed to the run that produced them (#1479).'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('NerExtractionLedger reconciliation check failed: '.$e->getMessage());
        }
    }

    /** The scan itself failed - distinct from finding nothing. */
    public static function fail(?int $id, string $reason = ''): void
    {
        self::finish($id, self::FAILED, 0);

        if ($id !== null && $reason !== '') {
            Log::warning("NER extraction {$id} failed: ".mb_substr($reason, 0, 300));
        }
    }

    private static function finish(?int $id, string $status, int $entityCount): void
    {
        if ($id === null || $id <= 0 || ! self::ready()) {
            return;
        }

        try {
            DB::table('ahg_ner_extraction')->where('id', $id)->update([
                'status' => $status,
                'entity_count' => $entityCount,
            ]);
        } catch (\Throwable $e) {
            Log::warning('NerExtractionLedger::finish failed: '.$e->getMessage());
        }
    }
}
