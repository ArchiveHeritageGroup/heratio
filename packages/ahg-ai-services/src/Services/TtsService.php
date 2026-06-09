<?php

/**
 * TtsService - Service for Heratio
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
 */

namespace AhgAiServices\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * #1168 - neural TTS via the AHG AI gateway (ai.theahg.co.za/ai/v1/tts, Piper
 * upstream). Routes through the gateway (never a direct node), reusing the same
 * api_url + api_key the NER/HTR clients use. Results are cached by text so
 * repeated narration (tour stops, docent lines) is synthesised once.
 */
class TtsService
{
    /** Read a gateway setting (ahg_ner_settings shadows ahg_ai_settings). */
    private function setting(string $key, string $default = ''): string
    {
        foreach (['ahg_ner_settings', 'ahg_ai_settings'] as $table) {
            try {
                $v = DB::table($table)->where('setting_key', $key)->value('setting_value');
                if ($v !== null && $v !== '') {
                    return (string) $v;
                }
            } catch (\Throwable $e) {
            }
        }

        return $default;
    }

    /**
     * Synthesise speech for $text via the gateway. Returns WAV bytes, or null on
     * any failure (the caller falls back to browser speech). Cached on disk.
     */
    public function synthesize(string $text, ?string $voice = null): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $text = mb_substr($text, 0, 2000);

        $base = rtrim($this->setting('api_url', 'https://ai.theahg.co.za/ai/v1'), '/');
        if ($base === '') {
            return null;
        }
        $key = $this->setting('api_key', '');

        $cacheDir = storage_path('app/tts-cache');
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $cacheFile = $cacheDir.'/'.md5($text.'|'.((string) $voice).'|'.$base).'.wav';
        if (is_file($cacheFile) && filesize($cacheFile) > 44) {   // 44 = WAV header size
            return file_get_contents($cacheFile) ?: null;
        }

        try {
            $req = Http::timeout(120);
            if ($key !== '') {
                $req = $req->withToken($key);
            }
            $resp = $req->post($base.'/tts', array_filter([
                'text' => $text,
                'voice' => $voice,
            ], fn ($v) => $v !== null && $v !== ''));

            if ($resp->successful() && str_contains((string) $resp->header('Content-Type'), 'audio')) {
                $body = $resp->body();
                if (strlen($body) > 44) {
                    @file_put_contents($cacheFile, $body);

                    return $body;
                }
            }
            Log::warning('[ahg-ai] TTS gateway returned non-audio', ['status' => $resp->status()]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] TTS gateway failed: '.$e->getMessage());
        }

        return null;
    }
}
