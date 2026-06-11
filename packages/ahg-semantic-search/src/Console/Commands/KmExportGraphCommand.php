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
 * A quality filter (heratio#1220) keeps the digest worth ingesting: only
 * PUBLISHED records that genuinely bridge collections are exported; the
 * synthetic tree root and synthetic test/demo fixtures (titles like "AI Test
 * 19", "Test AI", "Ironman", "3D People") are excluded so they never pollute KM.
 *
 * DEVELOPER / DEPLOY-TIME COMMAND: run it as the repo owner, not via the web
 * worker. It writes under docs/reference/ which is intentionally NOT
 * www-data-writable; a failed mkdir/write now ERRORS loudly (non-zero exit)
 * rather than reporting a false success.
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
        {--limit=25 : How many of the most-connected QUALIFYING entities to write (clamped to the bridge ceiling)}
        {--dir=km-graph : Subdirectory under docs/reference/ to write the digest into}
        {--dry-run : Report what would be written without creating any files}';

    protected $description = 'Export a bounded, quality-filtered digest of the unified cross-collection graph to KM-ingestable markdown (heratio#1197/#1220)';

    public function handle(GraphKmBridgeService $bridge): int
    {
        $this->comment('Developer / deploy-time command: run as the repo owner. docs/reference is intentionally NOT www-data-writable.');

        $requested = (int) $this->option('limit');
        if ($requested < 1) {
            $requested = 25;
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
            'Connected information objects in graph: %d. Selecting up to %d most-connected QUALIFYING entities.',
            $totalConnected,
            $limit
        ));

        // --- Quality-filtered ranking (heratio#1220) -----------------------
        $ranking = $bridge->topConnectedEntitiesFiltered($limit);
        $top = $ranking['included'];

        // Loud, accounted report of WHY candidates were dropped.
        $this->line(sprintf(
            '<comment>Filter:</comment> considered %d candidate(s); excluded %d root, %d unpublished, %d test-fixture, %d untitled, %d single-domain. Included %d.',
            $ranking['considered'],
            $ranking['excluded_root'],
            $ranking['excluded_unpublished'],
            $ranking['excluded_test'],
            $ranking['excluded_no_title'],
            $ranking['excluded_low_cross_domain'],
            count($top)
        ));

        if (! $top) {
            $this->warn('No record passed the quality filter (published + non-test + bridges >= '
                .$bridge->minCrossDomains().' domains) - nothing to export.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $sub = trim((string) $this->option('dir'), '/');
        if ($sub === '' || ! preg_match('/^[A-Za-z0-9_\-]+$/', $sub)) {
            $sub = 'km-graph';
        }
        $dir = base_path('docs/reference/'.$sub);

        // --- Loud directory creation (heratio#1220) ------------------------
        if (! $dryRun) {
            $this->ensureWritableDir($dir);
        }

        // --- Per-entity digests --------------------------------------------
        $written = 0;
        $empty = 0;
        $entries = [];

        foreach ($top as $entity) {
            $summary = $bridge->connectionsSummary((int) $entity['id']);
            if ($summary === null) {
                // A qualifying record whose neighbours did not resolve to names.
                $empty++;

                continue;
            }

            $entries[] = $entity + ['summary' => $summary];

            if ($dryRun) {
                $written++; // would-write

                continue;
            }

            // Loud write: a failed write ERRORS the whole run (no false success).
            $path = $this->writeEntityDigest($bridge, $dir, $sub, $entity, $summary);
            $written++;
            $this->line('  wrote '.$path);
        }

        // --- Bounded accounting --------------------------------------------
        // "skipped over limit" = qualifying candidates that exist beyond this
        // bounded run. We only know the lower bound from the candidate pool: any
        // qualifier we examined but stopped short of is over-limit. The pool is
        // degree-ranked, so reporting that more connected records exist (minus
        // what we covered) is honest and never silently truncates.
        $skippedOverLimit = max(0, $totalConnected - $ranking['considered']);

        // --- Overview digest ------------------------------------------------
        if (! $dryRun) {
            $overviewPath = $this->writeOverview(
                $dir,
                $sub,
                $entries,
                $totalConnected,
                $written,
                $ranking,
                $skippedOverLimit
            );
            $this->line('<info>Overview:</info> '.$overviewPath);
        }

        // --- Bounded reporting (no silent truncation) -----------------------
        $verb = $dryRun ? 'would write' : 'wrote';
        $this->newLine();
        $this->info(sprintf(
            'Done: %s %d entity digest(s)%s; %d qualifying record(s) had no resolvable connections.',
            $verb,
            $written,
            $dryRun ? '' : ' + 1 overview',
            $empty
        ));
        $this->table(
            ['metric', 'count'],
            [
                ['candidates considered', $ranking['considered']],
                ['excluded as test-fixture', $ranking['excluded_test']],
                ['excluded unpublished', $ranking['excluded_unpublished']],
                ['excluded root', $ranking['excluded_root']],
                ['excluded untitled', $ranking['excluded_no_title']],
                ['excluded single-domain', $ranking['excluded_low_cross_domain']],
                ['included (digests '.$verb.')', $written],
                ['skipped beyond bounded run', $skippedOverLimit],
            ]
        );
        $this->warn(sprintf(
            'BOUNDED EXPORT: %d connected record(s) exist; this run covered %d candidate(s) and wrote %d. '
            .'At least %d connected record(s) were NOT examined this run.',
            $totalConnected,
            $ranking['considered'],
            $written,
            $skippedOverLimit
        ));

        Log::info('ahg:km-export-graph run', [
            'connected_total' => $totalConnected,
            'limit_requested' => $requested,
            'limit_effective' => $limit,
            'considered' => $ranking['considered'],
            'excluded_root' => $ranking['excluded_root'],
            'excluded_unpublished' => $ranking['excluded_unpublished'],
            'excluded_test' => $ranking['excluded_test'],
            'excluded_no_title' => $ranking['excluded_no_title'],
            'excluded_low_cross_domain' => $ranking['excluded_low_cross_domain'],
            'written' => $written,
            'no_connections' => $empty,
            'skipped_beyond_run' => $skippedOverLimit,
            'dry_run' => $dryRun,
        ]);

        if (! $dryRun && $written > 0) {
            $this->comment('The KM inotify watcher will ingest these docs in ~2-3 minutes (no manual trigger needed).');
        }

        return self::SUCCESS;
    }

    /**
     * heratio#1220 - create the digest directory with a CHECKED, loud failure.
     * If the directory does not exist and mkdir() returns false, throw a
     * RuntimeException so the command exits non-zero with a clear message naming
     * the path and the likely cause - rather than the old @mkdir which swallowed
     * the failure and let the run report a false success.
     */
    protected function ensureWritableDir(string $dir): void
    {
        if (! is_dir($dir)) {
            if (! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new \RuntimeException(
                    'Could not create digest directory: '.$dir.'. '
                    .'Likely cause: run this as the repo owner - docs/reference is intentionally '
                    .'NOT www-data-writable. This is a developer/deploy-time command.'
                );
            }
        }

        if (! is_writable($dir)) {
            throw new \RuntimeException(
                'Digest directory is not writable: '.$dir.'. '
                .'Run this command as the repo owner (not the web/php-fpm user); '
                .'docs/reference is intentionally NOT www-data-writable.'
            );
        }
    }

    /**
     * Write one bounded per-entity digest. Plain catalogue prose only - the
     * summary is rendered by GraphKmBridgeService from the relation graph, no
     * AI generation. Returns the path written; a failed write THROWS (heratio
     * #1220 loud failure) so the run never reports a false success.
     *
     * @param  array{id:int,title:string,slug:?string,degree:int,crossDomains:int}  $entity
     */
    protected function writeEntityDigest(
        GraphKmBridgeService $bridge,
        string $dir,
        string $sub,
        array $entity,
        string $summary
    ): string {
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
                    // heratio#1220 - keep test/demo fixture names out of the
                    // neighbour list so they never reach KM.
                    if ($name !== '' && ! $bridge->looksLikeTestTitle($name)) {
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

        $this->checkedWrite($path, implode("\n", $lines));

        return $path;
    }

    /**
     * heratio#1220 - write a file with a CHECKED, loud failure. Replaces the old
     * @file_put_contents which swallowed errors. On a false return (e.g. a
     * read-only docs/reference under the web worker) it throws a RuntimeException
     * so the command exits non-zero with a path + likely cause.
     */
    protected function checkedWrite(string $path, string $content): void
    {
        $bytes = file_put_contents($path, $content);
        if ($bytes === false) {
            throw new \RuntimeException(
                'Failed to write digest file: '.$path.'. '
                .'Likely cause: run this as the repo owner - docs/reference is intentionally '
                .'NOT www-data-writable. This is a developer/deploy-time command.'
            );
        }
    }

    /**
     * Write the single overview digest that lists every entity covered this run
     * with its degree and a one-line connection summary. This is the entry
     * point a KM query lands on when asking "what connects across collections".
     *
     * @param  array<int,array<string,mixed>>  $entries
     * @param  array<string,int|array<int,array<string,mixed>>>  $ranking  The filtered-ranking accounting.
     */
    protected function writeOverview(
        string $dir,
        string $sub,
        array $entries,
        int $totalConnected,
        int $written,
        array $ranking,
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
        $lines[] = '- Candidates considered this run: '.(int) ($ranking['considered'] ?? 0);
        $lines[] = '- Entities covered in this digest: '.$written;
        $lines[] = '- Excluded as test/demo fixtures: '.(int) ($ranking['excluded_test'] ?? 0);
        $lines[] = '- Excluded as unpublished: '.(int) ($ranking['excluded_unpublished'] ?? 0);
        $lines[] = '- Excluded as single-domain (no cross-collection bridge): '.(int) ($ranking['excluded_low_cross_domain'] ?? 0);
        $lines[] = '- Connected records beyond this bounded run (not examined here): '.$skipped;
        $lines[] = '- Ranking: by relation-edge degree (highest first), then quality-filtered to '
            .'PUBLISHED records that bridge >= 2 collection domains; synthetic test fixtures and the '
            .'tree root are excluded.';
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
        $lines[] = '- Bounded: the export writes at most the top-N most-connected QUALIFYING records '
            .'(this run: '.$written.'); the rest are reported as skipped, never silently dropped.';
        $lines[] = '- Quality filter: only PUBLISHED records that bridge >= 2 collection domains are '
            .'exported; the synthetic tree root and synthetic test/demo fixtures are excluded so the '
            .'digest is worth ingesting.';
        $lines[] = '- Per-entity detail: see the `entity-*.md` files alongside this overview.';
        $lines[] = '- Generated by: ahg:km-export-graph (heratio#1197 / #1220).';
        $lines[] = '';

        $this->checkedWrite($path, implode("\n", $lines));

        return $path;
    }
}
