<?php

/**
 * FusekiDedupeBlankNodesCommand - Heratio
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

namespace AhgRic\Console\Commands;

use AhgRic\Services\SparqlUpdateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * heratio#1519 - collapse the duplicate blank-node subtrees that repeated
 * ric_sync.sh loads left in the default graph.
 *
 * Every load POSTed a fonds' JSON-LD to /data, which adds rather than
 * replaces. Named IRIs merge, but each blank node (a name, a date range, an
 * extent and its carrier type) is new each time, so every run added another
 * copy: corporatebody/731 carried 31,660 identical name nodes.
 *
 * Per batch of entities: CONSTRUCT each entity's blank-node subtrees, keep one
 * subtree per (entity, predicate, content) - content compared recursively, so
 * only exact copies collapse - then DELETE the subtrees and INSERT DATA the
 * kept ones in one UPDATE request, so a batch is done whole or not at all.
 * Subtrees that differ in content are all kept and counted as variants.
 *
 * Blank nodes in this graph are never shared between parents (checked on the
 * 30 Sep 2026 data), which is what makes per-entity replacement safe. The
 * default graph only: named graphs (provenance, live sync) are not touched.
 * Idempotent: a deduplicated entity plans no removals.
 *
 * Dry run by default - pass --apply to write. Afterwards compact the TDB2
 * store and rebuild the text index; see docs/help/ric-sync-setup.md.
 */
class FusekiDedupeBlankNodesCommand extends Command
{
    protected $signature = 'ahg:fuseki-dedupe-blank-nodes
                            {--apply : write the changes (without it the command only reports)}
                            {--dry-run : report counts only, the default}
                            {--entity= : process only this entity IRI}
                            {--batch=25 : entities per CONSTRUCT / UPDATE request}
                            {--limit=0 : max entities to process this run (0 = all)}
                            {--endpoint= : Fuseki dataset URL, e.g. http://localhost:3030/ric (default: the configured update endpoint)}';

    protected $description = 'Collapse duplicate blank-node subtrees (names, dates, extents) in the RiC default graph (heratio#1519).';

    /**
     * Predicates the RiC extractor points at blank nodes. Used only to find
     * the owning entities cheaply; once found, all of an entity's blank
     * children are deduplicated whatever the predicate.
     */
    private const LINK_PREDICATES = [
        'hasAgentName', 'hasOrHadName', 'hasPlaceName', 'isOrWasAssociatedWithDate',
        'hasExtent', 'hasCarrierType', 'hasOrHadLocation',
    ];

    /** Any predicate, as a property path: every node reachable from ?n. */
    private const SUBTREE = '(<urn:heratio:any>|!<urn:heratio:any>)*';

    public function handle(SparqlUpdateService $upd): int
    {
        $apply = (bool) $this->option('apply') && ! $this->option('dry-run');
        $base = rtrim((string) ($this->option('endpoint') ?: preg_replace('#/update/?$#', '', $upd->updateEndpoint())), '/');
        $auth = [$upd->username(), $upd->password()];
        $batch = max(1, (int) $this->option('batch'));
        $limit = max(0, (int) $this->option('limit'));

        $entities = $this->option('entity') ? [(string) $this->option('entity')] : $this->owningEntities($base, $auth);
        if ($entities === null) {
            return self::FAILURE;
        }
        if ($limit > 0) {
            $entities = array_slice($entities, 0, $limit);
        }

        $mode = $apply ? 'APPLY' : 'DRY-RUN';
        $this->line(sprintf('[ric-dedupe-blank-nodes] %s %s entities=%d', $mode, $base, count($entities)));

        $stats = ['links' => 0, 'kept' => 0, 'triples_before' => 0, 'triples_after' => 0, 'variants' => 0, 'failed' => 0];
        foreach (array_chunk($entities, $batch) as $chunk) {
            $values = implode(' ', array_map(fn ($e) => '<'.$e.'>', $chunk));
            $where = "VALUES ?e { {$values} } ?e ?l ?n FILTER(isBlank(?n)) ?n ".self::SUBTREE.' ?m . ?m ?p ?o';

            $res = $this->http($auth)->asForm()->withHeaders(['Accept' => 'application/n-triples'])
                ->post($base.'/sparql', ['query' => "CONSTRUCT { ?e ?l ?n . ?m ?p ?o } WHERE { {$where} }"]);
            if (! $res->successful()) {
                $stats['failed']++;
                $this->error('  CONSTRUCT failed: HTTP '.$res->status().' '.mb_substr($res->body(), 0, 200));

                continue;
            }

            $plan = self::plan($res->body());
            foreach (['links', 'kept', 'triples_before', 'triples_after', 'variants'] as $k) {
                $stats[$k] += $plan[$k];
            }
            if ($this->getOutput()->isVerbose()) {
                $this->line(sprintf('  batch of %d: links %d -> %d, triples %d -> %d',
                    count($chunk), $plan['links'], $plan['kept'], $plan['triples_before'], $plan['triples_after']));
            }
            if (! $apply || $plan['links'] === $plan['kept']) {
                continue;
            }

            $update = "DELETE { ?e ?l ?n . ?m ?p ?o } WHERE { {$where} } ;\nINSERT DATA {\n{$plan['insert']}}";
            $res = $this->http($auth)->withBody($update, 'application/sparql-update; charset=utf-8')->post($base.'/update');
            if (! $res->successful()) {
                $stats['failed']++;
                $this->error('  UPDATE failed: HTTP '.$res->status().' '.mb_substr($res->body(), 0, 200));
            }
        }

        $this->line(sprintf(
            '[ric-dedupe-blank-nodes] %s blank_links=%d kept=%d triples_before=%d triples_after=%d distinct_variants=%d failed=%d',
            $mode, $stats['links'], $stats['kept'], $stats['triples_before'], $stats['triples_after'], $stats['variants'], $stats['failed']
        ));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Pure planner over the N-Triples of some entities' blank-node subtrees
     * (entity -> blank links plus every triple of each subtree). Keeps the
     * first subtree per (entity, predicate, content signature) and returns
     * the INSERT DATA body that writes exactly the kept ones back.
     *
     * 'variants' counts (entity, predicate) pairs still holding more than one
     * distinct subtree - different content, which this never merges.
     *
     * @return array{insert:string,links:int,kept:int,triples_before:int,triples_after:int,variants:int}
     */
    public static function plan(string $ntriples): array
    {
        $links = [];   // [subject, predicate, blank child]
        $props = [];   // blank node => list of [predicate, raw object]
        $lines = [];   // blank node => its raw N-Triples lines
        $before = 0;

        foreach (preg_split('/\R/', $ntriples) as $line) {
            if (! preg_match('/^(\S+)\s+(<[^>]*>)\s+(.+?)\s*\.\s*$/', $line, $m)) {
                continue;
            }
            $before++;
            [, $s, $p, $o] = $m;
            if (str_starts_with($s, '_:')) {
                $props[$s][] = [$p, $o];
                $lines[$s][] = "{$s} {$p} {$o} .";
            } elseif (str_starts_with($o, '_:')) {
                $links[] = [$s, $p, $o];
            }
        }

        $memo = [];
        $sig = function (string $b) use (&$sig, &$memo, $props): string {
            if (isset($memo[$b])) {
                return $memo[$b];
            }
            $memo[$b] = '';   // guards a cycle, which a tree cannot have
            $parts = [];
            foreach ($props[$b] ?? [] as [$p, $o]) {
                $parts[] = $p.' '.(str_starts_with($o, '_:') ? '_:'.$sig($o) : $o);
            }
            sort($parts);

            return $memo[$b] = sha1(implode("\n", $parts));
        };
        $subtree = function (string $b) use (&$subtree, $props, $lines): array {
            $out = $lines[$b] ?? [];
            foreach ($props[$b] ?? [] as [, $o]) {
                if (str_starts_with($o, '_:')) {
                    array_push($out, ...$subtree($o));
                }
            }

            return $out;
        };

        $kept = [];
        $distinct = [];
        $insert = [];
        foreach ($links as [$s, $p, $b]) {
            $content = $sig($b);
            $distinct["{$s} {$p}"][$content] = true;
            if (isset($kept["{$s} {$p} {$content}"])) {
                continue;
            }
            $kept["{$s} {$p} {$content}"] = true;
            $insert[] = "{$s} {$p} {$b} .";
            array_push($insert, ...$subtree($b));
        }

        return [
            'insert' => $insert === [] ? '' : implode("\n", $insert)."\n",
            'links' => count($links),
            'kept' => count($kept),
            'triples_before' => $before,
            'triples_after' => count($insert),
            'variants' => count(array_filter($distinct, fn ($c) => count($c) > 1)),
        ];
    }

    /**
     * IRIs that own at least one blank child through a known link predicate.
     *
     * @return list<string>|null
     */
    private function owningEntities(string $base, array $auth): ?array
    {
        $found = [];
        foreach (self::LINK_PREDICATES as $pred) {
            $res = $this->http($auth)->asForm()->withHeaders(['Accept' => 'application/sparql-results+json'])
                ->post($base.'/sparql', ['query' => 'SELECT DISTINCT ?e WHERE { ?e <https://www.ica.org/standards/RiC/ontology#'
                    .$pred.'> ?n FILTER(isIRI(?e) && isBlank(?n)) }']);
            if (! $res->successful()) {
                $this->error("SELECT failed for rico:{$pred}: HTTP ".$res->status().' '.mb_substr($res->body(), 0, 200));

                return null;
            }
            foreach ($res->json('results.bindings') ?? [] as $row) {
                $found[$row['e']['value']] = true;
            }
        }

        return array_keys($found);
    }

    private function http(array $auth)
    {
        $client = Http::timeout(600);
        if ($auth[0] !== null && $auth[1] !== null) {
            $client = $client->withBasicAuth($auth[0], $auth[1]);
        }

        return $client;
    }
}
