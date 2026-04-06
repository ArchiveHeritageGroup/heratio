<?php

/**
 * RightsController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgRightsHolderManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RightsController extends Controller
{
    public function index(string $slug)
    {
        $culture = app()->getLocale();
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$objectId) abort(404);

        $resource = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.id', $objectId)
            ->where('information_object_i18n.culture', $culture)
            ->select('information_object.id', 'information_object.level_of_description_id', 'information_object_i18n.title', 'slug.slug')
            ->first();
        if (!$resource) abort(404);

        $rights = DB::table('rights')
            ->join('relation', function ($j) use ($objectId) {
                $j->on('rights.id', '=', 'relation.subject_id')
                   ->where('relation.object_id', '=', $objectId)
                   ->where('relation.type_id', '=', 168);
            })
            ->leftJoin('rights_i18n', function ($j) use ($culture) {
                $j->on('rights.id', '=', 'rights_i18n.id')->where('rights_i18n.culture', '=', $culture);
            })
            ->select('rights.*', 'rights_i18n.rights_note', 'rights_i18n.copyright_note',
                     'rights_i18n.license_terms', 'rights_i18n.license_note',
                     'rights_i18n.statute_note',
                     'rights_i18n.identifier_type', 'rights_i18n.identifier_value', 'rights_i18n.identifier_role',
                     'rights_i18n.statute_jurisdiction')
            ->get()
            ->map(function ($r) use ($culture) {
                $row = (array) $r;
                if ($r->basis_id) {
                    $row['basis'] = DB::table('term_i18n')->where('id', $r->basis_id)->where('culture', $culture)->value('name');
                }
                $row['basis_label'] = $row['basis'] ?? 'Rights Record';
                if ($r->copyright_status_id) {
                    $row['copyright_status'] = DB::table('term_i18n')->where('id', $r->copyright_status_id)->where('culture', $culture)->value('name');
                }
                if ($r->rights_holder_id) {
                    $row['rights_holder_name'] = DB::table('actor_i18n')->where('id', $r->rights_holder_id)->where('culture', $culture)->value('authorized_form_of_name');
                }
                $row['granted_rights'] = DB::table('granted_right')
                    ->where('granted_right.rights_id', $r->id)
                    ->select('granted_right.*')
                    ->get()
                    ->map(function ($gr) use ($culture) {
                        $arr = (array) $gr;
                        $arr['act'] = $gr->act_id ? DB::table('term_i18n')->where('id', $gr->act_id)->where('culture', $culture)->value('name') : '';
                        return $arr;
                    })->toArray();
                return $row;
            })->toArray();

        // Lookup data for the create form
        $basisTerms = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 68)
            ->where('term_i18n.culture', $culture)
            ->select('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get();

        $actTerms = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 67)
            ->where('term_i18n.culture', $culture)
            ->select('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get();

        $copyrightStatusTerms = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 69)
            ->where('term_i18n.culture', $culture)
            ->select('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get();

        return view('ahg-rights-holder-manage::rights.index', compact(
            'resource', 'rights', 'basisTerms', 'actTerms', 'copyrightStatusTerms'
        ));
    }

    /**
     * Store a new PREMIS rights record for an information object.
     */
    public function store(Request $request, string $slug)
    {
        $culture = app()->getLocale();
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$objectId) abort(404);

        $io = DB::table('information_object')
            ->where('id', $objectId)
            ->select('id', 'level_of_description_id')
            ->first();
        if (!$io) abort(404);

        $request->validate([
            'basis_id' => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'copyright_status_id' => 'nullable|integer',
            'copyright_status_date' => 'nullable|date',
            'copyright_jurisdiction' => 'nullable|string|max:1024',
            'statute_determination_date' => 'nullable|date',
            'rights_note' => 'nullable|string',
            'copyright_note' => 'nullable|string',
            'license_terms' => 'nullable|string',
            'license_note' => 'nullable|string',
            'statute_jurisdiction' => 'nullable|string',
            'statute_note' => 'nullable|string',
            'identifier_type' => 'nullable|string',
            'identifier_value' => 'nullable|string',
            'identifier_role' => 'nullable|string',
            'rights_holder_name' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $objectId, $culture) {
            // 1. Create object row
            $now = now();
            $rightsObjectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitRights',
                'created_at' => $now,
                'updated_at' => $now,
                'serial_number' => 0,
            ]);

            // 2. Resolve or find rights holder
            $rightsHolderId = null;
            $rightsHolderName = $request->input('rights_holder_name');
            if ($rightsHolderName && trim($rightsHolderName) !== '') {
                $rightsHolderId = DB::table('actor_i18n')
                    ->where('authorized_form_of_name', trim($rightsHolderName))
                    ->where('culture', $culture)
                    ->value('id');
            }

            // 3. Insert into rights table
            DB::table('rights')->insert([
                'id' => $rightsObjectId,
                'start_date' => $request->input('start_date') ?: null,
                'end_date' => $request->input('end_date') ?: null,
                'basis_id' => $request->input('basis_id'),
                'rights_holder_id' => $rightsHolderId,
                'copyright_status_id' => $request->input('copyright_status_id') ?: null,
                'copyright_status_date' => $request->input('copyright_status_date') ?: null,
                'copyright_jurisdiction' => $request->input('copyright_jurisdiction') ?: null,
                'statute_determination_date' => $request->input('statute_determination_date') ?: null,
                'statute_citation_id' => null,
                'source_culture' => $culture,
            ]);

            // 4. Insert into rights_i18n table
            DB::table('rights_i18n')->insert([
                'id' => $rightsObjectId,
                'culture' => $culture,
                'rights_note' => $request->input('rights_note') ?: null,
                'copyright_note' => $request->input('copyright_note') ?: null,
                'license_terms' => $request->input('license_terms') ?: null,
                'license_note' => $request->input('license_note') ?: null,
                'statute_jurisdiction' => $request->input('statute_jurisdiction') ?: null,
                'statute_note' => $request->input('statute_note') ?: null,
                'identifier_type' => $request->input('identifier_type') ?: null,
                'identifier_value' => $request->input('identifier_value') ?: null,
                'identifier_role' => $request->input('identifier_role') ?: null,
            ]);

            // 5. Create relation (object_id=IO, subject_id=rights.id, type_id=168)
            $relationObjectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitRelation',
                'created_at' => $now,
                'updated_at' => $now,
                'serial_number' => 0,
            ]);
            DB::table('relation')->insert([
                'id' => $relationObjectId,
                'subject_id' => $rightsObjectId,
                'object_id' => $objectId,
                'type_id' => 168,
                'source_culture' => $culture,
            ]);

            // 6. Insert granted rights
            $grantedActs = $request->input('granted_act', []);
            $grantedRestrictions = $request->input('granted_restriction', []);
            $grantedStartDates = $request->input('granted_start_date', []);
            $grantedEndDates = $request->input('granted_end_date', []);
            $grantedNotes = $request->input('granted_notes', []);

            if (is_array($grantedActs)) {
                foreach ($grantedActs as $i => $actId) {
                    if (empty($actId)) continue;

                    $restriction = $grantedRestrictions[$i] ?? 1;

                    DB::table('granted_right')->insert([
                        'rights_id' => $rightsObjectId,
                        'act_id' => (int) $actId,
                        'restriction' => (int) $restriction,
                        'start_date' => !empty($grantedStartDates[$i]) ? $grantedStartDates[$i] : null,
                        'end_date' => !empty($grantedEndDates[$i]) ? $grantedEndDates[$i] : null,
                        'notes' => !empty($grantedNotes[$i]) ? $grantedNotes[$i] : null,
                        'serial_number' => 0,
                    ]);
                }
            }
        });

        // Sector-aware redirect
        $redirectRoute = 'informationobject.show';
        if ($io->level_of_description_id && \Illuminate\Support\Facades\Schema::hasTable('level_of_description_sector')) {
            $sector = DB::table('level_of_description_sector')
                ->where('term_id', $io->level_of_description_id)
                ->whereNotIn('sector', ['archive'])
                ->orderBy('display_order')
                ->value('sector');

            $sectorRoutes = [
                'library' => 'library.show',
                'museum'  => 'museum.show',
                'gallery' => 'gallery.show',
                'dam'     => 'dam.show',
            ];

            if ($sector && isset($sectorRoutes[$sector])) {
                try {
                    if (\Illuminate\Support\Facades\Route::has($sectorRoutes[$sector])) {
                        $redirectRoute = $sectorRoutes[$sector];
                    }
                } catch (\Exception $e) {
                    // fall through
                }
            }
        }

        return redirect()
            ->route($redirectRoute, $slug)
            ->with('success', 'Rights record created successfully.');
    }
}
