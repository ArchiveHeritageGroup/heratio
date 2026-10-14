<?php

/**
 * SearchIndexHintsTest - H05: representative query permutations of the same
 * listing do not compete in search indexes. Every permutation points at one
 * canonical address, and only the clean and paginated forms stay indexable.
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

use App\Http\Middleware\SearchIndexHints;
use Illuminate\Http\Request;
use Tests\TestCase;

class SearchIndexHintsTest extends TestCase
{
    private function hints(string $uri): array
    {
        return (new SearchIndexHints)->hints(Request::create('https://example.org'.$uri, 'GET'));
    }

    public function test_browse_permutations_share_one_canonical_and_only_the_clean_form_is_indexable(): void
    {
        $permutations = [
            '/glam/browse?sort=alphabetic',
            '/glam/browse?sort=alphabetic&dir=desc&view=card&limit=30',
            '/glam/browse?subject=990&place=734',
            '/glam/browse?topLevel=0&sf_culture=fr',
            '/glam/browse?displayMode=tree&repo=982',
        ];
        foreach ($permutations as $uri) {
            $h = $this->hints($uri);
            $this->assertSame('<https://example.org/glam/browse>; rel="canonical"', $h['Link'], $uri);
            $this->assertSame('noindex, follow', $h['X-Robots-Tag'] ?? null, $uri);
        }

        $clean = $this->hints('/glam/browse');
        $this->assertSame('<https://example.org/glam/browse>; rel="canonical"', $clean['Link']);
        $this->assertArrayNotHasKey('X-Robots-Tag', $clean);
    }

    public function test_pagination_stays_indexable_and_drops_the_language_switch(): void
    {
        $h = $this->hints('/actor/browse?page=3&sf_culture=zu');
        $this->assertSame('<https://example.org/actor/browse?page=3>; rel="canonical"', $h['Link']);
        $this->assertArrayNotHasKey('X-Robots-Tag', $h);

        $this->assertSame('<https://example.org/actor/browse>; rel="canonical"', $this->hints('/actor/browse?page=1')['Link']);
    }

    public function test_print_export_and_search_results_are_kept_out_of_the_index(): void
    {
        foreach (['/glam/print?topLevel=0', '/glam/exportCsv?subject=990', '/some-record/export/ead'] as $uri) {
            $this->assertSame('noindex, nofollow', $this->hints($uri)['X-Robots-Tag'], $uri);
        }
        $this->assertSame('noindex, follow', $this->hints('/search?query=mandela')['X-Robots-Tag']);
    }

    public function test_a_language_variant_of_a_record_points_at_the_record(): void
    {
        $this->assertSame('<https://example.org/some-record>; rel="canonical"', $this->hints('/some-record?sf_culture=zu')['Link']);
        $this->assertSame([], $this->hints('/some-record'));
        $this->assertSame([], $this->hints('/some-record?tab=history'));
    }

    public function test_the_headers_reach_the_response_and_robots_txt_disallows_print_and_export(): void
    {
        $this->get('/glam/browse?sort=alphabetic')->assertHeader('X-Robots-Tag', 'noindex, follow');
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /*/print')->assertSee('Disallow: /*/exportCsv')->assertSee('Disallow: /*/export/');
    }
}
