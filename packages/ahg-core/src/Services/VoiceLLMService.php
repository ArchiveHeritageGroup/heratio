<?php

/**
 * VoiceLLMService - LLM gateway for the voice-AI feature.
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

namespace AhgCore\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Single gateway that all voice-AI callers route through. Closes the
 * settings-consumer half of issue #99 by giving every key in
 * /admin/ahgSettings/voice_ai a real consumer:
 *
 *   - voice_llm_provider       local | cloud | hybrid           dispatch
 *   - voice_local_llm_url      Ollama base URL                  callLocal
 *   - voice_local_llm_model    Ollama model (vision-capable)    callLocal
 *   - voice_local_llm_timeout  HTTP timeout in seconds          callLocal + callCloud
 *   - voice_anthropic_api_key  Anthropic API key                callCloud
 *   - voice_cloud_model        Anthropic model id               callCloud
 *   - voice_daily_cloud_limit  per-user/day cloud-call cap      enforceDailyCloudLimit
 *   - voice_audit_ai_calls     write-to-ahg_audit_log toggle    writeAuditEntry
 *
 * Integration contract: every caller invokes ->chat($prompt, $imageBase64?)
 * and gets back a plain array { ok, text, provider, model, error?,
 * http_status, latency_ms }. ConditionController::analyzePhoto and
 * InformationObjectController's image-describe path were both rewriting
 * the same Ollama curl/Http::post() block; both should delegate here so
 * the operator's settings actually take effect.
 */
class VoiceLLMService
{
    private const ANTHROPIC_VERSION = '2023-06-01';

    /**
     * @param string      $prompt       User-facing instruction.
     * @param string|null $imageBase64  Optional raw base64 image payload (no data: prefix). Triggers vision path.
     * @param array       $opts         Optional overrides:
     *                                    - 'temperature' (float)
     *                                    - 'media_type' (e.g. 'image/jpeg' for cloud vision; defaults to image/jpeg)
     *                                    - 'max_tokens' (int, cloud only — Anthropic requires this)
     * @return array{ok:bool,text:string,provider:string,model:string,error:?string,http_status:int,latency_ms:int}
     */
    public function chat(string $prompt, ?string $imageBase64 = null, array $opts = []): array
    {
        $settings = $this->loadSettings();
        $provider = $settings['voice_llm_provider'] ?? 'local';
        $startedAt = microtime(true);

        // Dispatch.
        if ($provider === 'local') {
            $result = $this->callLocal($prompt, $imageBase64, $settings, $opts);
            $result['latency_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
            $this->afterCall($result, $prompt, $imageBase64 !== null, $settings);
            return $result;
        }

        if ($provider === 'cloud') {
            $cloudGate = $this->enforceDailyCloudLimit($settings);
            if ($cloudGate !== null) {
                $cloudGate['latency_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
                $this->afterCall($cloudGate, $prompt, $imageBase64 !== null, $settings);
                return $cloudGate;
            }
            $result = $this->callCloud($prompt, $imageBase64, $settings, $opts);
            $result['latency_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
            if ($result['ok']) {
                $this->incrementCloudUsage();
            }
            $this->afterCall($result, $prompt, $imageBase64 !== null, $settings);
            return $result;
        }

        // hybrid: try local first, fall back to cloud on failure.
        $local = $this->callLocal($prompt, $imageBase64, $settings, $opts);
        if ($local['ok']) {
            $local['latency_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
            $this->afterCall($local, $prompt, $imageBase64 !== null, $settings);
            return $local;
        }

        $cloudGate = $this->enforceDailyCloudLimit($settings);
        if ($cloudGate !== null) {
            // Daily cap reached; surface the local error since cloud is also blocked.
            $local['fallback_blocked'] = $cloudGate['error'];
            $local['latency_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
            $this->afterCall($local, $prompt, $imageBase64 !== null, $settings);
            return $local;
        }

        $cloud = $this->callCloud($prompt, $imageBase64, $settings, $opts);
        $cloud['hybrid_local_error'] = $local['error'];
        $cloud['latency_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
        if ($cloud['ok']) {
            $this->incrementCloudUsage();
        }
        $this->afterCall($cloud, $prompt, $imageBase64 !== null, $settings);
        return $cloud;
    }

    // ─── local (ollama) ────────────────────────────────────────────

    private function callLocal(string $prompt, ?string $imageBase64, array $settings, array $opts): array
    {
        $url     = rtrim((string) ($settings['voice_local_llm_url']   ?? 'http://localhost:11434'), '/');
        $model   = (string) ($settings['voice_local_llm_model']  ?? 'llava:7b');
        $timeout = (int)    ($settings['voice_local_llm_timeout'] ?? 30);

        $body = [
            'model'   => $model,
            'prompt'  => $prompt,
            'stream'  => false,
            'options' => array_filter([
                'temperature' => $opts['temperature'] ?? null,
            ], fn ($v) => $v !== null),
        ];
        if ($imageBase64 !== null) {
            $body['images'] = [$imageBase64];
        }

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(min(5, $timeout))
                ->post($url . '/api/generate', $body);
            $status = $response->status();
            $json   = $response->json();
            if (!$response->successful()) {
                return [
                    'ok'          => false,
                    'text'        => '',
                    'provider'    => 'local',
                    'model'       => $model,
                    'error'       => 'Ollama HTTP ' . $status . ' (' . substr((string) $response->body(), 0, 200) . ')',
                    'http_status' => $status,
                ];
            }
            return [
                'ok'          => true,
                'text'        => (string) ($json['response'] ?? ''),
                'provider'    => 'local',
                'model'       => $model,
                'error'       => null,
                'http_status' => 200,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'          => false,
                'text'        => '',
                'provider'    => 'local',
                'model'       => $model,
                'error'       => 'Ollama call failed: ' . $e->getMessage(),
                'http_status' => 0,
            ];
        }
    }

    // ─── cloud (anthropic) ─────────────────────────────────────────

    private function callCloud(string $prompt, ?string $imageBase64, array $settings, array $opts): array
    {
        $apiKey  = (string) ($settings['voice_anthropic_api_key'] ?? '');
        $model   = (string) ($settings['voice_cloud_model']        ?? 'claude-sonnet-4-20250514');
        $timeout = (int)    ($settings['voice_local_llm_timeout']  ?? 30);

        if ($apiKey === '') {
            return [
                'ok'          => false,
                'text'        => '',
                'provider'    => 'cloud',
                'model'       => $model,
                'error'       => 'voice_anthropic_api_key is empty — cannot call cloud LLM',
                'http_status' => 0,
            ];
        }

        // Anthropic's /v1/messages content block: vision goes via [{type:image,source:base64}, {type:text,text:...}].
        $content = [];
        if ($imageBase64 !== null) {
            $content[] = [
                'type'   => 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => $opts['media_type'] ?? 'image/jpeg',
                    'data'       => $imageBase64,
                ],
            ];
        }
        $content[] = ['type' => 'text', 'text' => $prompt];

        $body = [
            'model'      => $model,
            'max_tokens' => (int) ($opts['max_tokens'] ?? 1024),
            'messages'   => [['role' => 'user', 'content' => $content]],
        ];
        if (isset($opts['temperature'])) {
            $body['temperature'] = (float) $opts['temperature'];
        }

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(min(10, $timeout))
                ->withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => self::ANTHROPIC_VERSION,
                    'Content-Type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', $body);
            $status = $response->status();
            $json   = $response->json();
            if (!$response->successful()) {
                $err = is_array($json) && isset($json['error']['message']) ? $json['error']['message'] : substr((string) $response->body(), 0, 200);
                return [
                    'ok'          => false,
                    'text'        => '',
                    'provider'    => 'cloud',
                    'model'       => $model,
                    'error'       => 'Anthropic HTTP ' . $status . ': ' . $err,
                    'http_status' => $status,
                ];
            }
            // Anthropic response: { content: [{ type: 'text', text: '...' }] }
            $text = '';
            foreach ((array) ($json['content'] ?? []) as $block) {
                if (($block['type'] ?? null) === 'text') {
                    $text .= (string) ($block['text'] ?? '');
                }
            }
            return [
                'ok'          => true,
                'text'        => $text,
                'provider'    => 'cloud',
                'model'       => $model,
                'error'       => null,
                'http_status' => 200,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'          => false,
                'text'        => '',
                'provider'    => 'cloud',
                'model'       => $model,
                'error'       => 'Anthropic call failed: ' . $e->getMessage(),
                'http_status' => 0,
            ];
        }
    }

    // ─── daily cloud limit ─────────────────────────────────────────

    /**
     * Returns null when the call is allowed, or a fully-formed 429 result when blocked.
     */
    private function enforceDailyCloudLimit(array $settings): ?array
    {
        $limit = (int) ($settings['voice_daily_cloud_limit'] ?? 0);
        if ($limit <= 0) {
            return null; // 0 = unlimited (per the form's hint text)
        }
        $userId = Auth::id();
        if (!Schema::hasTable('voice_usage')) {
            // Without the counter table we can't enforce; fail open (no
            // worse than today's behaviour where the limit is ignored).
            return null;
        }
        $today = (string) now()->format('Y-m-d');
        $row = DB::table('voice_usage')
            ->where('call_date', $today)
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->when($userId === null, fn ($q) => $q->whereNull('user_id'))
            ->first();
        $used = (int) ($row->call_count ?? 0);
        if ($used >= $limit) {
            return [
                'ok'          => false,
                'text'        => '',
                'provider'    => 'cloud',
                'model'       => (string) ($settings['voice_cloud_model'] ?? ''),
                'error'       => 'Daily cloud LLM limit reached (' . $used . '/' . $limit . '). Reset at midnight or raise voice_daily_cloud_limit.',
                'http_status' => 429,
            ];
        }
        return null;
    }

    private function incrementCloudUsage(): void
    {
        if (!Schema::hasTable('voice_usage')) return;
        $userId = Auth::id();
        $today = (string) now()->format('Y-m-d');
        // Idempotent upsert + increment via raw on-duplicate-key. user_id is
        // nullable so anonymous (CLI / system) calls share one bucket.
        DB::statement(
            'INSERT INTO voice_usage (user_id, call_date, call_count, created_at, updated_at) '
            . 'VALUES (?, ?, 1, NOW(), NOW()) '
            . 'ON DUPLICATE KEY UPDATE call_count = call_count + 1, updated_at = NOW()',
            [$userId, $today]
        );
    }

    // ─── audit ─────────────────────────────────────────────────────

    private function afterCall(array $result, string $prompt, bool $hadImage, array $settings): void
    {
        if (($settings['voice_audit_ai_calls'] ?? 'false') !== 'true') return;
        if (!Schema::hasTable('ahg_audit_log')) return;

        try {
            $request = request();
            DB::table('ahg_audit_log')->insert([
                'uuid'           => (string) Str::uuid(),
                'user_id'        => Auth::id(),
                'username'       => Auth::user()?->username,
                'user_email'     => Auth::user()?->email ?? null,
                'ip_address'     => $request?->ip(),
                'user_agent'     => substr((string) ($request?->userAgent() ?? ''), 0, 500),
                'session_id'     => session()?->getId(),
                'action'         => 'voice_ai_call',
                'entity_type'    => 'voice_llm',
                'entity_id'      => null,
                'module'         => 'voice_ai',
                'action_name'    => $result['provider'] . ($hadImage ? '_vision' : '_text'),
                'request_method' => $request?->method(),
                'request_uri'    => substr((string) ($request?->fullUrl() ?? ''), 0, 2000),
                'old_values'     => null,
                'new_values'     => json_encode([
                    'ok'          => (bool) $result['ok'],
                    'provider'    => $result['provider'],
                    'model'       => $result['model'],
                    'http_status' => $result['http_status'],
                    'latency_ms'  => $result['latency_ms'] ?? null,
                    'had_image'   => $hadImage,
                    'prompt_chars'=> mb_strlen($prompt),
                    'response_chars' => mb_strlen($result['text'] ?? ''),
                    'error'       => $result['error'] ?? null,
                ], JSON_UNESCAPED_SLASHES),
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit must never break the request.
            \Log::warning('[VoiceLLMService] audit insert failed: ' . $e->getMessage());
        }
    }

    // ─── helpers ───────────────────────────────────────────────────

    private function loadSettings(): array
    {
        if (!Schema::hasTable('ahg_settings')) return [];
        return DB::table('ahg_settings')
            ->where('setting_group', 'voice_ai')
            ->pluck('setting_value', 'setting_key')
            ->toArray();
    }
}
