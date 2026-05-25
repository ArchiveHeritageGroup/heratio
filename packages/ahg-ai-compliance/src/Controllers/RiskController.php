<?php
/**
 * Heratio - admin UI for the EU AI Act Article 9 risk register.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Controllers;

use AhgAiCompliance\Services\AiRiskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class RiskController
{
    public function __construct(private AiRiskService $service)
    {
    }

    public function index(Request $request): View
    {
        $filterService = $request->get('service');
        $filterStatus  = $request->get('status', 'active');

        return view('ahg-ai-compliance::risk.index', [
            'risks'         => $this->service->listAll($filterService ?: null, $filterStatus),
            'digest'        => $this->service->postMarketDigest(),
            'filterService' => $filterService,
            'filterStatus'  => $filterStatus,
            'services'      => ['llm', 'htr', 'ner', 'donut', 'guardrail', 'translate'],
            'severities'    => ['low', 'medium', 'high', 'critical'],
            'statuses'      => ['active', 'archived'],
        ]);
    }

    public function create(): View
    {
        return view('ahg-ai-compliance::risk.edit', [
            'risk'       => null,
            'services'   => ['llm', 'htr', 'ner', 'donut', 'guardrail', 'translate'],
            'severities' => ['low', 'medium', 'high', 'critical'],
            'likelihoods' => ['low', 'medium', 'high'],
            'usage'      => ['intended', 'misuse'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $attrs = $this->validateAttrs($request);
        $this->service->create($attrs);
        return redirect()->route('ai-compliance.risk.index')->with('status', 'Risk added.');
    }

    public function edit(int $id): View|RedirectResponse
    {
        $risk = $this->service->find($id);
        if ($risk === null) {
            return redirect()->route('ai-compliance.risk.index')->with('error', 'Risk not found.');
        }

        return view('ahg-ai-compliance::risk.edit', [
            'risk'        => $risk,
            'services'    => ['llm', 'htr', 'ner', 'donut', 'guardrail', 'translate'],
            'severities'  => ['low', 'medium', 'high', 'critical'],
            'likelihoods' => ['low', 'medium', 'high'],
            'usage'       => ['intended', 'misuse'],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $attrs = $this->validateAttrs($request);
        $this->service->update($id, $attrs);
        return redirect()->route('ai-compliance.risk.index')->with('status', 'Risk updated.');
    }

    public function signOff(int $id): RedirectResponse
    {
        $r = $this->service->signOff($id);
        if ($r === null) {
            return redirect()->route('ai-compliance.risk.index')->with('error', 'Risk not found.');
        }
        return redirect()->route('ai-compliance.risk.index')
            ->with('status', "Reviewed risk {$r->id}. Receipt written to the inference chain.");
    }

    public function archive(int $id): RedirectResponse
    {
        $this->service->archive($id);
        return redirect()->route('ai-compliance.risk.index')->with('status', 'Risk archived.');
    }

    public function reportIncident(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'description'       => 'required|string|max:4000',
            'severity_observed' => 'required|in:low,medium,high,critical',
        ]);
        $inc = $this->service->recordIncident(
            $id,
            $request->string('description')->toString(),
            $request->string('severity_observed')->toString(),
            $request->integer('inference_log_id') ?: null,
        );
        if ($inc === null) {
            return redirect()->route('ai-compliance.risk.index')->with('error', 'Risk not found.');
        }
        return redirect()->route('ai-compliance.risk.index')->with('status', 'Incident recorded.');
    }

    private function validateAttrs(Request $request): array
    {
        return $request->validate([
            'service'          => 'required|string|max:32',
            'risk_description' => 'required|string|max:512',
            'severity'         => 'required|in:low,medium,high,critical',
            'likelihood'       => 'required|in:low,medium,high',
            'intended_or_misuse' => 'required|in:intended,misuse',
            'affected_group'   => 'nullable|string|max:64',
            'mitigation'       => 'nullable|string|max:4000',
            'residual_risk'    => 'required|in:low,medium,high,critical',
        ]);
    }
}
