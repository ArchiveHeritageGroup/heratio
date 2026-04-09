<?php

/**
 * DonorController - Controller for Heratio
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



namespace AhgDonorManage\Controllers;

use AhgDonorManage\Services\DonorBrowseService;
use AhgDonorManage\Services\DonorService;
use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DonorController extends Controller
{
    protected DonorService $service;

    public function __construct()
    {
        $this->service = new DonorService(app()->getLocale());
    }

    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $browseService = new DonorBrowseService($culture);

        $result = $browseService->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'alphabetic'),
            'sortDir' => $request->get('sortDir', ''),
            'subquery' => $request->get('subquery', ''),
        ]);

        return view('ahg-donor-manage::browse', [
            'pager' => new SimplePager($result),
            'sortOptions' => [
                'alphabetic' => 'Name',
                'lastUpdated' => 'Date modified',
                'identifier' => 'Identifier',
            ],
        ]);
    }

    public function show(string $slug)
    {
        $donor = $this->service->getBySlug($slug);
        if (!$donor) abort(404);

        return view('ahg-donor-manage::show', [
            'donor' => $donor,
            'contacts' => $this->service->getContacts($donor->id),
            'accessions' => $this->service->getRelatedAccessions($donor->id),
            'informationObjects' => $this->service->getInformationObjects($donor->id),
        ]);
    }

    public function create()
    {
        return view('ahg-donor-manage::edit', [
            'donor' => null,
            'contacts' => collect([$this->emptyContact()]),
            'linkedInformationObjects' => collect(),
        ]);
    }

    public function edit(string $slug)
    {
        $donor = $this->service->getBySlug($slug);
        if (!$donor) abort(404);

        $contacts = $this->service->getContacts($donor->id);
        if ($contacts->isEmpty()) {
            $contacts = collect([$this->emptyContact()]);
        }

        return view('ahg-donor-manage::edit', [
            'donor' => $donor,
            'contacts' => $contacts,
            'linkedInformationObjects' => $this->service->getInformationObjects($donor->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['authorized_form_of_name' => 'required|string|max:1024']);
        $id = $this->service->create($request->only($this->fields()));
        if ($request->has('information_objects')) {
            $this->service->syncInformationObjects($id, (array) $request->input('information_objects', []));
        }
        return redirect()->route('donor.show', $this->service->getSlug($id))->with('success', 'Donor created successfully.');
    }

    public function update(Request $request, string $slug)
    {
        $donor = $this->service->getBySlug($slug);
        if (!$donor) abort(404);
        $request->validate(['authorized_form_of_name' => 'required|string|max:1024']);
        $this->service->update($donor->id, $request->only($this->fields()));
        if ($request->has('information_objects')) {
            $this->service->syncInformationObjects($donor->id, (array) $request->input('information_objects', []));
        }
        return redirect()->route('donor.show', $slug)->with('success', 'Donor updated successfully.');
    }

    public function confirmDelete(string $slug)
    {
        $donor = $this->service->getBySlug($slug);
        if (!$donor) abort(404);
        return view('ahg-donor-manage::delete', ['donor' => $donor]);
    }

    public function destroy(string $slug)
    {
        $donor = $this->service->getBySlug($slug);
        if (!$donor) abort(404);
        $this->service->delete($donor->id);
        return redirect()->route('donor.browse')->with('success', 'Donor deleted successfully.');
    }

    private function emptyContact(): object
    {
        return (object) [
            'id' => null, 'primary_contact' => 1, 'contact_person' => '', 'street_address' => '',
            'website' => '', 'email' => '', 'telephone' => '', 'fax' => '', 'postal_code' => '',
            'country_code' => '', 'longitude' => '', 'latitude' => '', 'contact_note' => '',
            'contact_type' => '', 'city' => '', 'region' => '', 'note' => '',
            'title' => '', 'role' => '', 'department' => '', 'id_number' => '',
            'preferred_contact_method' => '', 'language_preference' => '', 'cell' => '',
            'alternative_email' => '', 'alternative_phone' => '',
        ];
    }

    private function fields(): array
    {
        return [
            'authorized_form_of_name', 'dates_of_existence', 'history', 'places',
            'legal_status', 'functions', 'mandates', 'internal_structures',
            'general_context', 'institution_responsible_identifier', 'rules',
            'sources', 'revision_history', 'description_identifier',
            'corporate_body_identifiers', 'contacts',
        ];
    }

    // ── Agreement Actions ─────────────────────────────────────────

    public function agreementDashboard()
    {
        $activeCount = 0;
        $expiringCount = 0;
        $archivedCount = 0;

        try {
            if (\Schema::hasTable('donor_agreement')) {
                $activeCount = \DB::table('donor_agreement')->where('status', 'active')->count();
                $expiringCount = \DB::table('donor_agreement')
                    ->where('status', 'active')
                    ->whereNotNull('expiry_date')
                    ->where('expiry_date', '<=', now()->addDays(30))
                    ->count();
                $archivedCount = \DB::table('donor_agreement')
                    ->whereIn('status', ['expired', 'terminated'])
                    ->count();
            }
        } catch (\Exception $e) {
            // Table may not exist
        }

        return view('ahg-donor-manage::agreement-dashboard', compact('activeCount', 'expiringCount', 'archivedCount'));
    }

    public function agreementAdd(Request $request)
    {
        if ($request->isMethod('post')) {
            return $this->agreementStore($request);
        }

        return view('ahg-donor-manage::agreement-add', [
            'record' => (object) [],
            'formAction' => route('donor.agreement.add'),
        ]);
    }

    private function agreementStore(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        try {
            if (\Schema::hasTable('donor_agreement')) {
                $data = $request->only([
                    'title', 'description', 'agreement_number', 'agreement_type_id',
                    'donor_id', 'donor_name', 'donor_contact_info',
                    'institution_name', 'institution_contact_info',
                    'repository_representative', 'repository_representative_title',
                    'legal_representative', 'legal_representative_title', 'legal_representative_contact',
                    'agreement_date', 'effective_date', 'expiry_date', 'review_date',
                    'scope_description', 'extent_statement', 'transfer_method', 'transfer_date', 'received_by',
                    'general_terms', 'special_conditions', 'internal_notes', 'status',
                ]);
                $data['status'] = $data['status'] ?? 'draft';
                $data['created_at'] = now();
                $data['updated_at'] = now();
                $data['created_by'] = auth()->id();

                $id = \DB::table('donor_agreement')->insertGetId($data);

                return redirect()->route('donor.agreement.view', $id)
                    ->with('success', 'Agreement created successfully.');
            }
        } catch (\Exception $e) {
            return redirect()->route('donor.agreements')
                ->with('error', 'Failed to create agreement: ' . $e->getMessage());
        }

        return redirect()->route('donor.agreements');
    }

    public function agreementView(int $id)
    {
        $record = null;

        try {
            if (\Schema::hasTable('donor_agreement')) {
                $record = \DB::table('donor_agreement as da')
                    ->leftJoin('donor_agreement_i18n as dai', function ($join) {
                        $join->on('da.id', '=', 'dai.id')
                            ->where('dai.culture', '=', app()->getLocale());
                    })
                    ->where('da.id', $id)
                    ->select(['da.*', 'dai.title as i18n_title', 'dai.description as i18n_description'])
                    ->first();

                if ($record) {
                    if (empty($record->title) && !empty($record->i18n_title)) {
                        $record->title = $record->i18n_title;
                    }
                    if (empty($record->description) && !empty($record->i18n_description)) {
                        $record->description = $record->i18n_description;
                    }
                }
            }
        } catch (\Exception $e) {
            // Table may not exist
        }

        if (!$record) {
            $record = (object) ['id' => $id];
        }

        return view('ahg-donor-manage::agreement-view', compact('record'));
    }

    public function agreementEdit(Request $request, int $id)
    {
        if ($request->isMethod('post')) {
            return $this->agreementUpdate($request, $id);
        }

        $record = null;
        $documents = collect();
        $linkedRecords = collect();
        $linkedAccessions = collect();
        $reminders = collect();

        try {
            if (\Schema::hasTable('donor_agreement')) {
                $record = \DB::table('donor_agreement as da')
                    ->leftJoin('donor_agreement_i18n as dai', function ($join) {
                        $join->on('da.id', '=', 'dai.id')
                            ->where('dai.culture', '=', app()->getLocale());
                    })
                    ->where('da.id', $id)
                    ->select(['da.*', 'dai.title as i18n_title', 'dai.description as i18n_description'])
                    ->first();

                if ($record) {
                    if (empty($record->title) && !empty($record->i18n_title)) {
                        $record->title = $record->i18n_title;
                    }
                    if (empty($record->description) && !empty($record->i18n_description)) {
                        $record->description = $record->i18n_description;
                    }
                }

                if (\Schema::hasTable('donor_agreement_document')) {
                    $documents = \DB::table('donor_agreement_document')
                        ->where('donor_agreement_id', $id)
                        ->orderByDesc('created_at')
                        ->get();
                }

                if (\Schema::hasTable('donor_agreement_record')) {
                    $linkedRecords = \DB::table('donor_agreement_record as dar')
                        ->join('information_object as io', 'dar.information_object_id', '=', 'io.id')
                        ->leftJoin('information_object_i18n as ioi', function ($join) {
                            $join->on('io.id', '=', 'ioi.id')
                                ->where('ioi.culture', '=', app()->getLocale());
                        })
                        ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
                        ->where('dar.agreement_id', $id)
                        ->select(['dar.id as link_id', 'io.id', 'io.identifier', 's.slug', 'ioi.title'])
                        ->get();
                }

                if (\Schema::hasTable('donor_agreement_accession')) {
                    $linkedAccessions = \DB::table('donor_agreement_accession as daa')
                        ->join('accession as acc', 'daa.accession_id', '=', 'acc.id')
                        ->leftJoin('accession_i18n as acci', function ($join) {
                            $join->on('acc.id', '=', 'acci.id')
                                ->where('acci.culture', '=', app()->getLocale());
                        })
                        ->where('daa.donor_agreement_id', $id)
                        ->select(['daa.id as link_id', 'acc.id', 'acc.identifier', 'acci.title'])
                        ->get();
                }

                if (\Schema::hasTable('donor_agreement_reminder')) {
                    $reminders = \DB::table('donor_agreement_reminder')
                        ->where('donor_agreement_id', $id)
                        ->orderBy('reminder_date')
                        ->get();
                }
            }
        } catch (\Exception $e) {
            // Table may not exist
        }

        if (!$record) {
            abort(404, 'Agreement not found');
        }

        return view('ahg-donor-manage::agreement-edit', [
            'record' => $record,
            'documents' => $documents,
            'linkedRecords' => $linkedRecords,
            'linkedAccessions' => $linkedAccessions,
            'reminders' => $reminders,
            'formAction' => route('donor.agreement.edit', $id),
        ]);
    }

    private function agreementUpdate(Request $request, int $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        try {
            \DB::beginTransaction();

            $data = $request->only([
                'title', 'description', 'agreement_number', 'agreement_type_id',
                'donor_id', 'donor_name', 'donor_contact_info',
                'institution_name', 'institution_contact_info',
                'repository_representative', 'repository_representative_title',
                'legal_representative', 'legal_representative_title', 'legal_representative_contact',
                'agreement_date', 'effective_date', 'expiry_date', 'review_date',
                'termination_date', 'termination_reason',
                'scope_description', 'extent_statement', 'transfer_method', 'transfer_date', 'received_by',
                'has_financial_terms', 'purchase_amount', 'currency', 'payment_terms',
                'general_terms', 'special_conditions',
                'donor_signature_date', 'donor_signature_name',
                'repository_signature_date', 'repository_signature_name',
                'witness_name', 'witness_date',
                'internal_notes', 'is_template', 'status',
            ]);
            $data['updated_at'] = now();
            $data['updated_by'] = auth()->id();

            // Nullify empty dates
            foreach (['agreement_date', 'effective_date', 'expiry_date', 'review_date', 'termination_date', 'transfer_date', 'donor_signature_date', 'repository_signature_date', 'witness_date'] as $dateField) {
                if (isset($data[$dateField]) && empty($data[$dateField])) {
                    $data[$dateField] = null;
                }
            }

            \DB::table('donor_agreement')->where('id', $id)->update($data);

            // Update i18n
            if (\Schema::hasTable('donor_agreement_i18n')) {
                \DB::table('donor_agreement_i18n')->updateOrInsert(
                    ['id' => $id, 'culture' => app()->getLocale()],
                    ['title' => $data['title'] ?? '', 'description' => $data['description'] ?? '']
                );
            }

            // Log history
            if (\Schema::hasTable('donor_agreement_history')) {
                try {
                    \DB::table('donor_agreement_history')->insert([
                        'agreement_id' => $id,
                        'action' => 'updated',
                        'user_id' => auth()->id(),
                        'created_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    // History logging is optional
                }
            }

            \DB::commit();

            return redirect()->route('donor.agreement.view', $id)
                ->with('success', 'Agreement updated successfully.');
        } catch (\Exception $e) {
            \DB::rollBack();
            return redirect()->route('donor.agreement.edit', $id)
                ->with('error', 'Error updating agreement: ' . $e->getMessage());
        }
    }

    public function agreementDelete(Request $request, int $id)
    {
        if ($request->isMethod('delete') || $request->isMethod('post')) {
            try {
                if (\Schema::hasTable('donor_agreement')) {
                    // Delete related records first
                    if (\Schema::hasTable('donor_agreement_document')) {
                        \DB::table('donor_agreement_document')->where('donor_agreement_id', $id)->delete();
                    }
                    if (\Schema::hasTable('donor_agreement_record')) {
                        \DB::table('donor_agreement_record')->where('agreement_id', $id)->delete();
                    }
                    if (\Schema::hasTable('donor_agreement_accession')) {
                        \DB::table('donor_agreement_accession')->where('donor_agreement_id', $id)->delete();
                    }
                    if (\Schema::hasTable('donor_agreement_reminder')) {
                        \DB::table('donor_agreement_reminder')->where('donor_agreement_id', $id)->delete();
                    }
                    if (\Schema::hasTable('donor_agreement_i18n')) {
                        \DB::table('donor_agreement_i18n')->where('id', $id)->delete();
                    }

                    \DB::table('donor_agreement')->where('id', $id)->delete();
                }

                return redirect()->route('donor.agreements')
                    ->with('success', 'Agreement deleted successfully.');
            } catch (\Exception $e) {
                return redirect()->route('donor.agreements')
                    ->with('error', 'Error deleting agreement: ' . $e->getMessage());
            }
        }

        // GET request - show confirmation page
        $record = null;
        try {
            if (\Schema::hasTable('donor_agreement')) {
                $record = \DB::table('donor_agreement')->where('id', $id)->first();
            }
        } catch (\Exception $e) {
            // ignore
        }

        if (!$record) {
            abort(404, 'Agreement not found');
        }

        return view('ahg-donor-manage::agreement-delete', compact('record'));
    }

    public function agreementReminders(Request $request)
    {
        $rows = collect();

        try {
            if (\Schema::hasTable('donor_agreement_reminder')) {
                $rows = \DB::table('donor_agreement_reminder as r')
                    ->leftJoin('donor_agreement as da', 'r.donor_agreement_id', '=', 'da.id')
                    ->select(['r.*', 'da.title as agreement_title', 'da.agreement_number'])
                    ->orderBy('r.reminder_date')
                    ->get();
            }
        } catch (\Exception $e) {
            // Table may not exist
        }

        return view('ahg-donor-manage::agreement-reminders', compact('rows'));
    }

    public function agreementAutocompleteAccessions(Request $request)
    {
        $query = $request->get('query', '');
        $results = [];

        if (strlen($query) >= 2) {
            try {
                $results = \DB::table('accession as a')
                    ->leftJoin('accession_i18n as ai', function ($join) {
                        $join->on('a.id', '=', 'ai.id')
                            ->where('ai.culture', '=', app()->getLocale());
                    })
                    ->where(function ($q) use ($query) {
                        $q->where('a.identifier', 'LIKE', "%{$query}%")
                          ->orWhere('ai.title', 'LIKE', "%{$query}%");
                    })
                    ->select(['a.id', 'a.identifier', 'ai.title'])
                    ->limit(20)
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                // ignore
            }
        }

        return response()->json($results);
    }

    public function agreementAutocompleteRecords(Request $request)
    {
        $query = $request->get('query', '');
        $results = [];

        if (strlen($query) >= 2) {
            try {
                $results = \DB::table('information_object as io')
                    ->leftJoin('information_object_i18n as ioi', function ($join) {
                        $join->on('io.id', '=', 'ioi.id')
                            ->where('ioi.culture', '=', app()->getLocale());
                    })
                    ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
                    ->where(function ($q) use ($query) {
                        $q->where('io.identifier', 'LIKE', "%{$query}%")
                          ->orWhere('ioi.title', 'LIKE', "%{$query}%");
                    })
                    ->select(['io.id', 'io.identifier', 'ioi.title', 's.slug'])
                    ->limit(20)
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                // ignore
            }
        }

        return response()->json($results);
    }

    public function donorIndex(Request $request) { return view('ahg-donor-manage::donor-index', ['rows' => collect()]); }

    public function donorView(string $slug) { return view('ahg-donor-manage::donor-view', ['record' => (object)['slug'=>$slug]]); }
}
