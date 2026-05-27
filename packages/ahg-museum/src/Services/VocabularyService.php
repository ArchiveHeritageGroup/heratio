<?php

/**
 * VocabularyService - Service for Heratio
 *
 * Provides vocabulary autocomplete sources for Museum / Spectrum edit
 * forms: Getty AAT via SPARQL (24h cache), internal ahg_dropdown lookups
 * by taxonomy group, and internal authority search across actor / term.
 *
 * Issue: #739
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
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

namespace AhgMuseum\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VocabularyService
{
    /**
     * Getty SPARQL endpoint. The .json variant returns
     * application/sparql-results+json by default which matches the
     * autocomplete consumer shape.
     */
    public const GETTY_SPARQL_ENDPOINT = 'https://vocab.getty.edu/sparql.json';

    /**
     * Cache TTL for Getty AAT lookups (24h).
     */
    public const GETTY_CACHE_TTL = 86400;

    /**
     * Search Getty AAT via SPARQL.
     *
     * Returns up to $limit concepts whose pref label matches $query
     * (case-insensitive substring). Each result is shaped:
     *
     *   {uri, label, definition, parents: [{uri, label}, ...]}
     *
     * Cached for 24h per (query, limit) tuple.
     *
     * @return array<int, array{uri:string,label:string,definition:string,parents:array}>
     */
    public function searchGettyAat(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }
        $limit = max(1, min($limit, 25));

        $cacheKey = 'museum:getty-aat:' . md5(strtolower($query) . ':' . $limit);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        $fresh = $this->fetchGettyAat($query, $limit);
        // Only cache non-empty responses - empty results are usually
        // transient SPARQL endpoint failures and should retry on next hit.
        if (!empty($fresh)) {
            Cache::put($cacheKey, $fresh, self::GETTY_CACHE_TTL);
        }
        return $fresh;
    }

    /**
     * Live SPARQL fetch. Returns [] on transport / parse failure so the
     * autocomplete dropdown stays silent instead of throwing.
     */
    private function fetchGettyAat(string $query, int $limit): array
    {
        $termEscaped = str_replace('"', '\\"', $query);
        $aatScheme = 'http://vocab.getty.edu/aat/';

        $sparql = <<<SPARQL
PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
PREFIX gvp: <http://vocab.getty.edu/ontology#>
PREFIX xl: <http://www.w3.org/2008/05/skos-xl#>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>

SELECT DISTINCT ?subject ?prefLabel ?scopeNote ?parent ?parentLabel WHERE {
    ?subject a skos:Concept ;
             skos:inScheme <{$aatScheme}> ;
             gvp:prefLabelGVP/xl:literalForm ?prefLabel .
    OPTIONAL { ?subject skos:scopeNote/rdf:value ?scopeNote }
    OPTIONAL {
        ?subject gvp:broaderPreferred ?parent .
        ?parent gvp:prefLabelGVP/xl:literalForm ?parentLabel .
    }
    FILTER(REGEX(STR(?prefLabel), "{$termEscaped}", "i"))
    FILTER(LANG(?prefLabel) = "" || LANGMATCHES(LANG(?prefLabel), "en"))
}
ORDER BY ?prefLabel
LIMIT {$limit}
SPARQL;

        $url = self::GETTY_SPARQL_ENDPOINT . '?query=' . urlencode($sparql);

        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => "Accept: application/sparql-results+json\r\n"
                    . "User-Agent: Heratio-Museum/1.0 (issue-739)\r\n",
                'timeout'       => 10,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        try {
            $raw = @file_get_contents($url, false, $context);
            if ($raw === false || $raw === '') {
                return [];
            }

            $data = json_decode($raw, true);
            if (!is_array($data) || empty($data['results']['bindings'])) {
                return [];
            }

            // SPARQL returns one binding per parent edge; coalesce by URI.
            $byUri = [];
            foreach ($data['results']['bindings'] as $b) {
                $uri = $b['subject']['value'] ?? '';
                if ($uri === '') {
                    continue;
                }
                if (!isset($byUri[$uri])) {
                    $byUri[$uri] = [
                        'uri'        => $uri,
                        'label'      => $b['prefLabel']['value'] ?? '',
                        'definition' => isset($b['scopeNote'])
                            ? $this->truncate($b['scopeNote']['value'], 240)
                            : '',
                        'parents'    => [],
                    ];
                }
                if (!empty($b['parent']['value'])) {
                    $byUri[$uri]['parents'][] = [
                        'uri'   => $b['parent']['value'],
                        'label' => $b['parentLabel']['value'] ?? '',
                    ];
                }
            }

            return array_values($byUri);
        } catch (\Throwable $e) {
            Log::warning('Museum Getty AAT fetch failed', [
                'query'   => $query,
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Search a single ahg_dropdown taxonomy group.
     *
     * Returns active rows whose label or code matches $query (LIKE).
     *
     * @return array<int, array{code:string,label:string,color:?string,icon:?string}>
     */
    public function searchVocabulary(string $group, string $query, int $limit = 20): array
    {
        $group = trim($group);
        $query = trim($query);
        if ($group === '' || strlen($query) < 1) {
            return [];
        }
        $limit = max(1, min($limit, 50));

        $like = '%' . $this->escapeLike($query) . '%';

        $rows = DB::table('ahg_dropdown')
            ->where('taxonomy', $group)
            ->where('is_active', 1)
            ->where(function ($q) use ($like) {
                $q->where('label', 'LIKE', $like)
                  ->orWhere('code', 'LIKE', $like);
            })
            ->orderBy('sort_order')
            ->orderBy('label')
            ->limit($limit)
            ->get(['code', 'label', 'color', 'icon']);

        return $rows->map(static fn ($r) => [
            'code'  => (string) $r->code,
            'label' => (string) $r->label,
            'color' => $r->color,
            'icon'  => $r->icon,
        ])->all();
    }

    /**
     * Search internal authorities (actor or term).
     *
     * @param  string  $type  "actor" or "term"
     * @return array<int, array{id:int,type:string,label:string,note:string,slug:?string}>
     */
    public function searchAuthority(string $type, string $query, int $limit = 10): array
    {
        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }
        $limit = max(1, min($limit, 50));
        $like = '%' . $this->escapeLike($query) . '%';

        if ($type === 'actor') {
            $rows = DB::table('actor as a')
                ->join('actor_i18n as ai', 'ai.id', '=', 'a.id')
                ->where('ai.authorized_form_of_name', 'LIKE', $like)
                ->select(
                    'a.id',
                    'ai.authorized_form_of_name as label',
                    'ai.history as note'
                )
                ->orderBy('ai.authorized_form_of_name')
                ->limit($limit)
                ->get();

            return $rows->map(static fn ($r) => [
                'id'    => (int) $r->id,
                'type'  => 'actor',
                'label' => (string) ($r->label ?? ''),
                'note'  => mb_strimwidth((string) ($r->note ?? ''), 0, 200, '...'),
                'slug'  => null,
            ])->all();
        }

        if ($type === 'term') {
            $rows = DB::table('term as t')
                ->join('term_i18n as ti', 'ti.id', '=', 't.id')
                ->where('ti.name', 'LIKE', $like)
                ->select(
                    't.id',
                    't.taxonomy_id',
                    'ti.name as label'
                )
                ->orderBy('ti.name')
                ->limit($limit)
                ->get();

            return $rows->map(static fn ($r) => [
                'id'    => (int) $r->id,
                'type'  => 'term',
                'label' => (string) ($r->label ?? ''),
                'note'  => 'taxonomy #' . (int) $r->taxonomy_id,
                'slug'  => null,
            ])->all();
        }

        return [];
    }

    /**
     * MySQL LIKE escape - guard against wildcard injection from the
     * untrusted query string.
     */
    private function escapeLike(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }

    private function truncate(string $text, int $length): string
    {
        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $length, '...');
        }
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length - 3) . '...';
    }
}
