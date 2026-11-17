<?php

/**
 * HeritageAccountingController - Controller for Heratio
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



namespace AhgHeritageManage\Controllers;

use AhgCore\Support\DropdownOptions;
use AhgHeritageManage\Services\HeritageAccountingService;
use AhgHeritageManage\Services\HeritageValuationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HeritageAccountingController extends Controller
{
    public function __construct(protected HeritageAccountingService $accounting)
    {
    }

    /**
     * Dashboard: headline figures, assets by class, the active standards and
     * the ten most recently added assets, optionally scoped to a repository
     * and/or an accounting standard.
     */
    public function dashboard(Request $request)
    {
        $repositoryId = $request->integer('repository_id') ?: null;
        $standardId = $request->integer('standard_id') ?: null;

        $stats = $this->accounting->getDashboardStats($repositoryId, $standardId);
        $standards = $this->accounting->getAccountingStandards();
        $recentAssets = $this->accounting->browse(array_filter([
            'repository_id' => $repositoryId,
            'standard_id' => $standardId,
        ]), 10)['items'];
        $statusLabels = DropdownOptions::resolve('recognition_status', self::STATUS_FALLBACK);
        $currency = $this->accounting->currency();

        return view('ahg-heritage-manage::heritage-accounting.dashboard', compact(
            'stats', 'standards', 'recentAssets', 'statusLabels', 'currency', 'standardId'
        ));
    }

    private const STATUS_FALLBACK = [
        'recognised' => 'Recognised', 'not_recognised' => 'Not Recognised',
        'pending' => 'Pending', 'derecognised' => 'Derecognised',
    ];

    /** Register with filters: standard, class, status, repository, record, search. */
    public function browse(Request $request)
    {
        $filters = array_filter([
            'standard_id' => $request->query('standard_id'),
            'class_id' => $request->query('class_id'),
            'recognition_status' => $request->query('status'),
            'repository_id' => $request->query('repository_id'),
            'information_object_id' => $request->query('io_id'),
            'search' => trim((string) $request->query('sq', '')),
        ], fn ($v) => $v !== null && $v !== '');

        $items = $this->accounting->available() ? $this->accounting->paginate($filters, 25) : collect();
        $columns = [__('Asset'), __('Class'), __('Standard'), __('Status'), __('Carrying value'), __('Recognised')];
        $standards = $this->accounting->getAccountingStandards();
        $classes = $this->accounting->getAssetClasses();
        $statusOptions = DropdownOptions::resolve('recognition_status', self::STATUS_FALLBACK);

        return view('ahg-heritage-manage::heritage-accounting.browse', compact(
            'items', 'columns', 'standards', 'classes', 'statusOptions', 'filters'
        ));
    }

    public function add(Request $request)
    {
        $io = null;
        if ($request->filled('io_id')) {
            $culture = app()->getLocale();
            $io = DB::table('information_object as io')
                ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
                })
                ->where('io.id', $request->input('io_id'))
                ->select('io.id', 'i18n.title')
                ->first();
        }

        [$standards, $classes] = $this->accountingFormLookups();

        return view('ahg-heritage-manage::heritage-accounting.add', compact('io', 'standards', 'classes'));
    }
    public function store(Request $request)
    {
        if (! Schema::hasTable('heritage_asset')) {
            return back()->with('error', __('Heritage accounting is not installed.'))->withInput();
        }
        $request->validate([
            'recognition_status'     => ['nullable', \Illuminate\Validation\Rule::in(array_keys(DropdownOptions::resolve('recognition_status', self::STATUS_FALLBACK)))],
            'information_object_id'  => 'nullable|integer',
            'accounting_standard_id' => 'nullable|integer',
            'asset_class_id'         => 'nullable|integer',
        ]);

        $data = $this->collectAssetFields($request);
        if ($error = $this->accounting->validateCapitalisation($data)) {
            return back()->withInput()->with('error', $error);
        }
        $data['created_at'] = now();
        $data['updated_at'] = now();
        if (Schema::hasColumn('heritage_asset', 'created_by') && auth()->id()) {
            $data['created_by'] = auth()->id();
        }

        $id = DB::table('heritage_asset')->insertGetId($data);
        $this->accounting->logTransaction($id, (object) $data, 'create', null, $data, auth()->id());

        return redirect()->route('heritage.accounting.view', $id)
            ->with('success', __('Heritage asset created.'));
    }

    /**
     * Inline status change from the browse grid (AJAX). Whitelisted to the same
     * recognition_status values the add form offers.
     */
    public function updateStatus(Request $request, int $id)
    {
        $allowed = array_keys(DropdownOptions::resolve('recognition_status', self::STATUS_FALLBACK));
        $status = (string) $request->input('recognition_status');
        if (! in_array($status, $allowed, true)) {
            return response()->json(['ok' => false, 'error' => 'Invalid status.'], 422);
        }
        if (! Schema::hasTable('heritage_asset')
            || ! DB::table('heritage_asset')->where('id', $id)->exists()) {
            return response()->json(['ok' => false, 'error' => 'Asset not found.'], 404);
        }

        $update = ['recognition_status' => $status, 'updated_at' => now()];
        // Recognising an asset stamps the recognition date if not already set;
        // moving off "recognised" leaves history intact.
        if ($status === 'recognised') {
            $current = DB::table('heritage_asset')->where('id', $id)->value('recognition_date');
            if (empty($current)) {
                $update['recognition_date'] = now()->toDateString();
            }
        }
        if (Schema::hasColumn('heritage_asset', 'updated_by') && auth()->id()) {
            $update['updated_by'] = auth()->id();
        }
        DB::table('heritage_asset')->where('id', $id)->update($update);

        $row = DB::table('heritage_asset')->where('id', $id)->first(['recognition_status', 'recognition_date']);

        return response()->json([
            'ok' => true,
            'recognition_status' => $row->recognition_status,
            'recognition_date' => $row->recognition_date,
            'label' => ucwords(str_replace('_', ' ', (string) $row->recognition_status)),
        ]);
    }
    public function edit(int $id)
    {
        $asset = null;
        if (Schema::hasTable('heritage_asset')) {
            $asset = DB::table('heritage_asset')->where('id', $id)->first();
        }
        if (! $asset) {
            abort(404);
        }
        [$standards, $classes] = $this->accountingFormLookups($asset->accounting_standard_id ? (int) $asset->accounting_standard_id : null);
        $io = $this->fetchLinkedIo($asset->information_object_id ?: ($asset->object_id ?? null));

        return view('ahg-heritage-manage::heritage-accounting.edit', [
            'asset'      => $asset,
            'standards'  => $standards,
            'classes'    => $classes,
            'io'         => $io,
            'formAction' => route('heritage.accounting.update', $id),
        ]);
    }

    public function update(Request $request, int $id)
    {
        if (! Schema::hasTable('heritage_asset') || ! DB::table('heritage_asset')->where('id', $id)->exists()) {
            abort(404);
        }
        $request->validate([
            'recognition_status'     => ['nullable', \Illuminate\Validation\Rule::in(array_keys(DropdownOptions::resolve('recognition_status', self::STATUS_FALLBACK)))],
            'information_object_id'  => 'nullable|integer',
            'accounting_standard_id' => 'nullable|integer',
            'asset_class_id'         => 'nullable|integer',
        ]);

        $data = $this->collectAssetFields($request);
        $check = array_merge((array) DB::table('heritage_asset')->where('id', $id)->first(), $data);
        if ($error = $this->accounting->validateCapitalisation($check)) {
            return back()->withInput()->with('error', $error);
        }
        $data['updated_at'] = now();
        if (Schema::hasColumn('heritage_asset', 'updated_by') && auth()->id()) {
            $data['updated_by'] = auth()->id();
        }
        DB::table('heritage_asset')->where('id', $id)->update($data);
        $this->accounting->logTransaction($id, (object) $check, 'update', null, $data, auth()->id());

        return redirect()->route('heritage.accounting.view', $id)
            ->with('success', __('Heritage asset updated.'));
    }

    /**
     * Standards + classes for the create/edit form selects. Only active
     * standards are offered (Settings decides which); an asset already on a
     * since-disabled standard keeps it in the list so an edit does not drop it.
     */
    private function accountingFormLookups(?int $keepStandardId = null): array
    {
        $standards = $this->accounting->getAccountingStandards();
        if ($keepStandardId && ! $standards->contains('id', $keepStandardId)) {
            $kept = DB::table('heritage_accounting_standard')->where('id', $keepStandardId)->first();
            if ($kept) {
                $standards->push($kept);
            }
        }

        return [$standards, $this->accounting->getAssetClasses()];
    }

    /** The linked archival record (title/identifier/slug) for display on the form/view. */
    private function fetchLinkedIo(?int $ioId): ?object
    {
        if (! $ioId) {
            return null;
        }
        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $ioId)
            ->select('io.id', 'io.identifier', 'i18n.title', 's.slug')
            ->first();
    }

    /**
     * Whitelist + type-cast the heritage_asset columns from the create/edit form.
     * Only fields present in the request are returned (so a partial form never
     * nulls untouched columns), and only columns that actually exist on this
     * install's table survive - a schema-safe write.
     */
    private function collectAssetFields(Request $request): array
    {
        $money = ['acquisition_cost', 'fair_value_at_acquisition', 'nominal_value', 'initial_carrying_amount', 'current_carrying_amount', 'last_valuation_amount', 'revaluation_surplus', 'residual_value', 'annual_depreciation', 'accumulated_depreciation', 'impairment_loss', 'recoverable_amount', 'derecognition_proceeds', 'gain_loss_on_derecognition', 'insurance_value'];
        $ints = ['information_object_id', 'accounting_standard_id', 'asset_class_id', 'useful_life_years'];
        $dates = ['recognition_date', 'acquisition_date', 'last_valuation_date', 'last_impairment_date', 'derecognition_date', 'insurance_expiry_date', 'last_condition_assessment'];
        $text = ['asset_sub_class', 'recognition_status', 'recognition_status_reason', 'measurement_basis', 'acquisition_method', 'donor_name', 'donor_restrictions', 'valuation_method', 'valuer_name', 'valuer_credentials', 'valuation_report_reference', 'revaluation_frequency', 'depreciation_policy', 'impairment_indicators', 'impairment_indicators_details', 'derecognition_reason', 'heritage_significance', 'significance_statement', 'restrictions_on_use', 'restrictions_on_disposal', 'conservation_requirements', 'insurance_policy_number', 'insurance_provider', 'current_location', 'storage_conditions', 'condition_rating', 'notes'];

        $out = [];
        foreach ($money as $c) {
            if ($request->has($c)) {
                $val = $request->input($c);
                $out[$c] = ($val === null || $val === '') ? null : (float) $val;
            }
        }
        foreach ($ints as $c) {
            if ($request->has($c)) {
                $val = $request->input($c);
                $out[$c] = ($val === null || $val === '') ? null : (int) $val;
            }
        }
        foreach ($dates as $c) {
            if ($request->has($c)) {
                $val = $request->input($c);
                $out[$c] = ($val === null || $val === '') ? null : $val;
            }
        }
        foreach ($text as $c) {
            if ($request->has($c)) {
                $val = $request->input($c);
                $out[$c] = ($val === null || $val === '') ? null : trim((string) $val);
            }
        }
        // Insurance-required checkbox: a hidden 0 companion in the form guarantees
        // presence, so this only fires when the insurance section was rendered.
        if ($request->has('insurance_required')) {
            $out['insurance_required'] = $request->boolean('insurance_required') ? 1 : 0;
        }

        return array_filter($out, fn ($k) => Schema::hasColumn('heritage_asset', $k), ARRAY_FILTER_USE_KEY);
    }
    /**
     * #1454 - the full GRAP heritage-asset accounting record (all recognition,
     * measurement, valuation, depreciation, impairment, derecognition,
     * restriction, insurance and location/condition fields), grouped by GRAP
     * lifecycle. This is the rich replacement for the thin Spectrum grap
     * dashboard, which now redirects here.
     */
    public function view(int $id)
    {
        $asset = null;
        if (Schema::hasTable('heritage_asset')) {
            $asset = DB::table('heritage_asset as ha')
                ->leftJoin('heritage_asset_class as ac', 'ac.id', '=', 'ha.asset_class_id')
                ->leftJoin('heritage_accounting_standard as st', 'st.id', '=', 'ha.accounting_standard_id')
                ->where('ha.id', $id)
                ->select('ha.*', 'ac.name as asset_class_name', 'st.code as standard_code', 'st.name as standard_name')
                ->first();
        }
        if (! $asset) {
            abort(404);
        }

        $io = $this->fetchLinkedIo($asset->information_object_id ?: ($asset->object_id ?? null));

        return view('ahg-heritage-manage::heritage-accounting.view', [
            'asset' => $asset,
            'io' => $io,
            'valuations' => $this->accounting->getValuationHistory($id),
            'impairments' => $this->accounting->getImpairmentAssessments($id),
            'movements' => $this->accounting->getMovements($id),
            'journals' => $this->accounting->getJournalEntries($id),
            'currency' => $this->accounting->currency(),
        ]);
    }

    /**
     * #1454 - resolve an information-object id to its heritage-asset record and
     * show the full GRAP record. When no accounting record exists yet, hand off
     * to the Add form pre-linked to the object. This is the target the legacy
     * /admin/spectrum/grap-dashboard?slug= surface redirects to.
     */
    public function viewByObject(int $id)
    {
        $asset = null;
        if (Schema::hasTable('heritage_asset')) {
            $asset = DB::table('heritage_asset')
                ->where(function ($q) use ($id) {
                    $q->where('information_object_id', $id)->orWhere('object_id', $id);
                })
                ->orderByDesc('id')
                ->first();
        }
        if ($asset) {
            return redirect()->route('heritage.accounting.view', $asset->id);
        }
        // No accounting record yet - land on the asset register (browse), where
        // an "Add asset for this record" button (pre-linked to the object) lets
        // the operator create one. Matches the requested dashboard/browse-then-add
        // flow rather than jumping straight into a blank form.
        if (\Illuminate\Support\Facades\Route::has('heritage.accounting.browse')) {
            return redirect()->route('heritage.accounting.browse')
                ->with('info', __('No heritage-asset accounting record exists yet for this record. Use "Add asset for this record" to create one.'))
                ->with('add_io_id', $id);
        }
        abort(404);
    }
    /**
     * #1460 - Add Valuation form. Was a stub returning a blank form with
     * formAction '#'; now resolves the asset, shows what the revaluation will
     * do to the carrying amount / reserve, and lists prior valuations.
     *
     * Without an id we can't value anything, so send the operator to the
     * register to pick an asset first.
     */
    public function addValuation(?int $id = null)
    {
        if (! Schema::hasTable('heritage_asset')) {
            return redirect()->route('heritage.accounting.dashboard')
                ->with('error', __('Heritage accounting is not installed.'));
        }
        if (! $id) {
            return redirect()->route('heritage.accounting.browse')
                ->with('info', __('Choose a heritage asset to value.'));
        }

        $asset = DB::table('heritage_asset')->where('id', $id)->first();
        if (! $asset) {
            abort(404);
        }

        $service = app(HeritageValuationService::class);

        return view('ahg-heritage-manage::heritage-accounting.add-valuation', [
            'asset'   => $asset,
            'io'      => $this->fetchLinkedIo($asset->information_object_id ?? ($asset->object_id ?? null)),
            'methods' => HeritageValuationService::METHODS,
            'history' => $service->history($id),
        ]);
    }

    /**
     * #1460 - persist a valuation. All the GRAP arithmetic (history row,
     * carrying amount, revaluation reserve, OCI/P&L split) lives in
     * HeritageValuationService so the Spectrum valuation bridge and this form
     * post identically.
     */
    public function storeValuation(Request $request, int $id)
    {
        $validated = $request->validate([
            'valuation_date'             => 'required|date',
            'new_value'                  => 'required|numeric|min:0',
            'valuation_method'           => 'nullable|string|max:51',
            'valuer_name'                => 'nullable|string|max:255',
            'valuer_credentials'         => 'nullable|string|max:255',
            'valuer_organization'        => 'nullable|string|max:255',
            'valuation_report_reference' => 'nullable|string|max:255',
            'notes'                      => 'nullable|string',
        ]);
        $validated['user_id'] = auth()->id();

        $result = app(HeritageValuationService::class)->record($id, $validated);

        if (($result['status'] ?? '') !== 'recorded') {
            return back()->withInput()->with('error', match ($result['status'] ?? '') {
                'no_asset'    => __('Heritage asset not found.'),
                'unavailable' => __('Heritage accounting is not installed.'),
                default       => __('The valuation could not be recorded.'),
            });
        }

        $change = $result['change'];
        $msg = $change == 0.0
            ? __('Valuation recorded - no change to the carrying amount.')
            : __(':dir of :amt recorded; carrying amount is now :new.', [
                'dir' => $change > 0 ? __('Revaluation increase') : __('Revaluation decrease'),
                'amt' => number_format(abs($change), 2),
                'new' => number_format($result['new_value'], 2),
            ]);

        return redirect()->route('heritage.accounting.view', $id)->with('success', $msg);
    }

    /** Resolve the asset for a sub-ledger form, or send the operator to pick one. */
    private function assetOrPick(?int $id, string $what)
    {
        if (! $this->accounting->available()) {
            return redirect()->route('heritage.accounting.dashboard')->with('error', __('Heritage accounting is not installed.'));
        }
        if (! $id) {
            return redirect()->route('heritage.accounting.browse')->with('info', __('Choose a heritage asset to :what.', ['what' => $what]));
        }

        return $this->accounting->getAsset($id) ?? abort(404);
    }

    /** Impairment assessment form (indicators, outcome, recoverable amount). */
    public function addImpairment(?int $id = null)
    {
        $asset = $this->assetOrPick($id, __('assess for impairment'));
        if (! is_object($asset) || $asset instanceof \Symfony\Component\HttpFoundation\Response) {
            return $asset;
        }

        return view('ahg-heritage-manage::heritage-accounting.add-impairment', [
            'asset' => $asset,
            'history' => $this->accounting->getImpairmentAssessments($asset->id),
            'currency' => $this->accounting->currency(),
            'formAction' => route('heritage.accounting.store-impairment', $asset->id),
        ]);
    }

    public function storeImpairment(Request $request, int $id)
    {
        $v = $request->validate([
            'assessment_date' => 'required|date',
            'assessor_name' => 'nullable|string|max:255',
            'physical_damage_details' => 'nullable|string|max:5000',
            'obsolescence_details' => 'nullable|string|max:5000',
            'change_in_use_details' => 'nullable|string|max:5000',
            'external_factors_details' => 'nullable|string|max:5000',
            'recoverable_amount' => 'nullable|numeric|min:0',
            'impairment_loss' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:10000',
        ]);
        foreach (['physical_damage', 'obsolescence', 'change_in_use', 'external_factors', 'impairment_identified'] as $flag) {
            $v[$flag] = $request->boolean($flag) ? 1 : 0;
        }
        foreach (['recoverable_amount', 'impairment_loss'] as $m) {
            $v[$m] = ($v[$m] ?? '') === '' ? null : (float) $v[$m];
        }

        try {
            $this->accounting->addImpairment($id, $v, auth()->id());
        } catch (\RuntimeException $e) {
            abort(404);
        }

        return redirect()->route('heritage.accounting.view', $id)->with('success', __('Impairment assessment recorded.'));
    }

    /** Journal entry form. */
    public function addJournal(?int $id = null)
    {
        $asset = $this->assetOrPick($id, __('post a journal entry for'));
        if (! is_object($asset) || $asset instanceof \Symfony\Component\HttpFoundation\Response) {
            return $asset;
        }

        return view('ahg-heritage-manage::heritage-accounting.add-journal', [
            'asset' => $asset,
            'journalTypes' => DropdownOptions::resolve('journal_type', [
                'recognition' => 'Recognition', 'revaluation' => 'Revaluation', 'depreciation' => 'Depreciation',
                'impairment' => 'Impairment', 'impairment_reversal' => 'Impairment Reversal',
                'derecognition' => 'Derecognition', 'adjustment' => 'Adjustment', 'transfer' => 'Transfer',
            ]),
            'history' => $this->accounting->getJournalEntries($asset->id),
            'formAction' => route('heritage.accounting.store-journal', $asset->id),
        ]);
    }

    public function storeJournal(Request $request, int $id)
    {
        $types = array_keys(DropdownOptions::resolve('journal_type', ['adjustment' => 'Adjustment']));
        $v = $request->validate([
            'journal_date' => 'required|date',
            'journal_type' => ['required', \Illuminate\Validation\Rule::in($types)],
            'journal_number' => 'nullable|string|max:50',
            'debit_account' => 'required|string|max:50',
            'debit_amount' => 'required|numeric|min:0',
            'credit_account' => 'required|string|max:50',
            'credit_amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:10000',
            'reference_document' => 'nullable|string|max:255',
            'fiscal_year' => 'nullable|integer|min:1900|max:2999',
            'fiscal_period' => 'nullable|integer|min:1|max:13',
        ]);
        if (round((float) $v['debit_amount'], 2) !== round((float) $v['credit_amount'], 2)) {
            return back()->withInput()->withErrors(['credit_amount' => __('Debit and credit amounts must balance.')]);
        }

        try {
            $this->accounting->addJournal($id, $v, auth()->id());
        } catch (\RuntimeException $e) {
            abort(404);
        }

        return redirect()->route('heritage.accounting.view', $id)->with('success', __('Journal entry recorded.'));
    }

    /** Movement register form (loans, transfers, exhibitions, storage changes). */
    public function addMovement(?int $id = null)
    {
        $asset = $this->assetOrPick($id, __('record a movement for'));
        if (! is_object($asset) || $asset instanceof \Symfony\Component\HttpFoundation\Response) {
            return $asset;
        }

        return view('ahg-heritage-manage::heritage-accounting.add-movement', [
            'asset' => $asset,
            'movementTypes' => DropdownOptions::resolve('movement_type', [
                'loan_out' => 'Loan Out', 'loan_return' => 'Loan Return', 'transfer' => 'Transfer',
                'exhibition' => 'Exhibition', 'conservation' => 'Conservation',
                'storage_change' => 'Storage Change', 'other' => 'Other',
            ]),
            'conditions' => DropdownOptions::resolve('condition_on_departure', [
                'excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor',
            ]),
            'history' => $this->accounting->getMovements($asset->id),
            'currency' => $this->accounting->currency(),
            'formAction' => route('heritage.accounting.store-movement', $asset->id),
        ]);
    }

    public function storeMovement(Request $request, int $id)
    {
        $types = array_keys(DropdownOptions::resolve('movement_type', ['other' => 'Other']));
        $conditions = array_keys(DropdownOptions::resolve('condition_on_departure', ['good' => 'Good']));
        $v = $request->validate([
            'movement_date' => 'required|date',
            'movement_type' => ['required', \Illuminate\Validation\Rule::in($types)],
            'from_location' => 'nullable|string|max:255',
            'to_location' => 'nullable|string|max:255',
            'reason' => 'nullable|string|max:10000',
            'authorized_by' => 'nullable|string|max:255',
            'authorization_date' => 'nullable|date',
            'expected_return_date' => 'nullable|date|after_or_equal:movement_date',
            'condition_on_departure' => ['nullable', \Illuminate\Validation\Rule::in($conditions)],
            'insurance_value' => 'nullable|numeric|min:0',
        ]);
        $v['insurance_confirmed'] = $request->boolean('insurance_confirmed') ? 1 : 0;
        foreach (['authorization_date', 'expected_return_date', 'condition_on_departure', 'insurance_value'] as $k) {
            $v[$k] = ($v[$k] ?? '') === '' ? null : $v[$k];
        }

        try {
            $this->accounting->addMovement($id, $v, auth()->id());
        } catch (\RuntimeException $e) {
            abort(404);
        }

        return redirect()->route('heritage.accounting.view', $id)->with('success', __('Movement recorded.'));
    }

    /** Settings: choose which accounting standards are available for use. */
    public function settings()
    {
        $standards = $this->accounting->getAccountingStandards(false);

        return view('ahg-heritage-manage::heritage-accounting.settings', compact('standards'));
    }

    public function saveSettings(Request $request)
    {
        $v = $request->validate([
            'enabled' => 'array',
            'enabled.*' => 'integer|exists:heritage_accounting_standard,id',
        ]);
        if (empty($v['enabled'])) {
            return back()->with('error', __('At least one accounting standard should be enabled.'));
        }
        $this->accounting->setActiveStandards($v['enabled']);

        return redirect()->route('heritage.accounting.settings')->with('success', __('Settings saved successfully.'));
    }
}
