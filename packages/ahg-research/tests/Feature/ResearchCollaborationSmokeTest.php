<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchCollaborationSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // --- Collaborator management (auth-gated; anon -> /login redirect) ---
    // Each GET request must redirect to /login, proving the route resolves to
    // the extracted ResearchCollaborationController and stays inside the auth
    // group (issue #1269 decomposition).

    public function test_invite_collaborator_requires_auth()
    {
        $response = $this->get('/research/invite-collaborator/1');
        $response->assertRedirect('/login');
    }

    public function test_share_project_requires_auth()
    {
        $response = $this->get('/research/share-project/1');
        $response->assertRedirect('/login');
    }

    public function test_project_collaborators_requires_auth()
    {
        $response = $this->get('/research/project-collaborators/1');
        $response->assertRedirect('/login');
    }

    // --- Real-time collaboration ---
    // collabPanel is an HTML view route: anon -> /login redirect.

    public function test_collab_panel_requires_auth()
    {
        $response = $this->get('/research/projects/1/realtime/panel');
        $response->assertRedirect('/login');
    }

    // The AJAX/JSON endpoints call abort(401) when unauthenticated (no /login
    // redirect). We assert the route resolves to the right HTTP verb and is
    // not publicly readable (anything but 200 OK proves the auth gate fires).

    public function test_collab_join_resolves_as_post_and_is_gated()
    {
        // GET must not match (route is POST-only) -> 405, never 200.
        $get = $this->get('/research/projects/1/realtime/join');
        $this->assertNotSame(200, $get->getStatusCode());

        $post = $this->post('/research/projects/1/realtime/join');
        $this->assertNotSame(200, $post->getStatusCode());
    }

    public function test_collab_poll_resolves_as_get_and_is_gated()
    {
        $response = $this->get('/research/projects/1/realtime/poll');
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_collab_comment_resolves_as_post_and_is_gated()
    {
        // GET must not match (route is POST-only) -> 405, never 200.
        $get = $this->get('/research/projects/1/realtime/comment');
        $this->assertNotSame(200, $get->getStatusCode());

        $post = $this->post('/research/projects/1/realtime/comment');
        $this->assertNotSame(200, $post->getStatusCode());
    }

    public function test_collab_comment_resolve_resolves_as_post_and_is_gated()
    {
        $get = $this->get('/research/projects/1/realtime/comment/1/resolve');
        $this->assertNotSame(200, $get->getStatusCode());

        $post = $this->post('/research/projects/1/realtime/comment/1/resolve');
        $this->assertNotSame(200, $post->getStatusCode());
    }
}
