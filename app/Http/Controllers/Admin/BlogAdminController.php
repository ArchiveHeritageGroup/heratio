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

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BlogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        return view('admin.articles.index', [
            'articles' => $this->blog->listAll(),
        ]);
    }

    public function create()
    {
        $this->guard();

        return view('admin.articles.form', [
            'article' => null,
            'groups'  => $this->blog->distinctGroups(),
        ]);
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

        return view('admin.articles.form', [
            'article' => $article,
            'groups'  => $this->blog->distinctGroups(),
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

        return redirect()->route('admin.articles.index')
            ->with('success', __('Article deleted.'));
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
            'status'        => 'required|in:draft,published',
            'published_at'  => 'nullable|date',
            'cover'         => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);
    }
}
