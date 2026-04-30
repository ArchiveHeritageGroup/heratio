<?php

/**
 * VocabularyImportCommand — load a SKOS / OWL / RDF vocabulary into Fuseki
 * and pre-warm the vocabulary_label_cache MySQL table for every culture
 * with skos:prefLabel triples in the source data.
 *
 * Phase 1 of issue #36. Examples:
 *
 *   php artisan ahg:vocabulary-import https://raw.githubusercontent.com/ICA-EGAD/RiC-O/master/ontology/current-version/RiC-O_1-1.rdf --vocabulary=ric-o
 *   php artisan ahg:vocabulary-import data/vocabularies/icip.ttl --vocabulary=icip --format=turtle
 *   php artisan ahg:vocabulary-import http://vocab.getty.edu/aat.nt --vocabulary=aat --format=ntriples --no-prewarm
 *
 * The --no-prewarm flag skips the SPARQL cache-priming step (useful for very
 * large vocabularies where you'd rather populate on demand).
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class VocabularyImportCommand extends Command
{
    protected $signature = 'ahg:vocabulary-import
                            {source : Path or URL to RDF/Turtle/RDF-XML/N-Triples file}
                            {--vocabulary= : Short name (e.g. ric-o, aat, lcsh, icip) — required}
                            {--format=rdfxml : RDF format hint: rdfxml | turtle | ntriples | nquads | trig | jsonld}
                            {--graph= : Optional named graph URI (defaults to vocabulary tag)}
                            {--fuseki-user= : Override Fuseki HTTP basic auth user}
                            {--fuseki-password= : Override Fuseki HTTP basic auth password}
                            {--no-prewarm : Skip cache-priming SPARQL after upload}
                            {--dry-run : Probe the source + Fuseki without writing}';

    protected $description = 'Load a SKOS/OWL/RDF vocabulary into Fuseki and prime vocabulary_label_cache';

    private const FORMAT_MIME = [
        'rdfxml'   => 'application/rdf+xml',
        'turtle'   => 'text/turtle',
        'ntriples' => 'application/n-triples',
        'nquads'   => 'application/n-quads',
        'trig'     => 'application/trig',
        'jsonld'   => 'application/ld+json',
    ];

    public function handle(): int
    {
        $source = (string) $this->argument('source');
        $vocab = (string) $this->option('vocabulary');
        $format = (string) $this->option('format');
        $graph = (string) ($this->option('graph') ?? '');
        $noPrewarm = (bool) $this->option('no-prewarm');
        $dryRun = (bool) $this->option('dry-run');

        if ($vocab === '') {
            $this->error('--vocabulary is required (e.g. ric-o, aat, lcsh, icip)');
            return self::FAILURE;
        }
        if (! isset(self::FORMAT_MIME[$format])) {
            $this->error("Unknown --format={$format}. Valid: " . implode(', ', array_keys(self::FORMAT_MIME)));
            return self::FAILURE;
        }
        if ($graph === '') {
            $graph = "https://heratio.theahg.co.za/vocabulary/{$vocab}";
        }

        // 1) Load the RDF payload
        $rdf = $this->fetchSource($source);
        if ($rdf === null) {
            return self::FAILURE;
        }
        $bytes = strlen($rdf);
        $this->info(sprintf('Loaded %s (%s bytes)', $source, number_format($bytes)));

        // 2) Where does Fuseki live?
        $fusekiBase = rtrim((string) config('ric.fuseki.url', 'http://localhost:3030/ric'), '/');
        $dataEndpoint = $fusekiBase . '/data?graph=' . urlencode($graph);
        $queryEndpoint = $fusekiBase . '/query';
        $this->info("Fuseki data endpoint: {$dataEndpoint}");

        if ($dryRun) {
            $this->warn('DRY RUN — not writing to Fuseki, not priming cache.');
            return self::SUCCESS;
        }

        // 3) Upload to Fuseki via SPARQL Graph Store Protocol — auth-aware
        $this->info('Uploading to Fuseki...');
        // Auth resolution: --fuseki-user/--fuseki-password flag > RIC_FUSEKI_* env > FUSEKI_* env > none
        $fusekiUser = (string) ($this->option('fuseki-user')
            ?: env('RIC_FUSEKI_USER')
            ?: config('ahg-ric.ric_to_atom_sync.fuseki.user')
            ?: config('ric.fuseki.user', ''));
        $fusekiPassword = (string) ($this->option('fuseki-password')
            ?: env('RIC_FUSEKI_PASS')
            ?: config('ahg-ric.ric_to_atom_sync.fuseki.pass')
            ?: config('ric.fuseki.password', ''));
        try {
            $req = Http::timeout(120)
                ->withHeaders(['Content-Type' => self::FORMAT_MIME[$format]])
                ->withBody($rdf, self::FORMAT_MIME[$format]);
            if ($fusekiUser !== '') {
                $req = $req->withBasicAuth($fusekiUser, $fusekiPassword);
            }
            $resp = $req->put($dataEndpoint);
            if (! $resp->successful()) {
                $this->error('Fuseki upload failed: HTTP ' . $resp->status() . ' — ' . substr($resp->body(), 0, 300));
                if ($resp->status() === 401 && $fusekiUser === '') {
                    $this->warn('Fuseki returned 401. Set FUSEKI_USER + FUSEKI_PASSWORD in .env (Heratio reads these via config/ric.php).');
                }
                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('Fuseki upload exception: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->info("  ✓ uploaded into named graph <{$graph}>");

        // 4) Bust any existing cache rows for this vocabulary
        if (Schema::hasTable('vocabulary_label_cache')) {
            $deleted = DB::table('vocabulary_label_cache')
                ->where('source_vocabulary', $vocab)
                ->delete();
            if ($deleted > 0) {
                $this->info("  ✓ invalidated {$deleted} stale cache rows for {$vocab}");
            }
        }

        // 5) Pre-warm the cache by querying every (subject, prefLabel, lang) triple
        if (! $noPrewarm) {
            $this->info('Priming vocabulary_label_cache...');
            $primed = $this->primeCache($queryEndpoint, $graph, $vocab);
            $this->info("  ✓ primed {$primed} (uri, culture) cache rows");
        } else {
            $this->info('Skipping prewarm (--no-prewarm)');
        }

        $this->newLine();
        $this->info("Done. Vocabulary `{$vocab}` is live.");
        $this->line("Test: php artisan tinker  →  app(\\AhgCore\\Services\\VocabularyResolverService::class)->preferredLabel('<some-uri>')");

        return self::SUCCESS;
    }

    private function fetchSource(string $source): ?string
    {
        if (preg_match('#^https?://#i', $source)) {
            try {
                $resp = Http::timeout(30)->withHeaders(['Accept' => '*/*'])->get($source);
                if (! $resp->successful()) {
                    $this->error("HTTP {$resp->status()} fetching {$source}");
                    return null;
                }
                return (string) $resp->body();
            } catch (\Throwable $e) {
                $this->error('Fetch exception: ' . $e->getMessage());
                return null;
            }
        }
        if (! is_file($source)) {
            $this->error("File not found: {$source}");
            return null;
        }
        return (string) @file_get_contents($source);
    }

    private function primeCache(string $queryEndpoint, string $graph, string $vocab): int
    {
        $sparql = sprintf(
            'PREFIX skos: <http://www.w3.org/2004/02/skos/core#>'
            . ' PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>'
            . ' SELECT ?uri ?lang ?pref (GROUP_CONCAT(DISTINCT ?alt; separator="") AS ?alts) WHERE {'
            . '   GRAPH <%s> {'
            . '     { ?uri skos:prefLabel ?pref } UNION { ?uri rdfs:label ?pref }'
            . '     BIND(LANG(?pref) AS ?lang)'
            . '     OPTIONAL { ?uri skos:altLabel ?alt . FILTER (LANG(?alt) = ?lang) }'
            . '   }'
            . ' } GROUP BY ?uri ?lang ?pref',
            addslashes($graph)
        );

        // Auth resolution: --fuseki-user/--fuseki-password flag > RIC_FUSEKI_* env > FUSEKI_* env > none
        $fusekiUser = (string) ($this->option('fuseki-user')
            ?: env('RIC_FUSEKI_USER')
            ?: config('ahg-ric.ric_to_atom_sync.fuseki.user')
            ?: config('ric.fuseki.user', ''));
        $fusekiPassword = (string) ($this->option('fuseki-password')
            ?: env('RIC_FUSEKI_PASS')
            ?: config('ahg-ric.ric_to_atom_sync.fuseki.pass')
            ?: config('ric.fuseki.password', ''));
        try {
            $req = Http::timeout(120)
                ->withHeaders(['Accept' => 'application/sparql-results+json'])
                ->asForm();
            if ($fusekiUser !== '') {
                $req = $req->withBasicAuth($fusekiUser, $fusekiPassword);
            }
            $resp = $req->post($queryEndpoint, ['query' => $sparql]);
            if (! $resp->successful()) {
                $this->warn('SPARQL prewarm failed: HTTP ' . $resp->status());
                return 0;
            }
            $rows = $resp->json('results.bindings') ?? [];
        } catch (\Throwable $e) {
            $this->warn('SPARQL prewarm exception: ' . $e->getMessage());
            return 0;
        }

        $count = 0;
        foreach ($rows as $r) {
            $uri = $r['uri']['value'] ?? '';
            $lang = $r['lang']['value'] ?? '';
            if ($lang === '') $lang = 'en';
            $pref = $r['pref']['value'] ?? '';
            $altsRaw = $r['alts']['value'] ?? '';
            $alts = $altsRaw === '' ? [] : explode("\x1f", $altsRaw);

            DB::table('vocabulary_label_cache')->updateOrInsert(
                ['uri' => $uri, 'culture' => $lang],
                [
                    'preferred_label' => $pref,
                    'alt_labels' => json_encode($alts, JSON_UNESCAPED_UNICODE),
                    'source_vocabulary' => $vocab,
                    'sparql_endpoint' => $queryEndpoint,
                    'expires_at' => null,  // pre-warmed entries don't expire
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $count++;
        }
        return $count;
    }
}
