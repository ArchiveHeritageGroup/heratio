<?php

/**
 * HeritageAdminController - Controller for Heratio
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
use AhgHeritageManage\Services\HeritageAdminService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Heritage accounting administration: regional packs (standard + compliance
 * rules), the active region, accounting standards and compliance rules.
 * Every state change is a POST/PUT/DELETE with the CSRF token.
 */
class HeritageAdminController extends Controller
{
    public function __construct(protected HeritageAdminService $admin)
    {
    }

    // =====================
    // Dashboard
    // =====================
    public function index()
    {
        $standards = $this->admin->standards();
        $regions = $this->admin->regions();
        $activeConfig = $this->admin->activeConfig();
        $stats = [
            'total_assets' => DB::table('heritage_asset')->count(),
            'by_standard' => $this->admin->assetsByStandard(),
        ];

        return view('ahg-heritage-manage::heritage-admin.index', compact('standards', 'regions', 'activeConfig', 'stats'));
    }

    // =====================
    // Regions
    // =====================
    public function regions()
    {
        return view('ahg-heritage-manage::heritage-admin.regions', [
            'regions' => $this->admin->regions(),
            'standardsByRegion' => $this->admin->standardsByRegion(),
            'rulesByRegion' => $this->admin->rulesByRegion(),
            'activeConfig' => $this->admin->activeConfig(),
        ]);
    }

    public function regionInfo(string $region)
    {
        $row = $this->admin->region($region) ?? abort(404);
        $standard = $this->admin->regionStandard($region);
        $active = $this->admin->activeConfig();

        return view('ahg-heritage-manage::heritage-admin.region-info', [
            'region' => $row,
            'standard' => $standard,
            'rules' => $standard ? $this->admin->rulesForStandard($standard->id) : collect(),
            'isActive' => $active && $active->region_code === $region,
        ]);
    }

    public function regionInstall(string $region)
    {
        $r = $this->admin->installRegion($region);
        if (! $r['success']) {
            return redirect()->route('heritage.hadmin.regions')->with('error', __('Installation failed: :e', ['e' => $r['error']]));
        }
        if (! empty($r['already_installed'])) {
            return redirect()->route('heritage.hadmin.regions')->with('info', $r['message']);
        }

        return redirect()->route('heritage.hadmin.regions')->with('success', __("Region ':code' installed successfully. Standard: :std. Compliance rules: :n", [
            'code' => $region, 'std' => $r['standard_code'] ?? '-', 'n' => $r['compliance_rules_installed'],
        ]));
    }

    public function regionUninstall(string $region)
    {
        $r = $this->admin->uninstallRegion($region);

        return redirect()->route('heritage.hadmin.regions')->with(
            $r['success'] ? 'success' : 'error',
            $r['success'] ? $r['message'] : __('Uninstall failed: :e', ['e' => $r['error']])
        );
    }

    public function regionSetActive(string $region)
    {
        $r = $this->admin->setActiveRegion($region);

        return redirect()->route('heritage.hadmin.regions')->with(
            $r['success'] ? 'success' : 'error',
            $r['success']
                ? __("Active region set to ':code' (:std)", ['code' => $region, 'std' => $r['standard_code'] ?? '-'])
                : __('Activation failed: :e', ['e' => $r['error']])
        );
    }

    // =====================
    // Standards
    // =====================
    public function standardList()
    {
        return view('ahg-heritage-manage::heritage-admin.standard-list', ['standards' => $this->admin->standards()]);
    }

    public function standardAdd()
    {
        return view('ahg-heritage-manage::heritage-admin.standard-add', [
            'standard' => null,
            'valuationMethods' => $this->valuationMethods(),
            'formAction' => route('heritage.hadmin.standard-store'),
        ]);
    }

    public function standardStore(Request $request)
    {
        $this->admin->saveStandard($this->validateStandard($request));

        return redirect()->route('heritage.hadmin.standard-list')->with('success', __('Accounting standard added successfully'));
    }

    public function standardEdit(int $id)
    {
        return view('ahg-heritage-manage::heritage-admin.standard-edit', [
            'standard' => $this->admin->standard($id) ?? abort(404),
            'valuationMethods' => $this->valuationMethods(),
            'formAction' => route('heritage.hadmin.standard-update', $id),
        ]);
    }

    public function standardUpdate(Request $request, int $id)
    {
        $this->admin->standard($id) ?? abort(404);
        $this->admin->saveStandard($this->validateStandard($request, $id), $id);

        return redirect()->route('heritage.hadmin.standard-list')->with('success', __('Accounting standard updated successfully'));
    }

    public function standardToggle(int $id)
    {
        $this->admin->toggleStandard($id);

        return redirect()->route('heritage.hadmin.standard-list')->with('success', __('Standard status updated'));
    }

    public function standardDelete(int $id)
    {
        if (! $this->admin->deleteStandard($id)) {
            return redirect()->route('heritage.hadmin.standard-list')->with('error', __('Cannot delete - standard is in use by heritage assets'));
        }

        return redirect()->route('heritage.hadmin.standard-list')->with('success', __('Accounting standard deleted'));
    }

    private function validateStandard(Request $request, ?int $id = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('heritage_accounting_standard', 'code')->ignore($id)],
            'name' => 'required|string|max:100',
            'country' => 'required|string|max:50',
            'description' => 'nullable|string|max:10000',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'valuation_methods' => 'nullable|array',
            'valuation_methods.*' => ['string', Rule::in(array_keys($this->valuationMethods()))],
            'disclosure_requirements' => 'nullable|string|max:10000',
            'capitalisation_required' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);
    }

    /** Valuation bases a standard may allow (Dropdown Manager taxonomy). */
    private function valuationMethods(): array
    {
        return DropdownOptions::resolve('heritage_standard_valuation_method', [
            'cost' => 'Historical Cost',
            'fair_value' => 'Fair Value',
            'deemed_cost' => 'Deemed Cost',
            'revaluation' => 'Revaluation Model',
            'valuation' => 'Valuation',
            'nominal' => 'Nominal Value',
            'insurance' => 'Insurance Value',
            'replacement' => 'Replacement Cost',
        ]);
    }

    // =====================
    // Compliance rules
    // =====================
    public function ruleList(Request $request)
    {
        $standardId = $request->integer('standard_id') ?: null;

        return view('ahg-heritage-manage::heritage-admin.rule-list', [
            'standardId' => $standardId,
            'standards' => $this->admin->standards(true),
            'rules' => $this->admin->rules($standardId),
        ] + $this->ruleLookups());
    }

    public function ruleAdd(Request $request)
    {
        return view('ahg-heritage-manage::heritage-admin.rule-add', [
            'rule' => null,
            'standards' => $this->admin->standards(true),
            'preselectedStandard' => $request->integer('standard_id') ?: null,
            'formAction' => route('heritage.hadmin.rule-store'),
        ] + $this->ruleLookups());
    }

    public function ruleStore(Request $request)
    {
        $v = $this->validateRule($request);
        $this->admin->saveRule($v);

        return redirect()->route('heritage.hadmin.rule-list', ['standard_id' => $v['standard_id']])->with('success', __('Compliance rule added'));
    }

    public function ruleEdit(int $id)
    {
        $rule = $this->admin->rule($id) ?? abort(404);
        $standards = $this->admin->standards(true);
        if (! $standards->contains('id', $rule->standard_id) && ($own = $this->admin->standard($rule->standard_id))) {
            $standards->push($own);
        }

        return view('ahg-heritage-manage::heritage-admin.rule-edit', [
            'rule' => $rule,
            'standards' => $standards,
            'preselectedStandard' => $rule->standard_id,
            'formAction' => route('heritage.hadmin.rule-update', $id),
        ] + $this->ruleLookups());
    }

    public function ruleUpdate(Request $request, int $id)
    {
        $rule = $this->admin->rule($id) ?? abort(404);
        $this->admin->saveRule($this->validateRule($request), $id);

        return redirect()->route('heritage.hadmin.rule-list', ['standard_id' => $rule->standard_id])->with('success', __('Compliance rule updated'));
    }

    public function ruleToggle(int $id)
    {
        $rule = $this->admin->toggleRule($id);

        return redirect()->route('heritage.hadmin.rule-list', array_filter(['standard_id' => $rule->standard_id ?? null]))
            ->with('success', __('Rule status updated'));
    }

    public function ruleDelete(int $id)
    {
        $rule = $this->admin->deleteRule($id);

        return redirect()->route('heritage.hadmin.rule-list', array_filter(['standard_id' => $rule->standard_id ?? null]))
            ->with('success', __('Compliance rule deleted'));
    }

    /** Category / check type / severity values from the Dropdown Manager. */
    private function ruleLookups(): array
    {
        return [
            'categories' => DropdownOptions::resolve('compliance_category', [
                'recognition' => 'Recognition', 'measurement' => 'Measurement', 'disclosure' => 'Disclosure',
            ]),
            'checkTypes' => DropdownOptions::resolve('compliance_check_type', [
                'required_field' => 'Required field', 'value_check' => 'Value check',
                'date_check' => 'Date check', 'custom' => 'Custom',
            ]),
            'severities' => DropdownOptions::resolve('severity', ['error' => 'Error', 'warning' => 'Warning', 'info' => 'Info']),
        ];
    }

    private function validateRule(Request $request): array
    {
        $lk = $this->ruleLookups();

        return $request->validate([
            'standard_id' => 'required|integer|exists:heritage_accounting_standard,id',
            'category' => ['required', Rule::in(array_keys($lk['categories']))],
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'error_message' => 'required|string|max:255',
            'reference' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'check_type' => ['required', Rule::in(array_keys($lk['checkTypes']))],
            'field_name' => 'nullable|string|max:100',
            'condition' => 'nullable|string|max:255',
            'severity' => ['required', Rule::in(array_keys($lk['severities']))],
            'is_active' => 'nullable|boolean',
        ]);
    }
}
