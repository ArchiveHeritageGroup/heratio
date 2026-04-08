<?php

/**
 * ResearchController - Controller for Heratio
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

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgResearchPlugin/
 */
class ResearchController extends Controller
{
    /**
     * Source assessment for an IO.
     */
    public function sourceAssessment(Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) abort(404);

        $researcher = auth()->check()
            ? DB::table('research_researcher')->where('user_id', auth()->id())->first()
            : null;

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'add_metric') {
                DB::table('research_quality_metric')->insert([
                    'object_id' => $io->id,
                    'metric_type' => $request->input('metric_type'),
                    'metric_value' => (float) $request->input('metric_value'),
                    'source_service' => $request->input('source_service') ?: null,
                    'created_at' => now(),
                ]);
                return redirect()->route('io.research.assessment', $slug)->with('success', 'Metric added.');
            }

            if ($action === 'delete_metric') {
                DB::table('research_quality_metric')->where('id', (int) $request->input('metric_id'))->where('object_id', $io->id)->delete();
                return redirect()->route('io.research.assessment', $slug)->with('success', 'Metric deleted.');
            }

            if ($researcher) {
                DB::table('research_source_assessment')->updateOrInsert(
                    ['object_id' => $io->id, 'researcher_id' => $researcher->id],
                    [
                        'source_type' => $request->input('source_type', 'primary'),
                        'source_form' => $request->input('source_form', 'original'),
                        'completeness' => $request->input('completeness', 'complete'),
                        'rationale' => $request->input('rationale') ?: null,
                        'bias_context' => $request->input('bias_context') ?: null,
                        'assessed_at' => now(),
                    ]
                );
                return redirect()->route('io.research.assessment', $slug)->with('success', 'Assessment saved.');
            }
        }

        $assessment = $researcher
            ? DB::table('research_source_assessment')->where('object_id', $io->id)->where('researcher_id', $researcher->id)->first()
            : DB::table('research_source_assessment')->where('object_id', $io->id)->orderByDesc('assessed_at')->first();

        $metrics = DB::table('research_quality_metric')->where('object_id', $io->id)->orderByDesc('created_at')->get()->toArray();

        return view('ahg-io-manage::research.assessment', compact('io', 'assessment', 'metrics'));
    }

    /**
     * Annotation studio for an IO.
     * Migrated from ahgResearchPlugin annotationStudio action — uses research_annotation_v2 + research_annotation_target (W3C Web Annotation model).
     */
    public function annotations(Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) abort(404);

        // Load V2 annotations for this object (via target table)
        $annotationIds = DB::table('research_annotation_target')
            ->where('source_type', 'information_object')
            ->where('source_id', $io->id)
            ->pluck('annotation_id')
            ->unique();

        $annotations = [];
        if ($annotationIds->isNotEmpty()) {
            $annotations = DB::table('research_annotation_v2 as a')
                ->leftJoin('research_researcher as r', 'a.researcher_id', '=', 'r.id')
                ->whereIn('a.id', $annotationIds)
                ->select('a.*', DB::raw("CONCAT(r.first_name, ' ', r.last_name) as researcher_name"))
                ->orderByDesc('a.created_at')
                ->get()
                ->map(function ($ann) {
                    $ann->targets = DB::table('research_annotation_target')
                        ->where('annotation_id', $ann->id)
                        ->orderBy('id')
                        ->get()
                        ->toArray();
                    return $ann;
                })
                ->toArray();
        }

        // Get digital object for image display
        $digitalObject = DB::table('digital_object')->where('object_id', $io->id)->orderBy('usage_id')->first();
        $imageUrl = null;
        if ($digitalObject) {
            if (str_starts_with($digitalObject->path ?? '', 'http')) {
                $imageUrl = $digitalObject->path;
            } else {
                $ref = DB::table('digital_object')->where('parent_id', $digitalObject->id)->where('usage_id', 141)->first();
                if ($ref) {
                    $imageUrl = rtrim($ref->path, '/') . '/' . $ref->name;
                } elseif (str_starts_with($digitalObject->mime_type ?? '', 'image/')) {
                    $imageUrl = rtrim($digitalObject->path, '/') . '/' . $digitalObject->name;
                }
            }
        }

        // Detect 3D model
        $has3DModel = false;
        $model3D = null;
        try {
            $model3d = DB::table('object_3d_model')
                ->where('object_id', $io->id)
                ->where('is_public', 1)
                ->orderByDesc('is_primary')
                ->orderBy('display_order')
                ->first();
            if ($model3d) {
                $has3DModel = true;
                $model3D = $model3d;
            }
        } catch (\Exception $e) {}

        // Fallback: detect 3D digital objects
        if (!$has3DModel && $digitalObject) {
            $threeDExts = ['glb', 'gltf', 'obj', 'stl', 'usdz', 'ply'];
            $doExt = strtolower(pathinfo($digitalObject->name ?? '', PATHINFO_EXTENSION));
            if (in_array($doExt, $threeDExts)) {
                $has3DModel = true;
                $model3D = (object) [
                    'id' => $digitalObject->id,
                    'object_id' => $io->id,
                    'filename' => $digitalObject->name,
                    'original_filename' => $digitalObject->name,
                    'file_path' => $digitalObject->path,
                    'format' => $doExt ?: 'glb',
                    'auto_rotate' => 1,
                    'shadow_intensity' => '1.00',
                    'background_color' => '#1a1a2e',
                ];
            }
        }

        return view('ahg-io-manage::research.annotations', compact('io', 'annotations', 'imageUrl', 'digitalObject', 'has3DModel', 'model3D'));
    }

    /**
     * AJAX endpoint for annotation V2 CRUD (create, update, delete).
     * Migrated from ahgResearchPlugin createAnnotationV2 action.
     */
    public function annotationV2Api(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $researcher = DB::table('research_researcher')->where('user_id', auth()->id())->first();
        if (!$researcher) {
            return response()->json(['error' => 'Not a researcher'], 403);
        }

        $body = $request->json()->all();

        // Delete annotation
        if (!empty($body['delete_annotation'])) {
            $annId = (int) ($body['annotation_id'] ?? 0);
            DB::table('research_annotation_target')->where('annotation_id', $annId)->delete();
            DB::table('research_annotation_v2')->where('id', $annId)->where('researcher_id', $researcher->id)->delete();
            return response()->json(['success' => true]);
        }

        // Update annotation
        if (!empty($body['update_annotation'])) {
            $annId = (int) ($body['annotation_id'] ?? 0);
            $update = [];
            if (isset($body['body'])) {
                $update['body_json'] = json_encode($body['body']);
            }
            if (isset($body['motivation'])) {
                $update['motivation'] = $body['motivation'];
            }
            if (!empty($update)) {
                $update['updated_at'] = now();
                DB::table('research_annotation_v2')->where('id', $annId)->update($update);
            }
            return response()->json(['success' => true]);
        }

        // Create annotation
        $annId = DB::table('research_annotation_v2')->insertGetId([
            'researcher_id' => $researcher->id,
            'motivation' => $body['motivation'] ?? 'commenting',
            'body_json' => isset($body['body']) ? json_encode($body['body']) : null,
            'visibility' => $body['visibility'] ?? 'private',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create targets
        $targets = $body['targets'] ?? [];
        foreach ($targets as $target) {
            DB::table('research_annotation_target')->insert([
                'annotation_id' => $annId,
                'source_type' => $target['source_type'] ?? 'information_object',
                'source_id' => (int) ($target['source_id'] ?? 0),
                'selector_type' => $target['selector_type'] ?? null,
                'selector_json' => isset($target['selector_json']) ? json_encode($target['selector_json']) : null,
                'created_at' => now(),
            ]);
        }

        return response()->json(['success' => true, 'id' => $annId]);
    }

    /**
     * Export annotations as IIIF annotation list (JSON-LD).
     */
    public function exportAnnotationsIIIF(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) abort(404);

        $annotationIds = DB::table('research_annotation_target')
            ->where('source_type', 'information_object')
            ->where('source_id', $io->id)
            ->pluck('annotation_id')
            ->unique();

        $items = [];
        foreach ($annotationIds as $annId) {
            $ann = DB::table('research_annotation_v2')->where('id', $annId)->first();
            if (!$ann) continue;
            $targets = DB::table('research_annotation_target')->where('annotation_id', $annId)->get();
            $bodyData = json_decode($ann->body_json ?? '{}', true);

            $targetItems = [];
            foreach ($targets as $t) {
                $item = ['type' => 'SpecificResource', 'source' => url('/' . $io->slug)];
                if ($t->selector_type) {
                    $sel = json_decode($t->selector_json ?? '{}', true);
                    $item['selector'] = array_merge(['type' => $t->selector_type], $sel);
                }
                $targetItems[] = $item;
            }

            $items[] = [
                '@context' => 'http://www.w3.org/ns/anno.jsonld',
                'id' => url('/research/annotation-v2/' . $annId),
                'type' => 'Annotation',
                'motivation' => $ann->motivation ?? 'commenting',
                'body' => $bodyData,
                'target' => count($targetItems) === 1 ? $targetItems[0] : $targetItems,
                'created' => $ann->created_at,
            ];
        }

        return response()->json([
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => url('/research/annotations/' . $io->slug . '/export-iiif'),
            'type' => 'AnnotationPage',
            'items' => $items,
        ], 200, ['Content-Type' => 'application/ld+json']);
    }

    /**
     * Trust score for an IO.
     * Migrated from ahgResearchPlugin trustScore action + TrustScoringService.
     */
    public function trustScore(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        // Get latest assessment
        $assessment = DB::table('research_source_assessment as sa')
            ->leftJoin('research_researcher as r', 'sa.researcher_id', '=', 'r.id')
            ->where('sa.object_id', $io->id)
            ->select('sa.*', 'r.first_name as assessor_first_name', 'r.last_name as assessor_last_name')
            ->orderByDesc('sa.assessed_at')
            ->first();

        // Get assessment history
        $assessmentHistory = DB::table('research_source_assessment as sa')
            ->leftJoin('research_researcher as r', 'sa.researcher_id', '=', 'r.id')
            ->where('sa.object_id', $io->id)
            ->select('sa.*', 'r.first_name as assessor_first_name', 'r.last_name as assessor_last_name')
            ->orderByDesc('sa.assessed_at')
            ->get()
            ->toArray();

        // Get quality metrics
        $qualityMetrics = DB::table('research_quality_metric')
            ->where('object_id', $io->id)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();

        // Compute trust score (same algorithm as TrustScoringService)
        $sourceWeights = ['primary' => 40, 'secondary' => 25, 'tertiary' => 10];
        $completenessWeights = ['complete' => 30, 'partial' => 20, 'fragment' => 10, 'missing_pages' => 15, 'redacted' => 15];

        $sourceWeight = $sourceWeights[$assessment->source_type ?? ''] ?? 0;
        $completenessWeight = $completenessWeights[$assessment->completeness ?? ''] ?? 0;

        $qualityScore = 0;
        $qualityCount = count($qualityMetrics);
        if ($qualityCount > 0) {
            $sum = 0;
            foreach ($qualityMetrics as $m) {
                $sum += (float) $m->metric_value;
            }
            $avg = $sum / $qualityCount;
            $qualityScore = (int) round(max(0, min(1, $avg)) * 30);
        }

        $score = $assessment ? max(0, min(100, $sourceWeight + $completenessWeight + $qualityScore)) : 0;

        return view('ahg-io-manage::research.trust', compact(
            'io', 'score', 'assessment', 'assessmentHistory', 'qualityMetrics',
            'sourceWeight', 'completenessWeight', 'qualityScore', 'qualityCount'
        ));
    }

    /**
     * Research dashboard.
     */
    public function dashboard()
    {
        return view('ahg-io-manage::research.dashboard');
    }

    /**
     * Generate citation for an IO.
     * Migrated from ahgResearchPlugin citation action + ahgDoiPlugin.
     */
    public function citation(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $culture = app()->getLocale();

        // Get creators
        $creators = DB::table('event')
            ->join('actor_i18n', function ($j) use ($culture) {
                $j->on('actor_i18n.id', '=', 'event.actor_id')->where('actor_i18n.culture', $culture);
            })
            ->where('event.object_id', $io->id)
            ->where('event.type_id', 111)
            ->select('actor_i18n.authorized_form_of_name as name')
            ->get();

        // Get repository
        $repository = DB::table('information_object as io2')
            ->join('actor_i18n as repo_ai', function ($j) use ($culture) {
                $j->on('repo_ai.id', '=', 'io2.repository_id')->where('repo_ai.culture', $culture);
            })
            ->where('io2.id', $io->id)
            ->select('repo_ai.authorized_form_of_name as name')
            ->first();

        // Get dates (date_display is in event_i18n.date, not event table)
        $dates = DB::table('event')
            ->join('event_i18n', function ($j) use ($culture) {
                $j->on('event.id', '=', 'event_i18n.id')->where('event_i18n.culture', '=', $culture);
            })
            ->where('event.object_id', $io->id)
            ->whereNotNull('event_i18n.date')
            ->select('event_i18n.date as date_display')
            ->first();

        return view('ahg-io-manage::research.citation', [
            'io' => $io,
            'creators' => $creators,
            'repository' => $repository,
            'dates' => $dates,
        ]);
    }

    private function getIO(string $slug): ?object
    {
        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('s.slug', $slug)
            ->select('io.id', 'i18n.title', 'i18n.scope_and_content', 's.slug')
            ->first();
    }
}
