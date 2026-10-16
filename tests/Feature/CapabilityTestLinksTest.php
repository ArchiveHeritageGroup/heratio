<?php

/**
 * CapabilityTestLinksTest - HER-01: every capability-to-test link on
 * /solutions/capabilities names a real released capability and a test file that
 * exists, and links to it at the running release.
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

use App\Support\CapabilityMatrix;
use Tests\TestCase;

class CapabilityTestLinksTest extends TestCase
{
    public function test_every_test_link_names_a_released_capability_and_an_existing_file(): void
    {
        $released = array_column(array_filter(CapabilityMatrix::rows(), fn ($r) => $r[4] === CapabilityMatrix::RELEASED), 1);
        $this->assertNotEmpty(CapabilityMatrix::tests());
        foreach (CapabilityMatrix::tests() as $name => $file) {
            $this->assertContains($name, $released, "{$name} is a released capability");
            $this->assertFileExists(base_path($file));
        }
    }

    public function test_the_page_links_each_test_at_the_running_release(): void
    {
        $version = json_decode((string) file_get_contents(base_path('version.json')), true)['version'];
        $html = $this->get(route('solutions.capabilities'))->assertOk()->getContent();

        $this->assertSame(count(CapabilityMatrix::tests()), substr_count($html, '>Automated test</a>'));
        $this->assertStringContainsString('heratio/blob/v'.$version.'/tests/Feature/ArchaeologyMatrixTest.php', $html);
    }
}
