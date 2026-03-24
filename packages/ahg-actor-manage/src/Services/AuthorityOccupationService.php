<?php

namespace AhgActorManage\Services;

use Illuminate\Support\Facades\DB;

/**
 * Authority Occupation Service.
 *
 * Structured occupation CRUD with taxonomy term linking.
 * Manages the ahg_actor_occupation table.
 */
class AuthorityOccupationService
{
    /**
     * Get all occupations for an actor.
     */
    public function getOccupations(int $actorId): array
    {
        return DB::table('ahg_actor_occupation as o')
            ->leftJoin('term_i18n as ti', function ($j) {
                $j->on('o.term_id', '=', 'ti.id')
                    ->where('ti.culture', '=', 'en');
            })
            ->where('o.actor_id', $actorId)
            ->select('o.*', 'ti.name as term_name')
            ->orderBy('o.sort_order')
            ->orderBy('o.date_from')
            ->get()
            ->all();
    }

    /**
     * Save an occupation (create or update).
     */
    public function save(int $actorId, array $data, int $occupationId = 0): int
    {
        $row = [
            'actor_id'        => $actorId,
            'term_id'         => !empty($data['term_id']) ? (int) $data['term_id'] : null,
            'occupation_text' => $data['occupation_text'] ?? null,
            'date_from'       => $data['date_from'] ?? null,
            'date_to'         => $data['date_to'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'sort_order'      => (int) ($data['sort_order'] ?? 0),
        ];

        if ($occupationId > 0) {
            DB::table('ahg_actor_occupation')
                ->where('id', $occupationId)
                ->update($row);

            return $occupationId;
        }

        $row['created_at'] = date('Y-m-d H:i:s');

        return (int) DB::table('ahg_actor_occupation')->insertGetId($row);
    }

    /**
     * Delete an occupation.
     */
    public function delete(int $id): bool
    {
        return DB::table('ahg_actor_occupation')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Get occupation taxonomy terms (for autocomplete/dropdown).
     */
    public function getOccupationTerms(string $search = '', int $limit = 20): array
    {
        $taxonomyId = $this->getOccupationTaxonomyId();

        if (!$taxonomyId) {
            return [];
        }

        $query = DB::table('term_i18n as ti')
            ->join('term as t', 'ti.id', '=', 't.id')
            ->where('t.taxonomy_id', $taxonomyId)
            ->where('ti.culture', 'en')
            ->select('t.id', 'ti.name');

        if (!empty($search)) {
            $query->where('ti.name', 'like', '%' . $search . '%');
        }

        return $query->orderBy('ti.name')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Get or create the occupation taxonomy.
     */
    protected function getOccupationTaxonomyId(): ?int
    {
        $taxonomy = DB::table('taxonomy_i18n')
            ->where('culture', 'en')
            ->where('name', 'like', '%ccupation%')
            ->first();

        return $taxonomy ? (int) $taxonomy->id : null;
    }

    /**
     * Browse actors by occupation term.
     */
    public function browseByOccupation(int $termId): array
    {
        return DB::table('ahg_actor_occupation as o')
            ->join('actor_i18n as ai', function ($j) {
                $j->on('o.actor_id', '=', 'ai.id')
                    ->where('ai.culture', '=', 'en');
            })
            ->leftJoin('slug', 'o.actor_id', '=', 'slug.object_id')
            ->where('o.term_id', $termId)
            ->select(
                'o.*',
                'ai.authorized_form_of_name as name',
                'slug.slug'
            )
            ->orderBy('ai.authorized_form_of_name')
            ->get()
            ->all();
    }

    /**
     * Get occupation statistics.
     */
    public function getStats(): array
    {
        $total = DB::table('ahg_actor_occupation')->count();
        $withTerms = DB::table('ahg_actor_occupation')->whereNotNull('term_id')->count();
        $freeText = DB::table('ahg_actor_occupation')->whereNull('term_id')->count();
        $uniqueActors = DB::table('ahg_actor_occupation')->distinct('actor_id')->count('actor_id');

        return [
            'total'         => $total,
            'with_terms'    => $withTerms,
            'free_text'     => $freeText,
            'unique_actors' => $uniqueActors,
        ];
    }
}
