<?php

/**
 * LibraryStocktakeService - scan a shelf range, reconcile it against the
 * catalogue and open loans, sign off the variance, and post missing copies to
 * the heritage asset register as impairments (#1474 item 5).
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

namespace AhgLibrary\Services;

use AhgCore\Support\DropdownOptions;
use AhgHeritageManage\Services\HeritageAccountingService;
use AhgHeritageManage\Services\OciMovementService;
use AhgLibrary\Exceptions\StocktakeException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibraryStocktakeService
{
    public const STATUS_OPEN = 'open';
    public const STATUS_RECONCILED = 'reconciled';
    public const STATUS_SIGNED_OFF = 'signed_off';

    public const FOUND = 'found';
    public const MISSING = 'missing';
    public const MISFILED = 'misfiled';
    public const UNEXPECTED = 'unexpected';
    public const ON_LOAN = 'on_loan';
    public const ELSEWHERE = 'elsewhere';

    /** Copy status set when a loss is posted; seeded as not expected on the shelf. */
    public const COPY_STATUS_MISSING = 'missing';

    /** Dropdown Manager taxonomies this feature reads, with their seed values. */
    public static function dropdownSeed(): array
    {
        return [
            'library_stocktake_status' => ['label' => 'Stocktake Status', 'options' => [
                'open' => 'Counting', 'reconciled' => 'Reconciled', 'signed_off' => 'Signed off',
            ]],
            'library_stocktake_class' => ['label' => 'Stocktake Classification', 'options' => [
                'found' => 'Found', 'missing' => 'Missing', 'misfiled' => 'Misfiled',
                'unexpected' => 'Unexpected', 'on_loan' => 'On loan', 'elsewhere' => 'Accounted for elsewhere',
            ]],
            'library_stocktake_reason' => ['label' => 'Stocktake Reason', 'options' => [
                'on_home_shelf' => 'On its home shelf',
                'no_home_shelf' => 'Seen, but the copy has no home shelf recorded',
                'wrong_shelf' => 'Seen on a different shelf',
                'outside_range' => 'Seen here, but its home shelf is outside this count',
                'not_scanned' => 'Not seen during the count',
                'status_out_no_loan' => 'Marked checked out, but no open loan exists',
                'open_loan' => 'On an open loan',
                'open_loan_on_shelf' => 'Seen on the shelf, but a loan is still open',
                'returned_during_count' => 'Returned after the count started',
                'held_status' => 'Copy status places it off the shelf',
                'unknown_barcode' => 'Barcode is not in the catalogue',
                'status_excluded' => 'Catalogue says this copy is no longer held',
                'other_branch' => 'Belongs to another branch',
                'no_branch' => 'Copy has no branch recorded',
                'no_barcode' => 'Copy has no barcode, so it cannot be counted - check by hand',
            ]],
            'library_stocktake_excluded_status' => ['label' => 'Copy Statuses Not Expected On The Shelf', 'options' => [
                'withdrawn' => 'Withdrawn', 'lost' => 'Lost', 'missing' => 'Missing',
                'disposed' => 'Disposed', 'written_off' => 'Written off',
            ]],
        ];
    }

    /** Code => label for one of this feature's taxonomies. */
    public function labels(string $taxonomy): array
    {
        return DropdownOptions::resolve($taxonomy, self::dropdownSeed()[$taxonomy]['options'] ?? []);
    }

    public function create(array $data, ?int $userId): int
    {
        return (int) DB::table('library_stocktake')->insertGetId([
            'name' => $data['name'],
            'branch_id' => $data['branch_id'] ?? null,
            'shelf_from' => $this->clean($data['shelf_from'] ?? null),
            'shelf_to' => $this->clean($data['shelf_to'] ?? null),
            'status' => self::STATUS_OPEN,
            'created_by' => $userId,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Record scanned barcodes against a shelf. A repeat scan moves the barcode
     * to the latest shelf. Any scan reopens the count, so a variance can never
     * be signed off without reflecting the scans made after it was calculated.
     *
     * @return array{recorded:int, repeated:int, rejected:int}
     */
    public function recordScans(int $stocktakeId, ?string $shelf, array $barcodes, ?int $userId): array
    {
        $shelf = $this->clean($shelf);
        $out = ['recorded' => 0, 'repeated' => 0, 'rejected' => 0];

        DB::transaction(function () use ($stocktakeId, $shelf, $barcodes, $userId, &$out) {
            $this->lockUnsigned($stocktakeId);

            foreach ($barcodes as $raw) {
                $barcode = trim((string) $raw);
                if ($barcode === '') {
                    continue;
                }
                if (mb_strlen($barcode) > 50) {
                    $out['rejected']++;
                    continue;
                }
                $existing = DB::table('library_stocktake_scan')
                    ->where('stocktake_id', $stocktakeId)->where('barcode', $barcode)->first();
                if ($existing) {
                    DB::table('library_stocktake_scan')->where('id', $existing->id)->update([
                        'scanned_shelf' => $shelf,
                        'scan_count' => (int) $existing->scan_count + 1,
                        'scanned_by' => $userId,
                        'scanned_at' => now(),
                    ]);
                    $out['repeated']++;
                } else {
                    DB::table('library_stocktake_scan')->insert([
                        'stocktake_id' => $stocktakeId,
                        'barcode' => $barcode,
                        'scanned_shelf' => $shelf,
                        'scanned_by' => $userId,
                        'scanned_at' => now(),
                    ]);
                    $out['recorded']++;
                }
            }

            if ($out['recorded'] + $out['repeated'] > 0) {
                DB::table('library_stocktake')->where('id', $stocktakeId)->update([
                    'status' => self::STATUS_OPEN,
                    'updated_at' => now(),
                ]);
            }
        });

        return $out;
    }

    /**
     * Rebuild the variance lines from the scans and the live catalogue.
     *
     * @return array<string,int> Line count per classification.
     */
    public function reconcile(int $stocktakeId): array
    {
        return DB::transaction(function () use ($stocktakeId) {
            $stocktake = $this->lockUnsigned($stocktakeId);
            $branchId = $stocktake->branch_id !== null ? (int) $stocktake->branch_id : null;
            $excluded = array_keys($this->labels('library_stocktake_excluded_status'));

            $scans = [];
            foreach (DB::table('library_stocktake_scan')->where('stocktake_id', $stocktakeId)->get() as $scan) {
                $scans[mb_strtolower($scan->barcode)] = $scan;
            }

            $openLoans = DB::table('library_checkout')->whereNull('return_date')
                ->pluck('checkout_date', 'copy_id')->all();
            $returnedSince = array_flip(DB::table('library_checkout')
                ->where('return_date', '>=', $stocktake->started_at)
                ->pluck('copy_id')->map(fn ($id) => (int) $id)->all());

            DB::table('library_stocktake_line')->where('stocktake_id', $stocktakeId)->delete();

            $buffer = [];
            $seen = [];
            $query = $this->copyQuery();
            if ($branchId !== null) {
                $query->where('cp.branch_id', $branchId);
            }
            if ($excluded) {
                $query->whereNotIn('cp.status', $excluded);
            }

            $query->chunkById(1000, function ($copies) use (&$buffer, &$seen, $scans, $openLoans, $returnedSince, $stocktake) {
                foreach ($copies as $copy) {
                    if (! $this->inShelfRange($copy->home_shelf, $stocktake)) {
                        continue;
                    }
                    $key = mb_strtolower((string) $copy->barcode);
                    $scan = $key !== '' ? ($scans[$key] ?? null) : null;
                    if ($scan) {
                        $seen[$key] = true;
                    }
                    [$class, $reason] = $this->classifyExpected($copy, $scan, $openLoans, $returnedSince);
                    $buffer[] = $this->line($stocktake->id, $copy, $copy->barcode, $scan, $class, $reason);
                }
                $this->flush($buffer);
            }, 'cp.id', 'id');

            $remaining = array_diff_key($scans, $seen);
            foreach (array_chunk($remaining, 500, true) as $chunk) {
                $found = [];
                $barcodes = array_map(fn ($s) => $s->barcode, array_values($chunk));
                foreach ($this->copyQuery()->whereIn('cp.barcode', $barcodes)->get() as $copy) {
                    $found[mb_strtolower((string) $copy->barcode)] = $copy;
                }
                foreach ($chunk as $key => $scan) {
                    $copy = $found[$key] ?? null;
                    [$class, $reason] = $this->classifyStray($copy, $scan, $branchId, $excluded, $openLoans);
                    $buffer[] = $this->line($stocktake->id, $copy, $scan->barcode, $scan, $class, $reason);
                }
                $this->flush($buffer);
            }

            DB::table('library_stocktake')->where('id', $stocktakeId)->update([
                'status' => self::STATUS_RECONCILED,
                'reconciled_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->counts($stocktakeId);
        });
    }

    /**
     * Freeze the variance. The person who started the count cannot sign it off.
     * Missing copies are matched to the register here, so the amounts a finance
     * officer reviews are the ones as at sign-off.
     */
    public function signOff(int $stocktakeId, int $userId, ?string $note): void
    {
        DB::transaction(function () use ($stocktakeId, $userId, $note) {
            $stocktake = DB::table('library_stocktake')->where('id', $stocktakeId)->lockForUpdate()->first();
            if (! $stocktake) {
                throw new StocktakeException(__('Stocktake not found.'));
            }
            if ($stocktake->status !== self::STATUS_RECONCILED) {
                throw new StocktakeException(__('Reconcile the latest scans before signing off.'));
            }
            if ((int) $stocktake->created_by === $userId) {
                throw new StocktakeException(__('The person who started a stocktake cannot sign it off.'));
            }

            if ($this->heritageAvailable()) {
                $this->matchMissingToRegister($stocktakeId);
            }

            DB::table('library_stocktake')->where('id', $stocktakeId)->update([
                'status' => self::STATUS_SIGNED_OFF,
                'signed_off_by' => $userId,
                'signed_off_at' => now(),
                'sign_off_note' => $this->clean($note),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Post one missing copy as an impairment. The OCI ledger takes the
     * surplus-then-P&L split; the register's carrying amount, cumulative loss
     * and assessment history follow in the same transaction, because neither
     * existing path updates the other. The currency is the institution's
     * configured one, and each copy can be posted once across all stocktakes.
     *
     * @return int[] OCI movement ids.
     */
    public function postImpairment(int $lineId, float $amount, string $date, ?string $note, int $userId): array
    {
        if (! $this->heritageAvailable()) {
            throw new StocktakeException(__('The heritage asset register is not available on this installation.'));
        }

        return DB::transaction(function () use ($lineId, $amount, $date, $note, $userId) {
            $line = DB::table('library_stocktake_line')->where('id', $lineId)->lockForUpdate()->first();
            if (! $line || $line->classification !== self::MISSING) {
                throw new StocktakeException(__('Only a missing copy can be posted.'));
            }
            if ($line->posted_at !== null) {
                throw new StocktakeException(__('This copy has already been posted.'));
            }
            // Every read before the asset lock is a locking read, so the ledger sums
            // below cannot come from a snapshot older than a concurrent posting.
            $stocktake = DB::table('library_stocktake')->where('id', $line->stocktake_id)->lockForUpdate()->first();
            if (! $stocktake || $stocktake->status !== self::STATUS_SIGNED_OFF) {
                throw new StocktakeException(__('The stocktake must be signed off first.'));
            }
            if (! $line->heritage_asset_id) {
                throw new StocktakeException(__('This copy has no single recognised entry in the asset register.'));
            }

            if ($block = $this->postingBlock($line, $stocktake, true)) {
                throw new StocktakeException($block);
            }
            $asset = DB::table('heritage_asset')->where('id', $line->heritage_asset_id)->lockForUpdate()->first();
            $amount = round($amount, 2);
            $carrying = round((float) $asset->current_carrying_amount, 2);
            if ($amount <= 0 || $amount > $carrying) {
                throw new StocktakeException(__('The impairment must be more than zero and no more than the carrying amount of :amount.', [
                    'amount' => number_format($carrying, 2),
                ]));
            }

            $reason = 'Stocktake #' . $stocktake->id . ': copy ' . ($line->barcode ?? $line->copy_id) . ' not located'
                . ($note ? ' - ' . $note : '');

            // addImpairment() posts the reserve-first split to the ledger and lowers
            // the asset's reserve, so it is the only posting made here.
            $lastMovement = (int) DB::table('ahg_heritage_oci_movement')
                ->where('heritage_asset_id', $asset->id)->max('id');

            app(HeritageAccountingService::class)->addImpairment((int) $asset->id, [
                'assessment_date' => $date,
                'impairment_identified' => 1,
                'recoverable_amount' => round($carrying - $amount, 2),
                'impairment_loss' => $amount,
                'notes' => $reason,
            ], $userId);

            $ids = DB::table('ahg_heritage_oci_movement')
                ->where('heritage_asset_id', $asset->id)->where('id', '>', $lastMovement)
                ->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

            // The primary key on copy_id is the backstop against a double posting.
            DB::table('library_stocktake_posting')->insert([
                'copy_id' => $line->copy_id,
                'line_id' => $lineId,
                'stocktake_id' => $line->stocktake_id,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);
            DB::table('library_copy')->where('id', $line->copy_id)->update([
                'status' => self::COPY_STATUS_MISSING,
                'updated_at' => now(),
            ]);

            DB::table('library_stocktake_line')->where('id', $lineId)->update([
                'impairment_amount' => $amount,
                'oci_movement_ids' => implode(',', $ids),
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            return $ids;
        });
    }

    /**
     * Why this missing line cannot be posted as things stand now, or null. The
     * classification was frozen at sign-off, so the copy, its loans and the
     * register are read live. Posting calls this with locks held.
     */
    public function postingBlock(object $line, object $stocktake, bool $lock = false): ?string
    {
        return $this->copyBlock($line, $stocktake, $lock) ?? $this->registerBlock($line, $lock);
    }

    private function copyBlock(object $line, object $stocktake, bool $lock): ?string
    {
        if (! $line->copy_id) {
            return __('This line is not linked to a catalogue copy.');
        }
        $copyQuery = DB::table('library_copy')->where('id', $line->copy_id);
        $copy = ($lock ? $copyQuery->lockForUpdate() : $copyQuery)->first();
        if (! $copy) {
            return __('This copy is no longer in the catalogue.');
        }

        $postedQuery = DB::table('library_stocktake_posting')->where('copy_id', $line->copy_id);
        if (($lock ? $postedQuery->lockForUpdate() : $postedQuery)->exists()) {
            return __('This copy has already been posted as a loss in a stocktake.');
        }
        if ($copy->status !== $line->copy_status) {
            return __('Needs review: the copy status changed to :status after the count.', ['status' => $copy->status]);
        }

        $since = $stocktake->reconciled_at;
        $loanQuery = DB::table('library_checkout')->where('copy_id', $line->copy_id)
            ->where(fn ($q) => $q->whereNull('return_date')
                ->orWhere('checkout_date', '>=', $since)
                ->orWhere('return_date', '>=', $since));
        if (($lock ? $loanQuery->lockForUpdate() : $loanQuery)->exists()) {
            return __('Needs review: this copy has been on loan or returned since the count.');
        }

        return null;
    }

    /** The register, the OCI ledger and the currency must agree before a split is posted. */
    private function registerBlock(object $line, bool $lock): ?string
    {
        $assetQuery = DB::table('heritage_asset')->where('id', $line->heritage_asset_id);
        $asset = ($lock ? $assetQuery->lockForUpdate() : $assetQuery)->first();
        if (! $asset || $asset->derecognition_date !== null) {
            return __('The asset is no longer on the register.');
        }

        $currency = $this->ledgerCurrency();
        if ($currency === null) {
            return __('Set the institution currency in the heritage accounting settings before posting.');
        }
        $foreign = DB::table('ahg_heritage_oci_movement')->where('heritage_asset_id', $asset->id)
            ->whereRaw('UPPER(currency) <> ?', [$currency])->exists();
        if ($foreign) {
            return __('This asset has ledger movements in a currency other than :currency. Resolve them before posting.', ['currency' => $currency]);
        }

        $ledger = round(app(OciMovementService::class)->getAccumulatedOciSurplus((int) $asset->id), 2);
        $register = round((float) $asset->revaluation_surplus, 2);
        if (abs($ledger - $register) >= 0.005) {
            return __('The revaluation surplus on the register (:register) differs from the OCI ledger (:ledger). Reconcile them before posting.', [
                'register' => number_format($register, 2),
                'ledger' => number_format($ledger, 2),
            ]);
        }

        return null;
    }

    /** The institution's configured currency, or null when it is not usable. */
    private function ledgerCurrency(): ?string
    {
        $currency = strtoupper(trim((string) app(HeritageAccountingService::class)->currency()));

        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
    }

    /** @return array<string,int> */
    public function counts(int $stocktakeId): array
    {
        return DB::table('library_stocktake_line')->where('stocktake_id', $stocktakeId)
            ->groupBy('classification')
            ->select('classification', DB::raw('COUNT(*) as c'))
            ->pluck('c', 'classification')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    /** Variance lines with the record title, for the screen and the export. */
    public function linesQuery(int $stocktakeId, ?string $classification = null)
    {
        $query = DB::table('library_stocktake_line as l')
            ->where('l.stocktake_id', $stocktakeId)
            ->select('l.*')
            ->selectSub(function ($sub) {
                $sub->from('information_object_i18n as i')
                    ->whereColumn('i.id', 'l.information_object_id')
                    ->orderByRaw('CASE WHEN i.culture = ? THEN 0 ELSE 1 END', [app()->getLocale() ?: 'en'])
                    ->limit(1)
                    ->select('i.title');
            }, 'title')
            ->orderByRaw('CASE WHEN l.classification = ? THEN 0 ELSE 1 END', [self::MISSING])
            ->orderBy('l.home_shelf')
            ->orderBy('l.id');

        if ($classification !== null && $classification !== '') {
            $query->where('l.classification', $classification);
        }

        return $query;
    }

    public function heritageAvailable(): bool
    {
        return class_exists(OciMovementService::class)
            && class_exists(HeritageAccountingService::class)
            && Schema::hasTable('heritage_asset')
            && Schema::hasTable('ahg_heritage_oci_movement')
            && Schema::hasTable('heritage_impairment_assessment');
    }

    /** Natural order, so shelf A10 sorts after A2. Bounds are inclusive. */
    public function inShelfRange(?string $shelf, object $stocktake): bool
    {
        $from = $stocktake->shelf_from;
        $to = $stocktake->shelf_to;
        if ($from === null && $to === null) {
            return true;
        }
        if ($shelf === null || trim($shelf) === '') {
            return false;
        }

        return ($from === null || strnatcasecmp($shelf, $from) >= 0)
            && ($to === null || strnatcasecmp($shelf, $to) <= 0);
    }

    private function classifyExpected(object $copy, ?object $scan, array $openLoans, array $returnedSince): array
    {
        $loanDate = $openLoans[$copy->id] ?? null;

        if ($scan) {
            // A loan issued after the scan is ordinary traffic during a count.
            if ($loanDate !== null && strtotime((string) $loanDate) <= strtotime((string) $scan->scanned_at)) {
                return [self::UNEXPECTED, 'open_loan_on_shelf'];
            }
            if ($copy->home_shelf === null || trim((string) $copy->home_shelf) === '') {
                return [self::FOUND, 'no_home_shelf'];
            }
            if ($scan->scanned_shelf !== null
                && mb_strtolower(trim((string) $scan->scanned_shelf)) !== mb_strtolower(trim((string) $copy->home_shelf))) {
                return [self::MISFILED, 'wrong_shelf'];
            }

            return [self::FOUND, 'on_home_shelf'];
        }

        if ($loanDate !== null) {
            return [self::ON_LOAN, 'open_loan'];
        }
        if (isset($returnedSince[(int) $copy->id])) {
            return [self::ELSEWHERE, 'returned_during_count'];
        }
        // No barcode means no scan could ever match, so absence proves nothing.
        if (trim((string) $copy->barcode) === ''
            && in_array($copy->status, [LibraryCirculationService::STATUS_AVAILABLE, LibraryCirculationService::STATUS_CHECKED_OUT], true)) {
            return [self::ELSEWHERE, 'no_barcode'];
        }
        if ($copy->status === LibraryCirculationService::STATUS_AVAILABLE) {
            return [self::MISSING, 'not_scanned'];
        }
        if ($copy->status === LibraryCirculationService::STATUS_CHECKED_OUT) {
            return [self::MISSING, 'status_out_no_loan'];
        }

        return [self::ELSEWHERE, 'held_status'];
    }

    /** A scan that matched no copy expected in this count. */
    private function classifyStray(?object $copy, object $scan, ?int $branchId, array $excluded, array $openLoans): array
    {
        if (! $copy) {
            return [self::UNEXPECTED, 'unknown_barcode'];
        }
        if (in_array($copy->status, $excluded, true)) {
            return [self::UNEXPECTED, 'status_excluded'];
        }
        if (isset($openLoans[$copy->id])
            && strtotime((string) $openLoans[$copy->id]) <= strtotime((string) $scan->scanned_at)) {
            return [self::UNEXPECTED, 'open_loan_on_shelf'];
        }
        if ($branchId !== null) {
            if ($copy->branch_id === null) {
                return [self::UNEXPECTED, 'no_branch'];
            }
            if ((int) $copy->branch_id !== $branchId) {
                return [self::UNEXPECTED, 'other_branch'];
            }
        }

        return [self::MISFILED, 'outside_range'];
    }

    /**
     * An asset is matched only when exactly one recognised, undisposed entry
     * covers the record. The suggested amount is the carrying amount shared
     * equally across the copies still held; it is a proposal for review.
     */
    private function matchMissingToRegister(int $stocktakeId): void
    {
        $excluded = array_keys($this->labels('library_stocktake_excluded_status'));

        DB::table('library_stocktake_line')
            ->where('stocktake_id', $stocktakeId)
            ->where('classification', self::MISSING)
            ->whereNotNull('information_object_id')
            ->chunkById(500, function ($lines) use ($excluded) {
                foreach ($lines as $line) {
                    $assets = DB::table('heritage_asset')
                        ->where(fn ($q) => $q->where('information_object_id', $line->information_object_id)
                            ->orWhere('object_id', $line->information_object_id))
                        ->where('recognition_status', 'recognised')
                        ->whereNull('derecognition_date')
                        ->limit(2)
                        ->get(['id', 'current_carrying_amount']);
                    if ($assets->count() !== 1) {
                        continue;
                    }
                    $asset = $assets->first();
                    $held = DB::table('library_copy')->where('library_item_id', $line->library_item_id)
                        ->when($excluded, fn ($q) => $q->whereNotIn('status', $excluded))
                        ->count();
                    $carrying = (float) $asset->current_carrying_amount;
                    DB::table('library_stocktake_line')->where('id', $line->id)->update([
                        'heritage_asset_id' => $asset->id,
                        'carrying_snapshot' => $carrying,
                        'proposed_amount' => round($carrying / max(1, $held), 2),
                    ]);
                }
            });
    }

    private function copyQuery()
    {
        return DB::table('library_copy as cp')
            ->join('library_item as li', 'li.id', '=', 'cp.library_item_id')
            ->select(
                'cp.id', 'cp.barcode', 'cp.status', 'cp.branch_id', 'cp.library_item_id',
                'li.information_object_id',
                DB::raw("COALESCE(NULLIF(cp.shelf_location, ''), li.shelf_location) as home_shelf")
            );
    }

    private function line(int $stocktakeId, ?object $copy, ?string $barcode, ?object $scan, string $class, string $reason): array
    {
        return [
            'stocktake_id' => $stocktakeId,
            'copy_id' => $copy->id ?? null,
            'barcode' => $barcode,
            'library_item_id' => $copy->library_item_id ?? null,
            'information_object_id' => $copy->information_object_id ?? null,
            'copy_status' => $copy->status ?? null,
            'home_shelf' => $copy->home_shelf ?? null,
            'scanned_shelf' => $scan->scanned_shelf ?? null,
            'classification' => $class,
            'reason_code' => $reason,
        ];
    }

    private function flush(array &$buffer): void
    {
        foreach (array_chunk($buffer, 200) as $rows) {
            DB::table('library_stocktake_line')->insert($rows);
        }
        $buffer = [];
    }

    private function lockUnsigned(int $stocktakeId): object
    {
        $stocktake = DB::table('library_stocktake')->where('id', $stocktakeId)->lockForUpdate()->first();
        if (! $stocktake) {
            throw new StocktakeException(__('Stocktake not found.'));
        }
        if ($stocktake->status === self::STATUS_SIGNED_OFF) {
            throw new StocktakeException(__('This stocktake is signed off and can no longer change.'));
        }

        return $stocktake;
    }

    private function clean(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
