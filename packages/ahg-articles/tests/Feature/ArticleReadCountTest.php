<?php

/**
 * ArticleReadCountTest - only real browser reads are counted.
 *
 * A page load used to count a read once per session. A crawler without
 * cookies gets a new session on every request, so a proxy-rotating scraper
 * added 2,600 "reads" to one article overnight. The article page now counts a
 * read itself, after 5 visible seconds, through POST /articles/{slug}/read.
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

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArticleReadCountTest extends TestCase
{
    use DatabaseTransactions;

    private int $id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->id = (int) DB::table('blog_post')->insertGetId([
            'slug' => 'zz-read-count-test', 'title' => 'ZZ Read count', 'body' => '<p>Body</p>',
            'status' => 'published', 'view_count' => 0, 'published_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function reads(): int
    {
        return (int) DB::table('blog_post')->where('id', $this->id)->value('view_count');
    }

    public function test_loading_the_page_counts_nothing_and_carries_the_beacon(): void
    {
        $this->get('/articles/zz-read-count-test')->assertOk()->assertSee('zz-read-count-test\\/read', false);
        $this->get('/articles/zz-read-count-test')->assertOk();

        $this->assertSame(0, $this->reads());
    }

    public function test_the_beacon_counts_one_read_per_session(): void
    {
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];
        $this->post('/articles/zz-read-count-test/read', [], $ajax)->assertNoContent();
        $this->post('/articles/zz-read-count-test/read', [], $ajax)->assertNoContent();

        $this->assertSame(1, $this->reads());
    }

    public function test_a_plain_post_without_the_page_script_does_not_count(): void
    {
        $this->post('/articles/zz-read-count-test/read')->assertNotFound();

        $this->assertSame(0, $this->reads());
    }
}
