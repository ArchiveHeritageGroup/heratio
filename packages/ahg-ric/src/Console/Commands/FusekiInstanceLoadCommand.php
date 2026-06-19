<?php

/**
 * FusekiInstanceLoadCommand - Heratio
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
use AhgRic\Support\RicGraphManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * heratio#139 - publish RiC instance data into the /openric-model Fuseki
 * dataset so the authority-resolution engine's Fuseki candidate adapters
 * (FusekiAgentAdapter / FusekiPlaceAdapter) have something to match.
 *
 * Before this, /openric-model held only the RiC-O ontology - zero instances -
 * so the Fuseki candidate source always returned []. This command bulk-loads
 * every named actor as a rico:Agent (rico:Person / CorporateBody / Family) and
 * every place term as a rico:Place.
 *
 * Placement: the instances are written to the **default graph**. A live probe
 * showed the ontology sits in the default graph and the dataset does not union
 * named graphs, while the adapters query without a GRAPH clause - so default
 * graph is the only place the adapters can see.
 *
 * Idempotent: each instance's triples are DELETEd before re-INSERT, keyed on
 * its stable URN, so the command can be re-run as a sync.
 */
class FusekiInstanceLoadCommand extends Command
{
    protected $signature = 'ahg:ric:fuseki-load
                            {--agents-only : Load only rico:Agent instances}
                            {--places-only : Load only rico:Place instances}
                            {--limit= : Cap rows processed per entity kind (testing)}
                            {--batch=200 : Entities per Fuseki update request}
                            {--dry-run : Build the SPARQL update and report counts without writing}';

    protected $description = 'Publish RiC entities (agents, places, rules, activities, instantiations, relations) into the Fuseki graph per the projection manifest (ADR-0003; heratio#139/#1319).';

    /** RiC-O ontology namespace. */
    private const RICO = 'https://www.ica.org/standards/RiC/ontology#';

    /** Taxonomy id holding place terms (TAXONOMY_PLACE_ID). */
    private const PLACE_TAXONOMY_ID = 42;

    /** entity-type term name (lowercased) -> RiC agent subclass. */
    private const AGENT_TYPE_MAP = [
        'corporate body' => 'CorporateBody',
        'person'         => 'Person',
        'family'         => 'Family',
    ];

    public function handle(SparqlUpdateService $sparql): int
    {
        $batch      = max(1, (int) $this->option('batch'));
        $limit      = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $dryRun     = (bool) $this->option('dry-run');
        $agentsOnly = (bool) $this->option('agents-only');
        $placesOnly = (bool) $this->option('places-only');

        $loaded = 0;
        $failed = 0;

        if (!$placesOnly) {
            [$ok, $bad] = $this->loadEntities('agents', $this->buildAgentEntities($limit), $batch, $dryRun, $sparql);
            $loaded += $ok;
            $failed += $bad;
        }
        if (!$agentsOnly) {
            [$ok, $bad] = $this->loadEntities('places', $this->buildPlaceEntities($limit), $batch, $dryRun, $sparql);
            $loaded += $ok;
            $failed += $bad;
        }

        // RiC-native sidecar types + relations (ADR-0003 manifest). Loaded on a
        // full run (neither --agents-only nor --places-only).
        if (!$agentsOnly && !$placesOnly) {
            foreach ([
                ['rule', 'ric_rule', 'Rule'],
                ['activity', 'ric_activity', 'Activity'],
                ['instantiation', 'ric_instantiation', 'Instantiation'],
                ['relation', 'relation', 'Relation'],
            ] as [$type, $table, $ricClass]) {
                [$ok, $bad] = $this->loadEntities($type, $this->buildTypedEntities($type, $table, $ricClass, $limit), $batch, $dryRun, $sparql);
                $loaded += $ok;
                $failed += $bad;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d instance(s) %s, %d failed.',
            $dryRun ? 'DRY-RUN:' : 'Done:',
            $loaded,
            $dryRun ? 'would be loaded' : 'loaded',
            $failed
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Every named actor as a rico:Agent instance. Read from the actor's
     * source-culture i18n row (its canonical authorized form of name); the
     * agent subclass comes from actor.entity_type_id.
     *
     * @return array<int,array{uri:string,turtle:string}>
     */
    private function buildAgentEntities(?int $limit): array
    {
        $q = DB::table('actor as a')
            ->join('actor_i18n as ai', function ($j) {
                $j->on('ai.id', '=', 'a.id')->on('ai.culture', '=', 'a.source_culture');
            })
            ->leftJoin('term_i18n as et', function ($j) {
                $j->on('et.id', '=', 'a.entity_type_id')->where('et.culture', '=', 'en');
            })
            ->whereNotNull('ai.authorized_form_of_name')
            ->where('ai.authorized_form_of_name', '<>', '')
            ->orderBy('a.id')
            ->select('a.id', 'ai.authorized_form_of_name as name', 'et.name as etype');
        if ($limit !== null) {
            $q->limit($limit);
        }

        $entities = [];
        foreach ($q->get() as $row) {
            $ricType = self::AGENT_TYPE_MAP[strtolower(trim((string) ($row->etype ?? '')))] ?? 'Agent';
            $uri = RicGraphManifest::iri('agent', (int) $row->id);
            $entities[] = [
                'uri'    => $uri,
                'turtle' => '<' . $uri . '> a rico:' . $ricType
                          . ' ; rico:name ' . $this->ttlLiteral((string) $row->name) . ' .',
            ];
        }

        return $entities;
    }

    /**
     * Every ric_place row as a rico:Place instance. Canonical Place source is
     * ric_place (ADR-0003 / RicGraphManifest), NOT the term taxonomy. The
     * display name comes from the linked term (ric_place.term_id).
     *
     * @return array<int,array{uri:string,turtle:string}>
     */
    private function buildPlaceEntities(?int $limit): array
    {
        // Name via the linked term. Join through `term` and match on
        // term.source_culture (same collation as term_i18n) - NOT on
        // ric_place.source_culture, which is a different collation and triggers
        // MySQL error 1267 (illegal mix of collations) on the culture compare.
        $q = DB::table('ric_place as rp')
            ->leftJoin('term as t', 't.id', '=', 'rp.term_id')
            ->leftJoin('term_i18n as ti', function ($j) {
                $j->on('ti.id', '=', 't.id')->on('ti.culture', '=', 't.source_culture');
            })
            ->orderBy('rp.id')
            ->select('rp.id', 'ti.name');
        if ($limit !== null) {
            $q->limit($limit);
        }

        $entities = [];
        foreach ($q->get() as $row) {
            $uri = RicGraphManifest::iri('place', (int) $row->id);
            $ttl = '<' . $uri . '> a rico:Place';
            if (! empty($row->name)) {
                $ttl .= ' ; rico:name ' . $this->ttlLiteral((string) $row->name);
            }
            $ttl .= ' .';
            $entities[] = ['uri' => $uri, 'turtle' => $ttl];
        }

        return $entities;
    }

    /**
     * Project a RiC-native sidecar table (ric_rule / ric_activity /
     * ric_instantiation) or the relation table as typed rico nodes per the
     * manifest. These tables carry no display label, so the baseline is the
     * typed node (urn:ahg:ric:<type>:<id> a rico:<Class>); richer predicates
     * (dates, connected entities) are a follow-up.
     *
     * @return array<int,array{uri:string,turtle:string}>
     */
    private function buildTypedEntities(string $type, string $table, string $ricClass, ?int $limit): array
    {
        $q = DB::table($table)->orderBy('id')->select('id');
        if ($limit !== null) {
            $q->limit($limit);
        }

        $entities = [];
        foreach ($q->get() as $row) {
            $uri = RicGraphManifest::iri($type, (int) $row->id);
            $entities[] = [
                'uri'    => $uri,
                'turtle' => '<' . $uri . '> a rico:' . $ricClass . ' .',
            ];
        }

        return $entities;
    }

    /**
     * Load one entity kind in batches. Returns [loaded, failed].
     *
     * @param array<int,array{uri:string,turtle:string}> $entities
     * @return array{0:int,1:int}
     */
    private function loadEntities(string $kind, array $entities, int $batch, bool $dryRun, SparqlUpdateService $sparql): array
    {
        $count = count($entities);
        if ($count === 0) {
            $this->warn("No {$kind} to load.");

            return [0, 0];
        }

        $this->line(sprintf('Loading %d %s into the /openric-model default graph ...', $count, $kind));

        $loaded = 0;
        $failed = 0;
        foreach (array_chunk($entities, $batch) as $i => $chunk) {
            $update = $this->buildUpdate($chunk);

            if ($dryRun) {
                if ($i === 0) {
                    $this->line('  --- dry-run: first ' . $kind . ' batch SPARQL update (truncated) ---');
                    $this->line('  ' . str_replace("\n", "\n  ", mb_substr($update, 0, 900)));
                    $this->line('  --- end sample ---');
                }
                $loaded += count($chunk);
                continue;
            }

            $result = $sparql->executeUpdate($update);
            if (!empty($result['ok'])) {
                $loaded += count($chunk);
            } else {
                $failed += count($chunk);
                $this->error(sprintf('  %s batch %d failed: %s', $kind, $i + 1, $result['error'] ?? 'unknown'));
            }
        }

        $this->info(sprintf('  %s: %d %s, %d failed.', $kind, $loaded, $dryRun ? 'pending' : 'loaded', $failed));

        return [$loaded, $failed];
    }

    /**
     * Build one SPARQL UPDATE for a batch: delete each instance's existing
     * triples (idempotency), then insert the fresh triples into the default
     * graph. The DELETE { } WHERE { VALUES ... } form is used because a bare
     * DELETE WHERE does not accept a VALUES block.
     *
     * @param array<int,array{uri:string,turtle:string}> $chunk
     */
    private function buildUpdate(array $chunk): string
    {
        $uris   = [];
        $turtle = [];
        foreach ($chunk as $entity) {
            $uris[]   = '<' . $entity['uri'] . '>';
            $turtle[] = '  ' . $entity['turtle'];
        }

        return 'PREFIX rico: <' . self::RICO . ">\n"
            . 'DELETE { ?s ?p ?o } WHERE { VALUES ?s { ' . implode(' ', $uris) . " } ?s ?p ?o } ;\n"
            . "INSERT DATA {\n" . implode("\n", $turtle) . "\n}";
    }

    /** A SPARQL/turtle string literal with the meaning-changing chars escaped. */
    private function ttlLiteral(string $s): string
    {
        return '"' . strtr($s, [
            '\\' => '\\\\',
            '"'  => '\\"',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
        ]) . '"';
    }
}
