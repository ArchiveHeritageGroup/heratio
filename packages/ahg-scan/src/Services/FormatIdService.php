<?php

/**
 * FormatIdService — Heratio ahg-scan (P4)
 *
 * Identifies the technical format of a staged file via the PRONOM vocabulary.
 *
 *   Primary tool:    siegfried (`sf -json`) — DROID-compatible, fast, open.
 *   Fallback:        GNU `file --mime-type` when siegfried isn't installed.
 *
 * Writes to `preservation_format` (registry), and the caller emits a
 * `formatIdentification` PREMIS event via PremisEventService.
 *
 * When a PUID isn't in the local `preservation_format` table, the row is
 * created (`risk_level='medium'`, `preservation_action='monitor'`) so every
 * identified format has a record curators can enrich later. Obsolete
 * formats (`risk_level='high'` or `preservation_action='migrate'`) get an
 * entry in `preservation_format_obsolescence` so migration tooling can
 * surface them.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FormatIdService
{
    /**
     * Identify the format of a file. Returns a summary array:
     *   puid:        string PRONOM URI (e.g. 'fmt/43') or 'UNKNOWN'
     *   mime:        string
     *   format_name: string (human-readable)
     *   format_id:   int preservation_format.id (if written)
     *   tool:        'siegfried' | 'file' | 'none'
     *   risk_level:  'low' | 'medium' | 'high' (from preservation_format)
     *   warning:     string|null  non-fatal detection warning
     */
    public static function identify(string $filePath): array
    {
        $result = [
            'puid' => 'UNKNOWN',
            'mime' => null,
            'format_name' => null,
            'format_id' => null,
            'tool' => 'none',
            'risk_level' => 'medium',
            'warning' => null,
        ];

        if (!is_file($filePath)) {
            $result['warning'] = 'File does not exist';
            return $result;
        }

        // 1. siegfried — preferred
        $sf = trim((string) @shell_exec('command -v sf 2>/dev/null'));
        if ($sf !== '') {
            $result = array_merge($result, self::runSiegfried($sf, $filePath));
        } else {
            // 2. file command — fallback, no PUID, just MIME
            $file = trim((string) @shell_exec('command -v file 2>/dev/null'));
            if ($file !== '') {
                $mime = trim((string) @shell_exec($file . ' --mime-type --brief ' . escapeshellarg($filePath) . ' 2>/dev/null'));
                if ($mime !== '') {
                    $result['mime'] = $mime;
                    $result['tool'] = 'file';
                }
            }
        }

        // 3. Look up or create the preservation_format registry row.
        if ($result['puid'] !== 'UNKNOWN' || $result['mime']) {
            $formatRow = self::lookupOrCreateFormat($result['puid'], $result['mime'], $result['format_name'], $filePath);
            if ($formatRow) {
                $result['format_id'] = $formatRow->id;
                $result['risk_level'] = $formatRow->risk_level ?: 'medium';

                // Flag obsolete formats.
                if ($formatRow->risk_level === 'high' || $formatRow->preservation_action === 'migrate') {
                    self::recordObsolescence($formatRow);
                }
            }
        }

        return $result;
    }

    protected static function runSiegfried(string $binary, string $filePath): array
    {
        $out = [];
        $exit = 0;
        exec($binary . ' -json ' . escapeshellarg($filePath) . ' 2>/dev/null', $out, $exit);
        if ($exit !== 0 || empty($out)) {
            return ['tool' => 'none', 'warning' => 'siegfried exit ' . $exit];
        }
        $json = json_decode(implode("\n", $out), true);
        if (!is_array($json) || empty($json['files'][0]['matches'][0])) {
            return ['tool' => 'siegfried', 'warning' => 'siegfried produced no match'];
        }
        $m = $json['files'][0]['matches'][0];
        return [
            'puid' => $m['id'] ?? 'UNKNOWN',
            'mime' => $m['mime'] ?? null,
            'format_name' => $m['format'] ?? null,
            'tool' => 'siegfried',
            'warning' => $m['warning'] ?? null,
        ];
    }

    /**
     * Look up a PUID in preservation_format; create the row if not found.
     * Prefers PUID-based match, falls back to mime-type match, and finally
     * auto-creates with a conservative 'medium' risk level.
     */
    protected static function lookupOrCreateFormat(?string $puid, ?string $mime, ?string $formatName, string $filePath): ?object
    {
        if ($puid && $puid !== 'UNKNOWN') {
            $row = DB::table('preservation_format')->where('puid', $puid)->first();
            if ($row) { return $row; }
        }
        if ($mime) {
            $row = DB::table('preservation_format')->where('mime_type', $mime)->first();
            if ($row) { return $row; }
        }

        // Create a stub row so curators can enrich later.
        try {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $id = DB::table('preservation_format')->insertGetId([
                'puid' => $puid && $puid !== 'UNKNOWN' ? $puid : null,
                'mime_type' => $mime,
                'format_name' => $formatName ?: ($mime ?: 'Unidentified format'),
                'extension' => $ext ?: null,
                'risk_level' => 'medium',
                'preservation_action' => 'monitor',
                'created_at' => now(),
            ]);
            return DB::table('preservation_format')->where('id', $id)->first();
        } catch (\Throwable $e) {
            Log::warning('[ahg-scan] preservation_format insert failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Record an obsolete-format observation so migration planners can
     * surface it. Idempotent per (format_id, puid) — increments the
     * affected-object count instead of duplicating rows.
     */
    protected static function recordObsolescence(object $format): void
    {
        try {
            $row = DB::table('preservation_format_obsolescence')
                ->where('format_id', $format->id)
                ->where('puid', $format->puid ?: '')
                ->first();
            if ($row) {
                DB::table('preservation_format_obsolescence')->where('id', $row->id)->update([
                    'affected_object_count' => DB::raw('COALESCE(affected_object_count,0)+1'),
                    'last_assessed_at' => now(),
                ]);
            } else {
                DB::table('preservation_format_obsolescence')->insert([
                    'format_id' => $format->id,
                    'puid' => $format->puid ?: '',
                    'current_risk_level' => $format->risk_level ?: 'medium',
                    'migration_urgency' => $format->risk_level === 'high' ? 'high' : 'medium',
                    'affected_object_count' => 1,
                    'recommended_action' => 'Migrate to preservation format',
                    'last_assessed_at' => now(),
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-scan] preservation_format_obsolescence update failed: ' . $e->getMessage());
        }
    }
}
