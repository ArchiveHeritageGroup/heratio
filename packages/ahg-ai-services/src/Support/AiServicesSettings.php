<?php

/**
 * AiServicesSettings - typed accessors for the ahg_ner_settings group
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

namespace AhgAiServices\Support;

use Illuminate\Support\Facades\DB;

/**
 * Typed accessors for the 20 keys exposed on /admin/ahgSettings/aiServices.
 * Backed by ahg_ner_settings (flat key/value), reads only - the settings
 * dashboard owns the writes.
 *
 * Defaults mirror the form values in
 * packages/ahg-settings/resources/views/ai-services-settings.blade.php so
 * the runtime behaviour matches what the operator sees in the form before
 * they save anything.
 */
class AiServicesSettings
{
    private static function raw(string $key, ?string $default = null): ?string
    {
        try {
            $v = DB::table('ahg_ner_settings')->where('setting_key', $key)->value('setting_value');
            if ($v !== null && $v !== '') return (string) $v;
        } catch (\Throwable $e) {
            // Table absent on a fresh install is not an error - fall through.
        }
        return $default;
    }

    private static function bool(string $key, bool $default): bool
    {
        $v = self::raw($key);
        if ($v === null) return $default;
        return in_array(strtolower($v), ['1', 'true', 'on', 'yes'], true);
    }

    private static function int(string $key, int $default): int
    {
        $v = self::raw($key);
        return $v === null ? $default : (int) $v;
    }

    private static function float(string $key, float $default): float
    {
        $v = self::raw($key);
        return $v === null ? $default : (float) $v;
    }

    // ── Master dispatcher ──────────────────────────────────────────────

    /** local | cloud | hybrid */
    public static function processingMode(): string
    {
        $v = strtolower(self::raw('ai_services_processing_mode', 'local') ?? 'local');
        return in_array($v, ['local', 'cloud', 'hybrid'], true) ? $v : 'local';
    }

    public static function apiUrl(): ?string     { return self::raw('ai_services_api_url'); }
    public static function apiKey(): ?string     { return self::raw('ai_services_api_key'); }
    public static function apiTimeout(): int     { return self::int('ai_services_api_timeout', 60); }

    // ── NER (already wired - kept here for completeness) ──────────────

    public static function nerEnabled(): bool    { return self::bool('ner_enabled', true); }

    // ── Summarizer ─────────────────────────────────────────────────────

    public static function summarizerEnabled(): bool   { return self::bool('summarizer_enabled', false); }
    public static function summarizerMaxLength(): int  { return self::int('summarizer_max_length', 200); }
    public static function summarizerMinLength(): int  { return self::int('summarizer_min_length', 30); }

    /** scope_and_content | publication_status | etc. - target field for the summary */
    public static function summaryField(): string
    {
        $v = (string) self::raw('summary_field', 'scope_and_content');
        return $v !== '' ? $v : 'scope_and_content';
    }

    // ── Spellcheck ─────────────────────────────────────────────────────

    public static function spellcheckEnabled(): bool { return self::bool('spellcheck_enabled', false); }
    public static function spellcheckLanguage(): string
    {
        $v = (string) self::raw('spellcheck_language', 'en');
        return $v !== '' ? $v : 'en';
    }

    // ── Translation ────────────────────────────────────────────────────

    public static function translationEnabled(): bool { return self::bool('translation_enabled', false); }

    /** llm | mt - LLM round-trip vs dedicated MT endpoint */
    public static function translationMode(): string
    {
        $v = strtolower((string) self::raw('translation_mode', 'mt'));
        return in_array($v, ['llm', 'mt'], true) ? $v : 'mt';
    }

    public static function mtEndpoint(): ?string { return self::raw('mt_endpoint'); }
    public static function mtTimeout(): int      { return self::int('mt_timeout', 30); }

    // ── Discovery / Qdrant ─────────────────────────────────────────────

    public static function qdrantUrl(): ?string         { return self::raw('qdrant_url'); }
    public static function qdrantCollection(): ?string  { return self::raw('qdrant_collection'); }
    public static function qdrantModel(): string
    {
        $v = (string) self::raw('qdrant_model', 'sentence-transformers/all-MiniLM-L6-v2');
        return $v !== '' ? $v : 'sentence-transformers/all-MiniLM-L6-v2';
    }
    public static function qdrantMinScore(): float      { return self::float('qdrant_min_score', 0.6); }

    // ── Capture pipeline ───────────────────────────────────────────────

    public static function autoExtractOnUpload(): bool { return self::bool('auto_extract_on_upload', false); }
}
