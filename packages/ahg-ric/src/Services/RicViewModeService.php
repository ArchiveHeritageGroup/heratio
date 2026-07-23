<?php

namespace AhgRic\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persistent PER-RECORD RiC view preference (#1425 tail).
 *
 * Each record can durably prefer the RiC relational lens or its flat
 * description standard. This replaces the old session-global `ric_view_mode`
 * toggle: a choice made on one record no longer bleeds onto every other record
 * for the session.
 *
 * Keyed on (entity_type, entity_id) rather than a bare object.id, because not
 * every wired entity is an AtoM object-subtype - `loan`, for one, is a
 * standalone custom table whose small auto-increment ids would collide with
 * object ids in a shared single-column key. The composite key keeps every
 * entity type in its own namespace.
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
    public static function mode(string $entityType, ?int $entityId): string
    {
        if (! $entityId || $entityType === '' || ! self::available()) {
            return self::defaultMode();
        }

        try {
            $stored = DB::table(self::TABLE)
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->value('view_mode');
        } catch (\Throwable $e) {
            $stored = null;
        }

        return in_array($stored, self::MODES, true) ? $stored : self::defaultMode();
    }

    /** Convenience predicate used by the show pages. */
    public static function isRic(string $entityType, ?int $entityId): bool
    {
        return self::mode($entityType, $entityId) === 'ric';
    }

    /**
     * Persist a record's preference. An explicit choice is stored even when it
     * equals the current default, so a later default change cannot silently
     * flip records whose owner deliberately picked the other view.
     */
    public static function set(string $entityType, int $entityId, string $mode): void
    {
        if (! in_array($mode, self::MODES, true) || $entityType === '' || ! self::available()) {
            return;
        }

        try {
            DB::table(self::TABLE)->updateOrInsert(
                ['entity_type' => $entityType, 'entity_id' => $entityId],
                ['view_mode' => $mode, 'updated_at' => now()]
            );
        } catch (\Throwable $e) {
            // Non-fatal: the toggle silently no-ops if the write fails.
        }
    }
}
