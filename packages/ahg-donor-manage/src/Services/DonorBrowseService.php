<?php

/**
 * DonorBrowseService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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



namespace AhgDonorManage\Services;

use AhgCore\Services\BrowseService;

class DonorBrowseService extends BrowseService
{
    protected function getTable(): string
    {
        return 'donor';
    }

    protected function getI18nTable(): string
    {
        return 'actor_i18n';
    }

    protected function getI18nNameColumn(): string
    {
        return 'authorized_form_of_name';
    }

    protected function getBaseSelect(): array
    {
        return [
            'donor.id',
            'actor_i18n.authorized_form_of_name as name',
            'actor.description_identifier as identifier',
            'object.updated_at',
            'slug.slug',
        ];
    }

    protected function getBaseJoins($query)
    {
        return $query
            ->join('actor_i18n', 'donor.id', '=', 'actor_i18n.id')
            ->join('object', 'donor.id', '=', 'object.id')
            ->join('slug', 'donor.id', '=', 'slug.object_id')
            ->leftJoin('actor', 'donor.id', '=', 'actor.id')
            ->where('actor_i18n.culture', $this->culture);
    }

    protected function transformRow($row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'identifier' => $row->identifier ?? '',
            'updated_at' => $row->updated_at ?? '',
            'slug' => $row->slug ?? '',
        ];
    }
}
