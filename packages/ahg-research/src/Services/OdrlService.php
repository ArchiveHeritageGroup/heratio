<?php

/**
 * OdrlService - Service for Heratio
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


use Illuminate\Support\Facades\DB;

/**
 * OdrlService - Open Digital Rights Language Policy Management
 *
 * Migrated from AtoM: ahgResearchPlugin/lib/Services/OdrlService.php
 */
class OdrlService
{
    public function createPolicy(array $data): int
    {
        $constraintsJson = null;
        if (isset($data['constraints_json'])) {
            $constraintsJson = is_string($data['constraints_json'])
                ? $data['constraints_json']
                : json_encode($data['constraints_json']);
        }

        $policyJson = null;
        if (isset($data['policy_json'])) {
            $policyJson = is_string($data['policy_json'])
                ? $data['policy_json']
                : json_encode($data['policy_json']);
        }

        return DB::table('research_rights_policy')->insertGetId([
            'target_type' => $data['target_type'],
            'target_id' => (int) $data['target_id'],
            'policy_type' => $data['policy_type'],
            'action_type' => $data['action_type'],
            'constraints_json' => $constraintsJson,
            'policy_json' => $policyJson,
            'created_by' => isset($data['created_by']) ? (int) $data['created_by'] : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getAllPolicies(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $query = DB::table('research_rights_policy');

        if (!empty($filters['target_type'])) {
            $query->where('target_type', $filters['target_type']);
        }
        if (!empty($filters['policy_type'])) {
            $query->where('policy_type', $filters['policy_type']);
        }
        if (!empty($filters['action_type'])) {
            $query->where('action_type', $filters['action_type']);
        }
        if (!empty($filters['created_by'])) {
            $query->where('created_by', (int) $filters['created_by']);
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('created_at')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();

        return ['items' => $items, 'total' => $total];
    }

    public function deletePolicy(int $id): bool
    {
        DB::table('research_access_decision')->where('policy_id', $id)->delete();
        return DB::table('research_rights_policy')->where('id', $id)->delete() > 0;
    }

    public function evaluateAccess(string $targetType, int $targetId, int $researcherId, string $action): array
    {
        $policies = DB::table('research_rights_policy')
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('action_type', $action)
            ->orderBy('policy_type')
            ->get()
            ->toArray();

        $matchingPolicies = [];
        $permitted = null;
        $rationale = '';

        foreach ($policies as $policy) {
            if ($policy->policy_type === 'prohibition') {
                if ($this->evaluateConstraints($policy, $researcherId)) {
                    $matchingPolicies[] = $policy;
                    $permitted = false;
                    $rationale = "Action \"{$action}\" is prohibited on {$targetType}:{$targetId} (policy #{$policy->id})";
                    break;
                }
            }
        }

        if ($permitted === null) {
            foreach ($policies as $policy) {
                if ($policy->policy_type === 'permission') {
                    if ($this->evaluateConstraints($policy, $researcherId)) {
                        $matchingPolicies[] = $policy;
                        $permitted = true;
                        $rationale = "Action \"{$action}\" is permitted on {$targetType}:{$targetId} (policy #{$policy->id})";
                        break;
                    }
                }
            }
        }

        if ($permitted === null) {
            $permitted = false;
            $rationale = "No matching policy found for action \"{$action}\" on {$targetType}:{$targetId}. Access denied by default.";
        }

        $decisionPolicyId = !empty($matchingPolicies) ? $matchingPolicies[0]->id : 0;

        DB::table('research_access_decision')->insert([
            'policy_id' => $decisionPolicyId,
            'researcher_id' => $researcherId,
            'action_requested' => $action,
            'decision' => $permitted ? 'permitted' : 'denied',
            'rationale' => $rationale,
            'evaluated_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'permitted' => $permitted,
            'policies' => $matchingPolicies,
            'rationale' => $rationale,
        ];
    }

    private function evaluateConstraints(object $policy, int $researcherId): bool
    {
        if (empty($policy->constraints_json)) {
            return true;
        }

        $constraints = json_decode($policy->constraints_json, true);
        if (!is_array($constraints)) {
            return true;
        }

        if (isset($constraints['researcher_ids']) && is_array($constraints['researcher_ids'])) {
            if (!in_array($researcherId, $constraints['researcher_ids'], false)) {
                return false;
            }
        }

        $now = date('Y-m-d H:i:s');
        if (!empty($constraints['date_from']) && $now < $constraints['date_from']) {
            return false;
        }
        if (!empty($constraints['date_to']) && $now > $constraints['date_to']) {
            return false;
        }

        if (isset($constraints['max_uses']) && (int) $constraints['max_uses'] > 0) {
            $currentUses = DB::table('research_access_decision')
                ->where('policy_id', $policy->id)
                ->where('researcher_id', $researcherId)
                ->where('decision', 'permitted')
                ->count();

            if ($currentUses >= (int) $constraints['max_uses']) {
                return false;
            }
        }

        return true;
    }
}
