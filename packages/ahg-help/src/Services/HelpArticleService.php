<?php

/**
 * HelpArticleService - Service for Heratio
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

namespace AhgHelp\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HelpArticleService
{
    public const ADMIN_CATEGORIES = ['Technical', 'Plugin Reference'];

    /**
     * Ensure the article↔article link table exists (ahg-help ships via
     * install.sql, so create idempotently for already-installed instances).
     */
    public static function ensureLinkTable(): void
    {
        if (Schema::hasTable('help_article_link')) {
            return;
        }
        Schema::create('help_article_link', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('article_id');
            $t->unsignedBigInteger('related_article_id');
            $t->timestamp('created_at')->nullable()->useCurrent();
            $t->unique(['article_id', 'related_article_id'], 'uq_help_link');
            $t->index('related_article_id', 'idx_help_link_related');
        });
    }

    /**
     * Rebuild an article's OUTGOING cross-links by parsing `/help/article/{slug}`
     * references out of its rendered HTML. Links are stored one-directional but
     * surfaced BOTH ways by relatedArticles(), so authoring a link in one
     * article makes it appear as "Related" on both.
     */
    public static function rebuildLinks(int $articleId, string $bodyHtml): int
    {
        self::ensureLinkTable();

        preg_match_all('~/help/article/([a-z0-9][a-z0-9\-]*)~i', $bodyHtml, $m);
        $slugs = array_values(array_unique($m[1] ?? []));

        DB::table('help_article_link')->where('article_id', $articleId)->delete();

        $written = 0;
        foreach ($slugs as $slug) {
            $targetId = (int) DB::table('help_article')->where('slug', $slug)->value('id');
            if ($targetId > 0 && $targetId !== $articleId) {
                DB::table('help_article_link')->updateOrInsert(
                    ['article_id' => $articleId, 'related_article_id' => $targetId],
                    ['created_at' => now()]
                );
                $written++;
            }
        }

        return $written;
    }

    /**
     * Articles linked to $articleId in EITHER direction (bidirectional).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function relatedArticles(int $articleId): array
    {
        if (! Schema::hasTable('help_article_link')) {
            return [];
        }

        $ids = DB::table('help_article_link')->where('article_id', $articleId)->pluck('related_article_id')
            ->merge(DB::table('help_article_link')->where('related_article_id', $articleId)->pluck('article_id'))
            ->map(fn ($v) => (int) $v)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $query = DB::table('help_article')
            ->whereIn('id', $ids)
            ->where('is_published', 1)
            ->select('slug', 'title', 'category')
            ->orderBy('title');
        self::applyAdminFilter($query);

        return $query->get()->map(fn ($r) => (array) $r)->all();
    }

    public static function isAdmin(): bool
    {
        return auth()->check(); // All logged-in users can see admin categories in Heratio
    }

    protected static function applyAdminFilter($query): void
    {
        if (! self::isAdmin()) {
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
        if (! self::isAdmin() && in_array($category, self::ADMIN_CATEGORIES)) {
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

    /**
     * URL-safe slug for a category name. Converts "Import/Export" → "import-export",
     * "Admin & Settings" → "admin-settings", etc. Avoids `%2F`-in-path issues that
     * break the {category} route placeholder in some web-server configurations.
     */
    public static function categorySlug(string $name): string
    {
        $s = strtolower(trim($name));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';

        return trim($s, '-');
    }

    /**
     * Reverse-resolve a category slug back to its display name by scanning known
     * categories. Falls back to the input verbatim if no match (preserves backwards
     * compatibility with already-encoded URLs).
     */
    public static function categoryFromSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        foreach (self::getCategories() as $row) {
            if (self::categorySlug($row['category']) === $slug) {
                return $row['category'];
            }
        }

        return $slug;
    }

    public static function search(string $query, int $limit = 20): array
    {
        $escaped = addslashes($query);

        $q = DB::table('help_article')
            ->where('is_published', 1)
            ->whereRaw('MATCH(title, body_text) AGAINST(? IN BOOLEAN MODE)', [$query.'*']);
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
            ->whereRaw('MATCH(hs.heading, hs.body_text) AGAINST(? IN BOOLEAN MODE)', [$query.'*']);

        if (! self::isAdmin()) {
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
        if (! $current) {
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

    /**
     * Resolve the most relevant help article for the current page (#1332).
     *
     * Matching order: an exact route-name override (config help-context.routes)
     * wins; otherwise the longest matching URL-path prefix (help-context.paths).
     * The resolved slug is validated against a real, published, visible article
     * (admin-only articles are filtered out for guests), so a stale map entry
     * yields null rather than a broken link.
     *
     * @return array{slug:string,title:string,url:string}|null
     */
    public static function contextualFor(?string $routeName, ?string $path): ?array
    {
        $slug = null;

        $byName = (array) config('help-context.routes', []);
        if ($routeName !== null && isset($byName[$routeName])) {
            $slug = $byName[$routeName];
        }

        if ($slug === null) {
            $normalized = '/'.ltrim((string) $path, '/');
            $bestLen = -1;
            foreach ((array) config('help-context.paths', []) as $prefix => $candidate) {
                $p = '/'.trim((string) $prefix, '/');
                if ($p === '/') {
                    continue;
                }
                if (str_starts_with($normalized, $p) && strlen($p) > $bestLen) {
                    $slug = $candidate;
                    $bestLen = strlen($p);
                }
            }
        }

        if ($slug === null) {
            return null;
        }

        $article = self::getBySlug((string) $slug);
        if ($article === null) {
            return null;
        }

        return [
            'slug'  => (string) $slug,
            'title' => (string) ($article['title'] ?? 'Help'),
            'url'   => route('help.article', ['slug' => $slug]),
        ];
    }
}
