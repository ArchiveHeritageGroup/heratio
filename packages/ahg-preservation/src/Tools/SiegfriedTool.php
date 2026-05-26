<?php

/**
 * SiegfriedTool - PRONOM-aware identification via Richard Lehane's
 * Siegfried (https://www.itforarchivists.com/siegfried/).
 *
 * The Siegfried `sf -json` invocation produces a JSON object with one
 * `files` entry per path; each entry contains a `matches` array. We take
 * the first non-extension-only match and project it onto FixityToolInterface.
 *
 * Siegfried is the optional operator-installed dependency that replaces
 * the placeholder PronomIdentificationService PRONOM lookup until the
 * Phase 2+ real signature sync ships.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license AGPL-3.0-or-later
 */

namespace AhgPreservation\Tools;

class SiegfriedTool implements FixityToolInterface
{
    public function __construct(protected string $binary = 'sf')
    {
    }

    public function name(): string
    {
        return 'siegfried';
    }

    public function isAvailable(): bool
    {
        $cmd = escapeshellcmd($this->binary) . ' -version 2>/dev/null';
        $out = @shell_exec($cmd);
        return is_string($out) && str_contains($out, 'siegfried');
    }

    public function identify(string $path): array
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('siegfried binary not found at ' . $this->binary);
        }
        if (! is_file($path)) {
            throw new \RuntimeException('file not found: ' . $path);
        }

        $cmd = sprintf('%s -json %s 2>/dev/null', escapeshellcmd($this->binary), escapeshellarg($path));
        $raw = @shell_exec($cmd);
        if (! is_string($raw) || trim($raw) === '') {
            throw new \RuntimeException('siegfried produced no output for ' . $path);
        }
        $json = json_decode($raw, true);
        if (! is_array($json) || empty($json['files'])) {
            throw new \RuntimeException('siegfried output was not valid JSON for ' . $path);
        }

        $entry = $json['files'][0];
        $matches = $entry['matches'] ?? [];
        $match   = $this->pickBestMatch($matches);

        return [
            'format_id'      => (string) ($match['id']      ?? 'unknown'),
            'format_name'    => (string) ($match['format']  ?? ($match['mime'] ?? 'unknown')),
            'format_version' => isset($match['version']) && $match['version'] !== '' ? (string) $match['version'] : null,
            'format_pronom'  => $this->extractPronom($match),
            'mime_type'      => (string) ($match['mime']    ?? ($entry['mime'] ?? 'application/octet-stream')),
        ];
    }

    public function scan(string $path): array
    {
        // Siegfried is identification-only.
        return [
            'clean'           => true,
            'threats'         => [],
            'scanner_version' => 'siegfried (identify-only)',
        ];
    }

    protected function pickBestMatch(array $matches): array
    {
        if (empty($matches)) {
            return [];
        }
        foreach ($matches as $m) {
            if (! empty($m['id']) && stripos((string) ($m['basis'] ?? ''), 'extension match') === false) {
                return $m;
            }
        }
        return $matches[0];
    }

    protected function extractPronom(array $match): ?string
    {
        $id  = (string) ($match['id'] ?? '');
        $ns  = (string) ($match['ns'] ?? '');
        if ($ns === 'pronom' && $id !== '' && $id !== 'UNKNOWN') {
            return $id;
        }
        // Some sf builds put the PUID in a dedicated field.
        if (! empty($match['puid'])) {
            return (string) $match['puid'];
        }
        if ($id !== '' && preg_match('/^(fmt|x-fmt)\/\d+$/i', $id)) {
            return $id;
        }
        return null;
    }
}
