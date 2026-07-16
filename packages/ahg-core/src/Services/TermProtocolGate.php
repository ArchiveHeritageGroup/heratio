<?php

namespace AhgCore\Services;

use AhgCore\Models\AclGroup;
use Illuminate\Support\Facades\DB;

/**
 * #1388 Phase 1.3 - the jurisdiction-neutral protocol-enforcement engine
 * (sibling to {@see DisclosureGate}). Decides whether the current viewer may
 * see a protocol-bearing term, or a record tagged with one, and provides query
 * scopes for the retrieval choke points. Editors/administrators bypass; the
 * per-region label sets that FEED this gate live in per-region plugins.
 *
 * Owner/community bypass (a viewer who belongs to the owning community) is a
 * Phase-3 governance concern; for now only staff bypass.
 */
class TermProtocolGate
{
    /** May the current viewer see this term? */
    public static function allowsTerm(int $termId): bool
    {
        if (self::staffBypass()) {
            return true;
        }

        return ! TermProtocolService::isRestricted(TermProtocolService::effectiveCondition($termId));
    }

    /** May the current viewer see this record, given the terms tagged on it? */
    public static function allowsRecord(int $objectId): bool
    {
        if (self::staffBypass()) {
            return true;
        }

        return ! TermProtocolService::isRestricted(TermProtocolService::conditionForRecord($objectId));
    }

    /**
     * Retrieval scope: hide protocol-restricted terms from guests/non-editors.
     * $idColumn is the term id column of the outer query (e.g. 'term.id').
     */
    public static function addTermVisibilityCriteria($query, string $idColumn = 'term.id'): mixed
    {
        if (self::staffBypass()) {
            return $query;
        }
        $query->whereNotExists(function ($sub) use ($idColumn) {
            $sub->select(DB::raw(1))
                ->from('term_protocol as tp')
                ->whereColumn('tp.term_id', $idColumn)
                ->whereIn('tp.access_condition', TermProtocolService::RESTRICTED);
        });

        return $query;
    }

    /**
     * Retrieval scope: hide records tagged with a protocol-restricted term from
     * guests/non-editors. $idColumn is the object id column (e.g. 'io.id').
     */
    public static function excludeRestrictedRecords($query, string $idColumn = 'io.id'): mixed
    {
        if (self::staffBypass()) {
            return $query;
        }
        $query->whereNotExists(function ($sub) use ($idColumn) {
            $sub->select(DB::raw(1))
                ->from('object_term_relation as otrx')
                ->join('term_protocol as tpx', 'tpx.term_id', '=', 'otrx.term_id')
                ->whereColumn('otrx.object_id', $idColumn)
                ->whereIn('tpx.access_condition', TermProtocolService::RESTRICTED);
        });

        return $query;
    }

    private static function staffBypass(): bool
    {
        $user = AclService::getUser();
        if (! $user) {
            return false;
        }
        $groups = AclService::getUserGroups($user->id ?? null);

        return in_array(AclGroup::ADMINISTRATOR_ID, $groups) || in_array(AclGroup::EDITOR_ID, $groups);
    }
}
