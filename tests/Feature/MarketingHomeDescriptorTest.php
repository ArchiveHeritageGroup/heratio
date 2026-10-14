<?php

/**
 * MarketingHomeDescriptorTest - H02: the marketing home page says what Heratio
 * is (category), who it is for (use cases) and whether it is real (status),
 * beside the headline and in the browser title.
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

namespace Tests\Feature;

use Tests\TestCase;

class MarketingHomeDescriptorTest extends TestCase
{
    public function test_the_marketing_home_states_category_use_and_release_status(): void
    {
        config(['heratio.homepage_mode' => 'marketing']);
        $version = json_decode((string) file_get_contents(base_path('version.json')), true)['version'];

        $this->get('/')->assertOk()
            ->assertSee('<title>Heratio - Open-source GLAM and archival management software</title>', false)
            ->assertSee('Open-source GLAM and archival management software')
            ->assertSee('archives, museums, libraries and galleries')
            ->assertSee('Released')
            ->assertSee('Version '.$version)
            ->assertSee('github.com/ArchiveHeritageGroup/heratio#installation', false);
    }

    public function test_h08_metadata_licence_contribution_and_one_call_to_action(): void
    {
        config(['heratio.homepage_mode' => 'marketing']);
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html);
        preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m);
        $ld = json_decode($m[1], true);
        $this->assertSame('SoftwareApplication', $ld['@type']);
        $this->assertSame('https://www.gnu.org/licenses/agpl-3.0.html', $ld['license']);
        $this->assertSame(json_decode((string) file_get_contents(base_path('version.json')), true)['version'], $ld['softwareVersion']);

        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertStringNotContainsString("world's first", $html);
        $this->assertStringContainsString('CONTRIBUTING.md', $html);
        $this->assertStringContainsString('blob/main/LICENSE', $html);

        $this->assertSame(1, substr_count($html, '>Try the live demo</a>'), 'one primary call to action');
    }
}
