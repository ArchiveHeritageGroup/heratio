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
            $name = (string) (DB::table('ahg_settings')
                ->where('setting_key', 'discovery_db_connection')
                ->value('setting_value') ?? 'atom');
            $this->discoveryConn = $name !== '' ? $name : 'atom';
        }
        try {
            return DB::connection($this->discoveryConn);
        } catch (\Throwable $e) {
            return DB::connection();
        }
    }
}
