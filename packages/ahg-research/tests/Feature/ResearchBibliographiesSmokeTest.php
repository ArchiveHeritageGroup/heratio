<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchBibliographiesSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Three of the four bibliography routes are auth-gated - an anonymous
    // request must redirect to /login, proving each route resolves to the
    // extracted ResearchBibliographiesController and stays inside the auth group
    // (stage 6 extraction, issue #1253 / #1269).

    public function test_bibliographies_index_requires_auth()
    {
        $response = $this->get('/research/bibliographies');
        $response->assertRedirect('/login');
    }

    public function test_view_bibliography_requires_auth()
    {
        $response = $this->get('/research/viewBibliography/1');
        $response->assertRedirect('/login');
    }

    public function test_export_bibliography_requires_auth()
    {
        $response = $this->get('/research/bibliography/1/export/bibtex');
        $response->assertRedirect('/login');
    }

    // bibliographyEntry.export is *declared* in the auth group with URI
    // /research/cite/{itemId}/export/{format} ({itemId} = [0-9]+). At runtime,
    // however, the PUBLIC route research.citeExport
    // (/research/cite/{slug}/export/{format}, no auth, registered first because
    // the public group is declared before the auth group) shadows it: a numeric
    // path also matches the unconstrained {slug}. So this path behaves as a
    // PUBLIC route - it does NOT redirect to /login, and a non-existent
    // slug/id yields a 404 (citeExport aborts 404 when the slug is unknown).
    // This shadowing is pre-existing and unchanged by the stage-6 extraction;
    // the test pins the actual public behaviour rather than the declared-auth
    // intent. (Fixing the shadow is out of scope for the decomposition stage.)
    public function test_export_bibliography_entry_is_publicly_reachable_and_404s_on_bad_id()
    {
        $response = $this->get('/research/cite/999999999/export/bibtex');
        $this->assertNotEquals(302, $response->getStatusCode(), 'should not redirect to login (path is public via citeExport shadow)');
        $response->assertNotFound();
    }
}
