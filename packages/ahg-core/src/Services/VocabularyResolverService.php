<?php

/**
 * VocabularyResolverService — resolve preferredLabel / altLabels for a URI in
 * a given culture, with cache → SPARQL → write-through fallback.
 *
 * The proper translation strategy for controlled vocabularies (RiC-O, ISAD,
 * AAT/TGN/LCSH, ICIP) is to read SKOS skos:prefLabel / skos:altLabel triples
 * with xml:lang tags from a Jena Fuseki triplestore. This service hides the
 * Fuseki round-trip behind a MySQL cache so per-page renders stay cheap.
 *
 * Phase 1 of issue #36.
 *
 * Usage:
 *
 *   $svc = app(\AhgCore\Services\VocabularyResolverService::class);
 *   $label = $svc->preferredLabel('https://www.ica.org/standards/RiC/ontology#Record');
 *   $alt = $svc->altLabels('https://www.ica.org/standards/RiC/ontology#Record', 'fr');
 *   $batch = $svc->resolveMany([$uri1, $uri2, $uri3]);
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class VocabularyResolverService
{
    private string $sparqlEndpoint;
    private int $cacheTtlSeconds;

    public function __construct(?string $sparqlEndpoint = null, ?int $cacheTtlSeconds = null)
    {
        $this->sparqlEndpoint = $sparqlEndpoint
            ?? rtrim((string) config('ric.fuseki.url', 'http://localhost:3030/ric'), '/') . '/query';
        $this->cacheTtlSeconds = $cacheTtlSeconds ?? (int) config('ric.vocabulary_cache_ttl', 86400);
    }

    /**
     * Preferred label (skos:prefLabel) for the URI in the requested culture,
     * with fallback to current locale → app fallback locale → first available.
     * Returns the URI's local fragment as a last-ditch fallback.
     */
    public function preferredLabel(string $uri, ?string $culture = null): string
    {
        $culture = $culture ?? (string) app()->getLocale();
        $fallback = (string) config('app.fallback_locale', 'en');

        // 1) Try cache for current culture, then fallback culture
        foreach (array_unique([$culture, $fallback]) as $c) {
            $row = $this->fetchCache($uri, $c);
            if ($row && $row->preferred_label !== '') {
                return $row->preferred_label;
            }
        }

        // 2) SPARQL — fetch all labels for this URI at once, write through cache
        $allLabels = $this->fetchAllLabelsViaSparql($uri);
        if (! empty($allLabels)) {
            foreach ($allLabels as $lang => $labels) {
                $this->writeCache($uri, $lang, $labels['pref'] ?? '', $labels['alt'] ?? []);
            }
            // Prefer requested culture, then fallback, then any.
            return $allLabels[$culture]['pref']
                ?? $allLabels[$fallback]['pref']
                ?? (array_values($allLabels)[0]['pref'] ?? $this->localFragment($uri));
        }

        // 3) Last-ditch: derive a human-readable fragment from the URI
        return $this->localFragment($uri);
    }

    /**
     * All alternative labels (skos:altLabel) for a URI in a given culture.
     */
    public function altLabels(string $uri, ?string $culture = null): array
    {
        $culture = $culture ?? (string) app()->getLocale();
        $row = $this->fetchCache($uri, $culture);
        if ($row && ! empty($row->alt_labels)) {
            $decoded = json_decode($row->alt_labels, true);
            return is_array($decoded) ? $decoded : [];
        }
        // SPARQL miss path same as preferredLabel — populate cache
        $allLabels = $this->fetchAllLabelsViaSparql($uri);
        foreach ($allLabels as $lang => $labels) {
            $this->writeCache($uri, $lang, $labels['pref'] ?? '', $labels['alt'] ?? []);
        }
        return $allLabels[$culture]['alt'] ?? [];
    }

    /**
     * Bulk resolution — one SPARQL VALUES query for many URIs at once.
     *
     * @param  string[]  $uris
     * @return array<string, string>  uri => preferred label
     */
    public function resolveMany(array $uris, ?string $culture = null): array
    {
        $culture = $culture ?? (string) app()->getLocale();
        $out = [];
        $missing = [];
        foreach ($uris as $uri) {
            $row = $this->fetchCache($uri, $culture);
            if ($row && $row->preferred_label !== '') {
                $out[$uri] = $row->preferred_label;
            } else {
                $missing[] = $uri;
            }
        }
        if (! empty($missing)) {
            $batch = $this->fetchManyLabelsViaSparql($missing, $culture);
            foreach ($batch as $uri => $label) {
                $out[$uri] = $label;
            }
            // Anything still missing → URI fragment
            foreach ($missing as $uri) {
                if (! isset($out[$uri])) {
                    $out[$uri] = $this->localFragment($uri);
                }
            }
        }
        return $out;
    }

    /**
     * Bust the cache for a URI (e.g. after a re-import of the source ontology).
     */
    public function invalidate(string $uri): void
    {
        if (Schema::hasTable('vocabulary_label_cache')) {
            DB::table('vocabulary_label_cache')->where('uri', $uri)->delete();
        }
    }

    /**
     * Bust the cache for a whole vocabulary (e.g. after `ahg:vocabulary-import ric-o`).
     */
    public function invalidateVocabulary(string $vocabulary): void
    {
        if (Schema::hasTable('vocabulary_label_cache')) {
            DB::table('vocabulary_label_cache')->where('source_vocabulary', $vocabulary)->delete();
        }
    }

    // ─── Internals ─────────────────────────────────────────────────

    private function fetchCache(string $uri, string $culture): ?object
    {
        if (! Schema::hasTable('vocabulary_label_cache')) {
            return null;
        }
        return DB::table('vocabulary_label_cache')
            ->where('uri', $uri)
            ->where('culture', $culture)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    private function writeCache(string $uri, string $culture, string $pref, array $alt, ?string $vocabulary = null): void
    {
        if (! Schema::hasTable('vocabulary_label_cache') || $pref === '') {
            return;
        }
        $expires = now()->addSeconds($this->cacheTtlSeconds);
        DB::table('vocabulary_label_cache')->updateOrInsert(
            ['uri' => $uri, 'culture' => $culture],
            [
                'preferred_label' => $pref,
                'alt_labels' => json_encode(array_values($alt), JSON_UNESCAPED_UNICODE),
                'source_vocabulary' => $vocabulary,
                'sparql_endpoint' => $this->sparqlEndpoint,
                'expires_at' => $expires,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * SPARQL: fetch every skos label for a URI across all languages.
     *
     * @return array<string, array{pref: string, alt: string[]}>  by language tag
     */
    private function fetchAllLabelsViaSparql(string $uri): array
    {
        $sparql = sprintf(
            'PREFIX skos: <http://www.w3.org/2004/02/skos/core#>'
            . ' PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>'
            . ' SELECT ?lang ?pref ?alt WHERE {'
            . '   <%s> ?p ?label .'
            . '   FILTER (?p IN (skos:prefLabel, skos:altLabel, rdfs:label))'
            . '   BIND(LANG(?label) AS ?lang)'
            . '   BIND(IF(?p = skos:altLabel, ?label, "") AS ?alt)'
            . '   BIND(IF(?p != skos:altLabel, ?label, "") AS ?pref)'
            . ' }',
            addslashes($uri)
        );
        $rows = $this->runSparql($sparql);
        $out = [];
        foreach ($rows as $r) {
            $lang = $r['lang']['value'] ?? '';
            if ($lang === '') $lang = 'en';
            $out[$lang] ??= ['pref' => '', 'alt' => []];
            if (($r['pref']['value'] ?? '') !== '') {
                $out[$lang]['pref'] = $r['pref']['value'];
            }
            if (($r['alt']['value'] ?? '') !== '') {
                $out[$lang]['alt'][] = $r['alt']['value'];
            }
        }
        return $out;
    }

    /**
     * Bulk SPARQL with VALUES clause for many URIs in one round-trip.
     *
     * @param  string[]  $uris
     * @return array<string, string>  uri => preferredLabel
     */
    private function fetchManyLabelsViaSparql(array $uris, string $culture): array
    {
        if (empty($uris)) return [];
        $values = implode(' ', array_map(fn ($u) => '<' . addslashes($u) . '>', $uris));
        $sparql = sprintf(
            'PREFIX skos: <http://www.w3.org/2004/02/skos/core#>'
            . ' SELECT ?uri ?label WHERE {'
            . '   VALUES ?uri { %s }'
            . '   ?uri skos:prefLabel ?label .'
            . '   FILTER (LANG(?label) = "%s" || LANG(?label) = "")'
            . ' }',
            $values,
            addslashes($culture)
        );
        $rows = $this->runSparql($sparql);
        $out = [];
        foreach ($rows as $r) {
            $uri = $r['uri']['value'] ?? '';
            $label = $r['label']['value'] ?? '';
            if ($uri && $label && ! isset($out[$uri])) {
                $out[$uri] = $label;
            }
        }
        return $out;
    }

    private function runSparql(string $query): array
    {
        try {
            $req = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/sparql-results+json'])
                ->asForm();
            $user = (string) config('ric.fuseki.user', '');
            $password = (string) config('ric.fuseki.password', '');
            if ($user !== '') {
                $req = $req->withBasicAuth($user, $password);
            }
            $resp = $req->post($this->sparqlEndpoint, ['query' => $query]);
            if ($resp->successful()) {
                $data = $resp->json();
                return $data['results']['bindings'] ?? [];
            }
        } catch (\Throwable $e) {
            Log::warning('VocabularyResolverService SPARQL error: ' . $e->getMessage());
        }
        return [];
    }

    /**
     * Fallback when no label exists anywhere — return the URI's local fragment
     * (after # or last /) humanised (camelCase → spaced, dashes → spaces).
     */
    private function localFragment(string $uri): string
    {
        $fragment = '';
        if (str_contains($uri, '#')) {
            $fragment = substr($uri, strrpos($uri, '#') + 1);
        } elseif (str_contains($uri, '/')) {
            $fragment = substr($uri, strrpos($uri, '/') + 1);
        } else {
            $fragment = $uri;
        }
        // CamelCase → "Camel Case", "-" or "_" → space
        $fragment = preg_replace('/([a-z])([A-Z])/', '$1 $2', $fragment);
        $fragment = str_replace(['_', '-'], ' ', $fragment ?? '');
        return trim((string) $fragment);
    }
}
