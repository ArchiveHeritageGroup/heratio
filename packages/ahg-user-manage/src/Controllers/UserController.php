<?php

namespace AhgUserManage\Controllers;

use AhgUserManage\Services\UserBrowseService;
use AhgUserManage\Services\UserService;
use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected UserService $service;

    public function __construct()
    {
        $this->service = new UserService(app()->getLocale());
    }

    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $browseService = new UserBrowseService($culture);

        $status = $request->get('status', 'active');

        $result = $browseService->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'alphabetic'),
            'subquery' => $request->get('subquery', ''),
            'status' => $status,
        ]);

        $pager = new SimplePager($result);

        return view('ahg-user-manage::browse', [
            'pager' => $pager,
            'currentUserId' => auth()->id(),
            'sortOptions' => [
                'alphabetic' => 'Name',
                'lastUpdated' => 'Date modified',
                'email' => 'Email',
            ],
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $user = $this->service->getBySlug($slug);
        if (!$user) {
            abort(404);
        }

        return view('ahg-user-manage::show', [
            'user' => $user,
            'groups' => collect($user->groups),
        ]);
    }

    public function create()
    {
        return view('ahg-user-manage::edit', [
            'user' => null,
            'assignableGroups' => $this->service->getAssignableGroups(),
            'availableLanguages' => $this->service->getAvailableLanguages(),
        ]);
    }

    public function edit(string $slug)
    {
        $user = $this->service->getBySlug($slug);
        if (!$user) {
            abort(404);
        }

        return view('ahg-user-manage::edit', [
            'user' => $user,
            'assignableGroups' => $this->service->getAssignableGroups(),
            'availableLanguages' => $this->service->getAvailableLanguages(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:user,username',
            'email' => 'required|email|max:255|unique:user,email',
            'password' => 'required|string|min:6',
            'confirm_password' => 'nullable|same:password',
            'authorized_form_of_name' => 'nullable|string|max:1024',
            'contact_telephone' => 'nullable|string|max:255',
            'contact_fax' => 'nullable|string|max:255',
            'contact_street_address' => 'nullable|string|max:1024',
            'contact_city' => 'nullable|string|max:1024',
            'contact_region' => 'nullable|string|max:1024',
            'contact_postal_code' => 'nullable|string|max:255',
            'contact_country_code' => 'nullable|string|max:255',
            'contact_website' => 'nullable|url|max:1024',
            'contact_note' => 'nullable|string',
            'translate' => 'nullable|array',
        ]);

        $data = $request->only([
            'username', 'email', 'password', 'authorized_form_of_name',
            'contact_telephone', 'contact_fax', 'contact_street_address',
            'contact_city', 'contact_region', 'contact_postal_code',
            'contact_country_code', 'contact_website', 'contact_note',
        ]);
        $data['active'] = $request->has('active') ? 1 : 0;
        $data['groups'] = $request->input('groups', []);
        $data['translate'] = $request->input('translate', []);

        $id = $this->service->create($data);

        return redirect()
            ->route('user.show', $this->service->getSlug($id))
            ->with('success', 'User created successfully.');
    }

    public function update(Request $request, string $slug)
    {
        $user = $this->service->getBySlug($slug);
        if (!$user) {
            abort(404);
        }

        $request->validate([
            'username' => 'required|string|max:255|unique:user,username,' . $user->id,
            'email' => 'required|email|max:255|unique:user,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'confirm_password' => 'nullable|same:password',
            'authorized_form_of_name' => 'nullable|string|max:1024',
            'contact_telephone' => 'nullable|string|max:255',
            'contact_fax' => 'nullable|string|max:255',
            'contact_street_address' => 'nullable|string|max:1024',
            'contact_city' => 'nullable|string|max:1024',
            'contact_region' => 'nullable|string|max:1024',
            'contact_postal_code' => 'nullable|string|max:255',
            'contact_country_code' => 'nullable|string|max:255',
            'contact_website' => 'nullable|url|max:1024',
            'contact_note' => 'nullable|string',
            'translate' => 'nullable|array',
        ]);

        $data = $request->only([
            'username', 'email', 'password', 'authorized_form_of_name',
            'contact_telephone', 'contact_fax', 'contact_street_address',
            'contact_city', 'contact_region', 'contact_postal_code',
            'contact_country_code', 'contact_website', 'contact_note',
        ]);
        $data['active'] = $request->has('active') ? 1 : 0;
        $data['groups'] = $request->input('groups', []);
        $data['translate'] = $request->input('translate', []);

        $this->service->update($user->id, $data);

        return redirect()
            ->route('user.show', $slug)
            ->with('success', 'User updated successfully.');
    }

    public function confirmDelete(string $slug)
    {
        $user = $this->service->getBySlug($slug);
        if (!$user) {
            abort(404);
        }

        return view('ahg-user-manage::delete', ['user' => $user]);
    }

    public function destroy(string $slug)
    {
        $user = $this->service->getBySlug($slug);
        if (!$user) {
            abort(404);
        }

        $this->service->delete($user->id);

        return redirect()
            ->route('user.browse')
            ->with('success', 'User deleted successfully.');
    }

    public function registrationPending(Request $request) { return view('ahg-user-manage::registration-pending', ['rows' => collect()]); }

    public function register(Request $request) { return view('ahg-user-manage::registration-register'); }

    public function verify(string $token) { return view('ahg-user-manage::registration-verify'); }

    public function userView(string $slug) { return $this->show(request(), $slug); }

    // ── ACL Methods ──────────────────────────────────────────────────

    /**
     * Build ACL data for a user for a given object class.
     */
    private function buildAclData(string $slug, string $className): array
    {
        $user = $this->service->getBySlug($slug);
        if (!$user) {
            abort(404);
        }

        $culture = app()->getLocale();

        // Get user's groups
        $userGroups = \Illuminate\Support\Facades\DB::table('acl_user_group')
            ->where('user_id', $user->id)
            ->pluck('group_id')
            ->toArray();

        if (empty($userGroups)) {
            $userGroups = [99]; // Authenticated group
        }

        $tableCols = count($userGroups) + 3;

        // Group names
        $groupNames = [];
        $groups = \Illuminate\Support\Facades\DB::table('acl_group_i18n')
            ->whereIn('id', $userGroups)
            ->where('culture', $culture)
            ->get();
        foreach ($groups as $g) {
            $groupNames[$g->id] = $g->name;
        }

        // Get permissions for this user and class
        $permissions = \Illuminate\Support\Facades\DB::table('acl_permission')
            ->leftJoin('object', 'acl_permission.object_id', '=', 'object.id')
            ->where(function ($q) use ($user, $userGroups) {
                $q->where('acl_permission.user_id', $user->id)
                  ->orWhereIn('acl_permission.group_id', $userGroups);
            })
            ->where(function ($q) use ($className) {
                $q->where('object.class_name', $className)
                  ->orWhereNull('acl_permission.object_id');
            })
            ->orderBy('acl_permission.object_id')
            ->orderBy('acl_permission.user_id')
            ->orderBy('acl_permission.group_id')
            ->select('acl_permission.*', 'object.class_name')
            ->get();

        // Build ACL matrix
        $acl = [];
        $objectIds = [];
        $allUserGroups = array_merge($userGroups, [$user->username]);

        foreach ($permissions as $perm) {
            $objectId = $perm->object_id;
            $groupKey = $perm->group_id ?? $user->username;
            $acl[$objectId][$perm->action][$groupKey] = $perm;
            if ($objectId) {
                $objectIds[] = $objectId;
            }
        }

        // Get object names
        $objectNames = [];
        if (!empty($objectIds)) {
            // Try actor_i18n for actors, information_object_i18n for IOs, etc.
            $nameRows = \Illuminate\Support\Facades\DB::table('actor_i18n')
                ->whereIn('id', $objectIds)
                ->where('culture', $culture)
                ->pluck('authorized_form_of_name', 'id')
                ->toArray();
            $objectNames = array_merge($objectNames, $nameRows);

            $ioNames = \Illuminate\Support\Facades\DB::table('information_object_i18n')
                ->whereIn('id', $objectIds)
                ->where('culture', $culture)
                ->pluck('title', 'id')
                ->toArray();
            $objectNames = array_merge($objectNames, $ioNames);

            $repoNames = \Illuminate\Support\Facades\DB::table('repository_i18n')
                ->whereIn('id', $objectIds)
                ->where('culture', $culture)
                ->pluck('`desc`', 'id')
                ->toArray();
            $objectNames = array_merge($objectNames, $repoNames);

            $termNames = \Illuminate\Support\Facades\DB::table('term_i18n')
                ->whereIn('id', $objectIds)
                ->where('culture', $culture)
                ->pluck('name', 'id')
                ->toArray();
            $objectNames = array_merge($objectNames, $termNames);
        }

        $aclActions = [
            'create' => __('Create'),
            'read' => __('Read'),
            'update' => __('Update'),
            'delete' => __('Delete'),
            'publish' => __('Publish'),
            'translate' => __('Translate'),
        ];

        return compact('user', 'acl', 'userGroups', 'allUserGroups', 'groupNames',
            'objectNames', 'tableCols', 'aclActions') + ['userGroups' => $allUserGroups];
    }

    /**
     * Build edit ACL data (simpler — just user's own permissions for a class).
     */
    private function buildEditAclData(string $slug, string $className): array
    {
        $user = $this->service->getBySlug($slug);
        if (!$user) {
            abort(404);
        }

        $culture = app()->getLocale();

        $permissions = \Illuminate\Support\Facades\DB::table('acl_permission')
            ->leftJoin('object', 'acl_permission.object_id', '=', 'object.id')
            ->where('acl_permission.user_id', $user->id)
            ->where(function ($q) use ($className) {
                $q->where('object.class_name', $className)
                  ->orWhereNull('acl_permission.object_id');
            })
            ->select('acl_permission.*')
            ->get();

        // Add object names
        foreach ($permissions as $perm) {
            $perm->object_name = null;
            if ($perm->object_id) {
                $name = \Illuminate\Support\Facades\DB::table('actor_i18n')
                    ->where('id', $perm->object_id)->where('culture', $culture)
                    ->value('authorized_form_of_name');
                if (!$name) {
                    $name = \Illuminate\Support\Facades\DB::table('information_object_i18n')
                        ->where('id', $perm->object_id)->where('culture', $culture)
                        ->value('title');
                }
                if (!$name) {
                    $name = \Illuminate\Support\Facades\DB::table('term_i18n')
                        ->where('id', $perm->object_id)->where('culture', $culture)
                        ->value('name');
                }
                $perm->object_name = $name;
            }
        }

        return compact('user', 'permissions');
    }

    /**
     * Save ACL permission changes from a form POST.
     */
    private function saveAclPermissions(Request $request, object $user): void
    {
        // Update existing permissions
        if ($request->has('permissions')) {
            foreach ($request->input('permissions') as $id => $value) {
                if ($value === 'inherit') {
                    \Illuminate\Support\Facades\DB::table('acl_permission')->where('id', $id)->delete();
                } else {
                    \Illuminate\Support\Facades\DB::table('acl_permission')
                        ->where('id', $id)
                        ->update(['grant_deny' => ($value === 'grant') ? 1 : 0]);
                }
            }
        }

        // Add new permission
        if ($request->filled('new_action') || $request->filled('new_actor_id') || $request->filled('new_object_id')) {
            $objectId = $request->input('new_actor_id') ?: $request->input('new_object_id');
            $action = $request->input('new_action', '');
            $grantDeny = ($request->input('new_grant_deny', 'grant') === 'grant') ? 1 : 0;

            if ($objectId || $action) {
                \Illuminate\Support\Facades\DB::table('acl_permission')->insert([
                    'user_id' => $user->id,
                    'object_id' => $objectId ?: null,
                    'action' => $action ?: null,
                    'grant_deny' => $grantDeny,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    // ── Actor ACL ──

    public function indexActorAcl(string $slug)
    {
        $data = $this->buildAclData($slug, 'QubitActor');
        $data['actorNames'] = $data['objectNames'];
        return view('ahg-user-manage::index-actor-acl', $data);
    }

    public function editActorAcl(Request $request, string $slug)
    {
        $data = $this->buildEditAclData($slug, 'QubitActor');

        if ($request->isMethod('post')) {
            $this->saveAclPermissions($request, $data['user']);
            return redirect()->route('user.indexActorAcl', ['slug' => $slug])
                ->with('success', __('Actor permissions saved.'));
        }

        // Get actors for dropdown
        $data['actors'] = \Illuminate\Support\Facades\DB::table('actor')
            ->join('actor_i18n', function ($j) {
                $j->on('actor.id', '=', 'actor_i18n.id')
                  ->where('actor_i18n.culture', app()->getLocale());
            })
            ->whereNotNull('actor_i18n.authorized_form_of_name')
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->select('actor.id', 'actor_i18n.authorized_form_of_name')
            ->limit(500)
            ->get();

        return view('ahg-user-manage::edit-actor-acl', $data);
    }

    // ── Information Object ACL ──

    public function indexInformationObjectAcl(string $slug)
    {
        $data = $this->buildAclData($slug, 'QubitInformationObject');
        $data['ioNames'] = $data['objectNames'];
        return view('ahg-user-manage::index-information-object-acl', $data);
    }

    public function editInformationObjectAcl(Request $request, string $slug)
    {
        $data = $this->buildEditAclData($slug, 'QubitInformationObject');

        if ($request->isMethod('post')) {
            $this->saveAclPermissions($request, $data['user']);
            return redirect()->route('user.indexInformationObjectAcl', ['slug' => $slug])
                ->with('success', __('Information object permissions saved.'));
        }

        return view('ahg-user-manage::edit-information-object-acl', $data);
    }

    // ── Repository ACL ──

    public function indexRepositoryAcl(string $slug)
    {
        $data = $this->buildAclData($slug, 'QubitRepository');
        $data['repoNames'] = $data['objectNames'];
        return view('ahg-user-manage::index-repository-acl', $data);
    }

    public function editRepositoryAcl(Request $request, string $slug)
    {
        $data = $this->buildEditAclData($slug, 'QubitRepository');

        if ($request->isMethod('post')) {
            $this->saveAclPermissions($request, $data['user']);
            return redirect()->route('user.indexRepositoryAcl', ['slug' => $slug])
                ->with('success', __('Repository permissions saved.'));
        }

        $data['repositories'] = \Illuminate\Support\Facades\DB::table('repository')
            ->join('actor_i18n', function ($j) {
                $j->on('repository.id', '=', 'actor_i18n.id')
                  ->where('actor_i18n.culture', app()->getLocale());
            })
            ->whereNotNull('actor_i18n.authorized_form_of_name')
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->select('repository.id', 'actor_i18n.authorized_form_of_name')
            ->limit(500)
            ->get();

        return view('ahg-user-manage::edit-repository-acl', $data);
    }

    // ── Term ACL ──

    public function indexTermAcl(string $slug)
    {
        $data = $this->buildAclData($slug, 'QubitTerm');
        $data['termNames'] = $data['objectNames'];
        return view('ahg-user-manage::index-term-acl', $data);
    }

    public function editTermAcl(Request $request, string $slug)
    {
        $data = $this->buildEditAclData($slug, 'QubitTerm');

        if ($request->isMethod('post')) {
            $this->saveAclPermissions($request, $data['user']);
            return redirect()->route('user.indexTermAcl', ['slug' => $slug])
                ->with('success', __('Taxonomy permissions saved.'));
        }

        return view('ahg-user-manage::edit-term-acl', $data);
    }

    // ── Researcher ACL ──

    public function editResearcherAcl(Request $request, string $slug)
    {
        $user = $this->service->getBySlug($slug);
        if (!$user) {
            abort(404);
        }

        $culture = app()->getLocale();

        $permissions = \Illuminate\Support\Facades\DB::table('acl_permission')
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('action', 'LIKE', 'research%')
                  ->orWhere('action', 'LIKE', 'researcher%');
            })
            ->get();

        foreach ($permissions as $perm) {
            $perm->object_name = null;
        }

        if ($request->isMethod('post')) {
            $this->saveAclPermissions($request, $user);
            return redirect()->route('user.show', ['slug' => $slug])
                ->with('success', __('Researcher permissions saved.'));
        }

        return view('ahg-user-manage::edit-researcher-acl', compact('user', 'permissions'));
    }
}
