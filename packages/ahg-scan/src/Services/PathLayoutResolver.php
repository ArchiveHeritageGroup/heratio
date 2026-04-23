<?php

/**
 * PathLayoutResolver — Heratio ahg-scan
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Services;

use Illuminate\Support\Facades\DB;

/**
 * Parses a staged file's path (relative to its scan_folder root) into a
 * destination descriptor for IngestService::ingestFile().
 *
 * Layout 'path' (default):
 *   <folder>/<parent_slug>/<identifier>/<page_or_file>.ext
 *   <folder>/<parent_slug>/<identifier_file.ext>          (no subdir = identifier is the filename stem)
 *
 *   The parent_slug is resolved against the slug table; the identifier is
 *   taken from the directory name (or filename stem if flat). Additional
 *   path components beyond parent_slug/identifier are treated as sequence.
 */
class PathLayoutResolver
{
    /**
     * @param object $folder  scan_folder row
     * @param string $absPath absolute path to the detected file
     *
     * @return array{parent_id:int,identifier:?string,title:?string,sequence:?int,relative:string}|null
     *              null if the path cannot be resolved (caller should quarantine).
     */
    public function resolve(object $folder, string $absPath): ?array
    {
        $root = rtrim($folder->path, '/');
        $full = realpath($absPath) ?: $absPath;

        if (strpos($full, $root . '/') !== 0) {
            return null;
        }

        $relative = substr($full, strlen($root) + 1);
        $parts = explode('/', $relative);

        // Style 1: <parent_slug>/<identifier>/<file>
        // Style 1-flat: <parent_slug>/<identifier>.ext  (identifier = stem)
        if (count($parts) < 2) {
            return null;
        }

        $parentSlug = $parts[0];
        $parentId = $this->resolveParentSlug($parentSlug);
        if (!$parentId) {
            return null;
        }

        if (count($parts) === 2) {
            // <parent_slug>/<identifier>.ext — single-file item
            $stem = pathinfo($parts[1], PATHINFO_FILENAME);
            return [
                'parent_id' => $parentId,
                'identifier' => $stem,
                'title' => $stem,
                'sequence' => null,
                'relative' => $relative,
            ];
        }

        // <parent_slug>/<identifier>/<file[.../file]>
        $identifier = $parts[1];
        $tail = array_slice($parts, 2);
        $file = end($tail) ?: '';
        $sequence = $this->extractSequence($file);

        return [
            'parent_id' => $parentId,
            'identifier' => $identifier,
            'title' => $identifier,
            'sequence' => $sequence,
            'relative' => $relative,
        ];
    }

    protected function resolveParentSlug(string $slug): ?int
    {
        $row = DB::table('slug')->where('slug', $slug)->first();
        return $row ? (int) $row->object_id : null;
    }

    /**
     * Extract a 1-based page sequence from common filename patterns:
     *   page_001.tiff, img0042.jpg, scan-07.pdf, 003.tiff
     */
    protected function extractSequence(string $filename): ?int
    {
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        if (preg_match('/(\d{1,6})$/', $stem, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
