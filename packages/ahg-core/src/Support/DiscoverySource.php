<?php

/**
 * Which database a command should read its corpus from.
 *
 * Several commands defaulted to the connection named `atom`. That database
 * exists only on an install overlaying AtoM; on a Heratio-native instance it is
 * either absent or belongs to another account, so every scheduled run died with
 * "Access denied for user 'sasa'@'127.0.0.1' to database 'atom'". Pinning to
 * atom is an opt-in for the overlay case (heratio.org sets it explicitly to
 * share the ANC corpus), never a default.
 *
 * The same three lines had already been copied into
 * AuthorityFunctionSyncCommand, ResolvesDiscoveryConnection and
 * RefreshFacetCacheCommand before this existed. One definition instead.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio, licensed under the GNU AGPL v3 or later.
 */

namespace AhgCore\Support;

use Illuminate\Support\Facades\DB;

class DiscoverySource
{
    /**
     * The configured discovery connection, or this application's own.
     *
     * @param  string|null  $explicit  a --connection option, which always wins
     */
    public static function connectionName(?string $explicit = null): string
    {
        $explicit = trim((string) $explicit);
        if ($explicit !== '') {
            return $explicit;
        }

        try {
            $name = (string) (DB::table('ahg_settings')
                ->where('setting_key', 'discovery_db_connection')
                ->value('setting_value') ?? '');
        } catch (\Throwable $e) {
            $name = '';
        }

        return $name !== '' ? $name : (string) config('database.default');
    }

    /**
     * Can this connection actually be queried?
     *
     * A configured-but-forbidden source is nothing to process, not a fault - the
     * caller should report and exit 0 rather than throw. Note the probe runs a
     * real statement: constructing the connection succeeds even when the grant
     * is missing, so only a query reveals it.
     */
    public static function usable(string $connection): bool
    {
        try {
            DB::connection($connection)->select('SELECT 1');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
