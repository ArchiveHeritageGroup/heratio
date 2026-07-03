<?php

namespace Tests\Feature\Research;

use AhgResearch\Services\OfflineSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Researcher Offline (/research/mobile) sync-back applier.
 *
 * Proves the researcher-sync.json the offline package produces is applied to
 * the right places: notes + sources -> research_annotation, suggestions ->
 * research_metadata_suggestion (curator queue). Empty entries are skipped, and
 * the /research/mobile surface is auth-gated.
 */
class OfflineSyncTest extends TestCase
{
    use DatabaseTransactions;

    private function requireTables(): void
    {
        foreach (['research_annotation', 'research_metadata_suggestion'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t not provisioned in the test DB");
            }
        }
    }

    public function test_work_offline_requires_authentication(): void
    {
        if (! Route::has('research.mobileHome')) {
            $this->markTestSkipped('research routes not registered');
        }
        $this->get('/research/mobile')->assertRedirect();
    }

    public function test_apply_bundle_writes_notes_sources_and_suggestions(): void
    {
        $this->requireTables();
        $rid = 987654;
        $payload = [
            'heratio_sync' => 1,
            'package_id' => 0,
            'changes' => [
                'notes' => [['io_id' => 42, 'slug' => 'x', 'text' => 'A field note']],
                'sources' => [['io_id' => 42, 'slug' => 'x', 'title' => 'The Book', 'author' => 'Smith', 'year' => '2020', 'url' => 'http://example.test']],
                'metadata_suggestions' => [['io_id' => 42, 'slug' => 'x', 'field' => 'Title', 'text' => 'Corrected title']],
                'files' => [],
            ],
        ];

        $r = (new OfflineSyncService())->applyBundle($rid, $payload);

        $this->assertSame(3, $r['applied']);
        $this->assertSame(0, $r['conflicts']);
        $this->assertDatabaseHas('research_annotation', ['researcher_id' => $rid, 'object_id' => 42, 'annotation_type' => 'note']);
        $this->assertDatabaseHas('research_annotation', ['researcher_id' => $rid, 'object_id' => 42, 'annotation_type' => 'source']);
        $this->assertDatabaseHas('research_metadata_suggestion', ['researcher_id' => $rid, 'object_id' => 42, 'field' => 'Title', 'status' => 'open']);
    }

    public function test_apply_bundle_skips_empty_and_invalid_entries(): void
    {
        $this->requireTables();
        $rid = 987655;
        $payload = [
            'heratio_sync' => 1,
            'changes' => [
                'notes' => [['io_id' => 9, 'text' => '   ']],          // blank -> skipped
                'metadata_suggestions' => [['io_id' => 0, 'field' => 'Title', 'text' => 'x']], // no object -> skipped
                'sources' => [],
                'files' => [],
            ],
        ];

        $r = (new OfflineSyncService())->applyBundle($rid, $payload);

        $this->assertSame(0, $r['applied']);
        $this->assertDatabaseMissing('research_annotation', ['researcher_id' => $rid]);
        $this->assertDatabaseMissing('research_metadata_suggestion', ['researcher_id' => $rid]);
    }
}
