<?php

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;

/**
 * EntityResolutionService - Cross-Collection Entity Resolution
 *
 * Migrated from AtoM: ahgResearchPlugin/lib/Services/EntityResolutionService.php
 */
class EntityResolutionService
{
    private string $culture = 'en';

    public function proposeMatch(array $data): int
    {
        return DB::table('research_entity_resolution')->insertGetId([
            'entity_a_type' => $data['entity_a_type'],
            'entity_a_id' => $data['entity_a_id'],
            'entity_b_type' => $data['entity_b_type'],
            'entity_b_id' => $data['entity_b_id'],
            'confidence' => $data['confidence'] ?? null,
            'match_method' => $data['match_method'] ?? null,
            'status' => 'proposed',
            'notes' => $data['notes'] ?? null,
            'evidence_json' => isset($data['evidence']) ? json_encode($data['evidence']) : null,
            'relationship_type' => $data['relationship_type'] ?? 'sameAs',
            'proposer_id' => $data['proposer_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getProposals(array $filters = [], int $page = 1, int $limit = 25): array
    {
        $query = DB::table('research_entity_resolution as er')
            ->leftJoin('research_researcher as r', 'er.resolver_id', '=', 'r.id');

        if (!empty($filters['status'])) {
            $query->where('er.status', $filters['status']);
        }
        if (!empty($filters['entity_type'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('er.entity_a_type', $filters['entity_type'])
                    ->orWhere('er.entity_b_type', $filters['entity_type']);
            });
        }
        if (!empty($filters['relationship_type'])) {
            $query->where('er.relationship_type', $filters['relationship_type']);
        }

        $total = $query->count();
        $offset = ($page - 1) * $limit;

        $items = $query->select(
            'er.*',
            'r.first_name as resolver_first_name',
            'r.last_name as resolver_last_name'
        )
            ->orderBy('er.created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        foreach ($items as &$item) {
            $item->entity_a_label = $this->getEntityLabel($item->entity_a_type, (int) $item->entity_a_id);
            $item->entity_b_label = $this->getEntityLabel($item->entity_b_type, (int) $item->entity_b_id);
            $item->evidence = $item->evidence_json ? json_decode($item->evidence_json, true) : [];
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function resolveMatch(int $id, string $status, int $resolverId): bool
    {
        if (!in_array($status, ['accepted', 'rejected'], true)) {
            return false;
        }

        $resolution = DB::table('research_entity_resolution')->where('id', $id)->first();
        if (!$resolution) {
            return false;
        }

        $updated = DB::table('research_entity_resolution')
            ->where('id', $id)
            ->update([
                'status' => $status,
                'resolver_id' => $resolverId,
                'resolved_at' => date('Y-m-d H:i:s'),
            ]) >= 0;

        if ($updated && $status === 'accepted' && ($resolution->relationship_type ?? 'sameAs') === 'sameAs') {
            try {
                $projectId = DB::table('research_project_collaborator')
                    ->where('researcher_id', $resolverId)
                    ->where('status', 'accepted')
                    ->value('project_id');

                if ($projectId) {
                    DB::table('research_assertion')->insert([
                        'project_id' => $projectId,
                        'researcher_id' => $resolverId,
                        'subject_type' => $resolution->entity_a_type,
                        'subject_id' => $resolution->entity_a_id,
                        'predicate' => 'sameAs',
                        'object_type' => $resolution->entity_b_type,
                        'object_id' => $resolution->entity_b_id,
                        'assertion_type' => 'identity',
                        'confidence' => $resolution->confidence,
                        'evidence_json' => $resolution->evidence_json,
                        'status' => 'accepted',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            } catch (\Exception $e) {
                // Table may not exist
            }
        }

        return $updated;
    }

    public function getConflictingAssertions(int $resolutionId): array
    {
        $resolution = DB::table('research_entity_resolution')->where('id', $resolutionId)->first();
        if (!$resolution) {
            return [];
        }

        try {
            $conflicts = DB::table('research_assertion')
                ->where(function ($q) use ($resolution) {
                    $q->where(function ($inner) use ($resolution) {
                        $inner->where('subject_type', $resolution->entity_a_type)
                            ->where('subject_id', $resolution->entity_a_id)
                            ->where('object_type', $resolution->entity_b_type)
                            ->where('object_id', $resolution->entity_b_id);
                    })->orWhere(function ($inner) use ($resolution) {
                        $inner->where('subject_type', $resolution->entity_b_type)
                            ->where('subject_id', $resolution->entity_b_id)
                            ->where('object_type', $resolution->entity_a_type)
                            ->where('object_id', $resolution->entity_a_id);
                    });
                })
                ->whereIn('status', ['proposed', 'accepted'])
                ->get()
                ->toArray();

            $proposedRelType = $resolution->relationship_type ?? 'sameAs';
            $conflictingPredicates = $this->getConflictingPredicates($proposedRelType);

            return array_values(array_filter($conflicts, function ($a) use ($conflictingPredicates) {
                return in_array($a->predicate ?? '', $conflictingPredicates, true);
            }));
        } catch (\Exception $e) {
            return [];
        }
    }

    public function deleteResolution(int $id): bool
    {
        return DB::table('research_entity_resolution')->where('id', $id)->delete() > 0;
    }

    private function getConflictingPredicates(string $relationshipType): array
    {
        $conflicts = [
            'sameAs' => ['differentFrom', 'supersedes', 'replacedBy'],
            'relatedTo' => [],
            'partOf' => ['differentFrom'],
            'memberOf' => ['differentFrom'],
        ];
        return $conflicts[$relationshipType] ?? [];
    }

    private function getEntityLabel(string $type, int $id): string
    {
        if ($type === 'actor' || $type === 'repository') {
            $name = DB::table('actor_i18n')
                ->where('id', $id)
                ->where('culture', $this->culture)
                ->value('authorized_form_of_name');
            return $name ?: ucfirst(str_replace('_', ' ', $type)) . " #{$id}";
        }

        if ($type === 'information_object') {
            $title = DB::table('information_object_i18n')
                ->where('id', $id)
                ->where('culture', $this->culture)
                ->value('title');
            return $title ?: "Object #{$id}";
        }

        return ucfirst(str_replace('_', ' ', $type)) . " #{$id}";
    }
}
