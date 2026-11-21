<?php

/**
 * AiNerScoreCommand - Heratio
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

namespace AhgAiServices\Commands;

use AhgAiServices\Services\NerEvidenceScorer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1501 - backfill the evidence score on NER entities the extractor left
 * unscored. A plain run is CPU only and makes no network call; the GPU model
 * check runs only with --verify, and then on a capped number of rows.
 */
class AiNerScoreCommand extends Command
{
    protected $signature = 'ahg:ai-ner-score
        {--verify          : Also ask a small model through the AI gateway (uses a GPU). Off by default}
        {--model=mistral:7b : Model for --verify}
        {--limit=          : Most rows to score (rules: all; --verify: 25)}
        {--object-id=      : Only this record}
        {--rescore         : Score again rows that already have a rules score}
        {--stats           : Show how scored the pending entities are, and exit}
        {--dry-run         : Report what would be scored, write nothing}';

    protected $description = 'Give unscored NER entities an evidence score (CPU; --verify adds a model check through the gateway)';

    public function handle(NerEvidenceScorer $scorer): int
    {
        if (! Schema::hasColumn('ahg_ner_entity', 'evidence_score')) {
            $this->error('ahg_ner_entity.evidence_score is missing - run the migrations.');

            return self::FAILURE;
        }
        if ($this->option('stats')) {
            return $this->stats();
        }

        $dry = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        // Rules pass, in id chunks.
        $base = fn () => DB::table('ahg_ner_entity')->where('status', 'pending')->whereNull('confidence')
            ->whereIn('entity_type', NerEvidenceScorer::SCORABLE)
            ->when($this->option('object-id'), fn ($q, $id) => $q->where('object_id', (int) $id))
            ->when(! $this->option('rescore'), fn ($q) => $q->whereNull('evidence_score'));
        $done = 0;
        $lastId = 0;
        while ($limit === null || $done < $limit) {
            $ids = $base()->where('id', '>', $lastId)->orderBy('id')->limit(min(500, $limit === null ? 500 : $limit - $done))->pluck('id')->all();
            if (! $ids) {
                break;
            }
            $lastId = (int) end($ids);
            $done += $scorer->scoreIds($ids, (bool) $this->option('rescore'), ! $dry);
        }
        $this->info(($dry ? 'Would score ' : 'Scored ') . $done . ' entities from catalogue evidence (no AI call).');

        if ($this->option('verify')) {
            $this->verify($scorer, $dry, $limit ?? 25);
        }

        return self::SUCCESS;
    }

    private function verify(NerEvidenceScorer $scorer, bool $dry, int $limit): void
    {
        // Rows nothing in the catalogue backs (below 0.8), not yet model-checked.
        $ids = DB::table('ahg_ner_entity')->where('status', 'pending')->whereNull('confidence')
            ->whereIn('entity_type', NerEvidenceScorer::SCORABLE)
            ->when($this->option('object-id'), fn ($q, $id) => $q->where('object_id', (int) $id))
            ->where(fn ($q) => $q->whereNull('evidence_method')->orWhere('evidence_method', '<>', NerEvidenceScorer::METHOD_VERIFY))
            ->where(fn ($q) => $q->whereNull('evidence_score')->orWhere('evidence_score', '<', 0.8))
            ->orderBy('id')->limit($limit)->pluck('id')->all();
        if ($dry) {
            $this->info('Would ask ' . $this->option('model') . ' about ' . count($ids) . ' entities through the gateway.');

            return;
        }
        $ok = $failures = 0;
        foreach ($ids as $id) {
            try {
                $p = $scorer->verify((int) $id, (string) $this->option('model'));
                $failures = 0;
                if ($p !== null) {
                    $ok++;
                }
            } catch (\Throwable $e) {
                $this->warn("Entity {$id}: " . $e->getMessage());
                if (++$failures >= 3) {
                    $this->error('Three gateway failures in a row - stopping the model check.');
                    break;
                }
            }
        }
        $this->info("Model-checked {$ok} of " . count($ids) . ' entities.');
    }

    private function stats(): int
    {
        $rows = DB::table('ahg_ner_entity')->where('status', 'pending')
            ->selectRaw("CASE WHEN confidence IS NOT NULL THEN 'extractor' WHEN evidence_method IS NOT NULL THEN evidence_method ELSE 'unscored' END src,
                SUM(COALESCE(confidence, evidence_score) < 0.3) low,
                SUM(COALESCE(confidence, evidence_score) >= 0.3 AND COALESCE(confidence, evidence_score) < 0.8) mid,
                SUM(COALESCE(confidence, evidence_score) >= 0.8) high, COUNT(*) n")
            ->groupBy('src')->get();
        $this->table(['Score from', 'Below 0.3', '0.3 - 0.8', '0.8 and up', 'Pending'],
            $rows->map(fn ($r) => [$r->src, (int) $r->low, (int) $r->mid, (int) $r->high, (int) $r->n])->all());

        return self::SUCCESS;
    }
}
