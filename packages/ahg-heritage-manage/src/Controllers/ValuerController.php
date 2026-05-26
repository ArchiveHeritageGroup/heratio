<?php

/**
 * ValuerController - admin CRUD for the qualified valuer registry.
 *
 * GRAP 103.41 / IPSAS 45.69 require certain heritage asset valuations be
 * performed by an appropriately qualified valuer. This controller is the
 * admin UI for managing that registry.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgHeritageManage\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ValuerController extends Controller
{
    public function index(Request $request)
    {
        $items = collect();
        try {
            if (Schema::hasTable('ahg_valuer')) {
                $q = DB::table('ahg_valuer');
                if ($search = trim((string) $request->query('q', ''))) {
                    $q->where(function ($w) use ($search) {
                        $w->where('name', 'like', "%{$search}%")
                          ->orWhere('credential', 'like', "%{$search}%")
                          ->orWhere('professional_body', 'like', "%{$search}%")
                          ->orWhere('accreditation_number', 'like', "%{$search}%");
                    });
                }
                if ($request->query('active') !== null && $request->query('active') !== '') {
                    $q->where('active', (int) $request->query('active'));
                }
                $items = $q->orderBy('name')->paginate(25)->appends($request->query());
            }
        } catch (\Exception $e) {
        }
        return view('ahg-heritage-manage::valuers.index', ['items' => $items, 'q' => $request->query('q')]);
    }

    public function create()
    {
        return view('ahg-heritage-manage::valuers.add', [
            'item'       => null,
            'formAction' => route('heritage.valuer.store'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateInput($request);
        $data['created_at'] = now();
        $data['updated_at'] = now();
        DB::table('ahg_valuer')->insert($data);
        return redirect()->route('heritage.valuer.index')->with('success', __('Valuer added.'));
    }

    public function edit(int $id)
    {
        $item = DB::table('ahg_valuer')->where('id', $id)->first();
        abort_if(! $item, 404);
        return view('ahg-heritage-manage::valuers.edit', [
            'item'       => $item,
            'formAction' => route('heritage.valuer.update', ['id' => $id]),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $data = $this->validateInput($request);
        $data['updated_at'] = now();
        DB::table('ahg_valuer')->where('id', $id)->update($data);
        return redirect()->route('heritage.valuer.index')->with('success', __('Valuer updated.'));
    }

    public function destroy(int $id)
    {
        DB::table('ahg_valuer')->where('id', $id)->update([
            'active'     => 0,
            'updated_at' => now(),
        ]);
        return redirect()->route('heritage.valuer.index')->with('success', __('Valuer deactivated.'));
    }

    /**
     * Convert form input into a sanitised, validated insert/update payload.
     */
    protected function validateInput(Request $request): array
    {
        $v = $request->validate([
            'name'                 => 'required|string|max:255',
            'credential'           => 'nullable|string|max:255',
            'professional_body'    => 'nullable|string|max:255',
            'accreditation_number' => 'nullable|string|max:64',
            'email'                => 'nullable|email|max:255',
            'phone'                => 'nullable|string|max:64',
            'specialisations'      => 'nullable',
            'active'               => 'nullable|boolean',
            'notes'                => 'nullable|string',
        ]);

        // Specialisations may arrive as comma-separated string or already as array.
        $spec = $v['specialisations'] ?? null;
        if (is_string($spec)) {
            $spec = array_values(array_filter(array_map('trim', explode(',', $spec))));
        }
        $v['specialisations'] = $spec ? json_encode($spec) : null;
        $v['active'] = isset($v['active']) ? (int) $v['active'] : 1;
        return $v;
    }
}
