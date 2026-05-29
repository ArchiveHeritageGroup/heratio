<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers\Api;

use AhgLibrary\Http\Resources\LibraryOrderResource;
use AhgLibrary\Models\LibraryOrder;
use AhgLibrary\Models\LibraryOrderLine;
use AhgLibrary\Services\LibraryAcquisitionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * JSON:API CRUD for acquisitions orders (heratio#1100). Reuses
 * LibraryAcquisitionService for order-number generation and total/budget
 * recalculation so the API and the web acquisitions desk stay consistent.
 *
 * `?include=lines,vendor,budget` eager-loads those relationships into the
 * JSON:API relationships member.
 */
class LibraryOrderApiController extends Controller
{
    use AuthorizesLibraryApi;

    public function __construct(private LibraryAcquisitionService $acq) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeLibrary($request, 'read');
        [$page, $size] = $this->pageParams($request);

        $query = LibraryOrder::query()
            ->with($this->includes($request))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', $request->integer('vendor_id')))
            ->when($request->filled('budget_code'), fn ($q) => $q->where('budget_code', $request->string('budget_code')))
            ->orderByDesc('id');

        $total = (clone $query)->count();
        $items = $query->forPage($page, $size)->get();

        return LibraryOrderResource::collection($items)
            ->additional(['meta' => compact('total', 'page', 'size')]);
    }

    public function show(Request $request, LibraryOrder $order): LibraryOrderResource
    {
        $this->authorizeLibrary($request, 'read');
        $order->load($this->includes($request, ['lines', 'vendor', 'budget']));

        return new LibraryOrderResource($order);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeLibrary($request, 'create');
        $data = validator($this->jsonApiAttributes($request), [
            'order_number'   => ['nullable', 'string', 'max:50', Rule::unique('library_order', 'order_number')],
            'vendor_id'      => ['nullable', 'integer', Rule::exists('library_vendor', 'id')],
            'vendor_name'    => ['nullable', 'string', 'max:255'],
            'vendor_reference' => ['nullable', 'string', 'max:255'],
            'budget_code'    => ['nullable', 'string', Rule::exists('library_budget', 'budget_code')],
            'order_type'     => ['nullable', 'string', 'max:50'],
            'status'         => ['nullable', 'string', 'max:50'],
            'currency'       => ['nullable', 'string', 'max:8'],
            'order_date'     => ['nullable', 'date'],
            'expected_date'  => ['nullable', 'date'],
            'notes'          => ['nullable', 'string'],
            'lines'                 => ['nullable', 'array'],
            'lines.*.title'         => ['required_with:lines', 'string', 'max:500'],
            'lines.*.isbn'          => ['nullable', 'string', 'max:20'],
            'lines.*.author'        => ['nullable', 'string', 'max:500'],
            'lines.*.quantity'      => ['nullable', 'integer', 'min:1'],
            'lines.*.unit_price'    => ['nullable', 'numeric', 'min:0'],
        ])->validate();

        $order = DB::transaction(function () use ($data) {
            $order = LibraryOrder::create([
                'order_number'   => $data['order_number'] ?? $this->acq->generateOrderNumber(),
                'vendor_id'      => $data['vendor_id'] ?? null,
                'vendor_name'    => $data['vendor_name'] ?? ($data['vendor_id'] ?? null
                    ? (string) DB::table('library_vendor')->where('id', $data['vendor_id'])->value('name') : ''),
                'vendor_reference' => $data['vendor_reference'] ?? null,
                'budget_code'    => $data['budget_code'] ?? null,
                'order_type'     => $data['order_type'] ?? 'purchase',
                'status'         => $data['status'] ?? 'draft',
                'currency'       => $data['currency'] ?? 'ZAR',
                'order_date'     => $data['order_date'] ?? now()->toDateString(),
                'expected_date'  => $data['expected_date'] ?? null,
                'payment_status' => 'unpaid',
                'subtotal'       => 0, 'tax' => 0, 'shipping' => 0, 'total' => 0,
                'notes'          => $data['notes'] ?? null,
            ]);

            foreach ($data['lines'] ?? [] as $line) {
                $qty = (int) ($line['quantity'] ?? 1);
                $price = (float) ($line['unit_price'] ?? 0);
                LibraryOrderLine::create([
                    'order_id'    => $order->id,
                    'title'       => $line['title'],
                    'isbn'        => $line['isbn'] ?? null,
                    'author'      => $line['author'] ?? null,
                    'quantity'    => $qty,
                    'unit_price'  => $price,
                    'line_total'  => $qty * $price,
                    'status'      => 'pending',
                    'budget_code' => $order->budget_code,
                ]);
            }

            $this->acq->recalculateOrderTotals($order->id);
            if ($order->budget_code) {
                $this->acq->recalculateBudgetByCode($order->budget_code);
            }

            return $order;
        });

        return (new LibraryOrderResource($order->refresh()->load('lines', 'vendor', 'budget')))
            ->response()->setStatusCode(201);
    }

    public function update(Request $request, LibraryOrder $order): LibraryOrderResource
    {
        $this->authorizeLibrary($request, 'update');
        $data = validator($this->jsonApiAttributes($request), [
            'order_number'   => ['sometimes', 'string', 'max:50', Rule::unique('library_order', 'order_number')->ignore($order->id)],
            'vendor_id'      => ['nullable', 'integer', Rule::exists('library_vendor', 'id')],
            'vendor_name'    => ['nullable', 'string', 'max:255'],
            'vendor_reference' => ['nullable', 'string', 'max:255'],
            'budget_code'    => ['nullable', 'string', Rule::exists('library_budget', 'budget_code')],
            'order_type'     => ['sometimes', 'string', 'max:50'],
            'status'         => ['sometimes', 'string', 'max:50'],
            'payment_status' => ['sometimes', 'string', 'max:50'],
            'currency'       => ['sometimes', 'string', 'max:8'],
            'order_date'     => ['nullable', 'date'],
            'expected_date'  => ['nullable', 'date'],
            'received_date'  => ['nullable', 'date'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'notes'          => ['nullable', 'string'],
        ])->validate();

        $oldBudget = $order->budget_code;
        $order->update($data);

        // Keep both the old and new budget's committed totals in sync.
        foreach (array_unique(array_filter([$oldBudget, $order->budget_code])) as $code) {
            $this->acq->recalculateBudgetByCode($code);
        }

        return new LibraryOrderResource($order->refresh()->load('lines', 'vendor', 'budget'));
    }

    public function destroy(Request $request, LibraryOrder $order): JsonResponse
    {
        $this->authorizeLibrary($request, 'delete');

        $budgetCode = $order->budget_code;
        DB::transaction(function () use ($order) {
            $order->lines()->delete();
            $order->delete();
        });
        if ($budgetCode) {
            $this->acq->recalculateBudgetByCode($budgetCode);
        }

        return response()->json(null, 204);
    }

    /**
     * Resolve eager-load relations from ?include, intersected with the allowed
     * set. $default is used when no include param is present (show endpoint).
     *
     * @return array<int, string>
     */
    private function includes(Request $request, array $default = []): array
    {
        $allowed = ['lines', 'vendor', 'budget'];
        if (!$request->filled('include')) {
            return $default;
        }
        $requested = array_map('trim', explode(',', (string) $request->string('include')));

        return array_values(array_intersect($requested, $allowed));
    }
}
