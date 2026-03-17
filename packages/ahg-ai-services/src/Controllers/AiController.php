<?php

namespace AhgAiServices\Controllers;

use AhgAiServices\Services\LlmService;
use AhgAiServices\Services\NerService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

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

    public function __construct(LlmService $llmService, NerService $nerService)
    {
        $this->llmService = $llmService;
        $this->nerService = $nerService;
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
}
