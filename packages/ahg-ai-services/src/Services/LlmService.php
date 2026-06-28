<?php

/**
 * LlmService - Service for Heratio
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
    public function complete(string $prompt, array $options = [], ?int $digitalObjectId = null): ?string
    {
        // #750 - resolve embedded-metadata hints and inject as a system-prompt
        // prefix before any dispatch path sees the prompt. Empty hint set is
        // a silent no-op. The audit event is emitted on the success path.
        $contextHints = $this->resolveContextHints($digitalObjectId);
        if (!$contextHints->isEmpty()) {
            $prompt = $contextHints->toPromptPrefix() . "\n\n" . $prompt;
        }

        // #667 Phase 1 - per-tenant quota gate. Runs BEFORE every dispatch
        // path (cloud-mode override + local provider table). Throws
        // QuotaExceededException up to the caller when the limit is hit;
        // the caller (controller / job) is responsible for translating
        // that into an HTTP 429 or batch-failure event.
        // When a caller higher up the stack has already debited the
        // bucket (e.g. translate() called us with skip_quota_gate=true),
        // we record cost only.
        if (empty($options['skip_quota_gate'])) {
            try {
                app(\AhgAiServices\Services\QuotaService::class)->consume($options['quota_service'] ?? 'llm');
            } catch (\AhgAiServices\Exceptions\QuotaExceededException $e) {
                Log::info('[ahg-ai] LLM call blocked by quota gate', $e->toArray());
                throw $e;
            } catch (\Throwable $e) {
                // service not registered or DB hiccup - never block the call
                Log::warning('[ahg-ai] quota gate soft-failed: ' . $e->getMessage());
            }
        }
        $t0 = microtime(true);

        // #69: ai_services_processing_mode + api_url/key/timeout drive a master
        // 'cloud' override that bypasses the per-provider config table. Used
        // when the operator wants a single hosted endpoint to handle all calls
        // (e.g. self-hosted vLLM at a stable URL). 'hybrid' falls through to
        // the per-provider table; 'local' also falls through.
        $aiSet = class_exists(\AhgAiServices\Support\AiServicesSettings::class)
                ? \AhgAiServices\Support\AiServicesSettings::class : null;
        if ($aiSet && $aiSet::processingMode() === 'cloud') {
            $url = $aiSet::apiUrl();
            $key = $aiSet::apiKey();
            $tmo = $aiSet::apiTimeout();
            if ($url) {
                // #141 - the cloud-mode override is its own dispatch path, so
                // it needs the guardrail too. block -> abort; mask -> redacted prompt.
                try {
                    $guard = new GuardrailService();
                    $gi = $guard->inspect([
                        'provider'      => 'cloud',
                        'system_prompt' => '',
                        'user_prompt'   => $prompt,
                        'data_scope'    => $options['data_scope'] ?? null,
                        'purpose'       => $options['purpose'] ?? null,
                    ]);
                    if (($gi['action'] ?? 'allow') === 'block') {
                        Log::warning('[ahg-ai] cloud-mode call blocked by guardrail: ' . ($gi['reason'] ?? ''));
                        return null;
                    }
                    $prompt = $gi['user_prompt'];
                } catch (\Throwable $e) {
                    Log::warning('[ahg-ai] cloud-mode guardrail failed, proceeding: ' . $e->getMessage());
                }
                try {
                    $req = \Illuminate\Support\Facades\Http::timeout($tmo)->asJson();
                    if ($key) { $req = $req->withToken($key); }
                    $resp = $req->post(rtrim($url, '/') . '/v1/chat/completions', [
                        'messages' => [['role' => 'user', 'content' => $prompt]],
                        'temperature' => $options['temperature'] ?? 0.2,
                    ]);
                    if ($resp->ok()) {
                        $body = $resp->json();
                        $text = $body['choices'][0]['message']['content'] ?? null;
                        if (is_string($text) && $text !== '') {
                            $this->logInferenceReceipt('llm', $aiSet::apiUrl(), null, $prompt, $text, []);
                            $this->recordCost('llm', $aiSet::apiUrl(), [
                                'tokens_in'   => (int) ($body['usage']['prompt_tokens'] ?? 0),
                                'tokens_out'  => (int) ($body['usage']['completion_tokens'] ?? 0),
                                'duration_ms' => (int) round((microtime(true) - $t0) * 1000),
                            ]);
                            $this->logContextEventIfAny('llm', $digitalObjectId, $contextHints);
                            return $text;
                        }
                    }
                    Log::warning('[ahg-ai] cloud-mode endpoint returned no text', ['status' => $resp->status()]);
                } catch (\Throwable $e) {
                    Log::warning('[ahg-ai] cloud-mode endpoint threw, falling through to local: ' . $e->getMessage());
                }
            }
        }

        $configId = $options['config_id'] ?? null;
        $config   = $configId ? $this->getConfiguration($configId) : $this->getDefaultConfig();

        if (!$config) {
            Log::warning('LlmService::complete - No LLM configuration found');
            return null;
        }

        $result = $this->dispatchToProvider($config, $prompt, $options);

        if (!empty($result['success']) && isset($result['text']) && is_string($result['text']) && $result['text'] !== '') {
            $modelId = (string) ($result['model'] ?? ($config->name ?? 'unknown'));
            $this->logInferenceReceipt(
                'llm',
                $modelId,
                $config->model_version ?? null,
                $prompt,
                $result['text'],
                ['tokens_in' => $result['tokens_used'] ?? null],
            );
            $this->recordCost('llm', $modelId, [
                'tokens_in'   => (int) ($result['tokens_in']  ?? $result['tokens_used'] ?? 0),
                'tokens_out'  => (int) ($result['tokens_out'] ?? 0),
                'duration_ms' => (int) round((microtime(true) - $t0) * 1000),
            ]);
            $this->logContextEventIfAny('llm', $digitalObjectId, $contextHints);
        }

        return $result['success'] ? $result['text'] : null;
    }

    /**
     * #750 - resolve embedded-metadata hints. Safe no-op when no DO id.
     */
    private function resolveContextHints(?int $digitalObjectId): \AhgAiServices\DTO\AiContextHints
    {
        if ($digitalObjectId === null || $digitalObjectId <= 0) {
            return \AhgAiServices\DTO\AiContextHints::empty();
        }
        try {
            return app(\AhgAiServices\Services\EmbeddedMetadataContextService::class)
                ->forDigitalObject($digitalObjectId);
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] LlmService resolveContextHints failed: ' . $e->getMessage());
            return \AhgAiServices\DTO\AiContextHints::empty();
        }
    }

    /**
     * #750 - audit event after a successful completion.
     */
    private function logContextEventIfAny(string $service, ?int $digitalObjectId, \AhgAiServices\DTO\AiContextHints $hints): void
    {
        if ($digitalObjectId === null || $digitalObjectId <= 0 || $hints->isEmpty()) {
            return;
        }
        try {
            app(\AhgAiServices\Services\EmbeddedMetadataContextService::class)
                ->logContextEvent($service, $digitalObjectId, $hints);
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] LlmService logContextEventIfAny failed: ' . $e->getMessage());
        }
    }

    /**
     * #667 Phase 1 - persist one cost-ledger row. Mirrors logInferenceReceipt
     * in that it fails soft: a cost-ledger insert must never abort the
     * inference dispatch.
     *
     * @param array{tokens_in?:int,tokens_out?:int,duration_ms?:int} $meta
     */
    private function recordCost(string $service, string $modelId, array $meta = []): void
    {
        try {
            app(\AhgAiServices\Services\CostService::class)->record($service, $modelId, $meta);
        } catch (\Throwable) {
            // never block inference
        }
    }

    /**
     * EU AI Act Article 12 - log one inference call to the tamper-evident
     * chain. Fails soft: a logging failure must not abort the inference
     * caller's request flow. See packages/ahg-ai-compliance/ for the chain
     * implementation and packages/ahg-inference-receipts/ for the protocol.
     */
    private function logInferenceReceipt(
        string $service,
        string $modelId,
        ?string $modelVersion,
        string $input,
        string $output,
        array $extra = [],
    ): void {
        if (!class_exists(\AhgAiCompliance\Services\InferenceLogger::class)) {
            return;
        }
        try {
            app(\AhgAiCompliance\Services\InferenceLogger::class)
                ->log($service, $modelId, $modelVersion, $input, $output, $extra);
        } catch (\Throwable) {
            // chain failure must not abort inference
        }
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

        // #141 - the RAG provenance bundle: the retrieved source fields the
        // grounding check scores the generated description against.
        $contextSources = array_values(array_filter([
            $context['data']['title'] ?? null,
            $context['data']['scope_and_content'] ?? null,
            $context['data']['archival_history'] ?? null,
            $context['data']['extent_and_medium'] ?? null,
            $context['data']['arrangement'] ?? null,
            $context['data']['physical_characteristics'] ?? null,
            $context['data']['acquisition'] ?? null,
            $context['data']['ocr_text'] ?? null,
        ], static fn ($v) => is_string($v) && trim($v) !== ''));

        $result = $this->completeFull($prompts['system'], $prompts['user'], $configId, [
            'purpose'         => 'description_generation',
            'data_scope'      => 'internal',
            'context_sources' => $contextSources,
        ]);

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

        // Issue #61 Phase 2c: record the LLM inference. Input = built user
        // prompt (the canonical thing the model actually saw), output = the
        // generated text. Standard tag depends on the prompt template's
        // intent; we use RiC-O-scope_and_content as the default since
        // generateSuggestion targets that field today.
        try {
            $svc = app(\AhgProvenanceAi\Services\InferenceService::class);
            [$inH, $inE]   = \AhgProvenanceAi\DTO\InferenceRecord::hashAndExcerpt((string) ($prompts['user'] ?? ''));
            [$outH, $outE] = \AhgProvenanceAi\DTO\InferenceRecord::hashAndExcerpt((string) ($result['text'] ?? ''));
            $svc->record(new \AhgProvenanceAi\DTO\InferenceRecord(
                serviceName:      'LLM',
                modelName:        (string) ($result['model'] ?? 'unknown'),
                modelVersion:     'unknown',
                inputHash:        $inH,
                outputHash:       $outH,
                targetEntityType: 'information_object',
                targetEntityId:   (int) $objectId,
                targetField:      'scope_and_content',
                confidence:       null,
                standard:         'RiC-O-scope_and_content',
                endpoint:         (string) ($result['endpoint'] ?? ''),
                inputExcerpt:     $inE,
                outputExcerpt:    $outE,
                elapsedMs:        isset($result['generation_time_ms']) ? (int) $result['generation_time_ms'] : null,
                userId:           $userId,
                guardrail:        $result['guardrail'] ?? null,
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('LlmService::generateSuggestion: inference write failed: ' . $e->getMessage());
        }

        return [
            'success'           => true,
            'suggestion_id'     => (int) $suggestionId,
            'text'              => $result['text'] ?? null,
            'existing_text'     => $context['data']['scope_and_content'] ?? null,
            'tokens_used'       => $result['tokens_used'] ?? 0,
            'model'             => $result['model'] ?? null,
            'generation_time_ms'=> $result['generation_time_ms'] ?? 0,
            'template_name'     => $template->name ?? '',
            'guardrail'         => $result['guardrail'] ?? null,
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

        $result = $this->dispatchToProviderFull($config, $systemPrompt, $userPrompt, $options);

        if (!empty($result['success']) && isset($result['text']) && is_string($result['text']) && $result['text'] !== '') {
            $this->logInferenceReceipt(
                'llm',
                (string) ($result['model'] ?? ($config->name ?? 'unknown')),
                $config->model_version ?? null,
                ($systemPrompt === '' ? '' : "system:{$systemPrompt}\n") . "user:{$userPrompt}",
                $result['text'],
                [
                    'tokens_in'  => $result['tokens_used'] ?? null,
                    'latency_ms' => isset($result['generation_time_ms']) ? (int) $result['generation_time_ms'] : null,
                ],
            );
        }

        return $result;
    }

    /**
     * Summarize text using the active LLM.
     */
    public function summarize(string $text, ?int $maxLength = null): ?string
    {
        if (empty(trim($text))) {
            return null;
        }

        // #69: defaults from operator AI Services settings unless caller overrides.
        $aiSet = class_exists(\AhgAiServices\Support\AiServicesSettings::class)
                ? \AhgAiServices\Support\AiServicesSettings::class : null;
        if ($aiSet && !$aiSet::summarizerEnabled()) {
            return null; // master gate off - no work, no cost
        }
        $maxLength = $maxLength ?? ($aiSet ? $aiSet::summarizerMaxLength() : 200);
        $minLength = $aiSet ? $aiSet::summarizerMinLength() : 30;

        $prompt = "Summarize the following text in {$minLength} to {$maxLength} words. "
            . "Preserve key facts, dates, and names. Output only the summary, no preamble.\n\n"
            . "Text:\n{$text}";

        return $this->complete($prompt);
    }

    /**
     * Translate text to the target language.
     */
    public function translate(string $text, string $targetLang, string $sourceLang = ''): ?string
    {
        if (empty(trim($text))) {
            return null;
        }

        // #69: master gate + dual-mode dispatch (mt endpoint vs LLM round-trip).
        $aiSet = class_exists(\AhgAiServices\Support\AiServicesSettings::class)
                ? \AhgAiServices\Support\AiServicesSettings::class : null;
        if ($aiSet && !$aiSet::translationEnabled()) {
            return null;
        }

        // #667 Phase 1 - translation-memory cache lookup. On hit we skip
        // every dispatch path entirely; the call is free + instant. On
        // miss we fall through, then store the result at the end.
        $tm = null;
        try {
            $tm = app(\AhgAiServices\Services\TranslationMemoryService::class);
            $cached = $tm->lookup($text, $sourceLang, $targetLang);
            if ($cached !== null) {
                return $cached;
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] TM lookup unavailable: ' . $e->getMessage());
        }

        // #667 Phase 1 - quota gate for translate (separate from the llm
        // bucket so operators can throttle translate independently).
        try {
            app(\AhgAiServices\Services\QuotaService::class)->consume('translate');
        } catch (\AhgAiServices\Exceptions\QuotaExceededException $e) {
            Log::info('[ahg-ai] translate blocked by quota', $e->toArray());
            throw $e;
        } catch (\Throwable) {
            // soft-fail
        }

        // #128: SA-language targets route to MzansiLM when the operator has
        // enabled it - qwen3 produces Dutch-flavoured / hallucinated output for
        // those languages. Returns null (fall through to the MT/LLM path) when
        // MzansiLM is not configured for this locale or the call fails.
        $viaMzansi = $this->translateViaMzansiLm($text, $targetLang);
        if ($viaMzansi !== null) {
            if ($tm !== null) {
                try { $tm->store($text, $sourceLang, $targetLang, $viaMzansi, 'mzansilm', null); } catch (\Throwable) {}
            }
            return $viaMzansi;
        }

        $mode = $aiSet ? $aiSet::translationMode() : 'llm';
        $mtEp = $aiSet ? $aiSet::mtEndpoint() : null;
        if ($mode === 'mt' && $mtEp) {
            try {
                // #1250 - the MT endpoint can be the AHG AI gateway, which
                // requires a Bearer key. Reuse the same gateway-key resolution
                // the rest of the AI services use (ahg_ner_settings.api_key,
                // then ahg_ai_settings feature='general' api_key) via the
                // existing getAiSetting() helper. Only attach when non-empty so
                // a legacy direct-adapter MT endpoint still works keyless.
                $mtKey = (string) ($this->getAiSetting('general', 'api_key', '') ?? '');
                $req = \Illuminate\Support\Facades\Http::timeout($aiSet::mtTimeout())
                    ->asJson();
                if ($mtKey !== '') {
                    $req = $req->withToken($mtKey);
                }
                $resp = $req
                    ->post(rtrim($mtEp, '/') . '/translate', [
                        'text'   => $text,
                        'target' => $targetLang,
                    ]);
                if ($resp->ok()) {
                    $body = $resp->json();
                    // Local MT adapter returns `translation`; the gateway
                    // /ai/v1/translate route returns `translated` (opus-mt).
                    $mt = is_array($body) ? ($body['translation'] ?? $body['translated'] ?? $body['translatedText'] ?? null) : null;
                    if (is_string($mt) && $mt !== '') {
                        if ($tm !== null) {
                            try { $tm->store($text, $sourceLang, $targetLang, $mt, 'mt', null); } catch (\Throwable) {}
                        }
                        return $mt;
                    }
                }
                Log::warning('[ahg-ai] MT endpoint failed; falling back to LLM round-trip', [
                    'status' => $resp->status(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('[ahg-ai] MT endpoint threw; falling back to LLM round-trip: ' . $e->getMessage());
            }
        }

        $prompt = "Translate the following text into {$targetLang}. "
            . "Preserve proper nouns, dates, and archival terminology. "
            . "Output only the translation, no preamble.\n\n"
            . "Text:\n{$text}";

        // The translate gate has already debited one unit above; tell the
        // downstream complete() to not double-debit by tagging the bucket
        // explicitly. complete() will use 'translate' (a known bucket) and
        // the per-call accounting will still record the right service.
        $out = $this->complete($prompt, ['skip_quota_gate' => true]);
        if ($out !== null && $tm !== null) {
            try { $tm->store($text, $sourceLang, $targetLang, $out, 'machine', null); } catch (\Throwable) {}
        }
        return $out;
    }

    /**
     * #128: route an SA-language translation to MzansiLM-125M.
     *
     * MzansiLM is purpose-trained on the MzansiText corpus (all 11 official SA
     * languages); qwen3 has near-zero African-language pretraining and produces
     * Dutch-flavoured / hallucinated output for these locales.
     *
     * Returns the translated text, or null when MzansiLM is not enabled, not
     * configured for the target locale, or the call fails - the caller then
     * falls through to the default MT / LLM path. The MzansiLM endpoint is
     * operator-configured (no hardcoded host).
     */
    private function translateViaMzansiLm(string $text, string $targetLang): ?string
    {
        $aiSet = class_exists(\AhgAiServices\Support\AiServicesSettings::class)
                ? \AhgAiServices\Support\AiServicesSettings::class : null;
        if (!$aiSet || !$aiSet::mzansilmEnabled()) {
            return null;
        }

        // Match either the full locale (e.g. zu_ZA) or its base subtag (zu).
        $locale = strtolower(trim($targetLang));
        $base   = preg_split('/[-_]/', $locale)[0] ?? $locale;
        $routed = $aiSet::mzansilmLocales();
        if (!in_array($locale, $routed, true) && !in_array($base, $routed, true)) {
            return null;
        }

        $endpoint = $aiSet::mzansilmEndpoint();
        if (empty($endpoint)) {
            Log::warning('[ahg-ai] MzansiLM enabled for ' . $targetLang . ' but mzansilm_endpoint is unset; falling back');
            return null;
        }

        try {
            $t0 = microtime(true);
            $resp = \Illuminate\Support\Facades\Http::timeout($aiSet::mzansilmTimeout())
                ->asJson()
                ->post(rtrim($endpoint, '/') . '/chat/completions', [
                    'model'       => $aiSet::mzansilmModel(),
                    'messages'    => [[
                        'role'    => 'user',
                        'content' => "Translate the following text into {$targetLang}. "
                                   . "Preserve proper nouns, dates, and archival terminology. "
                                   . "Output only the translation, no preamble.\n\n{$text}",
                    ]],
                    'temperature' => 0.2,
                ]);
            if ($resp->ok()) {
                $out = $resp->json()['choices'][0]['message']['content'] ?? null;
                if (is_string($out) && trim($out) !== '') {
                    $this->logInferenceReceipt(
                        'translate',
                        (string) $aiSet::mzansilmModel(),
                        'mzansilm',
                        "lang:{$targetLang}\n{$text}",
                        $out,
                        ['latency_ms' => (int) round((microtime(true) - $t0) * 1000)],
                    );
                    return $out;
                }
            }
            Log::warning('[ahg-ai] MzansiLM translate returned no text; falling back', [
                'lang' => $targetLang, 'status' => $resp->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] MzansiLM translate failed; falling back: ' . $e->getMessage());
        }

        return null;
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
    public function spellcheck(string $text, array $options = []): array
    {
        if (empty(trim($text))) {
            return [];
        }

        // #69: master gate + locale.
        $aiSet = class_exists(\AhgAiServices\Support\AiServicesSettings::class)
                ? \AhgAiServices\Support\AiServicesSettings::class : null;
        if ($aiSet && !$aiSet::spellcheckEnabled()) {
            return [];
        }
        $lang = $options['lang'] ?? ($aiSet ? $aiSet::spellcheckLanguage() : 'en');

        // #667 Phase 1 - quota gate for spellcheck.
        try {
            app(\AhgAiServices\Services\QuotaService::class)->consume('spellcheck');
        } catch (\AhgAiServices\Exceptions\QuotaExceededException $e) {
            Log::info('[ahg-ai] spellcheck blocked by quota', $e->toArray());
            throw $e;
        } catch (\Throwable) {
            // soft-fail
        }

        // #667 Phase 1 - inline mode. The legacy contract returned
        // {original, suggestion, position} per error; inline mode now
        // also includes {position_start, position_end} computed from the
        // first exact-match search of `original` in the input text. The
        // original keys remain for backward compatibility.
        $prompt = "Check the following text in language '{$lang}' for spelling and grammar errors. "
            . "Preserve formatting (line breaks, punctuation) - do not rewrite the text. "
            . "Return ONLY a valid JSON array of corrections. Each item must have:\n"
            . "- \"original\": the misspelled/incorrect word or phrase as it appears verbatim in the text\n"
            . "- \"suggestion\": the corrected version\n"
            . "- \"suggestions\": (optional) array of alternative corrections\n"
            . "- \"position\": approximate character position in the text (0-based)\n\n"
            . "If there are no errors, return an empty array: []\n\n"
            . "Text:\n{$text}\n\n"
            . "JSON:";

        $result = $this->complete($prompt, ['temperature' => 0.1, 'skip_quota_gate' => true]);

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

        // Validate structure of each correction + compute inline offsets.
        $corrections = [];
        foreach ($parsed as $item) {
            if (!isset($item['original'], $item['suggestion'])) {
                continue;
            }
            $original   = (string) $item['original'];
            $suggestion = (string) $item['suggestion'];

            // Hint: start search from the LLM's reported position so we
            // pick the right occurrence in a doc with the same misspelling
            // multiple times.
            $hint = max(0, (int) ($item['position'] ?? 0) - 16);
            $start = stripos($text, $original, $hint);
            if ($start === false) {
                $start = stripos($text, $original);
            }
            $end = ($start === false) ? null : $start + strlen($original);

            $suggestions = $item['suggestions'] ?? [$suggestion];
            if (!is_array($suggestions)) {
                $suggestions = [$suggestion];
            }

            $corrections[] = [
                'original'       => $original,
                'suggestion'     => $suggestion,
                'suggestions'    => array_values(array_filter($suggestions, 'is_string')),
                'position'       => (int) ($item['position'] ?? 0),
                'position_start' => $start === false ? null : (int) $start,
                'position_end'   => $end,
            ];
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
        $model       = $options['model'] ?? $config->model;
        $maxTokens   = $options['max_tokens'] ?? $config->max_tokens ?? 2000;
        $temperature = $options['temperature'] ?? (float) ($config->temperature ?? 0.7);
        $timeout     = $options['timeout'] ?? $config->timeout_seconds ?? 120;

        // #141 - apply RAG guardrails before the prompt leaves the building.
        // Fail-open on an unexpected guardrail error: a bug in the policy
        // layer must never deny the AI service (deliberate `block` decisions
        // still block).
        $guard   = null;
        $inspect = null;
        try {
            $guard   = new GuardrailService();
            $inspect = $guard->inspect([
                'provider'      => $config->provider ?? '',
                'model'         => (string) $model,
                'system_prompt' => $systemPrompt,
                'user_prompt'   => $userPrompt,
                'data_scope'    => $options['data_scope'] ?? null,
                'purpose'       => $options['purpose'] ?? null,
            ]);
            if (($inspect['action'] ?? 'allow') === 'block') {
                return [
                    'success'            => false,
                    'error'              => $inspect['reason'] ?? 'Blocked by AI guardrail policy',
                    'text'               => null,
                    'tokens_used'        => 0,
                    'model'              => $model,
                    'generation_time_ms' => 0,
                    'blocked'            => true,
                    'guardrail'          => $guard->summarize($inspect, null),
                ];
            }
            // Dispatch the possibly-masked prompts.
            $systemPrompt = $inspect['system_prompt'];
            $userPrompt   = $inspect['user_prompt'];
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] guardrail inspect failed, proceeding unguarded: ' . $e->getMessage());
            $guard = null;
            $inspect = null;
        }

        // Decrypt API key if present
        $apiKey = null;
        if (!empty($config->api_key_encrypted)) {
            $apiKey = $this->decryptApiKey($config->api_key_encrypted);
        }

        $startTime = microtime(true);

        try {
            switch ($config->provider) {
                case 'openai':
                    $result = $this->callOpenAI($config->endpoint_url ?? 'https://api.openai.com/v1', $apiKey, $systemPrompt, $userPrompt, $model, $maxTokens, $temperature, $timeout);
                    break;

                case 'anthropic':
                    $result = $this->callAnthropic($config->endpoint_url ?? 'https://api.anthropic.com/v1', $apiKey, $systemPrompt, $userPrompt, $model, $maxTokens, $temperature, $timeout);
                    break;

                case 'ollama':
                    // #1368 — route Ollama completion through the AHG AI gateway,
                    // never a direct :11434 node. resolveOllamaBase() rejects a
                    // raw-node endpoint_url (stale ahg_llm_config row) and falls
                    // back to ai.theahg.co.za/ai/v1.
                    $result = $this->callOllama($this->resolveOllamaBase($config->endpoint_url ?? null), $systemPrompt, $userPrompt, $model, $maxTokens, $temperature, $timeout);
                    break;

                default:
                    $result = [
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

            $result = [
                'success'           => false,
                'error'             => $e->getMessage(),
                'text'              => null,
                'tokens_used'       => 0,
                'model'             => $model,
                'generation_time_ms' => round((microtime(true) - $startTime) * 1000),
            ];
        }

        // #141 - grounding check on the RAG output + attach the guardrail
        // summary so callers (and the inference record) can see the verdict.
        if ($guard !== null && $inspect !== null) {
            try {
                $grounding = null;
                if (!empty($result['success']) && !empty($result['text'])
                    && !empty($options['context_sources']) && is_array($options['context_sources'])) {
                    $grounding = $guard->checkGrounding((string) $result['text'], $options['context_sources']);
                }
                $result['guardrail'] = $guard->summarize($inspect, $grounding);
            } catch (\Throwable $e) {
                Log::warning('[ahg-ai] guardrail post-check failed: ' . $e->getMessage());
            }
        }

        return $result;
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
     * Resolve the Ollama base URL, routed through the AHG AI gateway (#1368).
     *
     * A raw-node endpoint_url (`:11434`, localhost, or a LAN/private IP) is
     * rejected so a stale `ahg_llm_config` row can neither bypass the gateway
     * (metering/quota/failover) nor break the /ollama passthrough; we fall back
     * to the configured general api_url (if itself a gateway URL) then to the
     * canonical gateway. Mirrors ahg-discovery's OllamaPageIndexClient.
     */
    private function resolveOllamaBase(?string $endpointUrl): string
    {
        $override = is_string($endpointUrl) ? trim($endpointUrl) : '';
        if ($override !== '' && ! self::looksLikeNode($override)) {
            return rtrim($override, '/');
        }
        $setting = (string) ($this->getAiSetting('general', 'api_url', '') ?? '');
        if ($setting !== '' && ! self::looksLikeNode($setting)) {
            return rtrim($setting, '/');
        }

        return 'https://ai.theahg.co.za/ai/v1';
    }

    /** True when a URL points at a raw GPU node rather than the gateway (#1368). */
    private static function looksLikeNode(string $url): bool
    {
        return (bool) preg_match('~:11434|://(?:127\.0\.0\.1|localhost|192\.168\.|10\.|172\.(?:1[6-9]|2\d|3[01])\.)~i', $url);
    }

    /**
     * Resolve the gateway Bearer key, same order NER/HTR/PageIndex use (#1368):
     * ahg_ner_settings.api_key, then ahg_ai_settings feature='general' api_key.
     */
    private function resolveGatewayKey(): ?string
    {
        try {
            $key = (string) (DB::table('ahg_ner_settings')
                ->where('setting_key', 'api_key')
                ->value('setting_value') ?? '');
            if ($key !== '') {
                return $key;
            }
        } catch (\Throwable $e) {
            // settings table absent during boot — fall through.
        }
        $key = (string) ($this->getAiSetting('general', 'api_key', '') ?? '');

        return $key !== '' ? $key : null;
    }

    /**
     * POST an Ollama completion through the AHG AI gateway (#1368).
     *
     * The gateway proxies Ollama transparently at {base}/ollama/api/... so the
     * request/response shapes are unchanged from native Ollama; we just prefix
     * /ollama and attach the gateway Bearer key. $endpoint is the gateway base
     * (already node-guarded by resolveOllamaBase()).
     */
    private function callOllama(string $endpoint, string $systemPrompt, string $userPrompt, string $model, int $maxTokens, float $temperature, int $timeout): array
    {
        $startTime = microtime(true);

        $url = rtrim($endpoint, '/') . '/ollama/api/generate';

        $headers = ['Content-Type' => 'application/json'];
        $gatewayKey = $this->resolveGatewayKey();
        if (! empty($gatewayKey)) {
            $headers['Authorization'] = 'Bearer ' . $gatewayKey;
        }

        $response = Http::timeout($timeout)
            ->withHeaders($headers)
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
                    // #1368 — health-check the gateway Ollama passthrough, not a
                    // direct node; a stale node endpoint_url is ignored.
                    $url      = $this->resolveOllamaBase($config->endpoint_url ?? null);
                    $gatewayKey = $this->resolveGatewayKey();
                    $req      = Http::timeout(10);
                    if (! empty($gatewayKey)) {
                        $req = $req->withToken($gatewayKey);
                    }
                    $response = $req->get($url . '/ollama/api/tags');
                    if ($response->successful()) {
                        $body   = $response->json();
                        $models = [];
                        foreach (($body['models'] ?? []) as $m) {
                            $models[] = $m['name'] ?? $m['model'] ?? 'unknown';
                        }
                        // Fetch version
                        $vResp   = (empty($gatewayKey) ? Http::timeout(5) : Http::timeout(5)->withToken($gatewayKey))->get($url . '/ollama/api/version');
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
