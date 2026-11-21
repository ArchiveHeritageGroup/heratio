<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgCore\Support\Concerns;

use Illuminate\Support\Facades\DB;
use AhgCore\Support\I18nFallback;

/**
 * Fetch an information object's creators (event.type_id 111) as {name,
 * entity_type_id} in a culture. Byte-identical in ExportController and
 * the FindingAidJob.
 */
trait FetchesObjectCreators
{
    private function getCreators($io, string $culture)
    {
        return DB::table('event')
            ->tap(fn ($q) => I18nFallback::join($q, 'actor_i18n', 'event.actor_id', $culture, required: true))
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->where('event.object_id', $io->id)
            ->where('event.type_id', 111)
            ->whereNotNull('event.actor_id')
            ->select(I18nFallback::col('actor_i18n', 'authorized_form_of_name', 'name'), 'actor.entity_type_id')
            ->distinct()
            ->get();
    }
}
