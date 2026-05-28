<?php

/**
 * LibraryAcquisitionController - orders CRUD + AJAX line management
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgLibrary\Controllers;

use AhgLibrary\Http\Requests\StoreLibraryOrderRequest;
use AhgLibrary\Http\Requests\UpdateLibraryOrderRequest;
use AhgLibrary\Services\LibraryAcquisitionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class LibraryAcquisitionController extends Controller
{
    public function __construct(protected LibraryAcquisitionService $acq) {}

    // ── List / Index ────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $orders = $this->acq->listOrders([
            'status'  => $request->query('status'),
            'search'   => $request->query('q'),
        ]);

        return view('ahg-library::acquisition.order-list', [
            'orders' => collect($orders),
            'statusFilter' => $request->query('status'),
            'searchQuery'  => $request->query('q'),
        ]);
    }

    // ── Create ─────────────────────────────────────────────────────────────

    public function create(): View
    {
        $budgets = $this->acq->listBudgets();

        return view('ahg-library::acquisition.order-create', [
            'order'   => null,
            'budgets' => collect($budgets),
        ]);
    }

    public function store(StoreLibraryOrderRequest $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validated();

        // Extract lines before persisting the order header
        $lines = $data['lines'] ?? [];
        unset($data['lines']);

        // Persist header
        $orderId = $this->acq->createOrder($data);

        if ($orderId === 0) {
            return back()->withInput()->with('error', 'Failed to create order.');
        }

        // Persist lines in order
        foreach ($lines as $line) {
            $this->acq->addLine($orderId, $line);
        }

        return redirect()
            ->route('library.acquisition-order', $orderId)
            ->with('success', 'Order created.');
    }

    // ── Show ────────────────────────────────────────────────────────────────

    public function show(int $id): View
    {
        $order = $this->acq->getOrder($id);
        if (!$order) {
            abort(404);
        }

        $lines = $this->acq->getOrderLines($id);
        $budget = $order->budget_code ? $this->acq->getBudgetByCode($order->budget_code) : null;

        return view('ahg-library::acquisition.order-show', [
            'order'  => $order,
            'lines'  => collect($lines),
            'budget' => $budget,
        ]);
    }

    // ── Edit ───────────────────────────────────────────────────────────────

    public function edit(int $id): View
    {
        $order = $this->acq->getOrder($id);
        if (!$order) {
            abort(404);
        }

        return view('ahg-library::acquisition.order-edit', [
            'order'   => $order,
            'budgets' => collect($this->acq->listBudgets()),
            'lines'   => collect($this->acq->getOrderLines($id)),
        ]);
    }

    public function update(UpdateLibraryOrderRequest $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $order = $this->acq->getOrder($id);
        if (!$order) {
            abort(404);
        }

        $data = $request->validated();

        // Persist header
        $updated = $this->acq->updateOrder($id, $data);
        if (!$updated) {
            Log::warning('LibraryAcquisitionController: updateOrder returned false', ['id' => $id]);
        }

        // Recalculate totals (shipping + handling + sum(lines))
        $this->acq->recalculateOrderTotals($id);

        return redirect()
            ->route('library.acquisition-order', $id)
            ->with('success', 'Order updated.');
    }

    // ── AJAX line management ────────────────────────────────────────────────

    /**
     * GET /acquisition/order/{id}/lines  →  _order-lines partial (AJAX refresh)
     */
    public function lines(int $id): View|JsonResponse
    {
        $order = $this->acq->getOrder($id);
        if (!$order) {
            if (request()->expectsJson()) {
                return response()->json(['error' => 'Order not found.'], 404);
            }
            abort(404);
        }

        return view('ahg-library::acquisition._order-lines', [
            'lines'  => collect($this->acq->getOrderLines($id)),
            'order'  => $order,
            'editable' => !in_array($order->status, ['received', 'cancelled']),
        ]);
    }

    /**
     * POST /acquisition/order/{id}/lines  →  add line via AJAX
     */
    public function addLine(Request $request, int $id): JsonResponse
    {
        $order = $this->acq->getOrder($id);
        if (!$order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        if (in_array($order->status, ['received', 'cancelled'])) {
            return response()->json(['error' => 'Cannot add lines to a received or cancelled order.'], 422);
        }

        $validated = $request->validate([
            'title'         => ['required', 'string', 'max:500'],
            'isbn'          => ['nullable', 'string', 'max:20'],
            'author'        => ['nullable', 'string', 'max:300'],
            'publisher'     => ['nullable', 'string', 'max:255'],
            'pub_year'      => ['nullable', 'integer', 'min:1000', 'max:2100'],
            'format'        => ['nullable', 'string', 'max:50'],
            'quantity'      => ['nullable', 'integer', 'min:1', 'max:9999'],
            'unit_price'    => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'currency'      => ['nullable', 'string', 'max:3'],
            'supplier_code' => ['nullable', 'string', 'max:50'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ]);

        $lineId = $this->acq->addLine($id, $validated);
        $line   = $this->acq->getLine($lineId);
        $this->acq->recalculateOrderTotals($id);

        return response()->json([
            'success'  => true,
            'line_id'  => $lineId,
            'line'     => $line,
            'order'    => $this->acq->getOrder($id),
        ]);
    }

    /**
     * PUT /acquisition/order/{id}/line/{lineId}  →  update line via AJAX
     */
    public function updateLine(Request $request, int $id, int $lineId): JsonResponse
    {
        $order = $this->acq->getOrder($id);
        if (!$order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        $line = $this->acq->getLine($lineId);
        if (!$line || (int) $line->order_id !== $id) {
            return response()->json(['error' => 'Line not found.'], 404);
        }

        $validated = $request->validate([
            'title'         => ['nullable', 'string', 'max:500'],
            'isbn'          => ['nullable', 'string', 'max:20'],
            'author'        => ['nullable', 'string', 'max:300'],
            'publisher'     => ['nullable', 'string', 'max:255'],
            'pub_year'      => ['nullable', 'integer', 'min:1000', 'max:2100'],
            'format'        => ['nullable', 'string', 'max:50'],
            'quantity'      => ['nullable', 'integer', 'min:1', 'max:9999'],
            'unit_price'    => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'currency'      => ['nullable', 'string', 'max:3'],
            'supplier_code' => ['nullable', 'string', 'max:50'],
            'received_qty'  => ['nullable', 'integer', 'min:0'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ]);

        $this->acq->updateLine($lineId, $validated);
        $this->acq->recalculateOrderTotals($id);

        return response()->json([
            'success' => true,
            'line'    => $this->acq->getLine($lineId),
            'order'   => $this->acq->getOrder($id),
        ]);
    }

    /**
     * DELETE /acquisition/order/{id}/line/{lineId}  →  remove line
     */
    public function removeLine(int $id, int $lineId): \Illuminate\Http\RedirectResponse
    {
        $order = $this->acq->getOrder($id);
        if (!$order) {
            abort(404);
        }

        $line = $this->acq->getLine($lineId);
        if (!$line || (int) $line->order_id !== $id) {
            abort(404);
        }

        $this->acq->removeLine($lineId);
        $this->acq->recalculateOrderTotals($id);

        return back()->with('success', 'Line removed.');
    }

    /**
     * POST /acquisition/order/{id}/receive-all  →  mark all pending lines received
     */
    public function receiveAll(int $id): \Illuminate\Http\RedirectResponse
    {
        $order = $this->acq->getOrder($id);
        if (!$order) {
            abort(404);
        }

        $count = $this->acq->receiveAllLines($id);

        return back()->with(
            $count > 0 ? 'success' : 'info',
            $count > 0
                ? "{$count} line(s) marked as received."
                : 'No pending lines to receive.'
        );
    }

    /**
     * POST /acquisition/order/{id}/receive-all  →  AJAX version
     */
    public function receiveAllAjax(int $id): JsonResponse
    {
        $order = $this->acq->getOrder($id);
        if (!$order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        $count = $this->acq->receiveAllLines($id);

        return response()->json([
            'success'     => true,
            'received'    => $count,
            'order'       => $this->acq->getOrder($id),
            'lines'       => $this->acq->getOrderLines($id),
        ]);
    }

    // ── Budget CRUD ─────────────────────────────────────────────────────────

    public function budgets(): View
    {
        return view('ahg-library::acquisition.budgets', [
            'budgets' => collect($this->acq->listBudgets()),
        ]);
    }

    public function budgetCreate(): View
    {
        return view('ahg-library::acquisition.budget-create', [
            'budget' => null,
        ]);
    }

    public function budgetStore(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'fiscal_year'  => ['required', 'integer', 'min:2000', 'max:2100'],
            'allocated'    => ['required', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ]);

        $id = $this->acq->createBudget($validated);
        if ($id === 0) {
            return back()->withInput()->with('error', 'Failed to create budget.');
        }

        return redirect()
            ->route('library.acquisition-budget', $id)
            ->with('success', 'Budget created.');
    }

    public function budgetShow(int $id): View
    {
        $budget = $this->acq->getBudget($id);
        if (!$budget) {
            abort(404);
        }

        return view('ahg-library::acquisition.budget-show', [
            'budget' => $budget,
        ]);
    }

    public function budgetEdit(int $id): View
    {
        $budget = $this->acq->getBudget($id);
        if (!$budget) {
            abort(404);
        }

        return view('ahg-library::acquisition.budget-edit', [
            'budget' => $budget,
        ]);
    }

    public function budgetUpdate(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $budget = $this->acq->getBudget($id);
        if (!$budget) {
            abort(404);
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'allocated'   => ['required', 'numeric', 'min:0'],
            'notes'       => ['nullable', 'string', 'max:2000'],
        ]);

        $this->acq->updateBudget($id, $validated);

        return redirect()
            ->route('library.acquisition-budget', $id)
            ->with('success', 'Budget updated.');
    }

    public function budgetDestroy(int $id): \Illuminate\Http\RedirectResponse
    {
        $budget = $this->acq->getBudget($id);
        if (!$budget) {
            abort(404);
        }

        if ($budget->spent > 0) {
            return back()->with('error', 'Cannot delete a budget with recorded expenditure.');
        }

        $this->acq->deleteBudget($id);

        return redirect()
            ->route('library.acquisition-budgets')
            ->with('success', 'Budget deleted.');
    }

    // ── Status transitions ───────────────────────────────────────────────────

    /**
     * POST /acquisition/order/{id}/status  →  change status
     */
    public function transition(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $order = $this->acq->getOrder($id);
        if (!$order) {
            abort(404);
        }

        $newStatus = $request->input('status');

        $valid = ['draft', 'submitted', 'approved', 'ordered', 'partial', 'received', 'cancelled'];
        if (!in_array($newStatus, $valid)) {
            return back()->with('error', "Invalid status '{$newStatus}'.");
        }

        $this->acq->transitionOrder($id, $newStatus);

        return back()->with('success', "Order status updated to '{$newStatus}'.");
    }

    public function transitionAjax(Request $request, int $id): JsonResponse
    {
        $order = $this->acq->getOrder($id);
        if (!$order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        $newStatus = $request->input('status');
        $valid = ['draft', 'submitted', 'approved', 'ordered', 'partial', 'received', 'cancelled'];
        if (!in_array($newStatus, $valid)) {
            return response()->json(['error' => "Invalid status '{$newStatus}'."], 422);
        }

        $this->acq->transitionOrder($id, $newStatus);

        return response()->json([
            'success' => true,
            'order'   => $this->acq->getOrder($id),
        ]);
    }
}