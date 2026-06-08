<?php

/**
 * Model3dRegistry - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 */

namespace Ahg3dModel\Services;

use Illuminate\Support\Facades\DB;

/**
 * #1178 (Option A) - unify 3D metadata across both 3D paths.
 *
 * A 3D model reaches a record either as a managed object_3d_model row (the 3D
 * package upload flow) OR as a plain digital_object (the normal "attach a file"
 * flow used on most GLAM/DAM records). This registry makes sure EVERY record
 * with a 3D digital object also has an object_3d_model row carrying the
 * auto-extracted technical metadata, so the same metadata panel can render on
 * the GLAM/DAM/sector show pages (via a View::composer) and the 3D admin page.
 *
 * Extraction is cheap (only the glTF JSON chunk / OBJ-PLY-STL headers are read,
 * never the binary geometry), and the row is created once then reused.
 */
class Model3dRegistry
{
    private const EXTS = ['glb', 'gltf', 'obj', 'ply', 'stl', 'usdz'];

    public function __construct(private ModelMetadataExtractor $extractor) {}

    /**
     * Return the object_3d_model row for a record, creating it from the record's
     * 3D master digital object (with extracted metadata) if it does not exist.
     * Returns null when the record has no 3D content. Never throws.
     */
    public function ensureForObject(int $ioId): ?object
    {
        try {
            $row = DB::table('object_3d_model')
                ->where('object_id', $ioId)
                ->orderByDesc('is_primary')->orderBy('id')->first();

            if ($row) {
                // Lazily backfill metadata onto a pre-existing row that lacks it.
                if (empty($row->format_version)) {
                    $this->extractInto($row);
                    $row = DB::table('object_3d_model')->where('id', $row->id)->first();
                }

                return $row;
            }

            $do = $this->findMasterDigitalObject($ioId);
            if (! $do) {
                return null;
            }
            $ext = strtolower(pathinfo((string) $do->name, PATHINFO_EXTENSION));
            if (! in_array($ext, self::EXTS, true)) {
                return null;
            }

            // Web-relative path the 3D viewer expects: /uploads/<file_path>.
            $rel = ltrim((string) $do->path, '/');
            if (str_starts_with($rel, 'uploads/')) {
                $rel = substr($rel, 8);
            }
            $filePath = rtrim($rel, '/').'/'.$do->name;

            $abs = $this->absolutePath($do);
            $meta = ($abs && is_file($abs)) ? $this->extractor->extract($abs, $ext) : [];

            $id = DB::table('object_3d_model')->insertGetId(array_merge([
                'object_id' => $ioId,
                'filename' => $do->name,
                'original_filename' => $do->name,
                'file_path' => $filePath,
                'file_size' => $do->byte_size ?? null,
                'mime_type' => $do->mime_type ?: 'model/gltf-binary',
                'format' => $ext,
                'is_primary' => 1,
                'is_public' => 1,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ], $meta));

            return DB::table('object_3d_model')->where('id', $id)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** The record's 3D master digital object (a model file attached as usage MASTER). */
    public function findMasterDigitalObject(int $ioId): ?object
    {
        return DB::table('digital_object')
            ->where('object_id', $ioId)
            ->where(function ($q) {
                $q->where('mime_type', 'like', 'model/%')
                    ->orWhere('name', 'like', '%.glb')->orWhere('name', 'like', '%.gltf')
                    ->orWhere('name', 'like', '%.obj')->orWhere('name', 'like', '%.ply')
                    ->orWhere('name', 'like', '%.stl')->orWhere('name', 'like', '%.usdz');
            })
            ->orderBy('id')->first();
    }

    /** Re-extract metadata for an existing object_3d_model row and persist it. */
    private function extractInto(object $row): void
    {
        $abs = config('heratio.uploads_path')
            ? rtrim((string) config('heratio.uploads_path'), '/').'/'.ltrim((string) $row->file_path, '/')
            : null;
        // 3D-package uploads live under uploads_path/3d/...; DO-sourced rows store
        // an /uploads-relative path resolved against storage_path instead.
        if (! $abs || ! is_file($abs)) {
            $abs = rtrim((string) config('heratio.storage_path'), '/').'/uploads/'.ltrim((string) $row->file_path, '/');
        }
        if (! is_file($abs)) {
            return;
        }
        $ext = strtolower((string) ($row->format ?: pathinfo($abs, PATHINFO_EXTENSION)));
        $meta = $this->extractor->extract($abs, $ext);
        if ($meta) {
            DB::table('object_3d_model')->where('id', $row->id)->update($meta + ['updated_at' => now()]);
        }
    }

    /** Absolute filesystem path of a digital object (served via the /uploads alias). */
    private function absolutePath(object $do): ?string
    {
        $base = rtrim((string) config('heratio.storage_path'), '/');
        if ($base === '') {
            return null;
        }

        return $base.$do->path.$do->name; // path has leading /uploads/ + trailing /
    }
}
