<?php

/**
 * ParametricCrystalPalaceService - #1323: build a WALKABLE Crystal Palace shell
 * procedurally, instead of guessing it with image-to-3D.
 *
 * The Crystal Palace was a modular cast-iron-and-glass kit on a regular bay grid,
 * so it reconstructs far more faithfully from parameters than from a diffusion
 * model fed mixed historic photos. This generates a representative section - a
 * basilica nave with a glazed barrel vault, side aisles, an iron colonnade on the
 * bay grid, clerestory glazing, and the great crossing transept with its
 * round-arched glass tympana - as a single .glb (via GlbBuilder), in true metres,
 * Y-up, on a corner origin so it drops straight in as an exhibition scan_shell.
 *
 * Dimensions are a human-walkable abstraction of the Sydenham palace, not a
 * survey-accurate model: honest inferred reconstruction (tagged as such in the
 * RiC provenance register), good enough to walk the nave under the glass vault.
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

namespace AhgExhibition\Services;

use Ahg3dModel\Services\GlbBuilder;

class ParametricCrystalPalaceService
{
    // --- Section parameters (metres). Tweak these to re-proportion the shell. ---
    private const L = 60.0;          // overall length (X)
    private const W = 30.0;          // overall width (Z)
    private const MARGIN = 3.0;      // grass margin between footprint edge and glass walls
    private const NAVE_Z0 = 9.0;     // nave (tall centre aisle) z-range
    private const NAVE_Z1 = 21.0;
    private const AISLE_EAVE = 6.0;  // side-aisle roof height + clerestory base
    private const NAVE_SPRING = 8.0; // height the nave vault springs from
    private const NAVE_R = 6.0;      // nave vault radius  (apex = SPRING + R = 14)
    private const TR_CX = 30.0;      // transept centre (X)
    private const TR_HALF = 8.0;     // transept half-width in X  (R = 8, apex = 16)
    private const TR_SPRING = 8.0;
    private const BAY = 7.2;         // colonnade bay spacing (X)
    private const ARC_SEG = 16;      // facets per half-vault
    private const FLOOR_Y = 0.05;

    /**
     * @return array{bytes:string,length:float,width:float}
     */
    public function generate(): array
    {
        $b = new GlbBuilder();
        $b->material('floor', [0.80, 0.76, 0.66, 1.0], 0.0, 0.95);
        $b->material('iron', [0.66, 0.16, 0.12, 1.0], 0.2, 0.5);          // Owen Jones red columns
        $b->material('girder', [0.55, 0.68, 0.82, 1.0], 0.3, 0.4);        // light-blue structural ribs
        $b->material('glass', [0.78, 0.87, 0.95, 0.15], 0.0, 0.05, true); // pale translucent glazing

        $x0 = self::MARGIN; $x1 = self::L - self::MARGIN;
        $z0 = self::MARGIN; $z1 = self::W - self::MARGIN;
        $cz = (self::NAVE_Z0 + self::NAVE_Z1) / 2;

        // 1. Stone floor.
        $b->quad('floor',
            [$x0, self::FLOOR_Y, $z0], [$x1, self::FLOOR_Y, $z0],
            [$x1, self::FLOOR_Y, $z1], [$x0, self::FLOOR_Y, $z1]
        );

        // 2. Iron colonnade on the bay grid: outer walls (to eave) + nave arcade (to springing).
        for ($x = $x0; $x <= $x1 + 0.01; $x += self::BAY) {
            $this->column($b, $x, $z0, self::AISLE_EAVE);
            $this->column($b, $x, $z1, self::AISLE_EAVE);
            $this->column($b, $x, self::NAVE_Z0, self::NAVE_SPRING);
            $this->column($b, $x, self::NAVE_Z1, self::NAVE_SPRING);
        }

        // 3. Glazed long walls (outer) + side-aisle roofs + nave clerestory.
        $b->quad('glass', [$x0, 0, $z0], [$x1, 0, $z0], [$x1, self::AISLE_EAVE, $z0], [$x0, self::AISLE_EAVE, $z0]);
        $b->quad('glass', [$x0, 0, $z1], [$x1, 0, $z1], [$x1, self::AISLE_EAVE, $z1], [$x0, self::AISLE_EAVE, $z1]);
        $b->quad('glass', [$x0, self::AISLE_EAVE, $z0], [$x1, self::AISLE_EAVE, $z0], [$x1, self::AISLE_EAVE, self::NAVE_Z0], [$x0, self::AISLE_EAVE, self::NAVE_Z0]);
        $b->quad('glass', [$x0, self::AISLE_EAVE, self::NAVE_Z1], [$x1, self::AISLE_EAVE, self::NAVE_Z1], [$x1, self::AISLE_EAVE, $z1], [$x0, self::AISLE_EAVE, $z1]);
        $b->quad('glass', [$x0, self::AISLE_EAVE, self::NAVE_Z0], [$x1, self::AISLE_EAVE, self::NAVE_Z0], [$x1, self::NAVE_SPRING, self::NAVE_Z0], [$x0, self::NAVE_SPRING, self::NAVE_Z0]);
        $b->quad('glass', [$x0, self::AISLE_EAVE, self::NAVE_Z1], [$x1, self::AISLE_EAVE, self::NAVE_Z1], [$x1, self::NAVE_SPRING, self::NAVE_Z1], [$x0, self::NAVE_SPRING, self::NAVE_Z1]);

        // 4. Nave barrel vault (interrupted at the crossing) + transverse ribs.
        $trX0 = self::TR_CX - self::TR_HALF; $trX1 = self::TR_CX + self::TR_HALF;
        foreach ([[$x0, $trX0], [$trX1, $x1]] as [$s, $e]) {
            $this->vault($b, 'x', $s, $e, $cz, self::NAVE_SPRING, self::NAVE_R);
            for ($rx = $s; $rx <= $e + 0.01; $rx += self::BAY) {
                $this->rib($b, 'x', $rx, $cz, self::NAVE_SPRING, self::NAVE_R);
            }
        }

        // 5. Crossing transept: taller barrel across the full width + round-arched glass ends.
        $this->vault($b, 'z', $z0, $z1, self::TR_CX, self::TR_SPRING, self::TR_HALF);
        foreach ([$z0, $cz, $z1] as $rz) {
            $this->rib($b, 'z', $rz, self::TR_CX, self::TR_SPRING, self::TR_HALF);
        }
        // tympana (the iconic round windows) + the clerestory strip beneath them
        foreach ([$z0, $z1] as $zEnd) {
            $b->quad('glass', [$trX0, self::AISLE_EAVE, $zEnd], [$trX1, self::AISLE_EAVE, $zEnd], [$trX1, self::TR_SPRING, $zEnd], [$trX0, self::TR_SPRING, $zEnd]);
            $this->tympanum($b, $zEnd, self::TR_CX, self::TR_SPRING, self::TR_HALF);
        }

        // 6. Glazed end gables (X extremes): lower wall + nave clerestory + nave tympanum.
        foreach ([$x0, $x1] as $xEnd) {
            $b->quad('glass', [$xEnd, 0, $z0], [$xEnd, 0, $z1], [$xEnd, self::AISLE_EAVE, $z1], [$xEnd, self::AISLE_EAVE, $z0]);
            $b->quad('glass', [$xEnd, self::AISLE_EAVE, self::NAVE_Z0], [$xEnd, self::AISLE_EAVE, self::NAVE_Z1], [$xEnd, self::NAVE_SPRING, self::NAVE_Z1], [$xEnd, self::NAVE_SPRING, self::NAVE_Z0]);
            $this->tympanumX($b, $xEnd, $cz, self::NAVE_SPRING, self::NAVE_R);
        }

        return ['bytes' => $b->toGlb(), 'length' => self::L, 'width' => self::W];
    }

    private function column(GlbBuilder $b, float $x, float $z, float $top): void
    {
        $b->box('iron', [$x - 0.2, 0, $z - 0.2], [$x + 0.2, $top, $z + 0.2]);
        $b->box('girder', [$x - 0.32, $top - 0.3, $z - 0.32], [$x + 0.32, $top, $z + 0.32]); // capital
    }

    /** A point on a half-barrel: axis 'x' sweeps X (arc in Y-Z); axis 'z' sweeps Z (arc in X-Y). */
    private function arcPoint(string $axis, float $theta, float $sweep, float $perpC, float $spring, float $r, float $rOff = 0.0): array
    {
        $rr = $r + $rOff;
        $y = $spring + $rr * sin($theta);
        $h = $perpC + $rr * cos($theta);

        return $axis === 'x' ? [$sweep, $y, $h] : [$h, $y, $sweep];
    }

    /** Glazed half-barrel vault from sweep s0..s1. */
    private function vault(GlbBuilder $b, string $axis, float $s0, float $s1, float $perpC, float $spring, float $r): void
    {
        for ($i = 0; $i < self::ARC_SEG; $i++) {
            $ta = M_PI * $i / self::ARC_SEG;
            $tb = M_PI * ($i + 1) / self::ARC_SEG;
            $b->quad('glass',
                $this->arcPoint($axis, $ta, $s0, $perpC, $spring, $r),
                $this->arcPoint($axis, $tb, $s0, $perpC, $spring, $r),
                $this->arcPoint($axis, $tb, $s1, $perpC, $spring, $r),
                $this->arcPoint($axis, $ta, $s1, $perpC, $spring, $r)
            );
        }
    }

    /** A transverse arch rib at a sweep station (thin band just outside the glass). */
    private function rib(GlbBuilder $b, string $axis, float $station, float $perpC, float $spring, float $r): void
    {
        $w = 0.25;
        for ($i = 0; $i < self::ARC_SEG; $i++) {
            $ta = M_PI * $i / self::ARC_SEG;
            $tb = M_PI * ($i + 1) / self::ARC_SEG;
            $b->quad('girder',
                $this->arcPoint($axis, $ta, $station - $w, $perpC, $spring, $r, 0.1),
                $this->arcPoint($axis, $tb, $station - $w, $perpC, $spring, $r, 0.1),
                $this->arcPoint($axis, $tb, $station + $w, $perpC, $spring, $r, 0.1),
                $this->arcPoint($axis, $ta, $station + $w, $perpC, $spring, $r, 0.1)
            );
        }
    }

    /** Semicircular glass tympanum on a Z-facing transept end (half-disk fan in X-Y). */
    private function tympanum(GlbBuilder $b, float $z, float $cx, float $spring, float $r): void
    {
        $c = [$cx, $spring, $z];
        for ($i = 0; $i < self::ARC_SEG; $i++) {
            $ta = M_PI * $i / self::ARC_SEG;
            $tb = M_PI * ($i + 1) / self::ARC_SEG;
            $b->triangle('glass', $c,
                [$cx + $r * cos($ta), $spring + $r * sin($ta), $z],
                [$cx + $r * cos($tb), $spring + $r * sin($tb), $z]
            );
        }
    }

    /** Semicircular glass tympanum on an X-facing nave end (half-disk fan in Y-Z). */
    private function tympanumX(GlbBuilder $b, float $x, float $cz, float $spring, float $r): void
    {
        $c = [$x, $spring, $cz];
        for ($i = 0; $i < self::ARC_SEG; $i++) {
            $ta = M_PI * $i / self::ARC_SEG;
            $tb = M_PI * ($i + 1) / self::ARC_SEG;
            $b->triangle('glass', $c,
                [$x, $spring + $r * sin($ta), $cz + $r * cos($ta)],
                [$x, $spring + $r * sin($tb), $cz + $r * cos($tb)]
            );
        }
    }
}
