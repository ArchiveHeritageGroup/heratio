<?php
/**
 * Heratio - AI model registry admin (CRUD for ai_model_registry).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Controllers;

use AhgAiCompliance\Models\AiModelRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ModelRegistryController extends Controller
{
    private const SERVICES = ['llm', 'htr', 'ner', 'donut', 'guardrail', 'translate'];

    public function index(): View
    {
        $models = AiModelRegistry::query()
            ->orderBy('service')
            ->orderByDesc('deployed_at')
            ->get();

        return view('ahg-ai-compliance::models.index', [
            'models'   => $models,
            'services' => self::SERVICES,
        ]);
    }

    public function create(): View
    {
        return view('ahg-ai-compliance::models.edit', [
            'model'    => new AiModelRegistry(),
            'services' => self::SERVICES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        AiModelRegistry::create($data);

        return redirect()
            ->route('ai-compliance.models.index')
            ->with('success', 'Model registry entry created.');
    }

    public function edit(int $id): View
    {
        /** @var AiModelRegistry $model */
        $model = AiModelRegistry::findOrFail($id);

        return view('ahg-ai-compliance::models.edit', [
            'model'    => $model,
            'services' => self::SERVICES,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        /** @var AiModelRegistry $model */
        $model = AiModelRegistry::findOrFail($id);
        $data = $this->validatePayload($request);
        $model->update($data);

        return redirect()
            ->route('ai-compliance.models.index')
            ->with('success', 'Model registry entry updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        /** @var AiModelRegistry $model */
        $model = AiModelRegistry::findOrFail($id);
        $model->delete();

        return redirect()
            ->route('ai-compliance.models.index')
            ->with('success', 'Model registry entry deleted.');
    }

    /**
     * @return array<string,mixed>
     */
    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'service'               => ['required', 'string', Rule::in(self::SERVICES)],
            'model_id'              => ['required', 'string', 'max:128'],
            'model_version'         => ['required', 'string', 'max:64'],
            'deployed_at'           => ['required', 'date'],
            'retired_at'            => ['nullable', 'date'],
            'gateway_endpoint'      => ['nullable', 'string', 'max:255'],
            'training_data_summary' => ['nullable', 'string'],
            'known_limits'          => ['nullable', 'string'],
            'intended_purpose'      => ['nullable', 'string'],
            'accuracy_metrics_json' => ['nullable', 'string'],
        ]);

        if (!empty($validated['accuracy_metrics_json'])) {
            $decoded = json_decode((string) $validated['accuracy_metrics_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                abort(422, 'accuracy_metrics_json must be valid JSON: ' . json_last_error_msg());
            }
            $validated['accuracy_metrics_json'] = $decoded;
        } else {
            $validated['accuracy_metrics_json'] = null;
        }

        return $validated;
    }
}
