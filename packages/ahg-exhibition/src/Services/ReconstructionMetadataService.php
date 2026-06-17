<?php

/**
 * ReconstructionMetadataService - heratio#1206 "walk through what no longer exists".
 *
 * OPTIONAL AI "evidence layer" annotator for a reconstruction rebuild stage. Given a
 * stage (its caption + body + date label), it asks the AI to SUGGEST structured
 * provenance metadata - a date estimate, an evidence type, a confidence level and a
 * source-credibility judgement - which the curator then reviews, edits and saves.
 *
 * The suggestion is grounded in the stage's CAPTION / BODY text only (not the image
 * bytes): the AI gateway's text completion is the sanctioned door, and a caption is
 * usually the richest cue anyway. The saved, curator-confirmed metadata is stored as
 * JSON on ahg_reconstruction_stage.metadata and surfaced read-only in the montage.
 *
 * All AI calls route through the AHG gateway via AhgAiServices\LlmService - never a
 * direct GPU node / model endpoint. Fully fail-soft: any gateway failure (down,
 * quota, garbage output) returns ['ok' => false, ...] and never throws, so the
 * Annotate action can flash a friendly error instead of a 500.
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
     * Suggest structured provenance metadata for a stage from its text. Returns:
     *   ['ok' => true,  'metadata' => [date_estimate, evidence_type, confidence,
     *                                   source_credibility, rationale]]
     *   ['ok' => false, 'error' => '<friendly message>']
     * Never throws - the caller flashes the error rather than 500ing.
     */
    public function suggest(object $stage): array
    {
        $caption = trim((string) ($stage->caption ?? ''));
        $body = trim((string) ($stage->body ?? ''));
        $date = trim((string) ($stage->date_display ?? ''));

        $text = trim($caption."\n".$body);
        if ($text === '') {
            return ['ok' => false, 'error' => 'This stage has no caption or body text for the AI to read. Add a caption first.'];
        }

        $prompt = $this->buildPrompt($caption, $body, $date);

        try {
            $resp = app(\AhgAiServices\Services\LlmService::class)
                ->complete($prompt, ['max_tokens' => 400, 'temperature' => 0.2]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-exhibition] reconstruction annotate LLM failed: '.$e->getMessage());

            return ['ok' => false, 'error' => 'The AI service is unavailable right now. Please try again, or fill the fields in by hand.'];
        }

        if (! is_string($resp) || trim($resp) === '') {
            return ['ok' => false, 'error' => 'The AI service returned no suggestion. Please try again, or fill the fields in by hand.'];
        }

        $meta = $this->parse($resp);
        if ($meta === null) {
            return ['ok' => false, 'error' => 'The AI suggestion could not be read. Please try again, or fill the fields in by hand.'];
        }

        return ['ok' => true, 'metadata' => $meta];
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
