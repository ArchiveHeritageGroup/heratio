<?php

/**
 * OpenApiController
 *
 * Serves the live OpenAPI 3.1 spec at /api/openapi.json and Swagger UI at
 * /api/docs. Both endpoints respect the ahg_settings.openapi_public flag
 * (default false). When disabled, an authenticated admin session is required.
 *
 * Issue #652 Phase 1.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright 2026 Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgApi\Controllers;

use AhgApi\Services\OpenApiGenerator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

class OpenApiController extends Controller
{
    /**
     * GET /api/openapi.json
     */
    public function spec(Request $request, OpenApiGenerator $generator): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Forbidden',
                'message' => 'OpenAPI spec is not publicly exposed on this installation. Log in as an admin or enable openapi_public.',
                'timestamp' => now()->toIso8601String(),
            ], 403);
        }

        $spec = Cache::remember('ahg_api.openapi_spec', 60, fn () => $generator->generate());

        return response()->json($spec)
            ->header('Cache-Control', 'public, max-age=60')
            ->header('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * GET /api/docs
     */
    public function docs(Request $request): Response
    {
        if (! $this->canView($request)) {
            abort(403, 'OpenAPI docs are not publicly exposed on this installation.');
        }

        $html = View::make('ahg-api::swagger-ui', [
            'specUrl' => url('/api/openapi.json'),
            'title' => 'Heratio API - Swagger UI',
        ])->render();

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Visibility rule:
     *   - logged-in admin = always allowed
     *   - ahg_settings.openapi_public = '1' / true / yes = anyone allowed
     */
    protected function canView(Request $request): bool
    {
        if ($request->user()) {
            return true;
        }

        if (! Schema::hasTable('ahg_settings')) {
            return false;
        }

        $val = DB::table('ahg_settings')
            ->where('setting_key', 'openapi_public')
            ->value('setting_value');

        $val = strtolower((string) $val);

        return in_array($val, ['1', 'true', 'yes', 'on'], true);
    }
}
