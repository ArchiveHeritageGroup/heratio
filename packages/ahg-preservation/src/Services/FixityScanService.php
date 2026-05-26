<?php

/**
 * FixityScanService - orchestrates FixityToolInterface implementations
 * across the digital objects of an IO.
 *
 * For each digital object:
 *   - call identify() on the format-aware tool, write a
 *     `format_identification` preservation_event
 *   - call scan() on the malware-aware tool, write a `virus_check`
 *     preservation_event (and a row in preservation_virus_scan when
 *     the table is available)
 *
 * Selection: callers pass an array of tools. The first tool whose
 * identify() does NOT return format_id='unknown' wins for identification;
 * the first tool whose scan() reports a real scanner_version (not the
 * NullFixityTool no-op) wins for scanning. This keeps the wrapper happy
 * whether the operator installs Siegfried only, ClamAV only, both, or
 * neither.
 *
 * Issue #653 Phase 1.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license AGPL-3.0-or-later
 */

namespace AhgPreservation\Services;

use AhgPreservation\Tools\FixityToolInterface;
use AhgPreservation\Tools\NullFixityTool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FixityScanService
{
    /** @var FixityToolInterface[] */
    protected array $tools;

    public function __construct(
        protected PreservationService $preservation,
        array $tools = []
    ) {
        // Always ensure at least one tool is present so identify()/scan()
        // never blow up against a clean dev install.
        $this->tools = $tools;
        if (empty($this->tools)) {
            $this->tools = [new NullFixityTool()];
        }
    }

    /**
     * Run identify + scan across every digital_object of an IO.
     *
     * @return array{
     *   information_object_id: int,
     *   objects_scanned: int,
     *   identified: int,
     *   clean: int,
     *   infected: int,
     *   errors: int,
     *   results: array<int, array>
     * }
     */
    public function scanIo(int $ioId): array
    {
        $summary = [
            'information_object_id' => $ioId,
            'objects_scanned' => 0,
            'identified'      => 0,
            'clean'           => 0,
            'infected'        => 0,
            'errors'          => 0,
            'results'         => [],
        ];

        if (! Schema::hasTable('digital_object')) {
            return $summary;
        }

        $digitalObjects = DB::table('digital_object')->where('object_id', $ioId)->get();
        $uploadsBase    = config('heratio.uploads_path');

        foreach ($digitalObjects as $do) {
            $relPath  = (string) ($do->path ?? '');
            $fullPath = $uploadsBase
                ? rtrim((string) $uploadsBase, '/') . '/' . ltrim($relPath, '/')
                : $relPath;

            $row = [
                'digital_object_id' => (int) $do->id,
                'path'              => $fullPath,
                'identify'          => null,
                'scan'              => null,
                'error'             => null,
            ];

            if (! is_file($fullPath)) {
                $row['error'] = 'file_missing';
                $summary['errors']++;
                $summary['results'][] = $row;
                $this->logFailure((int) $do->id, $ioId, 'file_missing', 'File not found on disk: ' . $fullPath);
                continue;
            }

            try {
                $row['identify'] = $this->runIdentify($fullPath);
                if (($row['identify']['format_id'] ?? 'unknown') !== 'unknown') {
                    $summary['identified']++;
                }
                $this->writeIdentifyEvent((int) $do->id, $ioId, $row['identify']);
            } catch (Throwable $e) {
                $row['error'] = 'identify:' . $e->getMessage();
                $summary['errors']++;
                $this->logFailure((int) $do->id, $ioId, 'format_identification', $e->getMessage());
            }

            try {
                $row['scan'] = $this->runScan($fullPath);
                if (! empty($row['scan']['clean'])) {
                    $summary['clean']++;
                } else {
                    $summary['infected']++;
                }
                $this->writeScanEvent((int) $do->id, $ioId, $row['scan']);
            } catch (Throwable $e) {
                $row['error'] = trim(($row['error'] ?? '') . ' scan:' . $e->getMessage());
                $summary['errors']++;
                $this->logFailure((int) $do->id, $ioId, 'virus_check', $e->getMessage());
            }

            $summary['objects_scanned']++;
            $summary['results'][] = $row;
        }

        return $summary;
    }

    /**
     * Iterate over IOs that don't have a fixity_check event in the last $days.
     *
     * @return array<int> List of information_object_id values.
     */
    public function staleIos(int $days = 90, int $limit = 100): array
    {
        if (! Schema::hasTable('digital_object') || ! Schema::hasTable('preservation_event')) {
            return [];
        }
        $cutoff = now()->subDays($days)->format('Y-m-d H:i:s');

        $sub = DB::table('preservation_event')
            ->select('information_object_id')
            ->where('event_type', 'fixity_check')
            ->where('event_datetime', '>=', $cutoff)
            ->whereNotNull('information_object_id');

        return DB::table('digital_object')
            ->select('object_id')
            ->whereNotIn('object_id', $sub)
            ->whereNotNull('object_id')
            ->groupBy('object_id')
            ->orderBy('object_id')
            ->limit($limit)
            ->pluck('object_id')
            ->all();
    }

    protected function runIdentify(string $path): array
    {
        $last = null;
        foreach ($this->tools as $tool) {
            try {
                if (! $tool->isAvailable()) {
                    continue;
                }
                $result = $tool->identify($path);
                $last = $result;
                if (($result['format_id'] ?? 'unknown') !== 'unknown') {
                    return $result;
                }
            } catch (Throwable $e) {
                Log::debug('fixity identify failed', ['tool' => $tool->name(), 'error' => $e->getMessage()]);
            }
        }
        // Fall back to the last attempt (or a null-tool result).
        return $last ?? (new NullFixityTool())->identify($path);
    }

    protected function runScan(string $path): array
    {
        $last = null;
        foreach ($this->tools as $tool) {
            try {
                if (! $tool->isAvailable()) {
                    continue;
                }
                $result = $tool->scan($path);
                $last = $result;
                if (! str_contains((string) ($result['scanner_version'] ?? ''), 'identify-only')
                    && ! str_contains((string) ($result['scanner_version'] ?? ''), 'null')) {
                    return $result;
                }
            } catch (Throwable $e) {
                Log::debug('fixity scan failed', ['tool' => $tool->name(), 'error' => $e->getMessage()]);
            }
        }
        return $last ?? (new NullFixityTool())->scan($path);
    }

    protected function writeIdentifyEvent(int $doId, int $ioId, array $identify): void
    {
        $detail = sprintf(
            'Format identification: name=%s version=%s mime=%s pronom=%s',
            $identify['format_name']    ?? 'unknown',
            $identify['format_version'] ?? '',
            $identify['mime_type']      ?? '',
            $identify['format_pronom']  ?? ''
        );
        $this->preservation->logEvent($doId, $ioId, 'format_identification', $detail,
            ($identify['format_id'] ?? 'unknown') === 'unknown' ? 'warning' : 'success');
    }

    protected function writeScanEvent(int $doId, int $ioId, array $scan): void
    {
        $outcome = ($scan['clean'] ?? false) ? 'success' : 'failure';
        $threats = ! empty($scan['threats']) ? ' threats=' . implode(',', $scan['threats']) : '';
        $detail  = sprintf('Malware scan via %s%s', $scan['scanner_version'] ?? 'unknown', $threats);
        $this->preservation->logEvent($doId, $ioId, 'virus_check', $detail, $outcome);

        if (Schema::hasTable('preservation_virus_scan')) {
            try {
                DB::table('preservation_virus_scan')->insert([
                    'digital_object_id' => $doId,
                    'scanner'           => substr((string) ($scan['scanner_version'] ?? 'unknown'), 0, 128),
                    'scanned_at'        => now()->format('Y-m-d H:i:s'),
                    'status'            => ($scan['clean'] ?? false) ? 'clean' : 'infected',
                    'threats_found'     => ! empty($scan['threats']) ? json_encode($scan['threats']) : null,
                    'created_at'        => now()->format('Y-m-d H:i:s'),
                ]);
            } catch (Throwable $e) {
                Log::debug('preservation_virus_scan insert failed', ['error' => $e->getMessage()]);
            }
        }
    }

    protected function logFailure(int $doId, int $ioId, string $type, string $detail): void
    {
        try {
            $this->preservation->logEvent($doId, $ioId, $type, $detail, 'failure');
        } catch (Throwable $e) {
            Log::warning('fixity scan logging failed', ['error' => $e->getMessage()]);
        }
    }
}
