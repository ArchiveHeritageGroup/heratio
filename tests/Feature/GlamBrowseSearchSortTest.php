<?php

/**
 * GlamBrowseSearchSortTest - a GLAM browse search ranks by relevance unless the
 * visitor chose a sort, and the sort menu says so (27 Nov 2026). The title/date
 * default listed the meaning-based matches of a semantic search alphabetically,
 * so the best match could be on page 10, and the menu had no Relevance entry.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use Tests\TestCase;

class GlamBrowseSearchSortTest extends TestCase
{
    public function test_a_search_defaults_to_relevance(): void
    {
        $this->get('/glam/browse?topLevel=0&query=funeral')->assertOk()->assertSee('Sort: Relevance', false);
    }

    public function test_a_chosen_sort_is_kept(): void
    {
        $this->get('/glam/browse?topLevel=0&query=funeral&sort=alphabetic')->assertOk()->assertSee('Sort: Title', false);
    }

    public function test_browsing_without_a_search_has_no_relevance_sort(): void
    {
        $this->get('/glam/browse?topLevel=0')->assertOk()->assertDontSee('Sort: Relevance', false);
    }
}
