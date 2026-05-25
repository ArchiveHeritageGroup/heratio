<?php

/**
 * CoOccurringPersonEvaluator - Service for Heratio
 *
 * Task-4 co-occurring-person evidence for PLACE candidates. Iterates over
 * the mention's co_occurring_entities of PERSON / ORG type, resolves any
 * that match an existing actor.authorized_form_of_name, and then asks the
 * relation table whether that actor is linked to the candidate term.
 *
 * AtoM stores actor<->term links most commonly through actor.id appearing
 * in relation alongside a term.id. We search both sides (subject/object)
 * for either pattern.
 *
 * Signals:
 *   match    - at least one co-occurring person/org is linked to the candidate
 *              place via the relation table
 *   silent   - mention has resolvable co-occurring persons AND candidate has
 *              relation rows, but no link between the two sets
 *   absent   - no co-occurring persons resolved OR candidate has no relations
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

namespace AhgAuthorityResolution\Services\Evidence;

use Illuminate\Support\Facades\DB;

class CoOccurringPersonEvaluator implements EvaluatorInterface
{
    private const PLACE_TYPES = ['GPE', 'PLACE', 'LOC'];

    private const PERSON_ORG_TYPES = ['PERSON', 'ORG'];

    public function dimension(): string
    {
        return 'co_occurring';
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, self::PLACE_TYPES, true);
    }

    public function evaluate(object $mention, object $context, object $candidate): array
    {
        if ((string) ($candidate->candidate_source ?? '') !== 'mysql_term') {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, ['reason' => 'candidate_source_not_term']);
        }
        $authId = $candidate->candidate_authority_id ?? null;
        if ($authId === null) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, ['reason' => 'no_authority_id']);
        }

        $personNames = $this->coOccurringPersonOrgNames($context->co_occurring_entities ?? null);
        if (empty($personNames)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, ['reason' => 'no_cooccurring_persons_or_orgs']);
        }

        $matchedActors = DB::table('actor as a')
            ->join('actor_i18n as ai', function ($j) {
                $j->on('ai.id', '=', 'a.id')->where('ai.culture', '=', 'en');
            })
            ->whereIn('ai.authorized_form_of_name', $personNames)
            ->pluck('ai.authorized_form_of_name', 'a.id')
            ->all();

        if (empty($matchedActors)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_cooccurring_actor_resolves',
                'cooccurring' => $personNames,
            ]);
        }

        $actorIds = array_keys($matchedActors);

        // Check relation table: actor <-> candidate term in either direction.
        $links = DB::table('relation')
            ->where(function ($q) use ($actorIds, $authId) {
                $q->whereIn('subject_id', $actorIds)->where('object_id', $authId);
            })
            ->orWhere(function ($q) use ($actorIds, $authId) {
                $q->where('subject_id', $authId)->whereIn('object_id', $actorIds);
            })
            ->get(['id', 'subject_id', 'object_id', 'type_id']);

        if ($links->isEmpty()) {
            return EvidenceSignal::make(EvidenceSignal::SILENT, [
                'reason' => 'no_actor_to_place_links',
                'resolved_cooccurring_actors' => array_values($matchedActors),
            ]);
        }

        $hits = [];
        foreach ($links as $link) {
            $actorId = (int) $link->subject_id === (int) $authId ? (int) $link->object_id : (int) $link->subject_id;
            $hits[] = [
                'relation_id' => (int) $link->id,
                'actor_id' => $actorId,
                'actor_name' => $matchedActors[$actorId] ?? null,
            ];
        }

        return EvidenceSignal::make(EvidenceSignal::MATCH, [
            'links' => $hits,
            'resolved_cooccurring_actors' => array_values($matchedActors),
        ]);
    }

    /**
     * @return list<string>
     */
    private function coOccurringPersonOrgNames($cooccurringJson): array
    {
        $rows = EvidenceDateUtil::decodeJsonish($cooccurringJson);
        if (! is_array($rows)) {
            return [];
        }
        $names = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = (string) ($row['type'] ?? '');
            if (! in_array($type, self::PERSON_ORG_TYPES, true)) {
                continue;
            }
            $value = (string) ($row['value'] ?? '');
            if ($value === '') {
                continue;
            }
            $names[] = $value;
        }

        return array_values(array_unique($names));
    }
}
