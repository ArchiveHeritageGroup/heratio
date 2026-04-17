<?php

/**
 * RegistryService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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

    /* ------------------------------------------------------------------ */
    /*  Admin browse helpers                                              */
    /* ------------------------------------------------------------------ */

    public function adminBrowseInstitutions(?string $q = null, int $page = 1, int $limit = 50): array
    {
        if (!\Schema::hasTable('registry_institution')) {
            return ['items' => collect(), 'total' => 0, 'page' => $page];
        }
        $query = DB::table('registry_institution');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('country', 'like', "%{$q}%")
                  ->orWhere('slug', 'like', "%{$q}%");
            });
        }
        $total = (int) (clone $query)->count();
        $items = $query->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();
        return ['items' => $items, 'total' => $total, 'page' => $page];
    }

    public function adminBrowseVendors(?string $q = null, int $page = 1, int $limit = 50): array
    {
        if (!\Schema::hasTable('registry_vendor')) {
            return ['items' => collect(), 'total' => 0, 'page' => $page];
        }
        $query = DB::table('registry_vendor');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('slug', 'like', "%{$q}%");
            });
        }
        $total = (int) (clone $query)->count();
        $items = $query->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();
        return ['items' => $items, 'total' => $total, 'page' => $page];
    }

    public function adminBrowseSoftware(?string $q = null, int $page = 1, int $limit = 50): array
    {
        if (!\Schema::hasTable('registry_software')) {
            return ['items' => collect(), 'total' => 0, 'page' => $page];
        }
        $query = DB::table('registry_software as s')
            ->leftJoin('registry_vendor as v', 's.vendor_id', '=', 'v.id')
            ->select('s.*', 'v.name as vendor_name');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('s.name', 'like', "%{$q}%")
                  ->orWhere('s.slug', 'like', "%{$q}%");
            });
        }
        $total = (int) (clone $query)->count();
        $items = $query->orderByDesc('s.created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();
        return ['items' => $items, 'total' => $total, 'page' => $page];
    }

    public function adminBrowseStandards(?string $q = null, int $page = 1, int $limit = 50): array
    {
        if (!\Schema::hasTable('registry_standard')) {
            return ['items' => collect(), 'total' => 0, 'page' => $page];
        }
        $query = DB::table('registry_standard');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('code', 'like', "%{$q}%");
            });
        }
        $total = (int) (clone $query)->count();
        $items = $query->orderBy('name')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();
        return ['items' => $items, 'total' => $total, 'page' => $page];
    }

    public function adminBrowseGroups(?string $q = null, int $page = 1, int $limit = 50): array
    {
        if (!\Schema::hasTable('registry_user_group')) {
            return ['items' => collect(), 'total' => 0, 'page' => $page];
        }
        $query = DB::table('registry_user_group as g')
            ->leftJoin(DB::raw('(SELECT group_id, COUNT(*) as mc FROM registry_user_group_member GROUP BY group_id) as m'), 'm.group_id', '=', 'g.id')
            ->select('g.*', DB::raw('COALESCE(m.mc, 0) as member_count'));
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('g.name', 'like', "%{$q}%")
                  ->orWhere('g.slug', 'like', "%{$q}%");
            });
        }
        $total = (int) (clone $query)->count();
        $items = $query->orderByDesc('g.created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();
        return ['items' => $items, 'total' => $total, 'page' => $page];
    }

    public function adminBrowseBlog(?string $q = null, ?string $status = null, int $page = 1, int $limit = 50): array
    {
        if (!\Schema::hasTable('registry_blog_post')) {
            return ['items' => collect(), 'total' => 0, 'page' => $page];
        }
        $query = DB::table('registry_blog_post as b')
            ->leftJoin('user as u', 'b.author_id', '=', 'u.id')
            ->select('b.*', 'u.username as author_name');
        if ($q) {
            $query->where('b.title', 'like', "%{$q}%");
        }
        if ($status) {
            $query->where('b.status', $status);
        }
        $total = (int) (clone $query)->count();
        $items = $query->orderByDesc('b.created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();
        return ['items' => $items, 'total' => $total, 'page' => $page];
    }

    public function adminBrowseDiscussions(?string $q = null, int $page = 1, int $limit = 50): array
    {
        if (!\Schema::hasTable('registry_discussion')) {
            return ['items' => collect(), 'total' => 0, 'page' => $page];
        }
        // Real schema has author_user_id + author_name on the discussion row itself.
        $query = DB::table('registry_discussion');
        if ($q) {
            $query->where('title', 'like', "%{$q}%");
        }
        $total = (int) (clone $query)->count();
        $items = $query->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();
        return ['items' => $items, 'total' => $total, 'page' => $page];
    }

    public function adminBrowseDropdowns(?string $q = null, int $page = 1, int $limit = 100): array
    {
        if (!\Schema::hasTable('registry_dropdown')) {
            return ['items' => collect(), 'total' => 0, 'page' => $page];
        }
        // Real schema uses dropdown_group + value (not taxonomy + code).
        $query = DB::table('registry_dropdown');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('dropdown_group', 'like', "%{$q}%")
                  ->orWhere('label', 'like', "%{$q}%")
                  ->orWhere('value', 'like', "%{$q}%");
            });
        }
        $total = (int) (clone $query)->count();
        $items = $query->orderBy('dropdown_group')
            ->orderBy('sort_order')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();
        return ['items' => $items, 'total' => $total, 'page' => $page];
    }

    /* ------------------------------------------------------------------ */
    /*  Edit-form helpers for batch 6                                     */
    /* ------------------------------------------------------------------ */

    public function getGroup(int $id): ?object
    {
        return \Schema::hasTable('registry_user_group')
            ? DB::table('registry_user_group')->where('id', $id)->first()
            : null;
    }

    public function getGroupMembers(int $id): \Illuminate\Support\Collection
    {
        if (!\Schema::hasTable('registry_user_group_member')) return collect();
        return DB::table('registry_user_group_member as m')
            ->leftJoin('user as u', 'm.user_id', '=', 'u.id')
            ->where('m.group_id', $id)
            ->select('u.id', 'u.username', 'u.email', 'm.role', 'm.created_at')
            ->orderBy('u.username')
            ->get();
    }

    public function getStandard(int $id): ?object
    {
        return \Schema::hasTable('registry_standard')
            ? DB::table('registry_standard')->where('id', $id)->first()
            : null;
    }

    public function getDropdown(int $id): ?object
    {
        return \Schema::hasTable('registry_dropdown')
            ? DB::table('registry_dropdown')->where('id', $id)->first()
            : null;
    }

    public function getNewsletter(?int $id): ?object
    {
        if (!$id || !\Schema::hasTable('registry_newsletter')) return null;
        return DB::table('registry_newsletter')->where('id', $id)->first();
    }

    /* ------------------------------------------------------------------ */
    /*  Extra admin helpers for batches 4+5                               */
    /* ------------------------------------------------------------------ */

    public function adminBrowseErd(?string $q = null): \Illuminate\Support\Collection
    {
        if (!\Schema::hasTable('registry_erd')) return collect();
        $query = DB::table('registry_erd');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('display_name', 'like', "%{$q}%")
                  ->orWhere('plugin_name', 'like', "%{$q}%")
                  ->orWhere('category', 'like', "%{$q}%");
            });
        }
        return $query->orderBy('sort_order')->orderBy('display_name')->get();
    }

    public function getErd(int $id): ?object
    {
        return \Schema::hasTable('registry_erd')
            ? DB::table('registry_erd')->where('id', $id)->first()
            : null;
    }

    public function adminBrowseSetupGuides(): \Illuminate\Support\Collection
    {
        if (!\Schema::hasTable('registry_setup_guide')) return collect();
        return DB::table('registry_setup_guide')->orderBy('title')->get();
    }

    public function adminBrowseSubscribers(?string $q = null, int $page = 1, int $limit = 100): array
    {
        if (!\Schema::hasTable('registry_newsletter_subscriber')) {
            return ['items' => collect(), 'total' => 0, 'page' => $page];
        }
        $query = DB::table('registry_newsletter_subscriber');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('email', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }
        $total = (int) (clone $query)->count();
        $items = $query->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();
        return ['items' => $items, 'total' => $total, 'page' => $page];
    }

    public function getSetting(string $key, $default = null)
    {
        if (!\Schema::hasTable('registry_settings')) return $default;
        $row = DB::table('registry_settings')->where('setting_key', $key)->first();
        return $row ? $row->setting_value : $default;
    }

    public function getAllSettings(): \Illuminate\Support\Collection
    {
        // Real schema has no setting_group column — sort by key only.
        return \Schema::hasTable('registry_settings')
            ? DB::table('registry_settings')->orderBy('setting_key')->get()
            : collect();
    }

    public function getSyncLogs(int $limit = 100): \Illuminate\Support\Collection
    {
        return \Schema::hasTable('registry_sync_log')
            ? DB::table('registry_sync_log')->orderByDesc('created_at')->limit($limit)->get()
            : collect();
    }

    public function getPendingUsers(int $limit = 100): \Illuminate\Support\Collection
    {
        if (!\Schema::hasTable('user')) {
            return collect();
        }
        // AtoM `user` has no timestamps — they live on `object` (CTI parent).
        return DB::table('user as u')
            ->leftJoin('object as o', 'o.id', '=', 'u.id')
            ->where('u.active', 0)
            ->orderByDesc('o.created_at')
            ->limit($limit)
            ->select('u.id', 'u.username', 'u.email', 'o.created_at')
            ->get();
    }

    public function getActiveUsers(int $limit = 100): \Illuminate\Support\Collection
    {
        if (!\Schema::hasTable('user')) {
            return collect();
        }
        return DB::table('user as u')
            ->leftJoin('object as o', 'o.id', '=', 'u.id')
            ->where('u.active', 1)
            ->orderByDesc('o.updated_at')
            ->limit($limit)
            ->select('u.id', 'u.username', 'u.email', 'o.updated_at as last_login_at')
            ->get();
    }

    public function getRegistryUser(int $id): ?object
    {
        return \Schema::hasTable('user')
            ? DB::table('user')->where('id', $id)->first()
            : null;
    }

    public function getInstitutionUsers(int $institutionId): \Illuminate\Support\Collection
    {
        if (!\Schema::hasTable('registry_user_institution')) {
            return collect();
        }
        return DB::table('registry_user_institution as ui')
            ->join('user as u', 'ui.user_id', '=', 'u.id')
            ->where('ui.institution_id', $institutionId)
            ->select('u.id', 'u.username', 'u.email', 'ui.role', 'ui.created_at')
            ->orderBy('u.username')
            ->get();
    }

    public function adminBrowseReviews(?string $q = null, ?string $status = null, int $page = 1, int $limit = 50): array
    {
        if (!\Schema::hasTable('registry_review')) {
            return ['items' => collect(), 'total' => 0, 'page' => $page];
        }
        $query = DB::table('registry_review');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                  ->orWhere('comment', 'like', "%{$q}%");
            });
        }
        if ($status) {
            $query->where('status', $status);
        }
        $total = (int) (clone $query)->count();
        $items = $query->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();
        return ['items' => $items, 'total' => $total, 'page' => $page];
    }

    public function adminBrowseNewsletters(?string $q = null, int $page = 1, int $limit = 50): array
    {
        if (!\Schema::hasTable('registry_newsletter')) {
            return ['items' => collect(), 'total' => 0, 'page' => $page];
        }
        $query = DB::table('registry_newsletter');
        if ($q) {
            $query->where('subject', 'like', "%{$q}%");
        }
        $total = (int) (clone $query)->count();
        $items = $query->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();
        return ['items' => $items, 'total' => $total, 'page' => $page];
    }

    /* ------------------------------------------------------------------ */
    /*  Admin stats                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Aggregate counts for the admin dashboard cards.
     * Cloned from PSIS adminDashboardSuccess.php — returns the same keys
     * used by the view.
     */
    public function getAdminStats(): array
    {
        $stats = [
            'institutions'         => 0,
            'institutions_pending' => 0,
            'vendors'              => 0,
            'vendors_pending'      => 0,
            'software'             => 0,
            'instances'            => 0,
            'instances_online'     => 0,
            'groups'               => 0,
            'discussions'          => 0,
            'blog_posts'           => 0,
            'blog_pending'         => 0,
            'reviews'              => 0,
            'users_pending'        => 0,
        ];

        try {
            if (\Schema::hasTable('registry_institution')) {
                $stats['institutions'] = (int) DB::table('registry_institution')->count();
                $stats['institutions_pending'] = (int) DB::table('registry_institution')
                    ->where('is_verified', 0)->count();
            }
            if (\Schema::hasTable('registry_vendor')) {
                $stats['vendors'] = (int) DB::table('registry_vendor')->count();
                $stats['vendors_pending'] = (int) DB::table('registry_vendor')
                    ->where('is_verified', 0)->count();
            }
            if (\Schema::hasTable('registry_software')) {
                $stats['software'] = (int) DB::table('registry_software')->count();
            }
            if (\Schema::hasTable('registry_instance')) {
                $stats['instances'] = (int) DB::table('registry_instance')->count();
                $stats['instances_online'] = (int) DB::table('registry_instance')
                    ->where('status', 'online')->count();
            }
            if (\Schema::hasTable('registry_user_group')) {
                $stats['groups'] = (int) DB::table('registry_user_group')->count();
            }
            if (\Schema::hasTable('registry_discussion')) {
                $stats['discussions'] = (int) DB::table('registry_discussion')->count();
            }
            if (\Schema::hasTable('registry_blog_post')) {
                $stats['blog_posts'] = (int) DB::table('registry_blog_post')->count();
                $stats['blog_pending'] = (int) DB::table('registry_blog_post')
                    ->where('status', 'pending')->count();
            }
            if (\Schema::hasTable('registry_review')) {
                $stats['reviews'] = (int) DB::table('registry_review')->count();
            }
        } catch (\Throwable $e) {
            // Tables may not exist yet — return zeros.
        }

        return $stats;
    }
}
