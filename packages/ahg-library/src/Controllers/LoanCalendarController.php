<?php

/**
 * LoanCalendarController - loan-rule editing and the circulation calendar (#1475)
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers;

use AhgCore\Services\AhgSettingsService;
use AhgLibrary\Http\Requests\LoanRuleRequest;
use AhgLibrary\Services\LibraryCalendarService;
use AhgLibrary\Services\LibraryCirculationService;
use AhgLibrary\Support\LibraryBranch;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanCalendarController extends Controller
{
    public function __construct(
        private LibraryCalendarService $calendar,
        private LibraryCirculationService $circ
    ) {
    }

    public function editRule(int $id)
    {
        $rule = DB::table('library_loan_rule')->where('id', $id)->first();
        abort_unless($rule, 404);
        abort_unless(LibraryCalendarService::operatorMayManage((int) ($rule->branch_id ?? 0)), 403);

        $branchLabels = LibraryBranch::available() ? LibraryBranch::options() : [];
        $ruleBranch = (int) ($rule->branch_id ?? LibraryBranch::ALL);
        if (LibraryBranch::available() && $ruleBranch !== LibraryBranch::ALL) {
            // branch_id has no foreign key, so the rule's branch may be gone
            // from the list. It must stay selectable or a save would widen the
            // rule to all branches.
            $branchLabels += [$ruleBranch => LibraryBranch::ruleLabel($ruleBranch)];
        }

        return view('ahg-library::circulation.loan-rule-edit', [
            'rule' => $rule,
            'branchAware' => LibraryBranch::available(),
            'branchLabels' => $branchLabels,
            'materialOptions' => LoanRuleRequest::materialOptions(),
            'patronOptions' => LoanRuleRequest::patronOptions(),
        ]);
    }

    public function updateRule(LoanRuleRequest $request, int $id)
    {
        abort_unless(DB::table('library_loan_rule')->where('id', $id)->exists(), 404);

        $this->circ->updateLoanRule($id, $request->validated());

        return redirect()->route('library.loan-rules')->with('success', __('Loan rule saved.'));
    }

    public function calendar(Request $request)
    {
        return $this->calendarView($this->branchFrom($request));
    }

    public function saveHours(Request $request)
    {
        $branch = $this->managedBranch($request);

        $data = $request->validate([
            'days' => 'required|array',
            'days.*.is_open' => 'nullable|boolean',
            'days.*.opens_at' => 'nullable|date_format:H:i',
            'days.*.closes_at' => 'nullable|date_format:H:i',
        ]);

        $days = [];
        $errors = [];
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $day = $data['days'][$weekday] ?? [];
            $days[$weekday] = $day;
            if (empty($day['is_open'])) {
                continue;
            }
            $opens = $day['opens_at'] ?? null;
            $closes = $day['closes_at'] ?? null;
            if ((bool) $opens !== (bool) $closes || ($opens && $opens >= $closes)) {
                $errors["days.{$weekday}.closes_at"] = __('Give both times, with closing after opening, or leave both blank.');
            }
        }
        if (! array_filter($days, fn ($d) => ! empty($d['is_open']))) {
            $errors['days'] = __('At least one day of the week must be open, or no due date could be set.');
        }
        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }

        $this->calendar->saveWeek($branch, $days);

        return redirect()->route('library.calendar', ['branch' => $branch])->with('success', __('Opening hours saved.'));
    }

    public function resetHours(Request $request)
    {
        $branch = $this->managedBranch($request);
        abort_if($branch === LibraryBranch::ALL, 422);

        $this->calendar->resetWeek($branch);

        return redirect()->route('library.calendar', ['branch' => $branch])
            ->with('success', __('This branch now follows the all-branches hours.'));
    }

    public function storeClosure(Request $request)
    {
        $branch = $this->managedBranch($request);
        $data = $this->validateClosure($request);
        $data['branch_id'] = $branch;

        $this->calendar->saveClosure($data, null, Auth::id() !== null ? (int) Auth::id() : null);

        return redirect()->route('library.calendar', ['branch' => $branch])->with('success', __('Closure saved.'));
    }

    public function editClosure(int $id)
    {
        $closure = $this->managedClosure($id);

        return $this->calendarView((int) $closure->branch_id, $closure);
    }

    public function updateClosure(Request $request, int $id)
    {
        $this->managedClosure($id);
        $branch = $this->managedBranch($request);
        $data = $this->validateClosure($request);
        $data['branch_id'] = $branch;

        $this->calendar->saveClosure($data, $id);

        return redirect()->route('library.calendar', ['branch' => $branch])->with('success', __('Closure saved.'));
    }

    public function deleteClosure(int $id)
    {
        $closure = $this->managedClosure($id);
        $this->calendar->deleteClosure($id);

        return redirect()->route('library.calendar', ['branch' => (int) $closure->branch_id])
            ->with('success', __('Closure deleted.'));
    }

    private function calendarView(int $branch, ?object $editing = null)
    {
        return view('ahg-library::circulation.calendar', [
            'ready' => $this->calendar->tablesReady(),
            'branch' => $branch,
            'branchAware' => LibraryBranch::available(),
            'branchLabels' => LibraryBranch::available() ? LibraryBranch::options() : [],
            'week' => $this->calendar->week($branch),
            'closures' => $this->calendar->closures($branch),
            'closureTypes' => AhgSettingsService::getDropdownChoices('library_closure_type', false),
            'editing' => $editing,
            'canManage' => LibraryCalendarService::operatorMayManage($branch),
        ]);
    }

    private function validateClosure(Request $request): array
    {
        $types = array_keys(AhgSettingsService::getDropdownChoices('library_closure_type', false));

        $data = $request->validate([
            'closure_type' => ['required', 'string', 'max:50', \Illuminate\Validation\Rule::in($types)],
            'name' => 'required|string|max:150',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'recurs_annually' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        $start = $data['start_date'];
        $end = ($data['end_date'] ?? null) ?: $start;

        if (! empty($data['recurs_annually'])) {
            if (substr($start, 5) === '02-29' || substr($end, 5) === '02-29') {
                throw ValidationException::withMessages(['start_date' => __('A yearly closure cannot start or end on 29 February.')]);
            }
            if (strtotime($end) >= strtotime($start . ' +1 year')) {
                throw ValidationException::withMessages(['end_date' => __('A yearly closure must be shorter than one year.')]);
            }
        }

        return $data;
    }

    private function branchFrom(Request $request): int
    {
        $branch = (int) $request->input('branch', $request->input('branch_id', LibraryBranch::ALL));

        if ($branch > 0 && ! array_key_exists($branch, LibraryBranch::options())) {
            abort(404);
        }

        return max($branch, LibraryBranch::ALL);
    }

    private function managedBranch(Request $request): int
    {
        $branch = $this->branchFrom($request);
        abort_unless($this->calendar->tablesReady(), 503);
        abort_unless(LibraryCalendarService::operatorMayManage($branch), 403);

        return $branch;
    }

    private function managedClosure(int $id): object
    {
        $closure = $this->calendar->findClosure($id);
        abort_unless($closure, 404);
        abort_unless(LibraryCalendarService::operatorMayManage((int) $closure->branch_id), 403);

        return $closure;
    }
}
