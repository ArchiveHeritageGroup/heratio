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

    /**
     * IO ids tagged (via object_term_relation) with a term carrying a restricted
     * protocol - for batch gates (offline export bundles) that need the full set
     * up front rather than per-record checks. Empty on any error (fail-open here
     * is safe: the per-record gate still fires on the live surfaces).
     */
    public static function restrictedRecordIds(): array
    {
        try {
            return DB::table('object_term_relation as otr')
                ->join('term_protocol as tp', 'tp.term_id', '=', 'otr.term_id')
                ->whereIn('tp.access_condition', self::RESTRICTED)
                ->pluck('otr.object_id')->map('intval')->unique()->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Set (replace) a term's community protocol from the ISAAR/term edit form.
     * A blank family+code with an 'open' condition clears the protocol.
     */
    public static function set(
        int $termId,
        ?string $labelFamily,
        ?string $labelCode,
        string $condition = 'open',
        ?int $ownerActorId = null,
        ?string $regionModule = null,
        ?int $createdBy = null
    ): void {
        DB::table('term_protocol')->where('term_id', $termId)->delete();
        $condition = $condition ?: 'open';
        if ($condition === 'open' && empty($labelFamily) && empty($labelCode)) {
            return; // nothing to record
        }
        DB::table('term_protocol')->insert([
            'term_id'          => $termId,
            'label_family'     => $labelFamily ?: null,
            'label_code'       => $labelCode ?: null,
            'access_condition' => $condition,
            'owner_actor_id'   => $ownerActorId,
            'region_module'    => $regionModule ?: null,
            'created_by'       => $createdBy,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
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
