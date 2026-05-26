<?php

/**
 * RdaMappingTest - asserts the per-MIME RDA 336/337/338 mapping for the
 * MARC21 exporter (#663 Phase 2). The tests work against the live
 * ahg_marc_rda_mapping table when it is present; otherwise they fall back
 * to the in-code hard defaults (so CI without a database still exercises
 * the resolver).
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
 */

namespace AhgMetadataExport\Tests;

use AhgMetadataExport\Services\Rda\RdaCarrierMapper;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RdaMappingTest extends TestCase
{
    /**
     * Each MIME class lands on the expected 336 content type.
     * Skipped when ahg_marc_rda_mapping is missing (the resolver falls back
     * to a single "computer dataset" hard default in that case).
     */
    public function test_mime_maps_to_expected_336(): void
    {
        try {
            if (! Schema::hasTable('ahg_marc_rda_mapping')) {
                $this->markTestSkipped('ahg_marc_rda_mapping table not installed; hard-defaults only');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }

        $mapper = new RdaCarrierMapper();

        $cases = [
            'text/plain' => 'text',
            'text/html' => 'text',
            'application/pdf' => 'text',
            'image/jpeg' => 'still image',
            'image/tiff' => 'still image',
            'audio/wav' => 'spoken word',
            'audio/mpeg' => 'spoken word',
            'video/mp4' => 'two-dimensional moving image',
            'video/quicktime' => 'two-dimensional moving image',
            'model/gltf+json' => 'three-dimensional moving image',
            'application/x-3d-obj' => 'three-dimensional moving image',
            'application/json' => 'computer dataset',
        ];

        foreach ($cases as $mime => $expected336) {
            $triple = $mapper->mapByMime($mime);
            $this->assertSame(
                $expected336,
                $triple[336]['a'],
                "MIME {$mime} expected 336='{$expected336}', got '{$triple[336]['a']}'"
            );
            $this->assertSame('rdacontent', $triple[336]['2']);
            // 337 is "computer" for every served-from-Heratio digital object
            $this->assertSame('computer', $triple[337]['a']);
            // 338 is "online resource" for the digital path
            $this->assertSame('online resource', $triple[338]['a']);
        }
    }

    /**
     * Carrier-driven mapping (physical-object IOs) hits the right rows.
     */
    public function test_carrier_codes_map_to_physical_338(): void
    {
        try {
            if (! Schema::hasTable('ahg_marc_rda_mapping')) {
                $this->markTestSkipped('ahg_marc_rda_mapping table not installed');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }

        $mapper = new RdaCarrierMapper();

        $this->assertSame('volume', $mapper->mapByCarrier('volume')[338]['a']);
        $this->assertSame('sheet', $mapper->mapByCarrier('sheet')[338]['a']);
        $this->assertSame('audio disc', $mapper->mapByCarrier('audio-disc')[338]['a']);
        // Unknown carrier falls back to the safe physical default (volume)
        $this->assertSame('volume', $mapper->mapByCarrier('does-not-exist')[338]['a']);
    }

    /**
     * Hard defaults are returned when the lookup table is empty / absent.
     * Constructs a mapper against the live DB; if the table exists we
     * still get a sensible answer for an unknown MIME because the catch-all
     * '*' row is seeded.
     */
    public function test_unknown_mime_falls_back_to_catch_all(): void
    {
        $mapper = new RdaCarrierMapper();
        $triple = $mapper->mapByMime('chemical/x-pdb');
        $this->assertNotEmpty($triple[336]['a']);
        $this->assertNotEmpty($triple[337]['a']);
        $this->assertNotEmpty($triple[338]['a']);
    }
}
