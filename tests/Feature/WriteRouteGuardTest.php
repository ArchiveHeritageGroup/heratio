<?php

/**
 * WriteRouteGuardTest - heratio#1777. Write routes guarded only by sign-in.
 *
 * Self-registration is open on public installs, so "signed in" is a weak
 * boundary. This test keeps the rule visible in the route table:
 *
 *  - every POST/PUT/PATCH/DELETE route with the 'auth' middleware must also
 *    carry acl:, admin or API-auth middleware, or be listed with a reason in
 *    tests/Feature/write-route-allowlist.php;
 *  - a plain signed-in user (no groups) is refused on a sample of the staff
 *    writes that were fixed, while an editor still gets through;
 *  - the per-user owner checks added in the research controllers hold.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WriteRouteGuardTest extends TestCase
{
    use DatabaseTransactions;

    /** Resolved middleware that counts as a permission check beyond sign-in. */
    private const GUARDS = [
        \App\Http\Middleware\CheckAcl::class,
        \App\Http\Middleware\RequireAdmin::class,
        'AhgApi\Middleware\ApiAuthenticate',
        'AhgApi\Middleware\ApiStaffOnly',
        'AhgLibrary\Middleware\EnsurePatronAuthenticated',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        AclService::forgetUser();
    }

    // ── CI guard ────────────────────────────────────────────────────────────

    public function test_every_auth_only_write_route_is_guarded_or_allow_listed(): void
    {
        $allow = require __DIR__.'/write-route-allowlist.php';
        $router = app('router');
        $unlisted = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! $this->isAuthOnlyWrite($route, $router->gatherRouteMiddleware($route))) {
                continue;
            }
            $action = ltrim($route->getActionName(), '\\');
            if ($action === 'Closure') {
                $listed = isset($allow['Closure'][$route->uri()]);
            } else {
                [$class, $method] = array_pad(explode('@', $action, 2), 2, '__invoke');
                $listed = isset($allow[$class][$method]);
            }
            if (! $listed) {
                $unlisted[] = implode('|', $route->methods()).' '.$route->uri().' -> '.$action;
            }
        }

        $this->assertSame([], $unlisted,
            "Write routes guarded only by 'auth'. Add acl:<action> or admin route middleware, "
            .'or - if the route writes only the caller\'s own data - list it in '
            .'tests/Feature/write-route-allowlist.php with a reason.');
    }

    public function test_allow_list_has_no_stale_entries(): void
    {
        $allow = require __DIR__.'/write-route-allowlist.php';
        $router = app('router');
        $live = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($this->isAuthOnlyWrite($route, $router->gatherRouteMiddleware($route))) {
                $action = ltrim($route->getActionName(), '\\');
                $action === 'Closure' ? $live['Closure'][$route->uri()] = 1 : $live[$action] = 1;
            }
        }

        $stale = [];
        foreach ($allow as $class => $entries) {
            foreach (array_keys($entries) as $key) {
                $hit = $class === 'Closure' ? isset($live['Closure'][$key]) : isset($live[$class.'@'.$key]);
                if (! $hit) {
                    $stale[] = $class.' '.$key;
                }
            }
        }

        $this->assertSame([], $stale, 'Allow-list entries that no longer match an auth-only write route - remove them.');
    }

    /** @param array<int, mixed> $middleware resolved middleware for the route */
    private function isAuthOnlyWrite(RoutingRoute $route, array $middleware): bool
    {
        if (array_diff($route->methods(), ['GET', 'HEAD']) === []) {
            return false;
        }
        $names = array_map(fn ($m) => is_string($m) ? $m : '', $middleware);
        $hasAuth = false;
        foreach ($names as $m) {
            if ($m === \Illuminate\Auth\Middleware\Authenticate::class || str_starts_with($m, \Illuminate\Auth\Middleware\Authenticate::class.':')) {
                $hasAuth = true;
            }
            foreach (self::GUARDS as $g) {
                if ($m === $g || str_starts_with($m, $g.':')) {
                    return false;
                }
            }
        }

        return $hasAuth;
    }

    // ── staff writes: plain user refused, editor allowed ────────────────────

    /** @return array<string, array{0: string, 1: string, 2: array<string, mixed>}> */
    public static function staffWrites(): array
    {
        return [
            'capture queue add' => ['post', 'capture-priority.queue.add', []],
            'alt text save' => ['post', 'alt-text.save', []],
            'story save' => ['post', 'stories.save', []],
            'self-assessment start' => ['post', 'preservation-self-assessment.start', []],
            'kbart commit' => ['post', 'library.kbart-commit', []],
            'page index build' => ['post', 'ahgdiscovery.build.store', []],
            'help article link' => ['post', 'help.article.links.add', ['slug' => 'no-such-article']],
            'archaeology site save' => ['post', 'archaeology.site.store', []],
            'archaeology relationship delete' => ['post', 'archaeology.context.relationship.delete', ['id' => 1, 'relId' => 1]],
        ];
    }

    #[DataProvider('staffWrites')]
    public function test_plain_user_is_refused_on_staff_write(string $verb, string $name, array $params): void
    {
        $this->actingAs($this->user())->{$verb}(route($name, $params))->assertForbidden();
    }

    #[DataProvider('staffWrites')]
    public function test_editor_passes_the_route_guard(string $verb, string $name, array $params): void
    {
        $status = $this->actingAs($this->user(101))->{$verb}(route($name, $params))->getStatusCode();
        $this->assertNotSame(403, $status, "Editor refused on {$name}");
    }

    public function test_multi_file_upload_form_stays_open_but_its_post_is_staff_only(): void
    {
        $url = route('io.multiFileUpload', ['slug' => 'no-such-record']);
        $this->actingAs($this->user())->post($url)->assertForbidden();
        $this->assertNotSame(403, $this->actingAs($this->user())->get($url)->getStatusCode());
    }

    // ── research owner checks (class b) ─────────────────────────────────────

    public function test_researcher_cannot_delete_another_researchers_lecture_resource(): void
    {
        $this->needTables('research_lecture', 'research_lecture_resource', 'research_researcher');
        [, $ownerResearcher] = $this->researcher();
        [$other] = $this->researcher();
        $lecture = DB::table('research_lecture')->insertGetId(['title' => 'L', 'researcher_id' => $ownerResearcher]);
        $res = DB::table('research_lecture_resource')->insertGetId(['lecture_id' => $lecture, 'label' => 'R']);

        $this->actingAs($other)->delete('/research/lectures/resource/'.$res)->assertForbidden();
        $this->assertTrue(DB::table('research_lecture_resource')->where('id', $res)->exists());
    }

    public function test_researcher_cannot_delete_or_rewrite_another_researchers_policy(): void
    {
        $this->needTables('research_rights_policy', 'research_researcher');
        [, $authorResearcher] = $this->researcher();
        [$other] = $this->researcher();
        $policy = DB::table('research_rights_policy')->insertGetId([
            'target_type' => 'project', 'target_id' => 999999, 'policy_type' => 'prohibition',
            'action_type' => 'use', 'created_by' => $authorResearcher,
        ]);

        $this->actingAs($other)->post('/research/odrlPolicies', ['form_action' => 'delete', 'policy_id' => $policy])->assertForbidden();
        $this->actingAs($other)->post('/research/odrlPolicies', [
            'form_action' => 'create', 'target_type' => 'information_object', 'target_id' => 1,
            'policy_type' => 'prohibition', 'action_type' => 'use',
        ])->assertForbidden();
        $this->assertTrue(DB::table('research_rights_policy')->where('id', $policy)->exists());
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function needTables(string ...$tables): void
    {
        foreach ($tables as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("{$t} not installed in this database.");
            }
        }
    }

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function user(?int $group = null): User
    {
        $id = $this->objectRow('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'wrg-'.$id, 'email' => uniqid('wrg-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        if ($group) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        }
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    /** @return array{0: User, 1: int} a plain user with an approved researcher profile */
    private function researcher(): array
    {
        $user = $this->user();
        $rid = (int) DB::table('research_researcher')->insertGetId([
            'user_id' => $user->id, 'first_name' => 'T', 'last_name' => 'R',
            'email' => $user->email, 'status' => 'approved',
        ]);

        return [$user, $rid];
    }
}
