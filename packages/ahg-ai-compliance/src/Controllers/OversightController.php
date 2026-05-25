<?php
/**
 * Heratio - admin UI for EU AI Act Article 14 human oversight policies.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Controllers;

use AhgAiCompliance\Models\AiOperatorAttestation;
use AhgAiCompliance\Models\AiReviewDecision;
use AhgAiCompliance\Services\OversightService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class OversightController
{
    public function __construct(private OversightService $service)
    {
    }

    public function index(): View
    {
        return view('ahg-ai-compliance::oversight.index', [
            'policies'        => $this->service->allPolicies(),
            'attestation'     => Auth::id() ? $this->service->latestAttestation((int) Auth::id()) : null,
            'hasAttestation'  => Auth::id() ? $this->service->hasActiveAttestation((int) Auth::id()) : false,
            'pendingReviews'  => AiReviewDecision::query()
                ->whereNotNull('countersigned_at')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
            'pendingCounter'  => AiReviewDecision::query()
                ->whereNull('countersigned_at')
                ->where(function ($q) {
                    $q->whereIn('service', $this->dualReviewServices());
                })
                ->orderBy('created_at')
                ->get(),
        ]);
    }

    public function updatePolicy(Request $request, int $id): RedirectResponse
    {
        $attrs = $request->validate([
            'requires_human_review' => 'sometimes|boolean',
            'confidence_threshold'  => 'required|numeric|between:0,1',
            'dual_review_required'  => 'sometimes|boolean',
            'automation_bias_prompt_text' => 'nullable|string|max:512',
        ]);

        $policy = $this->service->allPolicies()->firstWhere('id', $id);
        if ($policy === null) {
            return redirect()->route('ai-compliance.oversight.index')->with('error', 'Policy not found.');
        }

        $policy->requires_human_review = (bool) ($attrs['requires_human_review'] ?? false);
        $policy->dual_review_required  = (bool) ($attrs['dual_review_required'] ?? false);
        $policy->confidence_threshold  = (float) $attrs['confidence_threshold'];
        $policy->automation_bias_prompt_text = $attrs['automation_bias_prompt_text'] ?? null;
        $policy->save();

        return redirect()->route('ai-compliance.oversight.index')->with('status', "Policy for {$policy->service} updated.");
    }

    public function halt(Request $request, string $service): RedirectResponse
    {
        $reason = $request->input('reason', 'Operator halt via admin UI');
        $p = $this->service->halt($service, $reason);
        if ($p === null) {
            return redirect()->route('ai-compliance.oversight.index')->with('error', 'Service not found.');
        }
        return redirect()->route('ai-compliance.oversight.index')->with('status', "Halted {$service}. Receipt written.");
    }

    public function resume(string $service): RedirectResponse
    {
        $p = $this->service->resume($service);
        if ($p === null) {
            return redirect()->route('ai-compliance.oversight.index')->with('error', 'Service not found.');
        }
        return redirect()->route('ai-compliance.oversight.index')->with('status', "Resumed {$service}.");
    }

    public function haltAll(Request $request): RedirectResponse
    {
        $reason = $request->input('reason', 'Global halt via admin UI');
        $n = $this->service->haltAll($reason);
        return redirect()->route('ai-compliance.oversight.index')->with('status', "Halted {$n} services.");
    }

    public function attest(): RedirectResponse
    {
        if (Auth::id() === null) {
            return redirect()->route('ai-compliance.oversight.index')->with('error', 'You must be signed in to attest.');
        }
        $this->service->recordAttestation((int) Auth::id());
        return redirect()->route('ai-compliance.oversight.index')
            ->with('status', 'Automation-bias attestation recorded. Receipt written to the inference chain.');
    }

    public function countersign(int $reviewDecisionId): RedirectResponse
    {
        $row = $this->service->countersign($reviewDecisionId);
        if ($row === null) {
            return redirect()->route('ai-compliance.oversight.index')
                ->with('error', 'Cannot countersign: decision not found, or you are the original reviewer.');
        }
        return redirect()->route('ai-compliance.oversight.index')
            ->with('status', "Countersigned decision {$reviewDecisionId}. Art. 14(5) two-person verification complete.");
    }

    /**
     * @return array<int,string>
     */
    private function dualReviewServices(): array
    {
        return $this->service->allPolicies()
            ->filter(fn ($p) => (bool) $p->dual_review_required)
            ->pluck('service')
            ->all();
    }
}
