<?php

/**
 * SearchIndexHints - tells search engines which address is the real one (H05).
 *
 * Browse and search pages answer to endless query permutations: every facet,
 * sort order, view, page size and language makes a "new" URL with the same
 * content. Left alone those compete with each other in the index and soak up
 * crawl budget. This middleware adds two HTTP headers, which Google and Bing
 * honour for HTML and non-HTML responses alike:
 *
 *   Link: <url>; rel="canonical"   the address to index for this content
 *   X-Robots-Tag: ...              whether to index this address at all
 *
 * Rules, for successful GET responses only:
 *   - print views and CSV or other exports: noindex, nofollow
 *   - browse listings: canonical is the bare path, plus ?page=N when N > 1;
 *     any parameter other than page and language makes the address
 *     noindex, follow, so its links are still crawled
 *   - site search results: noindex, follow
 *   - any other page whose only parameter is the language switch: canonical
 *     is the bare path
 * Pages it does not recognise are left alone.
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

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchIndexHints
{
    /** Parameters that select a language only; they never make a page distinct. */
    private const LANGUAGE_PARAMS = ['sf_culture', 'culture', 'locale', 'lang'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200 || $response->headers->has('X-Robots-Tag')) {
            return $response;
        }

        foreach ($this->hints($request) as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /** @return array<string, string> */
    public function hints(Request $request): array
    {
        $path = '/'.ltrim($request->path(), '/');
        $params = array_diff(array_keys($request->query()), self::LANGUAGE_PARAMS);
        $base = $request->getSchemeAndHttpHost().($path === '/' ? '/' : rtrim($path, '/'));

        if (preg_match('#/(print|exportCsv)(/|$)|/export/[a-z0-9]+$|\.csv$#i', $path)) {
            return ['X-Robots-Tag' => 'noindex, nofollow'];
        }

        if (preg_match('#^/search(/|$)#', $path)) {
            return ['X-Robots-Tag' => 'noindex, follow', 'Link' => '<'.$base.'>; rel="canonical"'];
        }

        if (preg_match('#/browse$#', $path)) {
            $page = (int) $request->query('page', 1);
            $canonical = $base.($page > 1 ? '?page='.$page : '');
            $hints = ['Link' => '<'.$canonical.'>; rel="canonical"'];
            if (array_diff($params, ['page'])) {
                $hints['X-Robots-Tag'] = 'noindex, follow';
            }

            return $hints;
        }

        if ($request->query() !== [] && $params === []) {
            return ['Link' => '<'.$base.'>; rel="canonical"'];
        }

        return [];
    }
}
