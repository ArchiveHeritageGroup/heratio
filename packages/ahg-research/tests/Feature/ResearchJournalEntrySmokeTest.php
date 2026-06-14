<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchJournalEntrySmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // The personal research journal / diary routes are all auth-gated - an
    // anonymous request must redirect to /login, proving the route resolves to
    // the extracted ResearchJournalEntryController and stays inside the auth
    // group (split out of ResearchJournalController, issue #1270).
    public function test_journal_requires_auth()
    {
        $response = $this->get('/research/journal');
        $response->assertRedirect('/login');
    }

    public function test_journal_create_requires_auth()
    {
        $response = $this->get('/research/journal/create');
        $response->assertRedirect('/login');
    }

    public function test_journal_show_requires_auth()
    {
        $response = $this->get('/research/journal/1');
        $response->assertRedirect('/login');
    }

    public function test_journal_entry_requires_auth()
    {
        $response = $this->get('/research/journal/entry/1');
        $response->assertRedirect('/login');
    }
}
