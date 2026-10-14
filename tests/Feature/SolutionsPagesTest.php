<?php

/**
 * SolutionsPagesTest - H03: the buyer pages render, unknown pages 404, the
 * catch-all slug route does not swallow /solutions, and the pages advertise
 * nothing that the 14 October 2026 inventory found unreleased.
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

use App\Http\Controllers\SolutionsController;
use Tests\TestCase;

class SolutionsPagesTest extends TestCase
{
    /** Found unreleased or preview on 2026-10-14; advertise only once H04 badges exist. */
    private const NOT_YET = ['EAC-CPF', 'LIDO', 'Spectrum', 'GraphQL', 'semantic search', 'OPAC', 'SSO is provided', 'ArchivesSpace', 'Vernon', 'PastPerfect', 'CollectiveAccess'];

    public function test_the_index_lists_every_page(): void
    {
        $res = $this->get('/solutions')->assertOk();
        foreach (SolutionsController::PAGES as $slug => [$title]) {
            $res->assertSee(route('solutions.show', $slug), false)->assertSee($title);
        }
    }

    public function test_each_page_renders_and_claims_only_released_features(): void
    {
        foreach (SolutionsController::PAGES as $slug => [$title]) {
            $html = $this->get('/solutions/'.$slug)->assertOk()->assertSee('<title>'.$title.' - Heratio</title>', false)->getContent();
            // Only the page's own content: the site navigation has its own search options.
            $main = substr($html, (int) strpos($html, 'id="main-column"'));
            $main = substr($main, 0, (int) (strpos($main, '<footer') ?: strlen($main)));
            foreach (self::NOT_YET as $term) {
                $this->assertStringNotContainsStringIgnoringCase($term, strip_tags($main), "/solutions/$slug mentions '$term'");
            }
        }
    }

    public function test_an_unknown_page_is_a_404(): void
    {
        $this->get('/solutions/not-a-page')->assertNotFound();
    }
}
