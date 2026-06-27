<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgRdm\Services;

use AhgAiServices\Exceptions\QuotaExceededException;
use AhgAiServices\Services\NerService;
use AhgAiServices\Services\OcrService;
use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\EncryptionService;
use AhgPdfTools\Services\PdfTextExtractService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * POPIA sensitivity scan (#1339) - the value AND the liability of ahg-rdm, so:
 * deterministic-first, AI-augmented, human-final.
 *
 * - Deterministic backbone (NO LLM, cannot hallucinate): SA ID (Luhn + embedded
 *   date), email, SA phone, passport. High precision + explainable - the demo's
 *   headline finding.
 * - Special-category lexicon: health/religion/biometric terms -> flag for review.
 * - NER augmentation (lower trust): NerService persons/places/orgs, always
 *   'AI-suggested', routed through the AI gateway (never a node). Best-effort -
 *   if disabled/over-quota/down, the deterministic detectors stand alone.
 *
 * The scan NEVER auto-decides. It records findings (review_status='pending') and
 * a dataset verdict; a human confirms/overrides each in the gate (#1340).
 */
class PopiaScanService
{
    /** Illustrative special-category lexicon (health / religion / biometric / orientation). Expandable. */
    private const SPECIAL_TERMS = [
        'hiv', 'aids', 'diabetes', 'cancer', 'depression', 'pregnan', 'disability',
        'psychiatric', 'medication', 'diagnosis', 'treatment', 'mental health',
        'religio', 'church', 'mosque', 'muslim', 'christian', 'jewish', 'hindu',
        'biometric', 'fingerprint', 'dna', 'genetic', 'sexual orientation',
        'political party', 'trade union',
    ];

    /** Max chars sent to NER per file (keeps the gateway call bounded). */
    private const NER_TEXT_CAP = 20000;

    /** Below this many non-whitespace chars, a PDF's text layer is treated as
     *  empty/near-empty and we fall back to rasterise + OCR (#1346). */
    private const PDF_TEXTLAYER_MIN_CHARS = 24;

    /** OCR fallback bounds for scanned PDFs (#1346): page cap + raster DPI. */
    private const PDF_OCR_PAGE_CAP = 10;

    private const PDF_OCR_DPI = 200;

    /**
     * Scan every file in a dataset, persist findings, set the verdict.
     *
     * @return array{verdict:string, findings:int, files:int, scanned:int}
     */
    public function scanDataset(int $datasetId): array
    {
        $files = DB::table('rdm_dataset_file')->where('dataset_id', $datasetId)->get();

        // Re-scan is idempotent: clear prior findings first.
        DB::table('rdm_scan_finding')->where('dataset_id', $datasetId)->delete();
        DB::table('rdm_dataset')->where('id', $datasetId)->update(['status' => 'scanning']);

        $findingsCount = 0;
        $scanned = 0;
        $hasSpecial = false;
        $hasPersonal = false;

        foreach ($files as $f) {
            $extract = $this->extractContent($f);
            if ($extract === null || trim($extract['text']) === '') {
                continue; // unreadable / scanned-PDF-no-OCR / unsupported -> skip
            }
            $text = $extract['text'];
            $scanned++;

            $found = array_merge(
                $this->deterministic($text),
                $this->specialCategory($text),
                $this->ner($text)
            );

            // OCR introduces noise: demote OCR-derived findings one confidence
            // notch so the human gate treats them as lower-trust (#1346). Still
            // 'pending', still the same type/category/method.
            if ($extract['via_ocr']) {
                $found = array_map(function ($fd) {
                    $fd['confidence'] = $this->demote($fd['confidence']);

                    return $fd;
                }, $found);
            }

            foreach ($found as $fd) {
                DB::table('rdm_scan_finding')->insert([
                    'dataset_id'      => $datasetId,
                    'dataset_file_id' => $f->id,
                    'file_name'       => $f->original_name,
                    'type'            => $fd['type'],
                    'category'        => $fd['category'],
                    'sample'          => $fd['sample'],
                    'confidence'      => $fd['confidence'],
                    'method'          => $fd['method'],
                    'review_status'   => 'pending',
                    'created_at'      => now(),
                ]);
                $findingsCount++;
                $hasSpecial = $hasSpecial || $fd['category'] === 'special_category';
                $hasPersonal = $hasPersonal || $fd['category'] === 'personal';
            }
        }

        $verdict = $hasSpecial ? 'SPECIAL_CATEGORY' : ($hasPersonal ? 'PERSONAL' : 'CLEAR');
        // CLEAR -> back to draft (publishable); anything with PII -> human review gate (#1340).
        $status = $verdict === 'CLEAR' ? 'draft' : 'review';

        DB::table('rdm_dataset')->where('id', $datasetId)->update([
            'verdict'    => $verdict,
            'status'     => $status,
            'scanned_at' => now(),
            'updated_at' => now(),
        ]);

        return ['verdict' => $verdict, 'findings' => $findingsCount, 'files' => $files->count(), 'scanned' => $scanned];
    }

    // --- text extraction (reuses existing services) ------------------------

    /**
     * Extract a file's text for scanning, recording whether it came via OCR
     * (lower-trust). PDFs prefer the born-digital text layer and only fall back
     * to rasterise + OCR when that layer is empty/near-empty (#1346).
     *
     * @return array{text:string, via_ocr:bool}|null
     */
    private function extractContent(object $fileRow): ?array
    {
        $master = DB::table('digital_object')
            ->where('object_id', $fileRow->io_id)
            ->whereNull('parent_id')
            ->first();
        if (! $master) {
            return null;
        }

        $path = DigitalObjectService::resolveDiskPath($master);
        if (! $path || ! is_file($path)) {
            return null;
        }

        $mime = strtolower((string) ($master->mime_type ?? ''));
        $enc = app(EncryptionService::class);

        // PDF: pdftotext first (born-digital). When the text layer is empty/
        // near-empty (a scanned/image-only PDF), fall back to rasterise + OCR
        // so consent forms etc. - the docs most likely to carry PII - are still
        // scanned (#1346).
        if (str_contains($mime, 'pdf')) {
            // Decrypt-at-rest once; both pdftotext and the OCR raster reuse it.
            $scanPath = $path;
            $tmp = null;
            if ($enc->isFileEncrypted($path)) {
                $bytes = $enc->streamFileDecrypted($path);
                if ($bytes === null) {
                    return null;
                }
                $tmp = tempnam(sys_get_temp_dir(), 'rdmpdf').'.pdf';
                file_put_contents($tmp, $bytes);
                $scanPath = $tmp;
            }

            try {
                $text = '';
                $pdf = app(PdfTextExtractService::class);
                if ($pdf->isPdftotextAvailable()) {
                    $text = (string) ($pdf->extractText($scanPath) ?? '');
                } else {
                    Log::info('[PopiaScan] pdftotext unavailable; trying OCR for '.$fileRow->original_name);
                }

                if (mb_strlen(trim($text)) >= self::PDF_TEXTLAYER_MIN_CHARS) {
                    return ['text' => $text, 'via_ocr' => false];
                }

                // Empty/near-empty text layer -> OCR fallback.
                $ocr = $this->ocrPdf($scanPath, $fileRow->original_name);
                if ($ocr !== null && trim($ocr) !== '') {
                    return ['text' => $ocr, 'via_ocr' => true];
                }

                // OCR gave nothing usable: keep whatever sliver pdftotext found.
                return trim($text) === '' ? null : ['text' => $text, 'via_ocr' => false];
            } catch (\Throwable $e) {
                Log::info('[PopiaScan] PDF extract failed: '.$e->getMessage());

                return null;
            } finally {
                if ($tmp) {
                    @unlink($tmp);
                }
            }
        }

        // Image -> Tesseract OCR (local; degrades to null when unavailable).
        if (str_starts_with($mime, 'image/')) {
            try {
                $r = app(OcrService::class)->ocrImage($path);
                if (! ($r['success'] ?? false)) {
                    return null;
                }

                return ['text' => (string) ($r['text'] ?? ''), 'via_ocr' => true];
            } catch (\Throwable $e) {
                return null;
            }
        }

        // Text-like (csv/txt/json/xml/plain): read bytes, decrypting at rest if needed.
        $bytes = $enc->isFileEncrypted($path) ? $enc->streamFileDecrypted($path) : @file_get_contents($path);

        return is_string($bytes) ? ['text' => $bytes, 'via_ocr' => false] : null;
    }

    /**
     * Rasterise a scanned PDF (up to PDF_OCR_PAGE_CAP pages) and OCR each page
     * via the shared OcrService, returning the concatenated text. Reuses host
     * tooling only (pdftoppm from poppler-utils, same family as pdftotext;
     * tesseract via OcrService). Returns null when nothing is recoverable.
     */
    private function ocrPdf(string $pdfPath, string $name): ?string
    {
        if (! is_executable('/usr/bin/pdftoppm')) {
            Log::info('[PopiaScan] pdftoppm unavailable; cannot OCR scanned PDF '.$name);

            return null;
        }

        $dir = sys_get_temp_dir().'/rdmocr_'.bin2hex(random_bytes(6));
        if (! @mkdir($dir) && ! is_dir($dir)) {
            return null;
        }

        try {
            // pdftoppm zero-pads page suffixes to the range width, so glob+sort
            // rather than guess the filenames. -l caps work on huge PDFs.
            $cmd = sprintf(
                '/usr/bin/pdftoppm -jpeg -r %d -f 1 -l %d %s %s 2>/dev/null',
                self::PDF_OCR_DPI,
                self::PDF_OCR_PAGE_CAP,
                escapeshellarg($pdfPath),
                escapeshellarg($dir.'/p')
            );
            @exec($cmd, $out, $rc);

            $pages = glob($dir.'/p-*.jpg') ?: [];
            sort($pages, SORT_NATURAL);
            if (! $pages) {
                Log::info('[PopiaScan] OCR raster produced no pages for '.$name.' (rc='.$rc.')');

                return null;
            }

            $ocr = app(OcrService::class);
            $parts = [];
            foreach ($pages as $img) {
                try {
                    $r = $ocr->ocrImage($img);
                    if (($r['success'] ?? false) && trim((string) ($r['text'] ?? '')) !== '') {
                        $parts[] = (string) $r['text'];
                    }
                } catch (\Throwable $e) {
                    // one bad page shouldn't sink the whole document
                }
            }

            if (! $parts) {
                return null;
            }
            Log::info('[PopiaScan] OCR fallback scanned '.count($pages).' page(s) of '.$name.' (cap '.self::PDF_OCR_PAGE_CAP.').');

            return implode("\n", $parts);
        } finally {
            foreach (glob($dir.'/p-*.jpg') ?: [] as $img) {
                @unlink($img);
            }
            @rmdir($dir);
        }
    }

    /** Drop a confidence one notch (OCR noise marking). low is the floor. */
    private function demote(string $confidence): string
    {
        return match ($confidence) {
            'high'   => 'medium',
            'medium' => 'low',
            default  => 'low',
        };
    }

    // --- detectors ---------------------------------------------------------

    /** Deterministic, explainable, no LLM. */
    private function deterministic(string $text): array
    {
        $out = [];

        // SA ID number: 13-digit runs that pass embedded-date + Luhn.
        if (preg_match_all('/\b\d{13}\b/', $text, $m)) {
            foreach (array_unique($m[0]) as $cand) {
                if ($this->isValidSaId($cand)) {
                    $out[] = $this->finding('sa_id_number', 'personal', $this->mask($cand), 'high', 'deterministic');
                }
            }
        }
        // Email
        if (preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $text, $m)) {
            foreach (array_unique($m[0]) as $e) {
                $out[] = $this->finding('email', 'personal', $this->maskEmail($e), 'high', 'deterministic');
            }
        }
        // SA phone (+27XXXXXXXXX or 0XXXXXXXXX)
        if (preg_match_all('/\b(?:\+27|0)\d{9}\b/', $text, $m)) {
            foreach (array_unique($m[0]) as $p) {
                $out[] = $this->finding('phone', 'personal', $this->mask($p), 'high', 'deterministic');
            }
        }
        // Passport-style: a letter + 8 digits (illustrative; medium confidence).
        if (preg_match_all('/\b[A-Z]\d{8}\b/', $text, $m)) {
            foreach (array_unique($m[0]) as $p) {
                $out[] = $this->finding('passport', 'personal', $this->mask($p), 'medium', 'deterministic');
            }
        }

        return $out;
    }

    private function specialCategory(string $text): array
    {
        $lc = mb_strtolower($text);
        $out = [];
        foreach (self::SPECIAL_TERMS as $term) {
            if (str_contains($lc, $term)) {
                $out[] = $this->finding('special_category', 'special_category', $term, 'medium', 'lexicon');
            }
        }

        return $out;
    }

    /** NER augmentation - AI-suggested, best-effort, gateway-routed. */
    private function ner(string $text): array
    {
        $out = [];
        try {
            $r = app(NerService::class)->extract(mb_substr($text, 0, self::NER_TEXT_CAP), null);
            foreach (array_unique($r['persons'] ?? []) as $v) {
                $out[] = $this->finding('person', 'personal', (string) $v, 'medium', 'ner');
            }
            foreach (array_unique($r['places'] ?? []) as $v) {
                $out[] = $this->finding('location', 'personal', (string) $v, 'low', 'ner');
            }
            foreach (array_unique($r['organizations'] ?? []) as $v) {
                $out[] = $this->finding('org', 'personal', (string) $v, 'low', 'ner');
            }
        } catch (QuotaExceededException $e) {
            Log::info('[PopiaScan] NER quota exceeded; deterministic findings stand alone.');
        } catch (\Throwable $e) {
            Log::info('[PopiaScan] NER unavailable: '.$e->getMessage());
        }

        return $out;
    }

    // --- helpers -----------------------------------------------------------

    private function finding(string $type, string $category, string $sample, string $confidence, string $method): array
    {
        return compact('type', 'category', 'sample', 'confidence', 'method');
    }

    /**
     * Valid SA ID: 13 digits, valid embedded YYMMDD date, Luhn checksum 0.
     */
    private function isValidSaId(string $s): bool
    {
        if (! preg_match('/^\d{13}$/', $s)) {
            return false;
        }
        $mm = (int) substr($s, 2, 2);
        $dd = (int) substr($s, 4, 2);
        if ($mm < 1 || $mm > 12 || $dd < 1 || $dd > 31) {
            return false;
        }
        $sum = 0;
        $alt = false;
        for ($i = 12; $i >= 0; $i--) {
            $n = (int) $s[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }

    /** Mask a structured identifier: keep first 4 + last 2, redact the middle. */
    private function mask(string $v): string
    {
        $len = strlen($v);
        if ($len <= 6) {
            return str_repeat('*', max(0, $len - 1)).substr($v, -1);
        }

        return substr($v, 0, 4).str_repeat('*', $len - 6).substr($v, -2);
    }

    private function maskEmail(string $e): string
    {
        $at = strpos($e, '@');
        if ($at === false) {
            return $this->mask($e);
        }

        return substr($e, 0, 1).'***'.substr($e, $at);
    }
}
