<?php

/**
 * GlbBuilder - a tiny, dependency-free writer for binary glTF (.glb) meshes.
 *
 * Accumulate flat-shaded triangles grouped by material, then emit a single
 * self-contained .glb (JSON chunk + BIN chunk) that Three.js GLTFLoader reads
 * directly. Geometry is non-indexed (3 vertices = 1 triangle) with per-vertex
 * normals computed from the face, which is exactly what parametric architectural
 * shells need - simple, robust, no external 3D toolchain.
 *
 * Used by the parametric reconstruction generators (e.g. the Crystal Palace
 * shell, #1323) to produce a walkable structure as a glTF scan_shell.
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
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Ahg3dModel\Services;

class GlbBuilder
{
    /** @var array<string,array{color:array<float>,metal:float,rough:float,blend:bool,double:bool}> */
    private array $materials = [];

    /** @var array<string,array{pos:array<float>,nrm:array<float>}> insertion-ordered with $materials */
    private array $groups = [];

    /**
     * Register (or update) a material. Alpha < 1 auto-enables BLEND + double-sided
     * so glass reads correctly from both faces.
     *
     * @param  array{0:float,1:float,2:float,3?:float}  $rgba
     */
    public function material(string $key, array $rgba, float $metal = 0.0, float $rough = 0.8, bool $double = false): void
    {
        $a = $rgba[3] ?? 1.0;
        $blend = $a < 0.999;
        $this->materials[$key] = [
            'color' => [(float) $rgba[0], (float) $rgba[1], (float) $rgba[2], (float) $a],
            'metal' => $metal, 'rough' => $rough, 'blend' => $blend, 'double' => $double || $blend,
        ];
        $this->groups[$key] ??= ['pos' => [], 'nrm' => []];
    }

    /** Add a triangle (CCW = front). Normal is derived from the winding. */
    public function triangle(string $m, array $a, array $b, array $c): void
    {
        $g = &$this->groups[$m];
        $n = $this->faceNormal($a, $b, $c);
        foreach ([$a, $b, $c] as $v) {
            $g['pos'][] = (float) $v[0]; $g['pos'][] = (float) $v[1]; $g['pos'][] = (float) $v[2];
            $g['nrm'][] = $n[0]; $g['nrm'][] = $n[1]; $g['nrm'][] = $n[2];
        }
    }

    /** Add a quad as two triangles (a-b-c, a-c-d). */
    public function quad(string $m, array $a, array $b, array $c, array $d): void
    {
        $this->triangle($m, $a, $b, $c);
        $this->triangle($m, $a, $c, $d);
    }

    /** Axis-aligned box from min corner to max corner (6 outward-facing quads). */
    public function box(string $m, array $min, array $max): void
    {
        [$x0, $y0, $z0] = $min;
        [$x1, $y1, $z1] = $max;
        // 8 corners
        $a = [$x0, $y0, $z0]; $b = [$x1, $y0, $z0]; $c = [$x1, $y0, $z1]; $d = [$x0, $y0, $z1];
        $e = [$x0, $y1, $z0]; $f = [$x1, $y1, $z0]; $g = [$x1, $y1, $z1]; $h = [$x0, $y1, $z1];
        $this->quad($m, $d, $c, $b, $a); // bottom (-Y)
        $this->quad($m, $e, $f, $g, $h); // top (+Y)
        $this->quad($m, $a, $b, $f, $e); // -Z
        $this->quad($m, $c, $d, $h, $g); // +Z
        $this->quad($m, $d, $a, $e, $h); // -X
        $this->quad($m, $b, $c, $g, $f); // +X
    }

    /** Serialise everything to a .glb binary string. */
    public function toGlb(): string
    {
        $bin = '';
        $bufferViews = [];
        $accessors = [];
        $primitives = [];
        $materialsJson = [];
        $matIndex = 0;

        foreach ($this->materials as $key => $mat) {
            $mj = ['pbrMetallicRoughness' => [
                'baseColorFactor' => $mat['color'],
                'metallicFactor' => $mat['metal'],
                'roughnessFactor' => $mat['rough'],
            ]];
            if ($mat['blend']) { $mj['alphaMode'] = 'BLEND'; }
            if ($mat['double']) { $mj['doubleSided'] = true; }
            $materialsJson[] = $mj;

            $g = $this->groups[$key];
            $count = intdiv(count($g['pos']), 3);
            if ($count === 0) { $matIndex++; continue; }

            // POSITION
            $min = [INF, INF, INF];
            $max = [-INF, -INF, -INF];
            $posBytes = '';
            for ($i = 0, $n = count($g['pos']); $i < $n; $i += 3) {
                $x = $g['pos'][$i]; $y = $g['pos'][$i + 1]; $z = $g['pos'][$i + 2];
                $posBytes .= pack('g', $x).pack('g', $y).pack('g', $z);
                $min[0] = min($min[0], $x); $min[1] = min($min[1], $y); $min[2] = min($min[2], $z);
                $max[0] = max($max[0], $x); $max[1] = max($max[1], $y); $max[2] = max($max[2], $z);
            }
            $posView = count($bufferViews);
            $bufferViews[] = ['buffer' => 0, 'byteOffset' => strlen($bin), 'byteLength' => strlen($posBytes), 'target' => 34962];
            $bin .= $posBytes;
            $posAcc = count($accessors);
            $accessors[] = ['bufferView' => $posView, 'componentType' => 5126, 'count' => $count, 'type' => 'VEC3', 'min' => $min, 'max' => $max];

            // NORMAL
            $nrmBytes = '';
            foreach ($g['nrm'] as $v) { $nrmBytes .= pack('g', $v); }
            $nrmView = count($bufferViews);
            $bufferViews[] = ['buffer' => 0, 'byteOffset' => strlen($bin), 'byteLength' => strlen($nrmBytes), 'target' => 34962];
            $bin .= $nrmBytes;
            $nrmAcc = count($accessors);
            $accessors[] = ['bufferView' => $nrmView, 'componentType' => 5126, 'count' => $count, 'type' => 'VEC3'];

            $primitives[] = ['attributes' => ['POSITION' => $posAcc, 'NORMAL' => $nrmAcc], 'material' => $matIndex, 'mode' => 4];
            $matIndex++;
        }

        $gltf = [
            'asset' => ['version' => '2.0', 'generator' => 'Heratio GlbBuilder'],
            'scene' => 0,
            'scenes' => [['nodes' => [0]]],
            'nodes' => [['mesh' => 0]],
            'meshes' => [['primitives' => $primitives]],
            'materials' => $materialsJson,
            'accessors' => $accessors,
            'bufferViews' => $bufferViews,
            'buffers' => [['byteLength' => strlen($bin)]],
        ];

        return $this->container(json_encode($gltf, JSON_UNESCAPED_SLASHES), $bin);
    }

    private function faceNormal(array $a, array $b, array $c): array
    {
        $ux = $b[0] - $a[0]; $uy = $b[1] - $a[1]; $uz = $b[2] - $a[2];
        $vx = $c[0] - $a[0]; $vy = $c[1] - $a[1]; $vz = $c[2] - $a[2];
        $nx = $uy * $vz - $uz * $vy;
        $ny = $uz * $vx - $ux * $vz;
        $nz = $ux * $vy - $uy * $vx;
        $len = sqrt($nx * $nx + $ny * $ny + $nz * $nz) ?: 1.0;

        return [$nx / $len, $ny / $len, $nz / $len];
    }

    /** Wrap JSON + BIN into the binary glTF container (12-byte header + 2 chunks). */
    private function container(string $json, string $bin): string
    {
        $json .= str_repeat(' ', (4 - strlen($json) % 4) % 4);       // pad JSON chunk with spaces
        $bin .= str_repeat("\0", (4 - strlen($bin) % 4) % 4);        // pad BIN chunk with zeros
        $total = 12 + 8 + strlen($json) + 8 + strlen($bin);

        $out = pack('V', 0x46546C67).pack('V', 2).pack('V', $total);  // 'glTF', version 2, length
        $out .= pack('V', strlen($json)).pack('V', 0x4E4F534A).$json; // JSON chunk
        $out .= pack('V', strlen($bin)).pack('V', 0x004E4942).$bin;   // BIN chunk

        return $out;
    }
}
