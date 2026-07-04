<?php

/**
 * ResearchEventEmitTest - #1254
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 *
 * Verifies the research lifecycle events fired from the v1 research API
 * chokepoints (ResearchProjectApiController / ResearchOutputApiController):
 *
 *   - POST  /api/v1/research-projects        -> ProjectCreated
 *   - PUT   /api/v1/research-projects/{id}   -> ProjectUpdated (+ ProjectClosed
 *                                               when status moves to a closed
 *                                               state: completed / archived)
 *   - POST  /api/v1/research-outputs         -> OutputPublished (status=published)
 *
 * Runs against the pre-built heratio_test DB with DatabaseTransactions (NOT
 * RefreshDatabase, per #1136 - that would drop the ~995 base tables this suite
 * relies on). Each test rolls back. Authenticates as an AhgCore\Models\User
 * (the populated `user` table), NOT App\Models\User (the empty `users` table) -
 * a logged-in session is granted full API scopes (read/write/delete).
 *
 * NOTE: the legacy web ResearchController project/output create/update paths
 * are LOCKED (packages/ahg-research/) and are a separate #1254 follow-up; the
 * events are emitted from the unlocked ahg-api controllers only for now.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgResearch\Events\OutputPublished;
use AhgResearch\Events\ProjectClosed;
use AhgResearch\Events\ProjectCreated;
use AhgResearch\Events\ProjectUpdated;
use AhgResearch\Services\UserProvisioner\EloquentUserProvisioner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResearchEventEmitTest extends TestCase
{
    use DatabaseTransactions;

    /** The lifecycle events under test - fake exactly these, let others through. */
    private const FAKED = [
        ProjectCreated::class,
        ProjectUpdated::class,
        ProjectClosed::class,
        OutputPublished::class,
    ];

    /**
     * Resolve a researcher whose user row exists in `user`, or provision one,
     * so actingAs() + the owner-resolution path both work.
     */
    private function ownerResearcher(): object
    {
        $researcher = DB::table('research_researcher as r')
            ->join('user as u', 'u.id', '=', 'r.user_id')
            ->select('r.*')
            ->first();

        if ($researcher) {
            return $researcher;
        }

        $provisioner = new EloquentUserProvisioner;
        $uniq = 'evttest_' . uniqid();
        $userId = $provisioner->createUser($uniq, $uniq . '@example.test', 'Secret123!');

        $researcherId = DB::table('research_researcher')->insertGetId([
            'user_id' => $userId,
            'first_name' => 'Event',
            'last_name' => 'Tester',
            'email' => $uniq . '@example.test',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return DB::table('research_researcher')->where('id', $researcherId)->first();
    }

    private function actingUser(int $userId): User
    {
        // #1395: API write scopes are granted by ACL role, so the acting user
        // must be authorised. Join the ADMINISTRATOR group (idempotent) so the
        // create/update/delete/publish scopes resolve; a plain authenticated
        // user is read-only by design after the hardening.
        DB::table('acl_user_group')->updateOrInsert(['user_id' => $userId, 'group_id' => 100]);
        \Illuminate\Support\Facades\Cache::forget("acl_groups_{$userId}");

        return User::query()->findOrFail($userId);
    }

    public function test_store_project_dispatches_project_created(): void
    {
        Event::fake(self::FAKED);

        $owner = $this->ownerResearcher();

        $id = $this->actingAs($this->actingUser($owner->user_id))
            ->postJson('/api/v1/research-projects', [
                'title' => 'Event Project ' . uniqid(),
                'status' => 'planning',
            ])
            ->assertStatus(201)
            ->json('id');

        $this->assertNotNull($id);

        Event::assertDispatched(
            ProjectCreated::class,
            fn (ProjectCreated $e) => $e->projectId === (int) $id
                && $e->researcherId === (int) $owner->id
        );
        Event::assertNotDispatched(ProjectClosed::class);
    }

    public function test_update_to_active_dispatches_only_project_updated(): void
    {
        $owner = $this->ownerResearcher();
        $acting = $this->actingUser($owner->user_id);

        // Create OUTSIDE the fake so ProjectCreated is real and not counted.
        $id = (int) $this->actingAs($acting)
            ->postJson('/api/v1/research-projects', [
                'title' => 'Update Project ' . uniqid(),
                'status' => 'planning',
            ])
            ->assertStatus(201)
            ->json('id');

        Event::fake(self::FAKED);

        $this->actingAs($acting)
            ->putJson('/api/v1/research-projects/' . $id, ['status' => 'active'])
            ->assertStatus(200);

        Event::assertDispatched(
            ProjectUpdated::class,
            fn (ProjectUpdated $e) => $e->projectId === $id && $e->researcherId === (int) $owner->id
        );
        // active is not a closed state - no ProjectClosed.
        Event::assertNotDispatched(ProjectClosed::class);
    }

    public function test_update_to_completed_dispatches_project_updated_and_closed(): void
    {
        $owner = $this->ownerResearcher();
        $acting = $this->actingUser($owner->user_id);

        $id = (int) $this->actingAs($acting)
            ->postJson('/api/v1/research-projects', [
                'title' => 'Closing Project ' . uniqid(),
                'status' => 'active',
            ])
            ->assertStatus(201)
            ->json('id');

        Event::fake(self::FAKED);

        $this->actingAs($acting)
            ->putJson('/api/v1/research-projects/' . $id, ['status' => 'completed'])
            ->assertStatus(200);

        Event::assertDispatched(
            ProjectUpdated::class,
            fn (ProjectUpdated $e) => $e->projectId === $id && $e->researcherId === (int) $owner->id
        );
        Event::assertDispatched(
            ProjectClosed::class,
            fn (ProjectClosed $e) => $e->projectId === $id && $e->researcherId === (int) $owner->id
        );
    }

    public function test_store_published_output_dispatches_output_published(): void
    {
        $owner = $this->ownerResearcher();
        $acting = $this->actingUser($owner->user_id);

        // A project to hang the output on.
        $projectId = (int) DB::table('research_project')->insertGetId([
            'owner_id' => $owner->id,
            'title' => 'Output Project ' . Str::random(5),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Event::fake(self::FAKED);

        $id = (int) $this->actingAs($acting)
            ->postJson('/api/v1/research-outputs', [
                'project_id' => $projectId,
                'title' => 'Published Output ' . Str::random(5),
                'output_type' => 'journal_article',
                'identifier_type' => 'doi',
                'identifier' => '10.1234/evt.' . Str::random(5),
                'output_date' => '2026-02-01',
                'status' => 'published',
            ])
            ->assertStatus(201)
            ->json('id');

        $this->assertGreaterThan(0, $id);

        Event::assertDispatched(
            OutputPublished::class,
            fn (OutputPublished $e) => $e->outputId === $id && $e->projectId === $projectId
        );
    }

    public function test_store_unpublished_output_does_not_dispatch_output_published(): void
    {
        $owner = $this->ownerResearcher();
        $acting = $this->actingUser($owner->user_id);

        $projectId = (int) DB::table('research_project')->insertGetId([
            'owner_id' => $owner->id,
            'title' => 'Draft Output Project ' . Str::random(5),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Event::fake(self::FAKED);

        $this->actingAs($acting)
            ->postJson('/api/v1/research-outputs', [
                'project_id' => $projectId,
                'title' => 'Draft Output ' . Str::random(5),
                'output_type' => 'journal_article',
                'status' => 'in_progress',
            ])
            ->assertStatus(201);

        Event::assertNotDispatched(OutputPublished::class);
    }
}
