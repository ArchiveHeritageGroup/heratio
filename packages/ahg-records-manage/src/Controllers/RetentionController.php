<?php

namespace AhgRecordsManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use AhgRecordsManage\Services\RetentionScheduleService;
use AhgRecordsManage\Services\DisposalClassService;

class RetentionController extends Controller
{
    protected RetentionScheduleService $scheduleService;
    protected DisposalClassService $classService;

    public function __construct(RetentionScheduleService $scheduleService, DisposalClassService $classService)
    {
        $this->scheduleService = $scheduleService;
        $this->classService = $classService;
    }

    /**
     * Browse all retention schedules.
     */
    public function schedules(Request $request)
    {
        $filters = [];
        if ($request->filled('status')) {
            $filters['status'] = $request->input('status');
        }
        if ($request->filled('jurisdiction')) {
            $filters['jurisdiction'] = $request->input('jurisdiction');
        }
        if ($request->filled('search')) {
            $filters['search'] = $request->input('search');
        }

        $page = (int) $request->input('page', 1);
        $result = $this->scheduleService->browse($filters, $page);
        $stats = $this->scheduleService->getScheduleStats();
        $jurisdictions = $this->scheduleService->getJurisdictions();

        return view('ahg-records::retention.schedules', [
            'items'         => $result['data'],
            'total'         => $result['total'],
            'page'          => $result['page'],
            'perPage'       => $result['per_page'],
            'stats'         => $stats,
            'jurisdictions' => $jurisdictions,
            'filters'       => $filters,
        ]);
    }

    /**
     * Show create schedule form.
     */
    public function scheduleCreate()
    {
        return view('ahg-records::retention.schedule-create');
    }

    /**
     * Store a new schedule.
     */
    public function scheduleStore(Request $request)
    {
        $validated = $request->validate([
            'schedule_ref'   => 'required|string|max:100|unique:rm_retention_schedule,schedule_ref',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'authority'      => 'nullable|string|max:255',
            'jurisdiction'   => 'nullable|string|max:100',
            'effective_date' => 'nullable|date',
            'review_date'    => 'nullable|date',
            'expiry_date'    => 'nullable|date',
        ]);

        $validated['created_by'] = auth()->id();

        $id = $this->scheduleService->create($validated);

        return redirect()->route('records.schedules.show', $id)
            ->with('success', 'Retention schedule created successfully.');
    }

    /**
     * Show a single schedule with its disposal classes.
     */
    public function scheduleShow(int $id)
    {
        $schedule = $this->scheduleService->getById($id);
        if (!$schedule) {
            abort(404, 'Retention schedule not found.');
        }

        $classes = $this->classService->getBySchedule($id);

        return view('ahg-records::retention.schedule-show', [
            'schedule' => $schedule,
            'classes'  => $classes,
        ]);
    }

    /**
     * Show edit schedule form.
     */
    public function scheduleEdit(int $id)
    {
        $schedule = $this->scheduleService->getById($id);
        if (!$schedule) {
            abort(404, 'Retention schedule not found.');
        }

        return view('ahg-records::retention.schedule-edit', [
            'schedule' => $schedule,
        ]);
    }

    /**
     * Update a schedule.
     */
    public function scheduleUpdate(Request $request, int $id)
    {
        $schedule = $this->scheduleService->getById($id);
        if (!$schedule) {
            abort(404, 'Retention schedule not found.');
        }

        $validated = $request->validate([
            'schedule_ref'   => 'required|string|max:100|unique:rm_retention_schedule,schedule_ref,' . $id,
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'authority'      => 'nullable|string|max:255',
            'jurisdiction'   => 'nullable|string|max:100',
            'effective_date' => 'nullable|date',
            'review_date'    => 'nullable|date',
            'expiry_date'    => 'nullable|date',
        ]);

        $this->scheduleService->update($id, $validated);

        return redirect()->route('records.schedules.show', $id)
            ->with('success', 'Retention schedule updated successfully.');
    }

    /**
     * Approve a draft schedule.
     */
    public function scheduleApprove(int $id)
    {
        $schedule = $this->scheduleService->getById($id);
        if (!$schedule) {
            abort(404, 'Retention schedule not found.');
        }

        $approvedBy = auth()->user()->username ?? auth()->user()->email ?? (string) auth()->id();

        $result = $this->scheduleService->approve($id, $approvedBy);

        if ($result) {
            return redirect()->route('records.schedules.show', $id)
                ->with('success', 'Schedule approved and activated.');
        }

        return redirect()->route('records.schedules.show', $id)
            ->with('error', 'Schedule could not be approved. Only draft schedules can be approved.');
    }

    /**
     * Show create disposal class form.
     */
    public function classCreate(int $id)
    {
        $schedule = $this->scheduleService->getById($id);
        if (!$schedule) {
            abort(404, 'Retention schedule not found.');
        }

        return view('ahg-records::retention.class-create', [
            'schedule' => $schedule,
        ]);
    }

    /**
     * Store a new disposal class.
     */
    public function classStore(Request $request, int $id)
    {
        $schedule = $this->scheduleService->getById($id);
        if (!$schedule) {
            abort(404, 'Retention schedule not found.');
        }

        $validated = $request->validate([
            'class_ref'                      => 'required|string|max:100',
            'title'                          => 'required|string|max:255',
            'description'                    => 'nullable|string',
            'retention_period_years'         => 'nullable|integer|min:0',
            'retention_period_months'        => 'nullable|integer|min:0|max:11',
            'retention_trigger'              => 'required|string|max:50',
            'disposal_action'                => 'required|string|max:30',
            'disposal_confirmation_required' => 'nullable|boolean',
            'review_required'                => 'nullable|boolean',
            'citation'                       => 'nullable|string',
            'sort_order'                     => 'nullable|integer|min:0',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['disposal_confirmation_required'] = $request->has('disposal_confirmation_required') ? 1 : 0;
        $validated['review_required'] = $request->has('review_required') ? 1 : 0;

        $this->classService->create($id, $validated);

        return redirect()->route('records.schedules.show', $id)
            ->with('success', 'Disposal class created successfully.');
    }

    /**
     * Show edit disposal class form.
     */
    public function classEdit(int $id, int $classId)
    {
        $schedule = $this->scheduleService->getById($id);
        if (!$schedule) {
            abort(404, 'Retention schedule not found.');
        }

        $class = $this->classService->getById($classId);
        if (!$class || (int) $class->retention_schedule_id !== $id) {
            abort(404, 'Disposal class not found.');
        }

        return view('ahg-records::retention.class-edit', [
            'schedule' => $schedule,
            'class'    => $class,
        ]);
    }

    /**
     * Update a disposal class.
     */
    public function classUpdate(Request $request, int $id, int $classId)
    {
        $schedule = $this->scheduleService->getById($id);
        if (!$schedule) {
            abort(404, 'Retention schedule not found.');
        }

        $class = $this->classService->getById($classId);
        if (!$class || (int) $class->retention_schedule_id !== $id) {
            abort(404, 'Disposal class not found.');
        }

        $validated = $request->validate([
            'class_ref'                      => 'required|string|max:100',
            'title'                          => 'required|string|max:255',
            'description'                    => 'nullable|string',
            'retention_period_years'         => 'nullable|integer|min:0',
            'retention_period_months'        => 'nullable|integer|min:0|max:11',
            'retention_trigger'              => 'required|string|max:50',
            'disposal_action'                => 'required|string|max:30',
            'disposal_confirmation_required' => 'nullable|boolean',
            'review_required'                => 'nullable|boolean',
            'citation'                       => 'nullable|string',
            'is_active'                      => 'nullable|boolean',
            'sort_order'                     => 'nullable|integer|min:0',
        ]);

        $validated['disposal_confirmation_required'] = $request->has('disposal_confirmation_required') ? 1 : 0;
        $validated['review_required'] = $request->has('review_required') ? 1 : 0;
        $validated['is_active'] = $request->has('is_active') ? 1 : 0;

        $this->classService->update($classId, $validated);

        return redirect()->route('records.schedules.show', $id)
            ->with('success', 'Disposal class updated successfully.');
    }

    /**
     * Delete a disposal class.
     */
    public function classDelete(int $id, int $classId)
    {
        $schedule = $this->scheduleService->getById($id);
        if (!$schedule) {
            abort(404, 'Retention schedule not found.');
        }

        $result = $this->classService->delete($classId);

        if ($result) {
            return redirect()->route('records.schedules.show', $id)
                ->with('success', 'Disposal class deleted.');
        }

        return redirect()->route('records.schedules.show', $id)
            ->with('error', 'Cannot delete disposal class — records are still assigned to it.');
    }

    /**
     * Assign a disposal class to an information object.
     */
    public function assignClass(Request $request)
    {
        $validated = $request->validate([
            'information_object_id' => 'required|integer',
            'disposal_class_id'     => 'required|integer',
            'retention_start_date'  => 'nullable|date',
        ]);

        $this->classService->assignToRecord(
            $validated['information_object_id'],
            $validated['disposal_class_id'],
            auth()->id(),
            $validated['retention_start_date'] ?? null
        );

        return redirect()->route('records.record-class', $validated['information_object_id'])
            ->with('success', 'Disposal class assigned to record.');
    }

    /**
     * Show assigned disposal class for an IO.
     */
    public function recordClass(int $ioId)
    {
        $assignment = $this->classService->getAssignmentForRecord($ioId);
        $activeClasses = $this->classService->getActiveClasses();

        $culture = app()->getLocale();
        $ioTitle = \Illuminate\Support\Facades\DB::table('information_object_i18n')
            ->where('id', $ioId)
            ->where('culture', $culture)
            ->value('title') ?? 'Unknown';

        return view('ahg-records::retention.assign-class', [
            'ioId'          => $ioId,
            'ioTitle'       => $ioTitle,
            'assignment'    => $assignment,
            'activeClasses' => $activeClasses,
        ]);
    }
}
