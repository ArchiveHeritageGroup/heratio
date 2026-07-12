<?php

/**
 * AnimationService — HTTP client for the AI video server (SVD / CogVideoX /
 * WAN). Posts an image (+ optional prompt + params) and gets an MP4 back.
 *
 * The video-server lives at packages/ahg-image-ar/tools/video-server/ and
 * runs on the Heratio AI host. Requests are routed through the AHG AI gateway
 * (https://ai.theahg.co.za/ai/v1/ar) per #1361; a raw-node ar_server_url
 * override is deliberately ignored so a stale setting cannot bypass the gateway.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * AGPL-3.0-or-later. See LICENSE.
 */

namespace AhgImageAr\Services;

use CURLFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AnimationService
{
    /**
     * @param  array<string,mixed>  $opts
     * @return array{
     *     size:int, generation_secs:float, model:string, frames:int, fps:int,
     *     motion_bucket_id:int, seed:int, prompt:?string
     * }
     */
    public function generate(string $sourceImage, string $destMp4, array $opts = []): array
    {
        if (! is_file($sourceImage)) {
            throw new RuntimeException("Source image not found: {$sourceImage}");
        }
        $opts = array_merge($this->defaults(), $opts);

        $url = rtrim($opts['server_url'], '/').'/animate';
        $timeout = max(60, (int) $opts['request_timeout']);

        $fields = [
            'image' => new CURLFile($sourceImage, 'image/jpeg', basename($sourceImage)),
            'model' => (string) $opts['model'],
            'num_frames' => (int) $opts['num_frames'],
            'fps' => (int) $opts['fps'],
            'motion_bucket_id' => (int) $opts['motion_bucket_id'],
            'seed' => (int) $opts['seed'],
        ];
        if (! empty($opts['prompt'])) {
            $fields['prompt'] = (string) $opts['prompt'];
        }
        if (! empty($opts['width'])) {
            $fields['width'] = (int) $opts['width'];
        }
        if (! empty($opts['height'])) {
            $fields['height'] = (int) $opts['height'];
        }

        @mkdir(dirname($destMp4), 0775, true);

        // #1361 — gateway Bearer auth when routed through ai.theahg.co.za.
        $reqHeaders = [];
        if (! empty($opts['api_key'])) {
            $reqHeaders[] = 'Authorization: Bearer '.$opts['api_key'];
        }

        $headersOut = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_HTTPHEADER => $reqHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HEADERFUNCTION => function ($curl, $hdr) use (&$headersOut) {
                if (strpos($hdr, ':') !== false) {
                    [$k, $v] = explode(':', $hdr, 2);
                    $headersOut[strtolower(trim($k))] = trim($v);
                }

                return strlen($hdr);
            },
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException("AI server unreachable at {$url}: {$err}");
        }
        if ($httpCode !== 200) {
            $msg = is_string($body) ? substr($body, 0, 500) : '';
            throw new RuntimeException("AI server returned HTTP {$httpCode}: {$msg}");
        }
        if (! is_string($body) || strlen($body) < 1024) {
            throw new RuntimeException('AI server returned an empty / suspiciously small body.');
        }
        if (file_put_contents($destMp4, $body) === false) {
            throw new RuntimeException("Could not write MP4 to {$destMp4}");
        }
        @chmod($destMp4, 0644);

        return [
            'size' => filesize($destMp4),
            'generation_secs' => (float) ($headersOut['x-generation-secs'] ?? 0),
            'model' => (string) ($headersOut['x-model'] ?? $opts['model']),
            'frames' => (int) ($headersOut['x-frames'] ?? $opts['num_frames']),
            'fps' => (int) ($headersOut['x-fps'] ?? $opts['fps']),
            'motion_bucket_id' => (int) ($headersOut['x-motion-bucket'] ?? $opts['motion_bucket_id']),
            'seed' => (int) ($headersOut['x-seed'] ?? $opts['seed']),
            'prompt' => $opts['prompt'] ?? null,
        ];
    }

    /** @return array<string,mixed> */
    public function defaults(): array
    {
        $rows = [];
        try {
            $rows = DB::table('image_ar_settings')->pluck('setting_value', 'setting_key')->all();
        } catch (\Throwable $e) {
            // table may not exist yet
        }

        // #1361 — route through the AHG AI gateway (/ai/v1/ar), never a direct
        // node. A raw-node ar_server_url override is ignored so a stale setting
        // can't bypass the gateway (metering/quota/failover).
        $override = (string) ($rows['ar_server_url'] ?? '');
        $serverUrl = ($override !== '' && ! $this->looksLikeNode($override))
            ? rtrim($override, '/')
            : 'https://ai.theahg.co.za/ai/v1/ar';

        return [
            'server_url' => $serverUrl,
            'api_key' => $this->resolveGatewayKey(),
            'model' => (string) ($rows['ar_model'] ?? 'svd'),
            'num_frames' => (int) ($rows['ar_num_frames'] ?? 14),
            'fps' => (int) ($rows['ar_fps'] ?? 7),
            'motion_bucket_id' => (int) ($rows['ar_motion_bucket_id'] ?? 127),
            'seed' => (int) ($rows['ar_seed'] ?? 0),
            'prompt' => (string) ($rows['ar_default_prompt'] ?? ''),
            'request_timeout' => (int) ($rows['ar_request_timeout'] ?? 900),
        ];
    }

    public function isEnabled(): bool
    {
        try {
            $rows = DB::table('image_ar_settings')
                ->whereIn('setting_key', ['ar_enabled', 'ar_user_button'])
                ->pluck('setting_value', 'setting_key')->all();
        } catch (\Throwable $e) {
            return false;
        }

        return ((string) ($rows['ar_enabled'] ?? '0')) === '1'
            && ((string) ($rows['ar_user_button'] ?? '1')) === '1';
    }

    /** True when a URL points at a raw GPU node rather than the gateway (#1361). */
    private function looksLikeNode(string $url): bool
    {
        return (bool) preg_match('~:11434|://(?:127\.0\.0\.1|localhost|192\.168\.|10\.|172\.(?:1[6-9]|2\d|3[01])\.)~i', $url);
    }

    /**
     * Resolve the gateway Bearer key, same order NER/HTR/Donut use (#1361):
     * ahg_ner_settings.api_key, then ahg_ai_settings feature='general' api_key.
     */
    private function resolveGatewayKey(): string
    {
        try {
            $key = (string) (DB::table('ahg_ner_settings')
                ->where('setting_key', 'api_key')
                ->value('setting_value') ?? '');
            if ($key !== '') {
                return $key;
            }
            $key = (string) (DB::table('ahg_ai_settings')
                ->where('feature', 'general')
                ->where('setting_key', 'api_key')
                ->value('setting_value') ?? '');
            if ($key !== '') {
                return $key;
            }
        } catch (\Throwable) {
            // settings tables absent — no key.
        }

        return '';
    }

    /**
     * GET /health on the AI server. Returns null if unreachable.
     */
    public function health(): ?array
    {
        $defaults = $this->defaults();
        $url = rtrim($defaults['server_url'], '/').'/health';
        $reqHeaders = [];
        if (! empty($defaults['api_key'])) {
            $reqHeaders[] = 'Authorization: Bearer '.$defaults['api_key'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $reqHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || ! is_string($body)) {
            return null;
        }
        $data = json_decode($body, true);

        return is_array($data) ? $data : null;
    }
}
