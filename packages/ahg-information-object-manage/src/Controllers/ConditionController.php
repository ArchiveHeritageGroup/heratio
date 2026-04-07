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
        $dir = config('heratio.storage_path') . '/uploads/condition_photos';
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
        // Check both condition_image and spectrum_condition_photo tables
        $photo = DB::table('condition_image')->where('id', $id)->first();
        $table = 'condition_image';

        if (!$photo) {
            $photo = DB::table('spectrum_condition_photo')->where('id', $id)->first();
            $table = 'spectrum_condition_photo';
        }

        if (!$photo) {
            return response()->json(['success' => false, 'error' => 'Photo not found'], 404);
        }

        if ($request->isMethod('post')) {
            $annotations = $request->input('annotations', []);
            DB::table($table)->where('id', $id)->update([
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
     * AI Condition Assessment — POST JSON endpoint.
     * Calls the AI condition service on server 78.
     */
    public function aiAssess(Request $request)
    {
        $photoId = (int) $request->input('photo_id');
        $objectId = (int) $request->input('object_id');

        if (!$photoId) {
            return response()->json(['success' => false, 'error' => 'photo_id required']);
        }

        // Find the photo in either table
        $photo = DB::table('spectrum_condition_photo')->where('id', $photoId)->first();
        if (!$photo) {
            $photo = DB::table('condition_image')->where('id', $photoId)->first();
        }
        if (!$photo) {
            return response()->json(['success' => false, 'error' => 'Photo not found']);
        }

        $filePath = $photo->file_path ?? ('/uploads/condition_photos/' . ($photo->filename ?? ''));
        $fullPath = config('heratio.storage_path') . $filePath;

        if (!file_exists($fullPath)) {
            return response()->json(['success' => false, 'error' => 'Image file not found: ' . $filePath]);
        }

        // Call Ollama LLaVA vision model for condition assessment
        try {
            $ollamaUrl = DB::table('ahg_settings')
                ->where('setting_key', 'voice_local_llm_url')
                ->value('setting_value') ?: 'http://localhost:11434';
            $model = DB::table('ahg_settings')
                ->where('setting_key', 'voice_local_llm_model')
                ->value('setting_value') ?: 'llava:7b';

            $materialType = $request->input('material_type', 'unknown');
            $materialHint = $materialType !== 'unknown' ? "The object is made of {$materialType}. " : '';

            $prompt = "You are a professional conservator assessing the physical condition of a cultural heritage object from a photograph. {$materialHint}Analyze this image and provide a structured condition assessment.\n\nRespond in EXACTLY this format (one item per line):\n\nRATING: [one of: excellent, good, fair, poor, critical]\nSEVERITY: [one of: minor, moderate, severe, critical]\nDAMAGE: [comma-separated list from: tear, stain, foxing, fading, water_damage, mold, pest_damage, abrasion, brittleness, loss, crack, corrosion, discolouration, deformation, dust, none]\nDESCRIPTION: [2-3 sentences describing the visible condition]\nRECOMMENDATIONS: [1-2 sentences on conservation treatment needed]\n\nBe specific about what you observe. If the object appears in good condition with no visible damage, say so. Do not invent damage that is not visible.";

            $imageBase64 = base64_encode(file_get_contents($fullPath));

            $ch = curl_init($ollamaUrl . '/api/generate');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $model,
                    'prompt' => $prompt,
                    'images' => [$imageBase64],
                    'stream' => false,
                    'options' => [
                        'temperature' => 0,
                        'seed' => 42,
                    ],
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ollama returned HTTP ' . $httpCode . ($curlError ? ' (' . $curlError . ')' : ''),
                ]);
            }

            $ollamaResult = json_decode($response, true);
            $rawText = $ollamaResult['response'] ?? '';

            // Parse line-based response (matching AtoM ConditionAIService::parseResponse)
            $validRatings = ['excellent', 'good', 'fair', 'poor', 'critical'];
            $validSeverities = ['minor', 'moderate', 'severe', 'critical'];
            $validDamageTypes = ['tear','stain','foxing','fading','water_damage','mold','pest_damage','abrasion','brittleness','loss','crack','corrosion','discolouration','deformation','dust'];
            $damageSynonyms = [
                'water'=>'water_damage','moisture'=>'water_damage','wet'=>'water_damage',
                'mould'=>'mold','fungus'=>'mold','fungal'=>'mold',
                'insect'=>'pest_damage','pest'=>'pest_damage','bug'=>'pest_damage',
                'fade'=>'fading','faded'=>'fading',
                'torn'=>'tear','rip'=>'tear','split'=>'tear',
                'rust'=>'corrosion','oxidation'=>'corrosion','tarnish'=>'corrosion',
                'brittle'=>'brittleness','fragile'=>'brittleness',
                'missing'=>'loss','lacuna'=>'loss',
                'warp'=>'deformation','warped'=>'deformation','buckle'=>'deformation',
                'discolor'=>'discolouration','yellowing'=>'discolouration',
                'dirty'=>'dust','grime'=>'dust','soiled'=>'dust',
            ];

            $result = [
                'success' => true,
                'overall_rating' => 'fair',
                'severity' => 'moderate',
                'damage_types' => [],
                'description' => '',
                'recommendations' => '',
            ];

            foreach (explode("\n", $rawText) as $line) {
                $line = trim($line);
                if (preg_match('/^RATING:\s*(.+)/i', $line, $m)) {
                    $r = strtolower(trim($m[1]));
                    if (in_array($r, $validRatings)) $result['overall_rating'] = $r;
                } elseif (preg_match('/^SEVERITY:\s*(.+)/i', $line, $m)) {
                    $s = strtolower(trim($m[1]));
                    if (in_array($s, $validSeverities)) $result['severity'] = $s;
                } elseif (preg_match('/^DAMAGE:\s*(.+)/i', $line, $m)) {
                    $damages = array_map('trim', explode(',', strtolower($m[1])));
                    foreach ($damages as $d) {
                        $d = str_replace(' ', '_', $d);
                        if ($d === 'none') continue;
                        $matched = in_array($d, $validDamageTypes) ? $d : ($damageSynonyms[$d] ?? null);
                        if ($matched) {
                            $result['damage_types'][] = [
                                'type' => $matched,
                                'severity' => $result['severity'],
                            ];
                        }
                    }
                } elseif (preg_match('/^DESCRIPTION:\s*(.+)/i', $line, $m)) {
                    $result['description'] = trim($m[1]);
                } elseif (preg_match('/^RECOMMENDATIONS?:\s*(.+)/i', $line, $m)) {
                    $result['recommendations'] = trim($m[1]);
                }
            }

            if (empty($result['description'])) {
                $result['description'] = $rawText;
            }

            // Save as a condition_report (FK target for condition_damage)
            $resolvedObjectId = $objectId ?: (isset($photo->condition_check_id)
                ? DB::table('spectrum_condition_check')->where('id', $photo->condition_check_id)->value('object_id')
                : null);

            $reportId = DB::table('condition_report')->insertGetId([
                'information_object_id' => $resolvedObjectId,
                'assessor_user_id' => auth()->id(),
                'assessment_date' => now()->toDateString(),
                'context' => 'ai_assessment',
                'overall_rating' => $result['overall_rating'],
                'summary' => $result['description'],
                'recommendations' => $result['recommendations'],
                'priority' => match($result['severity']) {
                    'minor' => 'low', 'moderate' => 'normal', 'severe' => 'high', 'critical' => 'urgent', default => 'normal',
                },
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Save damage records (FK references condition_report.id)
            foreach ($result['damage_types'] as $dmg) {
                DB::table('condition_damage')->insert([
                    'condition_report_id' => $reportId,
                    'damage_type' => $dmg['type'],
                    'severity' => $dmg['severity'] ?? $result['severity'],
                    'location' => 'overall',
                    'treatment_required' => $result['severity'] !== 'minor' ? 1 : 0,
                    'created_at' => now(),
                ]);
            }

            $result['condition_check_id'] = $reportId;

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'AI service error: ' . $e->getMessage()]);
        }
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
