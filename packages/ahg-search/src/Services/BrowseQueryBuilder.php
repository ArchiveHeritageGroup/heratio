<?php

/**
 * BrowseQueryBuilder - the request bodies the GLAM browse search box sends to
 * Elasticsearch (heratio#1575, heratio#1583). Pure functions, no I/O, so the
 * query shapes can be unit-tested without a live index.
 *
 *  - text(): the normal search. Plain words go to simple_query_string, which
 *    never fails on stray syntax. A query that uses AND/OR/NOT, ? or inner *
 *    wildcards, field-free /regular expressions/ goes to query_string, which
 *    understands them, under a time limit.
 *  - fuzzy(): the close-match retry when the normal search found nothing -
 *    every word with fuzziness AUTO.
 *  - suggest(): the did-you-mean phrase suggester, collated so a suggestion
 *    is only offered when it matches a published, disclosable record.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgSearch\Services;

class BrowseQueryBuilder
{
    /** Search-level time limit for query_string (regex and wildcard expansion can be expensive). */
    public const ADVANCED_TIMEOUT = '2s';

    /** Cap on restricted ids written into the suggester's collate query. */
    public const COLLATE_ID_CAP = 1000;

    /** Does the query need query_string (operators, regex, inner wildcards)? */
    public static function usesAdvancedSyntax(string $query): bool
    {
        return self::hasRegex($query)
            || preg_match('/(^|\s)(AND|OR|NOT)(\s|$)/', $query) === 1
            || str_contains($query, '?')
            || preg_match('/\*\S/u', $query) === 1;
    }

    /** A /regular expression/ term anywhere in the query. */
    public static function hasRegex(string $query): bool
    {
        return preg_match('#(^|\s)/[^/\s][^/]*/(\s|$)#u', $query) === 1;
    }

    /**
     * The normal search-box query.
     *
     * @param  array<int,string>  $fields
     */
    public static function text(string $query, array $fields, string $minimumShouldMatch = '100%'): array
    {
        if (self::usesAdvancedSyntax($query)) {
            return ['query_string' => [
                'query' => $query,
                'fields' => $fields,
                'default_operator' => 'AND',
                'lenient' => true,
                'allow_leading_wildcard' => false,
                'analyze_wildcard' => true,
                'fuzzy_max_expansions' => 50,
                'max_determinized_states' => 10000,
            ]];
        }

        return ['simple_query_string' => [
            'query' => $query,
            'fields' => $fields,
            'default_operator' => 'OR',
            'minimum_should_match' => $minimumShouldMatch,
        ]];
    }

    /**
     * The close-match retry: each word may be one or two edits away
     * (fuzziness AUTO: none up to 2 letters, 1 up to 5, else 2). The first
     * letter must match, which keeps it fast and the matches plausible.
     * All words of a one- or two-word query must match; longer queries may
     * miss one.
     *
     * @param  array<int,string>  $fields
     */
    public static function fuzzy(string $query, array $fields): array
    {
        return ['multi_match' => [
            'query' => self::plainWords($query),
            'fields' => $fields,
            'fuzziness' => 'AUTO',
            'prefix_length' => 1,
            'max_expansions' => 50,
            'operator' => 'or',
            'minimum_should_match' => '2<-1',
        ]];
    }

    /**
     * A _search body for an id lookup. Advanced queries carry a time limit;
     * ES returns what it found in that time rather than running on.
     */
    public static function idSearchBody(array $query, int $size): array
    {
        $body = [
            'size' => $size,
            '_source' => false,
            'track_total_hits' => true,
            'query' => $query,
        ];
        if (isset($query['query_string'])) {
            $body['timeout'] = self::ADVANCED_TIMEOUT;
        }

        return $body;
    }

    /**
     * Did-you-mean: a phrase suggester on the unstemmed `all` field (titles
     * stem, so their suggestions would come back as stems), falling back to
     * per-word term suggestions. The collate query keeps a suggestion only
     * when it matches a published record that is not under embargo and not
     * in the restricted set, so words from drafts are never offered.
     *
     * @param  array<int,int>  $restrictedIds
     */
    public static function suggest(string $query, array $restrictedIds = []): array
    {
        $text = self::plainWords($query);
        $mustNot = [['range' => ['embargoUntil' => ['gt' => 'now']]]];
        if ($restrictedIds) {
            $mustNot[] = ['ids' => ['values' => array_map('strval', array_slice(array_values($restrictedIds), 0, self::COLLATE_ID_CAP))]];
        }
        $collate = ['bool' => [
            'must' => [['match' => ['all' => ['query' => '{{suggestion}}', 'operator' => 'and']]]],
            'filter' => [['term' => ['publicationStatusId' => 160]]],
            'must_not' => $mustNot,
        ]];

        return [
            'size' => 0,
            'suggest' => [
                'text' => $text,
                'browse_phrase' => [
                    'phrase' => [
                        'field' => 'all',
                        'size' => 1,
                        'max_errors' => 2,
                        'confidence' => 1.0,
                        'direct_generator' => [[
                            'field' => 'all',
                            'suggest_mode' => 'missing',
                            'min_word_length' => 3,
                            'prefix_length' => 1,
                        ]],
                        'collate' => ['query' => ['source' => $collate], 'prune' => false],
                    ],
                ],
            ],
        ];
    }

    /** Read the suggester response: the corrected text, or null when none differs. */
    public static function suggestionFrom(array $response, string $query): ?string
    {
        $text = trim((string) ($response['suggest']['browse_phrase'][0]['options'][0]['text'] ?? ''));
        if ($text === '' || mb_strtolower($text) === mb_strtolower(self::plainWords($query))) {
            return null;
        }

        return $text;
    }

    /** The query's words alone: operators, quotes, wildcards, ~N and regex markers removed. */
    public static function plainWords(string $query): string
    {
        $q = preg_replace('/(^|\s)(AND|OR|NOT)(?=\s|$)/', ' ', $query);
        $q = preg_replace('/~\d*/', ' ', $q);
        $q = preg_replace('/[+|\-"()*?\/\\\\]+/', ' ', $q);

        return trim(preg_replace('/\s+/', ' ', $q));
    }
}
