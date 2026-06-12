<?php

/**
 * OpenSearchController - Heratio ahg-core
 *
 * Serves the OpenSearch 1.1 description document at GET /opensearch.xml so that a
 * browser or a federated discovery aggregator can add this Heratio catalogue as a
 * search provider ("Add to search bar" / external search-provider registration).
 *
 * The description document points its html search template at the REAL public
 * catalogue search - GET /glam/browse with the free-text `query` parameter (the
 * same parameter DisplayController::browse reads as `$request->input('query')`) -
 * and, when the public JSON search route exists, also advertises a json result
 * template at GET /api/v1/informationobjects/search?query={searchTerms} (a public,
 * published-only, no-auth read endpoint). The site / institution name is read from
 * the existing `setting`/`setting_i18n` `siteTitle` value (the same value the theme
 * header uses), with a neutral fallback; the host comes from url(), never a
 * hardcoded host. No new table, no DB writes, no ALTER.
 *
 * The same controller also serves the OpenSearch Suggestions extension at
 * GET /opensearch/suggest?q={searchTerms} (advertised in the description via a
 * `<Url type="application/x-suggestions+json">` line). It returns the standard
 * OpenSearch Suggestions JSON shape - a 4-element array
 * [query, [completions], [descriptions], [urls]] - where completions are up to
 * ten PUBLISHED (status type_id=158, status_id=160; synthetic root id=1 excluded)
 * archival-record titles whose `information_object_i18n.title` prefix-matches the
 * query, and urls link to each record via url(). The LIKE input is sanitised
 * (the % and _ wildcards and the escape char are neutralised) so a user query can
 * neither break the match nor trigger an unbounded scan; the result set is capped
 * at ten and a query shorter than two characters degrades to the empty 4-element
 * shape rather than ever returning a 500. The /opensearch/suggest path is
 * two-segment and so is inherently safe from the single-segment /{slug} catch-all.
 *
 * The `.xml` path is dotted, so it can never be captured by the single-segment
 * /{slug} archival-record catch-all (that route only matches a slug of the form
 * [a-z0-9][a-z0-9-]* and excludes known prefixes); it is inherently catch-all-safe.
 *
 * Read-only and defensive: every lookup is guarded and on ANY failure the method
 * degrades to a minimal but valid OpenSearch document rather than returning a 500.
 * International - the search target is locale-neutral (no language is baked into
 * the template).
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

namespace AhgCore\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

class OpenSearchController extends Controller
{
    /** The OpenSearch description document media type (bare, for type attributes). */
    private const MEDIA_TYPE = 'application/opensearchdescription+xml';

    /** Response Content-Type header (media type + charset). */
    private const CONTENT_TYPE = self::MEDIA_TYPE.'; charset=UTF-8';

    /** Neutral fallback when no siteTitle setting is configured. */
    private const DEFAULT_SHORT_NAME = 'Heratio';

    /** Publication-status taxonomy row id and the "Published" status id. */
    private const STATUS_TYPE_PUBLICATION = 158;
    private const STATUS_PUBLISHED        = 160;

    /** Synthetic root description id, excluded from suggestions. */
    private const ROOT_ID = 1;

    /** The culture whose titles are suggested (locale-neutral default). */
    private const CULTURE = 'en';

    /** Suggestion bounds: minimum query length and the hard result cap. */
    private const SUGGEST_MIN_LENGTH = 2;
    private const SUGGEST_LIMIT      = 10;

    /**
     * GET /opensearch.xml - the OpenSearch 1.1 description document.
     *
     * Never 500s: builds the full document inside a guard and falls back to a
     * minimal-but-valid document on any failure.
     */
    public function index()
    {
        try {
            $xml = $this->buildDocument();
        } catch (Throwable $e) {
            \Log::warning('[ahg-core] opensearch.xml build failed: '.$e->getMessage());
            $xml = $this->minimalDocument();
        }

        return response($xml, 200, [
            'Content-Type'  => self::CONTENT_TYPE,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Build the full OpenSearch 1.1 description document targeting the real
     * public catalogue search.
     */
    private function buildDocument(): string
    {
        $siteName = $this->siteName();

        // ShortName is capped at 16 chars by the OpenSearch spec; keep a longer,
        // human-readable LongName separately.
        $shortName = $this->clip($siteName, 16);
        $longName  = $this->clip($siteName.' catalogue search', 48);

        $description = $this->clip(
            'Search the '.$siteName.' catalogue (archival descriptions, authority records and collections).',
            1024
        );

        // REAL public html search: GET /glam/browse?query={searchTerms}
        // (DisplayController::browse reads the free-text term from `query`).
        $htmlTemplate = $this->absolute('/glam/browse').'?query={searchTerms}';

        // OPTIONAL public JSON search surface, advertised only when its route
        // actually exists: GET /api/v1/informationobjects/search?query={searchTerms}
        // (published-only, no-auth read endpoint; param confirmed in the v1
        // InformationObjectApiController::search - `query` then `q`).
        $jsonTemplate = null;
        if ($this->jsonSearchAvailable()) {
            $jsonTemplate = $this->absolute('/api/v1/informationobjects/search').'?query={searchTerms}';
        }

        $contactEmail = $this->contactEmail();
        $host         = $this->host();
        $faviconUrl   = $this->absolute('/favicon.ico');

        // All dynamic values are XML-escaped via x(). Template URLs keep their
        // literal {searchTerms} macro (it must NOT be escaped) but the rest of
        // the URL is built from url()/route output and a literal query key.
        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">';
        $lines[] = '  <ShortName>'.$this->x($shortName).'</ShortName>';
        $lines[] = '  <LongName>'.$this->x($longName).'</LongName>';
        $lines[] = '  <Description>'.$this->x($description).'</Description>';
        $lines[] = '  <Tags>'.$this->x('archive catalogue collections heritage glam '.$host).'</Tags>';
        $lines[] = '  <Contact>'.$this->x($contactEmail).'</Contact>';
        $lines[] = '  <Image width="16" height="16" type="image/x-icon">'.$this->x($faviconUrl).'</Image>';
        $lines[] = '  <Url type="text/html" method="get" template="'.$this->xTemplate($htmlTemplate).'"/>';
        if ($jsonTemplate !== null) {
            $lines[] = '  <Url type="application/json" method="get" template="'.$this->xTemplate($jsonTemplate).'"/>';
        }
        // OpenSearch Suggestions extension: typeahead source. Browsers discover
        // this and call it as the user types in the search bar. The endpoint is
        // published-only, read-only and bounded (see suggest()).
        $suggestTemplate = $this->absolute('/opensearch/suggest').'?q={searchTerms}';
        $lines[] = '  <Url type="application/x-suggestions+json" method="get" template="'.$this->xTemplate($suggestTemplate).'"/>';
        // Self-reference so aggregators can re-fetch the description.
        $lines[] = '  <Url type="'.$this->x(self::MEDIA_TYPE).'" rel="self" template="'.$this->xTemplate($this->absolute('/opensearch.xml')).'"/>';
        $lines[] = '  <InputEncoding>UTF-8</InputEncoding>';
        $lines[] = '  <OutputEncoding>UTF-8</OutputEncoding>';
        $lines[] = '  <SyndicationRight>open</SyndicationRight>';
        $lines[] = '  <AdultContent>false</AdultContent>';
        $lines[] = '</OpenSearchDescription>';

        return implode("\n", $lines)."\n";
    }

    /**
     * The minimal-but-valid fallback document. Still points at the real html
     * search (built from url()), still well-formed, never throws.
     */
    private function minimalDocument(): string
    {
        $template = $this->absolute('/glam/browse').'?query={searchTerms}';

        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">';
        $lines[] = '  <ShortName>'.$this->x(self::DEFAULT_SHORT_NAME).'</ShortName>';
        $lines[] = '  <Description>'.$this->x('Search the catalogue.').'</Description>';
        $lines[] = '  <Url type="text/html" method="get" template="'.$this->xTemplate($template).'"/>';
        $lines[] = '  <InputEncoding>UTF-8</InputEncoding>';
        $lines[] = '</OpenSearchDescription>';

        return implode("\n", $lines)."\n";
    }

    /**
     * The institution / site name, read from the existing setting/setting_i18n
     * `siteTitle` value (the same value the theme header renders). Falls back to
     * the neutral default on any failure or when unset.
     */
    private function siteName(): string
    {
        try {
            $setting = DB::table('setting')->where('name', 'siteTitle')->first();
            if ($setting) {
                $i18n = DB::table('setting_i18n')
                    ->where('id', $setting->id)
                    ->where('culture', 'en')
                    ->first();
                $value = trim((string) ($i18n->value ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        } catch (Throwable $e) {
            // fall through to default
        }

        return self::DEFAULT_SHORT_NAME;
    }

    /**
     * A best-effort contact e-mail for the OpenSearch <Contact> line. Prefers an
     * operator-set setting if present; otherwise a neutral webmaster@<host>.
     */
    private function contactEmail(): string
    {
        try {
            $setting = DB::table('setting')->where('name', 'emailAddress')->first();
            if ($setting) {
                $i18n = DB::table('setting_i18n')
                    ->where('id', $setting->id)
                    ->where('culture', 'en')
                    ->first();
                $value = trim((string) ($i18n->value ?? ''));
                if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $value;
                }
            }
        } catch (Throwable $e) {
            // fall through to default
        }

        return 'webmaster@'.$this->host();
    }

    /** Whether the public JSON search route is registered. */
    private function jsonSearchAvailable(): bool
    {
        try {
            foreach (Route::getRoutes() as $route) {
                if ($route->uri() === 'api/v1/informationobjects/search'
                    && in_array('GET', $route->methods(), true)) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * GET /opensearch/suggest?q=... - the OpenSearch Suggestions extension.
     *
     * Returns the standard 4-element OpenSearch Suggestions JSON array
     * [query, [completions], [descriptions], [urls]] for browser typeahead. The
     * completions are up to TEN published archival-record titles that prefix-match
     * the query; urls link to each record. CORS-open so a search bar on any origin
     * can consume it. Never 500s: a short/empty query, an error, or no match all
     * degrade to [q, [], [], []].
     *
     * Read-only: a bounded, indexed prefix SELECT against information_object_i18n /
     * slug / status. No DB writes, no ALTER, no new table.
     */
    public function suggest(\Illuminate\Http\Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        // Empty shape for short/empty input - the spec requires the echoed query
        // as the first element even when there are no completions.
        $empty = [$q, [], [], []];

        try {
            // Minimum query length of two keeps the prefix scan meaningful and cheap.
            if (function_exists('mb_strlen') ? mb_strlen($q) < self::SUGGEST_MIN_LENGTH
                                             : strlen($q) < self::SUGGEST_MIN_LENGTH) {
                return $this->suggestionsResponse($empty);
            }

            $rows = $this->matchTitles($q);

            $completions  = [];
            $descriptions = [];
            $urls         = [];
            foreach ($rows as $row) {
                $title = trim((string) ($row->title ?? ''));
                $slug  = trim((string) ($row->slug ?? ''));
                if ($title === '' || $slug === '') {
                    continue;
                }
                $completions[]  = $title;
                $descriptions[] = '';
                $urls[]         = $this->absolute('/'.ltrim($slug, '/'));
            }

            return $this->suggestionsResponse([$q, $completions, $descriptions, $urls]);
        } catch (Throwable $e) {
            \Log::warning('[ahg-core] opensearch suggest failed: '.$e->getMessage());

            return $this->suggestionsResponse($empty);
        }
    }

    /**
     * Up to ten PUBLISHED, non-root information-object titles whose title
     * prefix-matches the (wildcard-escaped) query, with the record slug.
     *
     * SQL shape:
     *   SELECT i.title, s.slug
     *     FROM information_object_i18n i
     *     JOIN slug   s  ON s.object_id = i.id
     *     JOIN status st ON st.object_id = i.id AND st.type_id=158 AND st.status_id=160
     *    WHERE i.id > 1
     *      AND i.culture = 'en'
     *      AND i.title LIKE '<escaped-prefix>%' ESCAPE '\'
     *    ORDER BY i.title
     *    LIMIT 10
     *
     * The LIKE pattern escapes the SQL wildcards % and _ (and the escape char
     * itself) in the user input so the match stays a literal prefix and can never
     * widen into an unbounded scan.
     */
    private function matchTitles(string $q): \Illuminate\Support\Collection
    {
        $pattern = $this->likePrefix($q);

        return DB::table('information_object_i18n as i')
            ->join('slug as s', 's.object_id', '=', 'i.id')
            ->join('status as st', function ($join) {
                $join->on('st.object_id', '=', 'i.id')
                    ->where('st.type_id', self::STATUS_TYPE_PUBLICATION)
                    ->where('st.status_id', self::STATUS_PUBLISHED);
            })
            ->where('i.id', '>', self::ROOT_ID)
            ->where('i.culture', self::CULTURE)
            ->whereNotNull('i.title')
            ->where('i.title', '!=', '')
            ->whereRaw("i.title LIKE ? ESCAPE '\\\\'", [$pattern])
            ->orderBy('i.title')
            ->limit(self::SUGGEST_LIMIT)
            ->get(['i.title', 's.slug']);
    }

    /**
     * Build a literal-prefix LIKE pattern from raw user input. The LIKE wildcards
     * % and _ and the escape character \ are each backslash-escaped so the input
     * matches literally; a single trailing % then makes it a prefix match.
     */
    private function likePrefix(string $q): string
    {
        $escaped = str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $q
        );

        return $escaped.'%';
    }

    /**
     * A CORS-open JSON response with the OpenSearch Suggestions content type. The
     * payload is always the 4-element array, never wrapped or re-keyed.
     */
    private function suggestionsResponse(array $payload)
    {
        return response()->json($payload, 200, [
            'Content-Type'                => 'application/x-suggestions+json; charset=UTF-8',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control'               => 'public, max-age=60',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Absolute URL for a host-relative path, derived from url() (never hardcoded). */
    private function absolute(string $path): string
    {
        return url($path);
    }

    /** The request host (for tags / fallback contact), derived from url(). */
    private function host(): string
    {
        $host = parse_url((string) url('/'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    /** Trim a string to a maximum length without breaking mid-multibyte-char. */
    private function clip(string $value, int $max): string
    {
        $value = trim($value);
        if (function_exists('mb_strlen') && mb_strlen($value) > $max) {
            return rtrim(mb_substr($value, 0, $max));
        }
        if (strlen($value) > $max) {
            return rtrim(substr($value, 0, $max));
        }

        return $value;
    }

    /** XML-escape an element value / plain attribute value. */
    private function x(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Escape a URL template for an XML attribute while preserving the literal
     * {searchTerms} macro. Ampersands and quotes are escaped; the macro braces
     * are untouched (htmlspecialchars does not alter them).
     */
    private function xTemplate(string $template): string
    {
        return htmlspecialchars($template, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
