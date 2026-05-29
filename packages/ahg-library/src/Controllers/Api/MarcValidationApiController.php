<?php

/**
 * MarcValidationApiController - REST endpoint for MARC21/MARCXML validation.
 *
 * POST /api/cataloguing/marc/validate
 *   Body: { "marcxml": "<record>...</record>" }
 *   Returns the structured report from MarcValidationService::validate(),
 *   wrapped in a JSON:API-style envelope. HTTP 200 always (the report itself
 *   carries valid=true|false); 422 only when the request body is malformed.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers\Api;

use AhgLibrary\Services\MarcValidationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarcValidationApiController extends Controller
{
    use AuthorizesLibraryApi;

    private MarcValidationService $validator;

    public function __construct(?MarcValidationService $validator = null)
    {
        $this->validator = $validator ?: new MarcValidationService();
    }

    public function validateRecord(Request $request): JsonResponse
    {
        $this->authorizeLibrary($request, 'read');

        $data = validator($this->jsonApiAttributes($request), [
            'marcxml' => ['required', 'string'],
        ])->validate();

        $report = $this->validator->validate($data['marcxml']);

        return response()->json([
            'data' => [
                'type'       => 'marc-validation-report',
                'attributes' => $report,
            ],
        ], 200);
    }
}
