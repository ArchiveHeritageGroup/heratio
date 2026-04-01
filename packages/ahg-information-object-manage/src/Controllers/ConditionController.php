<?php

/**
 * ConditionController - Controller for Heratio
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



namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use AhgInformationObjectManage\Services\ConditionService;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgConditionPlugin/
 */
class ConditionController extends Controller
{
    private ConditionService $service;

    public function __construct(ConditionService $service)
    {
        $this->service = $service;
    }

    /**
     * List condition reports + latest report + SPECTRUM checks for an IO.
     */
    public function index(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $reports  = $this->service->getReportsForObject($io->id);
        $latest   = $this->service->getLatestReport($io->id);
        $spectrum = $this->service->getSpectrumChecks($io->id);

        // Merge condition_report + spectrum into a unified "checks" collection
        // so the existing view (which iterates $checks) keeps working.
        $checks = collect();

        foreach ($reports as $report) {
            $checks->push((object) [
                'id'               => $report->id,
                'check_date'       => $report->assessment_date,
                'condition_rating' => $report->overall_rating,
                'check_type'       => $report->context,
                'assessor'         => $this->resolveAssessor($report->assessor_user_id),
                'notes'            => $report->summary,
                'source'           => 'condition_report',
            ]);
        }

        foreach ($spectrum as $sc) {
            $checks->push((object) [
                'id'               => $sc->id,
                'check_date'       => $sc->check_date,
                'condition_rating' => $sc->overall_condition ?? $sc->condition_rating ?? null,
                'check_type'       => $sc->check_reason ?? 'spectrum',
                'assessor'         => $sc->checked_by,
                'notes'            => $sc->condition_note ?? $sc->condition_notes ?? null,
                'source'           => 'spectrum',
            ]);
        }

        // Sort combined by date descending
        $checks = $checks->sortByDesc('check_date')->values();

        return view('ahg-io-manage::condition.index', [
            'io'       => $io,
            'checks'   => $checks,
            'latest'   => $latest,
            'spectrum' => $spectrum,
        ]);
    }

    /**
     * Show the create-report form with dropdown options.
     */
    public function create(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        return view('ahg-io-manage::condition.create', [
            'io'              => $io,
            'ratingOptions'   => $this->service->getRatingOptions(),
            'contextOptions'  => $this->service->getContextOptions(),
            'priorityOptions' => $this->service->getPriorityOptions(),
            'damageTypes'     => $this->service->getDamageTypeOptions(),
            'severityOptions' => $this->service->getSeverityOptions(),
        ]);
    }

    /**
     * Validate and store a new condition report.
     */
    public function store(Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $validated = $request->validate([
            'assessment_date'     => 'required|date',
            'overall_rating'      => 'required|string|max:47',
            'context'             => 'nullable|string|max:121',
            'summary'             => 'nullable|string',
            'recommendations'     => 'nullable|string',
            'priority'            => 'nullable|string|max:32',
            'next_check_date'     => 'nullable|date',
            'environmental_notes' => 'nullable|string',
            'handling_notes'      => 'nullable|string',
            'display_notes'       => 'nullable|string',
            'storage_notes'       => 'nullable|string',
        ]);

        $validated['information_object_id'] = $io->id;
        $validated['assessor_user_id'] = auth()->id();

        $reportId = $this->service->createReport($validated);

        // Process inline damages if submitted
        $damageTypes = $request->input('damage_type', []);
        foreach ($damageTypes as $i => $type) {
            if (empty($type)) {
                continue;
            }
            $this->service->addDamage($reportId, [
                'damage_type'       => $type,
                'location'          => $request->input('damage_location.' . $i, 'overall'),
                'severity'          => $request->input('damage_severity.' . $i, 'minor'),
                'description'       => $request->input('damage_description.' . $i),
                'dimensions'        => $request->input('damage_dimensions.' . $i),
                'is_active'         => $request->has('damage_is_active.' . $i) ? 1 : 1,
                'treatment_required' => $request->has('damage_treatment_required.' . $i) ? 1 : 0,
                'treatment_notes'   => $request->input('damage_treatment_notes.' . $i),
            ]);
        }

        return redirect()
            ->route('io.condition', $slug)
            ->with('success', 'Condition report created successfully.');
    }

    /**
     * Show a single condition report with its damages.
     */
    public function show(int $id)
    {
        $report = $this->service->getReport($id);
        if (!$report) {
            abort(404);
        }

        // Resolve the IO for breadcrumb
        $io = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) {
                $j->on('i18n.id', '=', 'io.id')
                  ->where('i18n.culture', app()->getLocale());
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $report->information_object_id)
            ->select('io.id', 'i18n.title', 's.slug')
            ->first();

        // Get photos for this report
        $photos = DB::table('condition_image')
            ->where('condition_report_id', $id)
            ->orderBy('created_at')
            ->get();

        // Get damages
        $damages = $this->service->getDamages($id);

        return view('ahg-io-manage::condition.show', [
            'io'      => $io,
            'report'  => $report,
            'photos'  => $photos,
            'damages' => $damages,
        ]);
    }

    /**
     * Show a spectrum condition check with its photos (matching AtoM's /condition/check/{id}/photos).
     */
    public function spectrumShow(int $id)
    {
        $check = DB::table('spectrum_condition_check')->where('id', $id)->first();
        if (!$check) {
            abort(404);
        }

        $io = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', app()->getLocale());
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $check->object_id)
            ->select('io.id', 'i18n.title', 's.slug')
            ->first();

        $photos = DB::table('spectrum_condition_photo')
            ->where('condition_check_id', $id)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        // Map to a report-like object for the shared view
        $report = (object) [
            'id' => $check->id,
            'information_object_id' => $check->object_id,
            'assessment_date' => $check->check_date,
            'overall_rating' => $check->overall_condition ?? $check->condition_rating ?? 'pending',
            'context' => $check->check_reason ?? 'spectrum',
            'assessor_user_id' => null,
            'summary' => $check->condition_note ?? $check->condition_notes ?? null,
            'recommendations' => $check->recommended_treatment ?? $check->recommendations ?? null,
            'priority' => $check->treatment_priority ?? null,
            'next_check_date' => $check->next_check_date ?? null,
            'environmental_notes' => $check->environment_recommendation ?? null,
            'handling_notes' => $check->handling_recommendation ?? null,
            'display_notes' => $check->display_recommendation ?? null,
            'storage_notes' => $check->storage_recommendation ?? null,
            'source' => 'spectrum',
        ];

        // Map spectrum photos to condition_image format
        $mappedPhotos = $photos->map(function ($p) {
            return (object) [
                'id' => $p->id,
                'condition_report_id' => $p->condition_check_id,
                'file_path' => $p->file_path ?? ('/uploads/condition_photos/' . $p->filename),
                'caption' => $p->caption ?? $p->original_filename ?? null,
                'image_type' => $p->photo_type ?? 'detail',
                'annotations' => $p->annotations ?? null,
                'created_at' => $p->created_at,
            ];
        });

        $damages = collect();

        return view('ahg-io-manage::condition.show', [
            'io' => $io,
            'report' => $report,
            'photos' => $mappedPhotos,
            'damages' => $damages,
        ]);
    }

    /**
     * Upload a photo to a condition report.
     */
    public function uploadPhoto(Request $request, int $id)
    {
        $report = $this->service->getReport($id);
        if (!$report) {
            abort(404);
        }

        $request->validate([
            'photo' => 'required|image|max:10240',
            'image_type' => 'nullable|string|max:54',
            'caption' => 'nullable|string|max:500',
        ]);

        $file = $request->file('photo');
        $dir = public_path('uploads/condition_photos');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = $id . '_' . time() . '_' . $file->getClientOriginalName();
        $file->move($dir, $filename);

        DB::table('condition_image')->insert([
            'condition_report_id' => $id,
            'file_path' => '/uploads/condition_photos/' . $filename,
            'caption' => $request->input('caption'),
            'image_type' => $request->input('image_type', 'general'),
            'created_at' => now(),
        ]);

        return redirect()->route('io.condition.show', $id)->with('success', 'Photo uploaded.');
    }

    /**
     * Delete a condition photo.
     */
    public function deletePhoto(int $id)
    {
        $photo = DB::table('condition_image')->where('id', $id)->first();
        if (!$photo) {
            abort(404);
        }

        if ($photo->file_path && file_exists(public_path($photo->file_path))) {
            @unlink(public_path($photo->file_path));
        }

        DB::table('condition_image')->where('id', $id)->delete();

        return redirect()->back()->with('success', 'Photo deleted.');
    }

    /**
     * Get or save annotations for a condition photo (JSON API).
     */
    public function annotation(Request $request, int $id)
    {
        $photo = DB::table('condition_image')->where('id', $id)->first();
        if (!$photo) {
            return response()->json(['success' => false, 'error' => 'Photo not found'], 404);
        }

        if ($request->isMethod('post')) {
            $annotations = $request->input('annotations', []);
            DB::table('condition_image')->where('id', $id)->update([
                'annotations' => json_encode($annotations),
            ]);
            return response()->json(['success' => true]);
        }

        return response()->json([
            'success' => true,
            'annotations' => $photo->annotations ? json_decode($photo->annotations, true) : [],
        ]);
    }

    /**
     * Resolve assessor user ID to a display name.
     */
    private function resolveAssessor(?int $userId): string
    {
        if (!$userId) {
            return '—';
        }

        $user = DB::table('user')
            ->where('id', $userId)
            ->select('username')
            ->first();

        return $user->username ?? '—';
    }

    /**
     * Look up an information object by slug.
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
            ->select('io.id', 'i18n.title', 's.slug')
            ->first();
    }
}
