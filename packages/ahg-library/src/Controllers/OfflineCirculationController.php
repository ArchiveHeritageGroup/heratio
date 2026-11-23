<?php

/**
 * OfflineCirculationController - offline desk, sync and conflicts report
 * (#1474 item 4). Every route sits behind auth and acl:update.
 *
 * This file is part of Heratio, licensed under the GNU AGPL v3 or later.
 */

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\LibraryOfflineCirculationService as Offline;
use AhgLibrary\Support\LibraryBranch;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OfflineCirculationController extends Controller
{
    private const PAGE_SIZE = 1000;

    private const BATCH_MAX = 100;

    public function __construct(private Offline $offline)
    {
    }

    public function desk()
    {
        abort_unless(Offline::available(), 503, __('Offline circulation has not been installed on this system yet.'));

        return response()->view('ahg-library::circulation.offline.desk', [
            'branchId' => LibraryBranch::operatorBranchId(),
            'branchName' => LibraryBranch::name(LibraryBranch::operatorBranchId()),
        ]);
    }

    public function script()
    {
        return $this->javascript('offline-circulation.js');
    }

    public function worker()
    {
        $response = $this->javascript('offline-circulation-sw.js');
        $response->headers->set('Service-Worker-Allowed', '/library-manage/circulation/offline');

        return $response;
    }

    private function javascript(string $file)
    {
        return response()->file(dirname(__DIR__, 2) . '/resources/js/' . $file, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /** A fresh CSRF token: the page may have been opened from the offline cache. */
    public function session(Request $request)
    {
        return $this->private(['user_id' => (int) auth()->id(), 'token' => csrf_token()]);
    }

    public function package(Request $request)
    {
        abort_unless(Offline::available(), 503);

        $validated = $request->validate([
            'kind' => ['required', Rule::in(['rules', 'copies', 'patrons'])],
            'after' => 'nullable|integer|min:0',
        ]);
        $after = (int) ($validated['after'] ?? 0);
        $branch = LibraryBranch::operatorBranchId();

        if ($validated['kind'] === 'rules') {
            return $this->private($this->offline->rulesPackage() + ['branch_id' => $branch, 'generated_at' => now()->toIso8601String()]);
        }

        $rows = $validated['kind'] === 'copies'
            ? $this->offline->copiesPage($branch, $after, self::PAGE_SIZE)
            : $this->offline->patronsPage($branch, $after, self::PAGE_SIZE);

        return $this->private([
            'rows' => $rows,
            'next' => count($rows) === self::PAGE_SIZE ? (int) end($rows)->id : null,
        ]);
    }

    public function sync(Request $request)
    {
        abort_unless(Offline::available(), 503);

        $validated = $request->validate([
            'desk_clock' => 'required|date',
            'transactions' => 'required|array|min:1|max:' . self::BATCH_MAX,
            'transactions.*.uuid' => 'required|uuid',
            'transactions.*.device_id' => 'required|uuid',
            'transactions.*.seq' => 'required|integer|min:1|max:4294967295',
            'transactions.*.action' => ['required', Rule::in([Offline::ACTION_CHECKOUT, Offline::ACTION_RETURN])],
            'transactions.*.copy_barcode' => 'required|string|max:50',
            'transactions.*.patron_card' => 'nullable|required_if:transactions.*.action,' . Offline::ACTION_CHECKOUT . '|string|max:50',
            'transactions.*.expected_patron_card' => 'nullable|string|max:50',
            'transactions.*.branch_id' => 'nullable|integer|min:1',
            'transactions.*.occurred_at' => 'required|date',
            'transactions.*.operator_id' => 'required|integer|min:1',
        ]);

        return $this->private(['results' => $this->offline->replay(
            $validated['transactions'],
            (int) auth()->id(),
            LibraryBranch::operatorBranchId(),
            LibraryBranch::operatorLocked(),
            $validated['desk_clock']
        )]);
    }

    public function conflicts(Request $request)
    {
        abort_unless(Offline::available(), 503, __('Offline circulation has not been installed on this system yet.'));

        $statuses = $this->options('library_offline_status');
        $validated = $request->validate(['status' => ['nullable', 'string', Rule::in(array_keys($statuses))]]);
        $status = $request->has('status') ? ($validated['status'] ?? '') : Offline::CONFLICT;

        return view('ahg-library::circulation.offline.conflicts', [
            'rows' => $this->offline->reportQuery(LibraryBranch::operatorBranchId(), $status)->paginate(50)->withQueryString(),
            'status' => $status,
            'statuses' => $statuses,
            'conflictLabels' => $this->conflictLabels(),
            'actionLabels' => $this->options('library_offline_action'),
            'resolutions' => $this->options('library_offline_resolution'),
            'overridable' => Offline::OVERRIDABLE,
        ]);
    }

    public function resolve(Request $request, int $id)
    {
        abort_unless(Offline::available(), 503);

        $validated = $request->validate([
            'resolution' => ['required', Rule::in(array_keys($this->options('library_offline_resolution')))],
            'note' => 'nullable|required_if:resolution,' . Offline::RESOLVE_REJECT . ',' . Offline::RESOLVE_APPLY . '|string|max:1000',
        ]);

        $row = $this->offline->resolve($id, $validated['resolution'], $validated['note'] ?? null, (int) auth()->id(), LibraryBranch::operatorBranchId());
        abort_if($row === null, 404);

        $back = redirect()->route('library.circulation.offline.conflicts');

        return $row->status === Offline::CONFLICT
            ? $back->with('error', __('Still in conflict: :reason', ['reason' => $this->conflictLabels()[$row->conflict_code] ?? $row->conflict_code]))
            : $back->with('success', __('Transaction settled.'));
    }

    /**
     * ponytail: not_loanable has no ahg_dropdown row because this fix ships
     * without a data change. A row added later takes precedence over this label.
     */
    private function conflictLabels(): array
    {
        return $this->options('library_offline_conflict') + ['not_loanable' => 'Item is not for loan'];
    }

    private function options(string $taxonomy): array
    {
        return DB::table('ahg_dropdown')->where('taxonomy', $taxonomy)->where('is_active', 1)
            ->orderBy('sort_order')->pluck('label', 'code')->all();
    }

    /** Patron data and tokens must never land in a shared cache. */
    private function private(array $data)
    {
        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }
}
