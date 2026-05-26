<?php

/**
 * NullFixityTool - fallback FixityToolInterface for dev hosts and CI runs
 * where Siegfried / ClamAV are not installed. Always claims "unknown
 * format" and "clean" so the wrapper service can complete without blowing
 * up. Operator should install real tooling for production.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license AGPL-3.0-or-later
 */

namespace AhgPreservation\Tools;

class NullFixityTool implements FixityToolInterface
{
    public function name(): string
    {
        return 'null';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function identify(string $path): array
    {
        $mime = 'application/octet-stream';
        if (function_exists('mime_content_type') && is_file($path)) {
            $detected = @mime_content_type($path);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }
        return [
            'format_id'      => 'unknown',
            'format_name'    => 'unknown',
            'format_version' => null,
            'format_pronom'  => null,
            'mime_type'      => $mime,
        ];
    }

    public function scan(string $path): array
    {
        return [
            'clean'           => true,
            'threats'         => [],
            'scanner_version' => 'null (no-op)',
        ];
    }
}
