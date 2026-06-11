<?php

/**
 * GenerateResearchLeadsCommand - promote persisted discoveries into pending
 * research leads (north-star heratio#1210: generative scholarship).
 *
 *   php artisan ahg:generate-research-leads [--limit=25] [--enrich] [--dry-run]
 *
 * Reads the persisted Discoveries set (ahg_scholarship_discovery, written by
 * `ahg:generate-discoveries`) READ-ONLY and promotes the highest-confidence
 * discoveries into PENDING rows in research_lead (idempotent per record - a
 * re-run refreshes the grounding in place and PRESERVES any curator decision).
 * Only the research_lead table is written.
 *
 * --enrich attempts AI enrichment of each lead's "why this might matter" prompt
 * STRICTLY through the AHG gateway via LlmService - never a direct inference
 * node, and never on a page load. Without --enrich (or when the gateway is down)
 * each lead keeps its factual, graph-grounded prompt.
 *
 * --dry-run computes the counts WITHOUT writing.
 *
 * A pending lead is NOT public: a curator still has to publish it from the
 * curation screen (/admin/research-leads) before it appears on the public feed.
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

use AhgSemanticSearch\Services\ResearchLeadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateResearchLeadsCommand extends Command
{
    protected $signature = 'ahg:generate-research-leads
        {--limit=25 : Max discoveries to promote (highest confidence first; hard ceiling 200)}
        {--enrich : Enrich each lead "why it matters" prompt via the AHG gateway}
        {--dry-run : Compute and report without writing to research_lead}';

    protected $description = 'Promote the strongest persisted discoveries into pending research leads (heratio#1210)';

    public function handle(ResearchLeadService $service): int
    {
        $limit = (int) $this->option('limit');
        $enrich = (bool) $this->option('enrich');
        $dryRun = (bool) $this->option('dry-run');

        if (! $service->available()) {
            $this->error('Table research_lead is missing - cannot persist leads. '
                .'It is auto-created on a normal app boot; check DB permissions.');

            return self::FAILURE;
        }

        if (! $service->discoveriesAvailable()) {
            $this->warn('No persisted discoveries found (ahg_scholarship_discovery empty or missing). '
                .'Run "php artisan ahg:generate-discoveries" first. Nothing to do.');

            return self::SUCCESS;
        }

        $this->line(sprintf('Promoting up to %d discovery(ies) into leads%s%s...',
            $limit,
            $enrich ? ' with AI enrichment (via the AHG gateway)' : '',
            $dryRun ? ' (dry-run)' : ''
        ));

        try {
            $r = $service->generate($limit, $enrich, $dryRun, null);
        } catch (\Throwable $e) {
            Log::warning('[generate-research-leads] failed: '.$e->getMessage());
            $this->error('Generation failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('<info>Done.</info>');
        $this->table(
            ['Metric', 'Count'],
            [
                ['New leads promoted'.($dryRun ? ' [dry-run]' : ''), (int) ($r['promoted'] ?? 0)],
                ['Existing leads refreshed', (int) ($r['refreshed'] ?? 0)],
                ['Skipped', (int) ($r['skipped'] ?? 0)],
                ['AI prompts enriched', (int) ($r['enriched'] ?? 0)],
                ['AI gateway reached', ! empty($r['ai_reached']) ? 'yes' : 'no'],
            ]
        );

        $this->line('Pending leads are NOT public yet - publish them from /admin/research-leads.');

        Log::info(sprintf(
            '[generate-research-leads] %s promoted=%d refreshed=%d skipped=%d enriched=%d aiReached=%s',
            $dryRun ? 'DRY-RUN' : 'wrote',
            (int) ($r['promoted'] ?? 0), (int) ($r['refreshed'] ?? 0),
            (int) ($r['skipped'] ?? 0), (int) ($r['enriched'] ?? 0),
            ! empty($r['ai_reached']) ? 'yes' : 'no'
        ));

        return self::SUCCESS;
    }
}
