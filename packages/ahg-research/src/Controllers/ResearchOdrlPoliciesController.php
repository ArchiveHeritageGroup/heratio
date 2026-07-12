<?php

/**
 * ResearchOdrlPoliciesController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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



namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Concerns\AuthorizesProjectAccess;
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Services\ResearchService;
use AhgResearch\Services\OdrlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchOdrlPoliciesController - ODRL rights-policy admin + supporting AJAX
 * autocompletes (researcher + policy target).
 *
 * Extracted verbatim from ResearchController as part of the monolith
 * decomposition (issue #1269). The three public endpoints
 * (researcherAutocomplete, targetAutocomplete, odrlPolicies) plus the private
 * resolveTargetName helper move as a self-contained unit: they cross-call only
 * the shared trait helper (getSidebarData), the injected ResearchService
 * (getResearcherByUserId), and the private resolveTargetName helper carried over
 * with them. No cross-calls to other ResearchController methods existed, so the
 * move is a verbatim lift.
 */
class ResearchOdrlPoliciesController extends Controller
{
    use ResearchControllerHelpers;
    use AuthorizesProjectAccess;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    /**
     * AJAX: Researcher autocomplete (returns JSON).
     */
    public function researcherAutocomplete(Request $request)
    {
        $query = $request->get('query', '');
        $results = DB::table('research_researcher')
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'LIKE', "%{$query}%")
                  ->orWhere('last_name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->select('id', DB::raw("CONCAT(first_name, ' ', last_name) as name"), 'email')
            ->limit(20)
            ->get();

        return response()->json($results);
    }

    /**
     * AJAX: Target autocomplete for ODRL policies (returns JSON).
     */
    public function targetAutocomplete(Request $request)
    {
        $type = $request->get('type', '');
        $query = $request->get('query', '');
        $culture = app()->getLocale();

        $map = [
            'archival_description' => [
                'query' => fn () => DB::table('information_object')
                    ->join('information_object_i18n', function ($j) use ($culture) {
                        $j->on('information_object.id', '=', 'information_object_i18n.id')
                          ->where('information_object_i18n.culture', '=', $culture);
                    })
                    ->where('information_object_i18n.title', 'LIKE', "%{$query}%")
                    ->select('information_object.id', 'information_object_i18n.title as name')
                    ->limit(20)->get(),
            ],
            'collection' => [
                'query' => fn () => DB::table('research_collection')
                    ->where('name', 'LIKE', "%{$query}%")
                    ->select('id', 'name')
                    ->limit(20)->get(),
            ],
            'project' => [
                'query' => fn () => DB::table('research_project')
                    ->where('title', 'LIKE', "%{$query}%")
                    ->select('id', 'title as name')
                    ->limit(20)->get(),
            ],
            'snapshot' => [
                'query' => fn () => DB::table('research_snapshot')
                    ->where('title', 'LIKE', "%{$query}%")
                    ->select('id', 'title as name')
                    ->limit(20)->get(),
            ],
            'annotation' => [
                'query' => fn () => DB::table('research_annotation')
                    ->where('title', 'LIKE', "%{$query}%")
                    ->select('id', 'title as name')
                    ->limit(20)->get(),
            ],
            'assertion' => [
                'query' => fn () => DB::table('research_assertion')
                    ->where('assertion_type', 'LIKE', "%{$query}%")
                    ->select('id', 'assertion_type as name')
                    ->limit(20)->get(),
            ],
        ];

        if (!isset($map[$type])) {
            return response()->json([]);
        }

        try {
            return response()->json($map[$type]['query']());
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    public function odrlPolicies(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $odrlService = new OdrlService();

        if ($request->isMethod('post')) {
            $formAction = $request->input('form_action');

            if ($formAction === 'create') {
                // SECURITY (#1308-parity): a policy targeting a project may only be
                // authored by a member (owner/accepted collaborator) or an admin.
                if ($request->input('target_type') === 'project') {
                    $this->assertProjectMember((int) $request->input('target_id'), (int) $researcher->id);
                }
                $constraintsJson = $request->input('constraints_json');
                if ($constraintsJson) {
                    $decoded = json_decode($constraintsJson, true);
                    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                        return redirect('/research/odrlPolicies')->with('error', 'Invalid JSON in constraints.');
                    }
                }

                $odrlService->createPolicy([
                    'target_type' => $request->input('target_type'),
                    'target_id' => (int) $request->input('target_id'),
                    'policy_type' => $request->input('policy_type'),
                    'action_type' => $request->input('action_type'),
                    'constraints_json' => $constraintsJson ?: null,
                    'created_by' => $researcher->id,
                ]);
                return redirect('/research/odrlPolicies')->with('success', 'Policy created.');
            }

            if ($formAction === 'update') {
                $policyId = (int) $request->input('policy_id');
                // SECURITY (#1308-parity): a policy targeting a project may only be
                // rewritten by a member (owner/accepted collaborator) or an admin.
                if ($request->input('target_type') === 'project') {
                    $this->assertProjectMember((int) $request->input('target_id'), (int) $researcher->id);
                }
                $constraintsJson = $request->input('constraints_json');
                if ($constraintsJson) {
                    $decoded = json_decode($constraintsJson, true);
                    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                        return redirect('/research/odrlPolicies')->with('error', 'Invalid JSON in constraints.');
                    }
                }

                DB::table('research_rights_policy')
                    ->where('id', $policyId)
                    ->update([
                        'target_type'      => $request->input('target_type'),
                        'target_id'        => (int) $request->input('target_id'),
                        'policy_type'      => $request->input('policy_type'),
                        'action_type'      => $request->input('action_type'),
                        'constraints_json' => $constraintsJson ?: null,
                        'updated_at'       => now(),
                    ]);
                return redirect('/research/odrlPolicies')->with('success', 'Policy updated.');
            }

            if ($formAction === 'delete') {
                $odrlService->deletePolicy((int) $request->input('policy_id'));
                return redirect('/research/odrlPolicies')->with('success', 'Policy deleted.');
            }
        }

        $filters = [
            'target_type' => $request->input('filter_target_type'),
            'policy_type' => $request->input('filter_policy_type'),
            'action_type' => $request->input('filter_action_type'),
        ];

        $page = max(1, (int) $request->input('page', 1));
        $policies = $odrlService->getAllPolicies($filters, 25, ($page - 1) * 25);

        // Resolve target names and researcher names for display
        $culture = app()->getLocale();
        $targetNameCache = [];
        $researcherNameCache = [];

        foreach ($policies['items'] as $p) {
            // Resolve target name
            $cacheKey = $p->target_type . ':' . $p->target_id;
            if (!isset($targetNameCache[$cacheKey])) {
                $targetNameCache[$cacheKey] = $this->resolveTargetName($p->target_type, $p->target_id, $culture);
            }
            $p->target_name = $targetNameCache[$cacheKey];

            // Resolve researcher names in constraints
            $p->resolved_constraints = [];
            if (!empty($p->constraints_json)) {
                $constraints = json_decode($p->constraints_json, true);
                if (is_array($constraints)) {
                    foreach ($constraints as $ck => $cv) {
                        if ($ck === 'researcher_ids' && is_array($cv)) {
                            $names = [];
                            foreach ($cv as $rid) {
                                if (!isset($researcherNameCache[$rid])) {
                                    $r = DB::table('research_researcher')->where('id', $rid)
                                        ->select(DB::raw("CONCAT(first_name, ' ', last_name) as name"))->first();
                                    $researcherNameCache[$rid] = $r->name ?? "#{$rid}";
                                }
                                $names[] = $researcherNameCache[$rid];
                            }
                            $p->resolved_constraints['Researchers'] = implode(', ', $names);
                        } elseif ($ck === 'date_from') {
                            $p->resolved_constraints['From'] = $cv;
                        } elseif ($ck === 'date_to') {
                            $p->resolved_constraints['Until'] = $cv;
                        } elseif ($ck === 'max_uses') {
                            $p->resolved_constraints['Max uses'] = $cv;
                        } else {
                            $p->resolved_constraints[$ck] = is_array($cv) ? implode(', ', $cv) : $cv;
                        }
                    }
                }
            }
        }

        return view('research::research.odrl-policies', array_merge(
            $this->getSidebarData('odrlPolicies'),
            compact('policies')
        ));
    }

    /**
     * Resolve a target type + ID to a human-readable name.
     */
    private function resolveTargetName(string $type, int $id, string $culture = 'en'): string
    {
        try {
            return match ($type) {
                'archival_description' => DB::table('information_object_i18n')
                    ->where('id', $id)->where('culture', $culture)->value('title') ?? "AD #{$id}",
                'collection' => DB::table('research_collection')->where('id', $id)->value('name') ?? "Collection #{$id}",
                'project' => DB::table('research_project')->where('id', $id)->value('title') ?? "Project #{$id}",
                'snapshot' => DB::table('research_snapshot')->where('id', $id)->value('title') ?? "Snapshot #{$id}",
                'annotation' => DB::table('research_annotation')->where('id', $id)->value('title') ?? "Annotation #{$id}",
                'assertion' => DB::table('research_assertion')->where('id', $id)->value('assertion_type') ?? "Assertion #{$id}",
                default => "#{$id}",
            };
        } catch (\Exception $e) {
            return "#{$id}";
        }
    }
}
