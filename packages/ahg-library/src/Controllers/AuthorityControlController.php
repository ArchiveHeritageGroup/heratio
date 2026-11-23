<?php

/**
 * AuthorityControlController - Subject authority CRUD for Heratio.
 *
 * Routes:
 *   GET  /library-manage/authority               → index
 *   GET  /library-manage/authority/create        → create
 *   POST /library-manage/authority               → store
 *   GET  /library-manage/authority/{id}          → view
 *   PUT  /library-manage/authority/{id}          → update
 *   DELETE /library-manage/authority/{id}        → destroy
 *   GET  /library-manage/authority/{id}/link     → link
 *   POST /library-manage/authority/link          → storeLink
 *   POST /library-manage/authority/unlink/{linkId} → unlink
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgLibrary\Controllers;

use AhgCore\Support\DropdownOptions;
use AhgLibrary\Services\AuthorityControlService;
use AhgLibrary\Services\AuthorityHierarchyService;
use AhgLibrary\Services\AuthorityRecordService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class AuthorityControlController extends Controller
{
    private AuthorityControlService $svc;

    public function __construct()
    {
        $this->svc = new AuthorityControlService();
    }

    /** @return array<string,array<string,string>> Dropdown Manager options for the forms */
    private function options(): array
    {
        return [
            'recordTypes'  => DropdownOptions::resolve('library_authority_record_type', []),
            'subjectTypes' => DropdownOptions::resolve('library_authority_subject_type', []),
            'sources'      => DropdownOptions::resolve('library_authority_source', []),
        ];
    }

    private function rules(?object $current = null, ?string $currentRecordType = null): array
    {
        $options = $this->options();
        // A stored value retired from the Dropdown Manager must not block other edits.
        $allow = fn (array $codes, ?string $value) => Rule::in(array_filter([...array_keys($codes), $value]));

        return [
            'heading'         => 'required|string|max:500',
            'record_type'     => ['required', 'string', 'max:30', $allow($options['recordTypes'], $currentRecordType)],
            'subject_type'    => ['required', 'string', 'max:50', $allow($options['subjectTypes'], $current->subject_type ?? null)],
            'source'          => ['required', 'string', 'max:50', $allow($options['sources'], $current->source ?? null)],
            'uri'             => 'nullable|url|max:500',
            'lc_label'        => 'nullable|string|max:500',
            'rda_label'       => 'nullable|string|max:500',
            'authorized_form' => 'nullable|string|max:500',
            'vocab_code'      => 'nullable|string|max:50',
            'vocab_uri'       => 'nullable|url|max:500',
            'notes'           => 'nullable|string|max:1000',
        ];
    }

    public function index(Request $request)
    {
        $params = $request->only(['page', 'limit', 'search', 'subject_type', 'source', 'record_type']);
        $result = $this->svc->index($params);

        return view('ahg-library::authority.index', [
            'authorities' => $result['hits'],
            'total' => $result['total'],
            'page' => $result['page'],
            'limit' => $result['limit'],
        ] + $this->options());
    }

    public function create()
    {
        return view('ahg-library::authority.create', $this->options());
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        try {
            $id = DB::transaction(function () use ($validated) {
                $newId = $this->svc->create($validated);
                $this->svc->update($newId, array_intersect_key($validated, array_flip(['lc_label', 'rda_label', 'authorized_form', 'vocab_code', 'vocab_uri', 'notes'])));
                app(AuthorityRecordService::class)->setType($newId, $validated['record_type']);

                return $newId;
            });
            return redirect()->route('library.authority-view', $id)
                ->with('success', __('Authority record created.'));
        } catch (Throwable $e) {
            Log::error('AuthorityControlController::store error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', __('Failed to create authority record.'));
        }
    }

    public function view(int $id)
    {
        $authority = $this->svc->find($id);
        if (! $authority) {
            abort(404, 'Authority record not found.');
        }

        $linkedItems = $this->svc->linkedItems($id);
        $records = app(AuthorityRecordService::class);
        $hierarchy = app(AuthorityHierarchyService::class);
        $depth = $records->available();
        $typing = $depth ? $records->get($id) : null;

        return view('ahg-library::authority.view', [
            'authority' => $authority,
            'linkedItems' => $linkedItems,
            'depthAvailable' => $depth,
            'typing' => $typing,
            'recordType' => $records->typeFor($authority, $typing),
            'overrides' => $records->overriddenFields($typing),
            'recordTypes' => DropdownOptions::resolve('library_authority_record_type', []),
            'broader' => $depth ? $hierarchy->broader($id) : [],
            'narrower' => $depth ? $hierarchy->narrower($id) : [],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $before = $this->svc->find($id);
        abort_unless($before, 404);
        $records = app(AuthorityRecordService::class);
        $storedType = $records->typeFor($before, $records->available() ? $records->get($id) : null);
        $validated = $request->validate($this->rules($before, $storedType));

        try {
            DB::transaction(function () use ($id, $validated, $before, $records) {
                $this->svc->update($id, $validated);
                $records->setType($id, $validated['record_type']);
                $records->recordLocalEdit($id, $before, $validated);
            });

            return redirect()->route('library.authority-view', $id)
                ->with('success', __('Authority record updated.'));
        } catch (Throwable $e) {
            Log::error('AuthorityControlController::update error: ' . $e->getMessage());
            return redirect()->back()->with('error', __('Update failed.'));
        }
    }

    public function destroy(int $id)
    {
        try {
            $ok = $this->svc->delete($id);
            if (! $ok) {
                return redirect()->back()->with('error', __('Authority record not found.'));
            }

            return redirect()->route('library.authority-index')
                ->with('success', __('Authority record deleted.'));
        } catch (Throwable $e) {
            Log::error('AuthorityControlController::destroy error: ' . $e->getMessage());
            return redirect()->back()->with('error', __('Delete failed.'));
        }
    }

    public function link(int $id)
    {
        $authority = $this->svc->find($id);
        if (! $authority) {
            abort(404, 'Authority record not found.');
        }

        return view('ahg-library::authority.link', [
            'authority' => $authority,
        ]);
    }

    public function storeLink(Request $request)
    {
        $validated = $request->validate([
            'authority_id'     => 'required|integer|exists:library_subject_authority,id',
            'library_item_id'  => 'required|integer|exists:library_item,id',
            'source_tag'       => 'nullable|string|max:10',
        ]);

        try {
            $this->svc->linkToItem(
                (int) $validated['authority_id'],
                (int) $validated['library_item_id'],
                $validated['source_tag'] ?? '650'
            );

            return redirect()->route('library.authority-view', $validated['authority_id'])
                ->with('success', __('Subject heading linked to library item.'));
        } catch (Throwable $e) {
            Log::error('AuthorityControlController::storeLink error: ' . $e->getMessage());
            return redirect()->back()->with('error', __('Link failed.'));
        }
    }

    public function unlink(int $linkId)
    {
        $link = DB::table('library_item_authority_link')->where('id', $linkId)->first();
        if (! $link) {
            return redirect()->back()->with('error', __('Link not found.'));
        }

        try {
            $this->svc->unlinkFromItem($linkId);
            return redirect()->route('library.authority-view', $link->authority_id)
                ->with('success', __('Link removed.'));
        } catch (Throwable $e) {
            Log::error('AuthorityControlController::unlink error: ' . $e->getMessage());
            return redirect()->back()->with('error', __('Unlink failed.'));
        }
    }
}
