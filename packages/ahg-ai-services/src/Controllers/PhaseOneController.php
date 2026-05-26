<?php
/**
 * PhaseOneController - admin endpoints for issue #667 Phase 1.
 *
 * Quota dashboard, cost dashboard, translation memory browse, custom-NER
 * gazetteer CRUD, face-detect status. Lives separate from the legacy
 * AiController so the existing routes file stays unchanged.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 */

declare(strict_types=1);

namespace AhgAiServices\Controllers;

use AhgAiServices\Contracts\FaceDetectorInterface;
use AhgAiServices\Services\CostService;
use AhgAiServices\Services\QuotaService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

final class PhaseOneController extends Controller
{
    public function __construct(
        private QuotaService $quotaService,
        private CostService $costService,
    ) {
    }

    /**
     * GET /admin/ai/quotas - per-tenant quota dashboard.
     */
    public function quotas(Request $request)
    {
        $rows = $this->quotaService->snapshot();
        return view('ahg-ai-services::quotas', [
            'rows'     => $rows,
            'services' => QuotaService::SERVICES,
        ]);
    }

    /**
     * POST /admin/ai/quotas/save - upsert a single tenant+service row.
     */
    public function quotasSave(Request $request)
    {
        $data = $request->validate([
            'tenant_id'     => 'required|integer|min:0',
            'service'       => 'required|string|in:' . implode(',', QuotaService::SERVICES),
            'daily_limit'   => 'required|integer|min:0',
            'monthly_limit' => 'required|integer|min:0',
            'reset_day'     => 'required|integer|min:1|max:28',
        ]);

        DB::table('ahg_ai_quota')->updateOrInsert(
            ['tenant_id' => $data['tenant_id'], 'service' => $data['service']],
            [
                'daily_limit'   => $data['daily_limit'],
                'monthly_limit' => $data['monthly_limit'],
                'reset_day'     => $data['reset_day'],
                'updated_at'    => now(),
            ],
        );

        return redirect()->route('admin.ai.quotas')->with('status', 'Quota saved.');
    }

    /**
     * GET /admin/ai/costs - cost dashboard.
     */
    public function costs(Request $request)
    {
        $since = $request->query('since', now()->subDays(30)->toDateTimeString());
        $tenantId = $request->query('tenant_id');
        $tenantId = ($tenantId === null || $tenantId === '') ? null : (int) $tenantId;
        $totalsByService = [];
        foreach (QuotaService::SERVICES as $svc) {
            $totalsByService[$svc] = $this->costService->totals($tenantId, $svc, $since);
        }
        $overall = $this->costService->totals($tenantId, null, $since);
        $recent = DB::table('ahg_ai_call_cost')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('called_at', '>=', $since)
            ->orderByDesc('called_at')
            ->limit(100)
            ->get();
        $pricing = DB::table('ahg_ai_pricing')->orderBy('model_id')->get();

        return view('ahg-ai-services::costs', [
            'since'           => $since,
            'tenantId'        => $tenantId,
            'totalsByService' => $totalsByService,
            'overall'         => $overall,
            'recent'          => $recent,
            'pricing'         => $pricing,
        ]);
    }

    /**
     * GET /admin/ai/translation-memory - browse TM.
     */
    public function translationMemory(Request $request)
    {
        $q = DB::table('ahg_translation_memory');
        if ($request->filled('target_lang')) {
            $q->where('target_lang', $request->query('target_lang'));
        }
        if ($request->filled('search')) {
            $needle = '%' . $request->query('search') . '%';
            $q->where(function ($qq) use ($needle) {
                $qq->where('source_text', 'like', $needle)
                   ->orWhere('target_text', 'like', $needle);
            });
        }
        $rows = $q->orderByDesc('last_used_at')->paginate(50);
        $targetLangs = DB::table('ahg_translation_memory')->distinct()->pluck('target_lang');

        return view('ahg-ai-services::translation-memory', [
            'rows'        => $rows,
            'targetLangs' => $targetLangs,
        ]);
    }

    /**
     * GET /admin/ai/ner-custom - browse gazetteer.
     */
    public function nerCustom(Request $request)
    {
        $q = DB::table('ahg_ner_custom_entity');
        if ($request->filled('type')) {
            $q->where('entity_type', $request->query('type'));
        }
        $rows = $q->orderBy('entity_type')->orderBy('label')->paginate(50);
        $types = DB::table('ahg_ner_custom_entity')->distinct()->pluck('entity_type');

        return view('ahg-ai-services::ner-custom', [
            'rows'  => $rows,
            'types' => $types,
        ]);
    }

    /**
     * POST /admin/ai/ner-custom/save - upsert one entity row.
     */
    public function nerCustomSave(Request $request)
    {
        $data = $request->validate([
            'id'          => 'nullable|integer',
            'entity_type' => 'required|string|max:64',
            'label'       => 'required|string|max:255',
            'aliases'     => 'nullable|string', // newline-separated
            'definition'  => 'nullable|string',
            'target_uri'  => 'nullable|string|max:512',
            'is_active'   => 'nullable|in:0,1',
        ]);

        $aliases = [];
        if (!empty($data['aliases'])) {
            foreach (preg_split('/\r?\n/', $data['aliases']) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $aliases[] = $line;
                }
            }
        }

        $payload = [
            'entity_type' => $data['entity_type'],
            'label'       => $data['label'],
            'aliases'     => empty($aliases) ? null : json_encode(array_values($aliases), JSON_UNESCAPED_UNICODE),
            'definition'  => $data['definition'] ?? null,
            'target_uri'  => $data['target_uri'] ?? null,
            'is_active'   => (int) ($data['is_active'] ?? 1),
            'updated_at'  => now(),
        ];

        if (!empty($data['id'])) {
            DB::table('ahg_ner_custom_entity')->where('id', $data['id'])->update($payload);
        } else {
            $payload['created_at'] = now();
            DB::table('ahg_ner_custom_entity')->insert($payload);
        }

        return redirect()->route('admin.ai.ner-custom')->with('status', 'Custom entity saved.');
    }

    /**
     * POST /admin/ai/ner-custom/delete
     */
    public function nerCustomDelete(Request $request)
    {
        $id = (int) $request->input('id');
        if ($id > 0) {
            DB::table('ahg_ner_custom_entity')->where('id', $id)->delete();
        }
        return redirect()->route('admin.ai.ner-custom')->with('status', 'Custom entity deleted.');
    }

    /**
     * GET /admin/ai/face-detect - face detect status + driver health.
     */
    public function faceDetect()
    {
        $detector = app(FaceDetectorInterface::class);
        $driver = DB::table('ahg_ai_settings')->where('feature', 'face_detect')->where('setting_key', 'driver')->value('setting_value') ?? 'null';
        $enabled = DB::table('ahg_ai_settings')->where('feature', 'face_detect')->where('setting_key', 'enabled')->value('setting_value') === '1';
        $health = false;
        try {
            $health = $detector->health();
        } catch (\Throwable) {
            $health = false;
        }
        return view('ahg-ai-services::face-detect', [
            'driver'  => $driver,
            'enabled' => $enabled,
            'health'  => $health,
            'class'   => get_class($detector),
        ]);
    }
}
