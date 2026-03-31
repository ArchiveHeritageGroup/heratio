<?php

/**
 * HelpArticleService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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


use Illuminate\Support\Facades\DB;

class HelpArticleService
{
    public const ADMIN_CATEGORIES = ['Technical', 'Plugin Reference'];

    public static function isAdmin(): bool
    {
        return auth()->check(); // All logged-in users can see admin categories in Heratio
    }

    protected static function applyAdminFilter($query): void
    {
        if (!self::isAdmin()) {
            $query->whereNotIn('category', self::ADMIN_CATEGORIES);
        }
    }

    public static function getCategories(): array
    {
        $query = DB::table('help_article')->where('is_published', 1);
        self::applyAdminFilter($query);

        return $query->select('category', DB::raw('COUNT(*) as article_count'))
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public static function getBySlug(string $slug): ?array
    {
        $query = DB::table('help_article')
            ->where('slug', $slug)
            ->where('is_published', 1);
        self::applyAdminFilter($query);

        $row = $query->first();
        return $row ? (array) $row : null;
    }

    public static function getByCategory(string $category): array
    {
        if (!self::isAdmin() && in_array($category, self::ADMIN_CATEGORIES)) {
            return [];
        }

        return DB::table('help_article')
            ->where('category', $category)
            ->where('is_published', 1)
            ->select('id', 'slug', 'title', 'subcategory', 'word_count', 'related_plugin', 'tags', 'updated_at')
            ->orderBy('subcategory')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public static function search(string $query, int $limit = 20): array
    {
        $escaped = addslashes($query);

        $q = DB::table('help_article')
            ->where('is_published', 1)
            ->whereRaw('MATCH(title, body_text) AGAINST(? IN BOOLEAN MODE)', [$query . '*']);
        self::applyAdminFilter($q);

        return $q->select(
                'id', 'slug', 'title', 'category', 'subcategory', 'word_count',
                DB::raw("MATCH(title, body_text) AGAINST('{$escaped}*' IN BOOLEAN MODE) AS relevance"),
                DB::raw('SUBSTRING(body_text, 1, 300) AS snippet')
            )
            ->orderByDesc('relevance')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public static function searchSections(string $query, int $limit = 30): array
    {
        $escaped = addslashes($query);

        $q = DB::table('help_section as hs')
            ->join('help_article as ha', 'hs.article_id', '=', 'ha.id')
            ->where('ha.is_published', 1)
            ->whereRaw('MATCH(hs.heading, hs.body_text) AGAINST(? IN BOOLEAN MODE)', [$query . '*']);

        if (!self::isAdmin()) {
            $q->whereNotIn('ha.category', self::ADMIN_CATEGORIES);
        }

        return $q->select(
                'ha.slug', 'ha.title as article_title', 'ha.category',
                'hs.heading', 'hs.anchor', 'hs.level',
                DB::raw("MATCH(hs.heading, hs.body_text) AGAINST('{$escaped}*' IN BOOLEAN MODE) AS relevance"),
                DB::raw('SUBSTRING(hs.body_text, 1, 200) AS snippet')
            )
            ->orderByDesc('relevance')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public static function getAdjacentArticles(int $id, string $category): array
    {
        $current = DB::table('help_article')->where('id', $id)->first();
        if (!$current) {
            return ['prev' => null, 'next' => null];
        }

        $prev = DB::table('help_article')
            ->where('category', $category)
            ->where('is_published', 1)
            ->where(function ($q) use ($current) {
                $q->where('sort_order', '<', $current->sort_order)
                    ->orWhere(function ($q2) use ($current) {
                        $q2->where('sort_order', '=', $current->sort_order)
                            ->where('title', '<', $current->title);
                    });
            })
            ->select('slug', 'title')
            ->orderByDesc('sort_order')
            ->orderByDesc('title')
            ->first();

        $next = DB::table('help_article')
            ->where('category', $category)
            ->where('is_published', 1)
            ->where(function ($q) use ($current) {
                $q->where('sort_order', '>', $current->sort_order)
                    ->orWhere(function ($q2) use ($current) {
                        $q2->where('sort_order', '=', $current->sort_order)
                            ->where('title', '>', $current->title);
                    });
            })
            ->select('slug', 'title')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->first();

        return [
            'prev' => $prev ? (array) $prev : null,
            'next' => $next ? (array) $next : null,
        ];
    }

    public static function getRecentlyUpdated(int $limit = 5): array
    {
        $query = DB::table('help_article')->where('is_published', 1);
        self::applyAdminFilter($query);

        return $query->select('slug', 'title', 'category', 'subcategory', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }
}
