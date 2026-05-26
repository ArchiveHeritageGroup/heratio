<?php

/**
 * Article30Controller - admin CRUD for the GDPR Article 30 register
 * (ahg_processing_activity) plus regulator-ready download.
 *
 * Issue #669 Phase 1. Routes are mounted at /admin/privacy/article-30 by the
 * AhgPrivacyServiceProvider.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. Licensed AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Controllers;

use AhgPrivacy\Models\ProcessingActivity;
use AhgPrivacy\Services\Article30Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class Article30Controller extends Controller
{
    public function __construct(private Article30Service $service)
    {
    }

    public function index(): View
    {
        return view('privacy::article-30-index', [
            'activities' => $this->service->listAll(),
        ]);
    }

    public function create(): View
    {
        return view('privacy::article-30-form', [
            'activity' => new ProcessingActivity(),
            'mode'     => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateInput($request);
        $activity = $this->service->create($data);
        return redirect()
            ->route('ahgprivacy.article-30.index')
            ->with('status', sprintf('Created processing activity "%s".', $activity->name));
    }

    public function edit(int $id): View
    {
        $activity = $this->service->find($id);
        abort_if($activity === null, 404);
        return view('privacy::article-30-form', [
            'activity' => $activity,
            'mode'     => 'edit',
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $activity = $this->service->find($id);
        abort_if($activity === null, 404);
        $this->service->update($activity, $this->validateInput($request));
        return redirect()
            ->route('ahgprivacy.article-30.index')
            ->with('status', sprintf('Updated processing activity "%s".', $activity->name));
    }

    public function destroy(int $id): RedirectResponse
    {
        $activity = $this->service->find($id);
        abort_if($activity === null, 404);
        $this->service->delete($activity);
        return redirect()
            ->route('ahgprivacy.article-30.index')
            ->with('status', sprintf('Deactivated "%s" (kept in register for audit history).', $activity->name));
    }

    public function export(Request $request): Response
    {
        $format = strtolower((string) $request->query('format', 'json'));
        return match ($format) {
            'csv' => response($this->service->exportCsv(), 200, [
                'Content-Type'        => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="article-30-register.csv"',
            ]),
            'markdown', 'md' => response($this->service->exportMarkdown(), 200, [
                'Content-Type'        => 'text/markdown; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="article-30-register.md"',
            ]),
            default => response($this->service->exportJson(), 200, [
                'Content-Type'        => 'application/json; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="article-30-register.json"',
            ]),
        };
    }

    /** @return array<string,mixed> */
    private function validateInput(Request $request): array
    {
        return $request->validate([
            'name'                   => ['required', 'string', 'max:255'],
            'purpose'                => ['required', 'string'],
            'lawful_basis'           => ['required', 'string', 'in:' . implode(',', ProcessingActivity::LAWFUL_BASES)],
            'categories_of_data'     => ['nullable'],
            'categories_of_subjects' => ['nullable'],
            'recipients'             => ['nullable'],
            'retention_period'       => ['nullable', 'string', 'max:255'],
            'security_measures'      => ['nullable', 'string'],
            'transfers_outside_eea'  => ['nullable', 'boolean'],
            'safeguards'             => ['nullable', 'string'],
            'dpo_contact'            => ['nullable', 'string', 'max:255'],
            'is_active'              => ['nullable', 'boolean'],
        ]);
    }
}
