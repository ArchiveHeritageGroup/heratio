<?php

namespace AhgCore\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TTS (Text-to-Speech) API controller.
 *
 * Ported from AtoM ahgCorePlugin/modules/tts/actions/actions.class.php
 * Provides settings retrieval and PDF text extraction for the TTS widget.
 */
class TtsController extends Controller
{
    /**
     * Get TTS settings for a specific sector.
     * GET /tts/settings?sector=archive
     *
     * Returns JSON with enabled state, rate, field configuration, etc.
     */
    public function settings(Request $request)
    {
        $sector = $request->query('sector', 'archive');

        // Get general settings
        $settings = [];
        try {
            $rows = DB::table('ahg_tts_settings')->where('sector', 'all')->get();
            foreach ($rows as $row) {
                $settings[$row->setting_key] = $row->setting_value;
            }
        } catch (\Exception $e) {
            // Table may not exist yet; return sensible defaults
            $settings = [];
        }

        // Get sector-specific fields
        $fieldsToRead = [];
        try {
            $fieldsRow = DB::table('ahg_tts_settings')
                ->where('setting_key', 'fields_to_read')
                ->where('sector', $sector)
                ->first();
            $fieldsToRead = $fieldsRow ? json_decode($fieldsRow->setting_value, true) : [];
        } catch (\Exception $e) {
            // Ignore if table doesn't exist
        }

        return response()->json([
            'enabled' => ($settings['enabled'] ?? '1') === '1',
            'rate' => floatval($settings['default_rate'] ?? 1.0),
            'readLabels' => ($settings['read_labels'] ?? '1') === '1',
            'keyboardShortcuts' => ($settings['keyboard_shortcuts'] ?? '1') === '1',
            'fieldsToRead' => $fieldsToRead ?: [],
            'sector' => $sector,
        ]);
    }

    /**
     * Extract text from a PDF digital object for TTS playback.
     * GET /tts/pdfText?id=<digital_object_id>
     *
     * Returns JSON with extracted text, page count, character count.
     */
    public function pdfText(Request $request)
    {
        $objectId = (int) $request->query('id');

        if (!$objectId) {
            return response()->json(['success' => false, 'error' => 'Missing object ID']);
        }

        // Get the digital object
        $digitalObject = DB::table('digital_object')->where('id', $objectId)->first();

        if (!$digitalObject) {
            return response()->json(['success' => false, 'error' => 'Digital object not found']);
        }

        // Check if it's a PDF
        $mimeType = $digitalObject->mime_type ?? '';
        if ($mimeType !== 'application/pdf') {
            return response()->json(['success' => false, 'error' => 'Not a PDF file']);
        }

        // Resolve the file path
        $filePath = $this->resolveDigitalObjectPath($digitalObject);

        if (!$filePath || !file_exists($filePath)) {
            return response()->json(['success' => false, 'error' => 'PDF file not found on disk']);
        }

        // Extract text using pdftotext
        $maxPages = (int) $request->query('max_pages', 50);
        $text = $this->extractPdfText($filePath, $maxPages);

        if ($text === false) {
            return response()->json(['success' => false, 'error' => 'Failed to extract text from PDF']);
        }

        // Clean the text
        $text = $this->cleanText($text);

        // Check if text is meaningful
        if (strlen($text) < 50) {
            return response()->json([
                'success' => false,
                'error' => 'This PDF has no readable text. It may be a scanned image that has not been OCR processed.',
                'chars' => strlen($text),
            ]);
        }

        return response()->json([
            'success' => true,
            'text' => $text,
            'pages' => $this->countPages($filePath),
            'chars' => strlen($text),
            'redacted' => false,
        ]);
    }

    /**
     * Resolve the full file path for a digital object.
     */
    private function resolveDigitalObjectPath(object $do): ?string
    {
        $relative = ($do->path ?? '') . ($do->name ?? '');

        $candidates = [
            public_path($relative),
            '/mnt/nas/heratio/archive/' . $relative,
            '/usr/share/nginx/archive/' . $relative,
            '/usr/share/nginx/archive' . $relative,
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Extract text from PDF using pdftotext.
     */
    private function extractPdfText(string $filePath, int $maxPages = 50): string|false
    {
        $pdftotext = trim(shell_exec('which pdftotext 2>/dev/null') ?? '');
        if (empty($pdftotext)) {
            return false;
        }

        $escapedPath = escapeshellarg($filePath);
        $cmd = sprintf('%s -l %d -enc UTF-8 -nopgbrk %s -', $pdftotext, $maxPages, $escapedPath);
        $output = shell_exec($cmd . ' 2>/dev/null');

        return $output !== null ? $output : false;
    }

    /**
     * Count pages in PDF.
     */
    private function countPages(string $filePath): ?int
    {
        $pdfinfo = trim(shell_exec('which pdfinfo 2>/dev/null') ?? '');
        if (empty($pdfinfo)) {
            return null;
        }

        $escapedPath = escapeshellarg($filePath);
        $output = shell_exec(sprintf('%s %s 2>/dev/null', $pdfinfo, $escapedPath));

        if ($output && preg_match('/Pages:\s*(\d+)/', $output, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Clean extracted text for TTS.
     */
    private function cleanText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        // Remove control characters except newlines
        $text = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        // Replace redacted content with spoken word
        $text = $this->removeRedactions($text);
        return trim($text);
    }

    /**
     * Replace redacted/masked content with spoken word "redacted" for accessibility.
     */
    private function removeRedactions(string $text): string
    {
        $patterns = [
            '/\[\w+\s+REDACTED\]/i',
            '/\[REDACTED\]/i',
            '/\[REMOVED\]/i',
            '/\[PII REMOVED\]/i',
            '/\[PII REDACTED\]/i',
            '/\x{2588}+/u',          // Block characters
            '/\*{3,}/',              // Multiple asterisks
            '/X{3,}/',              // Multiple X's
            '/\[\.{3,}\]/',         // [...]
            '/<redacted>.*?<\/redacted>/is',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, ' redacted ', $text);
        }

        $text = preg_replace('/(\s*redacted\s*)+/', ' redacted ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
