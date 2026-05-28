<?php

/**
 * CopyCataloguingController — Z39.50 copy cataloguing for Heratio.
 *
 * Routes:
 *   GET  /library-manage/copy-cataloguing          → index
 *   GET  /library-manage copy-cataloguing/search   → search (with target query)
 *   POST /library-manage/copy-cataloguing/import  → import
 *   GET  /library-manage/copy-cataloguing/targets → targets
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

use AhgLibrary\Services\CopyCataloguingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CopyCataloguingController extends Controller
{
    private CopyCataloguingService $svc;

    public function __construct()
    {
        $this->svc = new CopyCataloguingService();
    }

    public function index()
    {
        $targets = $this->svc->getTargets();
        return view('ahg-library::copy-cataloguing.index', [
            'targets' => $targets,
        ]);
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'target_id' => 'required|integer|exists:library_z3950_target,id',
            'query'     => 'required|string|min:2|max:500',
        ]);

        try {
            $result = $this->svc->search(
                (int) $validated['target_id'],
                $validated['query'],
                20
            );

            $targets = $this->svc->getTargets();

            if ($result['error']) {
                return view('ahg-library::copy-cataloguing.index', [
                    'targets' => $targets,
                    'searchError' => $result['error'],
                    'query' => $validated['query'],
                    'targetId' => $validated['target_id'],
                ]);
            }

            return view('ahg-library::copy-cataloguing.index', [
                'targets'   => $targets,
                'records'   => $result['records'],
                'recordCount' => $result['count'],
                'query'     => $validated['query'],
                'targetId'  => $validated['target_id'],
            ]);
        } catch (Throwable $e) {
            Log::error('CopyCataloguingController::search error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Z39.50 search failed: ' . $e->getMessage());
        }
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'marc_content' => 'required|string',
        ]);

        try {
            $raw = base64_decode($validated['marc_content'], true);
            if ($raw === false) {
                return redirect()->back()->with('error', 'Invalid MARC data.');
            }

            $ioId = $this->svc->import($raw);

            $slug = DB::table('slug')->where('object_id', $ioId)->value('slug');

            return redirect()->route('library.show', $slug)
                ->with('success', 'Record imported via copy cataloguing.');
        } catch (Throwable $e) {
            Log::error('CopyCataloguingController::import error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function targets(Request $request)
    {
        $targets = DB::table('library_z3950_target')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('ahg-library::copy-cataloguing.targets', [
            'targets' => $targets,
        ]);
    }

    public function storeTarget(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'host'         => 'required|string|max:255',
            'port'         => 'nullable|integer|min:1|max:65535',
            'database_name'=> 'nullable|string|max:255',
            'syntax'       => 'nullable|string|max:50',
            'element_set'  => 'nullable|string|max:10',
            'username'     => 'nullable|string|max:255',
            'password'     => 'nullable|string|max:255',
            'active'       => 'nullable|boolean',
            'sort_order'   => 'nullable|integer',
        ]);

        try {
            DB::table('library_z3950_target')->insert([
                'name'          => $validated['name'],
                'host'          => $validated['host'],
                'port'          => $validated['port'] ?? 210,
                'database_name' => $validated['database_name'] ?? 'Default',
                'syntax'        => $validated['syntax'] ?? 'USmarc',
                'element_set'   => $validated['element_set'] ?? 'F',
                'username'      => $validated['username'] ?? null,
                'password'      => $validated['password'] ?? null,
                'active'        => $validated['active'] ?? 1,
                'sort_order'    => $validated['sort_order'] ?? 0,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            return redirect()->route('library.copy-cataloguing-targets')
                ->with('success', 'Z39.50 target added.');
        } catch (Throwable $e) {
            Log::error('CopyCataloguingController::storeTarget error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save target: ' . $e->getMessage());
        }
    }

    public function updateTarget(Request $request, int $id)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'host'         => 'required|string|max:255',
            'port'         => 'nullable|integer|min:1|max:65535',
            'database_name'=> 'nullable|string|max:255',
            'syntax'       => 'nullable|string|max:50',
            'element_set'  => 'nullable|string|max:10',
            'username'     => 'nullable|string|max:255',
            'password'     => 'nullable|string|max:255',
            'active'       => 'nullable|boolean',
            'sort_order'   => 'nullable|integer',
        ]);

        try {
            DB::table('library_z3950_target')
                ->where('id', $id)
                ->update(array_filter([
                    'name'          => $validated['name'],
                    'host'          => $validated['host'],
                    'port'          => $validated['port'] ?? null,
                    'database_name' => $validated['database_name'] ?? null,
                    'syntax'        => $validated['syntax'] ?? null,
                    'element_set'   => $validated['element_set'] ?? null,
                    'username'      => array_key_exists('username', $validated) ? $validated['username'] : null,
                    'password'      => array_key_exists('password', $validated) ? $validated['password'] : null,
                    'active'        => $validated['active'] ?? null,
                    'sort_order'    => $validated['sort_order'] ?? null,
                    'updated_at'    => now(),
                ], fn($v) => $v !== null));

            return redirect()->route('library.copy-cataloguing-targets')
                ->with('success', 'Z39.50 target updated.');
        } catch (Throwable $e) {
            Log::error('CopyCataloguingController::updateTarget error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Update failed.');
        }
    }

    public function destroyTarget(int $id)
    {
        try {
            DB::table('library_z3950_target')->where('id', $id)->delete();
            return redirect()->route('library.copy-cataloguing-targets')
                ->with('success', 'Z39.50 target deleted.');
        } catch (Throwable $e) {
            Log::error('CopyCataloguingController::destroyTarget error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Delete failed.');
        }
    }
}
