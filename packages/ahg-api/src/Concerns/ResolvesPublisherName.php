<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgApi\Concerns;

use Illuminate\Support\Facades\DB;

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
                ->join('actor_i18n as ai', function ($j) {
                    $j->on('r.id', '=', 'ai.id')->where('ai.culture', $this->culture);
                })
                ->where('r.id', (int) $repositoryId)
                ->value('ai.authorized_form_of_name');

            return $name ? (string) $name : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
