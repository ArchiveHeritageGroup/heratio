<?php

/**
 * RelationalEvaluator - Service for Heratio
 *
 * Task-4 relational evidence for PERSON / ORG candidates. Looks up the
 * candidate actor's known associates via the relation table (subject_id /
 * object_id), pulls the other-side actor's authorized_form_of_name, and
 * compares against the mention's co_occurring_entities list (PERSON / ORG
 * types only).
 *
 * Note: in AtoM the relation table also stores object<->actor links
 * (e.g. information_object NameAccessPoint -> actor). We exclude those by
 * checking BOTH ends resolve to an actor row.
 *
 * Signals:
 *   match    - at least one co-occurring entity name matches a known
 *              associate's authorized name
 *   silent   - candidate has actor-to-actor associates AND mention has
 *              co-occurring PERSON/ORG entities, but no overlap
 *   absent   - candidate has no actor-to-actor associates OR mention has
 *              no PERSON/ORG co-occurring entities
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

class RelationalEvaluator implements EvaluatorInterface
{
    private const PERSON_ORG_TYPES = ['PERSON', 'ORG'];

    public function dimension(): string
    {
        return 'relational';
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, self::PERSON_ORG_TYPES, true);
    }

    public function evaluate(object $mention, object $context, object $candidate): array
    {
        $candSource = (string) ($candidate->candidate_source ?? '');
        if (!in_array($candSource, ['mysql_actor', 'fuseki_agent'], true)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, ['reason' => 'candidate_source_not_actor']);
        }

        $authId = $candidate->candidate_authority_id ?? null;
        if ($authId === null) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, ['reason' => 'no_authority_id']);
        }

        $cooccurNames = $this->coOccurringPersonOrgNames($context->co_occurring_entities ?? null);
        $associates = $this->candidateAssociates((int) $authId);

        if (empty($associates) && empty($cooccurNames)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_associates_and_no_cooccurring_persons',
            ]);
        }
        if (empty($associates)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_known_actor_to_actor_associates',
                'cooccurring' => $cooccurNames,
            ]);
        }
        if (empty($cooccurNames)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_cooccurring_persons_or_orgs',
                'candidate_associates' => $associates,
            ]);
        }

        $overlaps = [];
        $assocLower = array_map(fn($s) => mb_strtolower($s), $associates);
        foreach ($cooccurNames as $cName) {
            $cLower = mb_strtolower($cName);
            foreach ($assocLower as $idx => $aLower) {
                if ($aLower === $cLower
                    || str_contains($aLower, $cLower)
                    || str_contains($cLower, $aLower)) {
                    $overlaps[] = [
                        'cooccurring' => $cName,
                        'candidate_associate' => $associates[$idx],
                    ];
                    break;
                }
            }
        }

        if (!empty($overlaps)) {
            return EvidenceSignal::make(EvidenceSignal::MATCH, [
                'overlaps' => $overlaps,
                'candidate_associates' => $associates,
                'cooccurring' => $cooccurNames,
            ]);
        }

        return EvidenceSignal::make(EvidenceSignal::SILENT, [
            'reason' => 'no_overlap',
            'candidate_associates' => $associates,
            'cooccurring' => $cooccurNames,
        ]);
    }

    /**
     * @return list<string>  PERSON / ORG co-occurring entity values
     */
    private function coOccurringPersonOrgNames($cooccurringJson): array
    {
        $rows = EvidenceDateUtil::decodeJsonish($cooccurringJson);
        if (!is_array($rows)) {
            return [];
        }
        $names = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = (string) ($row['type'] ?? '');
            if (!in_array($type, self::PERSON_ORG_TYPES, true)) {
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

    /**
     * Pull actor-to-actor relations only (both sides resolve to an actor row).
     *
     * @return list<string>  authorized_form_of_name of every known associate
     */
    private function candidateAssociates(int $actorId): array
    {
        // Subject_id = candidate -> object_id is the other side
        $outgoing = DB::table('relation as r')
            ->join('actor as a_subj', 'a_subj.id', '=', 'r.subject_id')
            ->join('actor as a_obj',  'a_obj.id',  '=', 'r.object_id')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('ai.id', '=', 'a_obj.id')->where('ai.culture', '=', 'en');
            })
            ->where('r.subject_id', $actorId)
            ->pluck('ai.authorized_form_of_name')
            ->all();

        // Object_id = candidate -> subject_id is the other side
        $incoming = DB::table('relation as r')
            ->join('actor as a_subj', 'a_subj.id', '=', 'r.subject_id')
            ->join('actor as a_obj',  'a_obj.id',  '=', 'r.object_id')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('ai.id', '=', 'a_subj.id')->where('ai.culture', '=', 'en');
            })
            ->where('r.object_id', $actorId)
            ->pluck('ai.authorized_form_of_name')
            ->all();

        $all = array_filter(array_map('strval', array_merge($outgoing, $incoming)), fn($s) => $s !== '');
        return array_values(array_unique($all));
    }
}
