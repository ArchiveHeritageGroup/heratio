<?php

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;

/**
 * #1388 Phase 1.2 - resolve the community access protocol attached to a term
 * (term-plus-protocol-plus-owner) or inherited by a record tagged with such a
 * term. Read-only helpers over the `term_protocol` table; the enforcement
 * decision lives in {@see TermProtocolGate}.
 */
class TermProtocolService
{
    /**
     * Access conditions that gate a term/record from the public, ordered
     * MOST-severe first (usage-obligation labels open/attribution/non_commercial
     * are NOT here - that content stays viewable, the obligation rides the export).
     */
    public const RESTRICTED = ['sacred_secret', 'restricted', 'gendered', 'seasonal', 'community_voice'];

    /** The strictest access condition attached directly to a term ('open' if none). */
    public static function effectiveCondition(int $termId): string
    {
        try {
            $conds = DB::table('term_protocol')->where('term_id', $termId)->pluck('access_condition')->all();
        } catch (\Throwable $e) {
            return 'open';
        }

        return self::strictest($conds);
    }

    /**
     * The strictest condition among the protocol-bearing terms tagged on a
     * record (inheritance term -> record via object_term_relation).
     */
    public static function conditionForRecord(int $objectId): string
    {
        try {
            $conds = DB::table('object_term_relation as otr')
                ->join('term_protocol as tp', 'tp.term_id', '=', 'otr.term_id')
                ->where('otr.object_id', $objectId)
                ->pluck('tp.access_condition')->all();
        } catch (\Throwable $e) {
            return 'open';
        }

        return self::strictest($conds);
    }

    public static function isRestricted(string $condition): bool
    {
        return in_array($condition, self::RESTRICTED, true);
    }

    /** The protocol row(s) on a term (label family/code, owner, pid) - for display. */
    public static function protocolsForTerm(int $termId): \Illuminate\Support\Collection
    {
        try {
            return DB::table('term_protocol')->where('term_id', $termId)->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** Pick the most-severe restricted condition present, else the first plain one, else 'open'. */
    private static function strictest(array $conds): string
    {
        foreach (self::RESTRICTED as $r) {
            if (in_array($r, $conds, true)) {
                return $r;
            }
        }

        return $conds[0] ?? 'open';
    }
}
