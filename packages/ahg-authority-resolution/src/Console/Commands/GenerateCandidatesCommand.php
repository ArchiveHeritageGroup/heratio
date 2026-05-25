<?php

/**
 * GenerateCandidatesCommand - Console command for Heratio
 *
 * Demo command for Task 3 (Candidate generation). Generates and persists
 * ranked candidate authority records for one mention, or for every mention
 * tied to an information object. With --show prints the persisted candidate
 * set (source / authority_id / display_name / score / rank).
 *
 * Usage:
 *   php artisan auth-res:generate-candidates {mention_id}
 *   php artisan auth-res:generate-candidates --object-id=901990
 *   php artisan auth-res:generate-candidates 42 --show
 *   php artisan auth-res:generate-candidates 42 --top=10 --show
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

namespace AhgAuthorityResolution\Console\Commands;

use AhgAuthorityResolution\Services\CandidateGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateCandidatesCommand extends Command
{
    protected $signature = 'auth-res:generate-candidates
                            {mention_id? : Single ahg_mention.id to generate candidates for}
                            {--object-id= : Generate for every ahg_mention row tied to this information_object}
                            {--show : Print the persisted candidate set after generation}
                            {--top= : Override the top-N (defaults to ahg_settings authority_resolution.candidate_top_n, fallback 5)}';

    protected $description = 'Generate and persist ranked authority candidates (mysql_actor + mysql_term) for one mention or every mention on an information object.';

    public function handle(CandidateGeneratorService $generator): int
    {
        $top = $this->option('top') !== null ? (int) $this->option('top') : null;
        if ($top !== null && $top <= 0) {
            $this->error('--top must be a positive integer.');

            return self::FAILURE;
        }

        $objectId = $this->option('object-id') !== null ? (int) $this->option('object-id') : null;
        $mentionId = $this->argument('mention_id') !== null ? (int) $this->argument('mention_id') : null;

        if ($objectId === null && $mentionId === null) {
            $this->error('Provide a mention_id argument or --object-id=N.');

            return self::FAILURE;
        }

        if ($objectId !== null) {
            return $this->handleObject($generator, $objectId, $top, (bool) $this->option('show'));
        }

        return $this->handleMention($generator, (int) $mentionId, $top, (bool) $this->option('show'));
    }

    private function handleMention(CandidateGeneratorService $generator, int $mentionId, ?int $top, bool $show): int
    {
        $mention = DB::table('ahg_mention as m')
            ->join('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
            ->where('m.id', $mentionId)
            ->first(['m.id', 'm.entity_type', 'm.object_id', 'n.entity_value']);

        if (! $mention) {
            $this->error("Mention #{$mentionId} not found.");

            return self::FAILURE;
        }

        $inserted = $generator->generate($mentionId, $top);
        $this->info(sprintf(
            'Mention #%d [%s] "%s" (object %d): %d candidates persisted.',
            $mention->id,
            $mention->entity_type,
            $mention->entity_value,
            $mention->object_id,
            count($inserted)
        ));

        if ($show) {
            $this->printCandidates($mentionId);
        }

        return self::SUCCESS;
    }

    private function handleObject(CandidateGeneratorService $generator, int $objectId, ?int $top, bool $show): int
    {
        $mentions = DB::table('ahg_mention')
            ->where('object_id', $objectId)
            ->whereIn('entity_type', ['PERSON', 'ORG', 'GPE', 'PLACE', 'LOC'])
            ->orderBy('id')
            ->get(['id']);

        if ($mentions->isEmpty()) {
            $this->error("No resolvable mentions found for object_id={$objectId}.");

            return self::FAILURE;
        }

        $this->info("Object {$objectId}: generating candidates for {$mentions->count()} mention(s)...");

        $total = 0;
        foreach ($mentions as $row) {
            $inserted = $generator->generate((int) $row->id, $top);
            $total += count($inserted);
            if ($show) {
                $this->printCandidates((int) $row->id);
            }
        }

        $this->info("Done. {$total} candidate rows persisted across {$mentions->count()} mentions.");

        return self::SUCCESS;
    }

    private function printCandidates(int $mentionId): void
    {
        $mention = DB::table('ahg_mention as m')
            ->join('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
            ->where('m.id', $mentionId)
            ->first(['m.id', 'm.entity_type', 'n.entity_value']);

        if (! $mention) {
            return;
        }

        $rows = DB::table('ahg_mention_candidate')
            ->where('mention_id', $mentionId)
            ->orderBy('rank_position')
            ->get([
                'rank_position',
                'candidate_source',
                'candidate_authority_id',
                'candidate_fuseki_uri',
                'candidate_display_name',
                'name_similarity_score',
                'composite_score',
            ]);

        $this->newLine();
        $this->line("--- candidates for mention #{$mention->id} [{$mention->entity_type}] \"{$mention->entity_value}\" ---");

        if ($rows->isEmpty()) {
            $this->line('  (no candidates)');

            return;
        }

        foreach ($rows as $r) {
            $authId = $r->candidate_authority_id !== null ? (string) $r->candidate_authority_id : '-';
            $uri = $r->candidate_fuseki_uri !== null && $r->candidate_fuseki_uri !== '' ? $r->candidate_fuseki_uri : '-';
            $this->line(sprintf(
                '  #%d  %-12s  auth=%s  score=%s  composite=%s  uri=%s',
                $r->rank_position,
                $r->candidate_source,
                $authId,
                number_format((float) $r->name_similarity_score, 4),
                number_format((float) $r->composite_score, 4),
                $uri
            ));
            $this->line('       name : '.(string) $r->candidate_display_name);
        }
    }
}
