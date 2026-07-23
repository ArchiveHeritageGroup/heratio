<?php

namespace AhgRic\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persistent PER-RECORD RiC view preference (#1425 tail).
 *
 * Each record (keyed on its AtoM object.id, which every entity type shares -
 * actor / repository / function / accession / information_object / ...) can
 * durably prefer the RiC relational lens or its flat description standard.
 * This replaces the old session-global `ric_view_mode` toggle: a choice made
 * on one record no longer bleeds onto every other record for the session.
 *
 * Every method is guarded by Schema::hasTable so a minimal / pre-install host
 * degrades to the config default rather than fatalling (the sidecar table is
 * self-installed by AhgRicServiceProvider::boot()).
 */
class RicViewModeService
{
    private const TABLE = 'ric_entity_view';
    public const MODES = ['heratio', 'ric'];

    /** Cache the hasTable probe per-request so repeated show-page calls are cheap. */
    private static ?bool $tableExists = null;

    private static function available(): bool
    {
        if (self::$tableExists === null) {
            try {
                self::$tableExists = Schema::hasTable(self::TABLE);
            } catch (\Throwable $e) {
                self::$tableExists = false;
            }
        }

        return self::$tableExists;
    }

    /** The default when a record has no stored preference. */
    public static function defaultMode(): string
    {
        $default = (string) config('ric.default_view', 'heratio');

        return in_array($default, self::MODES, true) ? $default : 'heratio';
    }

    /**
     * The effective view mode for a record: its stored per-record preference
     * if any, otherwise the configured default.
     */
    public static function mode(?int $objectId): string
    {
        if (! $objectId || ! self::available()) {
            return self::defaultMode();
        }

        try {
            $stored = DB::table(self::TABLE)->where('object_id', $objectId)->value('view_mode');
        } catch (\Throwable $e) {
            $stored = null;
        }

        return in_array($stored, self::MODES, true) ? $stored : self::defaultMode();
    }

    /** Convenience predicate used by the show pages. */
    public static function isRic(?int $objectId): bool
    {
        return self::mode($objectId) === 'ric';
    }

    /**
     * Persist a record's preference. An explicit choice is stored even when it
     * equals the current default, so a later default change cannot silently
     * flip records whose owner deliberately picked the other view.
     */
    public static function set(int $objectId, string $mode): void
    {
        if (! in_array($mode, self::MODES, true) || ! self::available()) {
            return;
        }

        try {
            DB::table(self::TABLE)->updateOrInsert(
                ['object_id' => $objectId],
                ['view_mode' => $mode, 'updated_at' => now()]
            );
        } catch (\Throwable $e) {
            // Non-fatal: the toggle silently no-ops if the write fails.
        }
    }
}
