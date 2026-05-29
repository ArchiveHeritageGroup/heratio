<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers\Api;

use AhgLibrary\Http\Resources\LibraryOrderLineResource;
use AhgLibrary\Models\LibraryOrder;
use AhgLibrary\Models\LibraryOrderLine;
use AhgLibrary\Services\LibraryAcquisitionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * JSON:API CRUD for acquisitions order lines (heratio#1100), nested under an
 * order for index/store and flat by line id for show/update/destroy. Every
 * mutation recalculates the parent order totals and budget commitment.
 */
class LibraryOrderLineApiController extends Controller
{
    use AuthorizesLibraryApi;

    public function __construct(private LibraryAcquisitionService $acq) {}

    public function index(Request $request, LibraryOrder $order): AnonymousResourceCollection
    {
        $this->authorizeLibrary($request, 'read');

        return LibraryOrderLineResource::collection(
            $order->lines()->orderBy('id')->get(),
        );
    }

    public function show(Request $request, LibraryOrderLine $line): LibraryOrderLineResource
    {
        $this->authorizeLibrary($request, 'read');

        return new LibraryOrderLineResource($line);
    }

    public function store(Request $request, LibraryOrder $order): JsonResponse
    {
        $this->authorizeLibrary($request, 'create');
        $data = $this->validateLine($request);

        $qty = (int) ($data['quantity'] ?? 1);
        $price = (float) ($data['unit_price'] ?? 0);
        $discount = (float) ($data['discount_percent'] ?? 0);

        $line = LibraryOrderLine::create(array_merge($data, [
            'order_id'    => $order->id,
            'quantity'    => $qty,
            'unit_price'  => $price,
            'line_total'  => round($qty * $price * (1 - $discount / 100), 2),
            'status'      => $data['status'] ?? 'pending',
            'budget_code' => $data['budget_code'] ?? $order->budget_code,
        ]));

        $this->recalc($order);

        return (new LibraryOrderLineResource($line))->response()->setStatusCode(201);
    }

    public function update(Request $request, LibraryOrderLine $line): LibraryOrderLineResource
    {
        $this->authorizeLibrary($request, 'update');
        $data = $this->validateLine($request, false);

        $line->fill($data);
        $qty = (int) $line->quantity;
        $price = (float) $line->unit_price;
        $discount = (float) $line->discount_percent;
        $line->line_total = round($qty * $price * (1 - $discount / 100), 2);
        $line->save();

        $this->recalc($line->order);

        return new LibraryOrderLineResource($line->refresh());
    }

    public function destroy(Request $request, LibraryOrderLine $line): JsonResponse
    {
        $this->authorizeLibrary($request, 'delete');
        $order = $line->order;
        $line->delete();
        if ($order) {
            $this->recalc($order);
        }

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function validateLine(Request $request, bool $creating = true): array
    {
        $titleRule = $creating ? ['required', 'string', 'max:500'] : ['sometimes', 'string', 'max:500'];

        return validator($this->jsonApiAttributes($request), [
            'title'            => $titleRule,
            'isbn'             => ['nullable', 'string', 'max:20'],
            'issn'             => ['nullable', 'string', 'max:20'],
            'author'           => ['nullable', 'string', 'max:500'],
            'publisher'        => ['nullable', 'string', 'max:255'],
            'pub_year'         => ['nullable', 'string', 'max:8'],
            'edition'          => ['nullable', 'string', 'max:100'],
            'material_type'    => ['nullable', 'string', 'max:50'],
            'quantity'         => ['nullable', 'integer', 'min:1'],
            'unit_price'       => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'library_item_id'  => ['nullable', 'integer'],
            'status'           => ['nullable', 'string', 'max:30'],
            'budget_code'      => ['nullable', 'string', 'max:50'],
            'fund_code'        => ['nullable', 'string', 'max:50'],
            'notes'            => ['nullable', 'string'],
        ])->validate();
    }

    private function recalc(?LibraryOrder $order): void
    {
        if (!$order) {
            return;
        }
        $this->acq->recalculateOrderTotals($order->id);
        if ($order->budget_code) {
            $this->acq->recalculateBudgetByCode($order->budget_code);
        }
    }
}
