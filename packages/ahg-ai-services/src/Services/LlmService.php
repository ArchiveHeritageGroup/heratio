<?php

/**
 * LlmService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgAiServices\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LLM Service
 *
 * Factory and orchestrator for LLM providers (OpenAI, Anthropic, Ollama).
 * Manages configurations from ahg_llm_config table and provides a unified
 * interface for LLM operations: completion, summarization, translation,
 * description suggestion, entity extraction, and spellcheck.
 *
 * Ported from ahgAIPlugin LlmService.php
 */
class LlmService
{
    private const ENCRYPTION_METHOD = 'aes-256-cbc';
    private const ANTHROPIC_VERSION = '2023-06-01';

    private ?string $encryptionKey = null;

    public function __construct()
    {
        $this->encryptionKey = $this->getEncryptionKey();
    }

    // ─── Provider / Config Management ───────────────────────────────

    /**
     * Get the active provider name from the default config.
     */
    public function getProvider(): string
    {
        $config = $this->getDefaultConfig();

        return $config->provider ?? 'ollama';
    }

    /**
     * Get a provider configuration by ID or the default.
     */
    public function getConfiguration(?int $configId = null): ?object
    {
        if ($configId) {
            return DB::table('ahg_llm_config')
                ->where('id', $configId)
                ->first();
        }

        return $this->getDefaultConfig();
    }

    /**
     * Get the default active configuration.
     */
    public function getDefaultConfig(): ?object
    {
        $config = DB::table('ahg_llm_config')
            ->where('is_default', 1)
            ->where('is_active', 1)
            ->first();

        if (!$config) {
            $config = DB::table('ahg_llm_config')
                ->where('is_active', 1)
                ->orderBy('id')
                ->first();
        }

        return $config;
    }

    /**
     * Get all configurations.
     */
    public function getConfigurations(bool $activeOnly = false): array
    {
        $query = DB::table('ahg_llm_config');

        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        return $query->orderBy('provider')->orderBy('name')->get()->toArray();
    }

    /**
     * Create a new LLM configuration.
     */
    public function createConfiguration(array $data): int
    {
        $insert = [
            'provider'        => $data['provider'],
            'name'            => $data['name'],
            'is_active'       => $data['is_active'] ?? 1,
            'is_default'      => $data['is_default'] ?? 0,
            'endpoint_url'    => $data['endpoint_url'] ?? null,
            'model'           => $data['model'],
            'max_tokens'      => $data['max_tokens'] ?? 2000,
            'temperature'     => $data['temperature'] ?? 0.70,
            'timeout_seconds' => $data['timeout_seconds'] ?? 120,
            'created_at'      => now(),
        ];

        if (!empty($data['api_key'])) {
            $insert['api_key_encrypted'] = $this->encryptApiKey($data['api_key']);
        }

        if (!empty($data['is_default'])) {
            DB::table('ahg_llm_config')->update(['is_default' => 0]);
        }

        return DB::table('ahg_llm_config')->insertGetId($insert);
    }

    /**
     * Update an existing configuration.
     */
    public function updateConfiguration(int $configId, array $data): bool
    {
        $update = [];
        $fields = ['name', 'is_active', 'endpoint_url', 'model', 'max_tokens', 'temperature', 'timeout_seconds'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if (array_key_exists('api_key', $data)) {
            $update['api_key_encrypted'] = !empty($data['api_key'])
                ? $this->encryptApiKey($data['api_key'])
                : null;
        }

        if (!empty($data['is_default'])) {
            DB::table('ahg_llm_config')->update(['is_default' => 0]);
            $update['is_default'] = 1;
        }

        if (empty($update)) {
            return true;
        }

        $update['updated_at'] = now();

        return DB::table('ahg_llm_config')
            ->where('id', $configId)
            ->update($update) >= 0;
    }

    /**
     * Delete a configuration.
     */
    public function deleteConfiguration(int $configId): bool
    {
        return DB::table('ahg_llm_config')
            ->where('id', $configId)
            ->delete() > 0;
    }

    // ─── LLM Operations ────────────────────────────────────────────

    /**
     * Send a completion request using specified or default config.
     *
     * @return array ['success' => bool, 'text' => ?string, 'tokens_used' => int, 'model' => ?string, 'error' => ?string]
     */
    public function complete(string $prompt, array $options = []): ?string
    {
        $configId = $options['config_id'] ?? null;
        $config   = $configId ? $this->getConfiguration($configId) : $this->getDefaultConfig();

        if (!$config) {
            Log::warning('LlmService::complete - No LLM configuration found');
            return null;
        }

        $result = $this->dispatchToProvider($config, $prompt, $options);

        return $result['success'] ? $result['text'] : null;
    }

    // =====================================================================
    //  LLM suggestion pipeline (Phase X.4 — Heratio-specific, no PSIS source)
    //
    //  These four methods back the "AI Suggest Description" feature in the
    //  information-object editor. PSIS has no equivalent — its AI flow is
    //  OCR-only. The suggestion pipeline is:
    //    1. gatherContext(id)      → assemble IO fields + OCR text
    //    2. getTemplateForObject(id, templateId?) → pick matching prompt template
    //    3. buildPrompt(template, data) → render template with context
    //    4. completeFull(system, user, configId) → call the LLM
    //    5. (optional) save result into ahg_ai_suggestion
    // =====================================================================

    /**
     * Gather all context needed to generate a suggestion for an information
     * object: title, identifier, level, scope_and_content, OCR text, etc.
     *
     * @return array{success: bool, data?: array<string, mixed>, error?: string}
     */
    public function gatherContext(int $objectId): array
    {
        $row = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', '=', app()->getLocale());
            })
            ->leftJoin('term_i18n as lod', function ($j) {
                $j->on('lod.id', '=', 'io.level_of_description_id')
                  ->where('lod.culture', '=', app()->getLocale());
            })
            ->where('io.id', $objectId)
            ->select([
                'io.id',
                'io.identifier',
                'io.parent_id',
                'io.repository_id',
                'io.level_of_description_id',
                'i18n.title',
                'i18n.scope_and_content',
                'i18n.archival_history',
                'i18n.extent_and_medium',
                'i18n.arrangement',
                'i18n.physical_characteristics',
                'i18n.acquisition',
                'lod.name as level_of_description',
            ])
            ->first();

        if (!$row) {
            return ['success' => false, 'error' => 'Object not found'];
        }

        // Aggregate OCR text from digital objects attached to this IO.
        $ocrText = DB::table('ahg_ai_pending_extraction')
            ->where('information_object_id', $objectId)
            ->whereNotNull('extracted_text')
            ->orderByDesc('created_at')
            ->limit(3)
            ->pluck('extracted_text')
            ->filter()
            ->implode("\n\n");

        $data = [
            'id'                      => (int) $row->id,
            'identifier'              => $row->identifier,
            'title'                   => $row->title,
            'scope_and_content'       => $row->scope_and_content,
            'archival_history'        => $row->archival_history,
            'extent_and_medium'       => $row->extent_and_medium,
            'arrangement'             => $row->arrangement,
            'physical_characteristics'=> $row->physical_characteristics,
            'acquisition'             => $row->acquisition,
            'level_of_description'    => $row->level_of_description,
            'level_of_description_id' => $row->level_of_description_id,
            'repository_id'           => $row->repository_id,
            'parent_id'                => $row->parent_id,
            'ocr_text'                => $ocrText ?: null,
        ];

        return ['success' => true, 'data' => $data];
    }

    /**
     * Pick the best prompt template for an object. Explicit `$templateId`
     * wins; otherwise the default template matching the object's repository
     * and level_of_description is preferred, then any default template.
     */
    public function getTemplateForObject(int $objectId, ?int $templateId = null): ?object
    {
        if ($templateId) {
            return DB::table('ahg_prompt_template')
                ->where('id', $templateId)
                ->where('is_active', 1)
                ->first();
        }

        $object = DB::table('information_object')->where('id', $objectId)->first(['repository_id', 'level_of_description_id']);
        if (!$object) {
            return DB::table('ahg_prompt_template')
                ->where('is_active', 1)
                ->where('is_default', 1)
                ->first();
        }

        $lodName = null;
        if (!empty($object->level_of_description_id)) {
            $lodName = DB::table('term_i18n')
                ->where('id', $object->level_of_description_id)
                ->where('culture', app()->getLocale())
                ->value('name');
        }

        // Prefer: same repository + same level → same repository → same level → default.
        $candidates = [
            ['repository_id' => $object->repository_id, 'level_of_description' => $lodName],
            ['repository_id' => $object->repository_id],
            ['level_of_description' => $lodName],
            [],
        ];

        foreach ($candidates as $filters) {
            $q = DB::table('ahg_prompt_template')->where('is_active', 1);
            foreach ($filters as $col => $val) {
                if ($val !== null) {
                    $q->where($col, $val);
                }
            }
            $q->orderByDesc('is_default')->orderByDesc('id');
            if ($template = $q->first()) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Render a prompt template against gathered context data.
     *
     * @param object $template Row from `ahg_prompt_template`.
     * @param array<string, mixed> $data Output of `gatherContext()->data`.
     * @return array{system: string, user: string}
     */
    public function buildPrompt(object $template, array $data): array
    {
        $includeOcr  = !empty($template->include_ocr);
        $maxOcrChars = (int) ($template->max_ocr_chars ?? 8000);

        $ocr = '';
        if ($includeOcr && !empty($data['ocr_text'])) {
            $ocr = mb_substr((string) $data['ocr_text'], 0, $maxOcrChars);
        }

        $replacements = [
            '{{title}}'                => (string) ($data['title']                    ?? ''),
            '{{identifier}}'           => (string) ($data['identifier']               ?? ''),
            '{{level_of_description}}' => (string) ($data['level_of_description']     ?? ''),
            '{{scope_and_content}}'    => (string) ($data['scope_and_content']        ?? ''),
            '{{archival_history}}'     => (string) ($data['archival_history']         ?? ''),
            '{{extent_and_medium}}'    => (string) ($data['extent_and_medium']        ?? ''),
            '{{arrangement}}'          => (string) ($data['arrangement']              ?? ''),
            '{{physical_characteristics}}' => (string) ($data['physical_characteristics'] ?? ''),
            '{{acquisition}}'          => (string) ($data['acquisition']              ?? ''),
            '{{ocr_text}}'             => $ocr,
        ];

        return [
            'system' => (string) ($template->system_prompt ?? ''),
            'user'   => strtr((string) ($template->user_prompt_template ?? ''), $replacements),
        ];
    }

    /**
     * End-to-end: gather context, pick template, build prompt, call LLM,
     * persist result into `ahg_ai_suggestion`.
     *
     * @return array{success: bool, suggestion_id?: int, text?: string, tokens_used?: int, model?: string, error?: string}
     */
    public function generateSuggestion(int $objectId, ?int $templateId = null, ?int $configId = null, ?int $userId = null): array
    {
        $context = $this->gatherContext($objectId);
        if (!($context['success'] ?? false)) {
            return $context;
        }

        $template = $this->getTemplateForObject($objectId, $templateId);
        if (!$template) {
            return ['success' => false, 'error' => 'No prompt template available'];
        }

        $prompts = $this->buildPrompt($template, $context['data']);
        $result  = $this->completeFull($prompts['system'], $prompts['user'], $configId);

        if (empty($result['success'])) {
            return $result;
        }

        $suggestionId = DB::table('ahg_ai_suggestion')->insertGetId([
            'object_id'      => $objectId,
            'field_name'     => 'scope_and_content',
            'original_value' => $context['data']['scope_and_content'] ?? null,
            'suggested_value'=> $result['text'] ?? null,
            'status'         => 'pending',
            'model'          => $result['model'] ?? null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return [
            'success'           => true,
            'suggestion_id'     => (int) $suggestionId,
            'text'              => $result['text'] ?? null,
            'existing_text'     => $context['data']['scope_and_content'] ?? null,
            'tokens_used'       => $result['tokens_used'] ?? 0,
            'model'             => $result['model'] ?? null,
            'generation_time_ms'=> $result['generation_time_ms'] ?? 0,
            'template_name'     => $template->name ?? '',
        ];
    }

    /**
     * Full completion returning the entire result array (for detailed responses).
     */
    public function completeFull(string $systemPrompt, string $userPrompt, ?int $configId = null, array $options = []): array
    {
        $config = $configId ? $this->getConfiguration($configId) : $this->getDefaultConfig();

        if (!$config) {
            return [
                'success'    => false,
                'error'      => 'No LLM configuration found',
                'text'       => null,
                'tokens_used' => 0,
                'model'      => null,
            ];
        }

        return $this->dispatchToProviderFull($config, $systemPrompt, $userPrompt, $options);
    }

    /**
     * Summarize text using the active LLM.
     */
    public function summarize(string $text, int $maxLength = 200): ?string
    {
        if (empty(trim($text))) {
            return null;
        }

        $prompt = "Summarize the following text in no more than {$maxLength} words. "
            . "Preserve key facts, dates, and names. Output only the summary, no preamble.\n\n"
            . "Text:\n{$text}";

        return $this->complete($prompt);
    }

    /**
     * Translate text to the target language.
     */
    public function translate(string $text, string $targetLang): ?string
    {
        if (empty(trim($text))) {
            return null;
        }

        $prompt = "Translate the following text into {$targetLang}. "
            . "Preserve proper nouns, dates, and archival terminology. "
            . "Output only the translation, no preamble.\n\n"
            . "Text:\n{$text}";

        return $this->complete($prompt);
    }

    /**
     * Generate a description suggestion from a title and optional context.
     */
    public function suggestDescription(string $title, string $context = ''): ?string
    {
        if (empty(trim($title))) {
            return null;
        }

        $prompt = "You are an archival description specialist. "
            . "Generate a concise scope and content note for an archival record.\n\n"
            . "Title: {$title}\n";

        if (!empty($context)) {
            $prompt .= "Additional context: {$context}\n";
        }

        $prompt .= "\nWrite a professional archival description (2-4 sentences) "
            . "following ISAD(G) standards. Output only the description.";

        return $this->complete($prompt);
    }

    /**
     * Extract named entities (NER) from text using the LLM.
     *
     * @return array ['persons' => [], 'organizations' => [], 'places' => [], 'dates' => []]
     */
    public function extractEntities(string $text): array
    {
        $default = ['persons' => [], 'organizations' => [], 'places' => [], 'dates' => []];

        if (empty(trim($text))) {
            return $default;
        }

        $prompt = "Extract all named entities from the following text. "
            . "Return ONLY a valid JSON object with these keys:\n"
            . "- \"persons\": array of person names\n"
            . "- \"organizations\": array of organization names\n"
            . "- \"places\": array of place/location names\n"
            . "- \"dates\": array of date references\n\n"
            . "Text:\n{$text}\n\n"
            . "JSON:";

        $result = $this->complete($prompt, ['temperature' => 0.1]);

        if (!$result) {
            return $default;
        }

        // Parse JSON from response - handle markdown code fences
        $cleaned = trim($result);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        $parsed = json_decode($cleaned, true);

        if (!is_array($parsed)) {
            Log::warning('LlmService::extractEntities - Failed to parse LLM response as JSON', [
                'response' => $result,
            ]);
            return $default;
        }

        return [
            'persons'       => $parsed['persons'] ?? [],
            'organizations' => $parsed['organizations'] ?? [],
            'places'        => $parsed['places'] ?? [],
            'dates'         => $parsed['dates'] ?? [],
        ];
    }

    /**
     * Spellcheck text and return corrections.
     *
     * @return array Array of ['original' => string, 'suggestion' => string, 'position' => int]
     */
    public function spellcheck(string $text): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $prompt = "Check the following text for spelling and grammar errors. "
            . "Return ONLY a valid JSON array of corrections. Each item must have:\n"
            . "- \"original\": the misspelled/incorrect word or phrase\n"
            . "- \"suggestion\": the corrected version\n"
            . "- \"position\": approximate character position in the text (0-based)\n\n"
            . "If there are no errors, return an empty array: []\n\n"
            . "Text:\n{$text}\n\n"
            . "JSON:";

        $result = $this->complete($prompt, ['temperature' => 0.1]);

        if (!$result) {
            return [];
        }

        $cleaned = trim($result);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        $parsed = json_decode($cleaned, true);

        if (!is_array($parsed)) {
            Log::warning('LlmService::spellcheck - Failed to parse LLM response', [
                'response' => $result,
            ]);
            return [];
        }

        // Validate structure of each correction
        $corrections = [];
        foreach ($parsed as $item) {
            if (isset($item['original'], $item['suggestion'])) {
                $corrections[] = [
                    'original'   => $item['original'],
                    'suggestion' => $item['suggestion'],
                    'position'   => (int) ($item['position'] ?? 0),
                ];
            }
        }

        return $corrections;
    }

    // ─── Health & Status ────────────────────────────────────────────

    /**
     * Get health status for all active providers.
     */
    public function getAllHealth(): array
    {
        $configs = $this->getConfigurations(true);
        $results = [];

        foreach ($configs as $config) {
            $results[$config->name] = $this->checkProviderHealth($config);
        }

        return $results;
    }

    /**
     * Test connection to a specific provider configuration.
     */
    public function testConnection(?int $configId = null): array
    {
        $config = $configId ? $this->getConfiguration($configId) : $this->getDefaultConfig();

        if (!$config) {
            return ['success' => false, 'error' => 'No configuration found'];
        }

        return $this->checkProviderHealth($config);
    }

    // ─── AI Settings Helpers ────────────────────────────────────────

    /**
     * Get an AI setting value. Checks ahg_ner_settings first (user-facing
     * AI Services settings page), then falls back to ahg_ai_settings (legacy).
     */
    public function getAiSetting(string $feature, string $key, ?string $default = null): ?string
    {
        try {
            // Primary: ahg_ner_settings (flat key-value from the settings UI)
            $value = DB::table('ahg_ner_settings')
                ->where('setting_key', $key)
                ->value('setting_value');
            if ($value !== null && $value !== '') {
                return $value;
            }
        } catch (\Exception $e) {}

        // Fallback: ahg_ai_settings (legacy feature-scoped table)
        $value = DB::table('ahg_ai_settings')
            ->where('feature', $feature)
            ->where('setting_key', $key)
            ->value('setting_value');

        return $value ?? $default;
    }

    /**
     * Save an AI setting value.
     */
    public function saveAiSetting(string $feature, string $key, ?string $value): void
    {
        $exists = DB::table('ahg_ai_settings')
            ->where('feature', $feature)
            ->where('setting_key', $key)
            ->exists();

        if ($exists) {
            DB::table('ahg_ai_settings')
                ->where('feature', $feature)
                ->where('setting_key', $key)
                ->update([
                    'setting_value' => $value,
                    'updated_at'    => now(),
                ]);
        } else {
            DB::table('ahg_ai_settings')->insert([
                'feature'       => $feature,
                'setting_key'   => $key,
                'setting_value' => $value,
                'updated_at'    => now(),
            ]);
        }
    }

    /**
     * Get all AI settings for a feature.
     */
    public function getAiSettingsByFeature(string $feature): \Illuminate\Support\Collection
    {
        return DB::table('ahg_ai_settings')
            ->where('feature', $feature)
            ->orderBy('setting_key')
            ->get();
    }

    /**
     * Get usage statistics.
     */
    public function getUsageStats(): array
    {
        $stats = [];

        // NER entity stats
        $stats['ner'] = DB::table('ahg_ner_entity')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'linked' THEN 1 ELSE 0 END) as linked,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            ")
            ->first();

        // Description suggestion stats
        try {
            $stats['suggestions'] = DB::table('ahg_description_suggestion')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(tokens_used) as total_tokens,
                    ROUND(AVG(generation_time_ms)) as avg_generation_ms
                ")
                ->first();
        } catch (\Exception $e) {
            $stats['suggestions'] = null;
        }

        // Config count
        $stats['config_count'] = DB::table('ahg_llm_config')->count();
        $stats['active_config_count'] = DB::table('ahg_llm_config')->where('is_active', 1)->count();

        return $stats;
    }

    // ─── Encryption ─────────────────────────────────────────────────

    /**
     * Encrypt an API key for storage.
     */
    public function encryptApiKey(string $apiKey): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::ENCRYPTION_METHOD));
        $encrypted = openssl_encrypt($apiKey, self::ENCRYPTION_METHOD, $this->encryptionKey, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a stored API key.
     */
    public function decryptApiKey(string $encrypted): string
    {
        $data      = base64_decode($encrypted);
        $ivLength  = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        $iv        = substr($data, 0, $ivLength);
        $encData   = substr($data, $ivLength);

        return openssl_decrypt($encData, self::ENCRYPTION_METHOD, $this->encryptionKey, 0, $iv) ?: '';
    }

    // ─── Private: Provider Dispatch ─────────────────────────────────

    /**
     * Dispatch a simple prompt to the configured provider (single prompt string).
     */
    private function dispatchToProvider(object $config, string $prompt, array $options = []): array
    {
        $systemPrompt = $options['system_prompt'] ?? 'You are a helpful assistant specializing in archival description and metadata.';

        return $this->dispatchToProviderFull($config, $systemPrompt, $prompt, $options);
    }

    /**
     * Dispatch system + user prompts to the configured provider.
     */
    private function dispatchToProviderFull(object $config, string $systemPrompt, string $userPrompt, array $options = []): array
    {
        // Decrypt API key if present
        $apiKey = null;
        if (!empty($config->api_key_encrypted)) {
            $apiKey = $this->decryptApiKey($config->api_key_encrypted);
        }

        $model       = $options['model'] ?? $config->model;
        $maxTokens   = $options['max_tokens'] ?? $config->max_tokens ?? 2000;
        $temperature = $options['temperature'] ?? (float) ($config->temperature ?? 0.7);
        $timeout     = $options['timeout'] ?? $config->timeout_seconds ?? 120;

        $startTime = microtime(true);

        try {
            switch ($config->provider) {
                case 'openai':
                    return $this->callOpenAI($config->endpoint_url ?? 'https://api.openai.com/v1', $apiKey, $systemPrompt, $userPrompt, $model, $maxTokens, $temperature, $timeout);

                case 'anthropic':
                    return $this->callAnthropic($config->endpoint_url ?? 'https://api.anthropic.com/v1', $apiKey, $systemPrompt, $userPrompt, $model, $maxTokens, $temperature, $timeout);

                case 'ollama':
                    return $this->callOllama($config->endpoint_url ?? 'http://localhost:11434', $systemPrompt, $userPrompt, $model, $maxTokens, $temperature, $timeout);

                default:
                    return [
                        'success'           => false,
                        'error'             => "Unknown provider: {$config->provider}",
                        'text'              => null,
                        'tokens_used'       => 0,
                        'model'             => $model,
                        'generation_time_ms' => round((microtime(true) - $startTime) * 1000),
                    ];
            }
        } catch (\Exception $e) {
            Log::error("LlmService dispatch error: " . $e->getMessage());

            return [
                'success'           => false,
                'error'             => $e->getMessage(),
                'text'              => null,
                'tokens_used'       => 0,
                'model'             => $model,
                'generation_time_ms' => round((microtime(true) - $startTime) * 1000),
            ];
        }
    }

    /**
     * POST to OpenAI chat completions API.
     */
    private function callOpenAI(string $endpoint, ?string $apiKey, string $systemPrompt, string $userPrompt, string $model, int $maxTokens, float $temperature, int $timeout): array
    {
        $startTime = microtime(true);

        if (empty($apiKey)) {
            return [
                'success'           => false,
                'error'             => 'OpenAI API key not configured',
                'text'              => null,
                'tokens_used'       => 0,
                'model'             => $model,
                'generation_time_ms' => 0,
            ];
        }

        $url = rtrim($endpoint, '/') . '/chat/completions';

        $response = Http::timeout($timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])
            ->post($url, [
                'model'      => $model,
                'messages'   => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens'  => $maxTokens,
                'temperature' => $temperature,
            ]);

        $generationTime = round((microtime(true) - $startTime) * 1000);

        if ($response->failed()) {
            $body = $response->json();
            $errorMsg = $body['error']['message'] ?? ('HTTP ' . $response->status());
            Log::error("OpenAI API error: {$errorMsg}");

            return [
                'success'           => false,
                'error'             => $errorMsg,
                'text'              => null,
                'tokens_used'       => 0,
                'model'             => $model,
                'generation_time_ms' => $generationTime,
            ];
        }

        $body      = $response->json();
        $text      = $body['choices'][0]['message']['content'] ?? '';
        $tokensUsed = $body['usage']['total_tokens'] ?? 0;

        return [
            'success'           => true,
            'text'              => trim($text),
            'tokens_used'       => $tokensUsed,
            'model'             => $body['model'] ?? $model,
            'generation_time_ms' => $generationTime,
            'error'             => null,
        ];
    }

    /**
     * POST to Anthropic messages API.
     */
    private function callAnthropic(string $endpoint, ?string $apiKey, string $systemPrompt, string $userPrompt, string $model, int $maxTokens, float $temperature, int $timeout): array
    {
        $startTime = microtime(true);

        if (empty($apiKey)) {
            return [
                'success'           => false,
                'error'             => 'Anthropic API key not configured',
                'text'              => null,
                'tokens_used'       => 0,
                'model'             => $model,
                'generation_time_ms' => 0,
            ];
        }

        $url = rtrim($endpoint, '/') . '/messages';

        $payload = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'system'     => $systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        if ($temperature > 0) {
            $payload['temperature'] = $temperature;
        }

        $response = Http::timeout($timeout)
            ->withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version'  => self::ANTHROPIC_VERSION,
                'Content-Type'       => 'application/json',
            ])
            ->post($url, $payload);

        $generationTime = round((microtime(true) - $startTime) * 1000);

        if ($response->failed()) {
            $body = $response->json();
            $errorMsg = $body['error']['message'] ?? ('HTTP ' . $response->status());
            Log::error("Anthropic API error: {$errorMsg}");

            return [
                'success'           => false,
                'error'             => $errorMsg,
                'text'              => null,
                'tokens_used'       => 0,
                'model'             => $model,
                'generation_time_ms' => $generationTime,
            ];
        }

        $body = $response->json();

        // Extract text from content blocks
        $text = '';
        if (isset($body['content']) && is_array($body['content'])) {
            foreach ($body['content'] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $text .= $block['text'] ?? '';
                }
            }
        }

        $tokensUsed = 0;
        if (isset($body['usage'])) {
            $tokensUsed = ($body['usage']['input_tokens'] ?? 0) + ($body['usage']['output_tokens'] ?? 0);
        }

        return [
            'success'           => true,
            'text'              => trim($text),
            'tokens_used'       => $tokensUsed,
            'model'             => $body['model'] ?? $model,
            'generation_time_ms' => $generationTime,
            'error'             => null,
            'stop_reason'       => $body['stop_reason'] ?? null,
        ];
    }

    /**
     * POST to local Ollama instance.
     */
    private function callOllama(string $endpoint, string $systemPrompt, string $userPrompt, string $model, int $maxTokens, float $temperature, int $timeout): array
    {
        $startTime = microtime(true);

        $url = rtrim($endpoint, '/') . '/api/generate';

        $response = Http::timeout($timeout)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'model'  => $model,
                'prompt' => $userPrompt,
                'system' => $systemPrompt,
                'stream' => false,
                'options' => [
                    'num_predict'  => $maxTokens,
                    'temperature'  => $temperature,
                ],
            ]);

        $generationTime = round((microtime(true) - $startTime) * 1000);

        if ($response->failed()) {
            $errorMsg = 'Ollama HTTP ' . $response->status();
            Log::error("Ollama API error: {$errorMsg}");

            return [
                'success'           => false,
                'error'             => $errorMsg,
                'text'              => null,
                'tokens_used'       => 0,
                'model'             => $model,
                'generation_time_ms' => $generationTime,
            ];
        }

        $body      = $response->json();
        $text      = $body['response'] ?? '';
        $tokensUsed = ($body['prompt_eval_count'] ?? 0) + ($body['eval_count'] ?? 0);

        return [
            'success'           => true,
            'text'              => trim($text),
            'tokens_used'       => $tokensUsed,
            'model'             => $body['model'] ?? $model,
            'generation_time_ms' => $generationTime,
            'error'             => null,
        ];
    }

    /**
     * Check health of a specific provider config.
     */
    private function checkProviderHealth(object $config): array
    {
        $apiKey = null;
        if (!empty($config->api_key_encrypted)) {
            $apiKey = $this->decryptApiKey($config->api_key_encrypted);
        }

        try {
            switch ($config->provider) {
                case 'ollama':
                    $url      = rtrim($config->endpoint_url ?? 'http://localhost:11434', '/');
                    $response = Http::timeout(10)->get($url . '/api/tags');
                    if ($response->successful()) {
                        $body   = $response->json();
                        $models = [];
                        foreach (($body['models'] ?? []) as $m) {
                            $models[] = $m['name'] ?? $m['model'] ?? 'unknown';
                        }
                        // Fetch version
                        $vResp   = Http::timeout(5)->get($url . '/api/version');
                        $version = $vResp->successful() ? ($vResp->json()['version'] ?? 'unknown') : 'unknown';

                        return [
                            'status'        => 'ok',
                            'provider'      => 'ollama',
                            'config_id'     => $config->id,
                            'models'        => $models,
                            'version'       => $version,
                            'endpoint'      => $url,
                            'default_model' => $config->model,
                            'error'         => null,
                        ];
                    }

                    return [
                        'status'    => 'error',
                        'provider'  => 'ollama',
                        'config_id' => $config->id,
                        'error'     => 'Cannot connect to Ollama at ' . $url,
                    ];

                case 'openai':
                    if (empty($apiKey)) {
                        return [
                            'status'    => 'error',
                            'provider'  => 'openai',
                            'config_id' => $config->id,
                            'error'     => 'API key not configured',
                        ];
                    }
                    $url      = rtrim($config->endpoint_url ?? 'https://api.openai.com/v1', '/');
                    $response = Http::timeout(10)
                        ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                        ->get($url . '/models');

                    if ($response->successful()) {
                        $body   = $response->json();
                        $models = [];
                        foreach (($body['data'] ?? []) as $m) {
                            $id = $m['id'] ?? '';
                            if (str_contains($id, 'gpt') || str_contains($id, 'o1')) {
                                $models[] = $id;
                            }
                        }
                        sort($models);

                        return [
                            'status'        => 'ok',
                            'provider'      => 'openai',
                            'config_id'     => $config->id,
                            'models'        => $models,
                            'version'       => 'v1',
                            'endpoint'      => $url,
                            'default_model' => $config->model,
                            'error'         => null,
                        ];
                    }

                    return [
                        'status'    => 'error',
                        'provider'  => 'openai',
                        'config_id' => $config->id,
                        'error'     => 'API returned HTTP ' . $response->status(),
                    ];

                case 'anthropic':
                    if (empty($apiKey)) {
                        return [
                            'status'    => 'error',
                            'provider'  => 'anthropic',
                            'config_id' => $config->id,
                            'error'     => 'API key not configured',
                        ];
                    }

                    // Anthropic has no lightweight health endpoint; report configured status
                    $knownModels = [
                        'claude-3-opus-20240229',
                        'claude-3-sonnet-20240229',
                        'claude-3-haiku-20240307',
                        'claude-3-5-sonnet-20241022',
                        'claude-3-5-haiku-20241022',
                        'claude-sonnet-4-20250514',
                    ];

                    return [
                        'status'        => 'configured',
                        'provider'      => 'anthropic',
                        'config_id'     => $config->id,
                        'models'        => $knownModels,
                        'version'       => self::ANTHROPIC_VERSION,
                        'endpoint'      => $config->endpoint_url ?? 'https://api.anthropic.com/v1',
                        'default_model' => $config->model,
                        'error'         => null,
                        'note'          => 'API key configured but not verified (would cost tokens)',
                    ];

                default:
                    return [
                        'status'    => 'error',
                        'provider'  => $config->provider,
                        'config_id' => $config->id,
                        'error'     => 'Unknown provider: ' . $config->provider,
                    ];
            }
        } catch (\Exception $e) {
            return [
                'status'    => 'error',
                'provider'  => $config->provider,
                'config_id' => $config->id,
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Get or generate the encryption key.
     */
    private function getEncryptionKey(): string
    {
        try {
            $setting = DB::table('ahg_ai_settings')
                ->where('feature', 'general')
                ->where('setting_key', 'encryption_key')
                ->first();

            if ($setting && !empty($setting->setting_value)) {
                return $setting->setting_value;
            }
        } catch (\Exception $e) {
            // DB may not be available yet during boot
        }

        // Fallback: derive from Laravel APP_KEY
        $appKey = config('app.key', 'heratio_default_key');

        return hash('sha256', 'ahg_llm_' . $appKey);
    }
}
