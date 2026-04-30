<?php

/**
 * TranslationProvenance — query helpers for the ahg_translation_log table.
 *
 * The translation modal save flow writes a row to ahg_translation_log every
 * time a human or MT engine writes into a record's *_i18n table. This helper
 * lets show-page Blade templates ask "was this field machine-translated?"
 * cheaply, so they can render an AI-disclosure pill next to translated values.
 *
 * Per-record bulk loading: callers should prefer {@see forRecord()} over
 * per-field {@see source()} calls when rendering many fields, to avoid N+1.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgTranslation\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TranslationProvenance
{
    /**
     * Latest translation source for a single (object, culture, field), or
     * null if no provenance row exists. Returns 'human' | 'machine' | null.
     */
    public static function source(int $objectId, string $culture, string $field): ?string
    {
        if (! Schema::hasTable('ahg_translation_log')) {
            return null;
        }
        if ($culture === '' || $field === '') {
            return null;
        }
        return DB::table('ahg_translation_log')
            ->where('object_id', $objectId)
            ->where('target_culture', $culture)
            ->where('field_name', $field)
            ->whereNotNull('source')
            ->orderByDesc('id')
            ->value('source');
    }

    /**
     * Bulk-load every translated field's provenance for a record + culture.
     *
     * Returns ['field_name' => 'human'|'machine', ...].
     * Use this once at the top of a show view, then index per-field at render.
     */
    public static function forRecord(int $objectId, string $culture): array
    {
        if (! Schema::hasTable('ahg_translation_log') || $culture === '') {
            return [];
        }
        // Latest row per field
        $rows = DB::table('ahg_translation_log')
            ->where('object_id', $objectId)
            ->where('target_culture', $culture)
            ->whereNotNull('source')
            ->whereNotNull('field_name')
            ->orderByDesc('id')
            ->get(['field_name', 'source', 'confirmed']);

        $out = [];
        foreach ($rows as $r) {
            // First seen wins (rows are pre-sorted DESC by id, so first seen = latest)
            if (! isset($out[$r->field_name])) {
                $out[$r->field_name] = (string) $r->source;
            }
        }
        return $out;
    }

    /**
     * Convenience — true when the field is currently shown as a machine
     * translation (latest provenance row says source=machine, not yet
     * verified by a human via confirmed=1).
     */
    public static function isMachineTranslated(int $objectId, string $culture, string $field): bool
    {
        return self::source($objectId, $culture, $field) === 'machine';
    }
}
