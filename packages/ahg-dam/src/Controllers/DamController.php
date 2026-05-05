<?php

/**
 * DamController - Controller for Heratio
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



namespace AhgDam\Controllers;

use AhgDam\Services\DamService;
use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DamController extends Controller
{
    protected DamService $service;

    public function __construct()
    {
        $this->service = new DamService(app()->getLocale());
    }

    /**
     * The DAM edit form prefixes most IPTC field names with `iptc_`
     * (`iptc_creator`, `iptc_caption`, etc.) but the controller's validators
     * and $request->only() lists use the unprefixed column names. Without
     * this remap the entire IPTC section silently never saved.
     *
     * Two prefixed names are kept on purpose because the column itself
     * carries the prefix or distinguishes from the i18n title:
     *   - iptc_subject_code (column is named that way)
     *   - iptc_title        (separate from main IO title)
     *
     * Also turns the form's `credit_role[]` / `credit_name[]` parallel
     * arrays into a `contributors_json` string for dam_iptc_metadata.
     */
    private function normalizeFormFields(Request $request): void
    {
        $prefixedToInternal = [
            'iptc_creator'                  => 'creator',
            'iptc_creator_job_title'        => 'creator_job_title',
            'iptc_creator_email'            => 'creator_email',
            'iptc_creator_phone'            => 'creator_phone',
            'iptc_creator_website'          => 'creator_website',
            'iptc_creator_city'             => 'creator_city',
            'iptc_creator_address'          => 'creator_address',
            'iptc_headline'                 => 'headline',
            'iptc_caption'                  => 'caption',
            'iptc_keywords'                 => 'keywords',
            'iptc_intellectual_genre'       => 'intellectual_genre',
            'iptc_persons_shown'            => 'persons_shown',
            'iptc_date_created'             => 'date_created',
            'iptc_city'                     => 'city',
            'iptc_state_province'           => 'state_province',
            'iptc_sublocation'              => 'sublocation',
            'iptc_country'                  => 'country',
            'iptc_country_code'             => 'country_code',
            'iptc_credit_line'              => 'credit_line',
            'iptc_source'                   => 'source',
            'iptc_copyright_notice'         => 'copyright_notice',
            'iptc_rights_usage_terms'       => 'rights_usage_terms',
            'iptc_license_type'             => 'license_type',
            'iptc_license_url'              => 'license_url',
            'iptc_license_expiry'           => 'license_expiry',
            'iptc_model_release_status'     => 'model_release_status',
            'iptc_model_release_id'         => 'model_release_id',
            'iptc_property_release_status'  => 'property_release_status',
            'iptc_property_release_id'      => 'property_release_id',
            'iptc_artwork_title'            => 'artwork_title',
            'iptc_artwork_creator'          => 'artwork_creator',
            'iptc_artwork_date'             => 'artwork_date',
            'iptc_artwork_source'           => 'artwork_source',
            'iptc_artwork_copyright'        => 'artwork_copyright',
            'iptc_job_id'                   => 'job_id',
            'iptc_instructions'             => 'instructions',
        ];
        $merge = [];
        foreach ($prefixedToInternal as $from => $to) {
            // Only fill the unprefixed key if it wasn't sent directly.
            // Empty-string is treated as "not filled" so `nullable` rules
            // collapse it into null on save.
            if ($request->has($from) && !$request->filled($to)) {
                $merge[$to] = $request->input($from);
            }
        }

        // Production credits: zip credit_role[] + credit_name[] → contributors_json.
        $roles = $request->input('credit_role', []);
        $names = $request->input('credit_name', []);
        if (!is_array($roles)) $roles = [];
        if (!is_array($names)) $names = [];
        $credits = [];
        $rowCount = max(count($roles), count($names));
        for ($i = 0; $i < $rowCount; $i++) {
            $role = trim((string) ($roles[$i] ?? ''));
            $name = trim((string) ($names[$i] ?? ''));
            if ($role === '' && $name === '') continue;
            $credits[] = ['role' => $role, 'name' => $name];
        }
        // Always send the key — including empty array — so a save that clears
        // every row actually wipes the stored JSON.
        $merge['contributors_json'] = $credits ? json_encode($credits, JSON_UNESCAPED_UNICODE) : null;

        if ($merge) {
            $request->merge($merge);
        }
    }

    public function dashboard()
    {
        $stats = $this->service->getDashboardStats();
        $recentAssets = $this->service->getRecentAssets(10);

        return view('ahg-dam::dam.dashboard', [
            'stats' => $stats,
            'recentAssets' => $recentAssets,
        ]);
    }

    public function browse(Request $request)
    {
        $result = $this->service->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'lastUpdated'),
            'sortDir' => $request->get('sortDir', ''),
            'subquery' => $request->get('subquery', ''),
            'asset_type' => $request->get('asset_type', ''),
        ]);

        $pager = new SimplePager($result);

        return view('ahg-dam::dam.browse', [
            'pager' => $pager,
            'sortOptions' => [
                'alphabetic' => 'Title',
                'identifier' => 'Identifier',
                'date' => 'Date created',
                'lastUpdated' => 'Date modified',
            ],
        ]);
    }

    public function show(string $slug)
    {
        $asset = $this->service->getBySlug($slug);
        if (!$asset) {
            abort(404);
        }

        $culture = app()->getLocale();
        $digitalObjects = \AhgCore\Services\DigitalObjectService::getForObject($asset->id);
        $relatedItems = $this->service->getRelatedItems($asset->id);

        // Repository — needed by the cloned museum-style sidebar to render
        // the institution logo + link at the top. Mirrors MuseumController::show.
        $repository = null;
        if (!empty($asset->repository_id)) {
            $repository = \Illuminate\Support\Facades\DB::table('repository')
                ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
                ->join('slug', 'repository.id', '=', 'slug.object_id')
                ->where('repository.id', $asset->repository_id)
                ->where('actor_i18n.culture', $culture)
                ->select('repository.id', 'actor_i18n.authorized_form_of_name as name', 'slug.slug')
                ->first();
        }

        return view('ahg-dam::dam.show', [
            'asset' => $asset,
            'digitalObjects' => $digitalObjects,
            'relatedItems' => $relatedItems,
            'repository' => $repository,
        ]);
    }

    public function create()
    {
        $formChoices = $this->service->getFormChoices();

        return view('ahg-dam::dam.edit', [
            'asset' => null,
            'formChoices' => $formChoices,
            'versionLinks' => collect(),
            'formatHoldings' => collect(),
            'externalLinks' => collect(),
        ]);
    }

    public function edit(string $slug)
    {
        $asset = $this->service->getBySlug($slug);
        if (!$asset) {
            abort(404);
        }

        // Decode contributors_json into $asset->credits so the production-credits
        // form rows repopulate. Stored as JSON in dam_iptc_metadata.contributors_json.
        $asset->credits = [];
        if (!empty($asset->contributors_json)) {
            $decoded = json_decode((string) $asset->contributors_json, true);
            if (is_array($decoded)) {
                $asset->credits = $decoded;
            }
        }

        $formChoices = $this->service->getFormChoices();

        return view('ahg-dam::dam.edit', [
            'asset' => $asset,
            'formChoices' => $formChoices,
            'versionLinks' => $this->service->getVersionLinks($asset->id),
            'formatHoldings' => $this->service->getFormatHoldings($asset->id),
            'externalLinks' => $this->service->getExternalLinks($asset->id),
        ]);
    }

    public function store(Request $request)
    {
        $this->normalizeFormFields($request);
        $request->validate([
            'identifier' => 'required|string|max:255',
            'title' => 'required|string|max:1024',
            'contributors_json' => 'nullable|string',
            'repository_id' => 'nullable|integer',
            'level_of_description_id' => 'nullable|integer',
            'scope_and_content' => 'nullable|string',
            'extent_and_medium' => 'nullable|string',
            'asset_type' => 'nullable|string|max:50',
            'genre' => 'nullable|string|max:255',
            'color_type' => 'nullable|string|max:50',
            'production_company' => 'nullable|string|max:255',
            'distributor' => 'nullable|string|max:255',
            // Stored as VARCHAR(100). Accept year-only (1954), year-month
            // (1954-06), or full date (1954-06-15) — Laravel's `date` rule
            // rejected bare years even though the form's placeholder
            // explicitly says "e.g., 1954".
            'broadcast_date' => ['nullable', 'string', 'max:100', 'regex:/^\d{4}(-\d{2}(-\d{2})?)?$/'],
            'series_title' => 'nullable|string|max:255',
            'season_number' => 'nullable|integer',
            'episode_number' => 'nullable|integer',
            'awards' => 'nullable|string',
            'audio_language' => 'nullable|string|max:255',
            'subtitle_language' => 'nullable|string|max:255',
            'creator' => 'nullable|string|max:255',
            'creator_job_title' => 'nullable|string|max:255',
            'creator_email' => 'nullable|email|max:255',
            'creator_phone' => 'nullable|string|max:100',
            'creator_website' => 'nullable|string|max:255',
            'creator_city' => 'nullable|string|max:255',
            'creator_address' => 'nullable|string|max:500',
            'headline' => 'nullable|string|max:255',
            'duration_minutes' => 'nullable|integer|min:0',
            'caption' => 'nullable|string',
            'keywords' => 'nullable|string',
            'iptc_subject_code' => 'nullable|string|max:255',
            'intellectual_genre' => 'nullable|string|max:255',
            'persons_shown' => 'nullable|string',
            'date_created' => 'nullable|date',
            'city' => 'nullable|string|max:255',
            'state_province' => 'nullable|string|max:255',
            'sublocation' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'country_code' => 'nullable|string|max:10',
            'production_country' => 'nullable|string|max:255',
            'production_country_code' => 'nullable|string|max:10',
            'credit_line' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'copyright_notice' => 'nullable|string|max:500',
            'rights_usage_terms' => 'nullable|string',
            'license_type' => 'nullable|string|max:50',
            'license_url' => 'nullable|string|max:500',
            'license_expiry' => 'nullable|date',
            'model_release_status' => 'nullable|string|max:50',
            'model_release_id' => 'nullable|string|max:255',
            'property_release_status' => 'nullable|string|max:50',
            'property_release_id' => 'nullable|string|max:255',
            'artwork_title' => 'nullable|string|max:255',
            'artwork_creator' => 'nullable|string|max:255',
            'artwork_date' => 'nullable|string|max:255',
            'artwork_source' => 'nullable|string|max:255',
            'artwork_copyright' => 'nullable|string|max:500',
            'iptc_title' => 'nullable|string|max:255',
            'job_id' => 'nullable|string|max:255',
            'instructions' => 'nullable|string',
        ]);

        $data = $request->only([
            'identifier', 'title', 'repository_id', 'level_of_description_id',
            'scope_and_content', 'extent_and_medium', 'asset_type', 'genre', 'color_type',
            'production_company', 'distributor', 'broadcast_date', 'series_title',
            'season_number', 'episode_number', 'awards', 'audio_language', 'subtitle_language',
            'creator', 'creator_job_title', 'creator_email', 'creator_phone',
            'creator_website', 'creator_city', 'creator_address',
            'headline', 'duration_minutes', 'caption', 'keywords',
            'iptc_subject_code', 'intellectual_genre', 'persons_shown', 'date_created',
            'city', 'state_province', 'sublocation', 'country', 'country_code',
            'production_country', 'production_country_code',
            'credit_line', 'source', 'copyright_notice', 'rights_usage_terms',
            'license_type', 'license_url', 'license_expiry',
            'model_release_status', 'model_release_id',
            'property_release_status', 'property_release_id',
            'artwork_title', 'artwork_creator', 'artwork_date',
            'artwork_source', 'artwork_copyright',
            'iptc_title', 'job_id', 'instructions',
            // Production credits (zipped from credit_role[]+credit_name[] by normalizeFormFields).
            'contributors_json',
        ]);

        $id = $this->service->create($data);

        // Save multi-row tables
        $multiRowData = $request->only([
            'version_id', 'version_title', 'version_type', 'version_language',
            'version_language_code', 'version_year', 'version_notes',
            'holding_id', 'holding_format', 'holding_format_details', 'holding_institution',
            'holding_location', 'holding_accession', 'holding_condition', 'holding_access',
            'holding_url', 'holding_verified', 'holding_primary', 'holding_access_notes', 'holding_notes',
            'link_id', 'link_type', 'link_url', 'link_title', 'link_verified',
            'link_primary', 'link_person', 'link_role', 'link_description',
        ]);
        $this->service->saveVersionLinks($id, $multiRowData);
        $this->service->saveFormatHoldings($id, $multiRowData);
        $this->service->saveExternalLinks($id, $multiRowData);

        $slug = $this->service->getSlug($id);

        return redirect()
            ->route('dam.show', $slug)
            ->with('success', 'DAM asset created successfully.');
    }

    public function update(Request $request, string $slug)
    {
        $asset = $this->service->getBySlug($slug);
        if (!$asset) {
            abort(404);
        }

        $this->normalizeFormFields($request);
        $request->validate([
            'identifier' => 'required|string|max:255',
            'title' => 'required|string|max:1024',
            'contributors_json' => 'nullable|string',
            'repository_id' => 'nullable|integer',
            'level_of_description_id' => 'nullable|integer',
            'scope_and_content' => 'nullable|string',
            'extent_and_medium' => 'nullable|string',
            'asset_type' => 'nullable|string|max:50',
            'genre' => 'nullable|string|max:255',
            'color_type' => 'nullable|string|max:50',
            'production_company' => 'nullable|string|max:255',
            'distributor' => 'nullable|string|max:255',
            // Stored as VARCHAR(100). Accept year-only (1954), year-month
            // (1954-06), or full date (1954-06-15) — Laravel's `date` rule
            // rejected bare years even though the form's placeholder
            // explicitly says "e.g., 1954".
            'broadcast_date' => ['nullable', 'string', 'max:100', 'regex:/^\d{4}(-\d{2}(-\d{2})?)?$/'],
            'series_title' => 'nullable|string|max:255',
            'season_number' => 'nullable|integer',
            'episode_number' => 'nullable|integer',
            'awards' => 'nullable|string',
            'audio_language' => 'nullable|string|max:255',
            'subtitle_language' => 'nullable|string|max:255',
            'creator' => 'nullable|string|max:255',
            'creator_job_title' => 'nullable|string|max:255',
            'creator_email' => 'nullable|email|max:255',
            'creator_phone' => 'nullable|string|max:100',
            'creator_website' => 'nullable|string|max:255',
            'creator_city' => 'nullable|string|max:255',
            'creator_address' => 'nullable|string|max:500',
            'headline' => 'nullable|string|max:255',
            'duration_minutes' => 'nullable|integer|min:0',
            'caption' => 'nullable|string',
            'keywords' => 'nullable|string',
            'iptc_subject_code' => 'nullable|string|max:255',
            'intellectual_genre' => 'nullable|string|max:255',
            'persons_shown' => 'nullable|string',
            'date_created' => 'nullable|date',
            'city' => 'nullable|string|max:255',
            'state_province' => 'nullable|string|max:255',
            'sublocation' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'country_code' => 'nullable|string|max:10',
            'production_country' => 'nullable|string|max:255',
            'production_country_code' => 'nullable|string|max:10',
            'credit_line' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'copyright_notice' => 'nullable|string|max:500',
            'rights_usage_terms' => 'nullable|string',
            'license_type' => 'nullable|string|max:50',
            'license_url' => 'nullable|string|max:500',
            'license_expiry' => 'nullable|date',
            'model_release_status' => 'nullable|string|max:50',
            'model_release_id' => 'nullable|string|max:255',
            'property_release_status' => 'nullable|string|max:50',
            'property_release_id' => 'nullable|string|max:255',
            'artwork_title' => 'nullable|string|max:255',
            'artwork_creator' => 'nullable|string|max:255',
            'artwork_date' => 'nullable|string|max:255',
            'artwork_source' => 'nullable|string|max:255',
            'artwork_copyright' => 'nullable|string|max:500',
            'iptc_title' => 'nullable|string|max:255',
            'job_id' => 'nullable|string|max:255',
            'instructions' => 'nullable|string',
        ]);

        $data = $request->only([
            'identifier', 'title', 'repository_id', 'level_of_description_id',
            'scope_and_content', 'extent_and_medium', 'asset_type', 'genre', 'color_type',
            'production_company', 'distributor', 'broadcast_date', 'series_title',
            'season_number', 'episode_number', 'awards', 'audio_language', 'subtitle_language',
            'creator', 'creator_job_title', 'creator_email', 'creator_phone',
            'creator_website', 'creator_city', 'creator_address',
            'headline', 'duration_minutes', 'caption', 'keywords',
            'iptc_subject_code', 'intellectual_genre', 'persons_shown', 'date_created',
            'city', 'state_province', 'sublocation', 'country', 'country_code',
            'production_country', 'production_country_code',
            'credit_line', 'source', 'copyright_notice', 'rights_usage_terms',
            'license_type', 'license_url', 'license_expiry',
            'model_release_status', 'model_release_id',
            'property_release_status', 'property_release_id',
            'artwork_title', 'artwork_creator', 'artwork_date',
            'artwork_source', 'artwork_copyright',
            'iptc_title', 'job_id', 'instructions',
            // Production credits (zipped from credit_role[]+credit_name[] by normalizeFormFields).
            'contributors_json',
            // ICIP cultural-sensitivity URI (issue #36 Phase 2b) — persisted to information_object.icip_sensitivity.
            'icip_sensitivity',
        ]);

        $this->service->update($slug, $data);

        // Save multi-row tables
        $multiRowData = $request->only([
            'version_id', 'version_title', 'version_type', 'version_language',
            'version_language_code', 'version_year', 'version_notes',
            'holding_id', 'holding_format', 'holding_format_details', 'holding_institution',
            'holding_location', 'holding_accession', 'holding_condition', 'holding_access',
            'holding_url', 'holding_verified', 'holding_primary', 'holding_access_notes', 'holding_notes',
            'link_id', 'link_type', 'link_url', 'link_title', 'link_verified',
            'link_primary', 'link_person', 'link_role', 'link_description',
        ]);
        $this->service->saveVersionLinks($asset->id, $multiRowData);
        $this->service->saveFormatHoldings($asset->id, $multiRowData);
        $this->service->saveExternalLinks($asset->id, $multiRowData);

        return redirect()
            ->route('dam.show', $slug)
            ->with('success', 'DAM asset updated successfully.');
    }

    public function destroy(string $slug)
    {
        $asset = $this->service->getBySlug($slug);
        if (!$asset) {
            abort(404);
        }

        $this->service->delete($slug);

        return redirect()
            ->route('dam.browse')
            ->with('success', 'DAM asset deleted successfully.');
    }

    public function bulkCreate(Request $request) { return view('ahg-dam::bulk-create'); }

    public function editIptc(Request $request, string $slug) { return view('ahg-dam::edit-iptc', ['record' => (object)['slug'=>$slug]]); }

    public function damIndex(Request $request) { return view('ahg-dam::dam-index', ['rows' => collect()]); }

    public function reportIndex()
    {
        $stats = ['total' => 0, 'totalSize' => 0, 'withMetadata' => 0, 'withIptc' => 0, 'withGps' => 0, 'recentUploads' => 0, 'byMimeType' => collect()];
        try {
            if (\Schema::hasTable('digital_object')) {
                $stats['total'] = \DB::table('digital_object')->count();
                $stats['totalSize'] = (int) \DB::table('digital_object')->sum('byte_size');
                $stats['recentUploads'] = \DB::table('digital_object')->where('created_at', '>=', now()->subDays(30))->count();
                $stats['byMimeType'] = \DB::table('digital_object')
                    ->select('mime_type', \DB::raw('COUNT(*) as count'), \DB::raw('SUM(byte_size) as size'))
                    ->whereNotNull('mime_type')->groupBy('mime_type')->orderByDesc('count')->limit(10)->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-dam::report-index', compact('stats'));
    }

    public function reportAssets()
    {
        $rows = collect();
        try {
            if (\Schema::hasTable('digital_object')) {
                $rows = \DB::table('digital_object as do')
                    ->leftJoin('information_object as io', 'do.object_id', '=', 'io.id')
                    ->leftJoin('information_object_i18n as io_i18n', function ($j) { $j->on('io.id', '=', 'io_i18n.id')->where('io_i18n.culture', '=', 'en'); })
                    ->select('do.id', 'do.name', 'do.mime_type', 'do.byte_size', 'io_i18n.title as record_title', 'io.identifier as record_identifier')
                    ->orderByDesc('do.created_at')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-dam::report-assets', compact('rows'));
    }

    public function reportIptc()
    {
        $rows = collect();
        try {
            if (\Schema::hasTable('digital_object') && \Schema::hasTable('property')) {
                $rows = \DB::table('digital_object as do')
                    ->leftJoin('property as p', function ($j) { $j->on('do.id', '=', 'p.object_id'); })
                    ->leftJoin('property_i18n as pi', function ($j) { $j->on('p.id', '=', 'pi.id')->where('pi.culture', '=', 'en'); })
                    ->select('do.id', 'do.name', 'p.name as property_name', 'pi.value as property_value')
                    ->whereIn('p.name', ['iptc_headline', 'iptc_creator', 'iptc_city', 'iptc_copyright', 'iptc_source'])
                    ->orderBy('do.name')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-dam::report-iptc', compact('rows'));
    }

    public function reportMetadata()
    {
        $rows = collect();
        try {
            if (\Schema::hasTable('digital_object')) {
                $rows = \DB::table('digital_object as do')
                    ->select('do.id', 'do.name', 'do.mime_type', 'do.byte_size', 'do.created_at')
                    ->orderByDesc('do.created_at')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-dam::report-metadata', compact('rows'));
    }

    public function reportStorage()
    {
        $storage = ['total' => 0, 'orphaned' => 0, 'byType' => collect()];
        try {
            if (\Schema::hasTable('digital_object')) {
                $storage['total'] = (int) \DB::table('digital_object')->sum('byte_size');
                $storage['byType'] = \DB::table('digital_object')
                    ->select('mime_type', \DB::raw('COUNT(*) as count'), \DB::raw('SUM(byte_size) as size'))
                    ->whereNotNull('mime_type')->groupBy('mime_type')->orderByDesc('size')->get();
            }
        } catch (\Throwable $e) {}
        return view('ahg-dam::report-storage', compact('storage'));
    }
}
