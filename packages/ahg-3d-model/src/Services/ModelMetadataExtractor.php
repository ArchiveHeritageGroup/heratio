<?php

/**
 * ModelMetadataExtractor - Service for Heratio
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

/**
 * #1178 - parse a 3D model file and derive technical metadata WITHOUT a GPU or
 * Node tools: format version, compression, model-space bounding box, vertex/face
 * counts, texture/animation counts, materials/rig flags, and the PBR map set.
 * Pure PHP (stream-friendly) so it runs safely in a web request at upload time.
 * Returns only the keys it could determine; callers merge into object_3d_model.
 */
class ModelMetadataExtractor
{
    public function extract(string $absPath, string $format): array
    {
        $format = strtolower($format);
        if (! is_file($absPath)) {
            return [];
        }
        try {
            return match ($format) {
                'glb' => $this->fromGltf($this->glbJson($absPath)),
                'gltf' => $this->fromGltf((string) @file_get_contents($absPath)),
                'obj' => $this->fromObj($absPath),
                'ply' => $this->fromPly($absPath),
                'stl' => $this->fromStl($absPath),
                default => [],
            };
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Pull the JSON chunk out of a binary glTF (.glb) container. */
    private function glbJson(string $path): string
    {
        $fh = @fopen($path, 'rb');
        if (! $fh) {
            return '';
        }
        try {
            $hdr = fread($fh, 12);
            if (strlen($hdr) < 12 || substr($hdr, 0, 4) !== 'glTF') {
                return '';
            }
            while (! feof($fh)) {
                $ch = fread($fh, 8);
                if (strlen($ch) < 8) {
                    break;
                }
                $u = unpack('Vlen/Vtype', $ch);
                $data = $u['len'] > 0 ? fread($fh, $u['len']) : '';
                if ($u['type'] === 0x4E4F534A) { // 'JSON'
                    return (string) $data;
                }
            }

            return '';
        } finally {
            fclose($fh);
        }
    }

    private function fromGltf(string $json): array
    {
        $g = $json !== '' ? json_decode($json, true) : null;
        if (! is_array($g)) {
            return [];
        }
        $o = [
            'format_version' => 'glTF '.($g['asset']['version'] ?? '2.0'),
            'animation_count' => count($g['animations'] ?? []),
            'texture_count' => count($g['textures'] ?? ($g['images'] ?? [])),
            'has_materials' => ! empty($g['materials']) ? 1 : 0,
            'has_rig' => ! empty($g['skins']) ? 1 : 0,
        ];

        $ext = $g['extensionsUsed'] ?? [];
        $o['compression'] = in_array('KHR_draco_mesh_compression', $ext, true) ? 'draco'
            : (in_array('EXT_meshopt_compression', $ext, true) ? 'meshopt'
            : (in_array('KHR_texture_basisu', $ext, true) ? 'ktx2' : 'none'));

        // Bounding box (model space) + vertex/face counts from accessors.
        $acc = $g['accessors'] ?? [];
        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];
        $verts = 0;
        $idx = 0;
        $foundPos = false;
        foreach (($g['meshes'] ?? []) as $mesh) {
            foreach (($mesh['primitives'] ?? []) as $prim) {
                $pi = $prim['attributes']['POSITION'] ?? null;
                if ($pi !== null && isset($acc[$pi])) {
                    $verts += (int) ($acc[$pi]['count'] ?? 0);
                    if (isset($acc[$pi]['min'], $acc[$pi]['max']) && count($acc[$pi]['min']) >= 3) {
                        $foundPos = true;
                        for ($i = 0; $i < 3; $i++) {
                            $min[$i] = min($min[$i], (float) $acc[$pi]['min'][$i]);
                            $max[$i] = max($max[$i], (float) $acc[$pi]['max'][$i]);
                        }
                    }
                }
                $ii = $prim['indices'] ?? null;
                if ($ii !== null && isset($acc[$ii])) {
                    $idx += (int) ($acc[$ii]['count'] ?? 0);
                }
            }
        }
        if ($verts) {
            $o['vertex_count'] = $verts;
        }
        if ($idx) {
            $o['face_count'] = intdiv($idx, 3);
        }
        if ($foundPos) {
            $o['bounding_box'] = $this->bbox($min, $max);
        }

        // PBR map set.
        $maps = [];
        foreach (($g['materials'] ?? []) as $m) {
            if (isset($m['pbrMetallicRoughness']['baseColorTexture'])) {
                $maps['baseColor'] = 1;
            }
            if (isset($m['pbrMetallicRoughness']['metallicRoughnessTexture'])) {
                $maps['metalRough'] = 1;
            }
            if (isset($m['normalTexture'])) {
                $maps['normal'] = 1;
            }
            if (isset($m['occlusionTexture'])) {
                $maps['occlusion'] = 1;
            }
            if (isset($m['emissiveTexture'])) {
                $maps['emissive'] = 1;
            }
        }
        if ($maps) {
            $o['pbr_maps'] = implode(',', array_keys($maps));
        }

        return $o;
    }

    private function fromObj(string $path): array
    {
        $fh = @fopen($path, 'r');
        if (! $fh) {
            return [];
        }
        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];
        $v = 0;
        $f = 0;
        try {
            while (($line = fgets($fh)) !== false) {
                if (str_starts_with($line, 'v ')) {
                    $p = preg_split('/\s+/', trim($line));
                    if (count($p) >= 4) {
                        $xyz = [(float) $p[1], (float) $p[2], (float) $p[3]];
                        for ($i = 0; $i < 3; $i++) {
                            $min[$i] = min($min[$i], $xyz[$i]);
                            $max[$i] = max($max[$i], $xyz[$i]);
                        }
                        $v++;
                    }
                } elseif (str_starts_with($line, 'f ')) {
                    $f++;
                }
            }
        } finally {
            fclose($fh);
        }
        $o = ['format_version' => 'OBJ', 'compression' => 'none'];
        if ($v) {
            $o['vertex_count'] = $v;
            $o['bounding_box'] = $this->bbox($min, $max);
        }
        if ($f) {
            $o['face_count'] = $f;
        }

        return $o;
    }

    private function fromPly(string $path): array
    {
        $fh = @fopen($path, 'rb');
        if (! $fh) {
            return [];
        }
        $o = ['format_version' => 'PLY', 'compression' => 'none'];
        try {
            for ($i = 0; $i < 60 && ($line = fgets($fh)) !== false; $i++) {
                $line = trim($line);
                if (str_starts_with($line, 'element vertex ')) {
                    $o['vertex_count'] = (int) substr($line, 15);
                } elseif (str_starts_with($line, 'element face ')) {
                    $o['face_count'] = (int) substr($line, 13);
                } elseif ($line === 'end_header') {
                    break;
                }
            }
        } finally {
            fclose($fh);
        }

        return $o;
    }

    private function fromStl(string $path): array
    {
        $o = ['format_version' => 'STL', 'compression' => 'none'];
        $fh = @fopen($path, 'rb');
        if (! $fh) {
            return $o;
        }
        try {
            $head = fread($fh, 5);
            if (strtolower((string) $head) === 'solid') {
                return $o; // ASCII STL - skip (no cheap count)
            }
            // Binary STL: 80-byte header then uint32 triangle count.
            fseek($fh, 80);
            $cnt = fread($fh, 4);
            if (strlen($cnt) === 4) {
                $n = unpack('Vn', $cnt)['n'];
                $o['face_count'] = (int) $n;
                $o['vertex_count'] = (int) $n * 3;
            }
        } finally {
            fclose($fh);
        }

        return $o;
    }

    /** Format a model-space bounding box "minX,minY,minZ maxX,maxY,maxZ". */
    private function bbox(array $min, array $max): string
    {
        $fmt = fn ($a) => sprintf('%g,%g,%g', $a[0], $a[1], $a[2]);

        return $fmt($min).' '.$fmt($max);
    }
}
