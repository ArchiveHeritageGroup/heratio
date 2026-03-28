<?php

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
            'Content-Disposition' => 'attachment; filename="accessions-' . date('Ymd') . '.csv"',
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (!$accession) {
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
        ]);
    }

    public function create()
    {
        $formChoices = $this->service->getFormChoices();

        return view('ahg-accession-manage::edit', [
            'accession' => null,
            'donor' => null,
            'donorContact' => null,
            'formChoices' => $formChoices,
        ]);
    }

    public function edit(string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (!$accession) {
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
        $formChoices = $this->service->getFormChoices();

        return view('ahg-accession-manage::edit', [
            'accession' => $accession,
            'donor' => $donor,
            'donorContact' => $donorContact,
            'formChoices' => $formChoices,
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

        return redirect()
            ->route('accession.show', $slug)
            ->with('success', 'Accession record created successfully.');
    }

    public function update(Request $request, string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (!$accession) {
            abort(404);
        }

        $request->validate([
            'identifier' => 'required|string|max:255|unique:accession,identifier,' . $accession->id,
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

        return redirect()
            ->route('accession.show', $slug)
            ->with('success', 'Accession record updated successfully.');
    }

    public function confirmDelete(string $slug)
    {
        $accession = $this->service->getBySlug($slug);
        if (!$accession) {
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
        if (!$accession) {
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
        if (!$accession) abort(404);
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
        if (!$accession) abort(404);
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
        if (!$accession) abort(404);
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
        if (!$accession) abort(404);
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
        if (!$accession) abort(404);
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
        if (!$accession) abort(404);
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
        if (!$accession) abort(404);
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
        if (!$accession) abort(404);
        $events = collect();
        return view('ahg-accession-manage::timeline', compact('accession', 'events'));
    }
}
