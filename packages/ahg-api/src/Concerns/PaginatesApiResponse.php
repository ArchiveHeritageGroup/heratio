<?php

/**
 * PaginatesApiResponse - shared paginated JSON envelope for /api/v1 controllers.
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

namespace AhgApi\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Build the standard `{data, meta, links}` paginated JSON envelope with
 * self/next/prev/first/last links. Was copy-pasted verbatim into the V1
 * Actor / InformationObject / Repository API controllers.
 */
trait PaginatesApiResponse
{
    protected function paginatedResponse($data, int $total, int $page, int $limit, string $path): JsonResponse
    {
        $lastPage = max(1, (int) ceil($total / $limit));
        $baseUrl = url("/api/v1/{$path}");

        $links = ['self' => "{$baseUrl}?page={$page}&limit={$limit}"];
        if ($page < $lastPage) {
            $links['next'] = "{$baseUrl}?page=".($page + 1)."&limit={$limit}";
        }
        if ($page > 1) {
            $links['prev'] = "{$baseUrl}?page=".($page - 1)."&limit={$limit}";
        }
        $links['first'] = "{$baseUrl}?page=1&limit={$limit}";
        $links['last'] = "{$baseUrl}?page={$lastPage}&limit={$limit}";

        return response()->json([
            'data' => $data->values(),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'last_page' => $lastPage,
            ],
            'links' => $links,
        ]);
    }
}
