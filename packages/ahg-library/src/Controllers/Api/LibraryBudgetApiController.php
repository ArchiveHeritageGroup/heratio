<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers\Api;

use AhgLibrary\Http\Resources\LibraryBudgetResource;
use AhgLibrary\Models\LibraryBudget;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * JSON:API CRUD for acquisitions budgets / fund lines (heratio#1100).
 */
class LibraryBudgetApiController extends Controller
{
    use AuthorizesLibraryApi;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeLibrary($request, 'read');
        [$page, $size] = $this->pageParams($request);

        $query = LibraryBudget::query()
            ->when($request->filled('fiscal_year'), fn ($q) => $q->where('fiscal_year', $request->string('fiscal_year')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('budget_code');

        $total = (clone $query)->count();
        $items = $query->forPage($page, $size)->get();

        return LibraryBudgetResource::collection($items)
            ->additional(['meta' => compact('total', 'page', 'size')]);
    }

    public function show(Request $request, LibraryBudget $budget): LibraryBudgetResource
    {
        $this->authorizeLibrary($request, 'read');

        return new LibraryBudgetResource($budget);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeLibrary($request, 'create');
        $data = validator($this->jsonApiAttributes($request), [
            'budget_code'      => ['required', 'string', 'max:50', Rule::unique('library_budget', 'budget_code')],
            'fund_name'        => ['required', 'string', 'max:255'],
            'fiscal_year'      => ['required', 'string', 'max:10'],
            'allocated_amount' => ['required', 'numeric', 'min:0'],
            'committed_amount' => ['nullable', 'numeric', 'min:0'],
            'spent_amount'     => ['nullable', 'numeric', 'min:0'],
            'currency'         => ['nullable', 'string', 'max:8'],
            'category'         => ['nullable', 'string', 'max:100'],
            'department'       => ['nullable', 'string', 'max:255'],
            'status'           => ['nullable', 'string', 'max:50'],
            'notes'            => ['nullable', 'string'],
        ])->validate();

        $budget = LibraryBudget::create($data);

        return (new LibraryBudgetResource($budget))->response()->setStatusCode(201);
    }

    public function update(Request $request, LibraryBudget $budget): LibraryBudgetResource
    {
        $this->authorizeLibrary($request, 'update');
        $data = validator($this->jsonApiAttributes($request), [
            'budget_code'      => ['sometimes', 'string', 'max:50', Rule::unique('library_budget', 'budget_code')->ignore($budget->id)],
            'fund_name'        => ['sometimes', 'string', 'max:255'],
            'fiscal_year'      => ['sometimes', 'string', 'max:10'],
            'allocated_amount' => ['sometimes', 'numeric', 'min:0'],
            'committed_amount' => ['nullable', 'numeric', 'min:0'],
            'spent_amount'     => ['nullable', 'numeric', 'min:0'],
            'currency'         => ['nullable', 'string', 'max:8'],
            'category'         => ['nullable', 'string', 'max:100'],
            'department'       => ['nullable', 'string', 'max:255'],
            'status'           => ['nullable', 'string', 'max:50'],
            'notes'            => ['nullable', 'string'],
        ])->validate();

        $budget->update($data);

        return new LibraryBudgetResource($budget->refresh());
    }

    public function destroy(Request $request, LibraryBudget $budget): JsonResponse
    {
        $this->authorizeLibrary($request, 'delete');

        if ($budget->orders()->exists()) {
            return response()->json([
                'errors' => [['status' => '409', 'title' => 'Conflict',
                    'detail' => 'Budget has linked orders; set its status to closed instead of deleting.']],
            ], 409);
        }

        $budget->delete();

        return response()->json(null, 204);
    }
}
