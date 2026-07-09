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

    /**
     * Refresh the pre-reset snapshot after an article edit so grouping, file
     * order, links and descriptions survive tonight's demo reset even on
     * non-protected articles. Best-effort: never let a snapshot failure break
     * the admin action.
     */
    private function snapshot(): void
    {
        try {
            (new ArticlePersistenceService())->capture();
        } catch (\Throwable $e) {
            // no-op: the scheduled 01:50 capture is the backstop.
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
            'related'     => \AhgArticles\Services\BlogLinkService::related($id),
            'allPosts'    => \AhgArticles\Services\BlogLinkService::allForPicker($id),
        ]);
    }

    /** heratio#1399 — add a bidirectional link to another article. */
    public function linksAdd(Request $request, int $id)
    {
        $this->guard();
        if (! $this->blog->find($id)) {
            abort(404);
        }
        $targetId = \AhgArticles\Services\BlogLinkService::resolveId((string) $request->input('target', ''));
        if (! $targetId) {
            return back()->with('error', 'Could not find that article — pick one from the list or paste its /articles/… URL.');
        }
        \AhgArticles\Services\BlogLinkService::add($id, $targetId, (string) $request->input('description', ''));
        $this->snapshot();

        return redirect()->route('admin.articles.edit', $id)->with('success', 'Link added.');
    }

    /** heratio#1399 — remove a link between two articles. */
    public function linksRemove(int $id, int $targetId)
    {
        $this->guard();
        if (! $this->blog->find($id)) {
            abort(404);
        }
        \AhgArticles\Services\BlogLinkService::remove($id, $targetId);
        $this->snapshot();

        return redirect()->route('admin.articles.edit', $id)->with('success', 'Link removed.');
    }

    /** heratio#1399 — persist a drag/arrow-reordered linked-articles order. */
    public function linksReorder(Request $request, int $id)
    {
        $this->guard();
        if (! $this->blog->find($id)) {
            abort(404);
        }
        $order = $request->input('order', []);
        if (is_string($order)) {
            $order = array_filter(array_map('trim', explode(',', $order)), fn ($v) => $v !== '');
        }
        \AhgArticles\Services\BlogLinkService::reorder($id, (array) $order);
        $this->snapshot();

        return redirect()->route('admin.articles.edit', $id)->with('success', 'Order saved.');
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

        $data = $request->validate([
            'kind'        => 'required|in:' . implode(',', $this->attachmentKindCodes()),
            'title'       => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'group_label' => 'nullable|string|max:150',
            'sort_order'  => 'nullable|integer|min:0',
            'file'        => $this->attachmentFileRule(true),
        ]);

        $this->blog->addAttachment($id, $request->file('file'), [
            'kind'        => $data['kind'],
            'title'       => $data['title'] ?? '',
            'description' => $data['description'] ?? null,
            'group_label' => $data['group_label'] ?? null,
            // No sort_order → the service appends the new file to the end.
            'created_by'  => Auth::id(),
        ]);
        $this->snapshot();

        return redirect()->route('admin.articles.edit', $id)
            ->with('success', __('Attachment uploaded.'));
    }

    /** Update an attachment's metadata + optionally replace its file. */
    public function updateAttachment(Request $request, int $id, int $attachmentId)
    {
        $this->guard();
        $att = $this->blog->findAttachment($attachmentId);
        if (! $att || (int) $att->blog_post_id !== $id) {
            abort(404);
        }

        $data = $request->validate([
            'kind'        => 'required|in:' . implode(',', $this->attachmentKindCodes()),
            'title'       => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'group_label' => 'nullable|string|max:150',
            'sort_order'  => 'nullable|integer|min:0',
            'file'        => $this->attachmentFileRule(false),
        ]);

        $this->blog->updateAttachment($attachmentId, [
            'kind'        => $data['kind'],
            'title'       => $data['title'] ?? '',
            'description' => $data['description'] ?? null,
            'group_label' => $data['group_label'] ?? null,
            'sort_order'  => $data['sort_order'] ?? 0,
        ], $request->file('file'));
        $this->snapshot();

        return redirect()->route('admin.articles.edit', $id)
            ->with('success', __('Attachment updated.'));
    }

    /**
     * Allowed attachment "kind" codes from the blog_attachment_kind taxonomy,
     * falling back to the legacy guide/template pair when the taxonomy is empty.
     */
    private function attachmentKindCodes(): array
    {
        $kinds = $this->attachmentKinds()->pluck('code')->all();

        return empty($kinds) ? ['guide', 'template'] : $kinds;
    }

    /**
     * File validation rule. Validates by the file's own extension against the
     * allow-list rather than content-sniffing (mimes:) - OOXML office files
     * (pptx/docx/xlsx) are zip containers that PHP fileinfo often reports as
     * application/zip or application/octet-stream, which made mimes: reject
     * perfectly valid uploads. No app-level size cap - the real ceiling is PHP
     * upload_max_filesize / nginx client_max_body_size.
     */
    private function attachmentFileRule(bool $required): array
    {
        $exts = BlogService::ATTACHMENT_EXTENSIONS;

        return [
            $required ? 'required' : 'nullable',
            'file',
            function (string $attr, $value, callable $fail) use ($exts) {
                $ext = strtolower($value->getClientOriginalExtension());
                if (! in_array($ext, $exts, true)) {
                    $fail(__('The file must be a file of type: :types.', [
                        'types' => implode(', ', $exts),
                    ]));
                }
            },
        ];
    }

    /** Persist a drag/arrow-reordered file order for an article's attachments. */
    public function attachmentsReorder(Request $request, int $id)
    {
        $this->guard();
        if (! $this->blog->find($id)) {
            abort(404);
        }
        $order = $request->input('order', []);
        if (is_string($order)) {
            $order = array_filter(array_map('trim', explode(',', $order)), fn ($v) => $v !== '');
        }
        $this->blog->reorderAttachments($id, (array) $order);
        $this->snapshot();

        return redirect()->route('admin.articles.edit', $id)->with('success', __('File order saved.'));
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
        $this->snapshot();

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
