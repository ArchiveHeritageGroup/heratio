<?php

namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use AhgInformationObjectManage\Services\ConditionService;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgConditionPlugin/
 */
class ConditionController extends Controller
{
    private ConditionService $service;

    public function __construct(ConditionService $service)
    {
        $this->service = $service;
    }

    /**
     * List condition reports + latest report + SPECTRUM checks for an IO.
     */
    public function index(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $reports  = $this->service->getReportsForObject($io->id);
        $latest   = $this->service->getLatestReport($io->id);
        $spectrum = $this->service->getSpectrumChecks($io->id);

        // Merge condition_report + spectrum into a unified "checks" collection
        // so the existing view (which iterates $checks) keeps working.
        $checks = collect();

        foreach ($reports as $report) {
            $checks->push((object) [
                'id'               => $report->id,
                'check_date'       => $report->assessment_date,
                'condition_rating' => $report->overall_rating,
                'check_type'       => $report->context,
                'assessor'         => $this->resolveAssessor($report->assessor_user_id),
                'notes'            => $report->summary,
                'source'           => 'condition_report',
            ]);
        }

        foreach ($spectrum as $sc) {
            $checks->push((object) [
                'id'               => $sc->id,
                'check_date'       => $sc->check_date,
                'condition_rating' => $sc->overall_condition ?? $sc->condition_rating ?? null,
                'check_type'       => $sc->check_reason ?? 'spectrum',
                'assessor'         => $sc->checked_by,
                'notes'            => $sc->condition_note ?? $sc->condition_notes ?? null,
                'source'           => 'spectrum',
            ]);
        }

        // Sort combined by date descending
        $checks = $checks->sortByDesc('check_date')->values();

        return view('ahg-io-manage::condition.index', [
            'io'       => $io,
            'checks'   => $checks,
            'latest'   => $latest,
            'spectrum' => $spectrum,
        ]);
    }

    /**
     * Show the create-report form with dropdown options.
     */
    public function create(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        return view('ahg-io-manage::condition.create', [
            'io'              => $io,
            'ratingOptions'   => $this->service->getRatingOptions(),
            'contextOptions'  => $this->service->getContextOptions(),
            'priorityOptions' => $this->service->getPriorityOptions(),
            'damageTypes'     => $this->service->getDamageTypeOptions(),
            'severityOptions' => $this->service->getSeverityOptions(),
        ]);
    }

    /**
     * Validate and store a new condition report.
     */
    public function store(Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $validated = $request->validate([
            'assessment_date'     => 'required|date',
            'overall_rating'      => 'required|string|max:47',
            'context'             => 'nullable|string|max:121',
            'summary'             => 'nullable|string',
            'recommendations'     => 'nullable|string',
            'priority'            => 'nullable|string|max:32',
            'next_check_date'     => 'nullable|date',
            'environmental_notes' => 'nullable|string',
            'handling_notes'      => 'nullable|string',
            'display_notes'       => 'nullable|string',
            'storage_notes'       => 'nullable|string',
        ]);

        $validated['information_object_id'] = $io->id;
        $validated['assessor_user_id'] = auth()->id();

        $reportId = $this->service->createReport($validated);

        // Process inline damages if submitted
        $damageTypes = $request->input('damage_type', []);
        foreach ($damageTypes as $i => $type) {
            if (empty($type)) {
                continue;
            }
            $this->service->addDamage($reportId, [
                'damage_type'       => $type,
                'location'          => $request->input('damage_location.' . $i, 'overall'),
                'severity'          => $request->input('damage_severity.' . $i, 'minor'),
                'description'       => $request->input('damage_description.' . $i),
                'dimensions'        => $request->input('damage_dimensions.' . $i),
                'is_active'         => $request->has('damage_is_active.' . $i) ? 1 : 1,
                'treatment_required' => $request->has('damage_treatment_required.' . $i) ? 1 : 0,
                'treatment_notes'   => $request->input('damage_treatment_notes.' . $i),
            ]);
        }

        return redirect()
            ->route('io.condition', $slug)
            ->with('success', 'Condition report created successfully.');
    }

    /**
     * Show a single condition report with its damages.
     */
    public function show(int $id)
    {
        $report = $this->service->getReport($id);
        if (!$report) {
            abort(404);
        }

        // Resolve the IO for breadcrumb
        $io = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) {
                $j->on('i18n.id', '=', 'io.id')
                  ->where('i18n.culture', app()->getLocale());
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $report->information_object_id)
            ->select('io.id', 'i18n.title', 's.slug')
            ->first();

        return view('ahg-io-manage::condition.show', [
            'io'     => $io,
            'report' => $report,
        ]);
    }

    /**
     * Resolve assessor user ID to a display name.
     */
    private function resolveAssessor(?int $userId): string
    {
        if (!$userId) {
            return '—';
        }

        $user = DB::table('user')
            ->where('id', $userId)
            ->select('username')
            ->first();

        return $user->username ?? '—';
    }

    /**
     * Look up an information object by slug.
     */
    private function getIO(string $slug): ?object
    {
        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('s.slug', $slug)
            ->select('io.id', 'i18n.title', 's.slug')
            ->first();
    }
}
