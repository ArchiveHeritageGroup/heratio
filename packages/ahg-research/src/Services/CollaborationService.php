<?php

/**
 * CollaborationService - Service for Heratio
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



namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * CollaborationService - Workspace and Collaboration Management
 *
 * Handles private workspaces, member management, resources, and discussions.
 * Workspaces are private by default to protect researcher data.
 *
 * Migrated from AtoM: ahgResearchPlugin/lib/Services/CollaborationService.php
 */
class CollaborationService
{
    // =========================================================================
    // WORKSPACE MANAGEMENT
    // =========================================================================

    /**
     * Create a new workspace.
     *
     * @param int $ownerId The researcher who owns the workspace
     * @param array $data Workspace data
     * @return int The workspace ID
     */
    public function createWorkspace(int $ownerId, array $data): int
    {
        $workspaceId = DB::table('research_workspace')->insertGetId([
            'owner_id' => $ownerId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'] ?? 'private', // Private by default
            'share_token' => bin2hex(random_bytes(32)),
            'settings' => isset($data['settings']) ? json_encode($data['settings']) : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Add owner as member with owner role
        DB::table('research_workspace_member')->insert([
            'workspace_id' => $workspaceId,
            'researcher_id' => $ownerId,
            'role' => 'owner',
            'invited_by' => $ownerId,
            'invited_at' => date('Y-m-d H:i:s'),
            'accepted_at' => date('Y-m-d H:i:s'),
            'status' => 'accepted',
        ]);

        return $workspaceId;
    }

    /**
     * Get a workspace by ID.
     *
     * @param int $workspaceId The workspace ID
     * @return object|null The workspace or null
     */
    public function getWorkspace(int $workspaceId): ?object
    {
        $workspace = DB::table('research_workspace as w')
            ->leftJoin('research_researcher as r', 'w.owner_id', '=', 'r.id')
            ->where('w.id', $workspaceId)
            ->select(
                'w.*',
                'r.first_name as owner_first_name',
                'r.last_name as owner_last_name',
                'r.email as owner_email'
            )
            ->first();

        if ($workspace) {
            $workspace->members = $this->getMembers($workspaceId);
            $workspace->member_count = count(array_filter($workspace->members, fn($m) => $m->status === 'accepted'));
            $workspace->resource_count = DB::table('research_workspace_resource')
                ->where('workspace_id', $workspaceId)
                ->count();
            $workspace->discussion_count = DB::table('research_discussion')
                ->where('workspace_id', $workspaceId)
                ->whereNull('parent_id')
                ->count();
        }

        return $workspace;
    }

    /**
     * Get workspaces for a researcher (owned or member of).
     *
     * @param int $researcherId The researcher ID
     * @param array $filters Optional filters
     * @return array List of workspaces
     */
    public function getWorkspaces(int $researcherId, array $filters = []): array
    {
        $query = DB::table('research_workspace as w')
            ->join('research_workspace_member as wm', function ($join) use ($researcherId) {
                $join->on('w.id', '=', 'wm.workspace_id')
                    ->where('wm.researcher_id', '=', $researcherId)
                    ->where('wm.status', '=', 'accepted');
            })
            ->leftJoin('research_researcher as r', 'w.owner_id', '=', 'r.id')
            ->select(
                'w.*',
                'wm.role as my_role',
                'r.first_name as owner_first_name',
                'r.last_name as owner_last_name'
            );

        if (!empty($filters['visibility'])) {
            $query->where('w.visibility', $filters['visibility']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('w.name', 'like', $search)
                    ->orWhere('w.description', 'like', $search);
            });
        }

        if (isset($filters['owned_only']) && $filters['owned_only']) {
            $query->where('w.owner_id', $researcherId);
        }

        $workspaces = $query->orderBy('w.updated_at', 'desc')->get()->toArray();

        foreach ($workspaces as &$workspace) {
            $workspace->member_count = DB::table('research_workspace_member')
                ->where('workspace_id', $workspace->id)
                ->where('status', 'accepted')
                ->count();
            $workspace->resource_count = DB::table('research_workspace_resource')
                ->where('workspace_id', $workspace->id)
                ->count();
        }

        return $workspaces;
    }

    /**
     * Update a workspace.
     *
     * @param int $workspaceId The workspace ID
     * @param array $data Fields to update
     * @return bool Success status
     */
    public function updateWorkspace(int $workspaceId, array $data): bool
    {
        $allowed = ['name', 'description', 'visibility', 'settings'];
        $updateData = array_intersect_key($data, array_flip($allowed));
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        if (isset($updateData['settings']) && is_array($updateData['settings'])) {
            $updateData['settings'] = json_encode($updateData['settings']);
        }

        return DB::table('research_workspace')
            ->where('id', $workspaceId)
            ->update($updateData) >= 0;
    }

    /**
     * Delete a workspace.
     *
     * @param int $workspaceId The workspace ID
     * @return bool Success status
     */
    public function deleteWorkspace(int $workspaceId): bool
    {
        // Delete related records
        DB::table('research_workspace_member')->where('workspace_id', $workspaceId)->delete();
        DB::table('research_workspace_resource')->where('workspace_id', $workspaceId)->delete();
        DB::table('research_discussion')->where('workspace_id', $workspaceId)->delete();

        return DB::table('research_workspace')->where('id', $workspaceId)->delete() > 0;
    }

    /**
     * Check if a researcher can access a workspace.
     *
     * @param int $workspaceId The workspace ID
     * @param int $researcherId The researcher ID
     * @param string|null $requiredRole Minimum role required
     * @return bool True if access is allowed
     */
    public function canAccess(int $workspaceId, int $researcherId, ?string $requiredRole = null): bool
    {
        $workspace = DB::table('research_workspace')->where('id', $workspaceId)->first();

        if (!$workspace) {
            return false;
        }

        // Public workspaces can be viewed by anyone
        if ($workspace->visibility === 'public' && ($requiredRole === null || $requiredRole === 'viewer')) {
            return true;
        }

        // Check membership
        $member = DB::table('research_workspace_member')
            ->where('workspace_id', $workspaceId)
            ->where('researcher_id', $researcherId)
            ->where('status', 'accepted')
            ->first();

        if (!$member) {
            return false;
        }

        if ($requiredRole === null) {
            return true;
        }

        $roleHierarchy = ['owner' => 4, 'admin' => 3, 'editor' => 2, 'viewer' => 1];
        $userLevel = $roleHierarchy[$member->role] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    // =========================================================================
    // MEMBER MANAGEMENT
    // =========================================================================

    /**
     * Add a member to a workspace.
     *
     * @param int $workspaceId The workspace ID
     * @param int $researcherId The researcher to add
     * @param string $role The role to assign
     * @param int $invitedBy The researcher sending the invitation
     * @return array Result with success status
     */
    public function addMember(int $workspaceId, int $researcherId, string $role, int $invitedBy): array
    {
        // Check if already a member
        $existing = DB::table('research_workspace_member')
            ->where('workspace_id', $workspaceId)
            ->where('researcher_id', $researcherId)
            ->first();

        if ($existing) {
            if ($existing->status === 'accepted') {
                return ['success' => false, 'error' => 'Researcher is already a member'];
            }
            if ($existing->status === 'pending') {
                return ['success' => false, 'error' => 'Invitation already pending'];
            }
            // Re-invite if previously declined or removed
            DB::table('research_workspace_member')
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

        DB::table('research_workspace_member')->insert([
            'workspace_id' => $workspaceId,
            'researcher_id' => $researcherId,
            'role' => $role,
            'invited_by' => $invitedBy,
            'invited_at' => date('Y-m-d H:i:s'),
            'status' => 'pending',
        ]);

        return ['success' => true, 'message' => 'Invitation sent'];
    }

    /**
     * Accept a workspace invitation.
     *
     * @param int $workspaceId The workspace ID
     * @param int $researcherId The researcher accepting
     * @return bool Success status
     */
    public function acceptMembership(int $workspaceId, int $researcherId): bool
    {
        return DB::table('research_workspace_member')
            ->where('workspace_id', $workspaceId)
            ->where('researcher_id', $researcherId)
            ->where('status', 'pending')
            ->update([
                'accepted_at' => date('Y-m-d H:i:s'),
                'status' => 'accepted',
            ]) > 0;
    }

    /**
     * Decline a workspace invitation.
     *
     * @param int $workspaceId The workspace ID
     * @param int $researcherId The researcher declining
     * @return bool Success status
     */
    public function declineMembership(int $workspaceId, int $researcherId): bool
    {
        return DB::table('research_workspace_member')
            ->where('workspace_id', $workspaceId)
            ->where('researcher_id', $researcherId)
            ->where('status', 'pending')
            ->update(['status' => 'declined']) > 0;
    }

    /**
     * Remove a member from a workspace.
     *
     * @param int $workspaceId The workspace ID
     * @param int $researcherId The researcher to remove
     * @return bool Success status
     */
    public function removeMember(int $workspaceId, int $researcherId): bool
    {
        // Cannot remove the owner
        $workspace = DB::table('research_workspace')->where('id', $workspaceId)->first();
        if ($workspace && $workspace->owner_id === $researcherId) {
            return false;
        }

        return DB::table('research_workspace_member')
            ->where('workspace_id', $workspaceId)
            ->where('researcher_id', $researcherId)
            ->update(['status' => 'removed']) > 0;
    }

    /**
     * Update a member's role.
     *
     * @param int $workspaceId The workspace ID
     * @param int $researcherId The researcher ID
     * @param string $newRole The new role
     * @return bool Success status
     */
    public function updateMemberRole(int $workspaceId, int $researcherId, string $newRole): bool
    {
        // Cannot change owner's role
        $member = DB::table('research_workspace_member')
            ->where('workspace_id', $workspaceId)
            ->where('researcher_id', $researcherId)
            ->first();

        if (!$member || $member->role === 'owner') {
            return false;
        }

        return DB::table('research_workspace_member')
            ->where('workspace_id', $workspaceId)
            ->where('researcher_id', $researcherId)
            ->update(['role' => $newRole]) > 0;
    }

    /**
     * Get members of a workspace.
     *
     * @param int $workspaceId The workspace ID
     * @param bool $acceptedOnly Only return accepted members
     * @return array List of members
     */
    public function getMembers(int $workspaceId, bool $acceptedOnly = false): array
    {
        $query = DB::table('research_workspace_member as wm')
            ->join('research_researcher as r', 'wm.researcher_id', '=', 'r.id')
            ->where('wm.workspace_id', $workspaceId)
            ->select(
                'wm.*',
                'r.first_name',
                'r.last_name',
                'r.email',
                'r.institution',
                'r.orcid_id'
            );

        if ($acceptedOnly) {
            $query->where('wm.status', 'accepted');
        }

        return $query->orderByRaw("FIELD(wm.role, 'owner', 'admin', 'editor', 'viewer')")
            ->get()
            ->toArray();
    }

    /**
     * Get pending workspace invitations for a researcher.
     *
     * @param int $researcherId The researcher ID
     * @return array List of pending invitations
     */
    public function getPendingInvitations(int $researcherId): array
    {
        return DB::table('research_workspace_member as wm')
            ->join('research_workspace as w', 'wm.workspace_id', '=', 'w.id')
            ->join('research_researcher as inviter', 'wm.invited_by', '=', 'inviter.id')
            ->where('wm.researcher_id', $researcherId)
            ->where('wm.status', 'pending')
            ->select(
                'wm.*',
                'w.name as workspace_name',
                'w.description as workspace_description',
                'inviter.first_name as inviter_first_name',
                'inviter.last_name as inviter_last_name'
            )
            ->orderBy('wm.invited_at', 'desc')
            ->get()
            ->toArray();
    }

    // =========================================================================
    // RESOURCES
    // =========================================================================

    /**
     * Add a resource to a workspace.
     *
     * @param int $workspaceId The workspace ID
     * @param array $data Resource data
     * @param int $addedBy Researcher ID who added it
     * @return int The resource ID
     */
    public function addResource(int $workspaceId, array $data, int $addedBy): int
    {
        $maxOrder = DB::table('research_workspace_resource')
            ->where('workspace_id', $workspaceId)
            ->max('sort_order') ?? 0;

        return DB::table('research_workspace_resource')->insertGetId([
            'workspace_id' => $workspaceId,
            'resource_type' => $data['resource_type'],
            'resource_id' => $data['resource_id'] ?? null,
            'external_url' => $data['external_url'] ?? null,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'added_by' => $addedBy,
            'sort_order' => $maxOrder + 1,
            'added_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Remove a resource from a workspace.
     *
     * @param int $resourceId The resource ID
     * @return bool Success status
     */
    public function removeResource(int $resourceId): bool
    {
        return DB::table('research_workspace_resource')
            ->where('id', $resourceId)
            ->delete() > 0;
    }

    /**
     * Get resources for a workspace.
     *
     * @param int $workspaceId The workspace ID
     * @param string|null $type Filter by resource type
     * @return array List of resources
     */
    public function getResources(int $workspaceId, ?string $type = null): array
    {
        $query = DB::table('research_workspace_resource as wr')
            ->leftJoin('research_researcher as r', 'wr.added_by', '=', 'r.id')
            ->where('wr.workspace_id', $workspaceId)
            ->select(
                'wr.*',
                'r.first_name as added_by_first_name',
                'r.last_name as added_by_last_name'
            );

        if ($type) {
            $query->where('wr.resource_type', $type);
        }

        $resources = $query->orderBy('wr.sort_order')->get()->toArray();

        // Enrich with linked resource data
        foreach ($resources as &$resource) {
            if ($resource->resource_id) {
                switch ($resource->resource_type) {
                    case 'collection':
                        $resource->linked_resource = DB::table('research_collection')
                            ->where('id', $resource->resource_id)
                            ->first();
                        break;
                    case 'project':
                        $resource->linked_resource = DB::table('research_project')
                            ->where('id', $resource->resource_id)
                            ->first();
                        break;
                    case 'bibliography':
                        $resource->linked_resource = DB::table('research_bibliography')
                            ->where('id', $resource->resource_id)
                            ->first();
                        break;
                    case 'saved_search':
                        $resource->linked_resource = DB::table('research_saved_search')
                            ->where('id', $resource->resource_id)
                            ->first();
                        break;
                }
            }
        }

        return $resources;
    }

    // =========================================================================
    // DISCUSSIONS
    // =========================================================================

    /**
     * Create a new discussion.
     *
     * @param array $data Discussion data
     * @return int The discussion ID
     */
    public function createDiscussion(array $data): int
    {
        return DB::table('research_discussion')->insertGetId([
            'workspace_id' => $data['workspace_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'researcher_id' => $data['researcher_id'],
            'subject' => $data['subject'] ?? null,
            'content' => $data['content'],
            'is_pinned' => $data['is_pinned'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get discussions for a workspace.
     *
     * @param int $workspaceId The workspace ID
     * @param bool $topLevelOnly Only return top-level discussions
     * @return array List of discussions
     */
    public function getDiscussions(int $workspaceId, bool $topLevelOnly = true): array
    {
        $query = DB::table('research_discussion as d')
            ->join('research_researcher as r', 'd.researcher_id', '=', 'r.id')
            ->where('d.workspace_id', $workspaceId)
            ->select(
                'd.*',
                'r.first_name',
                'r.last_name',
                'r.email'
            );

        if ($topLevelOnly) {
            $query->whereNull('d.parent_id');
        }

        $discussions = $query->orderByDesc('d.is_pinned')
            ->orderBy('d.created_at', 'desc')
            ->get()
            ->toArray();

        // Add reply counts for top-level discussions
        if ($topLevelOnly) {
            foreach ($discussions as &$discussion) {
                $discussion->reply_count = DB::table('research_discussion')
                    ->where('parent_id', $discussion->id)
                    ->count();
            }
        }

        return $discussions;
    }

    /**
     * Get discussions for a project.
     *
     * @param int $projectId The project ID
     * @param bool $topLevelOnly Only return top-level discussions
     * @return array List of discussions
     */
    public function getProjectDiscussions(int $projectId, bool $topLevelOnly = true): array
    {
        $query = DB::table('research_discussion as d')
            ->join('research_researcher as r', 'd.researcher_id', '=', 'r.id')
            ->where('d.project_id', $projectId)
            ->select(
                'd.*',
                'r.first_name',
                'r.last_name'
            );

        if ($topLevelOnly) {
            $query->whereNull('d.parent_id');
        }

        $discussions = $query->orderByDesc('d.is_pinned')
            ->orderBy('d.created_at', 'desc')
            ->get()
            ->toArray();

        if ($topLevelOnly) {
            foreach ($discussions as &$discussion) {
                $discussion->reply_count = DB::table('research_discussion')
                    ->where('parent_id', $discussion->id)
                    ->count();
            }
        }

        return $discussions;
    }

    /**
     * Get a discussion with its replies.
     *
     * @param int $discussionId The discussion ID
     * @return object|null The discussion with replies
     */
    public function getDiscussion(int $discussionId): ?object
    {
        $discussion = DB::table('research_discussion as d')
            ->join('research_researcher as r', 'd.researcher_id', '=', 'r.id')
            ->where('d.id', $discussionId)
            ->select(
                'd.*',
                'r.first_name',
                'r.last_name',
                'r.email'
            )
            ->first();

        if ($discussion) {
            $discussion->replies = DB::table('research_discussion as d')
                ->join('research_researcher as r', 'd.researcher_id', '=', 'r.id')
                ->where('d.parent_id', $discussionId)
                ->select(
                    'd.*',
                    'r.first_name',
                    'r.last_name'
                )
                ->orderBy('d.created_at')
                ->get()
                ->toArray();
        }

        return $discussion;
    }

    /**
     * Add a reply to a discussion.
     *
     * @param int $parentId The parent discussion ID
     * @param int $researcherId The researcher posting
     * @param string $content The reply content
     * @return int The reply ID
     */
    public function addReply(int $parentId, int $researcherId, string $content): int
    {
        $parent = DB::table('research_discussion')
            ->where('id', $parentId)
            ->first();

        if (!$parent) {
            throw new RuntimeException('Parent discussion not found');
        }

        return DB::table('research_discussion')->insertGetId([
            'workspace_id' => $parent->workspace_id,
            'project_id' => $parent->project_id,
            'parent_id' => $parentId,
            'researcher_id' => $researcherId,
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Delete a discussion.
     *
     * @param int $discussionId The discussion ID
     * @return bool Success status
     */
    public function deleteDiscussion(int $discussionId): bool
    {
        // Delete replies first
        DB::table('research_discussion')->where('parent_id', $discussionId)->delete();

        return DB::table('research_discussion')
            ->where('id', $discussionId)
            ->delete() > 0;
    }

    /**
     * Update a discussion.
     *
     * @param int $discussionId The discussion ID
     * @param array $data Fields to update
     * @return bool Success status
     */
    public function updateDiscussion(int $discussionId, array $data): bool
    {
        $allowed = ['subject', 'content', 'is_pinned', 'is_resolved', 'resolved_by', 'resolved_at'];
        $updateData = array_intersect_key($data, array_flip($allowed));
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        return DB::table('research_discussion')
            ->where('id', $discussionId)
            ->update($updateData) >= 0;
    }

    /**
     * Mark a discussion as resolved.
     *
     * @param int $discussionId The discussion ID
     * @param int $resolvedBy The researcher who resolved it
     * @return bool Success status
     */
    public function resolveDiscussion(int $discussionId, int $resolvedBy): bool
    {
        return DB::table('research_discussion')
            ->where('id', $discussionId)
            ->update([
                'is_resolved' => 1,
                'resolved_by' => $resolvedBy,
                'resolved_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Pin/unpin a discussion.
     *
     * @param int $discussionId The discussion ID
     * @param bool $pinned Whether to pin or unpin
     * @return bool Success status
     */
    public function pinDiscussion(int $discussionId, bool $pinned): bool
    {
        return DB::table('research_discussion')
            ->where('id', $discussionId)
            ->update([
                'is_pinned' => $pinned ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }
}
