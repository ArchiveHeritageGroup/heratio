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
