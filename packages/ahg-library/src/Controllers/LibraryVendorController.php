<?php

/**
 * LibraryVendorController - acquisitions vendor (supplier) management UI.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
 * details. You should have received a copy of the GNU Affero General Public
 * License along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgLibrary\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Web CRUD for library_vendor. The same rows are exposed read/write through the
 * JSON:API (LibraryVendorApiController); this controller is the staff UI.
 */
class LibraryVendorController extends Controller
{
    public function index(Request $request): View
    {
        $q = DB::table('library_vendor');
        if ($request->filled('q')) {
            $needle = '%' . $request->query('q') . '%';
            $q->where(function ($w) use ($needle) {
                $w->where('name', 'LIKE', $needle)
                    ->orWhere('vendor_code', 'LIKE', $needle)
                    ->orWhere('contact_name', 'LIKE', $needle);
            });
        }
        if ($request->filled('type')) {
            $q->where('vendor_type', $request->query('type'));
        }

        return view('ahg-library::acquisition.vendors', [
            'vendors'     => collect($q->orderBy('name')->get()),
            'searchQuery' => $request->query('q'),
            'typeFilter'  => $request->query('type'),
        ]);
    }

    public function create(): View
    {
        return view('ahg-library::acquisition.vendor-edit', ['vendor' => null]);
    }

    public function edit(int $id): View
    {
        $vendor = DB::table('library_vendor')->where('id', $id)->first();
        if (!$vendor) {
            abort(404);
        }

        return view('ahg-library::acquisition.vendor-edit', ['vendor' => $vendor]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateVendor($request);
        $data['created_by'] = auth()->id();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = (int) DB::table('library_vendor')->insertGetId($data);

        return redirect()
            ->route('library.acquisition-vendors')
            ->with('success', 'Vendor created.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $vendor = DB::table('library_vendor')->where('id', $id)->first();
        if (!$vendor) {
            abort(404);
        }

        $data = $this->validateVendor($request);
        $data['updated_at'] = now();
        DB::table('library_vendor')->where('id', $id)->update($data);

        return redirect()
            ->route('library.acquisition-vendors')
            ->with('success', 'Vendor updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $vendor = DB::table('library_vendor')->where('id', $id)->first();
        if (!$vendor) {
            abort(404);
        }

        $inUse = DB::table('library_order')->where('vendor_id', $id)->exists();
        if ($inUse) {
            // Soft-disable rather than delete a vendor with order history.
            DB::table('library_vendor')->where('id', $id)->update([
                'is_active'  => 0,
                'updated_at' => now(),
            ]);
            return back()->with('info', 'Vendor has orders; deactivated instead of deleted.');
        }

        DB::table('library_vendor')->where('id', $id)->delete();

        return redirect()
            ->route('library.acquisition-vendors')
            ->with('success', 'Vendor deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateVendor(Request $request): array
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'vendor_code'    => ['nullable', 'string', 'max:50'],
            'vendor_type'    => ['nullable', 'string', 'max:50'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'contact_name'   => ['nullable', 'string', 'max:255'],
            'email'          => ['nullable', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'website'        => ['nullable', 'string', 'max:255'],
            'address'        => ['nullable', 'string', 'max:1000'],
            'city'           => ['nullable', 'string', 'max:100'],
            'country'        => ['nullable', 'string', 'max:100'],
            'currency'       => ['nullable', 'string', 'max:3'],
            'san'            => ['nullable', 'string', 'max:20'],
            'notes'          => ['nullable', 'string', 'max:2000'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true) ? 1 : 0;

        return $validated;
    }
}
