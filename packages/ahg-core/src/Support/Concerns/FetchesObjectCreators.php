<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgCore\Support\Concerns;

use Illuminate\Support\Facades\DB;

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
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->where('event.object_id', $io->id)
            ->where('event.type_id', 111)
            ->where('actor_i18n.culture', $culture)
            ->whereNotNull('event.actor_id')
            ->select('actor_i18n.authorized_form_of_name as name', 'actor.entity_type_id')
            ->distinct()
            ->get();
    }
}
