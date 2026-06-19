<?php

/**
 * RicGraphManifest - Heratio
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

namespace AhgRic\Support;

/**
 * Canonical RiC graph projection manifest (ADR-0003).
 *
 * THE single declarative contract for "which RiC type is projected from which
 * relational table, under which internal IRI." The loader (fuseki-load), the
 * integrity check, and (ultimately) the CRM sync + export all read this, so
 * they cannot drift apart - the divergence ADR-0003 fixed.
 *
 * Internal live-graph node IRIs: urn:ahg:ric:<type>:<source-id>.
 * Public/export IRIs (https://ric.theahg.co.za/ric/<type>/<slug>) are minted at
 * serialisation time from config('ric.base_uri') - see the governance pin.
 */
final class RicGraphManifest
{
    /** Internal live-graph node-IRI prefix. */
    public const URN_PREFIX = 'urn:ahg:ric:';

    /**
     * type => ['table' => <authoritative source table>, 'id' => <id column>].
     *
     * One source of truth per type (ADR-0003). Place is `ric_place` (the
     * RiC-native table the serializer already reads), NOT the term taxonomy.
     */
    public const TYPES = [
        'agent'         => ['table' => 'actor',             'id' => 'id'],
        'place'         => ['table' => 'ric_place',         'id' => 'id'],
        'rule'          => ['table' => 'ric_rule',          'id' => 'id'],
        'activity'      => ['table' => 'ric_activity',      'id' => 'id'],
        'instantiation' => ['table' => 'ric_instantiation', 'id' => 'id'],
        'relation'      => ['table' => 'relation',          'id' => 'id'],
    ];

    /** Build the internal node IRI for a (type, id). */
    public static function iri(string $type, int|string $id): string
    {
        return self::URN_PREFIX . $type . ':' . $id;
    }

    /**
     * Parse urn:ahg:ric:<type>:<id> into [type, id]; null if it is not a known
     * manifest type or the id is not numeric.
     */
    public static function parse(string $iri): ?array
    {
        if (! str_starts_with($iri, self::URN_PREFIX)) {
            return null;
        }
        $parts = explode(':', substr($iri, strlen(self::URN_PREFIX)));
        if (count($parts) !== 2 || ! isset(self::TYPES[$parts[0]]) || ! ctype_digit($parts[1])) {
            return null;
        }
        return [$parts[0], (int) $parts[1]];
    }
}
