<?php

/**
 * VoiceSttService - server-side speech-to-text for voice search (heratio#1767).
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

use AhgAiServices\Support\AiServicesSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Sends a short voice-search recording to the AI gateway's standard
 * audio/transcriptions route (Whisper-style) and returns the text. Used when the browser has no
 * speech API (Firefox and others) or the user chose server recognition.
 *
 * Only the gateway is called: the route is a PATH joined to the gateway
 * origin taken from the AI services API URL, so a setting cannot point the
 * audio at a GPU node directly. The audio is never stored; the controller
 * deletes the upload when the request ends.
 *
 * Contract: docs/technical/speech-to-text-gateway-contract.md
 */
class VoiceSttService
{
    public const DEFAULT_PATH = '/ai/v1/audio/transcriptions';

    /** Containers MediaRecorder produces in the browsers that need the fallback. */
    public const MIME_TYPES = ['audio/webm', 'video/webm', 'audio/ogg', 'application/ogg'];

    /**
     * ISO 639-1 codes the Whisper models accept as `language`. A locale whose
     * primary subtag is not listed is sent without a language, and the model
     * detects it. ponytail: fixed list for Whisper; make it a setting if the
     * gateway ever serves a model with a different language set.
     */
    private const LANGUAGES = [
        'af', 'am', 'ar', 'as', 'az', 'ba', 'be', 'bg', 'bn', 'bo', 'br', 'bs', 'ca', 'cs', 'cy', 'da', 'de',
        'el', 'en', 'es', 'et', 'eu', 'fa', 'fi', 'fo', 'fr', 'gl', 'gu', 'ha', 'he', 'hi', 'hr', 'ht', 'hu',
        'hy', 'id', 'is', 'it', 'ja', 'jw', 'ka', 'kk', 'km', 'kn', 'ko', 'la', 'lb', 'ln', 'lo', 'lt', 'lv',
        'mg', 'mi', 'mk', 'ml', 'mn', 'mr', 'ms', 'mt', 'my', 'ne', 'nl', 'nn', 'no', 'oc', 'pa', 'pl', 'ps',
        'pt', 'ro', 'ru', 'sa', 'sd', 'si', 'sk', 'sl', 'sn', 'so', 'sq', 'sr', 'su', 'sv', 'sw', 'ta', 'te',
        'tg', 'th', 'tk', 'tl', 'tr', 'tt', 'uk', 'ur', 'uz', 'vi', 'yi', 'yo', 'zh',
    ];

    private ?array $settings = null;

    public function setting(string $key, string $default = ''): string
    {
        if ($this->settings === null) {
            $this->settings = Schema::hasTable('ahg_settings')
                ? DB::table('ahg_settings')->where('setting_group', 'voice_ai')
                    ->pluck('setting_value', 'setting_key')->map(fn ($v) => (string) $v)->toArray()
                : [];
        }
        $v = trim($this->settings[$key] ?? '');

        return $v === '' ? $default : $v;
    }

    private function flag(string $key, bool $default): bool
    {
        $v = strtolower($this->setting($key, $default ? 'true' : 'false'));

        return in_array($v, ['1', 'true', 'on', 'yes'], true);
    }

    private function intSetting(string $key, int $default, int $min, int $max): int
    {
        return max($min, min($max, (int) $this->setting($key, (string) $default)));
    }

    /** On only when the switch is on AND an administrator has set the model. */
    public function isEnabled(): bool
    {
        return $this->flag('voice_enabled', true)
            && $this->flag('voice_stt_enabled', true)
            && $this->model() !== '';
    }

    public function model(): string
    {
        return $this->setting('voice_stt_model');
    }

    public function requiresAuth(): bool
    {
        return $this->flag('voice_stt_require_auth', false);
    }

    public function maxSeconds(): int
    {
        return $this->intSetting('voice_stt_max_seconds', 15, 3, 60);
    }

    public function maxKilobytes(): int
    {
        return $this->intSetting('voice_stt_max_kb', 1024, 64, 10240);
    }

    /** Requests per minute per signed-in user, or per IP address for guests. */
    public function rateLimit(): int
    {
        return $this->intSetting('voice_stt_rate_limit', 10, 1, 600);
    }

    public function timeout(): int
    {
        return $this->intSetting('voice_stt_timeout', 20, 3, 120);
    }

    /** Gateway origin (scheme + host + port) from the AI services API URL. */
    public function endpoint(): string
    {
        $api = (string) (AiServicesSettings::apiUrl() ?: 'https://ai.theahg.co.za/ai/v1');
        $p = parse_url($api) ?: [];
        $origin = ($p['scheme'] ?? 'https').'://'.($p['host'] ?? 'ai.theahg.co.za').(isset($p['port']) ? ':'.$p['port'] : '');

        $path = $this->setting('voice_stt_path', self::DEFAULT_PATH);
        if (! str_starts_with($path, '/') || str_contains($path, '://')) {
            $path = self::DEFAULT_PATH;
        }

        return $origin.$path;
    }

    /** ISO 639-1 code for an interface locale, or null to let the model detect it. */
    public function languageFor(?string $locale): ?string
    {
        $primary = strtolower((string) preg_split('/[-_]/', (string) $locale)[0]);

        return in_array($primary, self::LANGUAGES, true) ? $primary : null;
    }

    /**
     * @return array{ok: bool, text: string, error: ?string}
     */
    public function transcribe(UploadedFile $audio, ?string $locale): array
    {
        $fields = array_filter([
            'model' => $this->model(),
            'language' => $this->languageFor($locale),
            'response_format' => 'json',
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $request = Http::timeout($this->timeout())->connectTimeout(min(5, $this->timeout()))->acceptJson();
            $key = AiServicesSettings::gatewayKey();
            if (! empty($key)) {
                $request = $request->withToken($key);
            }
            // Read into memory: the clip is small (capped by voice_stt_max_kb).
            $name = 'speech.'.(str_contains((string) $audio->getMimeType(), 'ogg') ? 'ogg' : 'webm');
            $response = $request->attach('file', (string) file_get_contents($audio->getRealPath()), $name)
                ->post($this->endpoint(), $fields);

            if (! $response->successful()) {
                return $this->failure('gateway HTTP '.$response->status());
            }
            $text = $response->json('text');
            if (! is_string($text)) {
                return $this->failure('gateway reply has no text field');
            }

            return ['ok' => true, 'text' => trim($text), 'error' => null];
        } catch (\Throwable $e) {
            return $this->failure(class_basename($e).': '.$e->getMessage());
        }
    }

    /**
     * One warning per kind of failure per 10 minutes, so an outage does not
     * write a log line for every voice search.
     */
    private function failure(string $error): array
    {
        $kind = preg_replace('/[^a-z0-9]+/i', '-', substr($error, 0, 40));
        if (Cache::add('voice-stt-failure:'.$kind, 1, 600)) {
            Log::warning('[voice-stt] transcription unavailable: '.$error, ['endpoint' => $this->endpoint()]);
        }

        return ['ok' => false, 'text' => '', 'error' => $error];
    }
}
