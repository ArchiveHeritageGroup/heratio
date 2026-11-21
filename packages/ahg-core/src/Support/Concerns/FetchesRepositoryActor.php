<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgCore\Support\Concerns;

use Illuminate\Support\Facades\DB;
use AhgCore\Support\I18nFallback;

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
            ->tap(fn ($q) => I18nFallback::join($q, 'actor_i18n', 'repository.id', $culture, required: true))
            ->where('repository.id', $io->repository_id)
            ->select('repository.id', I18nFallback::col('actor_i18n', 'authorized_form_of_name', 'name'))
            ->first();
    }
}
