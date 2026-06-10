<?php

/**
 * StorySourceService - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * heratio#1202 - extra grounding sources for the storytelling engine. Turns curator-supplied
 * material (a pasted note, a fetched web page, an uploaded document) into plain text the AI can
 * weave in, plus a structured attribution list so a published story can show where its
 * non-catalogue content came from. External fetches are size/timeout-bounded and SSRF-guarded.
 */
class StorySourceService
{
    /** Hard cap on assembled extra context handed to the LLM (keeps the prompt bounded). */
    public const MAX_CONTEXT = 8000;

    /** Per-source text cap before assembly. */
    public const MAX_SOURCE = 6000;

    /** Max bytes we will download from a source URL. */
    public const MAX_FETCH_BYTES = 600000;

    /**
     * Fetch a web page and reduce it to readable text. SSRF-guarded: http/https only, public
     * hosts only (no loopback/private/link-local/reserved IPs), bounded time and size.
     *
     * @return array{ok:bool, text:string, title:string, error:string}
     */
    public function fetchUrlText(string $url): array
    {
        $out = ['ok' => false, 'text' => '', 'title' => '', 'error' => ''];
        $url = trim($url);
        if ($url === '') {
            return $out;
        }
        $parts = parse_url($url);
        if (! $parts || empty($parts['scheme']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true) || empty($parts['host'])) {
            $out['error'] = 'Only http(s) URLs are allowed.';

            return $out;
        }
        if (! $this->hostIsPublic($parts['host'])) {
            $out['error'] = 'That address is not allowed.';

            return $out;
        }

        try {
            $ctx = stream_context_create(['http' => [
                'method' => 'GET', 'timeout' => 8, 'follow_location' => 0,
                'header' => "User-Agent: HeratioStoryBot/1.0\r\nAccept: text/html,text/plain\r\n",
            ]]);
            $fh = @fopen($url, 'rb', false, $ctx);
            if (! $fh) {
                $out['error'] = 'Could not reach that URL.';

                return $out;
            }
            $raw = (string) stream_get_contents($fh, self::MAX_FETCH_BYTES);
            fclose($fh);
        } catch (\Throwable $e) {
            Log::info('[ahg-core] story url fetch failed: '.$e->getMessage());
            $out['error'] = 'Could not read that URL.';

            return $out;
        }

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $raw, $m)) {
            $out['title'] = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $out['text'] = $this->htmlToText($raw);
        $out['ok'] = $out['text'] !== '';
        if (! $out['ok']) {
            $out['error'] = 'No readable text found at that URL.';
        }

        return $out;
    }

    /**
     * Extract text from an uploaded document. text/* is read directly, PDF via the pdf-tools
     * extractor, images via the HTR gateway service - both resolved softly so ahg-core keeps no
     * hard dependency on those packages.
     *
     * @return array{ok:bool, text:string, error:string}
     */
    public function extractUploadText(UploadedFile $file): array
    {
        $out = ['ok' => false, 'text' => '', 'error' => ''];
        $mime = (string) $file->getMimeType();
        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            $out['error'] = 'Could not read the uploaded file.';

            return $out;
        }

        try {
            if (str_starts_with($mime, 'text/')) {
                $out['text'] = $this->clip((string) file_get_contents($path));
            } elseif ($mime === 'application/pdf') {
                $svc = class_exists(\AhgPdfTools\Services\PdfTextExtractService::class)
                    ? app(\AhgPdfTools\Services\PdfTextExtractService::class) : null;
                $out['text'] = $svc ? $this->clip((string) $svc->extractText($path)) : '';
                if ($out['text'] === '') {
                    $out['error'] = 'No selectable text in that PDF (it may be scanned images).';
                }
            } elseif (str_starts_with($mime, 'image/')) {
                $htr = class_exists(\AhgAiServices\Services\HtrService::class)
                    ? app(\AhgAiServices\Services\HtrService::class) : null;
                if ($htr) {
                    $res = $htr->extract($path);
                    $text = is_array($res) ? ($res['text'] ?? $res['transcription'] ?? '') : (string) $res;
                    $out['text'] = $this->clip((string) $text);
                }
                if ($out['text'] === '') {
                    $out['error'] = 'Could not read any text from that image.';
                }
            } else {
                $out['error'] = 'Unsupported file type.';

                return $out;
            }
        } catch (\Throwable $e) {
            Log::info('[ahg-core] story upload extract failed: '.$e->getMessage());
            $out['error'] = 'Could not extract text from that file.';

            return $out;
        }

        $out['ok'] = $out['text'] !== '';

        return $out;
    }

    /** Assemble the per-source texts into one bounded context block for the prompt. */
    public function assembleContext(array $pieces): string
    {
        $joined = trim(implode("\n\n", array_filter(array_map('trim', $pieces))));

        return mb_substr($joined, 0, self::MAX_CONTEXT);
    }

    /** Resolve a host to one or more IPs and reject anything not globally routable. */
    private function hostIsPublic(string $host): bool
    {
        // Literal IPs are checked directly; names are resolved (all records).
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_filter(array_map(
            fn ($r) => $r['ip'] ?? ($r['ipv6'] ?? null),
            @dns_get_record($host, DNS_A | DNS_AAAA) ?: []
        ));
        if (! $ips) {
            // Fall back to gethostbyname (IPv4); if even that fails, refuse.
            $resolved = gethostbyname($host);
            $ips = ($resolved && $resolved !== $host) ? [$resolved] : [];
        }
        if (! $ips) {
            return false;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;   // private / reserved / loopback / link-local
            }
        }

        return true;
    }

    /** Strip a HTML document to readable text (drops script/style/nav noise). */
    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<(script|style|noscript|template|svg|head)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\n\s*/', "\n", $text) ?? $text;

        return $this->clip(trim($text));
    }

    private function clip(string $text): string
    {
        return mb_substr(trim($text), 0, self::MAX_SOURCE);
    }
}
