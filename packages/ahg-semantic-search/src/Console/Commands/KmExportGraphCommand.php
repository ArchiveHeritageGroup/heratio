<?php

/**
 * KmExportGraphCommand - export a BOUNDED digest of the unified graph to KM.
 *
 * The cross-link half of heratio#1197 (the unified G/L/A/M knowledge graph) and
 * #1214 item 6. Where KmGraphSyncCommand writes one doc per record, this command
 * produces a bounded, graph-wide digest: a single overview file plus the top-N
 * most-connected entities (default 50, hard ceiling enforced by the bridge), each
 * rendered as plain-prose cross-collection connections a RAG can ground on. The
 * markdown lands under docs/reference/km-graph/ where the KM inotify watcher
 * auto-ingests docs/ within ~2-3 minutes (no curl, no manual trigger).
 *
 * The run is bounded HARD: --limit caps how many entity digests are written,
 * the bridge clamps that to its own ceiling, and the command echoes exactly how
 * many entities were written and how many connected records were skipped - it
 * never silently dumps thousands of files. Read-only over the graph; the only
 * writes are the digest files under docs/reference/km-graph/.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
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

namespace AhgSemanticSearch\Console\Commands;

use AhgSemanticSearch\Services\GraphKmBridgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KmExportGraphCommand extends Command
{
    protected $signature = 'ahg:km-export-graph
        {--limit=50 : How many of the most-connected entities to write (clamped to the bridge ceiling)}
        {--dir=km-graph : Subdirectory under docs/reference/ to write the digest into}
        {--dry-run : Report what would be written without creating any files}';

    protected $description = 'Export a bounded digest of the unified cross-collection graph to KM-ingestable markdown (heratio#1197/#1214)';

    public function handle(GraphKmBridgeService $bridge): int
    {
        $requested = (int) $this->option('limit');
        if ($requested < 1) {
            $requested = 50;
        }
        $ceiling = $bridge->maxEntities();
        $limit = min($requested, $ceiling);

        if ($requested > $ceiling) {
            $this->warn(sprintf(
                'Requested --limit=%d exceeds the export ceiling of %d; clamping to %d.',
                $requested,
                $ceiling,
                $ceiling
            ));
        }

        $totalConnected = $bridge->connectedEntityCount();
        if ($totalConnected === 0) {
            $this->warn('No information objects participate in any relation - nothing to export.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Connected information objects in graph: %d. Writing top %d most-connected.',
            $totalConnected,
            $limit
        ));

        $top = $bridge->topConnectedEntities($limit);
        if (! $top) {
            $this->warn('Ranking returned no entities - nothing to export.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $sub = trim((string) $this->option('dir'), '/');
        if ($sub === '' || ! preg_match('/^[A-Za-z0-9_\-]+$/', $sub)) {
            $sub = 'km-graph';
        }
        $dir = base_path('docs/reference/'.$sub);

        if (! $dryRun && ! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        // --- Per-entity digests --------------------------------------------
        $written = 0;
        $empty = 0;
        $entries = [];

        foreach ($top as $entity) {
            $summary = $bridge->connectionsSummary((int) $entity['id']);
            if ($summary === null) {
                // A ranked record whose neighbours did not resolve to names.
                $empty++;

                continue;
            }

            $entries[] = $entity + ['summary' => $summary];

            if ($dryRun) {
                $written++; // would-write

                continue;
            }

            $path = $this->writeEntityDigest($bridge, $dir, $sub, $entity, $summary);
            if ($path !== null) {
                $written++;
            } else {
                $empty++;
            }
        }

        // --- Overview digest ------------------------------------------------
        $skipped = max(0, $totalConnected - $written);
        if (! $dryRun) {
            $overviewPath = $this->writeOverview($dir, $sub, $entries, $totalConnected, $written, $skipped);
            $this->line('<info>Overview:</info> '.$overviewPath);
        }

        // --- Bounded reporting (no silent truncation) -----------------------
        $verb = $dryRun ? 'would write' : 'wrote';
        $this->newLine();
        $this->info(sprintf(
            'Done: %s %d entity digest(s) + 1 overview; %d ranked record(s) had no resolvable connections.',
            $verb,
            $written,
            $empty
        ));
        $this->warn(sprintf(
            'BOUNDED EXPORT: %d connected record(s) exist in the graph; this run covers the top %d. %d record(s) were NOT exported.',
            $totalConnected,
            $written,
            $skipped
        ));

        Log::info('ahg:km-export-graph run', [
            'connected_total' => $totalConnected,
            'limit_requested' => $requested,
            'limit_effective' => $limit,
            'written' => $written,
            'no_connections' => $empty,
            'skipped_beyond_limit' => $skipped,
            'dry_run' => $dryRun,
        ]);

        if (! $dryRun && $written > 0) {
            $this->comment('The KM inotify watcher will ingest these docs in ~2-3 minutes (no manual trigger needed).');
        }

        return self::SUCCESS;
    }

    /**
     * Write one bounded per-entity digest. Plain catalogue prose only - the
     * summary is rendered by GraphKmBridgeService from the relation graph, no
     * AI generation. Returns the path written, or null on failure.
     *
     * @param  array{id:int,title:string,slug:?string,degree:int,crossDomains:int}  $entity
     */
    protected function writeEntityDigest(
        GraphKmBridgeService $bridge,
        string $dir,
        string $sub,
        array $entity,
        string $summary
    ): ?string {
        $id = (int) $entity['id'];
        $title = (string) $entity['title'];
        $slug = $entity['slug'] ?? null;

        $fileBase = $slug !== null && $slug !== '' ? Str::slug($slug) : (string) $id;
        if ($fileBase === '') {
            $fileBase = (string) $id;
        }
        // Always suffix the id so two records that slugify the same never collide.
        $path = $dir.'/entity-'.$fileBase.'-'.$id.'.md';

        $lines = [];
        $lines[] = '# Graph entity: '.$title;
        $lines[] = '';
        $lines[] = '**Summary:** '.$summary;
        $lines[] = '';
        $lines[] = '## Entity';
        $lines[] = '';
        $lines[] = '- Name: '.$title;
        $lines[] = '- Type: archival record (information object)';
        if ($slug !== null && $slug !== '') {
            $lines[] = '- Record page: `/'.$slug.'`';
        }
        $lines[] = '- Relation edges in graph: '.(int) $entity['degree'];
        $lines[] = '- Collection domains it links into: '.(int) $entity['crossDomains'];
        $lines[] = '';
        $lines[] = '## Cross-collection connections';
        $lines[] = '';

        $neighbours = app(\AhgRic\Services\RelationshipService::class)
            ->crossCollectionNeighbours($id);
        $groups = $neighbours['groups'] ?? [];
        if ($groups) {
            foreach ($groups as $group) {
                $domain = (string) ($group['domain'] ?? 'Other');
                $items = $group['items'] ?? [];
                $count = (int) ($group['count'] ?? count($items));
                if (! $items) {
                    continue;
                }
                $names = [];
                foreach ($items as $item) {
                    $name = trim((string) ($item['name'] ?? ''));
                    if ($name !== '') {
                        $names[] = $name;
                    }
                    if (count($names) >= 8) {
                        break;
                    }
                }
                if (! $names) {
                    continue;
                }
                $shown = implode(', ', $names);
                $remainder = $count - count($names);
                $tail = $remainder > 0 ? ', and '.$remainder.' more' : '';
                $lines[] = '- **'.$domain.'** ('.$count.'): '.$shown.$tail.'.';
            }
        } else {
            $lines[] = '- No resolvable cross-collection connections.';
        }
        $lines[] = '';
        $lines[] = '## Provenance';
        $lines[] = '';
        $lines[] = '- Record id: '.$id;
        if ($slug !== null && $slug !== '') {
            $lines[] = '- Record slug: '.$slug;
        }
        $lines[] = '- Generated by: ahg:km-export-graph (GraphKmBridgeService)';
        $lines[] = '- Content: plain catalogue facts from the RiC relation graph; no AI-generated text.';
        $lines[] = '';

        $ok = @file_put_contents($path, implode("\n", $lines));

        return $ok === false ? null : $path;
    }

    /**
     * Write the single overview digest that lists every entity covered this run
     * with its degree and a one-line connection summary. This is the entry
     * point a KM query lands on when asking "what connects across collections".
     *
     * @param  array<int,array<string,mixed>>  $entries
     */
    protected function writeOverview(
        string $dir,
        string $sub,
        array $entries,
        int $totalConnected,
        int $written,
        int $skipped
    ): string {
        $path = $dir.'/_overview.md';

        $lines = [];
        $lines[] = '# Unified graph: most-connected entities (cross-collection digest)';
        $lines[] = '';
        $lines[] = '**Summary:** This digest names the most cross-connected records in the unified '
            .'Gallery/Library/Archive/Museum knowledge graph and, for each, the other records, people, '
            .'organisations, repositories, subjects, places and accessions it is linked to across the '
            .'collection. It lets a plain-language query surface connections that span collection '
            .'boundaries (for example "what else is connected to a given person, place or record"). '
            .'Generated from the RiC relation graph - plain catalogue facts only, no AI-generated content.';
        $lines[] = '';
        $lines[] = '## Coverage';
        $lines[] = '';
        $lines[] = '- Connected records in the graph: '.$totalConnected;
        $lines[] = '- Entities covered in this digest: '.$written;
        $lines[] = '- Records beyond this bounded run (not exported here): '.$skipped;
        $lines[] = '- Ranking: by relation-edge degree, highest first.';
        $lines[] = '';
        $lines[] = '## Most-connected entities';
        $lines[] = '';

        if ($entries) {
            $rank = 0;
            foreach ($entries as $e) {
                $rank++;
                $title = (string) ($e['title'] ?? ('Record #'.($e['id'] ?? '')));
                $degree = (int) ($e['degree'] ?? 0);
                $domains = (int) ($e['crossDomains'] ?? 0);
                $slug = $e['slug'] ?? null;
                $link = ($slug !== null && $slug !== '') ? ' (record page `/'.$slug.'`)' : '';
                $lines[] = '### '.$rank.'. '.$title.$link;
                $lines[] = '';
                $lines[] = sprintf(
                    '%d relation edge(s) across %d collection domain(s).',
                    $degree,
                    $domains
                );
                $lines[] = '';
                if (! empty($e['summary'])) {
                    $lines[] = (string) $e['summary'];
                    $lines[] = '';
                }
            }
        } else {
            $lines[] = '_No entities were rendered in this run._';
            $lines[] = '';
        }

        $lines[] = '## How this digest is produced';
        $lines[] = '';
        $lines[] = '- Source: the generic relation table joined to the per-domain i18n name tables '
            .'(records, agents, repositories, subjects/places, accessions) plus RiC-native entity '
            .'labels. Read-only.';
        $lines[] = '- Bounded: the export writes at most the top-N most-connected records (this run: '
            .$written.'); the rest are reported as skipped, never silently dropped.';
        $lines[] = '- Per-entity detail: see the `entity-*.md` files alongside this overview.';
        $lines[] = '- Generated by: ahg:km-export-graph (heratio#1197 / #1214).';
        $lines[] = '';

        @file_put_contents($path, implode("\n", $lines));

        return $path;
    }
}
