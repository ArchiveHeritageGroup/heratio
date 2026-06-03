<?php

/**
 * ImageMetadataPanelTest - Tests for the image EXIF / IPTC / XMP panel.
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
 * Issue #746 - Image show-page EXIF / IPTC / XMP metadata panel.
 *
 * The partial under test lives at
 *   packages/ahg-information-object-manage/resources/views/partials/_image-metadata-panel.blade.php
 *
 * Two scenarios are covered:
 *   1. A DO that has rows in digital_object_metadata + dam_iptc_metadata
 *      renders the panel with the expected EXIF and IPTC field values.
 *   2. A DO with no rows in any of the three sidecar tables renders
 *      an empty string (panel suppresses itself).
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ImageMetadataPanelTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Render the partial directly by namespaced view path so the test
     * exercises the same loadViewsFrom binding the production show page
     * goes through. Returns the rendered HTML.
     */
    private function renderPanel(int $doId): string
    {
        return View::make(
            'ahg-information-object-manage::partials._image-metadata-panel',
            ['do' => (object) ['id' => $doId]]
        )->render();
    }

    public function test_image_with_exif_and_iptc_renders_panel_with_fields(): void
    {
        // Use an id well clear of any seeded data. DatabaseTransactions will
        // roll the inserts back at the end of the test.
        $doId = 987654321;

        // The sidecar rows carry a FK to digital_object(id); this test only
        // needs the metadata rows to render the panel, not a real parent DO.
        // Disable FK checks for the fixture inserts (rolled back by the
        // DatabaseTransactions trait at end of test).
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('digital_object_metadata')->insert([
            'digital_object_id' => $doId,
            'file_type'         => 'image',
            'title'             => 'Sample EXIF Title 746',
            'creator'           => 'Test Photographer',
            'camera_make'       => 'Canon',
            'camera_model'      => 'EOS R5',
            'image_width'       => 6000,
            'image_height'      => 4000,
            'gps_latitude'      => -25.74787900,
            'gps_longitude'     =>  28.22929300,
            'date_created'      => '2026-01-15',
            'extraction_date'   => now(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        DB::table('dam_iptc_metadata')->insert([
            'object_id'        => $doId,
            'headline'         => 'IPTC Headline 746',
            'caption'          => 'A test caption for issue 746',
            'creator'          => 'Test Photographer',
            'credit_line'      => 'Plain Sailing / Heratio',
            'copyright_notice' => '(c) 2026 Test',
            'city'             => 'Pretoria',
            'country'          => 'South Africa',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        try {
            $html = $this->renderPanel($doId);

            $this->assertStringContainsString('Embedded Image Metadata', $html,
                'Panel header must render when sidecar data exists.');

            // EXIF fields surfaced.
            $this->assertStringContainsString('Sample EXIF Title 746', $html);
            $this->assertStringContainsString('Canon', $html);
            $this->assertStringContainsString('EOS R5', $html);
            $this->assertStringContainsString('6000 px', $html);
            $this->assertStringContainsString('4000 px', $html);

            // IPTC fields surfaced.
            $this->assertStringContainsString('IPTC Headline 746', $html);
            $this->assertStringContainsString('Plain Sailing / Heratio', $html);
            $this->assertStringContainsString('Pretoria', $html);
            $this->assertStringContainsString('South Africa', $html);

            // GPS section renders with formatted coords + OSM link.
            $this->assertStringContainsString('-25.747879', $html);
            $this->assertStringContainsString('28.229293', $html);
            $this->assertStringContainsString('openstreetmap.org', $html);
            $this->assertStringContainsString('google.com/maps', $html);

            // Three section labels.
            $this->assertStringContainsString('>EXIF', $html);
            $this->assertStringContainsString('>IPTC', $html);
        } finally {
            DB::table('dam_iptc_metadata')->where('object_id', $doId)->delete();
            DB::table('digital_object_metadata')->where('digital_object_id', $doId)->delete();
        }
    }

    public function test_image_with_no_embedded_metadata_renders_nothing(): void
    {
        $doId = 987654322;

        // Sanity: confirm the three sidecar tables really are empty for
        // this DO id before rendering.
        $this->assertSame(0, DB::table('digital_object_metadata')
            ->where('digital_object_id', $doId)->count());
        $this->assertSame(0, DB::table('dam_iptc_metadata')
            ->where('object_id', $doId)->count());
        $this->assertSame(0, DB::table('media_metadata')
            ->where('digital_object_id', $doId)->count());

        $html = trim($this->renderPanel($doId));

        $this->assertSame('', $html,
            'Panel must suppress itself when all three sidecar tables are empty.');
    }
}
