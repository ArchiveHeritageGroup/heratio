<?php

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #1388 / #1406 P4 - interop + self-identification.
 *
 * Serialises the community access protocols governing a record into the
 * interchange formats (RiC / JSON-LD, OAI-PMH Dublin Core, portable export).
 * Unifies the THREE protocol sources the enforcement engine already reads:
 *   - icip_tk_label      (P2, applied Local Contexts TK/BC labels via ahg-icip)
 *   - object_protocol    (P1, protocol attached directly to the object)
 *   - term_protocol      (core, inherited from a tagged term)
 *
 * Two #1388 principles surface here:
 *   - Principle 1: render the community's OWN self_identified_term; "Indigenous
 *     peoples" is only ever a SKOS peer term, never the canonical spine.
 *   - Principle 2: no_equivalent is surfaced as a first-class, valid state (a
 *     label may have no Western/DC equivalent - that is information, not a gap).
 *
 * Everything is guarded: absent tables simply contribute nothing, so a record
 * with no protocols serialises to an empty (has_protocols=false) view.
 */
class CommunityProtocolSerializer
{
    /** Local Contexts vocabulary roots (labels + notices). */
    public const LC_LABELS_NS = 'https://localcontexts.org/labels/';
    public const LC_NOTICES_NS = 'https://localcontexts.org/notices/';

    /** UNDRIP peer term - a SKOS peer mapping ONLY, never the canonical spine (Principle 1). */
    public const PEER_INDIGENOUS = 'Indigenous peoples';

    /**
     * Normalised protocol view for a record:
     *   [ has_protocols, access_condition, restricted, labels[], communities[] ]
     * where each label carries family, code, name, local_contexts_url, condition,
     * source, no_equivalent, is_notice, and the owning community (if known).
     */
    public static function forObject(int $objectId): array
    {
        $labels = [];

        // (1) ICIP applied labels (P2) - richest source: catalog name + community.
        if (TermProtocolService::icipLabelTablesExist()) {
            try {
                $rows = DB::table('icip_tk_label as il')
                    ->join('icip_tk_label_type as ilt', 'ilt.id', '=', 'il.label_type_id')
                    ->leftJoin('icip_community as ic', 'ic.id', '=', 'il.community_id')
                    ->where('il.information_object_id', $objectId)
                    ->get([
                        'ilt.category', 'ilt.code', 'ilt.name', 'ilt.local_contexts_url',
                        'ilt.default_access_condition', 'ilt.is_local_contexts',
                        'il.applied_by', 'il.notes',
                        'ic.name as community_name', 'ic.self_identified_term',
                        'ic.pid as community_pid', 'ic.region_module',
                    ]);
                foreach ($rows as $r) {
                    $labels[] = [
                        'family'             => strtolower((string) $r->category),
                        'code'               => $r->code,
                        'name'               => $r->name,
                        'local_contexts_url' => $r->local_contexts_url,
                        'access_condition'   => $r->default_access_condition,
                        'source'             => 'icip',
                        'applied_by'         => $r->applied_by,
                        'is_local_contexts'  => (int) $r->is_local_contexts === 1,
                        'is_notice'          => false,
                        'no_equivalent'      => false,
                        'region_module'      => $r->region_module,
                        'community'          => $r->community_name ? [
                            'name'                 => $r->community_name,
                            'self_identified_term' => $r->self_identified_term,
                            'pid'                  => $r->community_pid,
                        ] : null,
                    ];
                }
            } catch (\Throwable $e) {
                // ahg-icip missing/partial - contribute nothing
            }
        }

        // (2) direct object protocols (P1) and (3) inherited term protocols (core).
        $labels = array_merge(
            $labels,
            self::fromProtocolRows(TermProtocolService::protocolsForObject($objectId), 'object'),
            self::fromProtocolRows(TermProtocolService::protocolsForRecord($objectId), 'term')
        );

        $access = TermProtocolService::conditionForRecord($objectId);

        return [
            'has_protocols'    => ! empty($labels),
            'access_condition' => $access,
            'restricted'       => TermProtocolService::isRestricted($access),
            'labels'           => $labels,
            'communities'      => self::distinctCommunities($labels),
        ];
    }

    /** Map object_protocol / term_protocol rows into the common label shape. */
    private static function fromProtocolRows($rows, string $source): array
    {
        $out = [];
        foreach ($rows as $r) {
            $code = $r->label_code ?? null;
            $meta = TermProtocolService::labelMeta($code);
            $out[] = [
                'family'             => strtolower((string) ($r->label_family ?? '')),
                'code'               => $code,
                'name'               => $meta->name ?? ($code ? ucwords(str_replace('_', ' ', $code)) : ucwords(str_replace('_', ' ', (string) $r->access_condition))),
                'local_contexts_url' => $meta->local_contexts_url ?? null,
                'access_condition'   => $r->access_condition,
                'source'             => $source,
                'applied_by'         => null,
                'is_local_contexts'  => $meta !== null,
                'is_notice'          => (int) ($r->is_notice ?? 0) === 1,
                'no_equivalent'      => (int) ($r->no_equivalent ?? 0) === 1,
                'region_module'      => $r->region_module ?? null,
                'community'          => $r->pid ? ['name' => null, 'self_identified_term' => null, 'pid' => $r->pid] : null,
            ];
        }

        return $out;
    }

    /** Distinct owning communities across the labels (for the record-level block). */
    private static function distinctCommunities(array $labels): array
    {
        $seen = [];
        foreach ($labels as $l) {
            $c = $l['community'] ?? null;
            if (! $c) {
                continue;
            }
            $key = ($c['pid'] ?? '') . '|' . ($c['name'] ?? '') . '|' . ($c['self_identified_term'] ?? '');
            $seen[$key] = $c;
        }

        return array_values($seen);
    }

    /**
     * A Local Contexts-flavoured JSON-LD fragment for the record's protocols, for
     * embedding in the RiC / linked-data representation. Null when there are none.
     */
    public static function toJsonLd(int $objectId): ?array
    {
        $view = self::forObject($objectId);
        if (! $view['has_protocols']) {
            return null;
        }

        $labels = array_map(function ($l) {
            $node = [
                '@type'            => $l['is_notice'] ? 'lc:Notice' : 'lc:Label',
                'lc:family'        => strtoupper((string) $l['family']),
                'lc:code'          => $l['code'],
                'name'             => $l['name'],
                'lc:accessCondition' => $l['access_condition'],
            ];
            if ($l['local_contexts_url']) {
                $node['@id'] = $l['local_contexts_url'];
            }
            if ($l['no_equivalent']) {
                $node['lc:noEquivalent'] = true; // Principle 2 - surfaced, not forced
            }
            if (! empty($l['community'])) {
                $node['lc:community'] = array_filter([
                    'name'                 => $l['community']['name'] ?? null,
                    'lc:selfIdentifiedTerm' => $l['community']['self_identified_term'] ?? null,
                    'lc:peerTerm'          => self::PEER_INDIGENOUS, // SKOS peer only (Principle 1)
                    '@id'                  => $l['community']['pid'] ?? null,
                ], fn ($v) => $v !== null);
            }

            return $node;
        }, $view['labels']);

        return [
            '@context' => [
                'lc'   => 'https://localcontexts.org/vocab#',
                'name' => 'http://www.w3.org/2000/01/rdf-schema#label',
            ],
            'lc:accessCondition' => $view['access_condition'],
            'lc:restricted'      => $view['restricted'],
            'lc:labels'          => $labels,
        ];
    }

    /**
     * dc:rights strings for OAI-PMH oai_dc (one per label). Human-readable,
     * self-identifying, and surfacing no_equivalent. Empty array when none.
     */
    public static function dublinCoreRights(int $objectId): array
    {
        $view = self::forObject($objectId);
        $out = [];
        foreach ($view['labels'] as $l) {
            $kind = $l['is_notice'] ? 'Notice' : 'Label';
            $fam = strtoupper((string) $l['family']);
            $line = trim("$fam $kind: " . $l['name']);
            $who = $l['community']['self_identified_term'] ?? ($l['community']['name'] ?? null);
            if ($who) {
                $line .= " ($who)";
            }
            if ($l['no_equivalent']) {
                $line .= ' [no Western equivalent]';
            }
            if ($l['local_contexts_url']) {
                $line .= ' <' . $l['local_contexts_url'] . '>';
            }
            $out[] = $line;
        }

        return $out;
    }
}
