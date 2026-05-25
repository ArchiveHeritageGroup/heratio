<?php

/**
 * RicOccupationController — admin CRUD for RiC-O Occupation entities.
 *
 * Phase 1 of issue #660: lightweight create/update/delete UI for rico:Occupation
 * records linked to an Actor.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 */

namespace AhgRic\Http\Controllers;

use AhgRic\Models\RicOccupation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RicOccupationController extends Controller
{
    /**
     * List all occupations.
     */
    public function index(Request $request): View
    {
        $this->ensureTable();

        $q = trim((string) $request->query('q', ''));

        $query = RicOccupation::query()
            ->leftJoin('actor', 'ric_occupation.actor_id', '=', 'actor.id')
            ->leftJoin('actor_i18n', function ($j) {
                $j->on('actor_i18n.id', '=', 'actor.id')
                  ->where('actor_i18n.culture', '=', app()->getLocale());
            })
            ->select([
                'ric_occupation.*',
                'actor.slug as actor_slug',
                'actor_i18n.authorized_form_of_name as actor_name',
            ])
            ->orderBy('ric_occupation.is_current', 'desc')
            ->orderBy('ric_occupation.start_date', 'desc');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('ric_occupation.title', 'like', "%{$q}%")
                  ->orWhere('actor_i18n.authorized_form_of_name', 'like', "%{$q}%");
            });
        }

        $occupations = $query->paginate(25)->appends(['q' => $q]);

        return view('ahg-ric::occupations.index', [
            'occupations' => $occupations,
            'q' => $q,
        ]);
    }

    /**
     * Show the create form.
     */
    public function create(): View
    {
        $this->ensureTable();

        return view('ahg-ric::occupations.form', [
            'occupation' => new RicOccupation(),
            'actors' => $this->actorChoices(),
            'mode' => 'create',
        ]);
    }

    /**
     * Persist a new occupation.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureTable();

        $data = $this->validateInput($request);

        RicOccupation::create($data);

        return redirect()
            ->route('ric.occupations.index')
            ->with('success', __('Occupation created.'));
    }

    /**
     * Show the edit form.
     */
    public function edit(int $id): View
    {
        $this->ensureTable();

        $occupation = RicOccupation::findOrFail($id);

        return view('ahg-ric::occupations.form', [
            'occupation' => $occupation,
            'actors' => $this->actorChoices(),
            'mode' => 'edit',
        ]);
    }

    /**
     * Update an existing occupation.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->ensureTable();

        $occupation = RicOccupation::findOrFail($id);
        $data = $this->validateInput($request);

        $occupation->update($data);

        return redirect()
            ->route('ric.occupations.index')
            ->with('success', __('Occupation updated.'));
    }

    /**
     * Delete an occupation.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->ensureTable();

        $occupation = RicOccupation::findOrFail($id);
        $occupation->delete();

        return redirect()
            ->route('ric.occupations.index')
            ->with('success', __('Occupation deleted.'));
    }

    /**
     * Common input validation for store + update.
     *
     * @return array<string,mixed>
     */
    protected function validateInput(Request $request): array
    {
        $data = $request->validate([
            'actor_id' => ['required', 'integer', 'exists:actor,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_current' => ['nullable', 'boolean'],
            'source_culture' => ['nullable', 'string', 'max:16'],
        ]);

        $data['is_current'] = (bool) ($data['is_current'] ?? false);
        if (empty($data['source_culture'])) {
            $data['source_culture'] = app()->getLocale();
        }

        return $data;
    }

    /**
     * Actor dropdown choices, limited to the current culture.
     *
     * @return array<int, object{id:int, name:string}>
     */
    protected function actorChoices(): array
    {
        $culture = app()->getLocale();

        return DB::table('actor as a')
            ->leftJoin('actor_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'a.id')->where('i18n.culture', '=', $culture);
            })
            ->select([
                'a.id',
                DB::raw("COALESCE(i18n.authorized_form_of_name, CONCAT('Actor #', a.id)) as name"),
            ])
            ->orderBy('name')
            ->limit(2000)
            ->get()
            ->all();
    }

    /**
     * Guard so we surface a friendly 404 when migrations have not been run.
     */
    protected function ensureTable(): void
    {
        if (!Schema::hasTable('ric_occupation')) {
            abort(404, 'The ric_occupation table does not exist yet. Run database migrations.');
        }
    }
}
