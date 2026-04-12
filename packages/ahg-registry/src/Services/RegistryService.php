<?php

/**
 * RegistryService - Service for Heratio
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



namespace AhgRegistry\Services;

use Illuminate\Support\Facades\DB;

class RegistryService
{
    /* ------------------------------------------------------------------ */
    /*  Dashboard / Index stats                                           */
    /* ------------------------------------------------------------------ */

    public function getStats(): array
    {
        return [
            'institutions' => DB::table('registry_institution')->count(),
            'vendors'      => DB::table('registry_vendor')->count(),
            'software'     => DB::table('registry_software')->count(),
            'standards'    => DB::table('registry_standard')->count(),
            'groups'       => DB::table('registry_group')->count(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Institutions                                                      */
    /* ------------------------------------------------------------------ */

    public function browseInstitutions(array $filters, int $page = 1, int $limit = 20): array
    {
        $q = DB::table('registry_institution');
        if (!empty($filters['q'])) {
            $q->where('name', 'like', '%' . $filters['q'] . '%');
        }
        if (!empty($filters['country'])) {
            $q->where('country', $filters['country']);
        }
        if (!empty($filters['type'])) {
            $q->where('institution_type', $filters['type']);
        }
        $total = $q->count();
        $items = $q->orderBy('name')->offset(($page - 1) * $limit)->limit($limit)->get();
        return ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    public function getInstitution(int $id): ?object
    {
        return DB::table('registry_institution')->where('id', $id)->first();
    }

    /* ------------------------------------------------------------------ */
    /*  Vendors                                                           */
    /* ------------------------------------------------------------------ */

    public function browseVendors(array $filters, int $page = 1, int $limit = 20): array
    {
        $q = DB::table('registry_vendor');
        if (!empty($filters['q'])) {
            $q->where('name', 'like', '%' . $filters['q'] . '%');
        }
        $total = $q->count();
        $items = $q->orderBy('name')->offset(($page - 1) * $limit)->limit($limit)->get();
        return ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    public function getVendor(int $id): ?object
    {
        return DB::table('registry_vendor')->where('id', $id)->first();
    }

    /* ------------------------------------------------------------------ */
    /*  Software                                                          */
    /* ------------------------------------------------------------------ */

    public function browseSoftware(array $filters, int $page = 1, int $limit = 20): array
    {
        $q = DB::table('registry_software');
        if (!empty($filters['q'])) {
            $q->where('name', 'like', '%' . $filters['q'] . '%');
        }
        $total = $q->count();
        $items = $q->orderBy('name')->offset(($page - 1) * $limit)->limit($limit)->get();
        return ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    public function getSoftware(int $id): ?object
    {
        return DB::table('registry_software')->where('id', $id)->first();
    }

    /* ------------------------------------------------------------------ */
    /*  Standards                                                         */
    /* ------------------------------------------------------------------ */

    public function browseStandards(array $filters, int $page = 1, int $limit = 20): array
    {
        $q = DB::table('registry_standard');
        if (!empty($filters['q'])) {
            $q->where('name', 'like', '%' . $filters['q'] . '%');
        }
        $total = $q->count();
        $items = $q->orderBy('name')->offset(($page - 1) * $limit)->limit($limit)->get();
        return ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    /* ------------------------------------------------------------------ */
    /*  Groups                                                            */
    /* ------------------------------------------------------------------ */

    public function browseGroups(array $filters, int $page = 1, int $limit = 20): array
    {
        $q = DB::table('registry_group');
        if (!empty($filters['q'])) {
            $q->where('name', 'like', '%' . $filters['q'] . '%');
        }
        $total = $q->count();
        $items = $q->orderBy('name')->offset(($page - 1) * $limit)->limit($limit)->get();
        return ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    /* ------------------------------------------------------------------ */
    /*  Blog                                                              */
    /* ------------------------------------------------------------------ */

    public function browseBlog(array $filters, int $page = 1, int $limit = 20): array
    {
        $q = DB::table('registry_blog_post')->whereNotNull('published_at');
        if (!empty($filters['q'])) {
            $q->where('title', 'like', '%' . $filters['q'] . '%');
        }
        if (!empty($filters['category'])) {
            $q->where('category', $filters['category']);
        }
        $total = $q->count();
        $items = $q->orderByDesc('published_at')->offset(($page - 1) * $limit)->limit($limit)->get();
        return ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    /* ------------------------------------------------------------------ */
    /*  Discussions                                                       */
    /* ------------------------------------------------------------------ */

    public function browseDiscussions(array $filters, int $page = 1, int $limit = 20): array
    {
        $q = DB::table('registry_discussion');
        if (!empty($filters['q'])) {
            $q->where('title', 'like', '%' . $filters['q'] . '%');
        }
        $total = $q->count();
        $items = $q->orderByDesc('created_at')->offset(($page - 1) * $limit)->limit($limit)->get();
        return ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    /* ------------------------------------------------------------------ */
    /*  Newsletters                                                       */
    /* ------------------------------------------------------------------ */

    public function browseNewsletters(int $page = 1, int $limit = 20): array
    {
        $q = DB::table('registry_newsletter');
        $total = $q->count();
        $items = $q->orderByDesc('created_at')->offset(($page - 1) * $limit)->limit($limit)->get();
        return ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    /* ------------------------------------------------------------------ */
    /*  ERD                                                               */
    /* ------------------------------------------------------------------ */

    public function browseErd(int $page = 1, int $limit = 20): array
    {
        $q = DB::table('registry_erd');
        $total = $q->count();
        // Real column is `display_name`; prior code assumed `name`.
        $items = $q->orderBy('display_name')->offset(($page - 1) * $limit)->limit($limit)->get();
        return ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    /* ------------------------------------------------------------------ */
    /*  Setup Guides                                                      */
    /* ------------------------------------------------------------------ */

    public function browseSetupGuides(int $page = 1, int $limit = 20): array
    {
        $q = DB::table('registry_setup_guide');
        $total = $q->count();
        $items = $q->orderBy('title')->offset(($page - 1) * $limit)->limit($limit)->get();
        return ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    /* ------------------------------------------------------------------ */
    /*  Featured Items (for index page)                                   */
    /* ------------------------------------------------------------------ */

    public function getFeaturedInstitutions(int $limit = 6): \Illuminate\Support\Collection
    {
        return DB::table('registry_institution')
            ->where('featured', 1)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    public function getFeaturedVendors(int $limit = 6): \Illuminate\Support\Collection
    {
        return DB::table('registry_vendor')
            ->where('featured', 1)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    public function getFeaturedSoftware(int $limit = 6): \Illuminate\Support\Collection
    {
        return DB::table('registry_software')
            ->where('featured', 1)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    public function getRecentBlogPosts(int $limit = 5): \Illuminate\Support\Collection
    {
        return DB::table('registry_blog_post')
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    public function getRecentDiscussions(int $limit = 5): \Illuminate\Support\Collection
    {
        return DB::table('registry_discussion')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
