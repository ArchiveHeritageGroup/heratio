<?php

/**
 * RicDisclosure - what the public RiC explorer surfaces may show.
 *
 * The explorer, its graph/timeline/relations JSON, the JSON-LD and CIDOC-CRM
 * exports and the entity validator are public, but read straight from the
 * database and the triplestore. Nothing checked publication or restrictions,
 * so an anonymous GET /admin/ric/timeline-data?id=<draft> returned the
 * draft's title, identifier and events. For anyone who is not staff, a
 * record DisclosureGate refuses now answers 404 when asked for directly and
 * is dropped wherever it would appear beside another entity. Only
 * information objects are judged; agents, places and other entities pass.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgRic\Support;

use AhgCore\Services\AclService;
use AhgCore\Services\DisclosureGate;
use Illuminate\Support\Facades\DB;

final class RicDisclosure
{
    /** Staff (administrator, editor, contributor) see everything, as on the record pages. */
    public static function seesAll(): bool
    {
        $u = auth()->user();

        return $u !== null && (AclService::isAdministrator($u) || AclService::isEditor($u) || AclService::isContributor($u));
    }

    /**
     * The ids among $ids that are information objects this viewer may not see.
     *
     * @param  int[]  $ids
     * @return array<int,true>
     */
    public static function hidden(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
        if (! $ids || self::seesAll()) {
            return [];
        }
        $ios = DB::table('information_object')->whereIn('id', $ids)->pluck('id')->map('intval')->all();
        $hidden = array_diff($ios, app(DisclosureGate::class)->filterIds($ios));

        return array_fill_keys($hidden, true);
    }

    /** 404 unless this viewer may see $id (only information objects can be refused). */
    public static function guard(int $id): void
    {
        abort_if(isset(self::hidden([$id])[$id]), 404);
    }

    /** The object id at the end of a RiC URI (.../recordset/631), or null. */
    public static function uriId(?string $uri): ?int
    {
        return $uri !== null && preg_match('~/(\d+)$~', $uri, $m) ? (int) $m[1] : null;
    }

    /**
     * A {nodes, edges} graph without the nodes for hidden records, and
     * without any edge that touched one.
     */
    public static function graph(array $graph): array
    {
        $nodes = $graph['nodes'] ?? [];
        $hidden = self::hidden(array_filter(array_map(fn ($n) => self::uriId($n['id'] ?? null), $nodes)));
        if (! $hidden) {
            return $graph;
        }
        $drop = [];
        foreach ($nodes as $n) {
            $id = self::uriId($n['id'] ?? null);
            if ($id !== null && isset($hidden[$id])) {
                $drop[$n['id']] = true;
            }
        }
        $graph['nodes'] = array_values(array_filter($nodes, fn ($n) => ! isset($drop[$n['id'] ?? ''])));
        $graph['edges'] = array_values(array_filter($graph['edges'] ?? [],
            fn ($e) => ! isset($drop[$e['source'] ?? '']) && ! isset($drop[$e['target'] ?? ''])));
        foreach (['total_nodes' => 'nodes', 'total_edges' => 'edges'] as $count => $key) {
            if (isset($graph[$count])) {
                $graph[$count] = count($graph[$key]);
            }
        }

        return $graph;
    }
}
