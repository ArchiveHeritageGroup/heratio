<?php

/**
 * WriteProvenanceCommand - Console command for Heratio
 *
 * Demo command for Task 8 (Decision provenance to Fuseki). Either writes
 * provenance for an existing ahg_mention_decision row, or creates a
 * "simulated" decision against a mention (mock-link to a chosen actor) and
 * writes that. Used to demonstrate the RDF-Star write path before Task 5
 * (review UI) is in place to produce real decisions.
 *
 * Usage:
 *   php artisan auth-res:write-provenance {decision_id}
 *   php artisan auth-res:write-provenance --simulate-link={mention_id} [--actor-id=N]
 *   php artisan auth-res:write-provenance {decision_id} --show
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

use AhgAuthorityResolution\Services\DecisionProvenanceWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WriteProvenanceCommand extends Command
{
    protected $signature = 'auth-res:write-provenance
                            {decision_id? : Existing ahg_mention_decision.id to write to Fuseki}
                            {--simulate-link= : Create a mock "link" decision against this mention_id (picks a similar-named actor) then write its provenance}
                            {--actor-id= : Override the actor chosen by --simulate-link with a specific actor.id}
                            {--show : Print the turtle body + the Fuseki status}';

    protected $description = 'Write RDF-Star provenance for an authority-resolution decision to the Heratio Fuseki dataset.';

    public function handle(DecisionProvenanceWriter $writer): int
    {
        $simulateMentionId = $this->option('simulate-link');

        if ($simulateMentionId) {
            $decisionId = $this->createSimulatedLinkDecision(
                (int) $simulateMentionId,
                $this->option('actor-id') !== null ? (int) $this->option('actor-id') : null
            );
            if ($decisionId === null) {
                return self::FAILURE;
            }
            $this->info("Simulated link decision #{$decisionId} inserted into ahg_mention_decision.");
        } else {
            $decisionId = (int) $this->argument('decision_id');
            if ($decisionId <= 0) {
                $this->error('Provide a decision_id argument, or --simulate-link=mention_id.');

                return self::FAILURE;
            }
        }

        $result = $writer->write($decisionId);

        if ($this->option('show')) {
            $this->line('');
            $this->line('--- turtle body sent to Fuseki ---');
            $this->line($result['turtle'] ?? '(no turtle returned)');
            $this->line('--- end turtle ---');
            $this->line('');
            $this->line('  graph_uri  : '.($result['graph'] ?? '(default)'));
            $this->line('  http_status: '.($result['status'] ?? '?'));
        }

        if (! ($result['ok'] ?? false)) {
            $this->error('Fuseki write FAILED: '.($result['error'] ?? 'unknown error'));

            return self::FAILURE;
        }

        $this->info("Provenance written for decision #{$decisionId}. Graph: ".($result['graph'] ?? '?'));

        return self::SUCCESS;
    }

    private function createSimulatedLinkDecision(int $mentionId, ?int $forceActorId): ?int
    {
        $mention = DB::table('ahg_mention as m')
            ->join('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
            ->where('m.id', $mentionId)
            ->first(['m.id', 'm.entity_type', 'n.entity_value']);

        if (! $mention) {
            $this->error("Mention #{$mentionId} not found.");

            return null;
        }

        $actorId = $forceActorId ?? $this->pickClosestActor((string) $mention->entity_value, (string) $mention->entity_type);

        if ($actorId === null) {
            $this->error("Could not find a candidate actor for entity_value='{$mention->entity_value}', entity_type='{$mention->entity_type}'. Use --actor-id=N to override.");

            return null;
        }

        $userId = (int) DB::table('user')->orderBy('id')->value('id') ?? 1;

        $candidatesSnapshot = DB::table('actor as a')
            ->join('actor_i18n as ai', 'ai.id', '=', 'a.id')
            ->where('a.entity_type_id', $mention->entity_type === 'PERSON' ? 132 : ($mention->entity_type === 'ORG' ? 131 : 0))
            ->whereNotNull('ai.authorized_form_of_name')
            ->limit(3)
            ->get(['a.id', 'ai.authorized_form_of_name'])
            ->map(fn ($r, $i) => [
                'candidate_id' => null,
                'rank' => $i + 1,
                'display_name' => (string) $r->authorized_form_of_name,
                'source' => 'mysql_actor',
            ])->values()->all();

        return DB::table('ahg_mention_decision')->insertGetId([
            'mention_id' => $mention->id,
            'decision_type' => 'link',
            'chosen_candidate_id' => null,
            'chosen_authority_id' => $actorId,
            'original_system_top_score' => 0.95,
            'archivist_user_id' => $userId,
            'decided_at' => now(),
            'candidates_visible_snapshot' => json_encode($candidatesSnapshot, JSON_UNESCAPED_UNICODE),
            'evidence_snapshot' => null,
            'fuseki_graph_uri' => null,
        ]);
    }

    private function pickClosestActor(string $value, string $entityType): ?int
    {
        $entityTypeId = $entityType === 'PERSON' ? 132 : ($entityType === 'ORG' ? 131 : null);
        if ($entityTypeId === null) {
            return null;
        }
        $row = DB::table('actor as a')
            ->join('actor_i18n as ai', 'ai.id', '=', 'a.id')
            ->where('a.entity_type_id', $entityTypeId)
            ->where('ai.authorized_form_of_name', 'like', '%'.$value.'%')
            ->orderByRaw('LENGTH(ai.authorized_form_of_name)')
            ->limit(1)
            ->first(['a.id']);
        if ($row) {
            return (int) $row->id;
        }
        // Fallback: any actor of the right type
        $row = DB::table('actor')
            ->where('entity_type_id', $entityTypeId)
            ->orderBy('id')
            ->limit(1)
            ->first(['id']);

        return $row ? (int) $row->id : null;
    }
}
