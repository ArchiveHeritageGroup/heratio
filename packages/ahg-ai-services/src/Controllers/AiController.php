<?php

namespace AhgAiServices\Controllers;

use AhgAiServices\Services\HtrService;
use AhgAiServices\Services\LlmService;
use AhgAiServices\Services\NerService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AI Services Controller
 *
 * Dashboard, configuration, and AJAX endpoints for AI services:
 * summarize, translate, NER, description suggestion, spellcheck.
 *
 * Ported from ahgAIPlugin aiActions.
 */
class AiController extends Controller
{
    private LlmService $llmService;
    private NerService $nerService;
    private HtrService $htrService;

    public function __construct(LlmService $llmService, NerService $nerService, HtrService $htrService)
    {
        $this->llmService = $llmService;
        $this->nerService = $nerService;
        $this->htrService = $htrService;
    }

    /**
     * AI Services dashboard with provider status and usage stats.
     */
    public function index()
    {
        $configs      = $this->llmService->getConfigurations();
        $defaultConfig = $this->llmService->getDefaultConfig();
        $usageStats   = $this->llmService->getUsageStats();
        $nerStats     = $this->nerService->getStats();
        $apiHealth    = $this->nerService->getApiHealth();

        // Provider health for active configs
        $providerHealth = $this->llmService->getAllHealth();

        // AI settings summary
        $generalSettings = $this->llmService->getAiSettingsByFeature('general');

        return view('ahg-ai-services::index', compact(
            'configs',
            'defaultConfig',
            'usageStats',
            'nerStats',
            'apiHealth',
            'providerHealth',
            'generalSettings'
        ));
    }

    /**
     * AI Configuration page (GET/POST).
     */
    public function config(Request $request)
    {
        if ($request->isMethod('post')) {
            return $this->saveConfig($request);
        }

        $configs       = $this->llmService->getConfigurations();
        $defaultConfig = $this->llmService->getDefaultConfig();

        // General AI settings
        $generalSettings = DB::table('ahg_ai_settings')
            ->where('feature', 'general')
            ->get()
            ->keyBy('setting_key');

        // NER settings
        $nerSettings = DB::table('ahg_ai_settings')
            ->where('feature', 'ner')
            ->get()
            ->keyBy('setting_key');

        // Summarize settings
        $summarizeSettings = DB::table('ahg_ai_settings')
            ->where('feature', 'summarize')
            ->get()
            ->keyBy('setting_key');

        // Translate settings
        $translateSettings = DB::table('ahg_ai_settings')
            ->where('feature', 'translate')
            ->get()
            ->keyBy('setting_key');

        // Spellcheck settings
        $spellcheckSettings = DB::table('ahg_ai_settings')
            ->where('feature', 'spellcheck')
            ->get()
            ->keyBy('setting_key');

        // Suggest settings
        $suggestSettings = DB::table('ahg_ai_settings')
            ->where('feature', 'suggest')
            ->get()
            ->keyBy('setting_key');

        return view('ahg-ai-services::config', compact(
            'configs',
            'defaultConfig',
            'generalSettings',
            'nerSettings',
            'summarizeSettings',
            'translateSettings',
            'spellcheckSettings',
            'suggestSettings'
        ));
    }

    /**
     * Save AI configuration (POST handler).
     */
    private function saveConfig(Request $request): \Illuminate\Http\RedirectResponse
    {
        $action = $request->input('_action');

        // Handle LLM config CRUD
        if ($action === 'create_config') {
            $request->validate([
                'provider' => 'required|in:openai,anthropic,ollama',
                'name'     => 'required|string|max:100',
                'model'    => 'required|string|max:100',
            ]);

            $this->llmService->createConfiguration($request->only([
                'provider', 'name', 'is_active', 'is_default',
                'endpoint_url', 'api_key', 'model', 'max_tokens',
                'temperature', 'timeout_seconds',
            ]));

            return redirect()->route('admin.ai.config')
                ->with('success', 'LLM configuration created.');
        }

        if ($action === 'update_config') {
            $request->validate([
                'config_id' => 'required|integer',
                'name'      => 'required|string|max:100',
                'model'     => 'required|string|max:100',
            ]);

            $this->llmService->updateConfiguration(
                (int) $request->input('config_id'),
                $request->only([
                    'name', 'is_active', 'is_default',
                    'endpoint_url', 'api_key', 'model', 'max_tokens',
                    'temperature', 'timeout_seconds',
                ])
            );

            return redirect()->route('admin.ai.config')
                ->with('success', 'LLM configuration updated.');
        }

        if ($action === 'delete_config') {
            $this->llmService->deleteConfiguration((int) $request->input('config_id'));

            return redirect()->route('admin.ai.config')
                ->with('success', 'LLM configuration deleted.');
        }

        // Handle AI settings save
        if ($action === 'save_settings') {
            $features = ['general', 'ner', 'summarize', 'translate', 'spellcheck', 'suggest'];

            foreach ($features as $feature) {
                $settingsKey = "settings_{$feature}";
                $featureSettings = $request->input($settingsKey, []);

                if (is_array($featureSettings)) {
                    foreach ($featureSettings as $key => $value) {
                        $this->llmService->saveAiSetting($feature, $key, $value);
                    }
                }
            }

            return redirect()->route('admin.ai.config')
                ->with('success', 'AI settings saved.');
        }

        return redirect()->route('admin.ai.config')
            ->with('error', 'Unknown action.');
    }

    /**
     * POST: Summarize text (AJAX/JSON).
     */
    public function summarize(Request $request)
    {
        $request->validate([
            'text'       => 'required|string|min:10',
            'max_length' => 'nullable|integer|min:50|max:5000',
        ]);

        $text      = $request->input('text');
        $maxLength = $request->input('max_length', 200);

        $startTime = microtime(true);
        $result    = $this->llmService->summarize($text, $maxLength);
        $elapsed   = round((microtime(true) - $startTime) * 1000);

        if ($result === null) {
            return response()->json([
                'success' => false,
                'error'   => 'Summarization failed. Check LLM configuration.',
            ], 500);
        }

        return response()->json([
            'success'            => true,
            'summary'            => $result,
            'processing_time_ms' => $elapsed,
        ]);
    }

    /**
     * POST: Translate text (AJAX/JSON).
     */
    public function translate(Request $request)
    {
        $request->validate([
            'text'        => 'required|string|min:1',
            'target_lang' => 'required|string|max:10',
        ]);

        $text       = $request->input('text');
        $targetLang = $request->input('target_lang');

        // Try the dedicated translation API first
        $translationEndpoint = $this->llmService->getAiSetting('translate', 'mt.endpoint')
            ?? $this->llmService->getAiSetting('translate', 'mt_endpoint');

        if ($translationEndpoint) {
            try {
                $mtApiKey = $this->llmService->getAiSetting('translate', 'mt.api_key', '');
                $mtTimeout = (int) $this->llmService->getAiSetting('translate', 'mt.timeout_seconds', '60');
                $sourceLang = $this->llmService->getAiSetting('translate', 'translation_source_lang', 'en');

                $response = \Illuminate\Support\Facades\Http::timeout($mtTimeout)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-API-Key'    => $mtApiKey,
                    ])
                    ->post($translationEndpoint, [
                        'text'        => $text,
                        'source_lang' => $sourceLang,
                        'target_lang' => $targetLang,
                    ]);

                if ($response->successful()) {
                    $body = $response->json();
                    if (!empty($body['success']) && !empty($body['translation'])) {
                        return response()->json([
                            'success'     => true,
                            'translation' => $body['translation'],
                            'source'      => 'api',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // Fall through to LLM
            }
        }

        $startTime   = microtime(true);
        $translation = $this->llmService->translate($text, $targetLang);
        $elapsed     = round((microtime(true) - $startTime) * 1000);

        if ($translation === null) {
            return response()->json([
                'success' => false,
                'error'   => 'Translation failed. Check LLM configuration.',
            ], 500);
        }

        return response()->json([
            'success'            => true,
            'translation'        => $translation,
            'source'             => 'llm',
            'processing_time_ms' => $elapsed,
        ]);
    }

    /**
     * POST: Extract named entities (AJAX/JSON).
     */
    public function extractEntities(Request $request)
    {
        $request->validate([
            'text'      => 'required|string|min:10',
            'object_id' => 'nullable|integer',
        ]);

        $text     = $request->input('text');
        $objectId = $request->input('object_id');

        $startTime = microtime(true);
        $entities  = $this->nerService->extract($text);
        $elapsed   = round((microtime(true) - $startTime) * 1000);

        $entityCount = count($entities['persons'])
            + count($entities['organizations'])
            + count($entities['places'])
            + count($entities['dates']);

        // If an object ID is given, store entities for review
        $stored = 0;
        if ($objectId) {
            $stored = $this->nerService->createAccessPoints($objectId, $entities);
        }

        return response()->json([
            'success'            => true,
            'entities'           => $entities,
            'entity_count'       => $entityCount,
            'stored_count'       => $stored,
            'processing_time_ms' => $elapsed,
        ]);
    }

    /**
     * POST: Generate description suggestion (AJAX/JSON).
     */
    public function suggestDescription(Request $request)
    {
        $request->validate([
            'title'   => 'required|string|min:2',
            'context' => 'nullable|string',
        ]);

        $title   = $request->input('title');
        $context = $request->input('context', '');

        $startTime = microtime(true);
        $result    = $this->llmService->suggestDescription($title, $context);
        $elapsed   = round((microtime(true) - $startTime) * 1000);

        if ($result === null) {
            return response()->json([
                'success' => false,
                'error'   => 'Description suggestion failed. Check LLM configuration.',
            ], 500);
        }

        return response()->json([
            'success'            => true,
            'description'        => $result,
            'processing_time_ms' => $elapsed,
        ]);
    }

    /**
     * POST: Spellcheck text (AJAX/JSON).
     */
    public function spellcheck(Request $request)
    {
        $request->validate([
            'text' => 'required|string|min:5',
        ]);

        $text = $request->input('text');

        $startTime   = microtime(true);
        $corrections = $this->llmService->spellcheck($text);
        $elapsed     = round((microtime(true) - $startTime) * 1000);

        return response()->json([
            'success'            => true,
            'corrections'        => $corrections,
            'correction_count'   => count($corrections),
            'processing_time_ms' => $elapsed,
        ]);
    }

    /**
     * POST: Test LLM connection (AJAX/JSON).
     */
    public function testConnection(Request $request)
    {
        $configId = $request->input('config_id');

        $result = $this->llmService->testConnection($configId ? (int) $configId : null);

        return response()->json($result);
    }

    public function batch(Request $request)
    {
        $rows = DB::table('job')->where('name', 'LIKE', '%ai%')->orderByDesc('created_at')->get();
        return view('ahg-ai-services::batch', ['rows' => $rows]);
    }

    public function batchView(int $id)
    {
        $record = DB::table('job')->where('id', $id)->first();
        if (!$record) abort(404);
        return view('ahg-ai-services::batch-view', ['record' => $record]);
    }

    public function pdfOverlay(Request $request)
    {
        return view('ahg-ai-services::pdf-overlay');
    }

    public function review(Request $request)
    {
        return view('ahg-ai-services::review', ['rows' => collect()]);
    }

    public function suggestReview(Request $request)
    {
        return view('ahg-ai-services::suggest-review', ['rows' => collect()]);
    }

    public function conditionAssess(Request $request) { return view('ahg-ai-services::condition-assess', ['rows' => collect()]); }

    public function conditionBrowse(Request $request) { return view('ahg-ai-services::condition-browse', ['rows' => collect()]); }

    public function conditionBulk(Request $request) { return view('ahg-ai-services::condition-bulk'); }

    public function conditionClients(Request $request) { return view('ahg-ai-services::condition-clients', ['rows' => collect()]); }

    public function conditionDashboard() { return view('ahg-ai-services::condition-dashboard', ['completedCount'=>0,'pendingCount'=>0,'criticalCount'=>0]); }

    public function conditionHistory(Request $request) { return view('ahg-ai-services::condition-history', ['rows' => collect()]); }

    public function conditionManualAssess(Request $request) { return view('ahg-ai-services::condition-manual-assess', ['record' => (object)[]]); }

    public function conditionTraining(Request $request) { return view('ahg-ai-services::condition-training'); }

    public function conditionView(int $id) { return view('ahg-ai-services::condition-view', ['record' => (object)['id'=>$id]]); }

    /* ------------------------------------------------------------------ */
    /*  HTR — Vital Records Handwritten Text Recognition                  */
    /* ------------------------------------------------------------------ */

    public function htrDashboard()
    {
        $health = $this->htrService->health();
        return view('ahg-ai-services::htr.dashboard', compact('health'));
    }

    public function htrExtract()
    {
        return view('ahg-ai-services::htr.extract');
    }

    public function htrDoExtract(Request $request)
    {
        $request->validate([
            'file'     => 'required|file|mimes:jpg,jpeg,png,tiff,tif,pdf|max:20480',
            'doc_type' => 'nullable|string|in:auto,type_a,type_b,type_c',
            'formats'  => 'nullable|array',
        ]);

        $file    = $request->file('file');
        $docType = $request->input('doc_type', 'auto');
        if ($docType === 'auto') $docType = 'type_a';

        // Save uploaded image for display on results page
        $jobId = 'htr_' . time() . '_' . mt_rand(1000, 9999);
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $imgName = $jobId . '.' . $ext;
        $imgDir = storage_path('app/private/htr-extracts');
        if (!is_dir($imgDir)) @mkdir($imgDir, 0777, true);
        $imgPath = $imgDir . '/' . $imgName;
        copy($file->getRealPath(), $imgPath);

        // Use local template extractor (bounding boxes from annotations)
        $script = <<<PY
import sys, json
sys.path.insert(0, '/opt/ahg-ai/htr')
from extractor import extract_and_ocr
result = extract_and_ocr(sys.argv[1], sys.argv[2])
print(json.dumps(result))
PY;
        $tmpScript = tempnam('/tmp', 'extract_') . '.py';
        file_put_contents($tmpScript, $script);
        $escaped = escapeshellarg($imgPath);
        $escapedType = escapeshellarg($docType);
        $output = shell_exec("python3 {$tmpScript} {$escaped} {$escapedType} 2>&1");
        @unlink($tmpScript);

        $results = json_decode(trim($output ?: '{}'), true);

        if (!$results || !($results['success'] ?? false)) {
            \Log::warning('HTR local extractor failed', [
                'output' => substr($output ?? '', 0, 500),
                'imgPath' => $imgPath,
                'exists' => file_exists($imgPath),
            ]);
            // Fallback to remote service
            $results = $this->htrService->extract($imgPath, $docType, 'all');
            if (!$results) {
                @unlink($imgPath);
                return redirect()->route('admin.ai.htr.extract')
                    ->with('error', 'HTR extraction failed. ' . ($results['error'] ?? 'Service may be offline.'));
            }
        }

        $results['job_id'] = $jobId;
        $results['image_name'] = $imgName;
        $results['doc_type'] = $docType;

        // Normalize bbox keys (width→w, height→h) for consistency
        foreach ($results['fields'] ?? [] as &$field) {
            if (isset($field['bbox'])) {
                if (isset($field['bbox']['width']) && !isset($field['bbox']['w'])) {
                    $field['bbox']['w'] = $field['bbox']['width'];
                }
                if (isset($field['bbox']['height']) && !isset($field['bbox']['h'])) {
                    $field['bbox']['h'] = $field['bbox']['height'];
                }
            }
        }
        unset($field);

        session()->put("htr_results_{$jobId}", $results);

        return redirect()->route('admin.ai.htr.results', $jobId);
    }

    public function htrResults(string $jobId)
    {
        $results = session("htr_results_{$jobId}", []);
        return view('ahg-ai-services::htr.results', compact('results', 'jobId'));
    }

    /**
     * Serve an extracted image for display on results page.
     */
    public function htrExtractImage(string $jobId)
    {
        $results = session("htr_results_{$jobId}", []);
        $imgName = $results['image_name'] ?? '';
        $imgPath = storage_path('app/private/htr-extracts/' . $imgName);
        if (!$imgName || !file_exists($imgPath)) {
            abort(404);
        }
        return response()->file($imgPath);
    }

    public function htrDownload(string $jobId, string $fmt)
    {
        $response = $this->htrService->downloadOutput($jobId, $fmt);

        if (!$response || !$response->successful()) {
            return redirect()->route('admin.ai.htr.results', $jobId)
                ->with('error', 'Download failed.');
        }

        $contentType = $response->header('Content-Type') ?? 'application/octet-stream';
        $filename    = "htr-{$jobId}.{$fmt}";

        return response($response->body(), 200)
            ->header('Content-Type', $contentType)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function htrBatch()
    {
        return view('ahg-ai-services::htr.batch');
    }

    public function htrDoBatch(Request $request)
    {
        $request->validate([
            'files'  => 'required|array|min:1',
            'files.*' => 'file|mimes:jpg,jpeg,png,tiff,tif,pdf|max:20480',
            'format' => 'nullable|string|in:csv,json,gedcom',
        ]);

        $paths = [];
        foreach ($request->file('files') as $file) {
            $tmpPath = $file->store('htr-uploads', 'local');
            $paths[] = storage_path('app/private/' . $tmpPath);
        }

        $format = $request->input('format', 'csv');
        $batchResults = $this->htrService->batch($paths, $format);

        foreach ($paths as $p) {
            @unlink($p);
        }

        if (!$batchResults) {
            return redirect()->route('admin.ai.htr.batch')
                ->with('error', 'Batch processing failed. The service may be offline.');
        }

        return view('ahg-ai-services::htr.batch', compact('batchResults'));
    }

    public function htrSources()
    {
        $data = $this->htrService->sources();

        $sources = $data['sources'] ?? [];
        $trainingStats = $data['training_stats'] ?? ['type_a' => 0, 'type_b' => 0, 'type_c' => 0];
        $fsConfigured = $data['familysearch_configured'] ?? false;

        // Get download jobs
        $jobsData = $this->htrService->downloadJobs();
        $jobs = $jobsData['jobs'] ?? [];

        return view('ahg-ai-services::htr.sources', compact('sources', 'trainingStats', 'fsConfigured', 'jobs'));
    }

    public function htrSaveFsConfig(Request $request)
    {
        $request->validate([
            'fs_client_id' => 'required|string|max:255',
            'fs_username' => 'required|string|max:255',
            'fs_password' => 'required|string|max:255',
        ]);

        // Update .env file
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        $mappings = [
            'FAMILYSEARCH_CLIENT_ID' => $request->input('fs_client_id'),
            'FAMILYSEARCH_USERNAME' => $request->input('fs_username'),
            'FAMILYSEARCH_PASSWORD' => $request->input('fs_password'),
        ];

        foreach ($mappings as $key => $value) {
            if (preg_match("/^{$key}=.*/m", $envContent)) {
                $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $envContent);
            } else {
                $envContent .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envPath, $envContent);

        return redirect()->route('admin.ai.htr.sources')
            ->with('success', 'FamilySearch credentials saved to .env. Restart the HTR service to apply.');
    }

    public function htrAnnotate()
    {
        return view('ahg-ai-services::htr.annotate');
    }

    /**
     * Skip an image — move it to a rework/ folder for later review.
     */
    /**
     * Crop OCR — recognize text in a bounding box region of an image.
     * Used by the annotate UI for hybrid pre-fill.
     */
    public function htrCropOcr(Request $request)
    {
        $imagePath = $request->input('image_path', '');
        $bbox = $request->input('bbox', []);

        if (!$imagePath || empty($bbox)) {
            return response()->json(['success' => false, 'error' => 'image_path and bbox required'], 400);
        }

        if (!file_exists($imagePath)) {
            return response()->json(['success' => false, 'error' => 'Image not found'], 404);
        }

        try {
            $htrUrl = config('services.htr.url', 'http://localhost:5006');
            $response = \Illuminate\Support\Facades\Http::timeout(30)->post("{$htrUrl}/crop-ocr", [
                'image_path' => $imagePath,
                'bbox' => $bbox,
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json(['success' => false, 'error' => 'HTR service error: ' . $response->status()], 502);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Bulk Annotate — page view.
     */
    public function htrBulkAnnotate()
    {
        return view('ahg-ai-services::htr.bulk-annotate');
    }

    /**
     * FS Overlay Annotate — overlay field labels on document images.
     * Cloned from bulk-annotate for overlay positioning tests.
     */
    public function htrFsOverlay()
    {
        return view('ahg-ai-services::htr.fs-overlay');
    }

    /**
     * FS Overlay — save field positions per form type (server-side, shared across PCs).
     */
    public function htrFsOverlaySavePositions(Request $request)
    {
        $formType = $request->input('form_type', '');
        $positions = $request->input('positions', []);

        if (!$formType) {
            return response()->json(['success' => false, 'error' => 'No form type']);
        }

        $file = storage_path('app/fs-overlay-positions.json');
        $all = [];
        if (file_exists($file)) {
            $all = json_decode(file_get_contents($file), true) ?: [];
        }

        $all[$formType] = $positions;
        file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT));

        return response()->json(['success' => true]);
    }

    /**
     * FS Overlay — load field positions for a form type.
     */
    public function htrFsOverlayLoadPositions(Request $request)
    {
        $formType = $request->input('form_type', '');

        $file = storage_path('app/fs-overlay-positions.json');
        $all = [];
        if (file_exists($file)) {
            $all = json_decode(file_get_contents($file), true) ?: [];
        }

        $positions = $all[$formType] ?? [];

        return response()->json(['success' => true, 'positions' => $positions]);
    }

    /**
     * Serve an auto-cropped image — removes black borders (book binding, dark edges).
     * Scans pixel brightness from each edge to find the white form area.
     */
    public function htrServeCroppedImage(Request $request)
    {
        $path = $request->input('path', '');
        if (!$path || !file_exists($path)) {
            abort(404);
        }

        $cacheDir = storage_path('app/cropped-cache');
        @mkdir($cacheDir, 0777, true);
        $cacheKey = md5($path . filemtime($path));
        $cachePath = "{$cacheDir}/{$cacheKey}.jpg";

        if (!file_exists($cachePath)) {
            // Load image
            $im = @imagecreatefromjpeg($path);
            if (!$im) {
                $im = @imagecreatefrompng($path);
            }
            if (!$im) {
                return response()->file($path); // fallback: serve original
            }

            $w = imagesx($im);
            $h = imagesy($im);
            $threshold = 140; // brightness threshold (0-255)
            $samples = 20;    // more sample points for accuracy

            // Helper: get brightness at a pixel (works for both grayscale and RGB)
            $getBrightness = function($im, $x, $y) {
                $rgb = imagecolorat($im, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                return (int)(($r + $g + $b) / 3); // average for RGB, same value for grayscale
            };

            // Helper: get median brightness of sample points along a line
            $getMedian = function($values) {
                sort($values);
                $n = count($values);
                return $values[(int)($n / 2)];
            };

            // Scan from left
            $left = 0;
            for ($x = 0; $x < $w; $x++) {
                $bright = [];
                for ($s = 0; $s < $samples; $s++) {
                    $sy = (int)($h * 0.1 + ($h * 0.8 / $samples) * $s);
                    $bright[] = $getBrightness($im, $x, min($sy, $h - 1));
                }
                if ($getMedian($bright) > $threshold) { $left = $x; break; }
            }

            // Scan from right
            $right = $w - 1;
            for ($x = $w - 1; $x > $left; $x--) {
                $bright = [];
                for ($s = 0; $s < $samples; $s++) {
                    $sy = (int)($h * 0.1 + ($h * 0.8 / $samples) * $s);
                    $bright[] = $getBrightness($im, $x, min($sy, $h - 1));
                }
                if ($getMedian($bright) > $threshold) { $right = $x; break; }
            }

            // Scan from top (sample from the middle of the detected width)
            $midLeft = $left + (int)(($right - $left) * 0.2);
            $midRight = $left + (int)(($right - $left) * 0.8);
            $top = 0;
            for ($y = 0; $y < $h; $y++) {
                $bright = [];
                for ($s = 0; $s < $samples; $s++) {
                    $sx = (int)($midLeft + ($midRight - $midLeft) / $samples * $s);
                    $bright[] = $getBrightness($im, min($sx, $w - 1), $y);
                }
                if ($getMedian($bright) > $threshold) { $top = $y; break; }
            }

            // Scan from bottom
            $bottom = $h - 1;
            for ($y = $h - 1; $y > $top; $y--) {
                $bright = [];
                for ($s = 0; $s < $samples; $s++) {
                    $sx = (int)($midLeft + ($midRight - $midLeft) / $samples * $s);
                    $bright[] = $getBrightness($im, min($sx, $w - 1), $y);
                }
                if ($getMedian($bright) > $threshold) { $bottom = $y; break; }
            }

            // Add small padding
            $pad = 5;
            $left = max(0, $left - $pad);
            $top = max(0, $top - $pad);
            $right = min($w - 1, $right + $pad);
            $bottom = min($h - 1, $bottom + $pad);

            $cropW = $right - $left;
            $cropH = $bottom - $top;

            // Only crop if we're actually removing significant borders (> 5% per side)
            if ($cropW > $w * 0.5 && $cropH > $h * 0.5) {
                $cropped = imagecrop($im, ['x' => $left, 'y' => $top, 'width' => $cropW, 'height' => $cropH]);
                if ($cropped) {
                    imagejpeg($cropped, $cachePath, 92);
                    imagedestroy($cropped);
                } else {
                    imagejpeg($im, $cachePath, 92);
                }
            } else {
                imagejpeg($im, $cachePath, 92);
            }
            imagedestroy($im);
        }

        return response()->file($cachePath, ['Content-Type' => 'image/jpeg']);
    }

    /**
     * FS Overlay — manual crop: user draws a rectangle, server crops and replaces the cached image.
     */
    public function htrFsOverlayManualCrop(Request $request)
    {
        // Handle skip → move to rework folder
        if ($request->input('action') === 'skip') {
            $imagePath = $request->input('image_path', '');
            $folder = $request->input('folder', '');
            if (!$folder) $folder = dirname($imagePath);
            if ($imagePath && file_exists($imagePath)) {
                $reworkDir = $folder . '/rework';
                @mkdir($reworkDir, 0777, true);
                @rename($imagePath, $reworkDir . '/' . basename($imagePath));
            }
            return response()->json(['success' => true]);
        }

        $imagePath = $request->input('image_path', '');
        $x = (int)$request->input('x', 0);
        $y = (int)$request->input('y', 0);
        $w = (int)$request->input('w', 0);
        $h = (int)$request->input('h', 0);

        if (!$imagePath || !file_exists($imagePath)) {
            return response()->json(['success' => false, 'error' => 'Image not found']);
        }
        if ($w < 50 || $h < 50) {
            return response()->json(['success' => false, 'error' => 'Crop area too small']);
        }

        // Crop the original image
        $im = @imagecreatefromjpeg($imagePath) ?: @imagecreatefrompng($imagePath);
        if (!$im) {
            return response()->json(['success' => false, 'error' => 'Cannot load image']);
        }

        $crop = imagecrop($im, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
        imagedestroy($im);

        if (!$crop) {
            return response()->json(['success' => false, 'error' => 'Crop failed']);
        }

        // Overwrite the original image with the cropped version
        imagejpeg($crop, $imagePath, 92);

        // Also update the cache
        $cacheDir = storage_path('app/cropped-cache');
        $cacheKey = md5($imagePath . filemtime($imagePath));
        $cachePath = "{$cacheDir}/{$cacheKey}.jpg";
        imagejpeg($crop, $cachePath, 92);
        imagedestroy($crop);

        $newInfo = @getimagesize($imagePath);

        return response()->json([
            'success' => true,
            'width' => $newInfo[0] ?? $w,
            'height' => $newInfo[1] ?? $h,
        ]);
    }

    /**
     * FS Overlay — recognise text in annotated field regions using the fine-tuned HTR model.
     * Crops each field from the image, sends to HTR service, returns recognised text.
     */
    public function htrFsOverlayRecognise(Request $request)
    {
        $imagePath = $request->input('image_path', '');
        $annotations = $request->input('annotations', []); // [{label, x, y, w, h}, ...]

        if (!$imagePath || !file_exists($imagePath)) {
            return response()->json(['success' => false, 'error' => 'Image not found']);
        }

        if (empty($annotations)) {
            return response()->json(['success' => false, 'error' => 'No annotations to recognise']);
        }

        // Load the image (use cropped version if available)
        $cacheDir = storage_path('app/cropped-cache');
        $cacheKey = md5($imagePath . filemtime($imagePath));
        $cachePath = "{$cacheDir}/{$cacheKey}.jpg";
        $srcPath = file_exists($cachePath) ? $cachePath : $imagePath;

        $im = @imagecreatefromjpeg($srcPath) ?: @imagecreatefrompng($srcPath);
        if (!$im) {
            return response()->json(['success' => false, 'error' => 'Cannot load image']);
        }

        $results = [];
        $htrUrl = 'http://192.168.0.115:5006/ocr-finetuned';

        foreach ($annotations as $ann) {
            $label = $ann['label'] ?? '';
            $x = max(0, (int)($ann['x'] ?? 0));
            $y = max(0, (int)($ann['y'] ?? 0));
            $w = max(1, (int)($ann['w'] ?? 1));
            $h = max(1, (int)($ann['h'] ?? 1));

            // Crop the field region
            $crop = imagecrop($im, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
            if (!$crop) {
                $results[$label] = ['text' => '', 'error' => 'Crop failed'];
                continue;
            }

            // Save crop to temp file
            $tmpFile = tempnam(sys_get_temp_dir(), 'htr_') . '.jpg';
            imagejpeg($crop, $tmpFile, 95);
            imagedestroy($crop);

            // Send to HTR service
            try {
                $ch = curl_init($htrUrl);
                $cFile = new \CURLFile($tmpFile, 'image/jpeg', 'crop.jpg');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => ['file' => $cFile],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200) {
                    $data = json_decode($response, true);
                    $text = $data['text'] ?? $data['result'] ?? '';
                    $results[$label] = ['text' => trim($text)];
                } else {
                    $results[$label] = ['text' => '', 'error' => "HTR returned {$httpCode}"];
                }
            } catch (\Exception $e) {
                $results[$label] = ['text' => '', 'error' => $e->getMessage()];
            }

            @unlink($tmpFile);
        }

        imagedestroy($im);

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    /**
     * FS Overlay — OCR the form labels to find their positions on the image.
     * Runs Tesseract with TSV output, matches words against field names.
     */
    public function htrFsOverlayOcr(Request $request)
    {
        $imagePath = $request->input('image_path', '');
        $fields = $request->input('fields', []); // field names to locate

        if (!$imagePath || !file_exists($imagePath)) {
            return response()->json(['success' => false, 'error' => 'Image not found']);
        }

        // Use cropped image if available
        $cacheDir = storage_path('app/cropped-cache');
        $cacheKey = md5($imagePath . filemtime($imagePath));
        $cachePath = "{$cacheDir}/{$cacheKey}.jpg";
        $ocrPath = file_exists($cachePath) ? $cachePath : $imagePath;

        // Run Tesseract with TSV output (word-level bounding boxes)
        $tmpOut = tempnam(sys_get_temp_dir(), 'ocr');
        $cmd = sprintf(
            'tesseract %s %s --psm 3 tsv 2>/dev/null',
            escapeshellarg($ocrPath),
            escapeshellarg($tmpOut)
        );
        exec($cmd);

        $tsvFile = $tmpOut . '.tsv';
        if (!file_exists($tsvFile)) {
            @unlink($tmpOut);
            return response()->json(['success' => false, 'error' => 'OCR failed']);
        }

        $lines = file($tsvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        @unlink($tsvFile);
        @unlink($tmpOut);

        // Parse TSV into word entries
        $words = [];
        foreach ($lines as $i => $line) {
            if ($i === 0) continue; // header
            $parts = explode("\t", $line);
            if (count($parts) < 12 || empty(trim($parts[11]))) continue;
            $conf = (float)$parts[10];
            if ($conf < 20) continue; // skip low confidence
            $words[] = [
                'text' => trim($parts[11]),
                'left' => (int)$parts[6],
                'top' => (int)$parts[7],
                'width' => (int)$parts[8],
                'height' => (int)$parts[9],
                'conf' => $conf,
            ];
        }

        // Fields to skip entirely — not useful for annotation
        $skipFields = ['Event Type', 'Birth Year', 'Relationship to Head of Household', 'Occupation', 'Cause of Death'];

        // Build a map of field label keywords → positions
        // For each CSV field name, find the matching printed label on the form
        // The FIRST keyword is the primary anchor (e.g. "Date" for Event Date, not "Death")
        // Bilingual SA death certificate labels (English / Afrikaans)
        $labelMap = [
            'Name' => ['christian', 'voornamen', 'familienaam', 'surname', 'names'],
            'Sex' => ['sex', 'geslacht', 'geslag', 'gender'],
            'Age' => ['age', 'ouderdom'],
            'Birth Date' => ['birth', 'born', 'geboorte'],
            'Birth Place' => ['birthplace', 'geboorteplek'],
            'Event Date' => ['date', 'datum'],           // "Date of Death" / "Datum van Overleden"
            'Event Place' => ['place', 'plaats', 'plek'], // "Place of Death" / "Plaats waar Overleden"
            'Cause of Death' => ['cause', 'causes', 'oorzaak', 'doodsoorzaak'],
            'Father' => ['father', 'vader'],
            'Mother' => ['mother', 'moeder', 'maiden'],
            'Spouse' => ['spouse', 'married', 'husband', 'wife', 'eggenoot', 'eggenote', 'getroud'],
            'Occupation' => ['occupation', 'profession', 'beroep'],
            'Race' => ['race', 'colour', 'color', 'ras', 'kleur'],
            'Marital Status' => ['married', 'marital', 'single', 'widow', 'huwelikstaat', 'getroud', 'ongetroud'],
            'Residence' => ['residence', 'address', 'abode', 'woonplek', 'adres'],
            'District' => ['district', 'division', 'distrik', 'afdeling'],
            'Registration No' => ['registration', 'register', 'entry', 'registrasie'],
            'Informant' => ['informant', 'informants', 'aangewer'],
            'Registrar' => ['registrar', 'registrateur'],
        ];

        // Get image dimensions for smart width calculation
        $imgInfo = @getimagesize($imagePath);
        $imgWidth = $imgInfo ? $imgInfo[0] : 1800;

        $positions = [];
        foreach ($fields as $fieldName) {
            // Skip excluded fields
            if (in_array($fieldName, $skipFields)) continue;

            $keywords = $labelMap[$fieldName] ?? [strtolower($fieldName)];
            $bestMatch = null;
            $bestConf = 0;

            foreach ($words as $word) {
                // Skip tiny words (likely noise/artifacts)
                if ($word['width'] < 15 || $word['height'] < 8) continue;
                // Skip very short OCR text (1-2 chars rarely match real labels)
                if (strlen($word['text']) < 3) continue;

                $wLower = strtolower($word['text']);
                foreach ($keywords as $kw) {
                    if (stripos($wLower, $kw) !== false || (strlen($kw) > 3 && stripos($kw, $wLower) !== false)) {
                        // Score: prefer higher confidence AND larger words
                        $score = $word['conf'] + ($word['width'] * 0.1);
                        if ($score > $bestConf) {
                            $bestMatch = $word;
                            $bestConf = $score;
                        }
                        break;
                    }
                }
            }

            if ($bestMatch) {
                // Start position: right after the label
                $startX = $bestMatch['left'] + $bestMatch['width'] + 5;
                $startY = $bestMatch['top'] - 5;

                // Default box width: from label end to ~85% of image width
                $boxW = max(200, (int)($imgWidth * 0.85) - $startX);
                $boxH = max(35, $bestMatch['height'] + 15);

                // Special handling for "Event Place" — starts at the label, covers wide area
                if ($fieldName === 'Event Place') {
                    $startX = $bestMatch['left']; // start at the label itself
                    $boxW = max(400, (int)($imgWidth * 0.9) - $startX);
                    $boxH = max(50, $bestMatch['height'] + 30); // taller for multi-line places
                }

                // Special handling for "Event Date" — start right at the label
                if ($fieldName === 'Event Date') {
                    $startX = $bestMatch['left'] + $bestMatch['width'] + 3;
                    $boxW = max(300, (int)($imgWidth * 0.7) - $startX);
                }

                $positions[$fieldName] = [
                    'label_x' => $bestMatch['left'],
                    'label_y' => $bestMatch['top'],
                    'label_w' => $bestMatch['width'],
                    'label_h' => $bestMatch['height'],
                    'x' => $startX,
                    'y' => $startY,
                    'w' => $boxW,
                    'h' => $boxH,
                ];
            }
        }

        // Return word texts for form type detection
        $wordTexts = array_column($words, 'text');

        // Find anchor text position (the form title)
        // Look for key title words and compute the bounding box spanning all of them
        $anchorKeywords = ['death', 'act', 'informasievorm', 'sterfgeval', 'kennisgewing', 'wet'];
        $anchorWords = [];
        foreach ($words as $word) {
            $wLower = strtolower($word['text']);
            foreach ($anchorKeywords as $kw) {
                if (stripos($wLower, $kw) !== false && $word['width'] > 20) {
                    $anchorWords[] = $word;
                    break;
                }
            }
        }

        $anchor = null;
        if (!empty($anchorWords)) {
            // Find words that are on the same line (similar Y position, within 30px)
            // Group by Y position
            usort($anchorWords, fn($a, $b) => $a['top'] - $b['top']);
            $titleLine = [$anchorWords[0]];
            $baseY = $anchorWords[0]['top'];
            foreach ($anchorWords as $w) {
                if (abs($w['top'] - $baseY) < 40) {
                    $titleLine[] = $w;
                }
            }

            // Compute bounding box spanning all title words
            $minX = min(array_column($titleLine, 'left'));
            $minY = min(array_column($titleLine, 'top'));
            $maxX = max(array_map(fn($w) => $w['left'] + $w['width'], $titleLine));
            $maxY = max(array_map(fn($w) => $w['top'] + $w['height'], $titleLine));

            $anchor = [
                'x' => $minX,
                'y' => $minY,
                'w' => $maxX - $minX,
                'h' => $maxY - $minY,
                'x_pct' => $imgWidth > 0 ? round($minX / $imgWidth, 4) : 0,
                'y_pct' => $imgInfo ? round($minY / $imgInfo[1], 4) : 0,
                'w_pct' => $imgWidth > 0 ? round(($maxX - $minX) / $imgWidth, 4) : 0,
                'h_pct' => $imgInfo ? round(($maxY - $minY) / $imgInfo[1], 4) : 0,
            ];
        }

        return response()->json([
            'success' => true,
            'positions' => $positions,
            'words' => $wordTexts,
            'anchor' => $anchor,
            'word_count' => count($words),
        ]);
    }

    /**
     * Bulk Annotate — load folder + spreadsheet data.
     * Scans for .xlsx/.csv in folder, parses, matches with images.
     */
    public function htrBulkAnnotateLoad(Request $request)
    {
        $folder = $request->input('folder', '');
        if (!$folder || !is_dir($folder)) {
            return response()->json(['success' => false, 'error' => 'Folder not found']);
        }

        // Find spreadsheets
        $spreadsheets = array_merge(
            glob("{$folder}/*.xlsx") ?: [],
            glob("{$folder}/*.csv") ?: [],
            glob("{$folder}/*.xls") ?: []
        );
        if (empty($spreadsheets)) {
            return response()->json(['success' => false, 'error' => 'No spreadsheet found in folder']);
        }

        // If only listing spreadsheets (no specific one selected), return the list
        $selectedSpreadsheet = $request->input('spreadsheet', '');
        if (!$selectedSpreadsheet) {
            // Return list of available spreadsheets for the dropdown
            $ssNames = array_map('basename', $spreadsheets);
            return response()->json([
                'success' => true,
                'spreadsheets' => $ssNames,
                'needsSelection' => true,
            ]);
        }

        $spreadsheet = "{$folder}/{$selectedSpreadsheet}";
        if (!file_exists($spreadsheet)) {
            return response()->json(['success' => false, 'error' => "Spreadsheet not found: {$selectedSpreadsheet}"]);
        }
        $images = [];

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($spreadsheet);
            $reader->setReadDataOnly(true);
            $wb = $reader->load($spreadsheet);
            $ws = $wb->getActiveSheet();
            $rows = $ws->toArray(null, true, true, true);

            if (empty($rows)) {
                return response()->json(['success' => false, 'error' => 'Spreadsheet is empty']);
            }

            // First row is headers
            $headers = array_shift($rows);
            $fnameCol = null;
            $isFsCsv = false;

            // Detect FS Capture CSV format (has "ARK ID" column)
            foreach ($headers as $col => $val) {
                $lower = strtolower(trim($val ?? ''));
                if ($lower === 'ark id') {
                    $fnameCol = $col;
                    $isFsCsv = true;
                    break;
                }
            }

            // Standard spreadsheet: look for filename column
            if (!$fnameCol) {
                foreach ($headers as $col => $val) {
                    $lower = strtolower(trim($val ?? ''));
                    if (in_array($lower, ['fname', 'filename', 'file', 'image', 'docname', 'doc_name', 'imagename', 'image_name'])) {
                        $fnameCol = $col;
                        break;
                    }
                }
            }
            if (!$fnameCol) $fnameCol = 'A'; // Default to first column

            // Columns to ignore in the FS CSV format
            $ignoreColumns = $isFsCsv
                ? ['row', 'image #', 'image', 'page url', 'timestamp', 'ark id']
                : [];

            // Build processed list to skip already-done images
            $processedDir = $folder . '/processed';
            $processedFiles = [];
            if (is_dir($processedDir)) {
                $processedFiles = array_map('basename', glob("{$processedDir}/*.{jpg,jpeg,png,tif}", GLOB_BRACE));
            }

            foreach ($rows as $row) {
                $fname = trim($row[$fnameCol] ?? '');
                if (!$fname) continue;

                // Try exact name, then with common extensions
                $imagePath = "{$folder}/{$fname}";
                if (!file_exists($imagePath)) {
                    foreach (['.jpg', '.jpeg', '.png', '.tif', '.JPG'] as $ext) {
                        if (file_exists("{$folder}/{$fname}{$ext}")) {
                            $fname = $fname . $ext;
                            $imagePath = "{$folder}/{$fname}";
                            break;
                        }
                    }
                }
                if (!file_exists($imagePath)) continue;
                if (in_array($fname, $processedFiles)) continue;

                $fields = [];
                foreach ($headers as $col => $header) {
                    if ($col === $fnameCol) continue;
                    $headerLower = strtolower(trim($header ?? ''));
                    if (in_array($headerLower, $ignoreColumns)) continue;
                    if (!$header) continue;
                    $val = $row[$col] ?? '';
                    if ($val instanceof \DateTime) {
                        $val = $val->format('j F Y');
                    }
                    $fields[trim($header)] = (string) $val;
                }

                $images[] = [
                    'fname' => $fname,
                    'path' => $imagePath,
                    'fields' => $fields,
                ];
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Spreadsheet parse error: ' . $e->getMessage()]);
        }

        // Build column list from headers (excluding filename + ignored columns)
        $columns = [];
        foreach ($headers as $col => $val) {
            if ($col === $fnameCol || !$val) continue;
            $valLower = strtolower(trim($val));
            if (in_array($valLower, $ignoreColumns)) continue;
            $columns[] = trim($val);
        }

        return response()->json([
            'success' => true,
            'images' => $images,
            'columns' => $columns,
            'spreadsheet' => basename($spreadsheet),
            'total' => count($images),
        ]);
    }

    /**
     * Bulk Annotate — save annotation + move image to processed.
     */
    public function htrBulkAnnotateSave(Request $request)
    {
        $imagePath = $request->input('image_path', '');
        $fname = $request->input('fname', '');
        $fields = $request->input('fields', []);
        $annotations = $request->input('annotations', []);
        $folder = $request->input('folder', '');

        if (!$imagePath || !file_exists($imagePath)) {
            return response()->json(['success' => false, 'error' => 'Image not found']);
        }

        // Determine record type from fields
        $eventType = strtolower($fields['Event Type'] ?? 'death');
        $rtMap = [
            'death' => ['type' => 'Death Records', 'id' => '1000015'],
            'birth' => ['type' => 'Birth Records', 'id' => '1000001'],
            'marriage' => ['type' => 'Marriage Records', 'id' => '1000006'],
        ];
        $rt = $rtMap[$eventType] ?? ['type' => 'Death Records', 'id' => '1000015'];
        $docType = str_contains($eventType, 'death') ? 'type_a' : (str_contains($eventType, 'birth') ? 'type_a' : 'type_b');

        // Extract year from Event Date
        $eventDate = $fields['Event Date'] ?? '';
        $year = '';
        if (preg_match('/\b(1[6-9]\d{2}|20[0-2]\d)\b/', $eventDate, $m)) {
            $year = $m[1];
        }

        // Build ILM annotation
        $annData = [
            'image' => $imagePath,
            'doc_type' => $docType,
            'source' => [
                'spreadsheet' => true,
                'folder' => $folder,
                'annotated_at' => now()->toIso8601String(),
            ],
            'annotations' => [[
                'non_genealogical' => false,
                'non_genealogical_type_id' => null,
                'FS_RECORD_TYPE' => $rt['type'],
                'FS_RECORD_TYPE_ID' => $rt['id'],
                'EVENT_YEAR_ORIG' => $year ?: ($fields['Event Date'] ?? ''),
                'EVENT_PLACE_ORIG' => $fields['Event Place'] ?? '',
                'LOCALITY_ID' => '',
                'person_name' => $fields['Name'] ?? '',
                'person_sex' => $fields['Sex'] ?? '',
                'person_age' => $fields['Age'] ?? '',
                'fields' => [],
            ]],
        ];

        // Add bounding box fields from annotations
        foreach ($annotations as $i => $ann) {
            if (!$ann || empty($ann['label'])) continue;
            $annData['annotations'][0]['fields'][] = [
                'zone_id' => $i,
                'label' => $ann['label'],
                'form_label' => $ann['label'],
                'text' => $ann['value'] ?? '',
                'bbox' => [
                    'x' => (int) ($ann['x'] ?? 0),
                    'y' => (int) ($ann['y'] ?? 0),
                    'w' => (int) ($ann['w'] ?? 0),
                    'h' => (int) ($ann['h'] ?? 0),
                ],
            ];
        }

        // Save to training data
        $trainingDir = '/opt/ahg-ai/htr/training_data/' . $docType;
        $imgDir = $trainingDir . '/images';
        $annDir = $trainingDir . '/annotations';
        $cropDir = $trainingDir . '/crops';
        @mkdir($imgDir, 0777, true);
        @mkdir($annDir, 0777, true);
        @mkdir($cropDir, 0777, true);

        // Copy image to training images
        $baseName = pathinfo($fname, PATHINFO_FILENAME);
        copy($imagePath, "{$imgDir}/{$fname}");

        // Save annotation JSON
        file_put_contents("{$annDir}/{$fname}.json", json_encode($annData, JSON_PRETTY_PRINT));

        // Crop each annotated region and save
        try {
            $im = imagecreatefromjpeg($imagePath);
            if ($im) {
                foreach ($annotations as $i => $ann) {
                    if (!$ann || empty($ann['x'])) continue;
                    $x = max(0, (int) $ann['x']);
                    $y = max(0, (int) $ann['y']);
                    $w = max(1, (int) $ann['w']);
                    $h = max(1, (int) $ann['h']);
                    $crop = imagecrop($im, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
                    if ($crop) {
                        $safeLabel = preg_replace('/[^a-zA-Z0-9_]/', '_', $ann['label'] ?? 'field');
                        $safeVal = preg_replace('/[^a-zA-Z0-9_]/', '_', substr($ann['value'] ?? '', 0, 30));
                        $cropName = "{$baseName}_{$i}_{$safeLabel}_{$safeVal}.jpg";
                        imagejpeg($crop, "{$cropDir}/{$cropName}", 95);
                        imagedestroy($crop);
                    }
                }
                imagedestroy($im);
            }
        } catch (\Exception $e) {
            // Crop failure is non-fatal
        }

        // Move original image to processed folder
        $processedDir = $folder . '/processed';
        @mkdir($processedDir, 0777, true);
        if (file_exists($imagePath)) {
            @rename($imagePath, "{$processedDir}/{$fname}");
        }

        // Check if all images are processed — if so, move CSV too
        $remainingImages = glob("{$folder}/*.{jpg,jpeg,png,tif}", GLOB_BRACE);
        $csvMoved = false;
        if (empty($remainingImages)) {
            // Move all CSVs and spreadsheets to processed
            $spreadsheets = array_merge(
                glob("{$folder}/*.csv") ?: [],
                glob("{$folder}/*.xlsx") ?: [],
                glob("{$folder}/*.xls") ?: []
            );
            foreach ($spreadsheets as $ss) {
                $ssName = basename($ss);
                @rename($ss, "{$processedDir}/{$ssName}");
            }
            $csvMoved = !empty($spreadsheets);
        }

        return response()->json([
            'success' => true,
            'saved' => "{$annDir}/{$fname}.json",
            'csv_moved' => $csvMoved,
            'remaining' => count($remainingImages ?? []),
            'crops' => count(array_filter($annotations)),
        ]);
    }

    /**
     * Split a register page into individual row images for annotation.
     * Takes the image path + array of row bounding boxes.
     * Crops each row and saves to a temp folder, returns paths.
     */
    public function htrSplitRows(Request $request)
    {
        $path = $request->input('path', '');
        $rows = $request->input('rows', []);

        if (!$path || !file_exists($path)) {
            return response()->json(['success' => false, 'error' => 'Image not found.']);
        }
        if (empty($rows)) {
            return response()->json(['success' => false, 'error' => 'No row boxes provided.']);
        }

        $basename = pathinfo($path, PATHINFO_FILENAME);
        $splitDir = dirname($path) . '/row_splits';
        if (!is_dir($splitDir)) {
            @mkdir($splitDir, 0777, true);
        }

        $cropsJson = json_encode($rows);
        $escapedImage = escapeshellarg($path);
        $escapedCrops = escapeshellarg($cropsJson);
        $escapedDir = escapeshellarg($splitDir);
        $escapedBase = escapeshellarg($basename);

        $script = <<<'PY'
import sys, json
from PIL import Image
img = Image.open(sys.argv[1])
rows = json.loads(sys.argv[2])
out_dir = sys.argv[3]
base = sys.argv[4]
PAD = 10
results = []
for i, r in enumerate(rows):
    box = (max(0, r['x']-PAD), max(0, r['y']-PAD),
           min(img.width, r['x']+r['w']+PAD), min(img.height, r['y']+r['h']+PAD))
    if box[2]<=box[0] or box[3]<=box[1]: continue
    crop = img.crop(box)
    fname = f"{base}_row{i+1:02d}.jpg"
    out_path = f"{out_dir}/{fname}"
    crop.save(out_path, 'JPEG', quality=95)
    results.append({'index': i, 'name': fname, 'path': out_path, 'width': crop.width, 'height': crop.height})
print(json.dumps(results))
PY;

        $tmpScript = tempnam('/tmp', 'split_') . '.py';
        file_put_contents($tmpScript, $script);
        $output = shell_exec("python3 {$tmpScript} {$escapedImage} {$escapedCrops} {$escapedDir} {$escapedBase} 2>&1");
        @unlink($tmpScript);

        $results = json_decode(trim($output ?: '[]'), true) ?: [];

        return response()->json([
            'success' => true,
            'rows' => $results,
            'split_dir' => $splitDir,
            'total' => count($results),
        ]);
    }

    public function htrSkipImage(Request $request)
    {
        $path = $request->input('path', '');
        if (!$path || !file_exists($path)) {
            return response()->json(['success' => false, 'error' => 'Image not found.']);
        }

        $sourceDir = dirname($path);
        $reworkDir = $sourceDir . '/rework';
        if (!is_dir($reworkDir)) {
            @mkdir($reworkDir, 0777, true);
        }
        if (!is_dir($reworkDir) || !is_writable($reworkDir)) {
            return response()->json(['success' => false, 'error' => 'Cannot create rework folder. Check folder permissions.']);
        }

        $moved = @rename($path, $reworkDir . '/' . basename($path));

        return response()->json([
            'success' => $moved,
            'moved_to' => $moved ? $reworkDir . '/' . basename($path) : null,
        ]);
    }

    /**
     * Spellcheck text using the HTR dictionary (EN + AF + SA towns + custom).
     */
    public function htrSpellcheck(Request $request)
    {
        $text = $request->input('text', '');
        if (!$text) {
            return response()->json(['errors' => []]);
        }

        $script = <<<'PY'
import sys, json
sys.path.insert(0, '/opt/ahg-ai/htr')
from spellcheck import SpellChecker
sc = SpellChecker()
errors = sc.check_text(sys.argv[1])
print(json.dumps(errors))
PY;
        $tmp = tempnam('/tmp', 'spell_') . '.py';
        file_put_contents($tmp, $script);
        $escaped = escapeshellarg($text);
        $output = shell_exec("python3 {$tmp} {$escaped} 2>/dev/null");
        @unlink($tmp);

        $errors = json_decode(trim($output ?: '[]'), true) ?: [];
        return response()->json(['errors' => $errors]);
    }

    /**
     * Add a word to the custom HTR dictionary.
     */
    public function htrAddWord(Request $request)
    {
        $word = $request->input('word', '');
        if (!$word || strlen($word) < 2) {
            return response()->json(['success' => false, 'error' => 'Word too short.']);
        }

        $script = <<<'PY'
import sys, json
sys.path.insert(0, '/opt/ahg-ai/htr')
from spellcheck import SpellChecker
sc = SpellChecker()
ok = sc.add_word(sys.argv[1])
print(json.dumps({'success': ok, 'word': sys.argv[1], 'stats': sc.stats()}))
PY;
        $tmp = tempnam('/tmp', 'addw_') . '.py';
        file_put_contents($tmp, $script);
        $escaped = escapeshellarg($word);
        $output = shell_exec("python3 {$tmp} {$escaped} 2>/dev/null");
        @unlink($tmp);

        return response()->json(json_decode(trim($output ?: '{}'), true) ?: ['success' => false]);
    }

    /**
     * List image files in a folder for the annotation tool.
     * Accepts ?path=/tmp or ?path=type_a (shortcut for training_data/type_a/images).
     */
    public function htrFolderList(Request $request)
    {
        $path = $request->get('path', '');

        // Shortcuts for training_data folders
        $shortcuts = [
            'type_a' => '/opt/ahg-ai/htr/training_data/type_a/images',
            'type_b' => '/opt/ahg-ai/htr/training_data/type_b/images',
            'type_c' => '/opt/ahg-ai/htr/training_data/type_c/images',
        ];
        $resolved = $shortcuts[$path] ?? $path;

        if (!$resolved || !is_dir($resolved)) {
            return response()->json(['success' => false, 'error' => 'Folder not found: ' . $resolved]);
        }

        // List image files
        $exts = ['jpg','jpeg','png','tif','tiff','bmp','webp'];
        $files = [];

        // Recursive scan — includes subfolders but skips 'processed' folders
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($resolved, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $fileInfo) {
            // Skip processed folders
            if (str_contains($fileInfo->getPathname(), '/processed/')) continue;

            $ext = strtolower($fileInfo->getExtension());
            if (!in_array($ext, $exts)) continue;

            $full = $fileInfo->getPathname();
            $rel = str_replace($resolved . '/', '', $full);

            // Check if annotated (annotation JSON exists in training_data)
            $basename = $fileInfo->getFilename();
            $annotated = false;
            foreach (['type_a','type_b','type_c'] as $t) {
                if (file_exists("/opt/ahg-ai/htr/training_data/{$t}/annotations/{$basename}.json")) {
                    $annotated = true;
                    break;
                }
            }

            $files[] = [
                'name' => $rel,
                'path' => $full,
                'size' => $fileInfo->getSize(),
                'annotated' => $annotated,
            ];
        }

        // Sort: unannotated first, then alphabetical
        usort($files, function ($a, $b) {
            if ($a['annotated'] !== $b['annotated']) return $a['annotated'] ? 1 : -1;
            return strcmp($a['name'], $b['name']);
        });

        return response()->json([
            'success' => true,
            'folder' => $resolved,
            'files' => $files,
            'total' => count($files),
            'annotated' => count(array_filter($files, fn($f) => $f['annotated'])),
        ]);
    }

    /**
     * Serve an image file from disk for the annotation canvas.
     */
    public function htrServeImage(Request $request)
    {
        $path = $request->get('path', '');

        if (!$path || !file_exists($path)) {
            abort(404, 'Image not found');
        }

        // Security: only allow images from known paths
        $allowed = ['/opt/ahg-ai/', '/tmp/', '/mnt/', '/usr/share/nginx/heratio/FamilySearch/'];
        $ok = false;
        foreach ($allowed as $prefix) {
            if (str_starts_with(realpath($path), $prefix)) { $ok = true; break; }
        }
        if (!$ok) {
            abort(403, 'Access denied');
        }

        return response()->file($path);
    }

    public function htrSaveAnnotation(Request $request)
    {
        $type = $request->input('type');
        $annotationsJson = $request->input('annotations', '{}');
        $annotations = json_decode($annotationsJson, true) ?? [];

        if (!$type || !in_array($type, ['type_a', 'type_b', 'type_c'])) {
            return response()->json(['success' => false, 'error' => 'Invalid type.']);
        }
        if (empty($annotations)) {
            return response()->json(['success' => false, 'error' => 'No annotations provided.']);
        }

        // Determine image source: uploaded file OR server_path (folder mode)
        $serverPath = $request->input('server_path', '');
        $fullPath = null;
        $tmpPath = null;
        $isServerFile = false;

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $tmpPath = $file->store('htr-annotations', 'local');
            $fullPath = storage_path('app/private/' . $tmpPath);
        } elseif ($serverPath && file_exists($serverPath)) {
            $fullPath = $serverPath;
            $isServerFile = true;
        } else {
            return response()->json(['success' => false, 'error' => 'No image provided.']);
        }

        // Always save locally to training_data (this is the ground truth)
        $destDir = '/opt/ahg-ai/htr/training_data/' . $type . '/images';
        $annDir  = '/opt/ahg-ai/htr/training_data/' . $type . '/annotations';
        $cropsDir = '/opt/ahg-ai/htr/training_data/' . $type . '/crops';

        if (!is_dir($destDir)) { @mkdir($destDir, 0777, true); }
        if (!is_dir($annDir)) { @mkdir($annDir, 0777, true); }
        if (!is_dir($cropsDir)) { @mkdir($cropsDir, 0777, true); }

        if ($isServerFile) {
            $filename = basename($fullPath);
            if (realpath(dirname($fullPath)) !== realpath($destDir)) {
                copy($fullPath, $destDir . '/' . $filename);
            }
        } else {
            $timestamp = (int)(microtime(true) * 1000);
            $ext = pathinfo($fullPath, PATHINFO_EXTENSION) ?: 'jpg';
            $filename = "annotated_{$timestamp}.{$ext}";
            copy($fullPath, $destDir . '/' . $filename);
        }

        // Auto-translate Dutch/Afrikaans → English using glossary
        $translated = $this->translateAnnotations($annotations);

        file_put_contents($annDir . '/' . $filename . '.json', json_encode([
            'image' => $destDir . '/' . $filename,
            'doc_type' => $type,
            'annotations' => $translated,
            'created_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));

        // Crop individual field regions
        $imgPath = $destDir . '/' . $filename;
        $this->cropAnnotatedFields($imgPath, $annotations, $cropsDir, $filename);

        // Also try remote HTR service (non-blocking — local save is the source of truth)
        $result = $this->htrService->saveAnnotation($fullPath, $type, $annotations);
        if ($result === null) {
            $result = ['success' => true, 'saved_locally' => true];
        }

        // Clean up temp file only if we uploaded one
        if ($tmpPath) {
            @unlink($fullPath);
        }

        // Move source image to processed/ folder so it's skipped on next load
        if ($result && $isServerFile && $serverPath && file_exists($serverPath)) {
            try {
                $sourceDir = dirname($serverPath);
                $processedDir = $sourceDir . '/processed';
                if (!is_dir($processedDir)) {
                    @mkdir($processedDir, 0777, true);
                }
                if (is_dir($processedDir) && is_writable($processedDir)) {
                    $moved = @rename($serverPath, $processedDir . '/' . basename($serverPath));
                    $result['moved_to'] = $moved ? $processedDir . '/' . basename($serverPath) : null;
                }
            } catch (\Exception $e) {
                // Don't fail the save if we can't move — annotation is already saved
                \Log::warning('Could not move to processed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => $result !== null,
            'data'    => $result,
            'error'   => $result === null ? 'Annotation save failed.' : null,
        ]);
    }

    public function htrTraining()
    {
        // Count local training data (source of truth is on 112, not 115)
        $baseDir = '/opt/ahg-ai/htr/training_data';
        $counts = [];
        foreach (['type_a', 'type_b', 'type_c'] as $type) {
            $annDir = $baseDir . '/' . $type . '/annotations';
            $counts[$type] = is_dir($annDir) ? count(glob($annDir . '/*.json')) : 0;
        }

        $status = [
            'counts' => $counts,
            'total' => array_sum($counts),
            'training_active' => false,
        ];

        // Also try remote service for training status
        $remote = $this->htrService->trainingStatus();
        if ($remote && isset($remote['training_active'])) {
            $status['training_active'] = $remote['training_active'];
        }

        return view('ahg-ai-services::htr.training', compact('status'));
    }

    public function htrStartTraining()
    {
        $result = $this->htrService->triggerTraining();

        if ($result) {
            return redirect()->route('admin.ai.htr.training')
                ->with('success', 'Fine-tuning started successfully.');
        }

        return redirect()->route('admin.ai.htr.training')
            ->with('error', 'Failed to start training. The service may be offline.');
    }

    /**
     * Crop annotated field regions from an image and save as separate files.
     * Each crop is saved with its label and text in the filename for training.
     */

    /**
     * Auto-translate Dutch/Afrikaans month names and place names in annotations.
     * Preserves original text as 'text_original', adds 'text_en' with English translation.
     */
    private function translateAnnotations(array $annotations): array
    {
        $script = <<<'PY'
import sys, json
sys.path.insert(0, '/opt/ahg-ai/htr')
from glossary import translate_month, normalize_place

data = json.loads(sys.argv[1])
for ann in data:
    for field in ann.get('fields', []):
        text = field.get('text', '')
        if not text:
            continue
        field['text_original'] = text
        # Translate months and places
        translated = translate_month(text)
        translated = normalize_place(translated)
        field['text_en'] = translated
    # Also translate top-level ILM fields
    for key in ['EVENT_YEAR_ORIG', 'EVENT_PLACE_ORIG']:
        val = ann.get(key, '')
        if val:
            ann[key + '_original'] = val
            t = translate_month(val)
            t = normalize_place(t)
            ann[key] = t
print(json.dumps(data))
PY;
        $tmpScript = tempnam('/tmp', 'translate_') . '.py';
        file_put_contents($tmpScript, $script);
        $escapedData = escapeshellarg(json_encode($annotations));
        $output = shell_exec("python3 {$tmpScript} {$escapedData} 2>/dev/null");
        @unlink($tmpScript);

        if ($output) {
            $result = json_decode(trim($output), true);
            if (is_array($result)) {
                return $result;
            }
        }

        return $annotations;
    }

    private function cropAnnotatedFields(string $imagePath, array $annotations, string $cropsDir, string $sourceFilename): void
    {
        if (!file_exists($imagePath)) return;

        $base = pathinfo($sourceFilename, PATHINFO_FILENAME);

        // Parse annotations — handle both flat array and nested [{fields:[...]}] format
        $fields = [];
        foreach ($annotations as $ann) {
            if (isset($ann['fields'])) {
                foreach ($ann['fields'] as $f) {
                    $fields[] = $f;
                }
            } elseif (isset($ann['bbox'])) {
                $fields[] = $ann;
            }
        }

        if (empty($fields)) return;

        // Use Python/Pillow to crop — single script call for all fields
        $crops = [];
        foreach ($fields as $i => $field) {
            $bbox = $field['bbox'] ?? [];
            if (empty($bbox['x']) && ($bbox['x'] ?? null) !== 0) continue;

            $label = $field['label'] ?? ('field_' . $i);
            $formLabel = preg_replace('/[^a-zA-Z0-9_-]/', '_', $field['form_label'] ?? '');
            $text = preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($field['text'] ?? '', 0, 40));
            $cropName = "{$base}_{$i}_{$label}" . ($formLabel ? "_{$formLabel}" : '') . ($text ? "_{$text}" : '') . '.jpg';

            $crops[] = [
                'x' => (int)$bbox['x'],
                'y' => (int)$bbox['y'],
                'w' => (int)$bbox['w'],
                'h' => (int)$bbox['h'],
                'out' => $cropsDir . '/' . $cropName,
                'label' => $label,
                'text' => $field['text'] ?? '',
            ];
        }

        if (empty($crops)) return;

        $cropsJson = json_encode($crops);
        $escapedImage = escapeshellarg($imagePath);
        $escapedCrops = escapeshellarg($cropsJson);

        // Python one-liner to crop all fields
        $script = <<<'PY'
import sys, json
from PIL import Image
img = Image.open(sys.argv[1])
crops = json.loads(sys.argv[2])
# ~2mm padding at 300 DPI = ~24px, at 150 DPI = ~12px. Use 20px as safe default.
PAD = 20
for c in crops:
    box = (c['x']-PAD, c['y']-PAD, c['x']+c['w']+PAD, c['y']+c['h']+PAD)
    box = (max(0,box[0]), max(0,box[1]), min(img.width,box[2]), min(img.height,box[3]))
    if box[2]>box[0] and box[3]>box[1]:
        crop = img.crop(box)
        crop.save(c['out'], 'JPEG', quality=95)
        print(c['out'])
PY;

        $tmpScript = tempnam('/tmp', 'crop_') . '.py';
        file_put_contents($tmpScript, $script);
        exec("python3 {$tmpScript} {$escapedImage} {$escapedCrops} 2>&1", $output);
        @unlink($tmpScript);
    }
}
