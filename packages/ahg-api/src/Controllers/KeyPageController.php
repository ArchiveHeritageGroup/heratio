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
 * API keys per user (#1558, #1566). /api/keys is a signed-in user's own keys;
 * an administrator manages anyone's from their user page (/user/{slug}).
 * Each key has its own scopes - never more than its owner's role allows -
 * an optional expiry and daily limit, and shows its last use. The secret is
 * shown once. Revoking a key refuses anything using it at once.
 */
class KeyPageController extends Controller
{
    public function __construct(protected ApiKeyService $keys) {}

    public function index()
    {
        $userId = (int) Auth::id();

        return view('ahg-api::keys', $this->managerData($userId) + [
            'storeUrl' => route('api.keys.store'),
            'revokeRoute' => fn ($id) => route('api.keys.revoke', $id),
        ]);
    }

    public function store(Request $request)
    {
        $key = $this->issueFrom($request, (int) Auth::id());

        return redirect()->route('api.keys')->with('new_key', $key['api_key']);
    }

    public function revoke(int $id)
    {
        $this->keys->revoke($id, (int) Auth::id());

        return redirect()->route('api.keys')->with('success', __('The key is revoked. Anything using it is disconnected.'));
    }

    /** POST /user/{slug}/api-keys - an administrator issues a key for this user. */
    public function storeFor(Request $request, string $slug)
    {
        $ownerId = $this->ownerOf($slug);
        $key = $this->issueFrom($request, $ownerId);

        return redirect(route('user.show', $slug).'#apiKeys')->with('new_key', $key['api_key']);
    }

    /** POST /user/{slug}/api-keys/{id}/revoke */
    public function revokeFor(string $slug, int $id)
    {
        $this->keys->revoke($id, $this->ownerOf($slug));

        return redirect(route('user.show', $slug).'#apiKeys')->with('success', __('The key is revoked. Anything using it is disconnected.'));
    }

    /** What the key manager partial needs for one owner. */
    public function managerData(int $ownerId): array
    {
        return [
            'keys' => $this->keys->keysFor($ownerId),
            'scopes' => $this->keys->allowedScopes($ownerId),
            'scopeLabels' => ApiKeyService::SCOPES,
            'publicOnly' => $this->keys->publicOnly($ownerId),
            'newKey' => session('new_key'),
        ];
    }

    protected function issueFrom(Request $request, int $ownerId): array
    {
        $input = $request->validate([
            'name' => 'required|string|max:100',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|in:'.implode(',', $this->keys->allowedScopes($ownerId)),
            'expires_at' => 'nullable|date|after:today',
            'daily_limit' => 'nullable|integer|min:1|max:1000000',
        ], [
            'scopes.*.in' => __('This user\'s role does not allow the ":input" scope.'),
        ]);

        return $this->keys->issue($ownerId, $input['name'], $input['scopes'] ?? ['read'],
            $input['expires_at'] ?? null, isset($input['daily_limit']) ? (int) $input['daily_limit'] : null);
    }

    /** The user behind a slug, for administrators only. */
    protected function ownerOf(string $slug): int
    {
        abort_unless(\AhgCore\Services\AclService::isAdministrator(Auth::user()), 403);
        $id = (int) DB::table('slug')->join('user', 'user.id', '=', 'slug.object_id')->where('slug.slug', $slug)->value('user.id');
        abort_unless($id > 0, 404);

        return $id;
    }
}
