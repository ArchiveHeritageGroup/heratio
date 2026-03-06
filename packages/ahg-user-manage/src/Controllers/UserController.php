<?php

namespace AhgUserManage\Controllers;

use AhgUserManage\Services\UserBrowseService;
use AhgCore\Pagination\SimplePager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $service = new UserBrowseService($culture);

        $result = $service->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', 30),
            'sort' => $request->get('sort', 'alphabetic'),
            'subquery' => $request->get('subquery', ''),
        ]);

        $pager = new SimplePager($result);

        return view('ahg-user-manage::browse', [
            'pager' => $pager,
            'sortOptions' => [
                'alphabetic' => 'Name',
                'lastUpdated' => 'Date modified',
                'email' => 'Email',
            ],
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $culture = app()->getLocale();

        $user = DB::table('user')
            ->join('slug', 'user.id', '=', 'slug.object_id')
            ->join('actor_i18n', 'user.id', '=', 'actor_i18n.id')
            ->join('object', 'user.id', '=', 'object.id')
            ->where('slug.slug', $slug)
            ->where('actor_i18n.culture', $culture)
            ->select([
                'user.id',
                'user.username',
                'user.email',
                'user.active',
                'actor_i18n.authorized_form_of_name',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (!$user) {
            abort(404);
        }

        // Get user groups
        $groups = DB::table('acl_user_group')
            ->join('acl_group_i18n', 'acl_user_group.group_id', '=', 'acl_group_i18n.id')
            ->where('acl_user_group.user_id', $user->id)
            ->where('acl_group_i18n.culture', $culture)
            ->select('acl_group_i18n.name', 'acl_group_i18n.description')
            ->get();

        // Get security clearance if table exists
        $securityClearance = null;
        try {
            $securityClearance = DB::table('security_clearance')
                ->where('user_id', $user->id)
                ->first();
        } catch (\Exception $e) {
            // Table may not exist — ignore
        }

        return view('ahg-user-manage::show', [
            'user' => $user,
            'groups' => $groups,
            'securityClearance' => $securityClearance,
        ]);
    }
}
