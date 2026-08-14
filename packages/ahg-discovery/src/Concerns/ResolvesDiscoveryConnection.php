<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgDiscovery\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Resolve (and cache) the discovery database connection, named by the
 * ahg_settings.discovery_db_connection setting (default 'atom'), falling
 * back to the default connection on error. Method + its cache property were
 * byte-identical in DiscoveryController and PageIndexService.
 */
trait ResolvesDiscoveryConnection
{
    private ?string $discoveryConn = null;

    private function discoveryDb(): \Illuminate\Database\ConnectionInterface
    {
        if ($this->discoveryConn === null) {
            // Falls back to THIS application's own connection, not 'atom'.
            //
            // The atom database only exists on an install overlaying AtoM. Where
            // it does not, or where the app's DB user has no rights to it, every
            // read here failed with "Access denied ... to database 'atom'".
            // Pinning to atom is an opt-in for that overlay (heratio.org sets it
            // explicitly to share the ANC corpus); a self-contained install
            // should read its own data, which is what the setting's own
            // documentation says it is for.
            $name = (string) (DB::table('ahg_settings')
                ->where('setting_key', 'discovery_db_connection')
                ->value('setting_value') ?? '');
            $this->discoveryConn = $name !== '' ? $name : (string) config('database.default');
        }
        try {
            return DB::connection($this->discoveryConn);
        } catch (\Throwable $e) {
            return DB::connection();
        }
    }
}
