<?php

/**
 * AclService - Service for Heratio
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

namespace AhgCore\Services;

use AhgCore\Constants\TermId;
use AhgCore\Models\AclGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ACL Service - Access Control List enforcement.
 *
 * Migrated from AtomExtensions\Services\AclService.
 * Replaces sfContext user detection with Laravel Auth.
 */
class AclService
{
    public const GRANT = 1;

    public const DENY = 0;

    public const INHERIT = -1;

    public static array $ACTIONS = [
        'read' => 'Read',
        'create' => 'Create',
        'update' => 'Update',
        'delete' => 'Delete',
        'translate' => 'Translate',
        'publish' => 'Publish',
        'viewDraft' => 'View Draft',
        'readMaster' => 'Read Master',
        'readReference' => 'Read Reference',
        'readThumbnail' => 'Read Thumbnail',
        'createTerm' => 'Create Term',
        'list' => 'List',
        'import' => 'Import',   // heratio#1535: data migration, ingest
        'export' => 'Export',   // heratio#1535: bulk export screens
    ];

    private static ?object $user = null;

    /** Cached presence of actor.embargo_until - schema drift must not 500 actor pages. */
    private static ?bool $actorEmbargoCol = null;

    private static ?array $userGroups = null;

    public static function setUser(?object $user): void
    {
        self::$user = $user;
        self::$userGroups = null;
    }

    /** Clear the per-request user/group cache (tests: prevents actingAs state leaking across cases). */
    public static function forgetUser(): void
    {
        self::$user = null;
        self::$userGroups = null;
        self::$actorEmbargoCol = null;
        self::$repoRules = [];
        self::$repoSlugOf = [];
    }

    // ---------------------------------------------------------------------
    //  Per-repository permissions (heratio#1776)
    //
    //  A rule limited to one repository is an acl_permission row whose
    //  constants name it: {"repository": "<slug>"} as the ACL editor writes it,
    //  or AtoM's serialized ['repository' => slug] / ['repositoryId' => id]
    //  with the conditional '%p[repository] == %k[repository]'. Precedence
    //  follows AtoM: a rule on the record itself beats a repository rule,
    //  which beats a global one; at one level deny beats grant. Such rows used
    //  to count as GLOBAL rules because nothing read constants.
    // ---------------------------------------------------------------------

    /** @var array<int, array<int, array{action: ?string, grant: bool, repo: string}>> */
    private static array $repoRules = [];

    /** @var array<int, ?string> */
    private static array $repoSlugOf = [];

    /** Repository slug named by a permission row's constants, or null. */
    public static function repositoryConstant(?string $constants): ?string
    {
        if ($constants === null || $constants === '' || ! str_contains($constants, 'repository')) {
            return null;
        }
        $c = json_decode($constants, true);
        if (! is_array($c)) {
            $c = @unserialize($constants, ['allowed_classes' => false]);
        }
        if (! is_array($c)) {
            return null;
        }
        if (! empty($c['repository']) && is_string($c['repository'])) {
            return $c['repository'];
        }
        if (! empty($c['repositoryId'])) {
            return DB::table('slug')->where('object_id', (int) $c['repositoryId'])->value('slug');
        }

        return null;
    }

    /** The user's (and their groups') repository rules. Empty for most users. */
    public static function repositoryRules(?int $userId): array
    {
        if (! $userId) {
            return [];
        }
        if (! array_key_exists($userId, self::$repoRules)) {
            $groups = self::getUserGroupIds($userId);
            self::$repoRules[$userId] = Cache::remember("acl_repo_rules_{$userId}", 300, function () use ($userId, $groups) {
                $out = [];
                $rows = DB::table('acl_permission')
                    ->where(fn ($q) => $q->where('user_id', $userId)->orWhereIn('group_id', $groups))
                    ->where('constants', 'like', '%repository%')
                    ->get(['action', 'grant_deny', 'constants']);
                foreach ($rows as $r) {
                    if (($slug = self::repositoryConstant($r->constants)) !== null) {
                        $out[] = ['action' => $r->action, 'grant' => (int) $r->grant_deny === self::GRANT, 'repo' => $slug];
                    }
                }

                return $out;
            });
        }

        return self::$repoRules[$userId];
    }

    /**
     * Slug of the repository a record belongs to: its own repository_id, else
     * the nearest ancestor's (one lft/rgt query, memoised per request). A
     * repository resolves to itself, a digital object to its description's.
     */
    public static function repositorySlugOf(int $objectId): ?string
    {
        if (array_key_exists($objectId, self::$repoSlugOf)) {
            return self::$repoSlugOf[$objectId];
        }
        $slug = DB::table('information_object as io')
            ->join('information_object as a', function ($j) {
                $j->on('a.lft', '<=', 'io.lft')->on('a.rgt', '>=', 'io.rgt');
            })
            ->join('slug as s', 's.object_id', '=', 'a.repository_id')
            ->where('io.id', $objectId)
            ->whereNotNull('a.repository_id')
            ->orderByDesc('a.lft')
            ->value('s.slug');
        if ($slug === null) {
            if (DB::table('repository')->where('id', $objectId)->exists()) {
                $slug = DB::table('slug')->where('object_id', $objectId)->value('slug');
            } elseif ($ioId = DB::table('digital_object')->where('id', $objectId)->value('object_id')) {
                $slug = $ioId !== $objectId ? self::repositorySlugOf((int) $ioId) : null;
            }
        }

        return self::$repoSlugOf[$objectId] = $slug;
    }

    /**
     * Decision from the rules more specific than a global one, or null when
     * none applies: a rule on the record itself, then a repository rule. Only
     * consulted for users who hold repository rules, so everyone else keeps
     * exactly the old behaviour and pays one cached lookup.
     */
    public static function scopedDecision(?int $userId, string $action, int $objectId): ?bool
    {
        $rules = self::repositoryRules($userId);
        if ($rules === []) {
            return null;
        }
        $groups = self::getUserGroupIds($userId);
        $own = DB::table('acl_permission')
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhereIn('group_id', $groups))
            ->where('object_id', $objectId)
            ->where(fn ($q) => $q->where('action', $action)->orWhereNull('action'))
            ->pluck('grant_deny');
        if ($own->isNotEmpty()) {
            return ! $own->contains(self::DENY);
        }
        $slug = self::repositorySlugOf($objectId);
        if ($slug === null) {
            return null;
        }
        $hit = array_filter($rules, fn ($r) => $r['repo'] === $slug && ($r['action'] === null || $r['action'] === $action));
        if ($hit === []) {
            return null;
        }

        return ! in_array(false, array_column($hit, 'grant'), true);
    }

    /**
     * A repository-scoped account: write rights only through repository rules,
     * no global create/update and no admin or editor group. Kept out of the
     * admin area and the API key manager.
     */
    public static function isRepositoryScoped(?int $userId): bool
    {
        if (! $userId || ! in_array(true, array_column(self::repositoryRules($userId), 'grant'), true)) {
            return false;
        }
        $groups = self::getUserGroupIds($userId);
        if (array_intersect([AclGroup::ADMINISTRATOR_ID, AclGroup::EDITOR_ID], $groups)) {
            return false;
        }

        return ! self::hasPermission($userId, 'create') && ! self::hasPermission($userId, 'update');
    }

    /** Global rows only: repository-keyed rows are evaluated per record above. */
    private static function notRepositoryScoped($q): void
    {
        $q->where(fn ($w) => $w->whereNull('constants')->orWhere('constants', 'not like', '%repository%'));
    }

    public static function getUser(): ?object
    {
        if (! self::$user) {
            self::$user = Auth::user();
        }

        return self::$user;
    }

    public static function check(?object $resource, $action, ?object $user = null): bool
    {
        $user = $user ?? self::getUser();

        if (! $user) {
            return false;
        }

        $groups = self::getUserGroups($user->id ?? null);

        // Administrator has all permissions
        if (in_array(AclGroup::ADMINISTRATOR_ID, $groups)) {
            return true;
        }

        // Handle array of actions
        if (is_array($action)) {
            foreach ($action as $a) {
                if (self::checkSingleAction($resource, $a, $user, $groups)) {
                    return true;
                }
            }

            return false;
        }

        return self::checkSingleAction($resource, $action, $user, $groups);
    }

    private static function checkSingleAction(?object $resource, string $action, object $user, array $groups): bool
    {
        // Record and repository rules first (heratio#1776), so a repository
        // deny also holds for an editor.
        if ($resource && ! empty($resource->id) && ($scoped = self::scopedDecision($user->id ?? null, $action, (int) $resource->id)) !== null) {
            return $scoped;
        }

        // Editors can do most things
        if (in_array(AclGroup::EDITOR_ID, $groups)) {
            $editorActions = ['create', 'read', 'update', 'delete', 'translate', 'publish', 'createTerm', 'list', 'readMaster', 'readReference', 'readThumbnail'];
            if (in_array($action, $editorActions)) {
                return true;
            }
        }

        // Contributors can create and update
        if (in_array(AclGroup::CONTRIBUTOR_ID, $groups)) {
            $contributorActions = ['create', 'read', 'update'];
            if (in_array($action, $contributorActions)) {
                return true;
            }
        }

        // Translators can translate
        if (in_array(AclGroup::TRANSLATOR_ID, $groups)) {
            if ($action === 'translate') {
                return true;
            }
        }

        // Check object-specific permissions
        if ($resource) {
            $perm = DB::table('acl_permission')
                ->where(function ($q) use ($user, $groups) {
                    $q->where('user_id', $user->id)
                        ->orWhereIn('group_id', $groups);
                })
                ->where('action', $action)
                ->where(function ($q) use ($resource) {
                    $q->whereNull('object_id')
                        ->orWhere('object_id', $resource->id ?? null);
                })
                ->where(fn ($q) => self::notRepositoryScoped($q))
                ->orderByRaw('object_id IS NULL')
                ->first();

            if ($perm) {
                return $perm->grant_deny == self::GRANT;
            }
        }

        return false;
    }

    public static function getUserGroups(?int $userId): array
    {
        if (! $userId) {
            return [];
        }

        if (self::$userGroups !== null && self::$user && (self::$user->id ?? null) === $userId) {
            return self::$userGroups;
        }

        self::$userGroups = DB::table('acl_user_group')
            ->where('user_id', $userId)
            ->pluck('group_id')
            ->toArray();

        return self::$userGroups;
    }

    public static function hasGroup(int $groupId, ?object $user = null): bool
    {
        $user = $user ?? self::getUser();
        if (! $user) {
            return false;
        }

        return in_array($groupId, self::getUserGroups($user->id ?? null));
    }

    public static function isAdministrator(?object $user = null): bool
    {
        return self::hasGroup(AclGroup::ADMINISTRATOR_ID, $user);
    }

    public static function isEditor(?object $user = null): bool
    {
        return self::hasGroup(AclGroup::EDITOR_ID, $user);
    }

    public static function isContributor(?object $user = null): bool
    {
        return self::hasGroup(AclGroup::CONTRIBUTOR_ID, $user);
    }

    public static function isTranslator(?object $user = null): bool
    {
        return self::hasGroup(AclGroup::TRANSLATOR_ID, $user);
    }

    /**
     * Filter a Laravel Query Builder to only return published records
     * or records the current user can view as drafts.
     *
     * Status type_id 158 = publicationStatusId
     * Status status_id 160 = PUBLICATION_STATUS_PUBLISHED_ID
     */
    public static function addFilterDraftsCriteria($query, string $idColumn = 'i.id'): mixed
    {
        $user = self::getUser();

        // No user → only show published
        if (! $user) {
            $query->whereExists(function ($sub) use ($idColumn) {
                $sub->select(DB::raw(1))
                    ->from('status')
                    ->whereColumn('status.object_id', $idColumn)
                    ->where('status.type_id', TermId::STATUS_TYPE_PUBLICATION)
                    ->where('status.status_id', 160);
            });

            return $query;
        }

        $groups = self::getUserGroups($user->id ?? null);

        // Administrators and editors can see all drafts
        if (in_array(AclGroup::ADMINISTRATOR_ID, $groups) || in_array(AclGroup::EDITOR_ID, $groups)) {
            return $query;
        }

        // Contributors can see their own drafts + all published
        $query->where(function ($q) use ($user, $idColumn) {
            $q->whereExists(function ($sub) use ($idColumn) {
                $sub->select(DB::raw(1))
                    ->from('status')
                    ->whereColumn('status.object_id', $idColumn)
                    ->where('status.type_id', TermId::STATUS_TYPE_PUBLICATION)
                    ->where('status.status_id', 160);
            });
            if ($user->id ?? null) {
                $q->orWhereExists(function ($sub) use ($user, $idColumn) {
                    $sub->select(DB::raw(1))
                        ->from('object')
                        ->whereColumn('object.id', $idColumn)
                        ->where('object.created_by', $user->id);
                });
            }
        });

        return $query;
    }

    /**
     * Part B - authority-record visibility. Restrict an actor query so guests
     * (and non-editor users) see only records that are BOTH published
     * (status type 158 / status_id 160) AND not currently embargoed
     * (embargo_until NULL or in the past). Editors/administrators see all.
     *
     * $idColumn is the actor id column of the outer query (e.g. 'actor.id' or
     * 'a.id'); the embargo column is derived from it so the filter is
     * alias-agnostic and needs no extra join.
     */
    public static function addActorVisibilityCriteria($query, string $idColumn = 'actor.id'): mixed
    {
        $user = self::getUser();
        if ($user) {
            $groups = self::getUserGroups($user->id ?? null);
            if (in_array(AclGroup::ADMINISTRATOR_ID, $groups) || in_array(AclGroup::EDITOR_ID, $groups)) {
                return $query; // staff see drafts + embargoed
            }
        }

        // Published only.
        $query->whereExists(function ($sub) use ($idColumn) {
            $sub->select(DB::raw(1))
                ->from('status')
                ->whereColumn('status.object_id', $idColumn)
                ->where('status.type_id', TermId::STATUS_TYPE_PUBLICATION)
                ->where('status.status_id', 160);
        });

        // Not currently embargoed.
        if (self::actorHasEmbargoColumn()) {
            $embargoCol = preg_replace('/\.id$/', '.embargo_until', $idColumn);
            $query->where(function ($q) use ($embargoCol) {
                $q->whereNull($embargoCol)->orWhereRaw("$embargoCol <= CURDATE()");
            });
        }

        return $query;
    }

    /**
     * Single-record form of addActorVisibilityCriteria() for show pages: true if
     * the current user may see this authority record.
     */
    public static function isActorVisible(int $actorId): bool
    {
        $user = self::getUser();
        if ($user) {
            $groups = self::getUserGroups($user->id ?? null);
            if (in_array(AclGroup::ADMINISTRATOR_ID, $groups) || in_array(AclGroup::EDITOR_ID, $groups)) {
                return true;
            }
        }

        $published = DB::table('status')
            ->where('object_id', $actorId)
            ->where('type_id', TermId::STATUS_TYPE_PUBLICATION)
            ->where('status_id', 160)
            ->exists();
        if (! $published) {
            return false;
        }

        if (! self::actorHasEmbargoColumn()) {
            return true; // published + no embargo capability = visible
        }
        $embargo = DB::table('actor')->where('id', $actorId)->value('embargo_until');

        return $embargo === null || $embargo <= date('Y-m-d');
    }

    /**
     * Set an authority record's publication status (159 draft / 160 published)
     * and embargo date. Upserts the single type-158 status row.
     */
    public static function setActorPublicationStatus(int $actorId, int $statusId, ?string $embargoUntil = null): void
    {
        $statusId = $statusId === 159 ? 159 : 160;
        \AhgCore\Support\StatusRow::put($actorId, TermId::STATUS_TYPE_PUBLICATION, (int) ($statusId));
        if (self::actorHasEmbargoColumn()) {
            DB::table('actor')->where('id', $actorId)->update([
                'embargo_until' => $embargoUntil ?: null,
            ]);
        }
    }

    /**
     * Whether actor.embargo_until exists (cached). If the column is absent -
     * e.g. the Part-B migration hasn't run or was reverted on an instance - the
     * embargo clauses are skipped so actor pages degrade to publication-only
     * instead of 500-ing on "Unknown column 'embargo_until'".
     */
    private static function actorHasEmbargoColumn(): bool
    {
        if (self::$actorEmbargoCol === null) {
            try {
                self::$actorEmbargoCol = \Illuminate\Support\Facades\Schema::hasColumn('actor', 'embargo_until');
            } catch (\Throwable $e) {
                self::$actorEmbargoCol = false;
            }
        }

        return self::$actorEmbargoCol;
    }

    /**
     * Get all group IDs for a user (with caching).
     * If no user (anonymous), return [98].
     * If authenticated, always include [99] + their actual groups.
     */
    public static function getUserGroupIds(?int $userId): array
    {
        if (! $userId) {
            return [98]; // anonymous
        }

        return Cache::remember("acl_groups_{$userId}", 300, function () use ($userId) {
            $groups = DB::table('acl_user_group')
                ->where('user_id', $userId)
                ->pluck('group_id')
                ->toArray();

            // Always include authenticated group
            if (! in_array(99, $groups)) {
                $groups[] = 99;
            }

            return $groups;
        });
    }

    /**
     * Check if user has permission for an action.
     * Checks user-specific permissions first, then group permissions.
     * Administrator group (100) has all permissions.
     */
    public static function hasPermission(?int $userId, string $action, ?int $objectId = null): bool
    {
        $groupIds = self::getUserGroupIds($userId);

        // Administrators have all permissions
        if (in_array(AclGroup::ADMINISTRATOR_ID, $groupIds)) {
            return true;
        }

        // Record and repository rules first (heratio#1776).
        if ($objectId && ($scoped = self::scopedDecision($userId, $action, $objectId)) !== null) {
            return $scoped;
        }

        // Check user-specific permissions
        if ($userId) {
            $userPerm = DB::table('acl_permission')
                ->where('user_id', $userId)
                ->where(function ($q) use ($action) {
                    $q->where('action', $action)->orWhereNull('action');
                })
                ->where(function ($q) use ($objectId) {
                    $q->where('object_id', $objectId)->orWhereNull('object_id');
                })
                ->where(fn ($q) => self::notRepositoryScoped($q))
                ->orderByDesc('grant_deny')
                ->first();

            if ($userPerm) {
                return (bool) $userPerm->grant_deny;
            }
        }

        // Check group permissions
        $groupPerm = DB::table('acl_permission')
            ->whereIn('group_id', $groupIds)
            ->whereNull('user_id')
            ->where(function ($q) use ($action) {
                $q->where('action', $action)->orWhereNull('action');
            })
            ->where(function ($q) use ($objectId) {
                $q->where('object_id', $objectId)->orWhereNull('object_id');
            })
            ->where(fn ($q) => self::notRepositoryScoped($q))
            ->orderByDesc('grant_deny')
            ->first();

        return $groupPerm ? (bool) $groupPerm->grant_deny : false;
    }

    /**
     * Check if user can access admin area (administrator or editor).
     */
    public static function canAdmin(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        $groupIds = self::getUserGroupIds($userId);

        // An editor given repository rules stays an editor; the repository
        // rules then narrow it per record. Scoped accounts are never editors.
        return ! empty(array_intersect([AclGroup::ADMINISTRATOR_ID, AclGroup::EDITOR_ID], $groupIds));
    }

    public static function getRepositoryAccess(string $action): array
    {
        $user = self::getUser();
        if (! $user) {
            return [['access' => self::DENY]];
        }
        if (self::isAdministrator()) {
            return [['access' => self::GRANT]];
        }

        $groups = self::getUserGroups($user->id);

        $permissions = DB::table('acl_permission')
            ->whereIn('group_id', $groups)
            ->where('action', $action)
            ->whereNotNull('object_id')
            ->select('object_id', 'grant_deny')
            ->get();

        $result = [];
        foreach ($permissions as $perm) {
            $result[] = ['repository_id' => $perm->object_id, 'access' => $perm->grant_deny];
        }

        $defaultAccess = in_array(AclGroup::EDITOR_ID, $groups) ? self::GRANT : self::DENY;
        $result[] = ['access' => $defaultAccess];

        return $result;
    }
}
