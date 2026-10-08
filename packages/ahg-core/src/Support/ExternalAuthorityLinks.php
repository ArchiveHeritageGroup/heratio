<?php

/**
 * ExternalAuthorityLinks - the external authority URIs an actor is the same
 * as (Wikidata, VIAF, LoC, ISNI, ORCID, GND, SNAC, ULAN), for owl:sameAs and
 * schema:sameAs in Heratio's Linked Data.
 *
 * sameAs asserts identity, so only identifiers marked verified are used. A
 * stored URI wins; otherwise the authority's standard URI is built from the
 * identifier value. Only http(s) URIs are returned.
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

namespace AhgCore\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExternalAuthorityLinks
{
    /** identifier_type => URI pattern for the value. */
    private const PATTERNS = [
        'wikidata' => 'http://www.wikidata.org/entity/%s',
        'viaf' => 'http://viaf.org/viaf/%s',
        'lcnaf' => 'http://id.loc.gov/authorities/names/%s',
        'loc' => 'http://id.loc.gov/authorities/names/%s',
        'isni' => 'https://isni.org/isni/%s',
        'orcid' => 'https://orcid.org/%s',
        'gnd' => 'https://d-nb.info/gnd/%s',
        'snac' => 'https://snaccooperative.org/ark:/99166/%s',
        'ulan' => 'http://vocab.getty.edu/ulan/%s',
    ];

    /** @return list<string> */
    public static function forActor(int $actorId): array
    {
        return self::forActors([$actorId])[$actorId] ?? [];
    }

    /**
     * @param  array<int, int>  $actorIds
     * @return array<int, list<string>> actor id => URIs
     */
    public static function forActors(array $actorIds): array
    {
        $actorIds = array_values(array_unique(array_filter(array_map('intval', $actorIds))));
        if (! $actorIds) {
            return [];
        }
        try {
            if (! Schema::hasTable('ahg_actor_identifier')) {
                return [];
            }
            $rows = DB::table('ahg_actor_identifier')->whereIn('actor_id', $actorIds)->where('is_verified', 1)
                ->orderBy('identifier_type')->get(['actor_id', 'identifier_type', 'identifier_value', 'uri']);
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $uri = self::uri((string) $r->identifier_type, (string) $r->identifier_value, (string) ($r->uri ?? ''));
            if ($uri !== null && ! in_array($uri, $out[(int) $r->actor_id] ?? [], true)) {
                $out[(int) $r->actor_id][] = $uri;
            }
        }

        return $out;
    }

    /** The URI for one identifier, or null when none can be made. */
    public static function uri(string $type, string $value, string $stored = ''): ?string
    {
        if (preg_match('#^https?://#i', trim($stored))) {
            return trim($stored);
        }
        $type = strtolower(trim($type));
        $value = trim($value);
        if ($value === '' || ! isset(self::PATTERNS[$type])) {
            return null;
        }
        if ($type === 'isni') {
            $value = str_replace(' ', '', $value);
        }

        return sprintf(self::PATTERNS[$type], rawurlencode($value));
    }
}
