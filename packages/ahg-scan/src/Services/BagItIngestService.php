<?php

/**
 * BagItIngestService — Heratio ahg-scan (P6)
 *
 * Unpacks a BagIt container (RFC 8493) and enqueues each `data/` file
 * as an ingest_file on the watched folder's session. The bag-info.txt is
 * parsed and applied as session-scoped metadata; the manifest-*.txt
 * checksum file is used for fixity verification before ingest.
 *
 * Input forms accepted:
 *   - Directory with `bagit.txt` + `manifest-<alg>.txt` + `data/` subdir
 *   - `.zip` archive of the same structure
 *
 * Field mapping from `bag-info.txt` (resolves plan §12 Q#10):
 *   Source-Organization      → ingest_file.sidecar_json.source_organization
 *   Contact-Email             → ingest_file.sidecar_json.contact_email
 *   External-Identifier       → used as the IO identifier for the bag
 *   Bag-Size / Bagging-Date   → provenance metadata
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BagItIngestService
{
    /**
     * Detect whether a path is a BagIt container (zip or dir).
     */
    public static function isBag(string $path): bool
    {
        if (is_dir($path)) {
            return is_file($path . '/bagit.txt');
        }
        if (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip') {
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) { return false; }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_ends_with($name, '/bagit.txt') || $name === 'bagit.txt') {
                    $zip->close();
                    return true;
                }
            }
            $zip->close();
        }
        return false;
    }

    /**
     * Extract a zip bag into a working directory, verify checksums, and
     * enqueue each data/ file. Returns ['enqueued' => int, 'warnings' => string[]].
     *
     * @param string $bagPath  Path to the bag (zip or dir)
     * @param object $folder   scan_folder row
     */
    public static function ingest(string $bagPath, object $folder): array
    {
        $workDir = null;
        try {
            if (is_file($bagPath)) {
                $workDir = self::extractZip($bagPath);
                $bagRoot = self::findBagRoot($workDir);
            } else {
                $bagRoot = $bagPath;
            }
            if (!$bagRoot) {
                throw new \RuntimeException('Could not locate bagit.txt in container');
            }

            $bagInfo = self::parseBagInfo($bagRoot);
            $manifests = self::readManifests($bagRoot);
            $warnings = [];

            $enqueued = 0;
            foreach ($manifests as $relPath => $expected) {
                if (strpos($relPath, 'data/') !== 0) { continue; }
                $abs = $bagRoot . '/' . $relPath;
                if (!is_file($abs)) {
                    $warnings[] = "Manifest file missing on disk: {$relPath}";
                    continue;
                }
                $actual = hash_file($expected['alg'], $abs);
                if ($actual !== strtolower($expected['hash'])) {
                    $warnings[] = "Checksum mismatch for {$relPath}: expected {$expected['hash']}, got {$actual}";
                    continue;
                }

                // Copy the file into the folder's path so the watcher sees
                // it on its next pass, with bag-info + computed identifier
                // applied as the sidecar_json.
                $stagingName = self::deriveIngestName($relPath, $bagInfo);
                $targetPath = rtrim($folder->path, '/') . '/' . $stagingName;
                if (!@copy($abs, $targetPath)) {
                    $warnings[] = "Failed to stage into folder: {$relPath}";
                    continue;
                }
                @chmod($targetPath, 0644);

                // Inline metadata: feed bag-info as if it were a JSON sidecar
                // so stageResolveDestination picks it up.
                $inlineJson = [
                    'identifier' => self::baseIdentifier($bagInfo, $relPath),
                    'title' => basename($relPath),
                    'source_organization' => $bagInfo['Source-Organization'] ?? null,
                    'contact_email' => $bagInfo['Contact-Email'] ?? null,
                    'bagging_date' => $bagInfo['Bagging-Date'] ?? null,
                ];

                $hash = hash_file('sha256', $targetPath);
                $ingestFileId = DB::table('ingest_file')->insertGetId([
                    'session_id' => $folder->ingest_session_id,
                    'file_type' => 'digital_object',
                    'original_name' => basename($relPath),
                    'stored_path' => $targetPath,
                    'file_size' => filesize($targetPath) ?: null,
                    'mime_type' => function_exists('mime_content_type') ? (@mime_content_type($targetPath) ?: null) : null,
                    'status' => 'pending',
                    'source_hash' => $hash,
                    'attempts' => 0,
                    'sidecar_json' => json_encode(array_filter($inlineJson, fn($v) => $v !== null)),
                    'created_at' => now(),
                ]);

                // If the session is set up to auto-commit (as watched folders are
                // by default), dispatch immediately so the file runs through the
                // pipeline without waiting for a human to click commit.
                $autoCommit = DB::table('ingest_session')
                    ->where('id', $folder->ingest_session_id)
                    ->value('auto_commit');
                if ((int) $autoCommit === 1) {
                    \AhgScan\Jobs\ProcessScanFile::dispatch($ingestFileId, (int) $folder->id);
                }
                $enqueued++;
            }

            return ['enqueued' => $enqueued, 'warnings' => $warnings];
        } finally {
            // Clean up extracted temp dir, but leave the original bag alone.
            if ($workDir && is_dir($workDir) && $workDir !== $bagPath) {
                self::rmdirRecursive($workDir);
            }
        }
    }

    protected static function extractZip(string $zipPath): string
    {
        $tmp = sys_get_temp_dir() . '/heratio-bagit-' . bin2hex(random_bytes(6));
        mkdir($tmp, 0755, true);
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("Cannot open zip: {$zipPath}");
        }
        $zip->extractTo($tmp);
        $zip->close();
        return $tmp;
    }

    protected static function findBagRoot(string $dir): ?string
    {
        if (is_file($dir . '/bagit.txt')) { return $dir; }
        // One level deeper (zips often wrap the bag in a single directory).
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') { continue; }
            $sub = $dir . '/' . $entry;
            if (is_dir($sub) && is_file($sub . '/bagit.txt')) {
                return $sub;
            }
        }
        return null;
    }

    protected static function parseBagInfo(string $bagRoot): array
    {
        $file = $bagRoot . '/bag-info.txt';
        if (!is_file($file)) { return []; }
        $out = [];
        $currentKey = null;
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^([\w-]+)\s*:\s*(.*)$/', $line, $m)) {
                $currentKey = $m[1];
                $out[$currentKey] = trim($m[2]);
            } elseif ($currentKey && preg_match('/^\s+(.+)$/', $line, $m)) {
                $out[$currentKey] .= ' ' . trim($m[1]);
            }
        }
        return $out;
    }

    /**
     * Read all manifest-*.txt files. Returns ['data/foo.tiff' => ['alg'=>'sha256', 'hash'=>'abc...']].
     * Prefers sha256 > sha512 > md5 when multiple manifests exist.
     */
    protected static function readManifests(string $bagRoot): array
    {
        $out = [];
        $priority = ['sha512', 'sha256', 'sha1', 'md5'];
        foreach ($priority as $alg) {
            $file = $bagRoot . '/manifest-' . $alg . '.txt';
            if (!is_file($file)) { continue; }
            foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                if (preg_match('/^([a-f0-9]+)\s+(.+)$/i', $line, $m)) {
                    $path = trim($m[2]);
                    if (!isset($out[$path])) {
                        $out[$path] = ['alg' => $alg, 'hash' => strtolower($m[1])];
                    }
                }
            }
        }
        return $out;
    }

    protected static function baseIdentifier(array $bagInfo, string $relPath): ?string
    {
        if (!empty($bagInfo['External-Identifier'])) {
            return $bagInfo['External-Identifier'];
        }
        // Fall back to the filename stem if no External-Identifier supplied.
        $stem = pathinfo($relPath, PATHINFO_FILENAME);
        return $stem ?: null;
    }

    protected static function deriveIngestName(string $relPath, array $bagInfo): string
    {
        $base = basename($relPath);
        $prefix = !empty($bagInfo['External-Identifier'])
            ? preg_replace('/[^a-zA-Z0-9_.-]/', '_', $bagInfo['External-Identifier']) . '_'
            : '';
        return $prefix . $base;
    }

    protected static function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) { return; }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') { continue; }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                self::rmdirRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
