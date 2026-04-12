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



namespace AhgCondition\Controllers;

use AhgCondition\Services\ConditionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ConditionController extends Controller
{
    protected ConditionService $service;

    public function __construct()
    {
        $this->service = new ConditionService(app()->getLocale());
    }

    public function admin()
    {
        $stats = $this->service->getAdminStats();
        $recentChecks = $this->service->getRecentChecks(20);
        $byCondition = $this->service->getByConditionBreakdown();

        return view('ahg-condition::admin', compact('stats', 'recentChecks', 'byCondition'));
    }

    /**
     * Risk Assessment dashboard — at-risk condition checks (poor + critical).
     * New page (no AtoM equivalent — built per project requirement to surface
     * heritage objects requiring conservation attention).
     */
    public function risk(Request $request)
    {
        $level = $request->get('level', 'all');
        $atRiskLevels = ['poor', 'critical'];
        if (in_array($level, $atRiskLevels, true)) {
            $atRiskLevels = [$level];
        }

        $rows = \Illuminate\Support\Facades\DB::table('spectrum_condition_check as cc')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'cc.object_id')
                    ->where('ioi.culture', '=', app()->getLocale());
            })
            ->whereIn('cc.overall_condition', $atRiskLevels)
            ->select('cc.*', 'ioi.title as object_title')
            ->orderByDesc('cc.check_date')
            ->limit(200)
            ->get();

        $counts = \Illuminate\Support\Facades\DB::table('spectrum_condition_check')
            ->selectRaw('overall_condition, COUNT(*) as count')
            ->whereIn('overall_condition', ['poor', 'critical'])
            ->groupBy('overall_condition')
            ->pluck('count', 'overall_condition');

        return view('ahg-condition::risk', compact('rows', 'counts', 'level'));
    }

    public function list(Request $request)
    {
        $conditions = $this->service->getConditionChecks([
            'condition' => $request->get('condition'),
        ]);

        return view('ahg-condition::list', compact('conditions'));
    }

    /**
     * GET /condition/check — list recent condition checks (JSON).
     * Legacy AtoM base-path alias.
     */
    public function checkIndex(Request $request)
    {
        $objectId = (int) $request->query('object_id', 0);

        if ($objectId) {
            $checks = $this->service->getConditionChecksForObject($objectId);
        } else {
            $checks = $this->service->getRecentChecks(20);
        }

        return response()->json(['success' => true, 'checks' => $checks]);
    }

    public function conditionCheck(string $slug)
    {
        $data = $this->service->getConditionCheckForObject($slug);
        abort_unless($data, 404, 'Object not found');

        return view('ahg-condition::condition-check', $data);
    }

    public function view(int $id)
    {
        $conditionCheck = $this->service->getConditionCheck($id);
        abort_unless($conditionCheck, 404, 'Condition check not found');

        $photos = $this->service->getPhotosForCheck($id);
        $stats = $this->service->getAnnotationStats($id);

        return view('ahg-condition::view', compact('conditionCheck', 'photos', 'stats'));
    }

    public function photos(Request $request, $id)
    {
        if ($id === 'new') {
            $objectId = (int) $request->get('object_id');
            abort_unless($objectId, 400, 'Object ID required');
            $newId = $this->service->createConditionCheck($objectId);

            return redirect()->route('condition.photos', $newId);
        }

        $checkId = (int) $id;
        $conditionCheck = $this->service->getConditionCheck($checkId);
        abort_unless($conditionCheck, 404, 'Condition check not found');

        $photos = $this->service->getPhotosForCheck($checkId);
        $stats = $this->service->getAnnotationStats($checkId);
        $canEdit = auth()->check();

        return view('ahg-condition::photos', compact('conditionCheck', 'photos', 'stats', 'canEdit'));
    }

    public function annotate(int $id)
    {
        $photo = $this->service->getPhoto($id);
        abort_unless($photo, 404, 'Photo not found');

        $conditionCheck = $this->service->getConditionCheck((int) $photo->condition_check_id);
        $annotations = $this->service->getAnnotations($id);
        $canEdit = auth()->check();
        $imageUrl = '/uploads/condition_photos/' . $photo->filename;

        return view('ahg-condition::annotate', compact('photo', 'conditionCheck', 'annotations', 'canEdit', 'imageUrl'));
    }

    public function getAnnotation(Request $request)
    {
        $photoId = (int) $request->get('photo_id');

        if (!$photoId) {
            return response()->json(['success' => false, 'error' => 'Missing photo_id']);
        }

        $annotations = $this->service->getAnnotations($photoId);

        return response()->json(['success' => true, 'annotations' => $annotations]);
    }

    public function saveAnnotation(Request $request)
    {
        $body = $request->json()->all();
        $photoId = (int) ($body['photo_id'] ?? $request->get('photo_id'));
        $annotations = $body['annotations'] ?? [];

        if (!$photoId) {
            return response()->json(['success' => false, 'error' => 'Missing photo_id']);
        }

        $result = $this->service->saveAnnotations($photoId, $annotations, auth()->id());

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Annotations saved' : 'Failed to save annotations',
        ]);
    }

    public function upload(Request $request)
    {
        $checkId = (int) ($request->get('id') ?: $request->get('condition_check_id'));

        if (!$checkId) {
            return response()->json(['success' => false, 'error' => 'Missing condition check ID']);
        }

        if (!$request->hasFile('photo')) {
            return response()->json(['success' => false, 'error' => 'No file uploaded']);
        }

        $file = $request->file('photo');
        $photoId = $this->service->uploadPhoto(
            $checkId,
            ['name' => $file->getClientOriginalName(), 'tmp_name' => $file->getPathname()],
            $request->get('photo_type', 'general'),
            $request->get('caption', ''),
            auth()->id()
        );

        if ($photoId) {
            $photo = $this->service->getPhoto($photoId);

            return response()->json([
                'success' => true,
                'photo_id' => $photoId,
                'filename' => $photo->filename,
            ]);
        }

        return response()->json(['success' => false, 'error' => 'Upload failed']);
    }

    public function deletePhoto(int $id)
    {
        $result = $this->service->deletePhoto($id, auth()->id());

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Photo deleted' : 'Failed to delete photo',
        ]);
    }

    public function exportReport(int $id)
    {
        $conditionCheck = $this->service->getConditionCheck($id);
        abort_unless($conditionCheck, 404, 'Condition check not found');

        $photos = $this->service->getPhotosForCheck($id);
        $stats = $this->service->getAnnotationStats($id);

        return view('ahg-condition::export-report', compact('conditionCheck', 'photos', 'stats'));
    }

    public function templateList()
    {
        $templates = $this->service->getTemplates();

        return view('ahg-condition::template-list', compact('templates'));
    }

    public function templateView(int $id)
    {
        $template = $this->service->getTemplateView($id);
        abort_unless($template, 404, 'Template not found');

        return view('ahg-condition::template-view', compact('template'));
    }
}
