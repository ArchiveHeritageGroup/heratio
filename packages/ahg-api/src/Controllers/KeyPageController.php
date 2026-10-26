<?php

/**
 * KeyPageController - Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgApi\Controllers;

use AhgApi\Middleware\ApiAuthenticate;
use AhgApi\Services\ApiKeyService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * /api/keys - a signed-in user's own API keys (#1558, #1566): create one
 * (scopes capped at the user's own, optional expiry and daily cap), see when
 * each was last used, revoke it. This is the page the AHG Archives MCP sends
 * people to for the key they sign in with; revoking a key disconnects it.
 */
class KeyPageController extends Controller
{
    public function __construct(protected ApiKeyService $keys) {}

    public function index()
    {
        $userId = (int) Auth::id();
        $scopes = ApiAuthenticate::sessionScopes($userId);
        $rows = DB::table('ahg_api_key')->where('user_id', $userId)->orderByDesc('created_at')->get()
            ->map(function ($k) {
                $k->scopes = json_decode($k->scopes ?? '[]', true) ?: [];

                return $k;
            });

        return view('ahg-api::keys', [
            'keys' => $rows,
            'scopes' => $scopes,
            'newKey' => session('new_key'),
        ]);
    }

    public function store(Request $request)
    {
        $userId = (int) Auth::id();
        $allowed = ApiAuthenticate::sessionScopes($userId);
        $input = $request->validate([
            'name' => 'required|string|max:100',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|in:'.implode(',', $allowed ?: ['read']),
            'expires_at' => 'nullable|date|after:today',
            'daily_limit' => 'nullable|integer|min:1|max:1000000',
        ]);

        // A user with no catalogue capability gets a key for the public
        // catalogue only (ApiAuthenticate); read is all it can carry.
        $scopes = $allowed ? array_values(array_intersect($input['scopes'] ?? ['read'], $allowed)) : ['read'];
        if (! $scopes) {
            $scopes = ['read'];
        }

        $key = $this->keys->createKey($userId, $input['name'], $scopes, 1000, $input['expires_at'] ?? null);
        if (! empty($input['daily_limit'])) {
            DB::table('ahg_api_key')->where('id', $key['id'])->update(['daily_limit' => (int) $input['daily_limit']]);
        }

        return redirect()->route('api.keys')->with('new_key', $key['api_key']);
    }

    public function revoke(int $id)
    {
        DB::table('ahg_api_key')->where('id', $id)->where('user_id', (int) Auth::id())
            ->update(['is_active' => 0, 'updated_at' => now()]);

        return redirect()->route('api.keys')->with('success', __('The key is revoked. Anything using it is disconnected.'));
    }
}
