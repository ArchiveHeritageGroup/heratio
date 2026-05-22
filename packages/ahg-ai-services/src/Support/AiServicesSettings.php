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

    /** A comma-separated setting parsed into a lowercased, trimmed list. */
    private static function csv(string $key, string $default): array
    {
        $raw = (string) self::raw($key, $default);
        $items = array_map('trim', explode(',', strtolower($raw)));

        return array_values(array_filter($items, static fn ($v) => $v !== ''));
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

    // ── RAG guardrails (#141) ──────────────────────────────────────────

    /** off | warn | mask | block - enforcement strength of the guardrails. */
    public static function ragGuardrailMode(): string
    {
        $v = strtolower(trim((string) self::raw('rag_guardrail_mode', 'warn')));
        return in_array($v, ['off', 'warn', 'mask', 'block'], true) ? $v : 'warn';
    }

    /** Data scopes permitted to be sent to a cloud provider. */
    public static function ragCloudAllowedScopes(): array
    {
        return self::csv('rag_cloud_allowed_scopes', 'public,internal');
    }

    /** Provider names treated as inside the local trust domain. */
    public static function ragLocalProviders(): array
    {
        return self::csv('rag_local_providers', 'ollama');
    }

    /** The operator-sanctioned set of inference purposes. */
    public static function ragSanctionedPurposes(): array
    {
        return self::csv(
            'rag_sanctioned_purposes',
            'description_generation,summarization,translation,entity_extraction,spellcheck,research_assistance,metadata_enrichment'
        );
    }

    /** Minimum grounding score below which a RAG output is flagged. */
    public static function ragGroundingThreshold(): float
    {
        return self::float('rag_grounding_threshold', 0.45);
    }

    // ── MzansiLM SA-language model (#128) ──────────────────────────────

    /** Master gate: route SA-language translation to MzansiLM instead of the default LLM/MT path. */
    public static function mzansilmEnabled(): bool
    {
        return self::bool('mzansilm_enabled', false);
    }

    /** OpenAI-shape base URL of the MzansiLM endpoint, e.g. http://gpu-host:8000/v1 . */
    public static function mzansilmEndpoint(): ?string
    {
        return self::raw('mzansilm_endpoint');
    }

    /** Model name requested from the MzansiLM endpoint. */
    public static function mzansilmModel(): string
    {
        $v = (string) self::raw('mzansilm_model', 'mzansilm-125m');
        return $v !== '' ? $v : 'mzansilm-125m';
    }

    /**
     * Target locales routed to MzansiLM. Default is the ten SA languages the
     * issue prioritises (af is deliberately excluded - the operator owns the
     * Afrikaans catalogue).
     */
    public static function mzansilmLocales(): array
    {
        return self::csv('mzansilm_locales', 'zu,xh,nso,st,ss,ts,tn,ve,nr,nd');
    }

    public static function mzansilmTimeout(): int
    {
        return self::int('mzansilm_timeout', 60);
    }
}
