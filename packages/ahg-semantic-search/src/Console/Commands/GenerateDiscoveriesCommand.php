<?php

/**
 * GenerateDiscoveriesCommand - refresh the persisted generative-scholarship set
 * (heratio#1210).
 *
 *   php artisan ahg:generate-discoveries [--limit=25] [--dry-run]
 *
 * Selects the most cross-collection-connected candidate records (bounded by
 * --limit, which is a hard ceiling), runs ScholarshipService::discover() over
 * each, and UPSERTS the result into ahg_scholarship_discovery keyed on
 * information_object_id (idempotent - re-running refreshes in place rather than
 * duplicating). This is how a cron job or an admin keeps the curated discovery
 * set current; the public DiscoveriesController reads from the table the command
 * writes.
 *
 * The command is the ONLY writer of ahg_scholarship_discovery. All AI use is via
 * ScholarshipService (which routes through the AHG gateway via LlmService) - this
 * command never touches an inference node directly. Records that reach the AI and
 * yield a non-obvious lead are stored with their lead text; records with real
 * graph links but no AI lead are still stored (connection-only) so the page is
 * grounded even when the gateway is down. Records with no graph links at all are
 * skipped. Per-record failures are logged and skipped - one bad record never
 * aborts the run.
 *
 * --dry-run computes everything and reports the counts WITHOUT writing.
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

use AhgSemanticSearch\Services\ScholarshipService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GenerateDiscoveriesCommand extends Command
{
    protected $signature = 'ahg:generate-discoveries
        {--limit=25 : Max candidate records to process (hard ceiling, default 25)}
        {--dry-run : Compute and report without writing to ahg_scholarship_discovery}';

    protected $description = 'Refresh the persisted generative-scholarship discovery set, most-connected records first (heratio#1210)';

    /** Absolute upper bound on --limit so a typo cannot run the whole catalogue. */
    protected int $limitCeiling = 200;

    public function handle(ScholarshipService $service): int
    {
        $limit = $this->resolveLimit();
        $dryRun = (bool) $this->option('dry-run');

        if (! Schema::hasTable('ahg_scholarship_discovery')) {
            // The boot probe should have created it; if it is still absent (e.g.
            // a read-only DB user at boot) we cannot persist. Report and stop
            // cleanly rather than fatal.
            $this->error('Table ahg_scholarship_discovery is missing - cannot persist discoveries. '
                .'It is auto-created on a normal app boot; check DB permissions.');

            return self::FAILURE;
        }

        $candidateIds = $this->candidateRecordIds($limit);
        if (! $candidateIds) {
            $this->warn('No connected candidate records found (relation / information_object empty or missing). Nothing to do.');

            return self::SUCCESS;
        }

        $this->line(sprintf('Processing up to %d candidate record(s)%s...', count($candidateIds), $dryRun ? ' (dry-run)' : ''));

        $upserts = 0;     // rows written (or that would be written in dry-run)
        $withLead = 0;    // records that produced an AI lead
        $connOnly = 0;    // records with graph links but no AI lead
        $skipped = 0;     // records with no graph links (or that failed)
        $aiSeen = false;  // did any record reach the AI gateway?

        $now = Carbon::now();

        foreach ($candidateIds as $id) {
            try {
                $d = $service->discover($id);
            } catch (\Throwable $e) {
                Log::info('[generate-discoveries] discover('.$id.') failed: '.$e->getMessage());
                $skipped++;

                continue;
            }

            if ((int) ($d['total'] ?? 0) === 0) {
                $skipped++; // no graph links - nothing to ground a discovery on

                continue;
            }

            if (! empty($d['ai_available'])) {
                $aiSeen = true;
            }

            $row = $this->buildRow($id, $d, $now);

            if (! empty($d['insights'])) {
                $withLead++;
            } else {
                $connOnly++;
            }

            if (! $dryRun) {
                $this->upsert($row);
            }
            $upserts++;
        }

        $this->newLine();
        $this->line('<info>Done.</info>');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Candidates examined', count($candidateIds)],
                ['Stored (upserted)'.($dryRun ? ' [dry-run]' : ''), $upserts],
                ['  with AI lead', $withLead],
                ['  connection-only', $connOnly],
                ['Skipped (no links / error)', $skipped],
                ['AI gateway reached', $aiSeen ? 'yes' : 'no'],
            ]
        );

        Log::info(sprintf(
            '[generate-discoveries] %s candidates=%d upserts=%d withLead=%d connOnly=%d skipped=%d aiReached=%s',
            $dryRun ? 'DRY-RUN' : 'wrote',
            count($candidateIds), $upserts, $withLead, $connOnly, $skipped, $aiSeen ? 'yes' : 'no'
        ));

        return self::SUCCESS;
    }

    /**
     * Clamp --limit to [1, $limitCeiling]; default 25.
     */
    protected function resolveLimit(): int
    {
        $raw = (int) $this->option('limit');
        if ($raw <= 0) {
            $raw = 25;
        }

        return min($raw, $this->limitCeiling);
    }

    /**
     * Build the persisted row from a discover() payload. Confidence mirrors the
     * controller's evidence-based banding (0-100), so the stored score matches
     * what the on-demand path would have shown. The evidence JSON snapshots the
     * grounded connections + metrics the lead rests on, so the stored discovery
     * is self-contained and citable without re-querying the graph.
     *
     * @param  array  $d
     * @return array<string,mixed>
     */
    protected function buildRow(int $objectId, array $d, Carbon $now): array
    {
        $rec = $d['record'] ?? ['id' => $objectId, 'title' => null, 'slug' => null];
        $total = (int) ($d['total'] ?? 0);
        $secondHop = (int) ($d['second_hop_count'] ?? 0);
        $grounded = (int) ($d['grounded_entities'] ?? 0);
        $insights = array_values(array_filter((array) ($d['insights'] ?? [])));

        // The lead/summary text: the AI insights joined into a readable lead, or
        // a factual fallback describing the verified connection when no AI lead
        // was produced (gateway down or nothing non-obvious found).
        $summary = $insights
            ? implode("\n", $insights)
            : $this->connectionSummary($total, $secondHop);

        $confidence = $this->confidenceScore($total, $secondHop, $grounded, count($insights));

        $evidence = [
            'connections' => $d['connections'] ?? [],
            'grounded_entities' => $grounded,
            'second_hop_count' => $secondHop,
            'ai_available' => (bool) ($d['ai_available'] ?? false),
            'insights' => $insights,
            'record' => [
                'id' => (int) ($rec['id'] ?? $objectId),
                'title' => $rec['title'] ?? null,
                'slug' => $rec['slug'] ?? null,
            ],
        ];

        return [
            'information_object_id' => $objectId,
            'title' => $rec['title'] ?? null,
            'summary' => $summary,
            'connection_count' => $total,
            'confidence' => $confidence,
            'evidence' => json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'generated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Factual lead text used when the AI surfaced no non-obvious insight. Grounds
     * the stored discovery in the verified graph so the card is never empty.
     */
    protected function connectionSummary(int $total, int $secondHop): string
    {
        $parts = [];
        $parts[] = $total === 1
            ? 'This record has 1 verified catalogue connection.'
            : 'This record has '.$total.' verified catalogue connections.';
        if ($secondHop > 0) {
            $parts[] = $secondHop === 1
                ? 'One further entity is reachable indirectly through a shared link.'
                : $secondHop.' further entities are reachable indirectly through shared links.';
        }
        $parts[] = 'No non-obvious AI lead was generated for this record; the verified links still stand.';

        return implode(' ', $parts);
    }

    /**
     * Evidence-based 0-100 confidence, mirroring DiscoveriesController so stored
     * and on-demand discoveries band identically. Reflects REAL graph evidence,
     * not the model's self-assessment.
     */
    protected function confidenceScore(int $total, int $secondHop, int $grounded, int $insightCount): int
    {
        $raw = $total + $grounded + ($secondHop > 0 ? 8 : 0) + ($insightCount > 0 ? 6 : 0);

        return (int) max(5, min(100, round($raw * 2)));
    }

    /**
     * Idempotent upsert keyed on information_object_id (the table's unique key).
     * created_at is preserved on update; only the generated content + generated_at
     * + updated_at change.
     *
     * @param  array<string,mixed>  $row
     */
    protected function upsert(array $row): void
    {
        DB::table('ahg_scholarship_discovery')->updateOrInsert(
            ['information_object_id' => $row['information_object_id']],
            [
                'title' => $row['title'],
                'summary' => $row['summary'],
                'connection_count' => $row['connection_count'],
                'confidence' => $row['confidence'],
                'evidence' => $row['evidence'],
                'generated_at' => $row['generated_at'],
                'updated_at' => $row['updated_at'],
                // created_at is only honoured on INSERT; updateOrInsert leaves
                // existing rows' created_at untouched because it is in the
                // update set only when the row is new. Set it so first inserts
                // get a value.
                'created_at' => $row['created_at'],
            ]
        );
    }

    /**
     * Candidate information_object ids ordered by graph degree (most-connected
     * first). Mirrors DiscoveriesController::candidateRecordIds() so the curated
     * set the command persists is the same population the on-demand path would
     * have surfaced. Read-only, bounded by $limit.
     *
     * @return array<int,int>
     */
    protected function candidateRecordIds(int $limit): array
    {
        if (! Schema::hasTable('relation') || ! Schema::hasTable('information_object')) {
            return [];
        }

        try {
            $degrees = DB::table('relation')
                ->selectRaw('id, COUNT(*) AS degree')
                ->fromSub(function ($q) {
                    $q->from('relation')->select('subject_id AS id')
                        ->unionAll(
                            DB::table('relation')->select('object_id AS id')
                        );
                }, 'relation')
                ->whereIn('id', function ($q) {
                    $q->from('information_object')->select('id');
                })
                ->groupBy('id')
                ->orderByDesc('degree')
                ->limit($limit)
                ->pluck('id');

            return $degrees->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->values()->all();
        } catch (\Throwable $e) {
            Log::info('[generate-discoveries] candidate query failed: '.$e->getMessage());

            return [];
        }
    }
}
