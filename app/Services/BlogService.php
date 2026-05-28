<?php

/**
 * BlogService - demo-site blog / articles data access.
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

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogService
{
    /** Published posts (public site), newest first. */
    public function listPublished(?int $limit = null): array
    {
        $q = DB::table('blog_post')
            ->where('status', 'published')
            ->orderByRaw('COALESCE(published_at, created_at) DESC');

        if ($limit) {
            $q->limit($limit);
        }

        return $q->get()->all();
    }

    /**
     * Published posts grouped by article_group, preserving newest-first order
     * within each group. Ungrouped posts collect under "Articles". Returns
     * ['Group label' => [post, ...], ...].
     */
    public function listPublishedGrouped(): array
    {
        $grouped = [];
        foreach ($this->listPublished() as $post) {
            $key = trim((string) ($post->article_group ?? '')) ?: __('Articles');
            $grouped[$key][] = $post;
        }

        return $grouped;
    }

    /** Distinct group labels in use (for the admin datalist). */
    public function distinctGroups(): array
    {
        return DB::table('blog_post')
            ->whereNotNull('article_group')
            ->where('article_group', '!=', '')
            ->distinct()
            ->orderBy('article_group')
            ->pluck('article_group')
            ->all();
    }

    /** Distinct groups that have at least one published post (filter pills). */
    public function publishedGroups(): array
    {
        return DB::table('blog_post')
            ->where('status', 'published')
            ->whereNotNull('article_group')
            ->where('article_group', '!=', '')
            ->distinct()
            ->orderBy('article_group')
            ->pluck('article_group')
            ->all();
    }

    /**
     * Paginated published posts for the public index (3-per-row grid),
     * newest first, optionally filtered to a single group.
     */
    public function paginatePublished(?string $group = null, int $perPage = 9)
    {
        return DB::table('blog_post')
            ->where('status', 'published')
            ->when($group, fn ($q) => $q->where('article_group', $group))
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Every post (admin list), newest first. */
    public function listAll(): array
    {
        return DB::table('blog_post')
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    /** A published post by slug (public show). */
    public function getPublishedBySlug(string $slug): ?object
    {
        return DB::table('blog_post')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first() ?: null;
    }

    public function find(int $id): ?object
    {
        return DB::table('blog_post')->where('id', $id)->first() ?: null;
    }

    /** Increment the read/visit counter for a post. */
    public function incrementViews(int $id): void
    {
        DB::table('blog_post')->where('id', $id)->increment('view_count');
    }

    public function create(array $data): int
    {
        $now = now();
        $status = ($data['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

        return (int) DB::table('blog_post')->insertGetId([
            'slug'         => $this->uniqueSlug($data['slug'] ?? $data['title'] ?? 'post'),
            'title'        => $data['title'] ?? '',
            'excerpt'      => $data['excerpt'] ?? null,
            'body'         => $data['body'] ?? null,
            'cover_image'  => $data['cover_image'] ?? null,
            'author'       => $data['author'] ?? null,
            'article_group' => $data['article_group'] ?? null,
            'status'       => $status,
            'published_at' => $status === 'published'
                ? ($data['published_at'] ?? $now)
                : ($data['published_at'] ?? null),
            'created_by'   => $data['created_by'] ?? null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $row = ['updated_at' => now()];

        foreach (['title', 'excerpt', 'body', 'cover_image', 'author', 'article_group'] as $f) {
            if (array_key_exists($f, $data)) {
                $row[$f] = $data[$f];
            }
        }

        if (array_key_exists('slug', $data) && $data['slug'] !== '') {
            $row['slug'] = $this->uniqueSlug($data['slug'], $id);
        }

        if (array_key_exists('status', $data)) {
            $row['status'] = $data['status'] === 'published' ? 'published' : 'draft';
            // Stamp publish time the first time it goes live, unless one is supplied.
            if ($row['status'] === 'published') {
                $existing = $this->find($id);
                $row['published_at'] = $data['published_at']
                    ?? ($existing && $existing->published_at ? $existing->published_at : now());
            }
        } elseif (array_key_exists('published_at', $data)) {
            $row['published_at'] = $data['published_at'];
        }

        return DB::table('blog_post')->where('id', $id)->update($row) > 0;
    }

    public function delete(int $id): bool
    {
        return DB::table('blog_post')->where('id', $id)->delete() > 0;
    }

    /**
     * Store an uploaded image on the public disk under blog/ and return the
     * web path (/storage/blog/...). Caller validates mime/size.
     */
    public function storeImage(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $name = Str::random(24) . '.' . $ext;
        $file->storeAs('blog', $name, 'public');

        return Storage::disk('public')->url('blog/' . $name);
    }

    /** Slugify + de-duplicate against existing rows. */
    protected function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'post';
        $slug = $base;
        $i = 2;

        while (
            DB::table('blog_post')
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
