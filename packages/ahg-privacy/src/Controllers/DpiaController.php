<?php

/**
 * DpiaController - admin CRUD + multi-step workflow for GDPR Article 35 DPIAs.
 *
 * Steps: 1 necessity -> 2 risk -> 3 mitigation -> 4 sign-off. Sign-off writes
 * a tamper-evident chain row (#676 Phase 5) through DpiaService::signOff().
 *
 * Issue #669 Phase 1.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. Licensed AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Controllers;

use AhgPrivacy\Models\Dpia;
use AhgPrivacy\Models\ProcessingActivity;
use AhgPrivacy\Services\DpiaService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DpiaController extends Controller
{
    public function __construct(private DpiaService $service)
    {
    }

    public function index(): View
    {
        return view('privacy::dpia-index', [
            'dpias' => $this->service->listAll(),
        ]);
    }

    public function create(): View
    {
        return view('privacy::dpia-form', [
            'dpia'       => new Dpia(),
            'activities' => ProcessingActivity::query()->where('is_active', true)->orderBy('name')->get(),
            'step'       => 1,
            'mode'       => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateInput($request);
        $dpia = $this->service->create($data, optional(Auth::user())->id);
        return redirect()
            ->route('ahgprivacy.dpia.edit', ['id' => $dpia->id, 'step' => 2])
            ->with('status', sprintf('Created DPIA "%s". Continue with the risk assessment.', $dpia->name));
    }

    public function edit(Request $request, int $id): View
    {
        $dpia = $this->service->find($id);
        abort_if($dpia === null, 404);
        $step = (int) $request->query('step', 1);
        $step = max(1, min(4, $step));
        return view('privacy::dpia-form', [
            'dpia'       => $dpia,
            'activities' => ProcessingActivity::query()->where('is_active', true)->orderBy('name')->get(),
            'step'       => $step,
            'mode'       => 'edit',
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $dpia = $this->service->find($id);
        abort_if($dpia === null, 404);
        $data = $this->validateInput($request);
        $this->service->update($dpia, $data);

        $step = (int) $request->input('step', 1);
        if ($step < 4) {
            return redirect()
                ->route('ahgprivacy.dpia.edit', ['id' => $dpia->id, 'step' => $step + 1])
                ->with('status', 'Saved. Continue to the next step.');
        }
        return redirect()
            ->route('ahgprivacy.dpia.edit', ['id' => $dpia->id, 'step' => 4])
            ->with('status', 'Saved. Ready for sign-off.');
    }

    public function moveToReview(int $id): RedirectResponse
    {
        $dpia = $this->service->find($id);
        abort_if($dpia === null, 404);
        $this->service->moveToReview($dpia);
        return redirect()
            ->route('ahgprivacy.dpia.edit', ['id' => $dpia->id, 'step' => 4])
            ->with('status', 'Moved to review.');
    }

    public function signOff(Request $request, int $id): RedirectResponse
    {
        $dpia = $this->service->find($id);
        abort_if($dpia === null, 404);
        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            return back()->with('error', 'Sign-off requires an authenticated user.');
        }
        $note = (string) $request->input('signoff_note', '');
        $this->service->signOff($dpia, $userId, $note !== '' ? $note : null);
        return redirect()
            ->route('ahgprivacy.dpia.index')
            ->with('status', sprintf('Signed off DPIA "%s". Chain entry written.', $dpia->name));
    }

    public function archive(int $id): RedirectResponse
    {
        $dpia = $this->service->find($id);
        abort_if($dpia === null, 404);
        $userId = (int) (Auth::id() ?? 0);
        $this->service->archive($dpia, max(0, $userId));
        return redirect()
            ->route('ahgprivacy.dpia.index')
            ->with('status', sprintf('Archived "%s".', $dpia->name));
    }

    /** @return array<string,mixed> */
    private function validateInput(Request $request): array
    {
        return $request->validate([
            'name'                      => ['required', 'string', 'max:255'],
            'processing_activity_id'    => ['nullable', 'integer'],
            'description'               => ['nullable', 'string'],
            'necessity_proportionality' => ['nullable', 'string'],
            'risks_to_subjects'         => ['nullable', 'string'],
            'measures_to_mitigate'      => ['nullable', 'string'],
            'residual_risks'            => ['nullable', 'string'],
            'dpo_opinion'               => ['nullable', 'string'],
            'dpo_consulted_at'          => ['nullable', 'date'],
            'completed_at'              => ['nullable', 'date'],
            'status'                    => ['nullable', 'string', 'in:' . implode(',', Dpia::statuses())],
        ]);
    }
}
