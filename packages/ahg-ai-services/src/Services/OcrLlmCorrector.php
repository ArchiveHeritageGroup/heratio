<?php

/**
 * OcrLlmCorrector - LLM post-correction for Tesseract output.
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

declare(strict_types=1);

namespace AhgAiServices\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #665 Phase 4 - LLM post-correction over raw Tesseract text.
 *
 * Tesseract has predictable error patterns (rn->m, O vs 0, l vs 1,
 * cl->d, c1->cl). An LLM with strict instructions can clean these up
 * while preserving the original wording, archaic spelling, and proper
 * nouns.
 *
 * Every correction call:
 *   - emits an inference receipt to the #693 receipt chain (via the
 *     LlmService::complete logInferenceReceipt path)
 *   - writes a row to ahg_audit_log / security_audit_log via AuditLog
 *     so an auditor can reconstruct what the model rewrote
 *   - writes a `ocr.llm_correction` preservation_event so PREMIS rights
 *     reports include the AI step in the chain of custody.
 *
 * The prompt is deliberately conservative: ONLY OCR-typical fixes, no
 * paraphrasing, marked inline so a reviewer can audit every change.
 *
 * Output JSON contract:
 *   {
 *     "text":        "<corrected text>",
 *     "corrections": [
 *       {"orig": "rn", "corrected": "m", "context": "...modem..."}
 *     ],
 *     "model":       "<model id>",
 *     "duration_ms": <int>,
 *     "skipped":     true|false,
 *     "reason":      "<why if skipped>"
 *   }
 */
class OcrLlmCorrector
{
    private ?LlmService $llm;

    /**
     * The LlmService is optional at construction so unit tests can build
     * the corrector without bootstrapping the Laravel container. Real
     * runtime use always passes one in via the service provider.
     */
    public function __construct(?LlmService $llm = null)
    {
        $this->llm = $llm;
    }

    private function llm(): LlmService
    {
        if ($this->llm === null) {
            $this->llm = new LlmService();
        }
        return $this->llm;
    }

    /**
     * Correct OCR errors in $text using the configured LLM. The image
     * path is OPTIONAL: vision LLMs can use it, text-only LLMs ignore it.
     *
     * @param array{io_id?:int,digital_object_id?:int,tesseract_confidence?:float,language?:string} $context
     */
    public function correct(string $text, ?string $imagePath = null, array $context = []): ?array
    {
        if (trim($text) === '') {
            return null;
        }
        $started = microtime(true);
        $prompt = $this->buildPrompt($text, $context);
        $response = null;
        $modelId = null;
        try {
            $response = $this->llm()->complete($prompt, [
                'temperature' => 0.0,
                'data_scope'  => 'ocr_correction',
                'purpose'     => 'ocr_post_correction',
            ]);
            // Best-effort capture of the chosen model name.
            try {
                $cfg = $this->llm()->getDefaultConfig();
                $modelId = $cfg->name ?? ($cfg->provider ?? null);
            } catch (\Throwable $e) {
                // ignore
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] OcrLlmCorrector LLM call threw: '.$e->getMessage());
            return [
                'text'        => $text,
                'corrections' => [],
                'model'       => $modelId,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'skipped'     => true,
                'reason'      => 'llm_call_failed',
            ];
        }

        if (! is_string($response) || trim($response) === '') {
            return [
                'text'        => $text,
                'corrections' => [],
                'model'       => $modelId,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'skipped'     => true,
                'reason'      => 'empty_response',
            ];
        }

        $parsed = $this->parseInline($response);
        $duration = (int) round((microtime(true) - $started) * 1000);

        // heratio#1525: a correction that changes too much is a rewrite, not
        // a correction (Brenthurst: "trekking for" became "trying to get to").
        // Keep the raw OCR rather than a fluent invention.
        $guard = self::rewriteGuard($text, $parsed['text']);
        if ($guard['rejected']) {
            Log::info('[ahg-ai] OcrLlmCorrector rejected a rewrite', $guard + ['io_id' => $context['io_id'] ?? null]);

            return [
                'text'        => $text,
                'corrections' => [],
                'model'       => $modelId,
                'duration_ms' => $duration,
                'skipped'     => true,
                'reason'      => 'rewrite_guard',
                'guard'       => $guard,
            ];
        }

        // Audit + PREMIS.
        $this->audit(
            $context['io_id'] ?? null,
            $context['digital_object_id'] ?? null,
            $text,
            $parsed['text'],
            $parsed['corrections'],
            $modelId,
            $context
        );
        $this->emitPremis(
            $context['io_id'] ?? null,
            $context['digital_object_id'] ?? null,
            $modelId,
            $parsed['corrections'],
            $duration
        );

        return [
            'text'        => $parsed['text'],
            'corrections' => $parsed['corrections'],
            'model'       => $modelId,
            'duration_ms' => $duration,
            'skipped'     => false,
            'reason'      => null,
        ];
    }

    /** Words changed and length drift above which a "correction" is rejected. */
    public const MAX_WORD_CHANGE = 0.20;

    public const MAX_LENGTH_DRIFT = 0.12;

    /**
     * How far the corrected text strays from the raw OCR: the share of words
     * not kept in order (1 - LCS / longer word count) and the relative change
     * in length. Either over its limit rejects the correction.
     *
     * @return array{word_change: float, length_drift: float, rejected: bool}
     */
    public static function rewriteGuard(string $raw, string $corrected): array
    {
        $a = preg_split('/\s+/u', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $b = preg_split('/\s+/u', trim($corrected), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $longer = max(count($a), count($b));
        if ($longer === 0) {
            return ['word_change' => 0.0, 'length_drift' => 0.0, 'rejected' => false];
        }

        // ponytail: O(n*m) word LCS, fine for a page; over 4,000 words a side the
        // guard compares the first 4,000. Upgrade: a linear-space diff.
        $a = array_slice($a, 0, 4000);
        $b = array_slice($b, 0, 4000);
        $prev = array_fill(0, count($b) + 1, 0);
        foreach ($a as $wa) {
            $cur = [0];
            foreach ($b as $j => $wb) {
                $cur[$j + 1] = $wa === $wb ? $prev[$j] + 1 : max($prev[$j + 1], $cur[$j]);
            }
            $prev = $cur;
        }
        $wordChange = 1 - end($prev) / max(count($a), count($b));
        $rawLen = max(1, mb_strlen(trim($raw)));
        $lengthDrift = abs(mb_strlen(trim($corrected)) - $rawLen) / $rawLen;

        return [
            'word_change' => round($wordChange, 4),
            'length_drift' => round($lengthDrift, 4),
            'rejected' => $wordChange > self::MAX_WORD_CHANGE || $lengthDrift > self::MAX_LENGTH_DRIFT,
        ];
    }

    /**
     * Build the conservative post-correction prompt. The model is
     * instructed to mark inline corrections as [orig->corrected]; that
     * marker is extracted back into a structured corrections array, then
     * stripped from the final text.
     */
    public function buildPrompt(string $text, array $context = []): string
    {
        $lang = $context['language'] ?? null;
        $conf = $context['tesseract_confidence'] ?? null;
        $bits = [];
        $bits[] = 'You are an archival OCR proof-reader. The text below was extracted by Tesseract and may contain typical OCR errors:';
        $bits[] = '- rn read as m, m read as rn';
        $bits[] = '- O read as 0, 0 read as O';
        $bits[] = '- l read as 1, 1 read as l, I read as l';
        $bits[] = '- cl read as d, c1 read as cl';
        $bits[] = '- broken hyphenation across line ends';
        $bits[] = '';
        $bits[] = 'STRICT RULES:';
        $bits[] = '1. Only correct OCR-typical errors. Do NOT paraphrase, modernise spelling, or restructure sentences.';
        $bits[] = '2. Preserve archaic spelling (e.g. shew, hath, geweten) and proper nouns exactly as they appear.';
        $bits[] = '3. Mark every correction inline as [orig->corrected]. The original wording stays in the bracket so a reviewer can audit each change.';
        $bits[] = '4. If a passage is illegible, leave it unchanged.';
        $bits[] = '5. Return ONLY the corrected text. No commentary, no headers.';
        if (is_string($lang) && $lang !== '') {
            $bits[] = '';
            $bits[] = 'Source language(s): '.$lang.'.';
        }
        if (is_numeric($conf)) {
            $bits[] = 'Tesseract page confidence: '.number_format((float) $conf, 1).' / 100.';
        }
        $bits[] = '';
        $bits[] = '--- BEGIN OCR TEXT ---';
        $bits[] = $text;
        $bits[] = '--- END OCR TEXT ---';
        return implode("\n", $bits);
    }

    /**
     * Pull [orig->corrected] markers back out of the LLM response.
     *
     * @return array{text:string,corrections:array<int,array{orig:string,corrected:string,context:string}>}
     */
    public function parseInline(string $response): array
    {
        $corrections = [];
        $offset = 0;
        $cleaned = '';
        $pattern = '/\[([^\]\-][^\]]*?)->([^\]]*?)\]/u';
        if (preg_match_all($pattern, $response, $matches, PREG_OFFSET_CAPTURE) === false) {
            return ['text' => $response, 'corrections' => []];
        }
        foreach ($matches[0] as $i => $whole) {
            $start = (int) $whole[1];
            $cleaned .= substr($response, $offset, $start - $offset);
            $orig      = trim((string) $matches[1][$i][0]);
            $corrected = trim((string) $matches[2][$i][0]);
            $contextStart = max(0, $start - 20);
            $contextLen   = strlen((string) $whole[0]) + 40;
            $ctx = substr($response, $contextStart, $contextLen);
            $corrections[] = [
                'orig'      => $orig,
                'corrected' => $corrected,
                'context'   => trim(preg_replace('/\s+/', ' ', (string) $ctx)),
            ];
            $cleaned .= $corrected;
            $offset = $start + strlen((string) $whole[0]);
        }
        $cleaned .= substr($response, $offset);
        return ['text' => $cleaned, 'corrections' => $corrections];
    }

    /**
     * Write an audit row per correction so the chain-of-custody record
     * shows exactly what the model rewrote. Uses captureMutation so the
     * record is keyed off the IO id.
     */
    private function audit(
        ?int $ioId,
        ?int $doId,
        string $originalText,
        string $correctedText,
        array $corrections,
        ?string $modelId,
        array $context
    ): void {
        if (empty($corrections)) {
            return;
        }
        try {
            if (class_exists(\AhgCore\Support\AuditLog::class)) {
                \AhgCore\Support\AuditLog::captureSecondaryMutation(
                    (int) ($ioId ?? $doId ?? 0),
                    'ocr_llm_correction',
                    'ocr.llm_correction',
                    [
                        'data' => [
                            'corrections'        => $corrections,
                            'correction_count'   => count($corrections),
                            'model'              => $modelId,
                            'original_length'    => mb_strlen($originalText),
                            'corrected_length'   => mb_strlen($correctedText),
                            'io_id'              => $ioId,
                            'digital_object_id'  => $doId,
                            'tesseract_confidence' => $context['tesseract_confidence'] ?? null,
                            'language'           => $context['language'] ?? null,
                        ],
                    ]
                );
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] OcrLlmCorrector audit-log call failed: '.$e->getMessage());
        }
        // Manual fallback if AuditLog isn't available.
        try {
            if (Schema::hasTable('ahg_audit_log')) {
                DB::table('ahg_audit_log')->insert([
                    'user_id'     => null,
                    'action'      => 'ocr.llm_correction',
                    'object_type' => 'digital_object',
                    'object_id'   => $doId,
                    'details'     => json_encode([
                        'corrections' => $corrections,
                        'model'       => $modelId,
                    ], JSON_UNESCAPED_SLASHES),
                    'created_at'  => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] OcrLlmCorrector fallback audit failed: '.$e->getMessage());
        }
    }

    private function emitPremis(?int $ioId, ?int $doId, ?string $modelId, array $corrections, int $durationMs): void
    {
        if (! Schema::hasTable('preservation_event')) {
            return;
        }
        try {
            $outcome = empty($corrections) ? 'success' : 'success';
            DB::table('preservation_event')->insertGetId([
                'digital_object_id'     => $doId,
                'information_object_id' => $ioId,
                'event_type'            => 'ocr.llm_correction',
                'event_datetime'        => now(),
                'event_detail'          => sprintf(
                    'LLM post-correction (%d edits) by %s in %dms',
                    count($corrections),
                    $modelId ?? 'unknown-llm',
                    $durationMs
                ),
                'event_outcome'         => $outcome,
                'event_outcome_detail'  => json_encode([
                    'model'             => $modelId,
                    'correction_count'  => count($corrections),
                    'corrections'       => array_slice($corrections, 0, 40),
                    'duration_ms'       => $durationMs,
                ], JSON_UNESCAPED_SLASHES),
                'linking_agent_type'    => 'software',
                'linking_agent_value'   => 'ahg-ai-services:OcrLlmCorrector '.($modelId ?? ''),
                'linking_object_type'   => $doId ? 'digital_object' : ($ioId ? 'information_object' : null),
                'linking_object_value'  => $doId ? (string) $doId : ($ioId ? (string) $ioId : null),
                'created_at'            => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] OcrLlmCorrector::emitPremis failed: '.$e->getMessage());
        }
    }
}
