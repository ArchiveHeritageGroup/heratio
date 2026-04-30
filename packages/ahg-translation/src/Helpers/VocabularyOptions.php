<?php

/**
 * VocabularyOptions — list narrower SKOS concepts of a top-level URI as
 * (uri, label) pairs in the current culture, for populating select dropdowns
 * from the vocabulary_label_cache.
 *
 * Issue #36 Phase 2b — supports the ICIP sensitivity dropdown (and any other
 * vocabulary-driven dropdown that needs hierarchical narrower-than queries).
 *
 * Reads exclusively from MySQL cache; no Fuseki round-trip. Cache is primed
 * by `ahg:vocabulary-import`.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgTranslation\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VocabularyOptions
{
    /**
     * Return cached SKOS concepts for a vocabulary, ordered by URI fragment.
     * Use when you want every term in the vocabulary as flat options.
     *
     * @return array<int, array{uri: string, label: string}>
     */
    public static function forVocabulary(string $vocabulary, ?string $culture = null): array
    {
        if (! Schema::hasTable('vocabulary_label_cache')) {
            return [];
        }
        $culture = $culture ?? (string) app()->getLocale();
        $fallback = (string) config('app.fallback_locale', 'en');

        $rows = DB::table('vocabulary_label_cache')
            ->where('source_vocabulary', $vocabulary)
            ->whereIn('culture', array_unique([$culture, $fallback]))
            ->orderBy('uri')
            ->orderByRaw("FIELD(culture, ?, ?)", [$culture, $fallback])
            ->get(['uri', 'culture', 'preferred_label']);

        $byUri = [];
        foreach ($rows as $r) {
            // Current-culture row wins; fallback only fills in missing
            if (! isset($byUri[$r->uri]) || $r->culture === $culture) {
                $byUri[$r->uri] = $r->preferred_label;
            }
        }

        $out = [];
        foreach ($byUri as $uri => $label) {
            $out[] = ['uri' => $uri, 'label' => $label];
        }
        return $out;
    }

    /**
     * Filter to a known set of URIs (use when you want only the children of a
     * specific top-concept and you know the URI list — typical for sensitivity
     * level pickers, etc.).
     *
     * @param  string[]  $uris
     * @return array<int, array{uri: string, label: string}>
     */
    public static function pickFromUris(array $uris, ?string $culture = null): array
    {
        if (empty($uris) || ! Schema::hasTable('vocabulary_label_cache')) {
            return [];
        }
        $culture = $culture ?? (string) app()->getLocale();
        $fallback = (string) config('app.fallback_locale', 'en');

        $rows = DB::table('vocabulary_label_cache')
            ->whereIn('uri', $uris)
            ->whereIn('culture', array_unique([$culture, $fallback]))
            ->get(['uri', 'culture', 'preferred_label']);

        $byUri = [];
        foreach ($rows as $r) {
            if (! isset($byUri[$r->uri]) || $r->culture === $culture) {
                $byUri[$r->uri] = $r->preferred_label;
            }
        }

        $out = [];
        // Preserve the caller's URI order
        foreach ($uris as $uri) {
            if (isset($byUri[$uri])) {
                $out[] = ['uri' => $uri, 'label' => $byUri[$uri]];
            }
        }
        return $out;
    }
}
