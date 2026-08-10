<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgApi\Concerns;

use Illuminate\Support\Facades\DB;

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
                ->join('term_i18n as ti', function ($j) {
                    $j->on('otr.term_id', '=', 'ti.id')->where('ti.culture', $this->culture);
                })
                ->where('otr.object_id', $objectId)
                ->whereNotNull('ti.name')
                ->distinct()
                ->pluck('ti.name')
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
