<?php

/**
 * FixityToolInterface - format-identification + malware-scan abstraction.
 *
 * Phase 1 of issue #653. Implementations wrap external binaries (Siegfried,
 * ClamAV, JHOVE, ...) and present a stable shape that FixityScanService
 * consumes regardless of which binary is actually installed.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license AGPL-3.0-or-later
 */

namespace AhgPreservation\Tools;

interface FixityToolInterface
{
    /**
     * PRONOM-aware format identification.
     *
     * @return array{
     *   format_id: string,
     *   format_name: string,
     *   format_version: ?string,
     *   format_pronom: ?string,
     *   mime_type: string
     * }
     */
    public function identify(string $path): array;

    /**
     * Malware / virus scan.
     *
     * @return array{
     *   clean: bool,
     *   threats: array<int, string>,
     *   scanner_version: string
     * }
     */
    public function scan(string $path): array;

    /**
     * Short human-readable tool name (e.g. "siegfried", "clamav", "null").
     */
    public function name(): string;

    /**
     * Is the underlying binary present on this host?
     */
    public function isAvailable(): bool;
}
