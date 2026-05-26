<?php

/**
 * ClamAVTool - malware scan via `clamscan` (https://www.clamav.net).
 *
 * Exit codes (clamscan(1)):
 *   0 - clean
 *   1 - virus found
 *   2+ - error (handled as scanner failure, not "infected")
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license AGPL-3.0-or-later
 */

namespace AhgPreservation\Tools;

class ClamAVTool implements FixityToolInterface
{
    public function __construct(protected string $binary = 'clamscan')
    {
    }

    public function name(): string
    {
        return 'clamav';
    }

    public function isAvailable(): bool
    {
        $cmd = escapeshellcmd($this->binary) . ' --version 2>/dev/null';
        $out = @shell_exec($cmd);
        return is_string($out) && stripos($out, 'ClamAV') !== false;
    }

    public function identify(string $path): array
    {
        // ClamAV is malware-scan-only; no PRONOM identification.
        return [
            'format_id'      => 'unknown',
            'format_name'    => 'unknown',
            'format_version' => null,
            'format_pronom'  => null,
            'mime_type'      => function_exists('mime_content_type') && is_file($path)
                ? (string) (@mime_content_type($path) ?: 'application/octet-stream')
                : 'application/octet-stream',
        ];
    }

    public function scan(string $path): array
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('clamscan binary not found at ' . $this->binary);
        }
        if (! is_file($path)) {
            throw new \RuntimeException('file not found: ' . $path);
        }

        $cmd = sprintf('%s -i --no-summary %s 2>&1', escapeshellcmd($this->binary), escapeshellarg($path));
        $output = [];
        $exit   = 0;
        @exec($cmd, $output, $exit);

        $threats = [];
        foreach ($output as $line) {
            // clamscan -i format:  /path/to/file: Malware.Name FOUND
            if (preg_match('/:\s+(.+?)\s+FOUND$/', $line, $m)) {
                $threats[] = $m[1];
            }
        }

        $clean = $exit === 0;
        if ($exit > 1) {
            throw new \RuntimeException(sprintf('clamscan failed (exit %d): %s', $exit, implode("\n", $output)));
        }

        return [
            'clean'           => $clean,
            'threats'         => $threats,
            'scanner_version' => $this->versionString(),
        ];
    }

    protected function versionString(): string
    {
        $cmd = escapeshellcmd($this->binary) . ' --version 2>/dev/null';
        $out = @shell_exec($cmd);
        return is_string($out) ? trim($out) : 'clamav';
    }
}
