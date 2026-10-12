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

namespace AhgArticles\Controllers;

use App\Http\Controllers\Controller;
use AhgArticles\Services\BlogService;
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

        // Whitelisted sort + view mode (default newest-first tiles).
        $sort = $request->query('sort');
        if (! in_array($sort, ['latest', 'oldest', 'title', 'popular'], true)) {
            $sort = 'latest';
        }
        $view = $request->query('view') === 'list' ? 'list' : 'tile';

        return view('articles::index', [
            'articles'     => $this->blog->paginatePublished($group ?: null, 9, $sort),
            'groups'       => $groups,
            'activeGroup'  => $group,
            'activeSort'   => $sort,
            'activeView'   => $view,
        ]);
    }

    /**
     * Count a read: called by the article page after it has been visible for
     * a few seconds. Needs the page's CSRF token and session cookie, so a
     * crawler that does not run the page's script never counts. One read per
     * session per article; administrators' views are not counted.
     */
    public function read(\Illuminate\Http\Request $request, string $slug)
    {
        $article = $this->blog->getPublishedBySlug($slug);
        if (! $article || ! $request->ajax()) {
            return response()->noContent(404);
        }
        $isAdmin = auth()->check() && (auth()->user()->is_admin ?? false);
        $seenKey = 'article_viewed_'.$article->id;
        if (! $isAdmin && ! session()->has($seenKey)) {
            $this->blog->incrementViews((int) $article->id);
            session()->put($seenKey, true);
        }

        return response()->noContent();
    }

    /** A single published article. */
    public function show(string $slug)
    {
        $article = $this->blog->getPublishedBySlug($slug);
        if (! $article) {
            abort(404);
        }

        // Reads are counted by read(), which the page calls once it has been
        // visible in a real browser for a few seconds. Counting here counted
        // every crawler hit: a bot without cookies gets a new session each time.

        // Related articles (bidirectional links) - only surface published ones publicly.
        $related = array_values(array_filter(
            \AhgArticles\Services\BlogLinkService::related((int) $article->id),
            fn ($r) => ($r['status'] ?? '') === 'published'
        ));

        return view('articles::show', [
            'article'     => $article,
            'bodyHtml'    => $article->body ? Str::markdown($article->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) : '',
            'comments'    => $this->blog->listApprovedComments((int) $article->id),
            'attachments' => $this->blog->listAttachments((int) $article->id),
            'related'     => $related,
        ]);
    }

    /**
     * Anonymous, blog-style comment on a published article. No login required.
     * Guards: honeypot field, per-IP flood window, length cap (route also
     * carries a throttle middleware). Name is optional (blank = Anonymous).
     */
    public function comment(Request $request, string $slug)
    {
        $article = $this->blog->getPublishedBySlug($slug);
        if (! $article) {
            abort(404);
        }

        // Honeypot: bots fill the hidden "website" field; humans never see it.
        if (trim((string) $request->input('website', '')) !== '') {
            return redirect()->route('articles.show', $slug); // silently drop
        }

        $request->validate([
            'author_name' => ['nullable', 'string', 'max:150'],
            'body'        => ['required', 'string', 'min:2', 'max:4000'],
        ]);

        // Simple per-IP flood guard (in addition to the route throttle).
        $since = $this->blog->secondsSinceLastCommentFromIp($request->ip());
        if ($since !== null && $since < 20) {
            return back()
                ->withInput()
                ->with('comment_error', __('Please wait a few seconds before commenting again.'));
        }

        $this->blog->addComment(
            (int) $article->id,
            $request->only(['author_name', 'body']),
            $request->ip(),
            Str::limit((string) $request->userAgent(), 255, '')
        );

        return redirect()
            ->route('articles.show', $slug)
            ->withFragment('comments')
            ->with('comment_success', __('Thanks - your comment has been posted.'));
    }
}
