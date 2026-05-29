<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers\Api;

use AhgLibrary\Http\Resources\LibrarySerialIssueResource;
use AhgLibrary\Models\LibrarySerial;
use AhgLibrary\Models\LibrarySerialIssue;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * JSON:API CRUD for serial issues (heratio#1092), nested under a serial for
 * index/store and flat by issue id for show/update/destroy - same shape as the
 * acquisitions order-lines controller.
 */
class LibrarySerialIssueApiController extends Controller
{
    use AuthorizesLibraryApi;

    public function index(Request $request, LibrarySerial $serial): AnonymousResourceCollection
    {
        $this->authorizeLibrary($request, 'read');
        [$page, $size] = $this->pageParams($request);

        $query = $serial->issues()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('issue_date');

        $total = (clone $query)->count();
        $items = $query->forPage($page, $size)->get();

        return LibrarySerialIssueResource::collection($items)
            ->additional(['meta' => compact('total', 'page', 'size')]);
    }

    public function show(Request $request, LibrarySerialIssue $issue): LibrarySerialIssueResource
    {
        $this->authorizeLibrary($request, 'read');

        return new LibrarySerialIssueResource($issue);
    }

    public function store(Request $request, LibrarySerial $serial): JsonResponse
    {
        $this->authorizeLibrary($request, 'create');
        $data = $this->validateIssue($request);

        $issue = LibrarySerialIssue::create(array_merge($data, [
            'serial_id' => $serial->id,
            'volume'       => $data['volume'] ?? '',
            'issue_number' => $data['issue_number'] ?? '',
            'status'       => $data['status'] ?? 'received',
        ]));

        return (new LibrarySerialIssueResource($issue))->response()->setStatusCode(201);
    }

    public function update(Request $request, LibrarySerialIssue $issue): LibrarySerialIssueResource
    {
        $this->authorizeLibrary($request, 'update');
        $data = $this->validateIssue($request, false);

        $issue->update($data);

        return new LibrarySerialIssueResource($issue->refresh());
    }

    public function destroy(Request $request, LibrarySerialIssue $issue): JsonResponse
    {
        $this->authorizeLibrary($request, 'delete');
        $issue->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function validateIssue(Request $request, bool $creating = true): array
    {
        return validator($this->jsonApiAttributes($request), [
            'volume'         => ['nullable', 'string', 'max:32'],
            'issue_number'   => ['nullable', 'string', 'max:32'],
            'issue_date'     => ['nullable', 'date'],
            'received_at'    => ['nullable', 'date'],
            'status'         => ['nullable', 'string', 'max:32'],
            'binding_id'     => ['nullable', 'integer'],
            'shelf_location' => ['nullable', 'string', 'max:255'],
            'bound_at'       => ['nullable', 'date'],
            'notes'          => ['nullable', 'string'],
        ])->validate();
    }
}
