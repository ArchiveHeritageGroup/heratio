<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchCollectionsSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Collection routes are all auth-gated - an anonymous GET must redirect to
    // /login, proving the route resolves to the extracted controller and stays
    // inside the auth group (Collections extraction, issue #1269).
    public function test_collections_index_requires_auth()
    {
        $response = $this->get('/research/collections');
        $response->assertRedirect('/login');
    }

    public function test_collections_create_form_requires_auth()
    {
        $response = $this->get('/research/collections/create');
        $response->assertRedirect('/login');
    }

    public function test_view_collection_requires_auth()
    {
        $response = $this->get('/research/viewCollection?id=1');
        $response->assertRedirect('/login');
    }

    // The mutating routes are registered as POST/PUT/DELETE (the extraction
    // must not drop or re-verb any route). Assert each named route exists and
    // accepts the expected verb. This does not assert the controller class -
    // rebinding the route action to ResearchCollectionsController is the
    // integrator's step - and it does not depend on runtime 405 behaviour (the
    // /{slug} catch-all can shadow a bare GET to a mutating path with a 404
    // before MethodNotAllowed is reached).
    public function test_collection_mutating_routes_are_registered_with_correct_verbs()
    {
        $routes = app('router')->getRoutes();

        $expected = [
            'research.collections.store'      => 'POST',
            'research.collections.update'     => 'PUT',
            'research.collections.destroy'    => 'DELETE',
            'research.collections.addItem'    => 'POST',
            'research.collections.removeItem' => 'DELETE',
            'research.addToCollection'        => 'POST',
            'research.createCollectionAjax'   => 'POST',
        ];

        foreach ($expected as $name => $verb) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "Route {$name} should be registered");
            $this->assertContains($verb, $route->methods(), "Route {$name} should accept {$verb}");
        }
    }
}
