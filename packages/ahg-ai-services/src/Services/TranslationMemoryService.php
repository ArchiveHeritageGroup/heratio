<?php
/**
 * TranslationMemoryService - exact-match translation memory.
 *
 * Issue #667 Phase 1.
 *
 * Lookup key is sha256(source_text || \0 || source_lang || \0 || target_lang).
 * On a hit, hit_count is incremented and last_used_at is updated; the
 * caller skips the inference dispatch entirely (cost = 0, latency = a
 * single primary-key read).
 *
 * Provenance values: 'machine' = LLM/MT dispatch, 'human' = operator-
 * curated, 'gateway' = AHG AI gateway result, 'mzansilm' = MzansiLM.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.
 */

declare(strict_types=1);

namespace AhgAiServices\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class TranslationMemoryService
{
    /**
     * Look up an existing translation. Returns the cached target text on
     * hit (and bumps hit_count + last_used_at), null on miss.
     */
    public function lookup(string $sourceText, string $sourceLang, string $targetLang): ?string
    {
        try {
            if (!Schema::hasTable('ahg_translation_memory')) {
                return null;
            }
            $hash = $this->hash($sourceText, $sourceLang, $targetLang);
            $row = DB::table('ahg_translation_memory')
                ->where('source_text_hash', $hash)
                ->where('target_lang', $targetLang)
                ->first();
            if ($row === null) {
                return null;
            }
            DB::table('ahg_translation_memory')
                ->where('id', $row->id)
                ->update([
                    'hit_count'    => DB::raw('hit_count + 1'),
                    'last_used_at' => now(),
                ]);
            return (string) $row->target_text;
        } catch (Throwable $e) {
            Log::warning('[ahg-ai] TM lookup failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Store a new translation. Idempotent on the (hash,target_lang)
     * unique key, so a second call with the same source updates
     * provenance/confidence in-place.
     */
    public function store(
        string $sourceText,
        string $sourceLang,
        string $targetLang,
        string $targetText,
        string $provenance = 'machine',
        ?float $confidence = null,
    ): void {
        try {
            if (!Schema::hasTable('ahg_translation_memory')) {
                return;
            }
            $hash = $this->hash($sourceText, $sourceLang, $targetLang);
            DB::table('ahg_translation_memory')->updateOrInsert(
                ['source_text_hash' => $hash, 'target_lang' => $targetLang],
                [
                    'source_lang'  => $sourceLang,
                    'source_text'  => $sourceText,
                    'target_text'  => $targetText,
                    'provenance'   => $provenance,
                    'confidence'   => $confidence,
                    'last_used_at' => now(),
                    'created_at'   => now(),
                ],
            );
        } catch (Throwable $e) {
            Log::warning('[ahg-ai] TM store failed: ' . $e->getMessage());
        }
    }

    public function hash(string $sourceText, string $sourceLang, string $targetLang): string
    {
        return hash('sha256', $sourceText . "\0" . $sourceLang . "\0" . $targetLang);
    }
}
