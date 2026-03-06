<?php

namespace AhgTermTaxonomy\Services;

use AhgCore\Services\BrowseService;
use Illuminate\Support\Facades\DB;

class TermBrowseService extends BrowseService
{
    protected function getTable(): string
    {
        return 'term';
    }

    protected function getI18nTable(): string
    {
        return 'term_i18n';
    }

    protected function getI18nNameColumn(): string
    {
        return 'name';
    }

    protected function getBaseJoins($query)
    {
        return $query
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('object', 'term.id', '=', 'object.id')
            ->join('slug', 'term.id', '=', 'slug.object_id')
            ->where('term_i18n.culture', $this->culture);
    }

    protected function getBaseSelect(): array
    {
        return [
            'term.id',
            'term_i18n.name as name',
            'term.taxonomy_id',
            'object.updated_at',
            'slug.slug',
        ];
    }

    protected function getSortOptions(): array
    {
        return [
            'alphabetic' => __('Name'),
            'lastUpdated' => __('Date modified'),
        ];
    }

    protected function applySort($query, string $sort, string $sortDir)
    {
        switch ($sort) {
            case 'alphabetic':
                $query->orderBy('term_i18n.name', $sortDir);
                break;
            case 'lastUpdated':
            default:
                $query->orderBy('object.updated_at', $sortDir);
                break;
        }

        return $query;
    }

    public function browse(array $params): array
    {
        // Apply taxonomy_id filter before parent browse
        $taxonomyId = $params['taxonomy_id'] ?? null;

        if ($taxonomyId) {
            $this->taxonomyId = (int) $taxonomyId;
        }

        return parent::browse($params);
    }

    protected ?int $taxonomyId = null;

    protected function applySearch($query, string $subquery)
    {
        // Apply taxonomy filter
        if ($this->taxonomyId) {
            $query->where('term.taxonomy_id', $this->taxonomyId);
        }

        return parent::applySearch($query, $subquery);
    }

    protected function transformRow($row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'taxonomy_id' => $row->taxonomy_id ?? null,
            'updated_at' => $row->updated_at ?? '',
            'slug' => $row->slug ?? '',
        ];
    }
}
