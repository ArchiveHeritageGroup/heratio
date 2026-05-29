<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers\Api;

use AhgLibrary\Http\Resources\LibrarySerialResource;
use AhgLibrary\Models\LibrarySerial;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * JSON:API CRUD for serial titles (heratio#1092). Mirrors the acquisitions
 * JSON:API pattern (LibraryOrderApiController): shared AuthorizesLibraryApi
 * trait for auth + body normalisation + pagination, JsonApiResource envelope,
 * and `?include=issues,subscription` eager-loading.
 */
class LibrarySerialApiController extends Controller
{
    use AuthorizesLibraryApi;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeLibrary($request, 'read');
        [$page, $size] = $this->pageParams($request);

        $query = LibrarySerial::query()
            ->with($this->includes($request))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('frequency'), fn ($q) => $q->where('frequency', $request->string('frequency')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $needle = '%' . $request->string('search') . '%';
                $q->where(fn ($w) => $w->where('title', 'LIKE', $needle)
                    ->orWhere('issn', 'LIKE', $needle)
                    ->orWhere('publisher', 'LIKE', $needle));
            })
            ->orderBy('title');

        $total = (clone $query)->count();
        $items = $query->forPage($page, $size)->get();

        return LibrarySerialResource::collection($items)
            ->additional(['meta' => compact('total', 'page', 'size')]);
    }

    public function show(Request $request, LibrarySerial $serial): LibrarySerialResource
    {
        $this->authorizeLibrary($request, 'read');
        $serial->load($this->includes($request, ['issues', 'subscription']));

        return new LibrarySerialResource($serial);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeLibrary($request, 'create');
        $data = validator($this->jsonApiAttributes($request), [
            'title'     => ['required', 'string', 'max:500'],
            'issn'      => ['nullable', 'string', 'max:20'],
            'frequency' => ['nullable', 'string', 'max:64'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'status'    => ['nullable', 'string', 'max:32'],
            'notes'     => ['nullable', 'string'],
        ])->validate();

        $serial = LibrarySerial::create([
            'title'     => $data['title'],
            'issn'      => $data['issn'] ?? '',
            'frequency' => $data['frequency'] ?? '',
            'publisher' => $data['publisher'] ?? '',
            'status'    => $data['status'] ?? 'active',
            'notes'     => $data['notes'] ?? null,
        ]);

        return (new LibrarySerialResource($serial))->response()->setStatusCode(201);
    }

    public function update(Request $request, LibrarySerial $serial): LibrarySerialResource
    {
        $this->authorizeLibrary($request, 'update');
        $data = validator($this->jsonApiAttributes($request), [
            'title'     => ['sometimes', 'string', 'max:500'],
            'issn'      => ['sometimes', 'string', 'max:20'],
            'frequency' => ['sometimes', 'string', 'max:64'],
            'publisher' => ['sometimes', 'string', 'max:255'],
            'status'    => ['sometimes', 'string', 'max:32'],
            'notes'     => ['nullable', 'string'],
        ])->validate();

        $serial->update($data);

        return new LibrarySerialResource($serial->refresh());
    }

    public function destroy(Request $request, LibrarySerial $serial): JsonResponse
    {
        $this->authorizeLibrary($request, 'delete');

        $serial->issues()->delete();
        $serial->subscription()->delete();
        $serial->delete();

        return response()->json(null, 204);
    }

    /** @return array<int, string> */
    private function includes(Request $request, array $default = []): array
    {
        $allowed = ['issues', 'subscription'];
        if (!$request->filled('include')) {
            return $default;
        }
        $requested = array_map('trim', explode(',', (string) $request->string('include')));

        return array_values(array_intersect($requested, $allowed));
    }
}
