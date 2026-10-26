<?php

/**
 * RelationshipProposalService - Heratio
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

namespace AhgAiServices\Services;

use Illuminate\Support\Facades\DB;

/**
 * AI > Relationship review over ahg_relationship_proposal (heratio#1563), as
 * PSIS: relationships between people or organisations that a record states
 * ("is the spouse of"), proposed through the API. Each side is an existing
 * authority record or a name proposed for the same record, which must be
 * linked (NER review or Authority Resolution) before approval. Approval writes
 * the actor relation, shown on both authority records.
 */
class RelationshipProposalService
{
    public const STATUSES = ['pending', 'approved', 'rejected'];

    public function paginate(string $status, int $perPage = 25)
    {
        $culture = app()->getLocale();
        $page = DB::table('ahg_relationship_proposal as p')
            ->leftJoin('slug as s', 's.object_id', '=', 'p.object_id')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('ioi.id', '=', 'p.object_id')->where('ioi.culture', $culture);
            })
            ->leftJoin('term_i18n as ti', function ($j) use ($culture) {
                $j->on('ti.id', '=', 'p.type_id')->where('ti.culture', $culture);
            })
            ->leftJoin('user as u', 'u.id', '=', 'p.created_by')
            ->leftJoin('user as r', 'r.id', '=', 'p.reviewed_by')
            ->where('p.status', $status)
            ->orderByDesc('p.id')
            ->select('p.*', 's.slug', 'ioi.title', 'ti.name as relation', 'u.username as proposer', 'r.username as reviewer')
            ->paginate($perPage)->withQueryString();
        $page->getCollection()->transform(function ($r) use ($culture) {
            $r->subject = $this->resolveSide($r->subject_actor_id, $r->subject_entity_id, $culture);
            $r->object = $this->resolveSide($r->object_actor_id, $r->object_entity_id, $culture);
            $r->sources_list = array_values(array_filter(json_decode((string) $r->sources, true) ?: [],
                fn ($u) => is_string($u) && preg_match('#^https?://#i', $u)));

            return $r;
        });

        return $page;
    }

    public function counts(): array
    {
        return DB::table('ahg_relationship_proposal')->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status')->all();
    }

    /**
     * The authority record one side stands for: the given record, or the one a
     * proposed name was linked to (NER review sets linked_actor_id; Authority
     * Resolution records it on its decision), or none yet.
     */
    public function resolveSide($actorId, $entityId, string $culture): array
    {
        $id = $actorId ? (int) $actorId : null;
        if (! $id && $entityId) {
            $id = DB::table('ahg_ner_entity')->where('id', (int) $entityId)->value('linked_actor_id') ?: null;
            if (! $id) {
                $id = DB::table('ahg_mention_decision as d')->join('ahg_mention as m', 'm.id', '=', 'd.mention_id')
                    ->where('m.ner_entity_id', (int) $entityId)->whereIn('d.decision_type', ['link', 'link_different', 'create_new'])
                    ->whereNotNull('d.chosen_authority_id')->orderByDesc('d.id')->value('d.chosen_authority_id') ?: null;
            }
        }
        if (! $id || ! DB::table('actor')->where('id', $id)->exists()) {
            return ['id' => null, 'name' => null, 'slug' => null];
        }

        return [
            'id' => (int) $id,
            'name' => DB::table('actor_i18n')->where('id', $id)->orderByRaw('culture = ? DESC', [$culture])->value('authorized_form_of_name'),
            'slug' => DB::table('slug')->where('object_id', $id)->value('slug'),
        ];
    }

    /** Approve or reject one pending proposal; a message for the reviewer, or an exception. */
    public function decide(int $id, string $decision, ?string $notes, int $reviewerId): string
    {
        $p = DB::table('ahg_relationship_proposal')->where('id', $id)->first();
        if (! $p || $p->status !== 'pending') {
            throw new \InvalidArgumentException(__('This proposal is no longer waiting for review.'));
        }
        $done = ['reviewed_by' => $reviewerId, 'reviewed_at' => now(), 'review_notes' => $notes !== null ? mb_substr($notes, 0, 2000) : null];
        if ($decision === 'reject') {
            DB::table('ahg_relationship_proposal')->where('id', $id)->update($done + ['status' => 'rejected']);

            return __('Relationship rejected.');
        }
        if ($decision !== 'approve') {
            throw new \InvalidArgumentException(__('Choose approve or reject.'));
        }

        $culture = app()->getLocale();
        $subject = $this->resolveSide($p->subject_actor_id, $p->subject_entity_id, $culture);
        $object = $this->resolveSide($p->object_actor_id, $p->object_entity_id, $culture);
        foreach (['subject' => $subject, 'object' => $object] as $side => $r) {
            if (! $r['id']) {
                throw new \InvalidArgumentException(__('Link ":name" to an authority record first, in NER review or Authority Resolution.', ['name' => $p->{$side.'_name'}]));
            }
        }
        if ($subject['id'] === $object['id']) {
            throw new \InvalidArgumentException(__('Both sides are the same authority record.'));
        }

        DB::transaction(function () use ($p, $subject, $object, $culture, $done) {
            // An identical relation already on the records (either direction) is reused.
            $relationId = DB::table('relation')->where('type_id', $p->type_id)
                ->where(function ($q) use ($subject, $object) {
                    $q->where(fn ($w) => $w->where('subject_id', $subject['id'])->where('object_id', $object['id']))
                        ->orWhere(fn ($w) => $w->where('subject_id', $object['id'])->where('object_id', $subject['id']));
                })->value('id');
            if (! $relationId) {
                $relationId = DB::table('object')->insertGetId(['class_name' => 'QubitRelation', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('relation')->insert([
                    'id' => $relationId, 'subject_id' => $subject['id'], 'object_id' => $object['id'], 'type_id' => $p->type_id,
                    'start_date' => $p->start_date, 'end_date' => $p->end_date, 'source_culture' => $culture,
                ]);
                if ($p->date_text) {
                    DB::table('relation_i18n')->insert(['id' => $relationId, 'culture' => $culture, 'date' => $p->date_text]);
                }
                DB::table('object')->whereIn('id', [$subject['id'], $object['id']])->update(['updated_at' => now()]);
            }
            DB::table('ahg_relationship_proposal')->where('id', $p->id)->update($done + ['status' => 'approved', 'relation_id' => $relationId]);
        });

        return __('Relationship saved on both authority records.');
    }
}
