<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchProjectOutputsSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // The project analysis / visualization / research-output routes are all
    // auth-gated - an anonymous request must redirect to /login, proving each
    // route resolves to the extracted ResearchProjectOutputsController and stays
    // inside the auth group (project-subsystem extraction, issue #1269).

    public function test_knowledge_graph_requires_auth()
    {
        $this->get('/research/knowledge-graph/1')->assertRedirect('/login');
    }

    public function test_assertions_requires_auth()
    {
        $this->get('/research/assertions/1')->assertRedirect('/login');
    }

    public function test_hypotheses_requires_auth()
    {
        $this->get('/research/hypotheses/1')->assertRedirect('/login');
    }

    public function test_extraction_jobs_requires_auth()
    {
        $this->get('/research/extraction-jobs/1')->assertRedirect('/login');
    }

    public function test_snapshots_requires_auth()
    {
        $this->get('/research/snapshots/1')->assertRedirect('/login');
    }

    public function test_view_snapshot_requires_auth()
    {
        $this->get('/research/viewSnapshot/1')->assertRedirect('/login');
    }

    public function test_assertion_batch_review_requires_auth()
    {
        $this->get('/research/assertion-batch-review/1')->assertRedirect('/login');
    }

    public function test_timeline_builder_requires_auth()
    {
        $this->get('/research/timeline/1')->assertRedirect('/login');
    }

    public function test_map_builder_requires_auth()
    {
        $this->get('/research/map/1')->assertRedirect('/login');
    }

    public function test_network_graph_requires_auth()
    {
        $this->get('/research/network-graph/1')->assertRedirect('/login');
    }

    public function test_ro_crate_requires_auth()
    {
        $this->get('/research/ro-crate/1')->assertRedirect('/login');
    }

    public function test_reproducibility_pack_requires_auth()
    {
        $this->get('/research/reproducibility/1')->assertRedirect('/login');
    }

    public function test_mint_doi_requires_auth()
    {
        $this->get('/research/doi/1')->assertRedirect('/login');
    }

    public function test_ethics_milestones_requires_auth()
    {
        $this->get('/research/ethics-milestones/1')->assertRedirect('/login');
    }

    public function test_compliance_dashboard_requires_auth()
    {
        $this->get('/research/compliance/1')->assertRedirect('/login');
    }
}
