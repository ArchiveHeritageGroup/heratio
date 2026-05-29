<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers\Api;

use AhgCore\Services\AclService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Shared helpers for the library JSON:API controllers (heratio#1100):
 * permission enforcement (same AclService gate as the web ACL + policies),
 * JSON:API request-body normalisation, and pagination parsing.
 */
trait AuthorizesLibraryApi
{
    /**
     * Enforce a library permission for the acting account. Works for both
     * session auth (Auth::id) and API-key auth (api_user_id request attribute,
     * set by ApiAuthenticate). Aborts 403 when denied. Administrators pass via
     * AclService::hasPermission.
     */
    protected function authorizeLibrary(Request $request, string $action): void
    {
        $userId = Auth::id() ?? $request->attributes->get('api_user_id');
        $userId = $userId !== null ? (int) $userId : null;

        if (!AclService::hasPermission($userId, $action)) {
            abort(403, "Insufficient permissions for library:{$action}.");
        }
    }

    /**
     * Accept either a flat JSON body ({"name": ...}) or a JSON:API envelope
     * ({"data": {"attributes": {...}}}).
     *
     * @return array<string, mixed>
     */
    protected function jsonApiAttributes(Request $request): array
    {
        $envelope = $request->input('data.attributes');

        return is_array($envelope) ? $envelope : $request->except(['data']);
    }

    /**
     * Resolve page + size, honouring both JSON:API page[number]/page[size] and
     * the simpler page/per_page query params.
     *
     * @return array{0:int,1:int}
     */
    protected function pageParams(Request $request): array
    {
        $page = $request->input('page');
        if (is_array($page)) {
            $number = (int) ($page['number'] ?? 1);
            $size   = (int) ($page['size'] ?? 25);
        } else {
            $number = (int) ($page ?? 1);
            $size   = (int) $request->input('per_page', 25);
        }

        return [max(1, $number), min(100, max(1, $size))];
    }
}
