<?php

namespace AhgCore\Repositories;

use AhgCore\Contracts\RelationRepository;
use Illuminate\Support\Facades\DB;

class MysqlRelationRepository implements RelationRepository
{
    public function getRelationsForEntity(int $entityId, ?int $typeId = null): array
    {
        $query = DB::table('relation')
            ->leftJoin('term_i18n as ti', function ($j) {
                $j->on('relation.type_id', '=', 'ti.id')->where('ti.culture', 'en');
            })
            ->where(function ($q) use ($entityId) {
                $q->where('relation.subject_id', $entityId)
                  ->orWhere('relation.object_id', $entityId);
            })
            ->select(
                'relation.*',
                'ti.name as type_name',
                DB::raw("CASE WHEN relation.subject_id = {$entityId} THEN relation.object_id ELSE relation.subject_id END as target_id"),
                DB::raw("CASE WHEN relation.subject_id = {$entityId} THEN 'outgoing' ELSE 'incoming' END as direction")
            );

        if ($typeId) {
            $query->where('relation.type_id', $typeId);
        }

        return $query->limit(100)->get()->toArray();
    }

    public function findById(int $id): ?object
    {
        return DB::table('relation')
            ->leftJoin('term_i18n as ti', function ($j) {
                $j->on('relation.type_id', '=', 'ti.id')->where('ti.culture', 'en');
            })
            ->where('relation.id', $id)
            ->select('relation.*', 'ti.name as type_name')
            ->first();
    }

    public function exists(int $subjectId, int $objectId, ?int $typeId = null): bool
    {
        $query = DB::table('relation')
            ->where(function ($q) use ($subjectId, $objectId) {
                $q->where(function ($inner) use ($subjectId, $objectId) {
                    $inner->where('subject_id', $subjectId)->where('object_id', $objectId);
                })->orWhere(function ($inner) use ($subjectId, $objectId) {
                    $inner->where('subject_id', $objectId)->where('object_id', $subjectId);
                });
            });

        if ($typeId) {
            $query->where('type_id', $typeId);
        }

        return $query->exists();
    }

    public function getRelationTypes(): array
    {
        return DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 74) // Relation type taxonomy
            ->where('term_i18n.culture', 'en')
            ->select('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get()
            ->toArray();
    }
}
