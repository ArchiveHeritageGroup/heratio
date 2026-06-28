<?php

/**
 * LlmFinetuneService - Service for Heratio
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
 * Triggers + monitors local-LLM QLoRA fine-tuning via the AHG AI gateway.
 *
 * Routes through https://ai.theahg.co.za/ai/v1/llm-finetune/* (gateway passthrough
 * to the node-side trainer on the GPU node) — never a bare node URL, per the
 * standing no-bypass rule. The trainer builds the dataset (KM Q&A + release notes
 * + session logs), trains a non-destructive QLoRA adapter over Qwen3-8B, eval-gates
 * it, and (optionally) promotes it. No model is ever removed.
 */
class LlmFinetuneService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $timeout;

    public function __construct()
    {
        // Same gateway base + key resolution the other AI services use.
        $apiUrl = rtrim($this->loadSetting('api_url', 'https://ai.theahg.co.za/ai/v1'), '/');
        $this->baseUrl = $apiUrl . '/llm-finetune';
        $this->apiKey = $this->loadSetting('api_key', '');
        $this->timeout = (int) $this->loadSetting('api_timeout', '60');
    }

    /** Dataset stats + current run state (idle/running/passed/held/failed) + last eval. */
    public function trainingStatus(): ?array
    {
        try {
            $response = $this->http()->timeout(10)->get("{$this->baseUrl}/training/status");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('LLM fine-tune status failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Kick off a background fine-tune run. epochs/rank/auto_promote are optional
     * knobs forwarded to the trainer. Returns the trainer's queue ack or null.
     */
    public function triggerTraining(array $opts = []): ?array
    {
        try {
            $payload = array_filter([
                'epochs'       => $opts['epochs'] ?? null,
                'rank'         => $opts['rank'] ?? null,
                'base_model'   => $opts['base_model'] ?? null,
                'auto_promote' => $opts['auto_promote'] ?? 0,
            ], static fn ($v) => $v !== null);

            $response = $this->http()->timeout($this->timeout)
                ->post("{$this->baseUrl}/train", $payload);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('LLM fine-tune trigger failed: ' . $e->getMessage());
            return null;
        }
    }

    private function http()
    {
        $req = Http::asJson();
        if ($this->apiKey !== '') {
            $req = $req->withToken($this->apiKey);
        }
        return $req;
    }

    private function loadSetting(string $key, string $default): string
    {
        try {
            $value = DB::table('ahg_ner_settings')->where('setting_key', $key)->value('setting_value');
            if ($value !== null && $value !== '') {
                return $value;
            }
            $value = DB::table('ahg_ai_settings')
                ->where('feature', 'general')->where('setting_key', $key)->value('setting_value');
            if ($value !== null && $value !== '') {
                return $value;
            }
        } catch (\Throwable $e) {
            // settings tables not migrated yet — fall through to default
        }
        return $default;
    }
}
