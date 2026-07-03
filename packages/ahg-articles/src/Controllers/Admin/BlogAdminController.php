<?php

/**
 * BlogAdminController - manage demo-site blog / articles (admin only).
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

namespace AhgArticles\Controllers\Admin;

use App\Http\Controllers\Controller;
use AhgArticles\Services\ArticlePersistenceService;
use AhgArticles\Services\BlogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BlogAdminController extends Controller
{
    private BlogService $blog;

    public function __construct()
    {
        $this->blog = new BlogService();
    }

    /** Only site admins may manage articles. */
    private function guard(): void
    {
        $user = Auth::user();
        if (! $user || ! ($user->is_admin ?? false)) {
            abort(403);
        }
    }

    public function index()
    {
        $this->guard();

        return view('articles::admin.index', [
            'articles' => $this->blog->listAll(),
        ]);
    }

    public function create()
    {
        $this->guard();

        return view('articles::admin.form', [
            'article' => null,
            'groups'  => $this->blog->distinctGroups(),
            'attachmentKinds' => $this->attachmentKinds(),
        ]);
    }

    /**
     * Attachment "Type" options from the Dropdown Manager (taxonomy
     * blog_attachment_kind), so types are managed at /admin/dropdowns rather
     * than hardcoded. Empty collection falls back to guide/template in the view.
     */
    private function attachmentKinds()
    {
        return DB::table('ahg_dropdown')
            ->where('taxonomy', 'blog_attachment_kind')
            ->where('is_active', 1)
            ->orderBy('sort_order')->orderBy('label')
            ->get(['code', 'label', 'is_default']);
    }

    // ── Comment moderation ───────────────────────────────────────────────────

    public function comments()
    {
        $this->guard();

        return view('articles::admin.comments', [
            'comments' => $this->blog->listAllComments(),
        ]);
    }

    public function commentStatus(Request $request, int $id)
    {
        $this->guard();
        $request->validate(['status' => ['required', 'in:approved,pending,spam']]);
        $this->blog->setCommentStatus($id, $request->input('status'));

        return back()->with('success', __('Comment updated.'));
    }

    public function commentDestroy(int $id)
    {
        $this->guard();
        $this->blog->deleteComment($id);

        return back()->with('success', __('Comment deleted.'));
    }

    public function store(Request $request)
    {
        $this->guard();
        $data = $this->validateInput($request);
        $data['created_by'] = Auth::id();

        if ($request->hasFile('cover')) {
            $data['cover_image'] = $this->blog->storeImage($request->file('cover'));
        }

        $id = $this->blog->create($data);

        return redirect()->route('admin.articles.edit', $id)
            ->with('success', __('Article created.'));
    }

    public function edit(int $id)
    {
        $this->guard();
        $article = $this->blog->find($id);
        if (! $article) {
            abort(404);
        }

        return view('articles::admin.form', [
            'article'     => $article,
            'groups'      => $this->blog->distinctGroups(),
            'attachments' => $this->blog->listAttachments($id),
            'attachmentKinds' => $this->attachmentKinds(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $this->guard();
        if (! $this->blog->find($id)) {
            abort(404);
        }

        $data = $this->validateInput($request);

        if ($request->hasFile('cover')) {
            $data['cover_image'] = $this->blog->storeImage($request->file('cover'));
        }

        $this->blog->update($id, $data);

        return redirect()->route('admin.articles.edit', $id)
            ->with('success', __('Article saved.'));
    }

    public function destroy(int $id)
    {
        $this->guard();
        $this->blog->delete($id);
        // Keep the pre-reset snapshot current so a deleted article is not
        // resurrected by the next post-reset apply().
        (new ArticlePersistenceService())->capture();

        return redirect()->route('admin.articles.index')
            ->with('success', __('Article deleted.'));
    }

    /**
     * Toggle whether an article survives the nightly demo DB reset. Re-captures
     * the snapshot immediately so protection takes effect the same night, before
     * the scheduled pre-reset capture runs.
     */
    public function toggleProtect(int $id)
    {
        $this->guard();
        $article = $this->blog->find($id);
        if (! $article) {
            abort(404);
        }

        $on = ! (bool) ($article->protect_from_reset ?? false);
        $this->blog->setProtected($id, $on);
        (new ArticlePersistenceService())->capture();

        return redirect()->route('admin.articles.index')->with(
            'success',
            $on
                ? __('Article will be kept across the nightly reset.')
                : __('Article is no longer kept across the nightly reset.')
        );
    }

    /** Attach a guide/template file to an article (parent/child upload). */
    public function storeAttachment(Request $request, int $id)
    {
        $this->guard();
        if (! $this->blog->find($id)) {
            abort(404);
        }

        $rules = 'required|file|mimes:' . implode(',', BlogService::ATTACHMENT_EXTENSIONS)
            . '|max:' . BlogService::ATTACHMENT_MAX_KB;

        // Accept any active kind from the blog_attachment_kind taxonomy (managed
        // at /admin/dropdowns), mirroring the form's <select>. Falls back to the
        // legacy guide/template pair when the taxonomy has no active rows.
        $kinds = $this->attachmentKinds()->pluck('code')->all();
        if (empty($kinds)) {
            $kinds = ['guide', 'template'];
        }

        $data = $request->validate([
            'kind'        => 'required|in:' . implode(',', $kinds),
            'title'       => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer|min:0',
            'file'        => $rules,
        ]);

        $this->blog->addAttachment($id, $request->file('file'), [
            'kind'        => $data['kind'],
            'title'       => $data['title'] ?? '',
            'description' => $data['description'] ?? null,
            'sort_order'  => $data['sort_order'] ?? 0,
            'created_by'  => Auth::id(),
        ]);

        return redirect()->route('admin.articles.edit', $id)
            ->with('success', __('Attachment uploaded.'));
    }

    /** Remove a child attachment + its stored file. */
    public function destroyAttachment(int $id, int $attachmentId)
    {
        $this->guard();
        $att = $this->blog->findAttachment($attachmentId);
        if (! $att || (int) $att->blog_post_id !== $id) {
            abort(404);
        }
        $this->blog->deleteAttachment($attachmentId);

        return redirect()->route('admin.articles.edit', $id)
            ->with('success', __('Attachment removed.'));
    }

    /** AJAX inline-image upload; returns {url} for insertion into the body. */
    public function uploadImage(Request $request)
    {
        $this->guard();
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);

        return response()->json([
            'url' => $this->blog->storeImage($request->file('image')),
        ]);
    }

    private function validateInput(Request $request): array
    {
        return $request->validate([
            'title'         => 'required|string|max:255',
            'slug'          => 'nullable|string|max:200',
            'article_group' => 'nullable|string|max:100',
            'author'        => 'nullable|string|max:150',
            'excerpt'       => 'nullable|string|max:500',
            'body'          => 'nullable|string',
            'attachments_label' => 'nullable|string|max:255',
            'status'        => 'required|in:draft,published',
            'published_at'  => 'nullable|date',
            'cover'         => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);
    }
}
