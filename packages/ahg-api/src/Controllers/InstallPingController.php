<?php

/**
 * InstallPingController - receives the opt-in install ping sent by
 * docker/install-heratio.sh (heratio#1571), so AHG can count real installs:
 * the GHCR download total mostly counts our own build pipeline.
 *
 * Stores a random install id, the version and the CPU architecture, nothing
 * else - not the caller's IP address. A reinstall that reuses its id bumps
 * the existing row. Always answers 204 for a well-formed ping, so the
 * installer has nothing to wait on.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgApi\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstallPingController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'install_id' => 'required|uuid',
            'version' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'arch' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            'source' => 'nullable|in:installer',
        ]);

        if (Schema::hasTable('heratio_install_ping')) {
            $id = strtolower($data['install_id']);
            $row = ['version' => $data['version'], 'arch' => $data['arch'], 'last_seen' => now()];
            if (DB::table('heratio_install_ping')->where('install_id', $id)->exists()) {
                DB::table('heratio_install_ping')->where('install_id', $id)->update($row + ['pings' => DB::raw('pings + 1')]);
            } else {
                DB::table('heratio_install_ping')->insertOrIgnore($row + ['install_id' => $id, 'source' => 'installer', 'first_seen' => now()]);
            }
        }

        return response()->noContent();
    }
}
