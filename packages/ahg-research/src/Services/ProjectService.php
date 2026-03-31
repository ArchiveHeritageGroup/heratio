<?php

/**
 * ProjectService - Service for Heratio
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
 * ProjectService - Research Project Management Service
 *
 * Handles research projects, collaboration, milestones, and activity tracking.
 *
 * Migrated from AtoM: ahgResearchPlugin/lib/Services/ProjectService.php
 */
class ProjectService
{
    // =========================================================================
    // PROJECT MANAGEMENT
    // =========================================================================

    /**
     * Create a new research project.
     *
     * @param int $ownerId The researcher who owns the project
     * @param array $data Project data
     * @return int The new project ID
     */
    public function createProject(int $ownerId, array $data): int
    {
        $projectId = DB::table('research_project')->insertGetId([
            'owner_id' => $ownerId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'project_type' => $data['project_type'] ?? 'personal',
            'institution' => $data['institution'] ?? null,
            'supervisor' => $data['supervisor'] ?? null,
            'funding_source' => $data['funding_source'] ?? null,
            'grant_number' => $data['grant_number'] ?? null,
            'ethics_approval' => $data['ethics_approval'] ?? null,
            'start_date' => !empty($data['start_date']) ? $data['start_date'] : null,
            'expected_end_date' => !empty($data['expected_end_date']) ? $data['expected_end_date'] : null,
            'status' => 'planning',
            'visibility' => $data['visibility'] ?? 'private',
            'share_token' => bin2hex(random_bytes(32)),
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Add owner as collaborator with owner role
        DB::table('research_project_collaborator')->insert([
            'project_id' => $projectId,
            'researcher_id' => $ownerId,
            'role' => 'owner',
            'invited_by' => $ownerId,
            'invited_at' => date('Y-m-d H:i:s'),
            'accepted_at' => date('Y-m-d H:i:s'),
            'status' => 'accepted',
        ]);

        $this->logActivity($ownerId, $projectId, 'create', 'project', $projectId, $data['title']);

        return $projectId;
    }

    /**
     * Get a project by ID.
     *
     * @param int $projectId The project ID
     * @return object|null The project or null if not found
     */
    public function getProject(int $projectId): ?object
    {
        $project = DB::table('research_project as p')
            ->leftJoin('research_researcher as r', 'p.owner_id', '=', 'r.id')
            ->where('p.id', $projectId)
            ->select(
                'p.*',
                'r.first_name as owner_first_name',
                'r.last_name as owner_last_name',
                'r.email as owner_email'
            )
            ->first();

        if ($project) {
            $project->collaborators = $this->getCollaborators($projectId);
            $project->resource_count = DB::table('research_project_resource')
                ->where('project_id', $projectId)
                ->count();
            $project->milestone_count = DB::table('research_project_milestone')
                ->where('project_id', $projectId)
                ->count();
            $project->completed_milestones = DB::table('research_project_milestone')
                ->where('project_id', $projectId)
                ->where('status', 'completed')
                ->count();
        }

        return $project;
    }

    /**
     * Get projects for a researcher (owned or collaborating).
     *
     * @param int $researcherId The researcher ID
     * @param array $filters Optional filters (status, type, search)
     * @return array List of projects
     */
    public function getProjects(int $researcherId, array $filters = []): array
    {
        $query = DB::table('research_project as p')
            ->join('research_project_collaborator as pc', function ($join) use ($researcherId) {
                $join->on('p.id', '=', 'pc.project_id')
                    ->where('pc.researcher_id', '=', $researcherId)
                    ->where('pc.status', '=', 'accepted');
            })
            ->leftJoin('research_researcher as r', 'p.owner_id', '=', 'r.id')
            ->select(
                'p.*',
                'pc.role as my_role',
                'r.first_name as owner_first_name',
                'r.last_name as owner_last_name'
            );

        if (!empty($filters['status'])) {
            $query->where('p.status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->where('p.project_type', $filters['type']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('p.title', 'like', $search)
                    ->orWhere('p.description', 'like', $search);
            });
        }

        if (isset($filters['owned_only']) && $filters['owned_only']) {
            $query->where('p.owner_id', $researcherId);
        }

        $projects = $query->orderBy('p.updated_at', 'desc')->get()->toArray();

        // Add counts
        foreach ($projects as &$project) {
            $project->collaborator_count = DB::table('research_project_collaborator')
                ->where('project_id', $project->id)
                ->where('status', 'accepted')
                ->count();
            $project->resource_count = DB::table('research_project_resource')
                ->where('project_id', $project->id)
                ->count();
        }

        return $projects;
    }

    /**
     * Update a project.
     *
     * @param int $projectId The project ID
     * @param array $data Fields to update
     * @return bool Success status
     */
    public function updateProject(int $projectId, array $data): bool
    {
        $allowed = [
            'title', 'description', 'project_type', 'institution', 'supervisor',
            'funding_source', 'grant_number', 'ethics_approval', 'start_date',
            'expected_end_date', 'actual_end_date', 'status', 'visibility', 'metadata',
        ];

        $updateData = array_intersect_key($data, array_flip($allowed));
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        // Convert empty date strings to NULL (HTML date inputs submit '' when blank)
        foreach (['start_date', 'expected_end_date', 'actual_end_date', 'due_date'] as $dateField) {
            if (array_key_exists($dateField, $updateData) && $updateData[$dateField] === '') {
                $updateData[$dateField] = null;
            }
        }

        if (isset($updateData['metadata']) && is_array($updateData['metadata'])) {
            $updateData['metadata'] = json_encode($updateData['metadata']);
        }

        return DB::table('research_project')
            ->where('id', $projectId)
            ->update($updateData) >= 0;
    }

    /**
     * Delete a project.
     *
     * @param int $projectId The project ID
     * @return bool Success status
     */
    public function deleteProject(int $projectId): bool
    {
        // Delete related records first
        DB::table('research_project_collaborator')->where('project_id', $projectId)->delete();
        DB::table('research_project_resource')->where('project_id', $projectId)->delete();
        DB::table('research_project_milestone')->where('project_id', $projectId)->delete();
        DB::table('research_discussion')->where('project_id', $projectId)->delete();

        return DB::table('research_project')->where('id', $projectId)->delete() > 0;
    }

    /**
     * Check if a researcher can access a project.
     *
     * @param int $projectId The project ID
     * @param int $researcherId The researcher ID
     * @param string|null $requiredRole Minimum role required (owner, editor, contributor, viewer)
     * @return bool True if access is allowed
     */
    public function canAccess(int $projectId, int $researcherId, ?string $requiredRole = null): bool
    {
        $project = DB::table('research_project')->where('id', $projectId)->first();

        if (!$project) {
            return false;
        }

        // Public projects can be viewed by anyone
        if ($project->visibility === 'public' && ($requiredRole === null || $requiredRole === 'viewer')) {
            return true;
        }

        // Check collaboration
        $collab = DB::table('research_project_collaborator')
            ->where('project_id', $projectId)
            ->where('researcher_id', $researcherId)
            ->where('status', 'accepted')
            ->first();

        if (!$collab) {
            return false;
        }

        if ($requiredRole === null) {
            return true;
        }

        $roleHierarchy = ['owner' => 4, 'editor' => 3, 'contributor' => 2, 'viewer' => 1];
        $userLevel = $roleHierarchy[$collab->role] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    // =========================================================================
    // COLLABORATION
    // =========================================================================

    /**
     * Invite a researcher to collaborate on a project.
     *
     * @param int $projectId The project ID
     * @param int $researcherId The researcher to invite
     * @param string $role The role to assign
     * @param int $invitedBy The researcher sending the invitation
     * @return array Result with success status
     */
    public function inviteCollaborator(int $projectId, int $researcherId, string $role, int $invitedBy): array
    {
        // Check if already a collaborator
        $existing = DB::table('research_project_collaborator')
            ->where('project_id', $projectId)
            ->where('researcher_id', $researcherId)
            ->first();

        if ($existing) {
            if ($existing->status === 'accepted') {
                return ['success' => false, 'error' => 'Researcher is already a collaborator'];
            }
            if ($existing->status === 'pending') {
                return ['success' => false, 'error' => 'Invitation already pending'];
            }
            // Re-invite if previously declined or removed
            DB::table('research_project_collaborator')
                ->where('id', $existing->id)
                ->update([
                    'role' => $role,
                    'invited_by' => $invitedBy,
                    'invited_at' => date('Y-m-d H:i:s'),
                    'accepted_at' => null,
                    'status' => 'pending',
                ]);
            return ['success' => true, 'message' => 'Invitation sent'];
        }

        DB::table('research_project_collaborator')->insert([
            'project_id' => $projectId,
            'researcher_id' => $researcherId,
            'role' => $role,
            'invited_by' => $invitedBy,
            'invited_at' => date('Y-m-d H:i:s'),
            'status' => 'pending',
        ]);

        return ['success' => true, 'message' => 'Invitation sent'];
    }

    /**
     * Accept a project invitation.
     *
     * @param int $projectId The project ID
     * @param int $researcherId The researcher accepting
     * @return bool Success status
     */
    public function acceptInvitation(int $projectId, int $researcherId): bool
    {
        return DB::table('research_project_collaborator')
            ->where('project_id', $projectId)
            ->where('researcher_id', $researcherId)
            ->where('status', 'pending')
            ->update([
                'accepted_at' => date('Y-m-d H:i:s'),
                'status' => 'accepted',
            ]) > 0;
    }

    /**
     * Decline a project invitation.
     *
     * @param int $projectId The project ID
     * @param int $researcherId The researcher declining
     * @return bool Success status
     */
    public function declineInvitation(int $projectId, int $researcherId): bool
    {
        return DB::table('research_project_collaborator')
            ->where('project_id', $projectId)
            ->where('researcher_id', $researcherId)
            ->where('status', 'pending')
            ->update(['status' => 'declined']) > 0;
    }

    /**
     * Remove a collaborator from a project.
     *
     * @param int $projectId The project ID
     * @param int $researcherId The researcher to remove
     * @return bool Success status
     */
    public function removeCollaborator(int $projectId, int $researcherId): bool
    {
        // Cannot remove the owner
        $project = DB::table('research_project')->where('id', $projectId)->first();
        if ($project && $project->owner_id === $researcherId) {
            return false;
        }

        return DB::table('research_project_collaborator')
            ->where('project_id', $projectId)
            ->where('researcher_id', $researcherId)
            ->update(['status' => 'removed']) > 0;
    }

    /**
     * Get collaborators for a project.
     *
     * @param int $projectId The project ID
     * @param bool $acceptedOnly Only return accepted collaborators
     * @return array List of collaborators
     */
    public function getCollaborators(int $projectId, bool $acceptedOnly = false): array
    {
        $query = DB::table('research_project_collaborator as pc')
            ->join('research_researcher as r', 'pc.researcher_id', '=', 'r.id')
            ->where('pc.project_id', $projectId)
            ->select(
                'pc.*',
                'r.first_name',
                'r.last_name',
                'r.email',
                'r.institution',
                'r.orcid_id'
            );

        if ($acceptedOnly) {
            $query->where('pc.status', 'accepted');
        }

        return $query->orderByRaw("FIELD(pc.role, 'owner', 'editor', 'contributor', 'viewer')")
            ->get()
            ->toArray();
    }

    /**
     * Update a collaborator's role.
     *
     * @param int $projectId The project ID
     * @param int $researcherId The researcher ID
     * @param string $newRole The new role
     * @return bool Success status
     */
    public function updateCollaboratorRole(int $projectId, int $researcherId, string $newRole): bool
    {
        // Cannot change owner's role
        $collab = DB::table('research_project_collaborator')
            ->where('project_id', $projectId)
            ->where('researcher_id', $researcherId)
            ->first();

        if (!$collab || $collab->role === 'owner') {
            return false;
        }

        return DB::table('research_project_collaborator')
            ->where('project_id', $projectId)
            ->where('researcher_id', $researcherId)
            ->update(['role' => $newRole]) > 0;
    }

    /**
     * Get pending invitations for a researcher.
     *
     * @param int $researcherId The researcher ID
     * @return array List of pending invitations
     */
    public function getPendingInvitations(int $researcherId): array
    {
        return DB::table('research_project_collaborator as pc')
            ->join('research_project as p', 'pc.project_id', '=', 'p.id')
            ->join('research_researcher as inviter', 'pc.invited_by', '=', 'inviter.id')
            ->where('pc.researcher_id', $researcherId)
            ->where('pc.status', 'pending')
            ->select(
                'pc.*',
                'p.title as project_title',
                'p.description as project_description',
                'inviter.first_name as inviter_first_name',
                'inviter.last_name as inviter_last_name'
            )
            ->orderBy('pc.invited_at', 'desc')
            ->get()
            ->toArray();
    }

    // =========================================================================
    // RESOURCES
    // =========================================================================

    /**
     * Add a resource to a project.
     *
     * @param int $projectId The project ID
     * @param array $data Resource data
     * @param int $addedBy Researcher ID who added it
     * @return int The new resource ID
     */
    public function addResource(int $projectId, array $data, int $addedBy): int
    {
        $maxOrder = DB::table('research_project_resource')
            ->where('project_id', $projectId)
            ->max('sort_order') ?? 0;

        return DB::table('research_project_resource')->insertGetId([
            'project_id' => $projectId,
            'resource_type' => $data['resource_type'],
            'resource_id' => $data['resource_id'] ?? null,
            'object_id' => $data['object_id'] ?? null,
            'external_url' => $data['external_url'] ?? null,
            'link_type' => $data['link_type'] ?? null,
            'link_metadata' => isset($data['link_metadata']) ? json_encode($data['link_metadata']) : null,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
            'tags' => $data['tags'] ?? null,
            'added_by' => $addedBy,
            'sort_order' => $maxOrder + 1,
            'added_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Remove a resource from a project.
     *
     * @param int $resourceId The resource ID
     * @return bool Success status
     */
    public function removeResource(int $resourceId): bool
    {
        return DB::table('research_project_resource')
            ->where('id', $resourceId)
            ->delete() > 0;
    }

    /**
     * Get resources for a project.
     *
     * @param int $projectId The project ID
     * @param string|null $type Filter by resource type
     * @return array List of resources
     */
    public function getResources(int $projectId, ?string $type = null): array
    {
        $query = DB::table('research_project_resource as pr')
            ->leftJoin('research_researcher as r', 'pr.added_by', '=', 'r.id')
            ->where('pr.project_id', $projectId)
            ->select(
                'pr.*',
                'r.first_name as added_by_first_name',
                'r.last_name as added_by_last_name'
            );

        if ($type) {
            $query->where('pr.resource_type', $type);
        }

        $resources = $query->orderBy('pr.sort_order')->get()->toArray();

        // Enrich resources with additional data based on type
        foreach ($resources as &$resource) {
            if ($resource->resource_type === 'collection' && $resource->resource_id) {
                $resource->collection = DB::table('research_collection')
                    ->where('id', $resource->resource_id)
                    ->first();
            } elseif ($resource->resource_type === 'saved_search' && $resource->resource_id) {
                $resource->saved_search = DB::table('research_saved_search')
                    ->where('id', $resource->resource_id)
                    ->first();
            } elseif ($resource->resource_type === 'bibliography' && $resource->resource_id) {
                $resource->bibliography = DB::table('research_bibliography')
                    ->where('id', $resource->resource_id)
                    ->first();
            } elseif ($resource->object_id) {
                $resource->object = DB::table('information_object_i18n')
                    ->where('id', $resource->object_id)
                    ->where('culture', 'en')
                    ->first();
            }
        }

        return $resources;
    }

    /**
     * Link a collection to a project.
     *
     * @param int $projectId The project ID
     * @param int $collectionId The collection ID
     * @param int $addedBy Researcher ID
     * @return int The resource ID
     */
    public function linkCollection(int $projectId, int $collectionId, int $addedBy): int
    {
        $collection = DB::table('research_collection')
            ->where('id', $collectionId)
            ->first();

        return $this->addResource($projectId, [
            'resource_type' => 'collection',
            'resource_id' => $collectionId,
            'title' => $collection->name ?? 'Collection',
            'description' => $collection->description ?? null,
        ], $addedBy);
    }

    /**
     * Link a saved search to a project.
     *
     * @param int $projectId The project ID
     * @param int $searchId The saved search ID
     * @param int $addedBy Researcher ID
     * @return int The resource ID
     */
    public function linkSavedSearch(int $projectId, int $searchId, int $addedBy): int
    {
        $search = DB::table('research_saved_search')
            ->where('id', $searchId)
            ->first();

        return $this->addResource($projectId, [
            'resource_type' => 'saved_search',
            'resource_id' => $searchId,
            'title' => $search->name ?? 'Saved Search',
            'description' => $search->description ?? null,
        ], $addedBy);
    }

    // =========================================================================
    // MILESTONES
    // =========================================================================

    /**
     * Add a milestone to a project.
     *
     * @param int $projectId The project ID
     * @param array $data Milestone data
     * @return int The milestone ID
     */
    public function addMilestone(int $projectId, array $data): int
    {
        $maxOrder = DB::table('research_project_milestone')
            ->where('project_id', $projectId)
            ->max('sort_order') ?? 0;

        return DB::table('research_project_milestone')->insertGetId([
            'project_id' => $projectId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => !empty($data['due_date']) ? $data['due_date'] : null,
            'status' => 'pending',
            'sort_order' => $maxOrder + 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update a milestone.
     *
     * @param int $milestoneId The milestone ID
     * @param array $data Fields to update
     * @return bool Success status
     */
    public function updateMilestone(int $milestoneId, array $data): bool
    {
        $allowed = ['title', 'description', 'due_date', 'status', 'sort_order'];
        $updateData = array_intersect_key($data, array_flip($allowed));

        return DB::table('research_project_milestone')
            ->where('id', $milestoneId)
            ->update($updateData) >= 0;
    }

    /**
     * Mark a milestone as completed.
     *
     * @param int $milestoneId The milestone ID
     * @param int $completedBy Researcher ID who completed it
     * @return bool Success status
     */
    public function completeMilestone(int $milestoneId, int $completedBy): bool
    {
        return DB::table('research_project_milestone')
            ->where('id', $milestoneId)
            ->update([
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
                'completed_by' => $completedBy,
            ]) > 0;
    }

    /**
     * Get milestones for a project.
     *
     * @param int $projectId The project ID
     * @param string|null $status Filter by status
     * @return array List of milestones
     */
    public function getMilestones(int $projectId, ?string $status = null): array
    {
        $query = DB::table('research_project_milestone as pm')
            ->leftJoin('research_researcher as r', 'pm.completed_by', '=', 'r.id')
            ->where('pm.project_id', $projectId)
            ->select(
                'pm.*',
                'r.first_name as completed_by_first_name',
                'r.last_name as completed_by_last_name'
            );

        if ($status) {
            $query->where('pm.status', $status);
        }

        return $query->orderBy('pm.sort_order')->get()->toArray();
    }

    /**
     * Delete a milestone.
     *
     * @param int $milestoneId The milestone ID
     * @return bool Success status
     */
    public function deleteMilestone(int $milestoneId): bool
    {
        return DB::table('research_project_milestone')
            ->where('id', $milestoneId)
            ->delete() > 0;
    }

    // =========================================================================
    // ACTIVITY LOGGING
    // =========================================================================

    /**
     * Log an activity for a researcher.
     *
     * @param int $researcherId The researcher ID
     * @param int|null $projectId The project ID (optional)
     * @param string $activityType Activity type
     * @param string|null $entityType Entity type
     * @param int|null $entityId Entity ID
     * @param string|null $entityTitle Entity title
     * @param array|null $details Additional details
     */
    public function logActivity(
        int $researcherId,
        ?int $projectId,
        string $activityType,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $entityTitle = null,
        ?array $details = null
    ): void {
        DB::table('research_activity_log')->insert([
            'researcher_id' => $researcherId,
            'project_id' => $projectId,
            'activity_type' => $activityType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_title' => $entityTitle,
            'details' => $details ? json_encode($details) : null,
            'session_id' => session()->getId() ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => substr(request()->userAgent() ?? '', 0, 500),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get activity log for a researcher.
     *
     * @param int $researcherId The researcher ID
     * @param array $filters Optional filters (project_id, activity_type, date_from, date_to)
     * @param int $limit Maximum records to return
     * @param int $offset Offset for pagination
     * @return array List of activities
     */
    public function getActivityLog(int $researcherId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = DB::table('research_activity_log')
            ->where('researcher_id', $researcherId);

        if (!empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (!empty($filters['activity_type'])) {
            $query->where('activity_type', $filters['activity_type']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();
    }

    /**
     * Get activity log for a project.
     *
     * @param int $projectId The project ID
     * @param int $limit Maximum records to return
     * @param int $offset Offset for pagination
     * @return array List of activities
     */
    public function getProjectActivity(int $projectId, int $limit = 50, int $offset = 0): array
    {
        return DB::table('research_activity_log as a')
            ->join('research_researcher as r', 'a.researcher_id', '=', 'r.id')
            ->where('a.project_id', $projectId)
            ->select(
                'a.*',
                'r.first_name',
                'r.last_name'
            )
            ->orderBy('a.created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();
    }

    /**
     * Get activity statistics for a researcher.
     *
     * @param int $researcherId The researcher ID
     * @param string $period Period for stats (day, week, month, year)
     * @return array Activity statistics
     */
    public function getActivityStats(int $researcherId, string $period = 'month'): array
    {
        $dateFrom = match ($period) {
            'day' => date('Y-m-d 00:00:00'),
            'week' => date('Y-m-d 00:00:00', strtotime('-7 days')),
            'month' => date('Y-m-d 00:00:00', strtotime('-30 days')),
            'year' => date('Y-m-d 00:00:00', strtotime('-365 days')),
            default => date('Y-m-d 00:00:00', strtotime('-30 days')),
        };

        $stats = DB::table('research_activity_log')
            ->where('researcher_id', $researcherId)
            ->where('created_at', '>=', $dateFrom)
            ->selectRaw('activity_type, COUNT(*) as count')
            ->groupBy('activity_type')
            ->pluck('count', 'activity_type')
            ->toArray();

        return [
            'period' => $period,
            'date_from' => $dateFrom,
            'total' => array_sum($stats),
            'by_type' => $stats,
        ];
    }

    // =========================================================================
    // CLIPBOARD INTEGRATION
    // =========================================================================

    /**
     * Add items from clipboard to a project.
     */
    public function addClipboardItems(int $projectId, int $researcherId, array $objectIds, ?string $notes = null): int
    {
        $added = 0;
        foreach ($objectIds as $objectId) {
            $objectId = (int) $objectId;
            if ($objectId <= 0) continue;

            // Skip duplicates
            $exists = DB::table('research_clipboard_project')
                ->where('project_id', $projectId)
                ->where('researcher_id', $researcherId)
                ->where('object_id', $objectId)
                ->exists();

            if (!$exists) {
                DB::table('research_clipboard_project')->insert([
                    'researcher_id' => $researcherId,
                    'project_id' => $projectId,
                    'object_id' => $objectId,
                    'notes' => $notes,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $added++;
            }
        }

        if ($added > 0) {
            $this->logActivity($researcherId, $projectId, 'clipboard_add', 'clipboard', null, $added . ' items added from clipboard');
        }

        return $added;
    }

    /**
     * Get clipboard items for a project.
     */
    public function getClipboardItems(int $projectId): array
    {
        return DB::table('research_clipboard_project as cp')
            ->leftJoin('information_object_i18n as i', function ($join) {
                $join->on('cp.object_id', '=', 'i.id')->where('i.culture', '=', 'en');
            })
            ->leftJoin('slug', function ($join) {
                $join->on('cp.object_id', '=', 'slug.object_id');
            })
            ->where('cp.project_id', $projectId)
            ->select('cp.*', 'i.title as object_title', 'slug.slug as object_slug')
            ->orderByDesc('cp.is_pinned')
            ->orderByDesc('cp.created_at')
            ->get()->toArray();
    }

    /**
     * Toggle pin status of a clipboard item.
     */
    public function toggleClipboardPin(int $itemId, int $researcherId): bool
    {
        $item = DB::table('research_clipboard_project')
            ->where('id', $itemId)
            ->where('researcher_id', $researcherId)
            ->first();
        if (!$item) return false;

        return DB::table('research_clipboard_project')
            ->where('id', $itemId)
            ->update(['is_pinned' => $item->is_pinned ? 0 : 1]) >= 0;
    }

    /**
     * Remove a clipboard item from a project.
     */
    public function removeClipboardItem(int $itemId, int $researcherId): bool
    {
        return DB::table('research_clipboard_project')
            ->where('id', $itemId)
            ->where('researcher_id', $researcherId)
            ->delete() > 0;
    }

    /**
     * Update notes on a clipboard item.
     */
    public function updateClipboardNotes(int $itemId, int $researcherId, ?string $notes): bool
    {
        return DB::table('research_clipboard_project')
            ->where('id', $itemId)
            ->where('researcher_id', $researcherId)
            ->update(['notes' => $notes]) >= 0;
    }
}
