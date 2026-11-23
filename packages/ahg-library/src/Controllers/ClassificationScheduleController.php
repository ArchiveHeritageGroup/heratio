<?php

namespace AhgLibrary\Controllers;

use AhgCore\Services\AhgSettingsService;
use AhgLibrary\Services\ClassificationScheduleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Classification schedule management and the cataloguing-form lookups (#1475 item 6). */
class ClassificationScheduleController extends Controller
{
    public function __construct(private ClassificationScheduleService $service)
    {
    }

    public function index()
    {
        return view('ahg-library::classification.index', [
            'schedules' => $this->service->schedules(),
            'schemes' => AhgSettingsService::getDropdownChoices('library_classification_scheme', false),
            'modes' => AhgSettingsService::getDropdownChoices('library_classification_validation', false),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $schedule = $this->service->schedule($id) ?? abort(404);
        $request->validate(['parent' => 'nullable|integer', 'q' => 'nullable|string|max:255']);
        $parent = $request->filled('parent') ? $this->entryIn($id, (int) $request->query('parent')) : null;
        $q = trim((string) $request->query('q'));

        return view('ahg-library::classification.show', [
            'schedule' => $schedule,
            'parent' => $parent,
            'trail' => $parent ? $this->service->path($parent) : [],
            'q' => $q,
            'entries' => $q !== '' ? $this->service->search($id, $q) : $this->service->children($id, $parent?->id),
            'schemes' => AhgSettingsService::getDropdownChoices('library_classification_scheme', false),
            'modes' => AhgSettingsService::getDropdownChoices('library_classification_validation', false),
        ]);
    }

    public function store(Request $request)
    {
        $id = $this->service->saveSchedule($this->validateSchedule($request));

        return redirect()->route('library.classification-show', $id)->with('success', __('Schedule created.'));
    }

    public function update(Request $request, int $id)
    {
        $this->service->schedule($id) ?? abort(404);
        $this->service->saveSchedule($this->validateSchedule($request, $id), $id);

        return redirect()->route('library.classification-show', $id)->with('success', __('Schedule updated.'));
    }

    public function destroy(int $id)
    {
        $this->service->schedule($id) ?? abort(404);
        $this->service->deleteSchedule($id);

        return redirect()->route('library.classification-index')->with('success', __('Schedule deleted.'));
    }

    public function import(Request $request, int $id)
    {
        $this->service->schedule($id) ?? abort(404);
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:20480']);
        $result = $this->service->importCsv($id, $request->file('file')->getRealPath());
        if ($result['errors']) {
            return back()->withErrors(['file' => $result['errors']]);
        }

        return back()->with('success', __(':created entries added, :updated updated.', [
            'created' => $result['created'], 'updated' => $result['updated'],
        ]));
    }

    public function storeEntry(Request $request, int $id)
    {
        $this->service->schedule($id) ?? abort(404);
        $data = $this->validateEntry($request);
        try {
            $this->service->saveEntry($id, $data);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['notation' => $e->getMessage()]);
        }

        return back()->with('success', __('Entry saved.'));
    }

    public function updateEntry(Request $request, int $entryId)
    {
        $entry = $this->service->entry($entryId) ?? abort(404);
        $data = $this->validateEntry($request);
        try {
            $this->service->saveEntry((int) $entry->schedule_id, $data, $entryId);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['notation' => $e->getMessage()]);
        }

        return back()->with('success', __('Entry saved.'));
    }

    public function destroyEntry(int $entryId)
    {
        $this->service->entry($entryId) ?? abort(404);
        try {
            $this->service->deleteEntry($entryId);
        } catch (RuntimeException $e) {
            return back()->withErrors(['notation' => $e->getMessage()]);
        }

        return back()->with('success', __('Entry deleted.'));
    }

    /** Children of a node (or search hits) in the scheme's active schedule, for the form picker. */
    public function lookup(Request $request)
    {
        $data = $request->validate([
            'scheme' => 'required|string|max:50',
            'parent' => 'nullable|integer',
            'q' => 'nullable|string|max:255',
        ]);
        $schedule = $this->service->activeSchedule($data['scheme']);
        if (! $schedule) {
            return response()->json(['schedule' => null, 'entries' => [], 'trail' => []]);
        }
        $parent = ! empty($data['parent']) ? $this->entryIn((int) $schedule->id, (int) $data['parent']) : null;
        $q = trim((string) ($data['q'] ?? ''));
        $entries = $q !== '' ? $this->service->search((int) $schedule->id, $q) : $this->service->children((int) $schedule->id, $parent?->id);

        return response()->json([
            'schedule' => ['id' => $schedule->id, 'name' => $schedule->name, 'edition' => $schedule->edition],
            'trail' => array_map(fn ($e) => ['id' => $e->id, 'notation' => $e->notation, 'caption' => $e->caption],
                $parent ? $this->service->path($parent) : []),
            'entries' => $entries->map(fn ($e) => [
                'id' => $e->id, 'notation' => $e->notation, 'notation_end' => $e->notation_end,
                'caption' => $e->caption, 'children' => (int) $e->child_count,
            ])->values(),
        ]);
    }

    public function check(Request $request)
    {
        $data = $request->validate([
            'scheme' => 'nullable|string|max:50',
            'number' => 'nullable|string|max:255',
        ]);
        $r = $this->service->check($data['scheme'] ?? null, $data['number'] ?? null);

        return response()->json([
            'status' => $r['status'],
            'schedule' => $r['schedule']->name ?? null,
            'mode' => $r['schedule']->validation_mode ?? null,
            'notation' => $r['entry']->notation ?? null,
            'caption' => $r['entry']->caption ?? null,
            'path' => $r['path'],
        ]);
    }

    private function entryIn(int $scheduleId, int $entryId): object
    {
        $entry = $this->service->entry($entryId);
        abort_unless($entry && (int) $entry->schedule_id === $scheduleId, 404);

        return $entry;
    }

    private function validateSchedule(Request $request, ?int $id = null): array
    {
        $schemes = array_keys(AhgSettingsService::getDropdownChoices('library_classification_scheme', false));
        $modes = array_keys(AhgSettingsService::getDropdownChoices('library_classification_validation', false));

        return $request->validate([
            'scheme' => ['required', 'string', Rule::in($schemes)],
            'name' => ['required', 'string', 'max:255',
                Rule::unique('library_classification_schedule', 'name')->where('scheme', $request->input('scheme'))->ignore($id)],
            'edition' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:5000',
            'validation_mode' => ['required', 'string', Rule::in($modes)],
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function validateEntry(Request $request): array
    {
        return $request->validate([
            'notation' => 'required|string|max:100',
            'notation_end' => 'nullable|string|max:100',
            'caption' => 'required|string|max:500',
            'note' => 'nullable|string|max:5000',
            'parent_id' => 'nullable|integer',
        ]);
    }
}
