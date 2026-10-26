<?php

/**
 * CanonicalUrl - the canonical address of a public page (heratio#1568).
 *
 * The configured app URL plus the path, with the sort, language and view
 * variant parameters dropped and page kept only past the first. Built from
 * config('app.url'), never the request host, so www or another host name
 * never canonicalises to itself. Facet parameters are kept: a filtered list
 * is a different page. robots.txt disallows the same variant parameters.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgCore\Support;

use Illuminate\Http\Request;

class CanonicalUrl
{
    /** Query parameters that only re-sort, translate or re-display a page. */
    public const VARIANT_PARAMS = ['sort', 'sf_culture', 'view', 'listPage', 'displayMode', 'limit', 'dir', 'sortDir'];

    public static function for(Request $request): string
    {
        $query = array_diff_key($request->query(), array_flip(self::VARIANT_PARAMS));
        if ((int) ($query['page'] ?? 1) <= 1) {
            unset($query['page']);
        }
        ksort($query);
        $path = '/'.ltrim($request->path(), '/');

        return rtrim((string) config('app.url'), '/').($path === '/' ? '/' : $path)
            .($query ? '?'.http_build_query($query) : '');
    }
}
