<?php

/**
 * NmmzController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgNmmz\Controllers;

use AhgNmmz\Services\NmmzService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NmmzController extends Controller
{
    protected NmmzService $service;

    public function __construct()
    {
        $this->service = new NmmzService();
    }

    public function index()
    {
        $stats = $this->service->getDashboardStats();
        $compliance = $this->service->getComplianceStatus();
        $config = $this->service->getAllConfig();

        $recentMonuments = \Illuminate\Support\Facades\DB::table('nmmz_monument')
            ->orderByDesc('created_at')->limit(5)->get();

        $pendingPermits = \Illuminate\Support\Facades\DB::table('nmmz_export_permit')
            ->where('status', 'pending')->orderBy('created_at')->limit(5)->get();

        return view('ahg-nmmz::index', compact('stats', 'compliance', 'config', 'recentMonuments', 'pendingPermits'));
    }

    // Monuments
    public function monuments(Request $request)
    {
        $filters = [
            'category_id' => $request->get('category'),
            'status' => $request->get('status'),
            'province' => $request->get('province'),
            'search' => $request->get('q'),
        ];

        $monuments = $this->service->getMonuments($filters);
        $categories = $this->service->getCategories();

        return view('ahg-nmmz::monuments', compact('monuments', 'categories', 'filters'));
    }

    public function monumentCreate(Request $request)
    {
        $categories = $this->service->getCategories();

        if ($request->isMethod('post')) {
            $id = $this->service->createMonument($request->only([
                'category_id', 'name', 'description', 'historical_significance',
                'province', 'district', 'location_description',
                'gps_latitude', 'gps_longitude', 'protection_level',
                'legal_status', 'ownership_type', 'condition_rating',
            ]) + ['user_id' => auth()->id()]);

            return redirect()->route('nmmz.monument.view', $id)->with('notice', 'Monument registered');
        }

        return view('ahg-nmmz::monument-create', compact('categories'));
    }

    public function monumentView(int $id)
    {
        $monument = $this->service->getMonument($id);
        abort_unless($monument, 404, 'Monument not found');

        $inspections = $this->service->getMonumentInspections($id);

        return view('ahg-nmmz::monument-view', compact('monument', 'inspections'));
    }

    // Antiquities
    public function antiquities(Request $request)
    {
        $filters = [
            'status' => $request->get('status'),
            'object_type' => $request->get('type'),
            'search' => $request->get('q'),
        ];

        $antiquities = $this->service->getAntiquities($filters);

        return view('ahg-nmmz::antiquities', compact('antiquities', 'filters'));
    }

    public function antiquityCreate(Request $request)
    {
        if ($request->isMethod('post')) {
            $id = $this->service->createAntiquity($request->only([
                'name', 'description', 'object_type', 'material',
                'estimated_age_years', 'provenance', 'find_location',
                'dimensions', 'condition_rating', 'current_location', 'estimated_value',
            ]) + ['user_id' => auth()->id()]);

            return redirect()->route('nmmz.antiquity.view', $id)->with('notice', 'Antiquity registered');
        }

        return view('ahg-nmmz::antiquity-create');
    }

    public function antiquityView(int $id)
    {
        $antiquity = $this->service->getAntiquity($id);
        abort_unless($antiquity, 404, 'Antiquity not found');

        return view('ahg-nmmz::antiquity-view', compact('antiquity'));
    }

    // Permits
    public function permits(Request $request)
    {
        $permits = $this->service->getPermits(['status' => $request->get('status')]);
        $currentStatus = $request->get('status');

        return view('ahg-nmmz::permits', compact('permits', 'currentStatus'));
    }

    public function permitCreate(Request $request)
    {
        if ($request->isMethod('post')) {
            $id = $this->service->createPermit($request->only([
                'applicant_name', 'applicant_address', 'applicant_email',
                'applicant_phone', 'applicant_type', 'antiquity_id',
                'object_description', 'quantity', 'estimated_value',
                'export_purpose', 'purpose_details', 'destination_country',
                'destination_institution', 'export_date_proposed', 'return_date',
            ]) + ['user_id' => auth()->id()]);

            return redirect()->route('nmmz.permit.view', $id)->with('notice', 'Permit application submitted');
        }

        return view('ahg-nmmz::permit-create');
    }

    public function permitView(Request $request, int $id)
    {
        $permit = $this->service->getPermit($id);
        abort_unless($permit, 404, 'Permit not found');

        if ($request->isMethod('post')) {
            $action = $request->get('action_type');

            if ($action === 'approve') {
                $this->service->approvePermit($id, auth()->id(), $request->get('conditions'));
            } elseif ($action === 'reject') {
                $this->service->rejectPermit($id, auth()->id(), $request->get('rejection_reason'));
            }

            return redirect()->route('nmmz.permit.view', $id)->with('notice', 'Permit updated');
        }

        return view('ahg-nmmz::permit-view', compact('permit'));
    }

    // Sites
    public function sites(Request $request)
    {
        $sites = $this->service->getSites([
            'province' => $request->get('province'),
            'protection_status' => $request->get('status'),
        ]);
        $currentStatus = $request->get('status');

        return view('ahg-nmmz::sites', compact('sites', 'currentStatus'));
    }

    public function siteCreate(Request $request)
    {
        if ($request->isMethod('post')) {
            $id = $this->service->createSite($request->only([
                'name', 'site_type', 'description', 'province', 'district',
                'location_description', 'gps_latitude', 'gps_longitude',
                'period', 'discovery_date', 'discovered_by',
                'protection_status', 'research_potential',
            ]) + ['user_id' => auth()->id()]);

            return redirect()->route('nmmz.site.view', $id)->with('notice', 'Site registered');
        }

        return view('ahg-nmmz::site-create');
    }

    public function siteView(int $id)
    {
        $site = $this->service->getSite($id);
        abort_unless($site, 404, 'Site not found');

        return view('ahg-nmmz::site-view', compact('site'));
    }

    // Heritage Impact Assessments
    public function hia(Request $request)
    {
        $hias = $this->service->getHIAs([
            'status' => $request->get('status'),
            'province' => $request->get('province'),
        ]);
        $currentStatus = $request->get('status');

        return view('ahg-nmmz::hia', compact('hias', 'currentStatus'));
    }

    public function hiaCreate(Request $request)
    {
        if ($request->isMethod('post')) {
            $this->service->createHIA($request->only([
                'project_name', 'project_type', 'project_description',
                'project_location', 'province', 'district',
                'developer_name', 'developer_contact', 'developer_email',
                'assessor_name', 'assessor_qualification',
                'impact_level', 'impact_description', 'mitigation_measures',
            ]) + ['user_id' => auth()->id()]);

            return redirect()->route('nmmz.hia')->with('notice', 'HIA submitted');
        }

        return view('ahg-nmmz::hia-create');
    }

    // Reports
    public function reports()
    {
        return view('ahg-nmmz::reports');
    }

    // Config
    public function config(Request $request)
    {
        if ($request->isMethod('post')) {
            $configs = [
                'antiquity_age_years', 'export_permit_fee_usd',
                'export_permit_validity_days', 'nmmz_contact_email',
                'nmmz_contact_phone', 'director_name',
            ];

            foreach ($configs as $key) {
                $value = $request->get($key);
                if ($value !== null) {
                    $this->service->setConfig($key, $value);
                }
            }

            return redirect()->route('nmmz.config')->with('notice', 'Configuration saved');
        }

        $config = $this->service->getAllConfig();

        return view('ahg-nmmz::config', compact('config'));
    }

    /**
     * Admin dashboard for NMMZ.
     */
    public function admin()
    {
        $stats = $this->service->getDashboardStats();
        $config = $this->service->getAllConfig();

        return view('ahg-nmmz::admin', compact('stats', 'config'));
    }

    /**
     * Handle POST actions for NMMZ.
     */
    public function post(Request $request)
    {
        $action = $request->get('action');
        $id = (int) $request->get('id');

        if ($action === 'delete_monument' && $id) {
            $this->service->deleteMonument($id);

            return redirect()->route('nmmz.monuments')->with('notice', 'Monument deleted.');
        }

        if ($action === 'delete_antiquity' && $id) {
            $this->service->deleteAntiquity($id);

            return redirect()->route('nmmz.antiquities')->with('notice', 'Antiquity deleted.');
        }

        if ($action === 'delete_site' && $id) {
            $this->service->deleteSite($id);

            return redirect()->route('nmmz.sites')->with('notice', 'Site deleted.');
        }

        return redirect()->back()->with('error', 'Invalid action.');
    }
}
