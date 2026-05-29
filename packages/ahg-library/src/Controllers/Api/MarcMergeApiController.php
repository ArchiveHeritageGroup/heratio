<?php

/**
 * MarcMergeApiController - REST endpoint for MARC merge / conflict detection.
 *
 * POST /api/cataloguing/marc/merge
 *   Body: { "marcxml": "<record>...</record>", "culture": "en" }
 *   Returns the field-level diff report from MarcMergeService::diff().
 *   No writes happen here - this is the conflict-review pre-flight. HTTP 200
 *   always when the body parses; 422 on a malformed request body.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers\Api;

use AhgLibrary\Services\MarcMergeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarcMergeApiController extends Controller
{
    use AuthorizesLibraryApi;

    private MarcMergeService $merge;

    public function __construct(?MarcMergeService $merge = null)
    {
        $this->merge = $merge ?: new MarcMergeService();
    }

    public function merge(Request $request): JsonResponse
    {
        $this->authorizeLibrary($request, 'read');

        $data = validator($this->jsonApiAttributes($request), [
            'marcxml' => ['required', 'string'],
            'culture' => ['nullable', 'string', 'max:12'],
        ])->validate();

        $report = $this->merge->diff($data['marcxml'], $data['culture'] ?? 'en');

        return response()->json([
            'data' => [
                'type'       => 'marc-merge-report',
                'attributes' => $report,
            ],
        ], 200);
    }
}
