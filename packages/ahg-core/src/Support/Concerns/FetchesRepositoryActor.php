<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgCore\Support\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Fetch an information object's repository actor {id, name} in a culture.
 * Was byte-identical across four serializers/fetchers (federation EdmSerializer,
 * ric CrmSerializer, metadata-export InformationObjectFetcher + EadFindingAidCommand).
 */
trait FetchesRepositoryActor
{
    protected function fetchRepository($io, string $culture)
    {
        if (empty($io->repository_id)) {
            return null;
        }
        return DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->where('repository.id', $io->repository_id)
            ->where('actor_i18n.culture', $culture)
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->first();
    }
}
