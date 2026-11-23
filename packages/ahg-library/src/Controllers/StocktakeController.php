<?php

/**
 * StocktakeController - staff screens for physical stocktake (#1474 item 5).
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

use AhgCore\Services\AclService;
use AhgHeritageManage\Services\HeritageAccountingService;
use AhgLibrary\Exceptions\StocktakeException;
use AhgLibrary\Services\LibraryStocktakeService;
use AhgLibrary\Support\LibraryBranch;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class StocktakeController extends Controller
{
    private const MAX_BARCODES = 2000;

    public function __construct(protected LibraryStocktakeService $stocktakes)
    {
    }

    public function index()
    {
        $query = DB::table('library_stocktake')->orderByDesc('id');
        if ($locked = $this->lockedBranch()) {
            $query->where('branch_id', $locked);
        }

        return view('ahg-library::stocktake.index', [
            'stocktakes' => $query->paginate(25),
            'branchOptions' => LibraryBranch::options(),
            'statusLabels' => $this->stocktakes->labels('library_stocktake_status'),
            'currentBranch' => LibraryBranch::operatorBranchId(),
            'branchLocked' => $this->lockedBranch() !== null,
        ]);
    }

    public function store(Request $request)
    {
        $branches = array_keys(LibraryBranch::options());
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => ['nullable', 'integer', Rule::in($branches)],
            'shelf_from' => 'nullable|string|max:100',
            'shelf_to' => 'nullable|string|max:100',
        ]);

        if ($locked = $this->lockedBranch()) {
            $data['branch_id'] = $locked;
        }
        $from = trim((string) ($data['shelf_from'] ?? ''));
        $to = trim((string) ($data['shelf_to'] ?? ''));
        if ($from !== '' && $to !== '' && strnatcasecmp($from, $to) > 0) {
            return back()->withInput()->withErrors(['shelf_to' => __('The last shelf must come after the first shelf.')]);
        }

        $id = $this->stocktakes->create($data, $this->userId());

        return redirect()->route('library.stocktake.show', $id)->with('success', __('Stocktake started.'));
    }

    public function show(Request $request, int $id)
    {
        $stocktake = $this->find($id);
        $filter = (string) $request->query('classification', '');
        $classLabels = $this->stocktakes->labels('library_stocktake_class');
        if ($filter !== '' && ! isset($classLabels[$filter])) {
            $filter = '';
        }
        $isAdmin = AclService::isAdministrator();
        $heritageAvailable = $this->stocktakes->heritageAvailable();
        $lines = $this->stocktakes->linesQuery($id, $filter)->paginate(50)->withQueryString();

        $postingBlocks = [];
        if ($isAdmin && $heritageAvailable && $stocktake->status === LibraryStocktakeService::STATUS_SIGNED_OFF) {
            foreach ($lines as $line) {
                if ($line->classification === LibraryStocktakeService::MISSING && ! $line->posted_at && $line->heritage_asset_id) {
                    $postingBlocks[$line->id] = $this->stocktakes->postingBlock($line, $stocktake);
                }
            }
        }

        return view('ahg-library::stocktake.show', [
            'stocktake' => $stocktake,
            'lines' => $lines,
            'postingBlocks' => $postingBlocks,
            'counts' => $this->stocktakes->counts($id),
            'scanCount' => DB::table('library_stocktake_scan')->where('stocktake_id', $id)->count(),
            'filter' => $filter,
            'classLabels' => $classLabels,
            'reasonLabels' => $this->stocktakes->labels('library_stocktake_reason'),
            'statusLabels' => $this->stocktakes->labels('library_stocktake_status'),
            'branchName' => LibraryBranch::name($stocktake->branch_id !== null ? (int) $stocktake->branch_id : null),
            'isAdmin' => $isAdmin,
            'canSignOff' => $isAdmin && (int) $stocktake->created_by !== $this->userId(),
            'heritageAvailable' => $heritageAvailable,
            'currency' => $isAdmin && class_exists(HeritageAccountingService::class)
                ? app(HeritageAccountingService::class)->currency()
                : null,
        ]);
    }

    public function scan(Request $request, int $id)
    {
        $this->find($id);
        $data = $request->validate([
            'scanned_shelf' => 'nullable|string|max:100',
            'barcodes' => 'required|string|max:120000',
        ]);
        $barcodes = preg_split('/[\r\n]+/', $data['barcodes']);
        if (count($barcodes) > self::MAX_BARCODES) {
            return back()->withInput()->withErrors(['barcodes' => __('Send at most :max barcodes at a time.', ['max' => self::MAX_BARCODES])]);
        }

        return $this->attempt($id, function () use ($id, $data, $barcodes) {
            $r = $this->stocktakes->recordScans($id, $data['scanned_shelf'] ?? null, $barcodes, $this->userId());

            return __(':new new, :repeat repeated, :bad rejected as too long.', [
                'new' => $r['recorded'], 'repeat' => $r['repeated'], 'bad' => $r['rejected'],
            ]);
        });
    }

    public function reconcile(int $id)
    {
        $this->find($id);

        return $this->attempt($id, function () use ($id) {
            $this->stocktakes->reconcile($id);

            return __('Variance recalculated from the latest scans.');
        });
    }

    public function signOff(Request $request, int $id)
    {
        $this->find($id);
        $data = $request->validate([
            'confirm' => 'accepted',
            'sign_off_note' => 'nullable|string|max:5000',
        ]);

        return $this->attempt($id, function () use ($id, $data) {
            $this->stocktakes->signOff($id, (int) $this->userId(), $data['sign_off_note'] ?? null);

            return __('Variance report signed off.');
        });
    }

    public function postImpairment(Request $request, int $id, int $lineId)
    {
        $this->find($id);
        if (! DB::table('library_stocktake_line')->where('id', $lineId)->where('stocktake_id', $id)->exists()) {
            abort(404);
        }
        $data = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'valuation_date' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:2000',
        ]);

        return $this->attempt($id, function () use ($lineId, $data) {
            $ids = $this->stocktakes->postImpairment(
                $lineId,
                (float) $data['amount'],
                $data['valuation_date'],
                $data['note'] ?? null,
                (int) $this->userId()
            );

            return __('Impairment posted as :count ledger movement(s).', ['count' => count($ids)]);
        });
    }

    /** The variance report as CSV. Amounts appear for administrators only. */
    public function export(int $id)
    {
        $stocktake = $this->find($id);
        $isAdmin = AclService::isAdministrator();
        $classLabels = $this->stocktakes->labels('library_stocktake_class');
        $reasonLabels = $this->stocktakes->labels('library_stocktake_reason');
        $query = $this->stocktakes->linesQuery($id);

        return response()->streamDownload(function () use ($query, $stocktake, $isAdmin, $classLabels, $reasonLabels) {
            $out = fopen('php://output', 'w');
            $head = ['Stocktake', 'Status', 'Barcode', 'Title', 'Home shelf', 'Scanned shelf', 'Classification', 'Reason'];
            if ($isAdmin) {
                array_push($head, 'Asset', 'Carrying amount', 'Proposed', 'Posted', 'Posted at');
            }
            fputcsv($out, $head);
            foreach ($query->cursor() as $l) {
                $row = [
                    $stocktake->name, $stocktake->status, $l->barcode, $l->title, $l->home_shelf, $l->scanned_shelf,
                    $classLabels[$l->classification] ?? $l->classification,
                    $reasonLabels[$l->reason_code] ?? $l->reason_code,
                ];
                if ($isAdmin) {
                    array_push($row, $l->heritage_asset_id, $l->carrying_snapshot, $l->proposed_amount, $l->impairment_amount, $l->posted_at);
                }
                fputcsv($out, array_map([self::class, 'csvSafe'], $row));
            }
            fclose($out);
        }, 'stocktake-' . $id . '-variance.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stops a catalogue value being run as a formula when opened in a spreadsheet. */
    public static function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    private function attempt(int $id, callable $action)
    {
        try {
            $message = $action();
        } catch (StocktakeException $e) {
            return redirect()->route('library.stocktake.show', $id)->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Stocktake action failed', ['stocktake_id' => $id, 'exception' => $e]);

            return redirect()->route('library.stocktake.show', $id)
                ->with('error', __('The action could not be completed and nothing was saved. Please try again, or report it if it keeps happening.'));
        }

        return redirect()->route('library.stocktake.show', $id)->with('success', $message);
    }

    /** Staff an administrator has locked to a branch see only that branch. */
    private function find(int $id): object
    {
        $stocktake = DB::table('library_stocktake')->where('id', $id)->first();
        abort_unless($stocktake, 404);
        $locked = $this->lockedBranch();
        abort_if($locked !== null && (int) $stocktake->branch_id !== $locked, 403);

        return $stocktake;
    }

    private function lockedBranch(): ?int
    {
        return LibraryBranch::operatorLocked() ? LibraryBranch::operatorBranchId() : null;
    }

    private function userId(): ?int
    {
        $id = auth()->id();

        return $id !== null ? (int) $id : null;
    }
}
