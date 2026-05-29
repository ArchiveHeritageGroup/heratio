<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers\Api;

use AhgLibrary\Http\Resources\LibrarySerialSubscriptionResource;
use AhgLibrary\Models\LibrarySerial;
use AhgLibrary\Models\LibrarySerialSubscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * JSON:API for serial subscriptions (heratio#1092). A serial has at most one
 * subscription (unique serial_id), so the nested resource is upserted: storing
 * against a serial that already has a subscription updates it. Flat show /
 * update / destroy operate by subscription id.
 */
class LibrarySerialSubscriptionApiController extends Controller
{
    use AuthorizesLibraryApi;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeLibrary($request, 'read');
        [$page, $size] = $this->pageParams($request);

        $query = LibrarySerialSubscription::query()
            ->when($request->filled('serial_id'), fn ($q) => $q->where('serial_id', $request->integer('serial_id')))
            ->orderBy('serial_id');

        $total = (clone $query)->count();
        $items = $query->forPage($page, $size)->get();

        return LibrarySerialSubscriptionResource::collection($items)
            ->additional(['meta' => compact('total', 'page', 'size')]);
    }

    public function show(Request $request, LibrarySerialSubscription $subscription): LibrarySerialSubscriptionResource
    {
        $this->authorizeLibrary($request, 'read');

        return new LibrarySerialSubscriptionResource($subscription);
    }

    public function store(Request $request, LibrarySerial $serial): JsonResponse
    {
        $this->authorizeLibrary($request, 'create');
        $data = $this->validateSubscription($request);

        // Upsert - one subscription per serial.
        $subscription = LibrarySerialSubscription::updateOrCreate(
            ['serial_id' => $serial->id],
            $data
        );

        $created = $subscription->wasRecentlyCreated;

        return (new LibrarySerialSubscriptionResource($subscription->refresh()))
            ->response()->setStatusCode($created ? 201 : 200);
    }

    public function update(Request $request, LibrarySerialSubscription $subscription): LibrarySerialSubscriptionResource
    {
        $this->authorizeLibrary($request, 'update');
        $data = $this->validateSubscription($request);

        $subscription->update($data);

        return new LibrarySerialSubscriptionResource($subscription->refresh());
    }

    public function destroy(Request $request, LibrarySerialSubscription $subscription): JsonResponse
    {
        $this->authorizeLibrary($request, 'delete');
        $subscription->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function validateSubscription(Request $request): array
    {
        return validator($this->jsonApiAttributes($request), [
            'subscription_start' => ['nullable', 'date'],
            'subscription_end'   => ['nullable', 'date'],
            'subscription_cost'  => ['nullable', 'numeric', 'min:0'],
            'notification_email' => ['nullable', 'email', 'max:255'],
            'auto_claim_max'     => ['nullable', 'integer', 'min:0', 'max:255'],
            'notes'              => ['nullable', 'string'],
        ])->validate();
    }
}
