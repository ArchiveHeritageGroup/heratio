<?php

namespace AhgCore\Services;

use AhgCore\Models\AclGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    /**
     * May the current viewer see this record? Considers BOTH the terms tagged on
     * it (inherited) AND any protocol attached directly to the object (#1406 P1);
     * {@see TermProtocolService::conditionForRecord} unions the two.
     */
    public static function allowsRecord(int $objectId): bool
    {
        if (self::staffBypass()) {
            return true;
        }

        return ! TermProtocolService::isRestricted(TermProtocolService::conditionForRecord($objectId));
    }

    /**
     * May the current viewer see this object, considering ONLY protocols attached
     * directly to it (object_protocol), ignoring inherited-term protocols. Use at
     * choke points that operate on a bare object id where term inheritance has
     * already been evaluated (e.g. a digital_object download).
     */
    public static function allowsObject(int $targetId, string $targetType = 'information_object'): bool
    {
        if (self::staffBypass()) {
            return true;
        }

        return ! TermProtocolService::isRestricted(TermProtocolService::conditionForObject($targetId, $targetType));
    }

    /**
     * Retrieval scope: hide protocol-restricted terms from guests/non-editors.
     * $idColumn is the term id column of the outer query (e.g. 'term.id').
     */
    public static function addTermVisibilityCriteria($query, string $idColumn = 'term.id'): mixed
    {
        if (self::staffBypass() || ! self::protocolTableExists()) {
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
        // (a) records tagged with a protocol-restricted TERM (inherited)
        if (self::protocolTableExists()) {
            $query->whereNotExists(function ($sub) use ($idColumn) {
                $sub->select(DB::raw(1))
                    ->from('object_term_relation as otrx')
                    ->join('term_protocol as tpx', 'tpx.term_id', '=', 'otrx.term_id')
                    ->whereColumn('otrx.object_id', $idColumn)
                    ->whereIn('tpx.access_condition', TermProtocolService::RESTRICTED);
            });
        }
        // (b) records with a restricted protocol attached DIRECTLY (#1406 P1)
        if (TermProtocolService::objectProtocolTableExists()) {
            $query->whereNotExists(function ($sub) use ($idColumn) {
                $sub->select(DB::raw(1))
                    ->from('object_protocol as opx')
                    ->where('opx.target_type', 'information_object')
                    ->whereColumn('opx.target_id', $idColumn)
                    ->whereIn('opx.access_condition', TermProtocolService::RESTRICTED);
            });
        }
        // (c) records with a restricted ICIP TK/BC label applied via ahg-icip (#1406 P2)
        if (TermProtocolService::icipLabelTablesExist()) {
            $query->whereNotExists(function ($sub) use ($idColumn) {
                $sub->select(DB::raw(1))
                    ->from('icip_tk_label as ilx')
                    ->join('icip_tk_label_type as iltx', 'iltx.id', '=', 'ilx.label_type_id')
                    ->whereColumn('ilx.information_object_id', $idColumn)
                    ->whereIn('iltx.default_access_condition', TermProtocolService::RESTRICTED);
            });
        }

        return $query;
    }

    /**
     * Whether the term_protocol table is present. Cached per request. When it's
     * absent (fresh install, CI schema dump that predates the migration) the
     * query scopes no-op - no table means no protocols to enforce, and a
     * whereNotExists against a missing table would 500 the whole page.
     */
    private static ?bool $protocolTable = null;

    private static function protocolTableExists(): bool
    {
        if (self::$protocolTable === null) {
            try {
                self::$protocolTable = Schema::hasTable('term_protocol');
            } catch (\Throwable $e) {
                self::$protocolTable = false;
            }
        }

        return self::$protocolTable;
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
