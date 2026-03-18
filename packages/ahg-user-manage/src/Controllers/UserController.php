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
}
