<?php

/**
 * VisionOcrService - OCR by a vision model that reads the page image
 * (heratio#1522).
 *
 * Proven on the Brenthurst Library cuttings (October 2026): reading the image
 * with qwen2.5vl beat Tesseract alone (89.1% real words) and Tesseract plus LLM
 * post-correction (94.0%) at 95.0%, without the post-corrector's fluent
 * rewrites. Tesseract is kept as an independent cross-check, never as the
 * text.
 *
 *   1. The page is cut into strips of about 1,200 px at the whitest row near
 *      each cut, so no line of text is split.
 *   2. Each strip is read through the AI gateway (Ollama chat, temperature 0)
 *      with a prompt that forbids correcting, modernising or completing words.
 *   3. Each reading is checked against Tesseract's words in the same strip.
 *      Much shorter, repeating itself, sharing under half of Tesseract's clean
 *      long words, or dropping a line Tesseract found: the strip is re-read
 *      with the fallback model. A strip that fails both is kept and flagged.
 *   4. Text the model adds that is not on the page (a bare role label, remarks
 *      such as "[Top of image is cut off]") is removed.
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

use AhgAiServices\Support\AiServicesSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VisionOcrService
{
    public const DEFAULT_URL = 'https://ai.theahg.co.za/ai/v1/ollama/api/chat';

    public const DEFAULT_MODEL = 'qwen2.5vl:7b-ctx16k';

    public const DEFAULT_FALLBACK_MODEL = 'qwen3.6:27b';

    private const STRIP_HEIGHT = 1200;

    private const PROMPT = <<<'TXT'
Transcribe all the text in this image exactly as printed, line by line.
Rules:
- Copy every word as it appears, including spelling that looks old, foreign or wrong. Do not correct, modernise or standardise anything.
- Do not complete words that are cut off at the edge of the image; copy only the visible letters.
- Keep the original line breaks. Do not add headings, labels, commentary, notes or explanations.
- If there is no text, reply with nothing.
TXT;

    /** @var callable|null fn(string $model, string $pngBase64): ?string, for tests */
    private $reader;

    public function __construct(?callable $reader = null)
    {
        $this->reader = $reader;
    }

    /**
     * Read one page image.
     *
     * @param  list<array{text: string, x: int, y: int, width: int, height: int}>  $tesseractWords  Tesseract's words for the same image (positions in pixels)
     * @return array{success: bool, text: string, model: string, strips: list<array{y0: int, y1: int, model: string, flagged: bool, reasons: list<string>}>, error: ?string}
     */
    public function read(string $imagePath, array $tesseractWords = []): array
    {
        $model = $this->setting('ocr_vision_model', self::DEFAULT_MODEL);
        $fallback = $this->setting('ocr_vision_fallback_model', self::DEFAULT_FALLBACK_MODEL);
        $out = ['success' => false, 'text' => '', 'model' => $model, 'strips' => [], 'error' => null];

        try {
            $strips = $this->strips($imagePath);
        } catch (\Throwable $e) {
            $out['error'] = 'image_unreadable: '.$e->getMessage();

            return $out;
        }

        $texts = [];
        foreach ($strips as [$y0, $y1, $png]) {
            $tess = $this->tesseractText($tesseractWords, $y0, $y1);
            $text = $this->clean((string) $this->ask($model, $png));
            $reasons = $this->problems($text, $tess);
            $used = $model;
            if ($reasons) {
                $second = $this->clean((string) $this->ask($fallback, $png));
                $secondReasons = $this->problems($second, $tess);
                if (! $secondReasons) {
                    [$text, $reasons, $used] = [$second, [], $fallback];
                } elseif (mb_strlen($second) > mb_strlen($text)) {
                    [$text, $used] = [$second, $fallback];
                }
            }
            $texts[] = $text;
            $out['strips'][] = ['y0' => $y0, 'y1' => $y1, 'model' => $used, 'flagged' => (bool) $reasons, 'reasons' => $reasons];
        }

        $out['text'] = trim(implode("\n", array_filter($texts, fn ($t) => $t !== '')));
        $out['success'] = $out['text'] !== '' || $tesseractWords === [];

        return $out;
    }

    /** True when the gateway route and a key are configured. */
    public function available(): bool
    {
        return $this->reader !== null || (string) AiServicesSettings::gatewayKey() !== '';
    }

    /**
     * Why a reading looks wrong against Tesseract's text for the same strip;
     * empty when it passes.
     *
     * @return list<string>
     */
    public function problems(string $vision, string $tesseract): array
    {
        $reasons = [];
        $tessLen = mb_strlen(trim($tesseract));
        if ($tessLen > 40 && mb_strlen(trim($vision)) < $tessLen * 0.5) {
            $reasons[] = 'much_shorter';
        }
        $lines = array_count_values(array_filter(array_map('trim', explode("\n", $vision)), fn ($l) => mb_strlen($l) > 10));
        if ($lines && max($lines) >= 3) {
            $reasons[] = 'repeats_itself';
        }

        $visionWords = array_flip(self::words($vision));
        $long = array_values(array_unique(array_filter(self::words($tesseract), fn ($w) => mb_strlen($w) >= 6)));
        if (count($long) >= 5) {
            $shared = count(array_filter($long, fn ($w) => isset($visionWords[$w])));
            if ($shared / count($long) < 0.5) {
                $reasons[] = 'shares_few_words';
            }
        }
        foreach (explode("\n", $tesseract) as $line) {
            $real = array_filter(self::words($line), fn ($w) => mb_strlen($w) >= 3);
            if (count($real) >= 3 && count(array_filter($real, fn ($w) => isset($visionWords[$w]))) < count($real) / 2) {
                $reasons[] = 'dropped_line';
                break;
            }
        }

        return $reasons;
    }

    /** Remove what the model adds that is not on the page. */
    public function clean(string $text): string
    {
        $text = (string) preg_replace('/^\s*(system|assistant|user)\s*$/mi', '', $text);
        // Bracketed remarks about the image, not bracketed text on the page
        // (a byline such as "[By Telegraph.-From Our Special Correspondent.]" stays).
        $text = (string) preg_replace('/\[[^\]\n]*\b(image|cut off|cropped|illegible|unclear|blurr(y|ed)|no text|not visible|text continues|top of|bottom of)\b[^\]\n]*\]/i', '', $text);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /** @return list<string> lower-case alphabetic words */
    private static function words(string $text): array
    {
        preg_match_all('/\p{L}+/u', mb_strtolower($text), $m);

        return $m[0];
    }

    /** Tesseract's words inside a strip, as lines (words sharing a row). */
    private function tesseractText(array $words, int $y0, int $y1): string
    {
        $rows = [];
        foreach ($words as $w) {
            $cy = (int) ($w['y'] ?? 0) + intdiv((int) ($w['height'] ?? 0), 2);
            if ($cy >= $y0 && $cy < $y1 && trim((string) ($w['text'] ?? '')) !== '') {
                $rows[intdiv($cy, 12)][(int) ($w['x'] ?? 0)] = (string) $w['text'];
            }
        }
        ksort($rows);

        return implode("\n", array_map(function ($r) {
            ksort($r);

            return implode(' ', $r);
        }, $rows));
    }

    /**
     * Cut the page into strips at the whitest row near every STRIP_HEIGHT.
     *
     * @return list<array{0: int, 1: int, 2: string}> [y0, y1, base64 PNG]
     */
    private function strips(string $path): array
    {
        $img = new \Imagick($path);
        $img->setIteratorIndex(0);
        $w = $img->getImageWidth();
        $h = $img->getImageHeight();

        // Row brightness on a narrow copy is enough to find the gaps between lines.
        $probe = clone $img;
        $probe->transformImageColorspace(\Imagick::COLORSPACE_GRAY);
        $probe->resizeImage(min(400, $w), $h, \Imagick::FILTER_POINT, 1);
        $pw = $probe->getImageWidth();

        $cuts = [0];
        while ($h - end($cuts) > (int) (self::STRIP_HEIGHT * 1.2)) {
            $from = end($cuts) + (int) (self::STRIP_HEIGHT * 0.8);
            $to = min($h - 1, end($cuts) + (int) (self::STRIP_HEIGHT * 1.2));
            $best = $from;
            $bestValue = -1.0;
            for ($y = $from; $y <= $to; $y += 2) {
                $row = $probe->exportImagePixels(0, $y, $pw, 1, 'I', \Imagick::PIXEL_CHAR);
                $v = array_sum($row) / max(1, count($row));
                if ($v > $bestValue) {
                    [$best, $bestValue] = [$y, $v];
                }
            }
            $cuts[] = $best;
        }
        $cuts[] = $h;

        $out = [];
        for ($i = 0; $i < count($cuts) - 1; $i++) {
            $strip = clone $img;
            $strip->cropImage($w, $cuts[$i + 1] - $cuts[$i], 0, $cuts[$i]);
            $strip->setImagePage(0, 0, 0, 0);
            $strip->setImageFormat('png');
            $out[] = [$cuts[$i], $cuts[$i + 1], base64_encode($strip->getImageBlob())];
        }

        return $out;
    }

    private function ask(string $model, string $pngBase64): ?string
    {
        if ($this->reader !== null) {
            return ($this->reader)($model, $pngBase64);
        }
        try {
            $resp = Http::withToken((string) AiServicesSettings::gatewayKey())
                ->timeout((int) $this->setting('ocr_vision_timeout', '300'))
                ->post($this->setting('ocr_vision_url', self::DEFAULT_URL), [
                    'model' => $model,
                    'stream' => false,
                    'options' => ['temperature' => 0],
                    'messages' => [['role' => 'user', 'content' => self::PROMPT, 'images' => [$pngBase64]]],
                ]);
            if ($resp->successful()) {
                return (string) $resp->json('message.content', '');
            }
            Log::warning('[ahg-ai] vision OCR gateway call failed', ['status' => $resp->status(), 'model' => $model]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] vision OCR gateway call threw: '.$e->getMessage());
        }

        return null;
    }

    private function setting(string $key, string $default): string
    {
        try {
            $v = DB::table('ahg_ai_settings')->where('feature', 'ocr')->where('setting_key', $key)->value('setting_value');

            return is_string($v) && $v !== '' ? $v : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
