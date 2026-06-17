<?php
/**
 * Heratio - admin UI for the EU AI Act system inventory and risk tiering.
 *
 * The model registry, risk register, oversight policies and attestations are
 * per-service or per-model. This controller manages the system-level register
 * the Act is framed around (Art. 6 classification, Art. 52 transparency tiers):
 * the AI systems the organisation provides or deploys, each with its role, risk
 * classification, lifecycle status, human-oversight measures, owner, and review
 * schedule.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Controllers;

use AhgAiCompliance\Models\AiSystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SystemInventoryController
{
    public function index(Request $request): View
    {
        $filterRisk   = $request->get('risk');
        $filterStatus = $request->get('status');

        $query = AiSystem::query()->orderBy('name');
        if (in_array($filterRisk, AiSystem::RISK_CLASSIFICATIONS, true)) {
            $query->where('risk_classification', $filterRisk);
        }
        if (in_array($filterStatus, AiSystem::LIFECYCLE_STATUSES, true)) {
            $query->where('lifecycle_status', $filterStatus);
        }

        // Risk-tier dashboard counts (across all systems, not the filtered set).
        $tierCounts = AiSystem::query()
            ->selectRaw('risk_classification, COUNT(*) as n')
            ->groupBy('risk_classification')
            ->pluck('n', 'risk_classification')
            ->all();

        // Systems whose next review is in the past or within 30 days.
        $reviewDue = AiSystem::query()
            ->whereNotNull('next_review_date')
            ->whereDate('next_review_date', '<=', now()->addDays(30)->toDateString())
            ->where('is_active', 1)
            ->orderBy('next_review_date')
            ->get();

        return view('ahg-ai-compliance::systems.index', [
            'systems'      => $query->get(),
            'tierCounts'   => $tierCounts,
            'reviewDue'    => $reviewDue,
            'filterRisk'   => $filterRisk,
            'filterStatus' => $filterStatus,
            'risks'        => AiSystem::RISK_CLASSIFICATIONS,
            'statuses'     => AiSystem::LIFECYCLE_STATUSES,
        ]);
    }

    public function create(): View
    {
        return view('ahg-ai-compliance::systems.edit', [
            'system'  => null,
            'roles'   => AiSystem::ROLES,
            'risks'   => AiSystem::RISK_CLASSIFICATIONS,
            'statuses' => AiSystem::LIFECYCLE_STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AiSystem::create($this->validated($request));

        return redirect()->route('ai-compliance.systems.index')
            ->with('status', 'AI system added to the inventory.');
    }

    public function edit(int $id): View|RedirectResponse
    {
        $system = AiSystem::find($id);
        if (! $system) {
            return redirect()->route('ai-compliance.systems.index')
                ->with('status', 'System not found.');
        }

        return view('ahg-ai-compliance::systems.edit', [
            'system'  => $system,
            'roles'   => AiSystem::ROLES,
            'risks'   => AiSystem::RISK_CLASSIFICATIONS,
            'statuses' => AiSystem::LIFECYCLE_STATUSES,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $system = AiSystem::find($id);
        if (! $system) {
            return redirect()->route('ai-compliance.systems.index')
                ->with('status', 'System not found.');
        }

        $system->update($this->validated($request));

        return redirect()->route('ai-compliance.systems.index')
            ->with('status', 'AI system updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        AiSystem::where('id', $id)->delete();

        return redirect()->route('ai-compliance.systems.index')
            ->with('status', 'AI system removed from the inventory.');
    }

    /**
     * Validate + normalise. Enumerated fields are constrained to the AI Act
     * constants via Rule::in so an out-of-range value can never be stored.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'description'         => 'nullable|string',
            'purpose'             => 'nullable|string',
            'provider'            => 'nullable|string|max:255',
            'role'                => 'required|in:'.implode(',', AiSystem::ROLES),
            'risk_classification' => 'required|in:'.implode(',', AiSystem::RISK_CLASSIFICATIONS),
            'lifecycle_status'    => 'required|in:'.implode(',', AiSystem::LIFECYCLE_STATUSES),
            'deployment_context'  => 'nullable|string',
            'human_oversight'     => 'nullable|string',
            'owner'               => 'nullable|string|max:255',
            'last_review_date'    => 'nullable|date',
            'next_review_date'    => 'nullable|date',
            'is_active'           => 'nullable|boolean',
        ]);

        $data['is_active'] = (int) $request->boolean('is_active', true);

        return $data;
    }
}
