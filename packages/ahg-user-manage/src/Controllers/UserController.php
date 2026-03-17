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
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:user,username',
            'email' => 'required|email|max:255|unique:user,email',
            'password' => 'required|string|min:6',
            'authorized_form_of_name' => 'nullable|string|max:1024',
        ]);

        $data = $request->only([
            'username', 'email', 'password', 'authorized_form_of_name',
        ]);
        $data['active'] = $request->has('active') ? 1 : 0;
        $data['groups'] = $request->input('groups', []);

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
            'authorized_form_of_name' => 'nullable|string|max:1024',
        ]);

        $data = $request->only([
            'username', 'email', 'password', 'authorized_form_of_name',
        ]);
        $data['active'] = $request->has('active') ? 1 : 0;
        $data['groups'] = $request->input('groups', []);

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
