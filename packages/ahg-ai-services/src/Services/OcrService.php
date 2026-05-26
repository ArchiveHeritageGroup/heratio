<?php

/**
 * OcrService - production Tesseract wrapper with multi-language support.
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
 * Issue #665 Phase 4 - production Tesseract integration.
 *
 * Wraps the `tesseract` CLI with:
 *   - multi-language packs (eng+afr+nld+osd ...)
 *   - configurable PSM / OEM
 *   - per-page TSV parsing (word-level boxes + confidence)
 *   - optional LLM post-correction (delegated to OcrLlmCorrector)
 *   - PREMIS event emission to preservation_event
 *   - persistence into iiif_ocr_text + iiif_ocr_block so OcrExportService
 *     (Phase 3) can render ALTO / hOCR / PAGE-XML afterwards.
 *
 * The service is intentionally process-level / synchronous: callers that
 * need queueing wrap the call in a Job (the dispatcher already exists in
 * ahg-scan's ProcessScanFile pipeline). The Tesseract binary path is
 * configurable; default "tesseract" on $PATH.
 *
 * @see /usr/share/nginx/heratio/docs/reference/ocr-phase-4-multilang-llm.md
 */
class OcrService
{
    public const DEFAULT_LANGS = 'osd+eng+afr';

    public const DEFAULT_PSM = 3;

    public const DEFAULT_OEM = 3;

    private string $binary;

    public function __construct(
        private ?LlmService $llm = null,
        private ?OcrLlmCorrector $corrector = null,
    ) {
        $this->binary = $this->setting('ocr_tesseract_binary', 'tesseract');
    }

    /**
     * Return the list of trained data packs available on the host.
     * One string per pack ("eng", "afr", "osd", ...). Filters out the
     * one-line banner Tesseract emits ("List of available languages ...").
     */
    public function listInstalledLanguages(): array
    {
        $cmd = escapeshellcmd($this->binary) . ' --list-langs 2>&1';
        $output = (string) @shell_exec($cmd);
        if ($output === '') {
            return [];
        }
        $langs = [];
        foreach (preg_split('/\R+/', trim($output)) as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'List of available') === 0) {
                continue;
            }
            if (preg_match('/^[A-Za-z0-9_-]{2,12}$/', $line)) {
                $langs[] = $line;
            }
        }
        sort($langs);
        return $langs;
    }

    /**
     * Probe `tesseract --version` and return the first line (e.g.
     * "tesseract 5.3.4"). Empty string when the binary is missing.
     */
    public function tesseractVersion(): string
    {
        $cmd = escapeshellcmd($this->binary) . ' --version 2>&1';
        $output = (string) @shell_exec($cmd);
        if ($output === '') {
            return '';
        }
        $line = strtok($output, "\n");
        return $line === false ? '' : trim((string) $line);
    }

    /**
     * Resolve the language spec to use for an information_object.
     *
     * Resolution order (first non-empty wins):
     *   1. Caller-supplied $lang
     *   2. ahg_setting.ocr_default_languages (operator override)
     *   3. information_object_i18n.language (per-IO record language)
     *   4. self::DEFAULT_LANGS
     *
     * Returns a Tesseract `+`-joined lang spec like "osd+eng+afr".
     */
    public function resolveLanguages(?int $ioId = null, ?string $lang = null): string
    {
        if (is_string($lang) && trim($lang) !== '') {
            return $this->normaliseLangSpec($lang);
        }
        $override = $this->setting('ocr_default_languages', '');
        if ($override !== '') {
            return $this->normaliseLangSpec($override);
        }
        if ($ioId !== null) {
            try {
                if (Schema::hasTable('information_object_i18n')) {
                    $row = DB::table('information_object_i18n')
                        ->where('id', $ioId)
                        ->value('language');
                    if (is_string($row) && trim($row) !== '') {
                        $iso = strtolower(trim($row));
                        return $this->normaliseLangSpec($this->iso639ToTesseract($iso));
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[ahg-ai] OcrService::resolveLanguages i18n lookup failed: '.$e->getMessage());
            }
        }
        return self::DEFAULT_LANGS;
    }

    /**
     * OCR a single image. Returns:
     *   [
     *     'success'    => bool,
     *     'text'       => string,
     *     'words'      => array<int,array{text,x,y,width,height,confidence}>,
     *     'confidence' => float|null,        // mean confidence across words
     *     'language'   => string,            // language spec used
     *     'version'    => string,            // tesseract version
     *     'duration_ms'=> int,
     *     'error'      => string|null,
     *   ]
     *
     * @param array{psm?:int,oem?:int,lang?:string,io_id?:int,digital_object_id?:int,llm_correct?:bool,persist?:bool} $opts
     */
    public function ocrImage(string $imagePath, array $opts = []): array
    {
        $started = microtime(true);
        $result = [
            'success'    => false,
            'text'       => '',
            'words'      => [],
            'confidence' => null,
            'language'   => '',
            'version'    => $this->tesseractVersion(),
            'duration_ms'=> 0,
            'error'      => null,
        ];

        if (! is_string($imagePath) || $imagePath === '' || ! @is_file($imagePath)) {
            $result['error'] = 'image_not_found';
            return $result;
        }

        $psm  = (int) ($opts['psm'] ?? (int) $this->setting('ocr_default_psm', (string) self::DEFAULT_PSM));
        $oem  = (int) ($opts['oem'] ?? (int) $this->setting('ocr_default_oem', (string) self::DEFAULT_OEM));
        $ioId = isset($opts['io_id']) ? (int) $opts['io_id'] : null;
        $doId = isset($opts['digital_object_id']) ? (int) $opts['digital_object_id'] : null;
        $lang = $this->resolveLanguages($ioId, $opts['lang'] ?? null);
        $result['language'] = $lang;

        $tmpOut = tempnam(sys_get_temp_dir(), 'heratio-ocr-');
        $tsvFile = $tmpOut . '.tsv';
        try {
            $cmd = sprintf(
                '%s %s %s -l %s --psm %d --oem %d tsv 2>/dev/null',
                escapeshellcmd($this->binary),
                escapeshellarg($imagePath),
                escapeshellarg($tmpOut),
                escapeshellarg($lang),
                $psm,
                $oem
            );
            @exec($cmd, $_outLines, $code);
            if (! is_file($tsvFile)) {
                $result['error'] = 'tesseract_failed';
                $this->emitPremis($ioId, $doId, 'ocr.tesseract', 'failure', $cmd, [
                    'language' => $lang,
                    'exit_code' => $code,
                ]);
                return $result;
            }

            $parsed = $this->parseTsv((string) @file_get_contents($tsvFile));
            $result['text']       = $parsed['text'];
            $result['words']      = $parsed['words'];
            $result['confidence'] = $parsed['mean_confidence'];
            $result['success']    = true;

            // Optional LLM post-correction (opt-in).
            $shouldCorrect = $opts['llm_correct'] ?? $this->correctionEnabled();
            if ($shouldCorrect && $result['text'] !== '') {
                $minConf = (float) $this->setting('ocr_llm_correction_min_confidence', '70');
                $pageConf = (float) ($result['confidence'] ?? 100);
                if ($pageConf < $minConf) {
                    $corrected = $this->postCorrect($result['text'], $imagePath, [
                        'io_id' => $ioId,
                        'digital_object_id' => $doId,
                        'tesseract_confidence' => $pageConf,
                        'language' => $lang,
                    ]);
                    if (is_array($corrected) && isset($corrected['text'])) {
                        $result['text']             = $corrected['text'];
                        $result['llm_corrected']    = true;
                        $result['corrections']      = $corrected['corrections'] ?? [];
                        $result['llm_model']        = $corrected['model'] ?? null;
                    }
                } else {
                    $result['llm_corrected'] = false;
                    $result['llm_skip_reason'] = 'page_confidence_above_threshold';
                }
            }

            // Persist into iiif_ocr_text + iiif_ocr_block when requested.
            if (! empty($opts['persist']) && $doId !== null) {
                $this->persist($ioId, $doId, $lang, $result);
            }

            $this->emitPremis($ioId, $doId, 'ocr.tesseract', 'success', sprintf(
                'tesseract %s lang=%s psm=%d oem=%d conf=%.2f',
                $result['version'],
                $lang,
                $psm,
                $oem,
                (float) ($result['confidence'] ?? 0)
            ), [
                'language'   => $lang,
                'psm'        => $psm,
                'oem'        => $oem,
                'confidence' => $result['confidence'],
                'word_count' => count($result['words']),
            ]);

            return $result;
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            Log::error('[ahg-ai] OcrService::ocrImage threw: '.$e->getMessage());
            $this->emitPremis($ioId, $doId, 'ocr.tesseract', 'failure', $e->getMessage(), [
                'language' => $lang,
            ]);
            return $result;
        } finally {
            @unlink($tmpOut);
            @unlink($tsvFile);
            $result['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
        }
    }

    /**
     * Run LLM post-correction over Tesseract text. Always emits its own
     * PREMIS event + receipt-chain entry via OcrLlmCorrector.
     */
    public function postCorrect(string $text, ?string $imagePath = null, array $context = []): ?array
    {
        $corrector = $this->corrector;
        if ($corrector === null) {
            try {
                $corrector = app(OcrLlmCorrector::class);
            } catch (\Throwable $e) {
                $corrector = new OcrLlmCorrector($this->llm ?? new LlmService());
            }
        }
        return $corrector->correct($text, $imagePath, $context);
    }

    /**
     * Persist OCR output into iiif_ocr_text + iiif_ocr_block.
     */
    private function persist(?int $ioId, int $doId, string $lang, array $result): void
    {
        if (! Schema::hasTable('iiif_ocr_text') || ! Schema::hasTable('iiif_ocr_block')) {
            return;
        }
        try {
            $ocrId = DB::table('iiif_ocr_text')->insertGetId([
                'digital_object_id' => $doId,
                'object_id'         => $ioId ?? 0,
                'full_text'         => $result['text'],
                'format'            => 'plain',
                'language'          => substr(preg_replace('/[^A-Za-z]+/', '', $lang) ?: 'en', 0, 10),
                'confidence'        => $result['confidence'],
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
            $order = 0;
            foreach ($result['words'] as $w) {
                $text = (string) ($w['text'] ?? '');
                if ($text === '') {
                    continue;
                }
                DB::table('iiif_ocr_block')->insert([
                    'ocr_id'      => $ocrId,
                    'page_number' => 1,
                    'block_type'  => 'word',
                    'text'        => mb_substr($text, 0, 1000),
                    'x'           => (int) ($w['x'] ?? 0),
                    'y'           => (int) ($w['y'] ?? 0),
                    'width'       => (int) ($w['width'] ?? 0),
                    'height'      => (int) ($w['height'] ?? 0),
                    'confidence'  => isset($w['confidence']) ? (float) $w['confidence'] : null,
                    'block_order' => ++$order,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] OcrService::persist failed: '.$e->getMessage());
        }
    }

    /**
     * Parse Tesseract TSV output into word boxes + recombined text.
     *
     * @return array{text:string,words:array,mean_confidence:?float}
     */
    public function parseTsv(string $tsv): array
    {
        $lines = preg_split('/\R+/', trim($tsv));
        $words = [];
        $confSum = 0.0;
        $confN   = 0;
        $textParts = [];
        $currentLine = '';
        $lastLineKey = null;
        if ($lines === false || count($lines) < 2) {
            return ['text' => '', 'words' => [], 'mean_confidence' => null];
        }
        // Header line tells us column ordering; pin to default Tesseract TSV.
        foreach ($lines as $i => $line) {
            if ($i === 0) { continue; }
            $cols = explode("\t", $line);
            if (count($cols) < 12) { continue; }
            // level=cols[0]; 5 == WORD per Tesseract docs.
            if ((int) $cols[0] !== 5) { continue; }
            $word = trim($cols[11]);
            if ($word === '') { continue; }
            $conf = (float) $cols[10];
            if ($conf < 0) { $conf = 0.0; }
            $words[] = [
                'text'       => $word,
                'x'          => (int) $cols[6],
                'y'          => (int) $cols[7],
                'width'      => (int) $cols[8],
                'height'     => (int) $cols[9],
                'confidence' => $conf,
            ];
            $confSum += $conf;
            $confN++;
            $lineKey = $cols[1].'/'.$cols[2].'/'.$cols[3].'/'.$cols[4];
            if ($lastLineKey !== null && $lineKey !== $lastLineKey) {
                $textParts[] = $currentLine;
                $currentLine = '';
            }
            $currentLine .= ($currentLine === '' ? '' : ' ').$word;
            $lastLineKey = $lineKey;
        }
        if ($currentLine !== '') {
            $textParts[] = $currentLine;
        }
        return [
            'text'            => implode("\n", $textParts),
            'words'           => $words,
            'mean_confidence' => $confN ? round($confSum / $confN, 2) : null,
        ];
    }

    /**
     * Map an ISO 639-1 / -2 code (from information_object_i18n.language)
     * to a Tesseract trained-data tag. Only the SADC + EU core set is
     * tabulated; unknown codes fall through unchanged so operators can
     * install custom traineddata and have it accepted by Tesseract.
     */
    public function iso639ToTesseract(string $iso): string
    {
        static $map = [
            'en' => 'eng', 'eng' => 'eng',
            'af' => 'afr', 'afr' => 'afr',
            'nl' => 'nld', 'nld' => 'nld', 'dut' => 'nld',
            'zu' => 'zul', 'zul' => 'zul',
            'xh' => 'xho', 'xho' => 'xho',
            'st' => 'sot', 'sot' => 'sot',
            'tn' => 'tsn', 'tsn' => 'tsn',
            'ts' => 'tso', 'tso' => 'tso',
            've' => 'ven', 'ven' => 'ven',
            'nr' => 'nbl', 'nbl' => 'nbl',
            'ss' => 'ssw', 'ssw' => 'ssw',
            'nso' => 'nso',
            'sn' => 'sna', 'sna' => 'sna',
            'pt' => 'por', 'por' => 'por',
            'fr' => 'fra', 'fra' => 'fra', 'fre' => 'fra',
            'de' => 'deu', 'deu' => 'deu', 'ger' => 'deu',
            'es' => 'spa', 'spa' => 'spa',
            'it' => 'ita', 'ita' => 'ita',
            'la' => 'lat', 'lat' => 'lat',
        ];
        $code = strtolower($iso);
        return $map[$code] ?? $iso;
    }

    /**
     * Normalise an arbitrary language spec ("eng,afr", "eng afr", "ENG+AFR")
     * into Tesseract's canonical "eng+afr" form.
     */
    private function normaliseLangSpec(string $spec): string
    {
        $parts = preg_split('/[\s,+]+/', strtolower(trim($spec))) ?: [];
        $parts = array_values(array_filter(array_unique(array_map('trim', $parts))));
        return $parts ? implode('+', $parts) : self::DEFAULT_LANGS;
    }

    private function correctionEnabled(): bool
    {
        $v = $this->setting('ocr_llm_correction_enabled', '0');
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    private function setting(string $key, string $default = ''): string
    {
        try {
            if (Schema::hasTable('ahg_ai_settings')) {
                $v = DB::table('ahg_ai_settings')
                    ->where('feature', 'ocr')
                    ->where('setting_key', $key)
                    ->value('setting_value');
                if (is_string($v) && $v !== '') {
                    return $v;
                }
                // Fall back to general scope so an operator can put it under "general".
                $v = DB::table('ahg_ai_settings')
                    ->where('feature', 'general')
                    ->where('setting_key', $key)
                    ->value('setting_value');
                if (is_string($v) && $v !== '') {
                    return $v;
                }
            }
            if (Schema::hasTable('ahg_settings')) {
                $v = DB::table('ahg_settings')->where('setting_key', $key)->value('setting_value');
                if (is_string($v) && $v !== '') {
                    return $v;
                }
            }
        } catch (\Throwable $e) {
            // Schema not yet installed - return default.
        }
        return $default;
    }

    /**
     * Emit a preservation_event row. Best-effort; failure is logged but
     * never thrown so the OCR pipeline can not be blocked.
     */
    public function emitPremis(
        ?int $ioId,
        ?int $doId,
        string $type,
        string $outcome,
        ?string $detail,
        array $detailExtra = []
    ): ?int {
        if (! Schema::hasTable('preservation_event')) {
            return null;
        }
        try {
            return DB::table('preservation_event')->insertGetId([
                'digital_object_id'     => $doId,
                'information_object_id' => $ioId,
                'event_type'            => $type,
                'event_datetime'        => now(),
                'event_detail'          => $detail,
                'event_outcome'         => $outcome,
                'event_outcome_detail'  => $detailExtra ? json_encode($detailExtra, JSON_UNESCAPED_SLASHES) : null,
                'linking_agent_type'    => 'software',
                'linking_agent_value'   => 'ahg-ai-services:OcrService '.($this->tesseractVersion() ?: 'tesseract'),
                'linking_object_type'   => $doId ? 'digital_object' : ($ioId ? 'information_object' : null),
                'linking_object_value'  => $doId ? (string) $doId : ($ioId ? (string) $ioId : null),
                'created_at'            => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] OcrService::emitPremis failed: '.$e->getMessage());
            return null;
        }
    }
}
