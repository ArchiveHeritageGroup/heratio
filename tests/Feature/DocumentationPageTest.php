<?php

/**
 * DocumentationPageTest - H07: the documentation page names the running release,
 * every guide link is pinned to that release's tag, and every linked file exists
 * in the repository so no link points at nothing.
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

class DocumentationPageTest extends TestCase
{
    public function test_every_guide_is_pinned_to_the_running_release_and_exists(): void
    {
        $version = json_decode((string) file_get_contents(base_path('version.json')), true)['version'];
        $html = $this->get(route('solutions.documentation'))->assertOk()
            ->assertSee('Heratio '.$version)
            ->assertSee('releases/tag/v'.$version, false)
            ->getContent();

        preg_match_all('#https://github\.com/ArchiveHeritageGroup/heratio/blob/([^/"]+)/([^"\#]+)#', $html, $m, PREG_SET_ORDER);
        $this->assertGreaterThanOrEqual(6, count($m), 'install, upgrade and security guides are linked');
        foreach ($m as [, $ref, $path]) {
            $this->assertSame('v'.$version, $ref, "$path is pinned to the release tag");
            $this->assertFileExists(base_path($path));
        }
        $this->assertStringContainsString('docs/upgrade.md', $html);
        $this->assertStringContainsString('SECURITY.md', $html);
    }

    public function test_the_tour_link_shows_only_on_the_demo_site(): void
    {
        config(['heratio.homepage_mode' => 'marketing']);
        $this->get(route('solutions.documentation'))->assertOk()->assertSee(route('solutions.tour'), false);

        config(['heratio.homepage_mode' => 'institutional']);
        $this->get(route('solutions.documentation'))->assertOk()->assertDontSee(route('solutions.tour'), false);
    }
}
