<?php

/**
 * AuthorityControlController — Subject authority CRUD for Heratio.
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

use AhgLibrary\Services\AuthorityControlService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthorityControlController extends Controller
{
    private AuthorityControlService $svc;

    public function __construct()
    {
        $this->svc = new AuthorityControlService();
    }

    public function index(Request $request)
    {
        $params = $request->only(['page', 'limit', 'search', 'subject_type', 'source']);
        $result = $this->svc->index($params);

        return view('ahg-library::authority.index', [
            'authorities' => $result['hits'],
            'total' => $result['total'],
            'page' => $result['page'],
            'limit' => $result['limit'],
        ]);
    }

    public function create()
    {
        return view('ahg-library::authority.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'heading'      => 'required|string|max:500',
            'subject_type' => 'required|string|max:50',
            'source'       => 'required|string|max:50',
            'uri'          => 'nullable|url|max:1000',
        ]);

        try {
            $id = $this->svc->create($validated);
            return redirect()->route('library.authority-view', $id)
                ->with('success', 'Authority record created.');
        } catch (Throwable $e) {
            Log::error('AuthorityControlController::store error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to create authority record.');
        }
    }

    public function view(int $id)
    {
        $authority = $this->svc->find($id);
        if (! $authority) {
            abort(404, 'Authority record not found.');
        }

        $linkedItems = $this->svc->linkedItems($id);

        return view('ahg-library::authority.view', [
            'authority' => $authority,
            'linkedItems' => $linkedItems,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'heading'      => 'required|string|max:500',
            'subject_type' => 'required|string|max:50',
            'source'       => 'required|string|max:50',
            'uri'          => 'nullable|url|max:1000',
        ]);

        try {
            $ok = $this->svc->update($id, $validated);
            if (! $ok) {
                return redirect()->back()->with('error', 'Nothing to update.');
            }

            return redirect()->route('library.authority-view', $id)
                ->with('success', 'Authority record updated.');
        } catch (Throwable $e) {
            Log::error('AuthorityControlController::update error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Update failed.');
        }
    }

    public function destroy(int $id)
    {
        try {
            $ok = $this->svc->delete($id);
            if (! $ok) {
                return redirect()->back()->with('error', 'Authority record not found.');
            }

            return redirect()->route('library.authority-index')
                ->with('success', 'Authority record deleted.');
        } catch (Throwable $e) {
            Log::error('AuthorityControlController::destroy error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Delete failed.');
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
                ->with('success', 'Subject heading linked to library item.');
        } catch (Throwable $e) {
            Log::error('AuthorityControlController::storeLink error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Link failed.');
        }
    }

    public function unlink(int $linkId)
    {
        $link = DB::table('library_item_authority_link')->where('id', $linkId)->first();
        if (! $link) {
            return redirect()->back()->with('error', 'Link not found.');
        }

        try {
            $this->svc->unlinkFromItem($linkId);
            return redirect()->route('library.authority-view', $link->authority_id)
                ->with('success', 'Link removed.');
        } catch (Throwable $e) {
            Log::error('AuthorityControlController::unlink error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unlink failed.');
        }
    }
}
