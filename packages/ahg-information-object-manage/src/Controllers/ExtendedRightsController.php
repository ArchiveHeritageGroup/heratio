<?php

/**
 * ExtendedRightsController - Controller for Heratio
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



namespace AhgInformationObjectManage\Controllers;

use AhgInformationObjectManage\Services\ExtendedRightsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgExtendedRightsPlugin/
 * and /usr/share/nginx/archive/atom-ahg-plugins/ahgRightsPlugin/
 */
class ExtendedRightsController extends Controller
{
    protected ExtendedRightsService $service;

    public function __construct()
    {
        $this->service = new ExtendedRightsService();
    }

    /**
     * Show rights for this IO (both standard rights and extended).
     */
    public function add(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $culture = app()->getLocale();

        // Standard rights via relation table
        $rights = $this->service->getRightsForObject($io->id, $culture);

        // Extended rights
        $extendedRights = $this->service->getExtendedRights($io->id);

        // Current primary extended right
        $currentRights = $extendedRights->firstWhere('is_primary', 1);

        // TK labels for current primary
        $currentTkLabels = [];
        if ($currentRights) {
            $currentTkLabels = $this->service->getTkLabelsForRights($currentRights->id)
                ->pluck('id')
                ->toArray();
        }

        // Form data
        $rightsStatements = $this->service->getRightsStatements();
        $ccLicenses = $this->service->getCreativeCommonsLicenses();
        $tkLabels = $this->service->getTkLabels();
        $donors = $this->service->getDonors();

        // Active embargo
        $embargo = $this->service->getActiveEmbargo($io->id);

        return view('ahg-io-manage::rights.extended', [
            'io'              => $io,
            'rights'          => $rights,
            'extendedRights'  => $extendedRights,
            'currentRights'   => $currentRights ? (object) [
                'rights_statement' => (object) ['rights_statement_id' => $currentRights->rights_statement_id ?? null],
                'cc_license'       => (object) ['creative_commons_license_id' => $currentRights->creative_commons_license_id ?? null],
                'rights_holder'    => (object) ['donor_id' => $currentRights->rights_holder ?? null],
                'tk_labels'        => $currentTkLabels,
            ] : null,
            'rightsStatements' => $rightsStatements,
            'ccLicenses'       => $ccLicenses,
            'tkLabels'         => $tkLabels,
            'donors'           => $donors,
            'embargo'          => $embargo,
        ]);
    }

    /**
     * Store (create or update) extended rights for this IO.
     */
    public function store(Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $request->validate([
            'rights_statement_id' => 'nullable|integer',
            'cc_license_id'       => 'nullable|integer',
            'rights_holder_id'    => 'nullable|integer',
            'tk_label_ids'        => 'nullable|array',
            'tk_label_ids.*'      => 'integer',
            'rights_note'         => 'nullable|string|max:10000',
            'usage_conditions'    => 'nullable|string|max:10000',
            'copyright_notice'    => 'nullable|string|max:10000',
            'rights_date'         => 'nullable|date',
            'expiry_date'         => 'nullable|date',
            'rights_holder'       => 'nullable|string|max:255',
            'rights_holder_uri'   => 'nullable|string|max:255',
        ]);

        $data = [
            'rights_statement_id'         => $request->input('rights_statement_id') ?: null,
            'creative_commons_license_id' => $request->input('cc_license_id') ?: null,
            'rights_date'                 => $request->input('rights_date'),
            'expiry_date'                 => $request->input('expiry_date'),
            'rights_holder'               => $request->input('rights_holder'),
            'rights_holder_uri'           => $request->input('rights_holder_uri'),
            'is_primary'                  => 1,
            'rights_note'                 => $request->input('rights_note'),
            'usage_conditions'            => $request->input('usage_conditions'),
            'copyright_notice'            => $request->input('copyright_notice'),
            'tk_label_ids'                => $request->input('tk_label_ids', []),
        ];

        // Check if a primary extended right already exists for this object
        $existing = DB::table('extended_rights')
            ->where('object_id', $io->id)
            ->where('is_primary', 1)
            ->first();

        $userId = auth()->id();

        if ($existing) {
            $this->service->updateExtendedRight($existing->id, $data, $userId);
            $message = 'Extended rights updated successfully.';
        } else {
            $this->service->saveExtendedRight($io->id, $data, $userId);
            $message = 'Extended rights created successfully.';
        }

        return redirect($this->resolveRecordUrl($slug))
            ->with('notice', $message);
    }

    /**
     * Show embargo status + form to create/lift.
     */
    public function embargo(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        // Active embargo
        $activeEmbargo = $this->service->getActiveEmbargo($io->id);

        // All embargoes (history)
        $embargoes = $this->service->getAllEmbargoes($io->id);

        // Descendant count for propagation option
        $descendantCount = $this->service->getDescendantCount($io->id);

        return view('ahg-io-manage::rights.embargo', [
            'io'              => $io,
            'activeEmbargo'   => $activeEmbargo,
            'embargoes'       => $embargoes,
            'descendantCount' => $descendantCount,
        ]);
    }

    /**
     * Create a new embargo.
     */
    public function storeEmbargo(Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $request->validate([
            'embargo_type' => 'required|string|max:50',
            'start_date'   => 'required|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'reason'       => 'nullable|string|max:5000',
            'is_perpetual' => 'nullable|boolean',
            'notify_on_expiry'  => 'nullable|boolean',
            'notify_days_before'=> 'nullable|integer|min:1|max:365',
        ]);

        $data = [
            'object_id'         => $io->id,
            'embargo_type'      => $request->input('embargo_type'),
            'start_date'        => $request->input('start_date'),
            'end_date'          => $request->input('end_date'),
            'reason'            => $request->input('reason'),
            'is_perpetual'      => $request->boolean('is_perpetual') ? 1 : 0,
            'created_by'        => auth()->id(),
            'notify_on_expiry'  => $request->boolean('notify_on_expiry') ? 1 : 0,
            'notify_days_before'=> $request->input('notify_days_before', 30),
        ];

        $applyToChildren = $request->boolean('apply_to_children');

        if ($applyToChildren) {
            $results = $this->service->createEmbargoWithPropagation($data, true);
            $message = "Embargo created for {$results['created']} record(s).";
            if ($results['failed'] > 0) {
                $message .= " {$results['failed']} record(s) failed.";
            }
        } else {
            $this->service->createEmbargo($data);
            $message = 'Embargo created successfully.';
        }

        return redirect($this->resolveRecordUrl($slug))
            ->with('notice', $message);
    }

    /**
     * Lift an embargo.
     */
    public function liftEmbargo(Request $request, int $id)
    {
        $request->validate([
            'lift_reason' => 'nullable|string|max:5000',
        ]);

        $embargo = DB::table('embargo')->where('id', $id)->first();
        if (!$embargo) {
            abort(404);
        }

        $userId = auth()->id() ?? 0;
        $reason = $request->input('lift_reason', '');

        $this->service->liftEmbargo($id, $userId, $reason);

        // Resolve the slug for redirect
        $slug = DB::table('slug')
            ->where('object_id', $embargo->object_id)
            ->value('slug');

        return redirect($this->resolveRecordUrl($slug ?? ''))
            ->with('notice', 'Embargo lifted successfully.');
    }

    /**
     * Unified rights management form: PREMIS + Extended + Embargo in one screen.
     */
    public function manage(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $culture = app()->getLocale();

        // Existing PREMIS rights (via relation type 168)
        $premisRights = $this->service->getRightsForObject($io->id, $culture);

        // Granted rights for each PREMIS right
        $grantedRights = collect();
        foreach ($premisRights as $pr) {
            $grantedRights[$pr->id] = $this->service->getGrantedRights($pr->id);
        }

        // Existing extended rights (primary)
        $extendedRights = $this->service->getExtendedRights($io->id);
        $currentExtended = $extendedRights->firstWhere('is_primary', 1);

        // TK labels for current extended right
        $currentTkLabels = [];
        if ($currentExtended) {
            $currentTkLabels = $this->service->getTkLabelsForRights($currentExtended->id)
                ->pluck('id')
                ->toArray();
        }

        // Active embargo
        $embargo = $this->service->getActiveEmbargo($io->id);

        // Form dropdown data
        $rightsStatements = $this->service->getRightsStatements();
        $ccLicenses = $this->service->getCreativeCommonsLicenses();
        $tkLabels = $this->service->getTkLabels();
        $basisTerms = $this->service->getTermsByTaxonomy(68);
        $actTerms = $this->service->getTermsByTaxonomy(67);
        $copyrightStatusTerms = $this->service->getTermsByTaxonomy(69);

        return view('ahg-io-manage::rights.manage', [
            'io'                   => $io,
            'premisRights'         => $premisRights,
            'grantedRights'        => $grantedRights,
            'currentExtended'      => $currentExtended,
            'currentTkLabels'      => $currentTkLabels,
            'embargo'              => $embargo,
            'rightsStatements'     => $rightsStatements,
            'ccLicenses'           => $ccLicenses,
            'tkLabels'             => $tkLabels,
            'basisTerms'           => $basisTerms,
            'actTerms'             => $actTerms,
            'copyrightStatusTerms' => $copyrightStatusTerms,
        ]);
    }

    /**
     * Store unified rights form: saves PREMIS + Extended + Embargo in one transaction.
     */
    public function manageStore(Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $request->validate([
            // Extended rights
            'rights_statement_id'         => 'nullable|integer',
            'cc_license_id'               => 'nullable|integer',
            'ext_usage_conditions'        => 'nullable|string|max:10000',
            'ext_copyright_notice'        => 'nullable|string|max:10000',
            'ext_rights_note'             => 'nullable|string|max:10000',
            'ext_rights_holder'           => 'nullable|string|max:255',
            'ext_rights_holder_uri'       => 'nullable|string|max:255',
            'ext_rights_date'             => 'nullable|date',
            'ext_expiry_date'             => 'nullable|date',
            'tk_label_ids'                => 'nullable|array',
            'tk_label_ids.*'              => 'integer',
            // PREMIS rights
            'premis_basis_id'             => 'nullable|integer',
            'premis_start_date'           => 'nullable|date',
            'premis_end_date'             => 'nullable|date',
            'premis_rights_holder_id'     => 'nullable|integer',
            'premis_copyright_status_id'  => 'nullable|integer',
            'premis_copyright_status_date'=> 'nullable|date',
            'premis_copyright_jurisdiction'=> 'nullable|string|max:1024',
            'premis_copyright_note'       => 'nullable|string|max:10000',
            'premis_license_terms'        => 'nullable|string|max:10000',
            'premis_license_note'         => 'nullable|string|max:10000',
            'premis_statute_jurisdiction' => 'nullable|string|max:10000',
            'premis_statute_determination_date' => 'nullable|date',
            'premis_statute_note'         => 'nullable|string|max:10000',
            'premis_rights_note'          => 'nullable|string|max:10000',
            'premis_identifier_type'      => 'nullable|string|max:500',
            'premis_identifier_value'     => 'nullable|string|max:500',
            'premis_identifier_role'      => 'nullable|string|max:500',
            // Granted rights
            'granted'                     => 'nullable|array',
            'granted.*.act_id'            => 'nullable|integer',
            'granted.*.restriction'       => 'nullable|integer|in:0,1,2',
            'granted.*.start_date'        => 'nullable|date',
            'granted.*.end_date'          => 'nullable|date',
            'granted.*.notes'             => 'nullable|string|max:5000',
            // Embargo
            'embargo_type'                => 'nullable|string|max:50',
            'embargo_start_date'          => 'nullable|date',
            'embargo_end_date'            => 'nullable|date|after_or_equal:embargo_start_date',
            'embargo_reason'              => 'nullable|string|max:5000',
            'embargo_is_perpetual'        => 'nullable|boolean',
            'embargo_notify_on_expiry'    => 'nullable|boolean',
            'embargo_notify_days_before'  => 'nullable|integer|min:1|max:365',
            'lift_embargo_id'             => 'nullable|integer',
            'lift_reason'                 => 'nullable|string|max:5000',
        ]);

        $userId = auth()->id();

        DB::transaction(function () use ($request, $io, $userId) {
            // 1. Save/update extended rights + i18n
            $extData = [
                'rights_statement_id'         => $request->input('rights_statement_id') ?: null,
                'creative_commons_license_id' => $request->input('cc_license_id') ?: null,
                'rights_date'                 => $request->input('ext_rights_date'),
                'expiry_date'                 => $request->input('ext_expiry_date'),
                'rights_holder'               => $request->input('ext_rights_holder'),
                'rights_holder_uri'           => $request->input('ext_rights_holder_uri'),
                'is_primary'                  => 1,
                'rights_note'                 => $request->input('ext_rights_note'),
                'usage_conditions'            => $request->input('ext_usage_conditions'),
                'copyright_notice'            => $request->input('ext_copyright_notice'),
                'tk_label_ids'                => $request->input('tk_label_ids', []),
            ];

            $existingExt = DB::table('extended_rights')
                ->where('object_id', $io->id)
                ->where('is_primary', 1)
                ->first();

            if ($existingExt) {
                $this->service->updateExtendedRight($existingExt->id, $extData, $userId);
            } else {
                $this->service->saveExtendedRight($io->id, $extData, $userId);
            }

            // 2. Save/update PREMIS rights + i18n
            $hasPremisData = $request->filled('premis_basis_id')
                || $request->filled('premis_rights_note')
                || $request->filled('premis_start_date');

            if ($hasPremisData) {
                $premisData = [
                    'basis_id'                  => $request->input('premis_basis_id') ?: null,
                    'start_date'                => $request->input('premis_start_date'),
                    'end_date'                  => $request->input('premis_end_date'),
                    'rights_holder_id'          => $request->input('premis_rights_holder_id') ?: null,
                    'copyright_status_id'       => $request->input('premis_copyright_status_id') ?: null,
                    'copyright_status_date'     => $request->input('premis_copyright_status_date'),
                    'copyright_jurisdiction'    => $request->input('premis_copyright_jurisdiction'),
                    'copyright_note'            => $request->input('premis_copyright_note'),
                    'license_terms'             => $request->input('premis_license_terms'),
                    'license_note'              => $request->input('premis_license_note'),
                    'statute_jurisdiction'       => $request->input('premis_statute_jurisdiction'),
                    'statute_determination_date'=> $request->input('premis_statute_determination_date'),
                    'statute_note'              => $request->input('premis_statute_note'),
                    'rights_note'               => $request->input('premis_rights_note'),
                    'identifier_type'           => $request->input('premis_identifier_type'),
                    'identifier_value'          => $request->input('premis_identifier_value'),
                    'identifier_role'           => $request->input('premis_identifier_role'),
                ];

                // Check for existing PREMIS right linked to this IO
                $existingPremis = DB::table('relation as r')
                    ->join('rights', 'rights.id', '=', 'r.object_id')
                    ->where('r.subject_id', $io->id)
                    ->where('r.type_id', 168)
                    ->select('rights.id')
                    ->first();

                if ($existingPremis) {
                    $this->service->updatePremisRight($existingPremis->id, $premisData);
                    $premisRightsId = $existingPremis->id;
                } else {
                    $premisRightsId = $this->service->createPremisRight($io->id, $premisData);
                }

                // 3. Save/update granted rights
                $grantedRows = $request->input('granted', []);
                $filteredGranted = array_filter($grantedRows, function ($row) {
                    return !empty($row['act_id']);
                });
                $this->service->saveGrantedRights($premisRightsId, array_values($filteredGranted));
            }

            // 4. Save/update embargo
            if ($request->filled('lift_embargo_id')) {
                $this->service->liftEmbargo(
                    (int) $request->input('lift_embargo_id'),
                    $userId ?? 0,
                    $request->input('lift_reason', '')
                );
            } elseif ($request->filled('embargo_type') && $request->filled('embargo_start_date')) {
                $existingEmbargo = DB::table('embargo')
                    ->where('object_id', $io->id)
                    ->where('is_active', 1)
                    ->first();

                $embargoData = [
                    'embargo_type'      => $request->input('embargo_type'),
                    'start_date'        => $request->input('embargo_start_date'),
                    'end_date'          => $request->input('embargo_end_date'),
                    'reason'            => $request->input('embargo_reason'),
                    'is_perpetual'      => $request->boolean('embargo_is_perpetual') ? 1 : 0,
                    'notify_on_expiry'  => $request->boolean('embargo_notify_on_expiry') ? 1 : 0,
                    'notify_days_before'=> $request->input('embargo_notify_days_before', 30),
                ];

                if ($existingEmbargo) {
                    $this->service->updateEmbargo($existingEmbargo->id, array_merge($embargoData, [
                        'updated_by' => $userId,
                    ]));
                } else {
                    $this->service->createEmbargo(array_merge($embargoData, [
                        'object_id'  => $io->id,
                        'created_by' => $userId,
                    ]));
                }
            }
        });

        return redirect($this->resolveRecordUrl($slug))
            ->with('notice', 'Rights saved successfully.');
    }

    /**
     * Export rights as JSON-LD.
     */
    public function exportJsonLd(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $jsonLd = $this->service->exportJsonLd($io->id);

        return response(json_encode($jsonLd, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/ld+json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $slug . '_rights.jsonld"',
        ]);
    }

    /**
     * Resolve IO from slug.
     */
    private function getIO(string $slug): ?object
    {
        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('s.slug', $slug)
            ->select('io.id', 'io.level_of_description_id', 'i18n.title', 's.slug')
            ->first();
    }

    /**
     * Resolve the correct show URL for a record (GLAM sector aware).
     */
    private function resolveRecordUrl(string $slug): string
    {
        $io = $this->getIO($slug);
        if (!$io || !$io->level_of_description_id) {
            return '/' . $slug;
        }

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

        if ($sector && isset($sectorRoutes[$sector]) && \Illuminate\Support\Facades\Route::has($sectorRoutes[$sector])) {
            return route($sectorRoutes[$sector], $slug);
        }

        return '/' . $slug;
    }
}
