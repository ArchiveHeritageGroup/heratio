<?php

/**
 * AuthorityMergeService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgActorManage\Services;

use Illuminate\Support\Facades\DB;

/**
 * Authority Merge/Split Service.
 *
 * Handles merge and split workflows for authority records.
 * Includes field comparison, relation/resource transfer, slug redirect, and audit.
 */
class AuthorityMergeService
{
    /**
     * Get a merge record by ID.
     */
    public function getMerge(int $id): ?object
    {
        return DB::table('ahg_actor_merge')
            ->where('id', $id)
            ->first();
    }

    /**
     * Get merge history for an actor.
     */
    public function getMergeHistory(int $actorId): array
    {
        return DB::table('ahg_actor_merge')
            ->where(function ($q) use ($actorId) {
                $q->where('primary_actor_id', $actorId)
                    ->orWhereRaw("JSON_CONTAINS(secondary_actor_ids, ?)", [json_encode($actorId)]);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->all();
    }

    /**
     * Build a side-by-side comparison of two actors.
     */
    public function compareActors(int $primaryId, int $secondaryId): array
    {
        $primary = $this->getActorDetail($primaryId);
        $secondary = $this->getActorDetail($secondaryId);

        $fields = [
            'authorized_form_of_name', 'dates_of_existence', 'history',
            'places', 'legal_status', 'functions', 'mandates',
            'internal_structures', 'general_context', 'sources',
            'description_identifier', 'revision_history',
        ];

        $comparison = [];
        foreach ($fields as $field) {
            $comparison[$field] = [
                'primary'   => $primary->$field ?? '',
                'secondary' => $secondary->$field ?? '',
                'match'     => ($primary->$field ?? '') === ($secondary->$field ?? ''),
            ];
        }

        // Count relations and resources
        $primaryRelations = DB::table('relation')
            ->where(function ($q) use ($primaryId) {
                $q->where('subject_id', $primaryId)->orWhere('object_id', $primaryId);
            })->count();

        $secondaryRelations = DB::table('relation')
            ->where(function ($q) use ($secondaryId) {
                $q->where('subject_id', $secondaryId)->orWhere('object_id', $secondaryId);
            })->count();

        $primaryResources = DB::table('event')->where('actor_id', $primaryId)->count();
        $secondaryResources = DB::table('event')->where('actor_id', $secondaryId)->count();

        // External identifiers
        $primaryIds = DB::table('ahg_actor_identifier')->where('actor_id', $primaryId)->get()->all();
        $secondaryIds = DB::table('ahg_actor_identifier')->where('actor_id', $secondaryId)->get()->all();

        return [
            'primary'              => $primary,
            'secondary'            => $secondary,
            'comparison'           => $comparison,
            'primary_relations'    => $primaryRelations,
            'secondary_relations'  => $secondaryRelations,
            'primary_resources'    => $primaryResources,
            'secondary_resources'  => $secondaryResources,
            'primary_identifiers'  => $primaryIds,
            'secondary_identifiers' => $secondaryIds,
        ];
    }

    /**
     * Create a merge request (may require approval).
     */
    public function createMergeRequest(
        int $primaryId,
        array $secondaryIds,
        array $fieldChoices,
        int $userId,
        ?string $notes = null
    ): int {
        $requireApproval = $this->getConfig('merge_require_approval', '0') === '1';

        $status = $requireApproval ? 'pending' : 'approved';

        $mergeId = (int) DB::table('ahg_actor_merge')->insertGetId([
            'merge_type'          => 'merge',
            'primary_actor_id'    => $primaryId,
            'secondary_actor_ids' => json_encode($secondaryIds),
            'field_choices'       => json_encode($fieldChoices),
            'status'              => $status,
            'notes'               => $notes,
            'performed_by'        => $userId,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        if (!$requireApproval) {
            $this->executeMerge($mergeId, $userId);
        }

        return $mergeId;
    }

    /**
     * Execute a merge operation.
     */
    public function executeMerge(int $mergeId, int $userId): bool
    {
        $merge = $this->getMerge($mergeId);
        if (!$merge || !in_array($merge->status, ['approved', 'pending'])) {
            return false;
        }

        $primaryId = $merge->primary_actor_id;
        $secondaryIds = json_decode($merge->secondary_actor_ids, true) ?? [];
        $fieldChoices = json_decode($merge->field_choices, true) ?? [];

        $relationsTransferred = 0;
        $resourcesTransferred = 0;
        $contactsTransferred = 0;
        $identifiersTransferred = 0;

        foreach ($secondaryIds as $secId) {
            $this->applyFieldChoices($primaryId, $secId, $fieldChoices);
            $relationsTransferred += $this->transferRelations($primaryId, $secId);
            $resourcesTransferred += $this->transferResources($primaryId, $secId);
            $contactsTransferred += $this->transferContacts($primaryId, $secId);
            $identifiersTransferred += $this->transferIdentifiers($primaryId, $secId);
            $this->createSlugRedirect($primaryId, $secId);
        }

        DB::table('ahg_actor_merge')
            ->where('id', $mergeId)
            ->update([
                'status'                 => 'completed',
                'relations_transferred'  => $relationsTransferred,
                'resources_transferred'  => $resourcesTransferred,
                'contacts_transferred'   => $contactsTransferred,
                'identifiers_transferred' => $identifiersTransferred,
                'performed_at'           => date('Y-m-d H:i:s'),
                'approved_by'            => $userId,
                'approved_at'            => date('Y-m-d H:i:s'),
            ]);

        return true;
    }

    /**
     * Create a split request.
     */
    public function createSplitRequest(
        int $sourceActorId,
        array $fieldsToMove,
        array $relationsToMove,
        int $userId,
        ?string $notes = null
    ): int {
        return (int) DB::table('ahg_actor_merge')->insertGetId([
            'merge_type'          => 'split',
            'primary_actor_id'    => $sourceActorId,
            'secondary_actor_ids' => json_encode([]),
            'field_choices'       => json_encode([
                'fields_to_move'    => $fieldsToMove,
                'relations_to_move' => $relationsToMove,
            ]),
            'status'              => 'pending',
            'notes'               => $notes,
            'performed_by'        => $userId,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);
    }

    protected function applyFieldChoices(int $primaryId, int $secondaryId, array $choices): void
    {
        $updates = [];
        $secondaryI18n = DB::table('actor_i18n')
            ->where('id', $secondaryId)
            ->where('culture', 'en')
            ->first();

        if (!$secondaryI18n) {
            return;
        }

        foreach ($choices as $field => $source) {
            if ($source === 'secondary_' . $secondaryId && isset($secondaryI18n->$field)) {
                $updates[$field] = $secondaryI18n->$field;
            }
        }

        if (!empty($updates)) {
            DB::table('actor_i18n')
                ->where('id', $primaryId)
                ->where('culture', 'en')
                ->update($updates);
        }
    }

    protected function transferRelations(int $primaryId, int $secondaryId): int
    {
        $count = 0;

        $count += DB::table('relation')
            ->where('subject_id', $secondaryId)
            ->where('object_id', '!=', $primaryId)
            ->update(['subject_id' => $primaryId]);

        $count += DB::table('relation')
            ->where('object_id', $secondaryId)
            ->where('subject_id', '!=', $primaryId)
            ->update(['object_id' => $primaryId]);

        return $count;
    }

    protected function transferResources(int $primaryId, int $secondaryId): int
    {
        return DB::table('event')
            ->where('actor_id', $secondaryId)
            ->update(['actor_id' => $primaryId]);
    }

    protected function transferContacts(int $primaryId, int $secondaryId): int
    {
        try {
            return DB::table('contact_information')
                ->where('actor_id', $secondaryId)
                ->update(['actor_id' => $primaryId]);
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function transferIdentifiers(int $primaryId, int $secondaryId): int
    {
        $secondaryIds = DB::table('ahg_actor_identifier')
            ->where('actor_id', $secondaryId)
            ->get()
            ->all();

        $count = 0;
        foreach ($secondaryIds as $ident) {
            $existing = DB::table('ahg_actor_identifier')
                ->where('actor_id', $primaryId)
                ->where('identifier_type', $ident->identifier_type)
                ->exists();

            if (!$existing) {
                DB::table('ahg_actor_identifier')
                    ->where('id', $ident->id)
                    ->update(['actor_id' => $primaryId, 'updated_at' => date('Y-m-d H:i:s')]);
                $count++;
            }
        }

        return $count;
    }

    protected function createSlugRedirect(int $primaryId, int $secondaryId): void
    {
        $oldSlug = DB::table('slug')
            ->where('object_id', $secondaryId)
            ->first();

        if ($oldSlug) {
            DB::table('slug')
                ->where('object_id', $secondaryId)
                ->update(['object_id' => $primaryId]);
        }
    }

    protected function getActorDetail(int $actorId): ?object
    {
        return DB::table('actor_i18n as ai')
            ->leftJoin('actor as a', 'ai.id', '=', 'a.id')
            ->leftJoin('slug', 'a.id', '=', 'slug.object_id')
            ->where('ai.id', $actorId)
            ->where('ai.culture', 'en')
            ->select('ai.*', 'slug.slug', 'a.entity_type_id')
            ->first();
    }

    protected function getConfig(string $key, string $default = ''): string
    {
        try {
            $row = DB::table('ahg_authority_config')
                ->where('config_key', $key)
                ->first();

            return $row ? ($row->config_value ?? $default) : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}
