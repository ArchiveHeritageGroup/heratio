<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgApi\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Resolve a term id to its localized name (term_i18n) in the controller's
 * culture. Was byte-identical in Citation/Dataset/Entity/Iiif/Mets/OaiPmh
 * controllers. The using controller must expose a `$culture` string.
 */
trait ResolvesTermName
{
    protected function termName($termId): ?string
    {
        if (empty($termId)) {
            return null;
        }

        try {
            return DB::table('term_i18n')
                ->where('id', (int) $termId)
                ->where('culture', $this->culture)
                ->value('name');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
