<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgApi\Concerns;

use Illuminate\Support\Facades\DB;
use AhgCore\Support\I18nFallback;

/**
 * Resolve a repository id to its publisher name (the repository actor's
 * authorized_form_of_name) in the controller's culture, or null. Was
 * byte-identical in Dataset/Entity/OaiPmh controllers. (Citation/Iiif/Mets
 * have a string-returning '' variant, deliberately not merged here.) The
 * using controller must expose a `$culture` string.
 */
trait ResolvesPublisherName
{
    protected function publisher($repositoryId): ?string
    {
        if (empty($repositoryId)) {
            return null;
        }

        try {
            $name = DB::table('repository as r')
                ->tap(fn ($q) => I18nFallback::join($q, 'actor_i18n as ai', 'r.id', $this->culture, required: true))
                ->where('r.id', (int) $repositoryId)
                ->value(I18nFallback::col('ai', 'authorized_form_of_name', 'authorized_form_of_name'));

            return $name ? (string) $name : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
