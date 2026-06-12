<?php

/**
 * AltTextSuggestionService - Heratio ahg-core
 *
 * heratio#1211 ("every museum for everyone"), alt-text curation slice - the AI
 * ASSISTANCE path that speeds up the human curation in AltTextService /
 * AltTextController. The accessibility report surfaced that published image
 * surrogates carry essentially no genuine alternative text. The curation worklist
 * lets a person author one per image; this service offers an optional AI DRAFT to
 * seed that textarea so the curator has somewhere to start.
 *
 * Hard rules, by design:
 *   - The draft is a SUGGESTION ONLY. It is never written anywhere. The existing
 *     human save path (AltTextController::store -> AltTextService::save) remains the
 *     ONLY write, and the curator must review and edit before saving. The author of
 *     the stored alt text is always a person, never the model.
 *   - AI is reached ONLY through the sanctioned AHG AI gateway
 *     (https://ai.theahg.co.za/ai/v1), resolved from the same settings the rest of
 *     Heratio uses (ahg_ner_settings / ahg_ai_settings, default the gateway host).
 *     We NEVER post to a GPU node port (11434 / 5004 / 5006 / 8011). If the resolved
 *     endpoint is not the gateway, or no key / no vision model is configured, the
 *     service degrades to NO suggestion - the manual curation is untouched.
 *   - Bounded: one image per call, a hard byte cap on what we send, a single short
 *     prompt, a bounded timeout, and a length-capped draft back. No batching.
 *   - Resilient: every failure path (no file, oversized file, gateway down, empty
 *     model reply, package not registered) returns a clean { ok:false, reason }
 *     rather than throwing. The caller never 500s.
 *
 * The image is sent to the gateway's OpenAI-compatible vision chat endpoint
 * ({gateway}/ollama/v1/chat/completions) as a base64 data URL in an image_url
 * content part - the same shape llava and other vision models on the gateway accept.
 * The model and gateway selection happen DB-side / gateway-side; this client only
 * picks the door (the gateway) and a sane default vision model name.
 *
 * International / jurisdiction-neutral: the draft is requested in the working
 * language (Afrikaans is a first-class working language, not a fallback). The prompt
 * is generic and carries no country-specific assumptions.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AltTextSuggestionService
{
    /** The sanctioned AHG AI gateway base (default when no setting overrides it). */
    private const GATEWAY_DEFAULT = 'https://ai.theahg.co.za/ai/v1';

    /**
     * Hostnames we will NEVER post a vision request to: bare GPU node inference
     * ports. The gateway is the only sanctioned door. A resolved endpoint pointing
     * at one of these (by :port) degrades to no-suggestion rather than bypassing the
     * gateway. Defence-in-depth on top of the default-is-the-gateway behaviour.
     *
     * @var array<int,int>
     */
    private const NODE_PORTS = [11434, 5004, 5006, 8011];

    /** Default vision model on the gateway (llava is available there). */
    private const DEFAULT_VISION_MODEL = 'llava';

    /** Hard ceiling on the image bytes we are willing to send (8 MB). */
    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    /** Bounded request timeout, seconds. */
    private const TIMEOUT_SECONDS = 60;

    /** Cap on the returned draft so it cannot exceed the stored-alt-text limit. */
    private const MAX_DRAFT_LEN = AltTextService::MAX_ALT_LEN;

    /** Image extensions we will hand to a vision model. Mirrors AltTextService. */
    private const EXT_IMAGE = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    public function __construct(private AltTextService $altText) {}

    /**
     * Is the AI suggestion path usable on this install right now? True only when the
     * curation store exists AND a gateway endpoint + key are configured. Used by the
     * controller / view to decide whether to offer the button at all. Never throws.
     */
    public function isEnabled(): bool
    {
        try {
            if (! $this->altText->isAvailable()) {
                return false;
            }
            $gw = $this->gateway();

            return $gw['url'] !== '' && $gw['key'] !== '';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Produce a DRAFT alt-text description for ONE published image surrogate, in the
     * working language, via the AHG gateway vision model. The draft is for review by a
     * person and is NEVER saved here.
     *
     * @return array{ok:bool, draft:?string, reason:?string, lang:string}
     */
    public function suggest(int $digitalObjectId, string $lang = AltTextService::DEFAULT_LANG): array
    {
        $lang = $this->altText->normalizeLang($lang);
        $fail = fn (string $reason): array => ['ok' => false, 'draft' => null, 'reason' => $reason, 'lang' => $lang];

        if ($digitalObjectId <= 0) {
            return $fail(__('No image was selected.'));
        }

        try {
            // Only ever describe a real, published image surrogate the curator could
            // also save against - reuse the curation store's published-image gate.
            $row = $this->altText->one($digitalObjectId, $lang);
            if ($row === null) {
                return $fail(__('That image is not available for a suggestion.'));
            }

            $gw = $this->gateway();
            if ($gw['url'] === '' || $gw['key'] === '') {
                return $fail(__('AI suggestions are not configured on this instance.'));
            }

            $image = $this->loadImage($digitalObjectId);
            if ($image === null) {
                return $fail(__('The image file could not be read for a suggestion.'));
            }

            $draft = $this->callGatewayVision($gw, $image['data_url'], $lang, (string) ($row['caption'] ?? ''));
            if ($draft === null || trim($draft) === '') {
                return $fail(__('No suggestion could be generated right now.'));
            }

            $draft = $this->tidy($draft);
            if ($draft === '') {
                return $fail(__('No suggestion could be generated right now.'));
            }

            return ['ok' => true, 'draft' => $draft, 'reason' => null, 'lang' => $lang];
        } catch (\Throwable $e) {
            Log::warning('[ahg-core] alt-text AI suggestion failed: '.$e->getMessage());

            return $fail(__('The suggestion service is unavailable right now.'));
        }
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * Resolve the sanctioned gateway base URL + API key, mirroring the precedence the
     * AI-services controller uses (ahg_ner_settings -> ahg_ai_settings general ->
     * default gateway). A resolved URL that points at a bare GPU node port is rejected
     * (treated as unconfigured) so we can never bypass the gateway.
     *
     * @return array{url:string, key:string, model:string}
     */
    private function gateway(): array
    {
        $url = $this->setting('api_url', self::GATEWAY_DEFAULT);
        $key = $this->setting('api_key', '');
        $model = $this->setting('vision_model', self::DEFAULT_VISION_MODEL);

        $url = trim($url);
        $key = trim($key);
        $model = trim($model) !== '' ? trim($model) : self::DEFAULT_VISION_MODEL;

        // Never accept a direct node-port endpoint as the door.
        $port = parse_url($url, PHP_URL_PORT);
        if ($port !== null && in_array((int) $port, self::NODE_PORTS, true)) {
            Log::warning('[ahg-core] alt-text suggestion: configured endpoint is a node port, refusing to bypass the gateway.');
            $url = '';
        }

        return ['url' => $url, 'key' => $key, 'model' => $model];
    }

    /**
     * Read one general AI setting with the same fallback order as the AI-services
     * controller: ahg_ner_settings (flat) then ahg_ai_settings (feature=general).
     * Never throws; returns the default when the tables are absent.
     */
    private function setting(string $key, string $default): string
    {
        try {
            if (Schema::hasTable('ahg_ner_settings')) {
                $v = DB::table('ahg_ner_settings')->where('setting_key', $key)->value('setting_value');
                if ($v !== null && $v !== '') {
                    return (string) $v;
                }
            }
            if (Schema::hasTable('ahg_ai_settings')) {
                $v = DB::table('ahg_ai_settings')
                    ->where('feature', 'general')
                    ->where('setting_key', $key)
                    ->value('setting_value');
                if ($v !== null && $v !== '') {
                    return (string) $v;
                }
            }
        } catch (\Throwable $e) {
            // tables missing during boot - fall through to the default
        }

        return $default;
    }

    /**
     * Resolve a digital_object id to a readable local image file and return it as a
     * base64 data URL (capped at MAX_IMAGE_BYTES). Mirrors the path-candidate strategy
     * used elsewhere (config('heratio.storage_path') / uploads_path, with the AtoM
     * mirror as a legacy fallback). Returns null when no readable image is found, the
     * file is oversized, or it is not an image we will hand to a vision model.
     *
     * @return array{data_url:string}|null
     */
    private function loadImage(int $digitalObjectId): ?array
    {
        try {
            if (! Schema::hasTable('digital_object')) {
                return null;
            }

            $do = DB::table('digital_object')->where('id', $digitalObjectId)->first();
            if ($do === null) {
                return null;
            }

            $name = (string) ($do->name ?? '');
            $path = (string) ($do->path ?? '');
            if ($name === '' || $path === '') {
                return null;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (! in_array($ext, self::EXT_IMAGE, true)) {
                // Vision models read raster images; skip TIFF/JP2/HEIC masters etc.
                return null;
            }

            $rel = ltrim($path, '/').$name;
            $candidates = [
                rtrim((string) config('heratio.storage_path'), '/').'/'.$rel,
                rtrim((string) config('heratio.uploads_path'), '/').'/'.$rel,
                '/usr/share/nginx/archive/'.$rel,
            ];

            $file = null;
            foreach ($candidates as $candidate) {
                if (is_file($candidate) && is_readable($candidate)) {
                    $file = $candidate;
                    break;
                }
            }
            if ($file === null) {
                return null;
            }

            $size = @filesize($file);
            if ($size === false || $size <= 0 || $size > self::MAX_IMAGE_BYTES) {
                return null;
            }

            $bytes = @file_get_contents($file, false, null, 0, self::MAX_IMAGE_BYTES + 1);
            if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_IMAGE_BYTES) {
                return null;
            }

            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png'         => 'image/png',
                'gif'         => 'image/gif',
                'webp'        => 'image/webp',
                'bmp'         => 'image/bmp',
                default       => 'image/jpeg',
            };

            return ['data_url' => 'data:'.$mime.';base64,'.base64_encode($bytes)];
        } catch (\Throwable $e) {
            Log::warning('[ahg-core] alt-text suggestion image load failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * POST the image to the gateway's OpenAI-compatible vision chat endpoint and
     * return the model's draft text (or null on any non-success / empty reply).
     *
     * Endpoint: {gateway}/ollama/v1/chat/completions - the sanctioned gateway path,
     * never a node port. The image rides as a base64 data URL in an image_url content
     * part. Bounded timeout; no retries.
     *
     * @param  array{url:string, key:string, model:string}  $gw
     */
    private function callGatewayVision(array $gw, string $dataUrl, string $lang, string $caption): ?string
    {
        $langName = $this->languageName($lang);
        $captionHint = $caption !== ''
            ? "\n\nAn embedded caption is available as a hint (do not copy it verbatim, and ignore it if it does not match the image): ".mb_substr($caption, 0, 300)
            : '';

        $instruction = 'You are helping a museum cataloguer write alternative text (alt text) for an image, '
            ."for people who use screen readers. Write ONE concise, factual sentence in {$langName} that describes "
            .'what is visibly shown in the image, for someone who cannot see it. Describe only what is clearly '
            .'visible; do not guess at names, dates, or places, and do not invent detail. Do not begin with '
            .'"image of" or "picture of". Return only the description, with no preamble or quotation marks.'
            .$captionHint;

        $endpoint = rtrim($gw['url'], '/').'/ollama/v1/chat/completions';

        try {
            $resp = Http::timeout(self::TIMEOUT_SECONDS)
                ->withToken($gw['key'])
                ->asJson()
                ->post($endpoint, [
                    'model'       => $gw['model'],
                    'temperature' => 0.2,
                    'max_tokens'  => 300,
                    'messages'    => [[
                        'role'    => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $instruction],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                        ],
                    ]],
                ]);

            if (! $resp->ok()) {
                Log::info('[ahg-core] alt-text suggestion gateway returned HTTP '.$resp->status());

                return null;
            }

            $body = $resp->json();
            $text = $body['choices'][0]['message']['content'] ?? null;

            return is_string($text) ? $text : null;
        } catch (\Throwable $e) {
            Log::info('[ahg-core] alt-text suggestion gateway call failed: '.$e->getMessage());

            return null;
        }
    }

    /** Tidy a raw model reply into a clean, length-capped single description. */
    private function tidy(string $text): string
    {
        $text = trim($text);
        // Strip wrapping quotes the model sometimes adds.
        $text = trim($text, " \t\n\r\0\x0B\"'");
        // Drop a leading "Alt text:" / "Description:" style prefix.
        $text = preg_replace('/^\s*(alt[\s-]?text|description|caption)\s*[:\-]\s*/i', '', $text) ?? $text;
        // Collapse whitespace.
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text) > self::MAX_DRAFT_LEN) {
            $text = rtrim(mb_substr($text, 0, self::MAX_DRAFT_LEN));
        }

        return $text;
    }

    /**
     * A human-readable language name for the prompt, for a small common set; falls
     * back to the code itself so the model still gets a target. Afrikaans leads the
     * non-English entries as a first-class working language.
     */
    private function languageName(string $lang): string
    {
        $map = [
            'en' => 'English',
            'af' => 'Afrikaans',
            'fr' => 'French',
            'pt' => 'Portuguese',
            'de' => 'German',
            'es' => 'Spanish',
            'nl' => 'Dutch',
            'zu' => 'isiZulu',
            'xh' => 'isiXhosa',
            'st' => 'Sesotho',
        ];
        $base = strtolower(substr($lang, 0, 2));

        return $map[$base] ?? $lang;
    }
}
