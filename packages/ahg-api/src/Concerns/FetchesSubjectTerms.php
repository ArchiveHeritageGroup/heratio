<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgApi\Concerns;

use Illuminate\Support\Facades\DB;
use AhgCore\Support\I18nFallback;

/**
 * Distinct subject-term names for an object in the controller's culture.
 * Byte-identical body in OaiPmhController::subjects and
 * DatasetController::subjectsList; both names provided. Uses $this->culture.
 */
trait FetchesSubjectTerms
{
    protected function subjects(int $objectId): array
    {
        try {
            return DB::table('object_term_relation as otr')
                ->tap(fn ($q) => I18nFallback::join($q, 'term_i18n as ti', 'otr.term_id', $this->culture, required: true))
                ->where('otr.object_id', $objectId)
                ->whereNotNull(I18nFallback::col('ti', 'name'))
                ->distinct()
                ->pluck(I18nFallback::col('ti', 'name', 'name'))
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Alias of subjects() - kept for DatasetController's call sites. */
    protected function subjectsList(int $objectId): array
    {
        return $this->subjects($objectId);
    }
}
