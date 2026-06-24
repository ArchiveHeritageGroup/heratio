<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgResearch\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Human-action provenance for research controllers (#1326).
 *
 * Every research controller that mutates a resource (store/update/destroy/
 * accept/reject/...) must leave a traceable human-action trail in
 * research_activity_log. This trait is the single, side-effect-safe way to do
 * that: it resolves the acting researcher from the auth user, captures the
 * session/IP/user-agent, and never throws (provenance must not break the
 * mutation it records).
 *
 * Mirrors ProjectService::logActivity() but is dependency-free so it can be
 * mixed into any controller without constructor wiring.
 */
trait LogsResearchActivity
{
    /**
     * Record a human action against research_activity_log.
     *
     * @param string      $activityType e.g. create, update, delete, accept, reject, approve, merge, save
     * @param string|null $entityType   logical entity touched (e.g. 'research_milestone')
     * @param int|null    $entityId     primary key of the entity, when known
     * @param string|null $entityTitle  human label for the entity, when known
     * @param array|null  $details      extra context (json-encoded)
     * @param int|null    $projectId    owning project, when known
     */
    protected function logResearchActivity(
        string $activityType,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $entityTitle = null,
        ?array $details = null,
        ?int $projectId = null
    ): void {
        try {
            $researcherId = 0;
            $userId = Auth::id();
            if ($userId) {
                $researcherId = (int) (DB::table('research_researcher')
                    ->where('user_id', $userId)
                    ->value('id') ?? 0);
            }

            DB::table('research_activity_log')->insert([
                'researcher_id' => $researcherId,
                'project_id'    => $projectId,
                'activity_type' => $activityType,
                'entity_type'   => $entityType,
                'entity_id'     => $entityId,
                'entity_title'  => $entityTitle !== null ? mb_substr($entityTitle, 0, 500) : null,
                'details'       => $details ? json_encode($details) : null,
                'session_id'    => session()->getId() ?: null,
                'ip_address'    => request()->ip(),
                'user_agent'    => substr(request()->userAgent() ?? '', 0, 500),
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Provenance is best-effort: a logging failure must never break the
            // mutation that triggered it. Table missing on a fresh boot, etc.
        }
    }
}
