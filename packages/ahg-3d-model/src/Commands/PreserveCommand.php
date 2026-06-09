<?php

/**
 * PreserveCommand - Console command for Heratio
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

namespace Ahg3dModel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * #1179 - enrol every 3D model as a managed OAIS preservation object:
 *   - resolve the PRESERVATION MASTER file (the uncompressed original where a
 *     Draco "-opt.glb" access copy exists, otherwise the model file);
 *   - SHA-256 checksum (fixity);
 *   - PRONOM format identification (PUID, MIME, risk);
 *   - a premis_object record carrying the checksum, PUID, size, the 3D
 *     SIGNIFICANT PROPERTIES (geometry / scale & units / coordinate system /
 *     compression / materials / animation), and an is_preservation_master flag;
 *   - a preservation_checksum row for the access digital object (fixity coverage);
 *   - a PREMIS event.
 *
 * Master-mesh scope (per #1179): raw capture data + Archivematica are out of scope.
 */
class PreserveCommand extends Command
{
    protected $signature = 'ahg:3d-preserve {--id= : One information_object id} {--force : Re-enrol even if a premis_object already exists}';

    protected $description = 'Enrol 3D model masters as OAIS preservation objects (fixity + PRONOM + PREMIS + significant properties)';

    public function handle(\Ahg3dModel\Services\Model3dRegistry $registry): int
    {
        $pronom = app(\AhgPreservation\Services\PronomIdentificationService::class);

        $q = DB::table('object_3d_model');
        if ($this->option('id')) {
            $q->where('object_id', (int) $this->option('id'));
        }
        $models = $q->get();
        $this->info("Enrolling {$models->count()} 3D model(s)...");

        $done = 0;
        foreach ($models as $m) {
            $ioId = (int) $m->object_id;
            $accessAbs = $this->resolve((string) $m->file_path);
            if (! $accessAbs) {
                $this->warn("  io#{$ioId} {$m->filename} - file not found, skip");
                continue;
            }

            // Preservation master = the uncompressed original when this is a
            // Draco "-opt.glb" access copy and the original sits alongside.
            $presAbs = $accessAbs;
            $isMasterOriginal = false;
            if (preg_match('/-opt\.glb$/i', $accessAbs)) {
                $orig = preg_replace('/-opt\.glb$/i', '.glb', $accessAbs);
                if ($orig && is_file($orig)) {
                    $presAbs = $orig;
                    $isMasterOriginal = true;
                }
            }
            $presName = basename($presAbs);

            // premis_object.id is a FK to object.id => it is keyed by the access
            // digital object's id (1:1). 3D-module models without a digital object
            // can't get a premis_object row; skip them (rare).
            $doId = $this->accessDigitalObjectId($registry, $ioId);
            if (! $doId) {
                $this->warn("  io#{$ioId} {$presName} - no digital object (3D-module model); PREMIS skipped");
                continue;
            }

            $existing = DB::table('premis_object')->where('id', $doId)->first();
            if ($existing && ! $this->option('force')) {
                $this->line("  io#{$ioId} {$presName} - already enrolled");
                continue;
            }

            $checksum = @hash_file('sha256', $presAbs);
            $fmt = $pronom->identifyFile($presAbs);
            $now = now();

            DB::table('premis_object')->updateOrInsert(
                ['id' => $doId],
                [
                    'information_object_id' => $ioId,
                    'filename' => $presName,
                    'puid' => $fmt['puid'] ?? '',
                    'mime_type' => $fmt['mime'] ?? ($m->mime_type ?? 'model/gltf-binary'),
                    'size' => @filesize($presAbs) ?: 0,
                    'last_modified' => date('Y-m-d H:i:s', @filemtime($presAbs) ?: time()),
                    'date_ingested' => $now,
                    'checksum' => $checksum,
                    'checksum_algorithm' => 'sha256',
                    'significant_properties' => json_encode($this->significantProperties($m, $fmt, $presAbs)),
                    'is_preservation_master' => 1,
                    'digital_object_id' => $doId,
                ]
            );

            // Fixity coverage on the access digital object (the dashboard metric).
            if (! DB::table('preservation_checksum')->where('digital_object_id', $doId)->exists()) {
                $accChk = @hash_file('sha256', $accessAbs);
                if ($accChk) {
                    DB::table('preservation_checksum')->insert([
                        'digital_object_id' => $doId,
                        'algorithm' => 'sha256',
                        'checksum_value' => $accChk,
                        'file_size' => @filesize($accessAbs) ?: 0,
                        'generated_at' => $now,
                        'verification_status' => 'pending',
                        'created_at' => $now,
                    ]);
                }
            }

            $this->logEvent($doId, $ioId, $presName, $fmt, $isMasterOriginal);

            $note = $isMasterOriginal ? ' (uncompressed original = master; -opt.glb is the access derivative)' : '';
            $this->info("  io#{$ioId} {$presName} [".($fmt['puid'] ?: 'no-PUID').'] '.$fmt['name'].$note);
            $done++;
        }

        $this->info("Enrolled {$done} model(s). PREMIS objects + fixity recorded.");

        return 0;
    }

    /** Best-effort absolute-path resolution across the two storage layouts. */
    private function resolve(string $filePath): ?string
    {
        $fp = ltrim($filePath, '/');
        $storage = rtrim((string) config('heratio.storage_path'), '/');
        $uploads = rtrim((string) config('heratio.uploads_path'), '/');
        foreach ([
            $storage.'/uploads/'.$fp,   // DO-sourced: /uploads/r/... (r -> archive symlink)
            $uploads.'/'.$fp,           // 3D-module uploads
            $storage.'/'.$fp,
            public_path().'/uploads/'.$fp,
        ] as $cand) {
            if ($cand && is_file($cand)) {
                return $cand;
            }
        }

        return null;
    }

    private function accessDigitalObjectId(\Ahg3dModel\Services\Model3dRegistry $registry, int $ioId): ?int
    {
        $do = $registry->findMasterDigitalObject($ioId);

        return $do ? (int) $do->id : null;
    }

    private function significantProperties(object $m, array $fmt, string $masterAbs): array
    {
        // Geometry / format / compression are extracted from the PRESERVATION
        // MASTER file itself (so an uncompressed master reads compression=none,
        // not the access copy's draco). Curator paradata comes from the model row.
        $ext = strtolower(pathinfo($masterAbs, PATHINFO_EXTENSION));
        $x = app(\Ahg3dModel\Services\ModelMetadataExtractor::class)->extract($masterAbs, $ext);

        return array_filter([
            'format' => $x['format_version'] ?? ($m->format_version ?: ($fmt['name'] ?? null)),
            'compression' => $x['compression'] ?? ($m->compression ?? null),
            'vertex_count' => $x['vertex_count'] ?? ($m->vertex_count ?? null),
            'face_count' => $x['face_count'] ?? ($m->face_count ?? null),
            'texture_count' => $x['texture_count'] ?? ($m->texture_count ?? null),
            'animation_count' => $x['animation_count'] ?? ($m->animation_count ?? null),
            'has_materials' => isset($x['has_materials']) ? (bool) $x['has_materials'] : (isset($m->has_materials) ? (bool) $m->has_materials : null),
            'pbr_maps' => $x['pbr_maps'] ?? ($m->pbr_maps ?? null),
            'bounding_box' => $x['bounding_box'] ?? ($m->bounding_box ?? null),
            'coordinate_system' => $m->coordinate_system ?? null,
            'real_dimensions' => $this->dims($m),
            'colour_space' => $m->texture_colorspace ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function dims(object $m): ?string
    {
        if (! empty($m->real_width) || ! empty($m->real_height) || ! empty($m->real_depth)) {
            return trim(sprintf('%s x %s x %s %s', $m->real_width ?? '?', $m->real_height ?? '?', $m->real_depth ?? '?', $m->dimension_unit ?? ''));
        }

        return null;
    }

    private function logEvent(?int $doId, int $ioId, string $name, array $fmt, bool $masterOriginal): void
    {
        try {
            app(\AhgPreservation\Services\PreservationService::class)->logEvent(
                $doId ?? 0,
                $ioId,
                'ingestion',
                '3D preservation master enrolled: '.$name.' ['.($fmt['puid'] ?: 'no-PUID').']'.($masterOriginal ? ' (uncompressed original)' : ''),
                'success'
            );
        } catch (\Throwable $e) {
            // best-effort
        }
    }
}
