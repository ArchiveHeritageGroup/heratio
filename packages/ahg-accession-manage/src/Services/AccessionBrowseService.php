<?php

namespace AhgAccessionManage\Services;

use AhgCore\Services\BrowseService;
use Illuminate\Support\Facades\DB;

class AccessionBrowseService extends BrowseService
{
    protected function getTable(): string
    {
        return 'accession';
    }

    protected function getI18nTable(): string
    {
        return 'accession_i18n';
    }

    protected function getI18nNameColumn(): string
    {
        return 'title';
    }

    protected function getBaseSelect(): array
    {
        return [
            'accession.id',
            'accession_i18n.title as name',
            'accession.identifier',
            'accession.date as accession_date',
            'accession.processing_status_id',
            'accession.processing_priority_id',
            'object.updated_at',
            'slug.slug',
        ];
    }

    protected function getBaseJoins($query)
    {
        return $query
            ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
            ->join('object', 'accession.id', '=', 'object.id')
            ->join('slug', 'accession.id', '=', 'slug.object_id')
            ->where('accession_i18n.culture', $this->culture);
    }

    protected function applySort($query, string $sort, string $sortDir)
    {
        switch ($sort) {
            case 'alphabetic':
                $query->orderBy('accession_i18n.title', $sortDir);
                break;
            case 'identifier':
                $query->orderBy('accession.identifier', $sortDir);
                $query->orderBy('accession_i18n.title', $sortDir);
                break;
            case 'date':
                $query->orderBy('accession.date', $sortDir);
                break;
            case 'lastUpdated':
            default:
                $query->orderBy('object.updated_at', $sortDir);
                break;
        }

        return $query;
    }

    protected function applySearch($query, string $subquery)
    {
        if ($subquery !== '') {
            $query->where(function ($q) use ($subquery) {
                $q->where('accession_i18n.title', 'LIKE', "%{$subquery}%")
                  ->orWhere('accession.identifier', 'LIKE', "%{$subquery}%");
            });
        }

        return $query;
    }

    protected function transformRow($row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'identifier' => $row->identifier ?? '',
            'accession_date' => $row->accession_date ?? '',
            'processing_status_id' => $row->processing_status_id ?? null,
            'processing_priority_id' => $row->processing_priority_id ?? null,
            'updated_at' => $row->updated_at ?? '',
            'slug' => $row->slug ?? '',
        ];
    }

    /**
     * Resolve term names for status and priority IDs.
     */
    public function resolveTermNames(array $hits): array
    {
        $ids = [];
        foreach ($hits as $h) {
            if (!empty($h['processing_status_id'])) $ids[] = $h['processing_status_id'];
            if (!empty($h['processing_priority_id'])) $ids[] = $h['processing_priority_id'];
        }
        $ids = array_unique(array_filter($ids));
        if (empty($ids)) return [];

        return DB::table('term_i18n')
            ->whereIn('id', $ids)
            ->where('culture', $this->culture)
            ->pluck('name', 'id')
            ->toArray();
    }
}
