<?php

/**
 * ResearchApiKeysController - Controller for Heratio
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



namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ResearchApiKeysController - Researcher self-service API key management.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1253 / #1269). The single endpoint is auth-gated and operates on the
 * current (approved) researcher's own API keys via ResearchService. No
 * cross-calls to other ResearchController methods existed - the method used
 * only the shared trait helper (getSidebarData) and the injected
 * ResearchService (getResearcherByUserId, getApiKeys, generateApiKey,
 * revokeApiKey), so the move is a verbatim lift.
 */
class ResearchApiKeysController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function apiKeys(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher || $researcher->status !== 'approved') {
            return redirect()->route('research.dashboard')->with('error', 'Must be an approved researcher');
        }

        $apiKeys = $this->service->getApiKeys($researcher->id);

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');
            if ($action === 'generate') {
                $result = $this->service->generateApiKey(
                    $researcher->id,
                    trim($request->input('name', 'API Key')),
                    $request->input('permissions', []),
                    $request->input('expires_at') ?: null
                );
                return redirect()->route('research.apiKeys')
                    ->with('success', 'API key generated successfully. <br><code id="apiKeyValue" class="user-select-all fs-6">' . e($result['key']) . '</code><br><small class="text-muted">Copy this key now — it will not be shown again.</small>');
            }
            if ($action === 'revoke') {
                $this->service->revokeApiKey((int) $request->input('key_id'), $researcher->id);
                return redirect()->route('research.apiKeys')->with('success', 'API key revoked');
            }
        }

        return view('research::research.api-keys', array_merge(
            $this->getSidebarData('profile'),
            compact('researcher', 'apiKeys')
        ));
    }
}
