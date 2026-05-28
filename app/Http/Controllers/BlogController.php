<?php

/**
 * BlogController - public demo-site blog / articles.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace App\Http\Controllers;

use App\Services\BlogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    private BlogService $blog;

    public function __construct()
    {
        $this->blog = new BlogService();
    }

    /** Public articles index: 3-per-row grid, paginated, group-filterable. */
    public function index(Request $request)
    {
        $group = $request->query('group');
        $groups = $this->blog->publishedGroups();
        // Ignore an unknown group filter rather than show an empty page.
        if ($group && ! in_array($group, $groups, true)) {
            $group = null;
        }

        return view('articles.index', [
            'articles'     => $this->blog->paginatePublished($group ?: null),
            'groups'       => $groups,
            'activeGroup'  => $group,
        ]);
    }

    /** A single published article. */
    public function show(string $slug)
    {
        $article = $this->blog->getPublishedBySlug($slug);
        if (! $article) {
            abort(404);
        }

        // Count one read per session per article; don't count admin previews.
        $isAdmin = auth()->check() && (auth()->user()->is_admin ?? false);
        $seenKey = 'article_viewed_' . $article->id;
        if (! $isAdmin && ! session()->has($seenKey)) {
            $this->blog->incrementViews((int) $article->id);
            session()->put($seenKey, true);
            $article->view_count = (int) ($article->view_count ?? 0) + 1;
        }

        return view('articles.show', [
            'article'  => $article,
            'bodyHtml' => $article->body ? Str::markdown($article->body) : '',
        ]);
    }
}
