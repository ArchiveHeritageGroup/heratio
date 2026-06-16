<?php

/**
 * AccessionController - Controller for Heratio
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

namespace AhgAccessionManage\Controllers;

use AhgAccessionManage\Services\AccessionBrowseService;
use AhgAccessionManage\Services\AccessionService;
use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessionController extends Controller
{
    protected AccessionService $service;

    public function __construct()
    {
        $this->service = new AccessionService(app()->getLocale());
    }

    public function intakeQueue(Request $request)
    {
        $culture = app()->getLocale();

        $rows = DB::table('accession')
            ->join('accession_i18n', function ($j) use ($culture) {
                $j->on('accession.id', '=', 'accession_i18n.id')
                    ->where('accession_i18n.culture', '=', $culture);
            })
            ->join('object', 'accession.id', '=', 'object.id')
            ->leftJoin('slug', function ($j) {
                $j->on('accession.id', '=', 'slug.object_id');
            })
            ->leftJoin('term_i18n as status_term', function ($j) use ($culture) {
                $j->on('accession.processing_status_id', '=', 'status_term.id')
                    ->where('status_term.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as priority_term', function ($j) use ($culture) {
                $j->on('accession.processing_priority_id', '=', 'priority_term.id')
                    ->where('priority_term.culture', '=', $culture);
            })
            ->whereNotIn('accession.processing_status_id', function ($q) {
                // Exclude "Complete" status (term 163 in default AtoM taxonomy)
                $q->select('id')->from('term')->where('taxonomy_id', 90);
            })
            ->select([
                'accession.id',
                'accession.identifier',
                'accession_i18n.title',
                'accession.date',
                'status_term.name as status_name',
                'priority_term.name as priority_name',
                'slug.slug',
                'object.created_at',
                'object.updated_at',
            ])
            ->orderBy('accession.date', 'desc')
            ->paginate($request->get('limit', 25));

        return view('ahg-accession-manage::intake-queue', [
            'rows' => $rows,
        ]);
    }

    public function dashboard(Request $request)
    {
        $culture = app()->getLocale();

        $total = DB::table('accession')->count();

        $byStatus = DB::table('accession')
            ->leftJoin('term_i18n', function ($j) use ($culture) {
                $j->on('accession.processing_status_id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $culture);
            })
            ->select(DB::raw('COALESCE(term_i18n.name, "Unassigned") as status_name'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('status_name')
            ->orderByDesc('cnt')
            ->get();

        $byPriority = DB::table('accession')
            ->leftJoin('term_i18n', function ($j) use ($culture) {
                $j->on('accession.processing_priority_id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $culture);
            })
            ->select(DB::raw('COALESCE(term_i18n.name, "Unassigned") as priority_name'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('priority_name')
            ->orderByDesc('cnt')
            ->get();

        $recentCount = DB::table('accession')
            ->join('object', 'accession.id', '=', 'object.id')
            ->where('object.created_at', '>=', now()->subDays(30))
            ->count();

        return view('ahg-accession-manage::dashboard', [
            'total' => $total,
            'byStatus' => $byStatus,
            'byPriority' => $byPriority,
            'recentCount' => $recentCount,
        ]);
    }

    public function valuationReport(Request $request)
    {
        $culture = app()->getLocale();

        $rows = DB::table('accession')
            ->join('accession_i18n', function ($j) use ($culture) {
                $j->on('accession.id', '=', 'accession_i18n.id')
                    ->where('accession_i18n.culture', '=', $culture);
            })
            ->join('object', 'accession.id', '=', 'object.id')
            ->leftJoin('slug', function ($j) {
                $j->on('accession.id', '=', 'slug.object_id');
            })
            ->leftJoin('term_i18n as type_term', function ($j) use ($culture) {
                $j->on('accession.acquisition_type_id', '=', 'type_term.id')
                    ->where('type_term.culture', '=', $culture);
            })
            ->select([
                'accession.id',
                'accession.identifier',
                'accession_i18n.title',
                'accession.date',
                'accession_i18n.received_extent_units',
                'accession_i18n.appraisal',
                'type_term.name as acquisition_type',
                'slug.slug',
            ])
            ->orderBy('accession.identifier')
            ->paginate($request->get('limit', 25));

        return view('ahg-accession-manage::valuation-report', [
            'rows' => $rows,
        ]);
    }

    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $browseService = new AccessionBrowseService($culture);

        $result = $browseService->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'lastUpdated'),
            'sortDir' => $request->get('sortDir', ''),
            'subquery' => $request->get('subquery', ''),
        ]);

        $pager = new SimplePager($result);
        $termNames = $browseService->resolveTermNames($result['hits'] ?? []);

        return view('ahg-accession-manage::browse', [
            'pager' => $pager,
            'termNames' => $termNames,
            'sortOptions' => [
                'alphabetic' => 'Title',
                'identifier' => 'Accession number',
                'date' => 'Acquisition date',
                'lastUpdated' => 'Date modified',
            ],
        ]);
    }

    public function exportCsv()
    {
        $culture = app()->getLocale();

        $rows = DB::table('accession')
            ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
            ->join('object', 'accession.id', '=', 'object.id')
            ->leftJoin('term_i18n as status_term', function ($j) use ($culture) {
                $j->on('accession.processing_status_id', '=', 'status_term.id')
                    ->where('status_term.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as priority_term', function ($j) use ($culture) {
                $j->on('accession.processing_priority_id', '=', 'priority_term.id')
                    ->where('priority_term.culture', '=', $culture);
            })
            ->where('accession_i18n.culture', $culture)
            ->select([
                'accession.identifier',
                'accession_i18n.title',
                'accession.date',
                'status_term.name as status',
                'priority_term.name as priority',
                'object.created_at',
                'object.updated_at',
            ])
            ->orderBy('accession.identifier')
            ->get();

        return new StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Identifier', 'Title', 'Accession Date', 'Status', 'Priority', 'Created', 'Updated']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->identifier,
                    $row->title,
                    $row->date,
                    $row->status ?? '',
                    $row->priority ?? '',
                    $row->created_at,
                    $row->updated_at,
                ]);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="accessions-'.date('Ymd').'.csv"',
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (! $accession) {
            abort(404);
        }

        // Resolve term names for type/priority/status IDs
        $termIds = array_filter([
            $accession->acquisition_type_id,
            $accession->processing_priority_id,
            $accession->processing_status_id,
            $accession->resource_type_id,
        ]);
        $termNames = $this->service->getTermNames($termIds);

        // Get donors (plural) via relation table with contacts
        $donors = $this->service->getDonors($accession->id);
        foreach ($donors as $donorItem) {
            $donorItem->contacts = $this->service->getDonorContacts($donorItem->id);
        }
        $donor = $donors->first();

        // Get deaccessions
        $deaccessions = $this->service->getDeaccessions($accession->id);
        // Add slugs to deaccessions
        foreach ($deaccessions as $d) {
            $d->slug = $this->service->getSlug($d->id) ?? $d->id;
        }

        // Resolve deaccession scope term names
        $scopeIds = $deaccessions->pluck('scope_id')->filter()->unique()->values()->toArray();
        $scopeNames = $this->service->getTermNames($scopeIds);

        // Get accruals and accrual-to
        $accruals = $this->service->getAccruals($accession->id);
        $accrualTo = $this->service->getAccrualTo($accession->id);

        // Get creators, dates, events
        $creators = $this->service->getCreators($accession->id);
        $dates = $this->service->getDates($accession->id);
        $accessionEvents = $this->service->getAccessionEvents($accession->id);

        // Get alternative identifiers
        $alternativeIdentifiers = $this->service->getAlternativeIdentifiers($accession->id);

        // Get linked information objects
        $informationObjects = $this->service->getInformationObjects($accession->id);

        // Get rights records
        $rights = $this->service->getRights($accession->id);

        // Get physical objects for context menu
        $physicalObjects = $this->service->getPhysicalObjects($accession->id);

        // Source language name
        $sourceLangName = null;
        if ($accession->source_culture ?? null) {
            $langNames = ['en' => 'English', 'fr' => 'French', 'es' => 'Spanish', 'pt' => 'Portuguese', 'de' => 'German', 'nl' => 'Dutch', 'it' => 'Italian', 'af' => 'Afrikaans', 'zu' => 'Zulu', 'xh' => 'Xhosa', 'st' => 'Southern Sotho', 'tn' => 'Tswana', 'ar' => 'Arabic', 'ja' => 'Japanese', 'zh' => 'Chinese'];
            $sourceLangName = $langNames[$accession->source_culture] ?? $accession->source_culture;
        }

        // Surface the accession_require_donor_agreement and
        // accession_require_appraisal settings as a "Finalisation
        // requirements" panel — list of human-readable strings naming
        // each criterion still missing. Empty array = all gates passed.
        $finalisationBlockers = $this->service->finalisationBlockers($accession->id);

        // Workflow row from accession_v2 — used to render the current
        // status badge + decide whether to show the Finalise button.
        $workflow = DB::table('accession_v2')
            ->where('accession_id', $accession->id)
            ->first();

        return view('ahg-accession-manage::show', [
            'accession' => $accession,
            'termNames' => $termNames,
            'donor' => $donor,
            'donors' => $donors,
            'deaccessions' => $deaccessions,
            'scopeNames' => $scopeNames,
            'accruals' => $accruals,
            'accrualTo' => $accrualTo,
            'creators' => $creators,
            'dates' => $dates,
            'accessionEvents' => $accessionEvents,
            'alternativeIdentifiers' => $alternativeIdentifiers,
            'informationObjects' => $informationObjects,
            'rights' => $rights,
            'physicalObjects' => $physicalObjects,
            'sourceLangName' => $sourceLangName,
            'finalisationBlockers' => $finalisationBlockers,
            'workflow' => $workflow,
        ]);
    }

    /**
     * Finalise an accession — moves accession_v2.status to 'accepted' and
     * stamps accepted_at. Refuses the transition when any finalisationBlockers
     * (driven by the accession_require_donor_agreement + accession_require_appraisal
     * settings) are unmet, or when the accession is already accepted/rejected.
     * The UI button on the show page POSTs here.
     */
    public function finalise(Request $request, string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (! $accession) {
            abort(404);
        }

        $blockers = $this->service->finalisationBlockers($accession->id);
        if (! empty($blockers)) {
            return redirect()
                ->route('accession.show', $slug)
                ->with('error', 'Cannot finalise: '.implode('; ', $blockers));
        }

        $current = DB::table('accession_v2')
            ->where('accession_id', $accession->id)
            ->value('status');
        if ($current === 'accepted') {
            return redirect()
                ->route('accession.show', $slug)
                ->with('info', 'This accession is already finalised.');
        }
        if ($current === 'rejected') {
            return redirect()
                ->route('accession.show', $slug)
                ->with('error', 'A rejected accession cannot be finalised — clear the rejection first.');
        }

        $this->service->upsertWorkflow($accession->id, [
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return redirect()
            ->route('accession.show', $slug)
            ->with('success', 'Accession finalised.');
    }

    /**
     * Materialise a new InformationObject from this accession. Copies
     * basic metadata across (title + identifier + scope_and_content),
     * links the new IO to the accession via the relation table
     * (RELATION_ACCESSION = 167), and — when the
     * accession_rights_inheritance_enabled setting is on —
     * propagates the accession's PREMIS rights down via
     * inheritRightsToIo(). This is the caller the rights-inheritance
     * helper was waiting for; with this route in place all seven
     * accession settings are now enforcing.
     */
    public function createInformationObject(Request $request, string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (! $accession) {
            abort(404);
        }

        $newIoId = \AhgInformationObjectManage\Services\InformationObjectService::create([
            'title' => $accession->title ?? $accession->identifier,
            'identifier' => $accession->identifier,
            'scope_and_content' => $accession->scope_and_content ?? null,
        ]);

        // Link the new IO to the accession (relation: subject=IO, object=accession).
        // The relation table re-uses the object table's id (Qubit class-table
        // inheritance) — pre-create a QubitRelation object row and use its id.
        $relationId = DB::table('object')->insertGetId([
            'class_name' => 'QubitRelation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('relation')->insert([
            'id' => $relationId,
            'subject_id' => $newIoId,
            'object_id' => $accession->id,
            'type_id' => \AhgCore\Constants\TermId::RELATION_ACCESSION,
            'source_culture' => app()->getLocale(),
        ]);

        // Honour accession_rights_inheritance_enabled — inheritRightsToIo
        // is a no-op when the setting is off.
        $rightsApplied = $this->service->inheritRightsToIo($accession->id, $newIoId);

        $newSlug = DB::table('slug')->where('object_id', $newIoId)->value('slug');
        $msg = 'Archival description created from accession.';
        if ($rightsApplied > 0) {
            $msg .= " Inherited {$rightsApplied} rights record(s) from this accession.";
        }

        return redirect()
            ->route('informationobject.edit', $newSlug)
            ->with('success', $msg);
    }

    public function create()
    {
        $formChoices = $this->service->getFormChoices();

        // Settings-driven defaults. Passing them as scalars (rather than
        // wrapping them in a stub \$accession) keeps the form's
        // \$accession-truthy branches (Edit vs Add new title, route name)
        // unchanged for new-accession context.
        return view('ahg-accession-manage::edit', [
            'accession' => null,
            'donor' => null,
            'donorContact' => null,
            'formChoices' => $formChoices,
            'defaultIdentifier' => $this->service->nextAccessionNumber(),
            'defaultPriorityTermId' => $this->service->defaultPriorityTermId(),
        ]);
    }

    public function edit(string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (! $accession) {
            abort(404);
        }

        $donor = $this->service->getDonor($accession->id);
        $donorContact = null;
        if ($donor) {
            $donorContact = DB::table('contact_information')
                ->leftJoin('contact_information_i18n', function ($j) {
                    $j->on('contact_information.id', '=', 'contact_information_i18n.id')
                        ->where('contact_information_i18n.culture', '=', app()->getLocale());
                })
                ->where('contact_information.actor_id', $donor->id ?? 0)
                ->select('contact_information.*', 'contact_information_i18n.*')
                ->first();
        }
        // All linked donors (+ decrypted primary contact) for the multi-donor
        // "Related donors" table. Each becomes a pre-populated row + hidden
        // donors[] block the modal can edit.
        $dsvc = new \AhgDonorManage\Services\DonorService(app()->getLocale());
        $donorRows = [];
        foreach ($this->service->getDonors($accession->id) as $d) {
            $c = $dsvc->getContacts((int) $d->id)->first();
            $donorRows[] = [
                'id' => (int) $d->id,
                'slug' => $d->slug,
                'name' => $d->name,
                'contact_person' => $c->contact_person ?? '',
                'telephone' => $c->telephone ?? '',
                'fax' => $c->fax ?? '',
                'email' => $c->email ?? '',
                'url' => $c->website ?? '',
                'street_address' => $c->street_address ?? '',
                'region' => $c->region ?? '',
                'country' => $c->country_code ?? '',
                'postal_code' => $c->postal_code ?? '',
                'city' => $c->city ?? '',
                'latitude' => $c->latitude ?? '',
                'longitude' => $c->longitude ?? '',
                'contact_type' => $c->contact_type ?? '',
                'note' => $c->note ?? '',
            ];
        }

        $formChoices = $this->service->getFormChoices();

        return view('ahg-accession-manage::edit', [
            'accession' => $accession,
            'donor' => $donor,
            'donorContact' => $donorContact,
            'donorRows' => $donorRows,
            'formChoices' => $formChoices,
            'defaultIdentifier' => null,
            'defaultPriorityTermId' => null,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|max:255|unique:accession,identifier',
            'title' => 'nullable|string|max:1024',
            'date' => 'nullable|date',
            'acquisition_type_id' => 'nullable|integer|exists:term,id',
            'processing_priority_id' => 'nullable|integer|exists:term,id',
            'processing_status_id' => 'nullable|integer|exists:term,id',
            'resource_type_id' => 'nullable|integer|exists:term,id',
            'scope_and_content' => 'nullable|string',
            'archival_history' => 'nullable|string',
            'source_of_acquisition' => 'nullable|string',
            'location_information' => 'nullable|string',
            'received_extent_units' => 'nullable|string',
            'physical_characteristics' => 'nullable|string',
            'appraisal' => 'nullable|string',
            'processing_notes' => 'nullable|string',
        ]);

        $data = $request->only([
            'identifier', 'title', 'date',
            'acquisition_type_id', 'processing_priority_id',
            'processing_status_id', 'resource_type_id',
            'scope_and_content', 'archival_history', 'source_of_acquisition',
            'location_information', 'received_extent_units', 'physical_characteristics',
            'appraisal', 'processing_notes',
        ]);

        $id = $this->service->create($data);
        $slug = $this->service->getSlug($id);

        // Link/create the related donor(s) from the "Related donor" modal so
        // donors entered on the create form persist with the new accession.
        $this->syncAccessionDonor($request, $id);

        // Auto-Assign to Archivist setting: if enabled, stamp the workflow
        // row's assigned_to with the creating user. Honoured here rather
        // than inside service->create() so the create() path stays
        // re-usable from contexts (CSV import, API) that may want to
        // bypass the per-user setting.
        if ($this->service->autoAssignEnabled() && auth()->id()) {
            $this->service->upsertWorkflow($id, [
                'assigned_to' => auth()->id(),
                'status' => 'draft',
                'priority' => 'normal',
            ]);
        }

        return redirect()
            ->route('accession.show', $slug)
            ->with('success', 'Accession record created successfully.');
    }

    public function update(Request $request, string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (! $accession) {
            abort(404);
        }

        $request->validate([
            'identifier' => 'required|string|max:255|unique:accession,identifier,'.$accession->id,
            'title' => 'nullable|string|max:1024',
            'date' => 'nullable|date',
            'acquisition_type_id' => 'nullable|integer|exists:term,id',
            'processing_priority_id' => 'nullable|integer|exists:term,id',
            'processing_status_id' => 'nullable|integer|exists:term,id',
            'resource_type_id' => 'nullable|integer|exists:term,id',
            'scope_and_content' => 'nullable|string',
            'archival_history' => 'nullable|string',
            'source_of_acquisition' => 'nullable|string',
            'location_information' => 'nullable|string',
            'received_extent_units' => 'nullable|string',
            'physical_characteristics' => 'nullable|string',
            'appraisal' => 'nullable|string',
            'processing_notes' => 'nullable|string',
        ]);

        $data = $request->only([
            'identifier', 'title', 'date',
            'acquisition_type_id', 'processing_priority_id',
            'processing_status_id', 'resource_type_id',
            'scope_and_content', 'archival_history', 'source_of_acquisition',
            'location_information', 'received_extent_units', 'physical_characteristics',
            'appraisal', 'processing_notes',
        ]);

        $this->service->update($accession->id, $data);

        // Persist the "Related donor" modal: link an existing donor, create a
        // new one from the typed name + contact, or refresh the contact/name of
        // the donor already linked. An empty selection unlinks any prior donor.
        $this->syncAccessionDonor($request, $accession->id);

        return redirect()
            ->route('accession.show', $slug)
            ->with('success', 'Accession record updated successfully.');
    }

    /**
     * Resolve the donor selection carried by the accession edit form into a
     * list of existing donor ids. Accepts a numeric donor_id (preferred,
     * written by the modal autocomplete) or a donor_slug (the autocomplete
     * anchor href value). Supports the multi-donor shape donor_ids[] too.
     * Returns [] when nothing is selected (which unlinks all donors).
     *
     * @return int[]
     */
    private function resolveSelectedDonorIds(Request $request): array
    {
        $ids = [];

        foreach ((array) $request->input('donor_ids', []) as $v) {
            if (is_numeric($v) && (int) $v > 0) {
                $ids[] = (int) $v;
            }
        }

        $single = $request->input('donor_id');
        if (is_numeric($single) && (int) $single > 0) {
            $ids[] = (int) $single;
        }

        // Fall back to resolving a slug when no numeric id was supplied.
        if (empty($ids)) {
            $slug = trim((string) $request->input('donor_slug', ''));
            if ($slug !== '') {
                $slug = ltrim($slug, '/');
                $donor = (new \AhgDonorManage\Services\DonorService(app()->getLocale()))->getBySlug($slug);
                if ($donor) {
                    $ids[] = (int) $donor->id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Create a brand-new donor from the "Related donor" modal fields when the
     * user typed a name that does not resolve to an existing donor (no
     * donor_id / donor_slug). The modal lives inside the accession <form>, so
     * its donor_* inputs arrive on the accession save. Returns the new donor
     * id, or null when there is no name to create from. Contact details are
     * optional — saveContacts() skips an all-empty contact row.
     */
    /**
     * Map one donor entry (an element of the donors[] array, or the legacy
     * single donor_* fields) to the contact_information shape DonorService
     * expects.
     *
     * @param  array<string, mixed>  $e
     * @return array<string, mixed>
     */
    private function donorContactFromEntry(array $e): array
    {
        return [
            'primary_contact' => 1,
            'contact_person'  => $e['contact_person'] ?? null,
            'telephone'       => $e['telephone'] ?? null,
            'fax'             => $e['fax'] ?? null,
            'email'           => $e['email'] ?? null,
            'website'         => $e['url'] ?? ($e['website'] ?? null),
            'street_address'  => $e['street_address'] ?? null,
            'region'          => $e['region'] ?? null,
            'country_code'    => $e['country'] ?? ($e['country_code'] ?? null),
            'postal_code'     => $e['postal_code'] ?? null,
            'city'            => $e['city'] ?? null,
            'latitude'        => $e['latitude'] ?? null,
            'longitude'       => $e['longitude'] ?? null,
            'contact_type'    => $e['contact_type'] ?? null,
            'note'            => $e['note'] ?? null,
        ];
    }

    /**
     * Resolve one donor entry to a donor id: link an existing donor (by id or
     * slug) and refresh its name + primary contact when it is already linked
     * to this accession, or create a brand-new donor from a typed name.
     * Returns null when the entry carries neither an existing reference nor a
     * name.
     *
     * @param  array<string, mixed>  $e
     * @param  int[]  $currentlyLinked  donor ids already linked to this accession
     */
    private function resolveDonorEntry(array $e, array $currentlyLinked): ?int
    {
        $svc = new \AhgDonorManage\Services\DonorService(app()->getLocale());

        $id = (isset($e['id']) && is_numeric($e['id']) && (int) $e['id'] > 0) ? (int) $e['id'] : null;
        if (! $id && ! empty($e['slug'])) {
            $donor = $svc->getBySlug(ltrim((string) $e['slug'], '/'));
            $id = $donor ? (int) $donor->id : null;
        }

        if ($id) {
            // Only refresh the name/contact of a donor ALREADY linked to this
            // accession (its details were shown in the modal). A freshly-picked
            // existing donor is just linked, never overwritten.
            if (in_array($id, $currentlyLinked, true)) {
                $data = [];
                $name = trim((string) ($e['name'] ?? ''));
                if ($name !== '') {
                    $data['authorized_form_of_name'] = $name;
                }
                $contact = $this->donorContactFromEntry($e);
                $existingContactId = DB::table('contact_information')
                    ->where('actor_id', $id)
                    ->orderByDesc('primary_contact')->orderBy('id')
                    ->value('id');
                if ($existingContactId) {
                    $contact['id'] = (int) $existingContactId;
                }
                $data['contacts'] = [$contact];
                $svc->update($id, $data);
            }

            return $id;
        }

        $name = trim((string) ($e['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        return $svc->create([
            'authorized_form_of_name' => $name,
            'contacts' => [$this->donorContactFromEntry($e)],
        ]);
    }

    /**
     * Full donor handling for the accession create/save. Processes the
     * donors[] array (multiple related donors): creates new ones, links
     * existing ones, and refreshes the contact of those already linked. Falls
     * back to the legacy single donor_* fields when no donors[] array is
     * posted. An empty result unlinks every donor via syncDonors().
     */
    private function syncAccessionDonor(Request $request, int $accessionId): void
    {
        $entries = $request->input('donors');
        $entries = is_array($entries) ? array_values($entries) : [];

        // Back-compat: the single-donor modal posts donor_* fields flat.
        if (empty($entries)
            && ($request->filled('donor_name') || $request->filled('donor_id') || $request->filled('donor_slug'))) {
            $entries = [[
                'id'             => $request->input('donor_id'),
                'slug'           => $request->input('donor_slug'),
                'name'           => $request->input('donor_name'),
                'contact_person' => $request->input('donor_contact_person'),
                'telephone'      => $request->input('donor_telephone'),
                'fax'            => $request->input('donor_fax'),
                'email'          => $request->input('donor_email'),
                'url'            => $request->input('donor_url'),
                'street_address' => $request->input('donor_street_address'),
                'region'         => $request->input('donor_region'),
                'country'        => $request->input('donor_country'),
                'postal_code'    => $request->input('donor_postal_code'),
                'city'           => $request->input('donor_city'),
                'latitude'       => $request->input('donor_latitude'),
                'longitude'      => $request->input('donor_longitude'),
                'contact_type'   => $request->input('donor_contact_type'),
                'note'           => $request->input('donor_note'),
            ]];
        }

        $currentlyLinked = $this->service->getDonors($accessionId)
            ->pluck('id')->map(fn ($x) => (int) $x)->all();

        $ids = [];
        foreach ($entries as $e) {
            $donorId = $this->resolveDonorEntry((array) $e, $currentlyLinked);
            if ($donorId) {
                $ids[] = $donorId;
            }
        }

        $this->service->syncDonors($accessionId, array_values(array_unique($ids)));
    }

    /**
     * #1267: link an existing donor to this accession (AJAX add-existing
     * action from the "Related donor" modal). Accepts donor_id or donor_slug.
     */
    public function linkDonor(Request $request, string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (! $accession) {
            abort(404);
        }

        $ids = $this->resolveSelectedDonorIds($request);
        if (empty($ids)) {
            return $this->donorActionResponse($request, $slug, false, 'No existing donor selected.');
        }

        $created = false;
        foreach ($ids as $donorId) {
            $created = $this->service->linkDonor($accession->id, $donorId) || $created;
        }

        return $this->donorActionResponse($request, $slug, true,
            $created ? 'Donor linked to accession.' : 'Donor already linked to this accession.');
    }

    /**
     * #1267: unlink a donor from this accession (modal delete-row action).
     */
    public function unlinkDonor(Request $request, string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (! $accession) {
            abort(404);
        }

        $ids = $this->resolveSelectedDonorIds($request);
        $removed = 0;
        foreach ($ids as $donorId) {
            $removed += $this->service->unlinkDonor($accession->id, $donorId);
        }

        return $this->donorActionResponse($request, $slug, true,
            $removed > 0 ? 'Donor unlinked from accession.' : 'No matching donor link to remove.');
    }

    private function donorActionResponse(Request $request, string $slug, bool $ok, string $message)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => $ok, 'message' => $message]);
        }

        return redirect()
            ->route('accession.show', $slug)
            ->with($ok ? 'success' : 'error', $message);
    }

    /**
     * #1267: JSON donor typeahead for the accession edit form's TomSelect.
     * Returns [{id, name, slug}, ...] using DonorService::search().
     */
    public function donorSearch(Request $request)
    {
        $query = (string) $request->input('query', $request->input('q', ''));
        $limit = (int) $request->input('limit', 15);
        $donors = (new \AhgDonorManage\Services\DonorService(app()->getLocale()))->search($query, $limit > 0 ? $limit : 15);

        $out = array_map(function ($d) {
            return [
                'id' => $d['id'],
                'name' => $d['label'],
                'slug' => $d['slug'],
            ];
        }, $donors);

        return response()->json($out);
    }

    /**
     * #1267: resolve an existing donor by slug to {id, name, slug}. Powers
     * the related-donor row template's edit action so the modal can pre-fill
     * the hidden donor_id when editing an already-linked donor.
     */
    public function relatedDonor(string $slug)
    {
        $donor = (new \AhgDonorManage\Services\DonorService(app()->getLocale()))->getBySlug($slug);
        if (! $donor) {
            abort(404);
        }

        return response()->json([
            'id' => (int) $donor->id,
            'name' => $donor->authorized_form_of_name ?? '',
            'slug' => $donor->slug ?? $slug,
        ]);
    }

    public function confirmDelete(string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (! $accession) {
            abort(404);
        }

        $deaccessions = $this->service->getDeaccessions($accession->id);
        foreach ($deaccessions as $d) {
            $d->slug = $this->service->getSlug($d->id) ?? $d->id;
        }
        $accruals = $this->service->getAccruals($accession->id);

        return view('ahg-accession-manage::delete', [
            'accession' => $accession,
            'deaccessions' => $deaccessions,
            'accruals' => $accruals,
        ]);
    }

    public function destroy(Request $request, string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (! $accession) {
            abort(404);
        }

        $this->service->delete($accession->id);

        return redirect()
            ->route('accession.browse')
            ->with('success', 'Accession record deleted successfully.');
    }

    // ── Appraisal & Valuation ──────────────────────────────────────

    public function appraisal(int $id)
    {
        $accession = DB::table('accession')
            ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
            ->leftJoin('slug', 'accession.id', '=', 'slug.object_id')
            ->where('accession.id', $id)
            ->where('accession_i18n.culture', app()->getLocale())
            ->select('accession.*', 'accession_i18n.title', 'slug.slug')
            ->first();
        if (! $accession) {
            abort(404);
        }
        $currentAppraisal = null;

        return view('ahg-accession-manage::appraisal', compact('accession', 'currentAppraisal'));
    }

    public function appraisalStore(Request $request, int $id)
    {
        return redirect()->route('accession.appraisal', $id)->with('success', 'Appraisal saved.');
    }

    public function appraisalTemplates()
    {
        $templates = collect();

        return view('ahg-accession-manage::appraisal-templates', compact('templates'));
    }

    public function valuation(int $id)
    {
        $accession = DB::table('accession')
            ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
            ->leftJoin('slug', 'accession.id', '=', 'slug.object_id')
            ->where('accession.id', $id)
            ->where('accession_i18n.culture', app()->getLocale())
            ->select('accession.*', 'accession_i18n.title', 'slug.slug')
            ->first();
        if (! $accession) {
            abort(404);
        }
        $currentValuation = null;
        $valuations = collect();

        return view('ahg-accession-manage::valuation', compact('accession', 'currentValuation', 'valuations'));
    }

    // ── Containers & Rights ────────────────────────────────────────

    public function containers(int $id)
    {
        $accession = DB::table('accession')
            ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
            ->leftJoin('slug', 'accession.id', '=', 'slug.object_id')
            ->where('accession.id', $id)
            ->where('accession_i18n.culture', app()->getLocale())
            ->select('accession.*', 'accession_i18n.title', 'slug.slug')
            ->first();
        if (! $accession) {
            abort(404);
        }
        $containers = collect();

        return view('ahg-accession-manage::containers', compact('accession', 'containers'));
    }

    public function rights(int $id)
    {
        $accession = DB::table('accession')
            ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
            ->leftJoin('slug', 'accession.id', '=', 'slug.object_id')
            ->where('accession.id', $id)
            ->where('accession_i18n.culture', app()->getLocale())
            ->select('accession.*', 'accession_i18n.title', 'slug.slug')
            ->first();
        if (! $accession) {
            abort(404);
        }
        $rights = collect();

        return view('ahg-accession-manage::rights', compact('accession', 'rights'));
    }

    // ── Intake Workflow ────────────────────────────────────────────

    public function attachments(int $id)
    {
        $accession = DB::table('accession')
            ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
            ->leftJoin('slug', 'accession.id', '=', 'slug.object_id')
            ->where('accession.id', $id)
            ->where('accession_i18n.culture', app()->getLocale())
            ->select('accession.*', 'accession_i18n.title', 'slug.slug')
            ->first();
        if (! $accession) {
            abort(404);
        }
        $attachments = collect();

        return view('ahg-accession-manage::attachments', compact('accession', 'attachments'));
    }

    public function attachmentsStore(Request $request, int $id)
    {
        return redirect()->route('accession.attachments', $id)->with('success', 'Attachment uploaded.');
    }

    public function checklist(int $id)
    {
        $accession = DB::table('accession')
            ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
            ->leftJoin('slug', 'accession.id', '=', 'slug.object_id')
            ->where('accession.id', $id)
            ->where('accession_i18n.culture', app()->getLocale())
            ->select('accession.*', 'accession_i18n.title', 'slug.slug')
            ->first();
        if (! $accession) {
            abort(404);
        }
        $checklistItems = collect();

        return view('ahg-accession-manage::checklist', compact('accession', 'checklistItems'));
    }

    public function checklistStore(Request $request, int $id)
    {
        return redirect()->route('accession.checklist', $id)->with('success', 'Checklist saved.');
    }

    public function intakeConfig()
    {
        $config = (object) ['default_status_id' => null, 'default_priority_id' => null, 'prefix' => '', 'next_number' => 1];
        $statuses = collect();
        $priorities = collect();

        return view('ahg-accession-manage::intake-config', compact('config', 'statuses', 'priorities'));
    }

    public function intakeConfigStore(Request $request)
    {
        return redirect()->route('accession.intake-config')->with('success', 'Configuration saved.');
    }

    public function numbering()
    {
        $numbering = (object) ['format' => 'yyyy-nnn', 'prefix' => '', 'next_number' => 1];

        return view('ahg-accession-manage::numbering', compact('numbering'));
    }

    public function numberingStore(Request $request)
    {
        return redirect()->route('accession.numbering')->with('success', 'Numbering scheme saved.');
    }

    public function queue(Request $request)
    {
        $rows = collect();

        return view('ahg-accession-manage::queue', compact('rows'));
    }

    public function queueDetail(int $id)
    {
        $accession = DB::table('accession')
            ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
            ->leftJoin('slug', 'accession.id', '=', 'slug.object_id')
            ->where('accession.id', $id)
            ->where('accession_i18n.culture', app()->getLocale())
            ->select('accession.*', 'accession_i18n.title', 'slug.slug')
            ->first();
        if (! $accession) {
            abort(404);
        }

        return view('ahg-accession-manage::queue-detail', compact('accession'));
    }

    public function timeline(int $id)
    {
        $accession = DB::table('accession')
            ->join('accession_i18n', 'accession.id', '=', 'accession_i18n.id')
            ->leftJoin('slug', 'accession.id', '=', 'slug.object_id')
            ->where('accession.id', $id)
            ->where('accession_i18n.culture', app()->getLocale())
            ->select('accession.*', 'accession_i18n.title', 'slug.slug')
            ->first();
        if (! $accession) {
            abort(404);
        }
        $events = collect();

        return view('ahg-accession-manage::timeline', compact('accession', 'events'));
    }
}
