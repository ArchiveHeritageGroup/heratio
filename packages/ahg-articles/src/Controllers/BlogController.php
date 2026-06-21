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

        return view('articles::index', [
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

        return view('articles::show', [
            'article'     => $article,
            'bodyHtml'    => $article->body ? Str::markdown($article->body) : '',
            'comments'    => $this->blog->listApprovedComments((int) $article->id),
            'attachments' => $this->blog->listAttachments((int) $article->id),
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
