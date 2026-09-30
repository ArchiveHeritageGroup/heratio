<?php

/**
 * MigrateStarCommand - Heratio
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

namespace AhgProvenanceAi\Console\Commands;

use AhgRic\Services\SparqlUpdateService;
use AhgCore\Support\ReifiedStatement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * heratio#1517 - one-off migration of RDF-star quoted triples to plain
 * rdf:Statement nodes, so the dataset loads in stores without RDF-star
 * (QLever stops at the first '<<').
 *
 * Per named graph: SELECT every `<< s p o >> ap ao`, INSERT the statement
 * node (ReifiedStatement - the same IRI the writers now mint) with each
 * annotation moved onto it, then DELETE the quoted-triple annotations. Both
 * run as one SPARQL UPDATE request per graph, so a graph is either fully
 * migrated or untouched.
 *
 * Generic over graphs: it converts provenance-ai inference graphs and the
 * authority-resolution decision / field-provenance graphs alike. A graph
 * whose annotations include a blank node or a nested quoted triple cannot be
 * written back with INSERT DATA; it is reported and left as is.
 *
 * Idempotent: a migrated graph has no quoted triples left to find, and the
 * statement IRIs are deterministic, so a re-run is a no-op.
 *
 * Dry run by default - pass --apply to write.
 */
class MigrateStarCommand extends Command
{
    protected $signature = 'ahg:provenance-ai:migrate-star
                            {--apply : write the changes (without it the command only reports)}
                            {--dry-run : report counts only, the default}
                            {--graph= : migrate only this named graph}
                            {--limit=0 : max graphs to process this run (0 = all)}
                            {--endpoint= : Fuseki dataset URL, e.g. http://localhost:3030/ric (default: the configured update endpoint)}';

    protected $description = 'Convert RDF-star quoted-triple provenance to plain rdf:Statement nodes (heratio#1517).';

    public function handle(SparqlUpdateService $upd): int
    {
        $apply = (bool) $this->option('apply') && ! $this->option('dry-run');
        $base = rtrim((string) ($this->option('endpoint') ?: preg_replace('#/update/?$#', '', $upd->updateEndpoint())), '/');
        $auth = [$upd->username(), $upd->password()];
        $limit = max(0, (int) $this->option('limit'));

        $graphFilter = $this->option('graph') ? 'FILTER(?g = <'.$this->option('graph').'>)' : '';
        $graphs = $this->select($base, $auth,
            "SELECT ?g (COUNT(*) AS ?n) WHERE { GRAPH ?g { ?q ?ap ?ao FILTER(isTRIPLE(?q)) } {$graphFilter} }"
            .' GROUP BY ?g ORDER BY ?g'.($limit > 0 ? " LIMIT {$limit}" : ''));
        if ($graphs === null) {
            return self::FAILURE;
        }

        $this->line(sprintf('[provenance-ai-migrate-star] %s %s graphs=%d', $apply ? 'APPLY' : 'DRY-RUN', $base, count($graphs)));

        $stats = ['graphs' => 0, 'quoted' => 0, 'statements' => 0, 'inserted' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($graphs as $row) {
            $g = (string) ($row['g']['value'] ?? '');
            $rows = $this->select($base, $auth,
                "SELECT ?s ?p ?o ?ap ?ao WHERE { GRAPH <{$g}> { ?q ?ap ?ao FILTER(isTRIPLE(?q))"
                .' BIND(SUBJECT(?q) AS ?s) BIND(PREDICATE(?q) AS ?p) BIND(OBJECT(?q) AS ?o) } }');
            $plan = $rows === null ? null : self::planGraph($g, $rows);
            if ($plan === null) {
                $stats['skipped']++;
                $this->warn("  skipped {$g}: blank node or nested quoted triple, left as is");

                continue;
            }

            $stats['graphs']++;
            $stats['quoted'] += $plan['quoted'];
            $stats['statements'] += $plan['statements'];
            $stats['inserted'] += $plan['triples'];
            if ($this->getOutput()->isVerbose()) {
                $this->line(sprintf('  %s quoted=%d statements=%d', $g, $plan['quoted'], $plan['statements']));
            }
            if (! $apply) {
                continue;
            }

            $update = "INSERT DATA { GRAPH <{$g}> {\n{$plan['insert']}} } ;\n"
                ."DELETE { GRAPH <{$g}> { ?q ?ap ?ao } } WHERE { GRAPH <{$g}> { ?q ?ap ?ao FILTER(isTRIPLE(?q)) } }";
            $res = $this->http($auth)->withBody($update, 'application/sparql-update; charset=utf-8')->post($base.'/update');
            if (! $res->successful()) {
                $stats['failed']++;
                $this->error("  failed {$g}: HTTP ".$res->status().' '.mb_substr($res->body(), 0, 200));
            }
        }

        $this->line(sprintf(
            '[provenance-ai-migrate-star] %s graphs=%d quoted_annotations=%d statement_nodes=%d triples_to_insert=%d skipped=%d failed=%d',
            $apply ? 'APPLY' : 'DRY-RUN',
            $stats['graphs'], $stats['quoted'], $stats['statements'], $stats['inserted'], $stats['skipped'], $stats['failed']
        ));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Pure planner: the INSERT DATA body for one graph's quoted-triple rows
     * (SPARQL JSON bindings with s, p, o, ap, ao). Returns null when any term
     * cannot be written back, so the caller leaves the whole graph alone.
     *
     * @return array{insert:string,quoted:int,statements:int,triples:int}|null
     */
    public static function planGraph(string $graphUri, array $rows): ?array
    {
        $nodes = [];
        $lines = [];
        foreach ($rows as $b) {
            $terms = array_map(fn ($k) => ReifiedStatement::fromBinding($b[$k] ?? null), ['s', 'p', 'o', 'ap', 'ao']);
            if (in_array(null, $terms, true)) {
                return null;
            }
            [$s, $p, $o, $ap, $ao] = $terms;
            $node = ReifiedStatement::node($graphUri, $s, $p, $o);
            if (! isset($nodes[$node])) {
                $nodes[$node] = ReifiedStatement::describe($node, $s, $p, $o);
            }
            $lines["<{$node}> {$ap} {$ao} ."] = true;
        }

        return [
            'insert' => implode('', $nodes).implode("\n", array_keys($lines))."\n",
            'quoted' => count($rows),
            'statements' => count($nodes),
            'triples' => count($nodes) * 4 + count($lines),
        ];
    }

    private function http(array $auth)
    {
        $client = Http::timeout(120);
        if ($auth[0] !== null && $auth[1] !== null) {
            $client = $client->withBasicAuth($auth[0], $auth[1]);
        }

        return $client;
    }

    /**
     * Read-only SELECT against the dataset; returns the bindings or null.
     */
    private function select(string $base, array $auth, string $sparql): ?array
    {
        $res = $this->http($auth)->asForm()
            ->withHeaders(['Accept' => 'application/sparql-results+json'])
            ->post($base.'/sparql', ['query' => $sparql]);
        if (! $res->successful()) {
            $this->error('SELECT failed: HTTP '.$res->status().' '.mb_substr($res->body(), 0, 200));

            return null;
        }

        return $res->json('results.bindings') ?? [];
    }
}
