<?php

/**
 * LibraryOpacService - public catalogue search + availability + widgets
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

namespace AhgLibrary\Services;

use AhgLibrary\Support\LibrarySettings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LibraryOpacService
{
    /**
     * Public-facing search across library_item joined to information_object
     * for title, with optional ?q= matching ISBN / call_number / publisher /
     * author. Pagination size from library_opac_results_per_page.
     */
    public function search(string $query, array $filters = []): LengthAwarePaginator
    {
        $perPage = LibrarySettings::opacResultsPerPage();
        $culture = app()->getLocale();

        $q = DB::table('library_item as li')
            ->leftJoin('information_object as io', 'li.information_object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id');

        if ($query !== '') {
            $like = '%' . $query . '%';
            $q->where(function ($w) use ($like) {
                $w->where('i18n.title', 'LIKE', $like)
                    ->orWhere('li.isbn', 'LIKE', $like)
                    ->orWhere('li.call_number', 'LIKE', $like)
                    ->orWhere('li.publisher', 'LIKE', $like)
                    ->orWhere('li.responsibility_statement', 'LIKE', $like);
            });
        }

        if (!empty($filters['material_type'])) {
            $q->where('li.material_type', $filters['material_type']);
        }
        if (!empty($filters['language'])) {
            $q->where('li.language', $filters['language']);
        }

        return $q->select(
                'li.id', 'li.isbn', 'li.call_number', 'li.material_type', 'li.publisher',
                'li.publication_date', 'li.cover_url', 'li.responsibility_statement',
                'i18n.title', 'slug.slug',
            )
            ->orderBy('i18n.title')
            ->paginate($perPage)
            ->appends(['q' => $query] + $filters);
    }

    /**
     * Per-copy availability summary for a library_item. Returns
     * {available, checked_out, on_hold, total} so the OPAC can render
     * "3 of 5 available" badges when library_opac_show_availability is on.
     */
    public function getAvailability(int $itemId): array
    {
        $rows = DB::table('library_copy')
            ->where('library_item_id', $itemId)
            ->whereNull('withdrawal_date')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->get();

        $summary = [
            'total' => 0,
            'available' => 0,
            'checked_out' => 0,
            'on_hold' => 0,
            'other' => 0,
        ];
        foreach ($rows as $r) {
            $summary['total'] += (int) $r->cnt;
            switch ($r->status) {
                case 'available':   $summary['available'] += (int) $r->cnt; break;
                case 'checked_out': $summary['checked_out'] += (int) $r->cnt; break;
                case 'on_hold':     $summary['on_hold'] += (int) $r->cnt; break;
                default:            $summary['other'] += (int) $r->cnt;
            }
        }
        return $summary;
    }

    /**
     * Newest items by acquisition_date on any copy. Limit from
     * library_opac_new_arrivals_count.
     */
    public function newArrivals(): array
    {
        $limit = LibrarySettings::opacNewArrivalsCount();
        $culture = app()->getLocale();

        return DB::table('library_item as li')
            ->leftJoin('information_object as io', 'li.information_object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('library_copy as cp', 'li.id', '=', 'cp.library_item_id')
            ->whereNotNull('cp.acquisition_date')
            ->select(
                'li.id', 'li.isbn', 'li.cover_url', 'li.responsibility_statement',
                'i18n.title', 'slug.slug',
                DB::raw('MAX(cp.acquisition_date) as latest_acquisition'),
            )
            ->groupBy('li.id', 'li.isbn', 'li.cover_url', 'li.responsibility_statement', 'i18n.title', 'slug.slug')
            ->orderByDesc('latest_acquisition')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Most-borrowed items in the rolling library_opac_popular_days window.
     */
    public function popular(): array
    {
        $days = LibrarySettings::opacPopularDays();
        $limit = LibrarySettings::opacNewArrivalsCount();
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $culture = app()->getLocale();

        return DB::table('library_checkout as c')
            ->join('library_copy as cp', 'c.copy_id', '=', 'cp.id')
            ->join('library_item as li', 'cp.library_item_id', '=', 'li.id')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('li.information_object_id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'li.information_object_id', '=', 'slug.object_id')
            ->where('c.checkout_date', '>=', $cutoff)
            ->select(
                'li.id', 'li.isbn', 'li.cover_url',
                'i18n.title', 'slug.slug',
                DB::raw('COUNT(c.id) as checkout_count'),
            )
            ->groupBy('li.id', 'li.isbn', 'li.cover_url', 'i18n.title', 'slug.slug')
            ->orderByDesc('checkout_count')
            ->limit($limit)
            ->get()
            ->all();
    }
}
