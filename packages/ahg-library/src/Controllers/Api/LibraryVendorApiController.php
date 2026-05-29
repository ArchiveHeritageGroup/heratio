<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers\Api;

use AhgLibrary\Http\Resources\LibraryVendorResource;
use AhgLibrary\Models\LibraryVendor;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * JSON:API CRUD for acquisitions vendors (heratio#1100).
 */
class LibraryVendorApiController extends Controller
{
    use AuthorizesLibraryApi;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeLibrary($request, 'read');
        [$page, $size] = $this->pageParams($request);

        $query = LibraryVendor::query()
            ->when($request->filled('type'), fn ($q) => $q->where('vendor_type', $request->string('type')))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . $request->string('q') . '%'))
            ->orderBy('name');

        $total = (clone $query)->count();
        $items = $query->forPage($page, $size)->get();

        return LibraryVendorResource::collection($items)
            ->additional(['meta' => compact('total', 'page', 'size')]);
    }

    public function show(Request $request, LibraryVendor $vendor): LibraryVendorResource
    {
        $this->authorizeLibrary($request, 'read');

        return new LibraryVendorResource($vendor);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeLibrary($request, 'create');
        $data = validator($this->jsonApiAttributes($request), [
            'vendor_code'    => ['required', 'string', 'max:50', Rule::unique('library_vendor', 'vendor_code')],
            'name'           => ['required', 'string', 'max:255'],
            'vendor_type'    => ['nullable', Rule::in(['local', 'international'])],
            'account_number' => ['nullable', 'string', 'max:100'],
            'contact_name'   => ['nullable', 'string', 'max:255'],
            'email'          => ['nullable', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'website'        => ['nullable', 'string', 'max:255'],
            'address'        => ['nullable', 'string', 'max:500'],
            'city'           => ['nullable', 'string', 'max:120'],
            'country'        => ['nullable', 'string', 'max:120'],
            'currency'       => ['nullable', 'string', 'max:8'],
            'san'            => ['nullable', 'string', 'max:20'],
            'notes'          => ['nullable', 'string'],
            'is_active'      => ['nullable', 'boolean'],
        ])->validate();

        $vendor = LibraryVendor::create($data);

        return (new LibraryVendorResource($vendor))->response()->setStatusCode(201);
    }

    public function update(Request $request, LibraryVendor $vendor): LibraryVendorResource
    {
        $this->authorizeLibrary($request, 'update');
        $data = validator($this->jsonApiAttributes($request), [
            'vendor_code'    => ['sometimes', 'string', 'max:50', Rule::unique('library_vendor', 'vendor_code')->ignore($vendor->id)],
            'name'           => ['sometimes', 'string', 'max:255'],
            'vendor_type'    => ['sometimes', Rule::in(['local', 'international'])],
            'account_number' => ['nullable', 'string', 'max:100'],
            'contact_name'   => ['nullable', 'string', 'max:255'],
            'email'          => ['nullable', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'website'        => ['nullable', 'string', 'max:255'],
            'address'        => ['nullable', 'string', 'max:500'],
            'city'           => ['nullable', 'string', 'max:120'],
            'country'        => ['nullable', 'string', 'max:120'],
            'currency'       => ['nullable', 'string', 'max:8'],
            'san'            => ['nullable', 'string', 'max:20'],
            'notes'          => ['nullable', 'string'],
            'is_active'      => ['nullable', 'boolean'],
        ])->validate();

        $vendor->update($data);

        return new LibraryVendorResource($vendor->refresh());
    }

    public function destroy(Request $request, LibraryVendor $vendor): JsonResponse
    {
        $this->authorizeLibrary($request, 'delete');

        if ($vendor->orders()->exists()) {
            return response()->json([
                'errors' => [['status' => '409', 'title' => 'Conflict',
                    'detail' => 'Vendor has linked orders; deactivate it instead of deleting.']],
            ], 409);
        }

        $vendor->delete();

        return response()->json(null, 204);
    }
}
