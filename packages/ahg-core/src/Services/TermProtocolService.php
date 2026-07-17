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
     * The strictest condition governing a record, unioning THREE sources:
     *  - protocols inherited from terms tagged on it (object_term_relation), and
     *  - a protocol attached DIRECTLY to the object (object_protocol, #1406 P1), and
     *  - an ICIP TK/BC label applied via ahg-icip (icip_tk_label ->
     *    icip_tk_label_type.default_access_condition, #1406 P2).
     * Resolution is "strictest wins" across the union; any source can raise the bar.
     */
    public static function conditionForRecord(int $objectId): string
    {
        $conds = [];
        try {
            $conds = DB::table('object_term_relation as otr')
                ->join('term_protocol as tp', 'tp.term_id', '=', 'otr.term_id')
                ->where('otr.object_id', $objectId)
                ->pluck('tp.access_condition')->all();
        } catch (\Throwable $e) {
            // inherited-term protocols unavailable; fall through to direct
        }
        $conds = array_merge($conds, self::directConditions($objectId), self::icipLabelConditions($objectId));

        return self::strictest($conds);
    }

    /**
     * Access conditions from ICIP TK/BC labels applied to a record through the
     * ahg-icip governance UI (#1406 P2). Joins icip_tk_label to its type to read
     * the type's default_access_condition. Empty on any error / tables absent, so
     * ahg-icip being uninstalled simply contributes no conditions.
     */
    private static function icipLabelConditions(int $objectId): array
    {
        try {
            if (! self::icipLabelTablesExist()) {
                return [];
            }

            return DB::table('icip_tk_label as il')
                ->join('icip_tk_label_type as ilt', 'ilt.id', '=', 'il.label_type_id')
                ->where('il.information_object_id', $objectId)
                ->pluck('ilt.default_access_condition')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Whether ahg-icip's applied-label tables are present (cached per request). */
    private static ?bool $icipLabelTables = null;

    public static function icipLabelTablesExist(): bool
    {
        if (self::$icipLabelTables === null) {
            try {
                self::$icipLabelTables = \Illuminate\Support\Facades\Schema::hasTable('icip_tk_label')
                    && \Illuminate\Support\Facades\Schema::hasColumn('icip_tk_label_type', 'default_access_condition');
            } catch (\Throwable $e) {
                self::$icipLabelTables = false;
            }
        }

        return self::$icipLabelTables;
    }

    /**
     * The strictest condition attached DIRECTLY to an object (object_protocol),
     * ignoring inherited-term protocols. 'open' if none / table absent.
     */
    public static function conditionForObject(int $targetId, string $targetType = 'information_object'): string
    {
        return self::strictest(self::directConditions($targetId, $targetType));
    }

    /** Raw access-condition list from object_protocol for one target. Empty on any error/absent table. */
    private static function directConditions(int $targetId, string $targetType = 'information_object'): array
    {
        try {
            if (! self::objectProtocolTableExists()) {
                return [];
            }

            return DB::table('object_protocol')
                ->where('target_type', $targetType)
                ->where('target_id', $targetId)
                ->pluck('access_condition')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Whether the object_protocol table is present (cached per request). */
    private static ?bool $objectProtocolTable = null;

    public static function objectProtocolTableExists(): bool
    {
        if (self::$objectProtocolTable === null) {
            try {
                self::$objectProtocolTable = \Illuminate\Support\Facades\Schema::hasTable('object_protocol');
            } catch (\Throwable $e) {
                self::$objectProtocolTable = false;
            }
        }

        return self::$objectProtocolTable;
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
        $ids = [];
        try {
            $ids = DB::table('object_term_relation as otr')
                ->join('term_protocol as tp', 'tp.term_id', '=', 'otr.term_id')
                ->whereIn('tp.access_condition', self::RESTRICTED)
                ->pluck('otr.object_id')->all();
        } catch (\Throwable $e) {
            // inherited-term set unavailable; still return direct-object set below
        }
        try {
            if (self::objectProtocolTableExists()) {
                $direct = DB::table('object_protocol')
                    ->where('target_type', 'information_object')
                    ->whereIn('access_condition', self::RESTRICTED)
                    ->pluck('target_id')->all();
                $ids = array_merge($ids, $direct);
            }
        } catch (\Throwable $e) {
            // direct set unavailable
        }
        try {
            if (self::icipLabelTablesExist()) {
                $icip = DB::table('icip_tk_label as il')
                    ->join('icip_tk_label_type as ilt', 'ilt.id', '=', 'il.label_type_id')
                    ->whereIn('ilt.default_access_condition', self::RESTRICTED)
                    ->pluck('il.information_object_id')->all();
                $ids = array_merge($ids, $icip);
            }
        } catch (\Throwable $e) {
            // icip-label set unavailable
        }

        return collect($ids)->map('intval')->unique()->values()->all();
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

    /**
     * Canonical Local Contexts TK/BC label catalog (ahg-icip's icip_tk_label_type),
     * optionally filtered to a family ('tk'|'bc'). Empty collection if ahg-icip
     * isn't installed - the free-text code path still works without it.
     */
    public static function labelCatalog(?string $family = null): \Illuminate\Support\Collection
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('icip_tk_label_type')) {
                return collect();
            }
            $q = DB::table('icip_tk_label_type')->where('is_active', 1);
            if ($family) {
                $q->where('category', strtoupper($family));
            }

            return $q->orderBy('display_order')->orderBy('name')->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Resolve one label's Local Contexts metadata (name, description,
     * local_contexts_url, icon_path) for the badge/tooltip, from ahg-icip's
     * catalog. Case-insensitive on the stored code. Null if unknown or ahg-icip
     * is absent - callers fall back to the raw code.
     */
    public static function labelMeta(?string $code): ?object
    {
        $code = trim((string) $code);
        if ($code === '') {
            return null;
        }
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('icip_tk_label_type')) {
                return null;
            }

            return DB::table('icip_tk_label_type')
                ->whereRaw('LOWER(code) = ?', [strtolower($code)])
                ->first() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
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

    /** Inherited protocol row(s) from the terms tagged on a record - for the badge/tooltip. */
    public static function protocolsForRecord(int $objectId): \Illuminate\Support\Collection
    {
        try {
            return DB::table('object_term_relation as otr')
                ->join('term_protocol as tp', 'tp.term_id', '=', 'otr.term_id')
                ->where('otr.object_id', $objectId)
                ->select('tp.*')
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** The protocol row(s) attached DIRECTLY to an object (#1406 P1) - for the badge/tooltip. */
    public static function protocolsForObject(int $targetId, string $targetType = 'information_object'): \Illuminate\Support\Collection
    {
        try {
            if (! self::objectProtocolTableExists()) {
                return collect();
            }

            return DB::table('object_protocol')
                ->where('target_type', $targetType)
                ->where('target_id', $targetId)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * ICIP TK/BC labels applied to a record through ahg-icip (#1406 P2), shaped
     * like a protocol row for the badge: label_family (tk|bc), label_code,
     * access_condition (from the type's default), applied_by. Empty if ahg-icip
     * is absent.
     */
    public static function icipLabelsForObject(int $objectId): \Illuminate\Support\Collection
    {
        try {
            if (! self::icipLabelTablesExist()) {
                return collect();
            }

            return DB::table('icip_tk_label as il')
                ->join('icip_tk_label_type as ilt', 'ilt.id', '=', 'il.label_type_id')
                ->where('il.information_object_id', $objectId)
                ->get([
                    DB::raw('LOWER(ilt.category) as label_family'),
                    'ilt.code as label_code',
                    'ilt.default_access_condition as access_condition',
                    'il.applied_by as applied_by',
                ]);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Add a direct community protocol to an object (#1406 P1). Unlike {@see set()}
     * an object may legitimately carry MORE than one label (e.g. a TK Attribution
     * plus a BC Provenance notice), so this appends rather than replacing. Use
     * {@see clearObjectProtocol()} to remove one. Returns the new row id.
     */
    public static function setObject(
        int $targetId,
        ?string $labelFamily,
        ?string $labelCode,
        string $condition = 'open',
        string $targetType = 'information_object',
        ?int $ownerActorId = null,
        ?string $regionModule = null,
        bool $isNotice = false,
        ?int $createdBy = null
    ): int {
        $condition = $condition ?: 'open';

        return (int) DB::table('object_protocol')->insertGetId([
            'target_type'      => $targetType,
            'target_id'        => $targetId,
            'label_family'     => $labelFamily ?: null,
            'label_code'       => $labelCode ?: null,
            'access_condition' => $condition,
            'owner_actor_id'   => $ownerActorId,
            'region_module'    => $regionModule ?: null,
            'is_notice'        => $isNotice,
            'created_by'       => $createdBy,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /** Remove a single direct object protocol row by id. */
    public static function clearObjectProtocol(int $protocolId): void
    {
        try {
            DB::table('object_protocol')->where('id', $protocolId)->delete();
        } catch (\Throwable $e) {
            // no-op if table absent
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
