<?php

/**
 * ResolvesEventMeta - shared event date / creator resolution for citation-style exporters.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgApi\Concerns;

use Illuminate\Support\Facades\DB;
use AhgCore\Support\I18nFallback;

/**
 * Resolve an object's primary display date and creator names from the
 * event / event_i18n / actor_i18n tables. These three methods were
 * copy-pasted verbatim into CitationController, MetsController and
 * IiifPresentationController (creators only in the first two).
 *
 * The using class must expose a `$culture` string property (all three
 * hosts declare `protected string $culture` set in their constructor).
 */
trait ResolvesEventMeta
{
    protected function primaryDate(int $objectId): string
    {
        try {
            $rows = DB::table('event as e')
                ->tap(fn ($q) => I18nFallback::join($q, 'event_i18n as ei', 'e.id', $this->culture))
                ->where('e.object_id', $objectId)
                ->select(I18nFallback::col('ei', 'date', 'display_date'), 'e.start_date', 'e.end_date')
                ->get();

            foreach ($rows as $r) {
                if (! empty($r->display_date)) {
                    return trim((string) $r->display_date);
                }
            }
            foreach ($rows as $r) {
                if (! empty($r->start_date)) {
                    return $this->trimDate((string) $r->start_date)
                        .(! empty($r->end_date) ? '/'.$this->trimDate((string) $r->end_date) : '');
                }
            }
        } catch (\Throwable $e) {
            return '';
        }

        return '';
    }

    protected function creators(int $objectId): array
    {
        try {
            return DB::table('event')
                ->tap(fn ($q) => I18nFallback::join($q, 'actor_i18n', 'event.actor_id', $this->culture, required: true))
                ->where('event.object_id', $objectId)
                ->whereNotNull('event.actor_id')
                ->whereNotNull(I18nFallback::col('actor_i18n', 'authorized_form_of_name'))
                ->distinct()
                ->pluck(I18nFallback::col('actor_i18n', 'authorized_form_of_name', 'authorized_form_of_name'))
                ->map(fn ($v) => trim((string) $v))
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function trimDate(string $value): string
    {
        $value = trim($value);
        $value = (string) preg_replace('/-00(-00)?$/', '', $value);

        return (string) preg_replace('/-00$/', '', $value);
    }
}
