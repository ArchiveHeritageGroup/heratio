<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgCore\Support\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Fetch an information object's term names for a taxonomy (languages =
 * taxonomy 7). Both methods were byte-identical in federation EdmSerializer
 * and ric CrmSerializer.
 */
trait FetchesObjectTerms
{
    protected function fetchLanguages($io, string $culture): array
    {
        return DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 7)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get()
            ->all();
    }

    protected function fetchAccessPoints($io, int $taxonomyId, string $culture): array
    {
        return DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', $taxonomyId)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get()
            ->all();
    }
}
