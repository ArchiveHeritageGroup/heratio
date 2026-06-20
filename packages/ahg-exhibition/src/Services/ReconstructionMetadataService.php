<?php

/**
 * ReconstructionMetadataService - heratio#1206 "walk through what no longer exists".
 *
 * OPTIONAL AI "evidence layer" annotator for a reconstruction rebuild stage. Given a
 * stage (its caption + body + date label), it asks the AI to SUGGEST structured
 * provenance metadata - a date estimate, an evidence type, a confidence level and a
 * source-credibility judgement - which the curator then reviews, edits and saves.
 *
 * The suggestion is grounded in the stage's IMAGE when one is on disk: the stage
 * picture (a photograph, plan or drawing of the lost place) is sent to the gateway
 * vision model so the assessor reads the actual evidence, with the caption / body /
 * date label given as supporting context. When the stage has no local image, it
 * falls back to a text-only reading of the caption / body. The saved, curator-
 * confirmed metadata is stored as JSON on ahg_reconstruction_stage.metadata and
 * surfaced read-only in the montage; the response reports which path ran ('vision'
 * or 'text') so the UI can badge it.
 *
 * All AI calls route through the AHG gateway - vision via AhgCore\VoiceLLMService
 * (->chat with a base64 image), text via AhgAiServices\LlmService - never a direct
 * GPU node / model endpoint. Fully fail-soft: any gateway failure (down, quota,
 * garbage output) returns ['ok' => false, ...] and never throws, so the Annotate
 * action can flash a friendly error instead of a 500.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * @copyright Plain Sailing Information Systems
 *
 * @license AGPL-3.0-or-later
 */

namespace AhgExhibition\Services;

use Illuminate\Support\Facades\Log;

class ReconstructionMetadataService
{
    /**
     * The four metadata facets the annotator suggests + the curator saves. These are
     * a small fixed list in code (NOT a MySQL ENUM): the values live inside the JSON
     * metadata column, so adding a facet value never needs a schema change.
     */
    public const EVIDENCE_TYPES = [
        'photograph',
        'survey_plan',
        'architectural_drawing',
        'written_account',
        'oral_history',
        'archaeological',
        'comparable_structure',
        'inference',
        'unknown',
    ];

    public const CONFIDENCE_LEVELS = ['high', 'medium', 'low'];

    public const SOURCE_CREDIBILITY = ['primary', 'secondary', 'tertiary', 'conjectural', 'unknown'];

    /**
     * Suggest structured provenance metadata for a stage. When the stage has a local
     * image on disk, the IMAGE is read by the gateway vision model (caption/body/date
     * as context); otherwise it falls back to a text-only reading. Returns:
     *   ['ok' => true,  'method' => 'vision'|'text',
     *    'metadata' => [date_estimate, evidence_type, confidence, source_credibility, rationale]]
     *   ['ok' => false, 'error' => '<friendly message>']
     * Never throws - the caller flashes the error rather than 500ing.
     */
    public function suggest(object $stage): array
    {
        $caption = trim((string) ($stage->caption ?? ''));
        $body = trim((string) ($stage->body ?? ''));
        $date = trim((string) ($stage->date_display ?? ''));

        $image = $this->stageImageBase64($stage);

        // No image AND no text means there is nothing for the AI to read at all.
        if ($image === null && trim($caption."\n".$body) === '') {
            return ['ok' => false, 'error' => 'This stage has no image or caption for the AI to read. Add one first.'];
        }

        $resp = $image !== null
            ? $this->askVision($image['base64'], $image['media_type'], $caption, $body, $date)
            : null;

        // Vision unavailable / failed, or no image: fall back to a text-only reading.
        $method = 'vision';
        if ($resp === null) {
            $method = 'text';
            $resp = $this->askText($caption, $body, $date);
        }

        if ($resp === null) {
            return ['ok' => false, 'error' => 'The AI service is unavailable right now. Please try again, or fill the fields in by hand.'];
        }
        if (trim($resp) === '') {
            return ['ok' => false, 'error' => 'The AI service returned no suggestion. Please try again, or fill the fields in by hand.'];
        }

        $meta = $this->parse($resp);
        if ($meta === null) {
            return ['ok' => false, 'error' => 'The AI suggestion could not be read. Please try again, or fill the fields in by hand.'];
        }

        return ['ok' => true, 'method' => $method, 'metadata' => $meta];
    }

    /** Ask the gateway VISION model to read the stage image. Null on any failure (caller falls back to text). */
    private function askVision(string $base64, string $mediaType, string $caption, string $body, string $date): ?string
    {
        try {
            $result = app(\AhgCore\Services\VoiceLLMService::class)->chat(
                $this->buildVisionPrompt($caption, $body, $date),
                $base64,
                ['max_tokens' => 400, 'temperature' => 0.2, 'media_type' => $mediaType]
            );
        } catch (\Throwable $e) {
            Log::warning('[ahg-exhibition] reconstruction annotate vision failed: '.$e->getMessage());

            return null;
        }

        return ($result['ok'] ?? false) && is_string($result['text'] ?? null) && trim($result['text']) !== ''
            ? $result['text']
            : null;
    }

    /** Ask the gateway TEXT model from the caption/body only. Null on failure. */
    private function askText(string $caption, string $body, string $date): ?string
    {
        if (trim($caption."\n".$body) === '') {
            return null;   // nothing to read, and the image path already failed
        }
        try {
            $resp = app(\AhgAiServices\Services\LlmService::class)
                ->complete($this->buildPrompt($caption, $body, $date), ['max_tokens' => 400, 'temperature' => 0.2]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-exhibition] reconstruction annotate LLM failed: '.$e->getMessage());

            return null;
        }

        return is_string($resp) ? $resp : null;
    }

    /**
     * Resolve a stage's locally-stored image to base64 + media type, or null when there
     * is no on-disk image (image_url-only stages stay text-only - no server-side fetch).
     * Large images are skipped (vision payload cap) so the gateway call stays sane.
     *
     * @return array{base64:string,media_type:string}|null
     */
    private function stageImageBase64(object $stage): ?array
    {
        $path = trim((string) ($stage->image_path ?? ''));
        if ($path === '') {
            return null;
        }
        $abs = app(\AhgExhibition\Services\ReconstructionService::class)->absoluteStagePath($path);
        if ($abs === null || ! is_file($abs) || ! is_readable($abs)) {
            return null;
        }
        $size = (int) @filesize($abs);
        if ($size <= 0 || $size > 12 * 1024 * 1024) {   // skip empty / oversized (>12MB) images
            return null;
        }
        $bytes = @file_get_contents($abs);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mediaType = in_array($ext, ['jpg', 'jpeg'], true) ? 'image/jpeg'
            : ($ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg')));

        return ['base64' => base64_encode($bytes), 'media_type' => $mediaType];
    }

    /**
     * Normalise a curator-submitted (or AI-suggested) metadata array into the stored
     * shape: each enumerated facet is constrained to its fixed list (falling back to
     * 'unknown' for picklists), free text is trimmed + capped. Returns null when every
     * field is empty, so the caller can store NULL and leave the stage un-annotated.
     */
    public function normalise(array $data): ?array
    {
        $dateEstimate = $this->cleanText($data['date_estimate'] ?? null, 120);
        $rationale = $this->cleanText($data['rationale'] ?? null, 1000);

        $evidenceType = $this->pick($data['evidence_type'] ?? null, self::EVIDENCE_TYPES, 'unknown');
        $confidence = $this->pick($data['confidence'] ?? null, self::CONFIDENCE_LEVELS, null);
        $sourceCredibility = $this->pick($data['source_credibility'] ?? null, self::SOURCE_CREDIBILITY, null);

        $meta = array_filter([
            'date_estimate' => $dateEstimate,
            'evidence_type' => $evidenceType,
            'confidence' => $confidence,
            'source_credibility' => $sourceCredibility,
            'rationale' => $rationale,
        ], fn ($v) => $v !== null && $v !== '');

        // An evidence_type of 'unknown' alone is not meaningful metadata.
        if (count($meta) === 1 && ($meta['evidence_type'] ?? null) === 'unknown') {
            return null;
        }

        return $meta ?: null;
    }

    /**
     * The labelled option lists for the admin form selects. Labels are humanised from
     * the fixed codes (small fixed picklist, not a dropdown taxonomy) so the partial
     * never hardcodes an <option> list of its own.
     *
     * @return array{evidence_type:array<int,array{code:string,label:string}>, confidence:array<int,array{code:string,label:string}>, source_credibility:array<int,array{code:string,label:string}>}
     */
    public function options(): array
    {
        return [
            'evidence_type' => $this->labelled(self::EVIDENCE_TYPES),
            'confidence' => $this->labelled(self::CONFIDENCE_LEVELS),
            'source_credibility' => $this->labelled(self::SOURCE_CREDIBILITY),
        ];
    }

    /** Build the gateway prompt asking for the four facets as strict JSON. */
    private function buildPrompt(string $caption, string $body, string $date): string
    {
        $types = implode(', ', self::EVIDENCE_TYPES);
        $conf = implode(', ', self::CONFIDENCE_LEVELS);
        $cred = implode(', ', self::SOURCE_CREDIBILITY);

        $stageText = 'Caption: '.($caption !== '' ? $caption : '(none)')."\n"
            .'Body: '.($body !== '' ? $body : '(none)')."\n"
            .'Date label shown: '.($date !== '' ? $date : '(none)');

        return "You are an archival evidence assessor helping a curator annotate one stage of a "
            ."virtual reconstruction of a lost building or place. Read the stage text below and "
            ."suggest structured provenance metadata for it. Be cautious: if the text does not "
            ."support a confident judgement, say so with a lower confidence and an 'unknown' "
            ."category rather than inventing detail.\n\n"
            ."STAGE TEXT:\n{$stageText}\n\n"
            ."Return ONLY valid JSON, no preamble, in exactly this shape:\n"
            ."{\"date_estimate\":\"a short human date or range, e.g. 'c. 1905' or 'early 1900s', or empty\","
            ."\"evidence_type\":\"one of: {$types}\","
            ."\"confidence\":\"one of: {$conf}\","
            ."\"source_credibility\":\"one of: {$cred}\","
            ."\"rationale\":\"one short sentence on why\"}";
    }

    /** Vision-prompt variant: the model reads the IMAGE, with the stage text as context. */
    private function buildVisionPrompt(string $caption, string $body, string $date): string
    {
        $types = implode(', ', self::EVIDENCE_TYPES);
        $conf = implode(', ', self::CONFIDENCE_LEVELS);
        $cred = implode(', ', self::SOURCE_CREDIBILITY);

        $context = 'Caption: '.($caption !== '' ? $caption : '(none)')."\n"
            .'Body: '.($body !== '' ? $body : '(none)')."\n"
            .'Date label shown: '.($date !== '' ? $date : '(none)');

        return "You are an archival evidence assessor helping a curator annotate one stage of a "
            ."virtual reconstruction of a lost building or place. LOOK AT THE IMAGE PROVIDED - it is "
            ."the evidence for this stage (it may be a historic photograph, a survey plan, an "
            ."architectural drawing, an engraving, or a modern render). Judge its provenance from what "
            ."you actually see, using the curator's notes below only as supporting context. Be cautious: "
            ."if the image does not support a confident judgement, say so with a lower confidence and an "
            ."'unknown' category rather than inventing detail.\n\n"
            ."CURATOR NOTES:\n{$context}\n\n"
            ."Return ONLY valid JSON, no preamble, in exactly this shape:\n"
            ."{\"date_estimate\":\"a short human date or range, e.g. 'c. 1905' or 'early 1900s', or empty\","
            ."\"evidence_type\":\"one of: {$types}\","
            ."\"confidence\":\"one of: {$conf}\","
            ."\"source_credibility\":\"one of: {$cred}\","
            ."\"rationale\":\"one short sentence on what you see in the image and why\"}";
    }

    /**
     * Parse the model response into the normalised metadata array, or null on failure.
     * Robust to preamble / code fences (extracts the first JSON object).
     */
    private function parse(string $resp): ?array
    {
        $json = $this->extractJsonObject($resp);
        if ($json === null) {
            return null;
        }
        $parsed = json_decode($json, true);
        if (! is_array($parsed)) {
            return null;
        }

        return $this->normalise($parsed);
    }

    /** Pull the first JSON object out of a model response. */
    private function extractJsonObject(string $resp): ?string
    {
        $resp = trim($resp);
        $start = strpos($resp, '{');
        $end = strrpos($resp, '}');
        if ($start !== false && $end !== false && $end > $start) {
            return substr($resp, $start, $end - $start + 1);
        }

        return null;
    }

    /** Constrain a value to an allowed list; return $default when it is not a member. */
    private function pick($value, array $allowed, ?string $default): ?string
    {
        if ($value === null) {
            return $default;
        }
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return $default;
        }

        return in_array($value, $allowed, true) ? $value : $default;
    }

    /** Trim + length-cap a free-text value; null when blank. */
    private function cleanText($value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    /** Humanise a list of snake_case codes into {code, label} option rows. */
    private function labelled(array $codes): array
    {
        return array_map(fn ($c) => [
            'code' => $c,
            'label' => ucfirst(str_replace('_', ' ', $c)),
        ], $codes);
    }
}
