<?php

namespace AhgLibrary\Tests\Feature;

use AhgLibrary\Services\AuthorityControlService;
use AhgLibrary\Services\AuthorityHierarchyService;
use AhgLibrary\Services\AuthorityRecordService;
use AhgLibrary\Services\AuthorityVocabularySyncService;
use AhgLibrary\Services\LcshVocabularyClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * heratio#1475 item 3, on the fresh-install table shape (subject_type column)
 * in the package's in-memory harness. No request leaves the process.
 */
class AuthorityDepthTest extends LibraryFeatureTestCase
{
    protected array $migrationFiles = [
        '2026_05_30_000001_create_library_authority_tables.php',
        '2026_11_22_100000_create_library_authority_depth_tables.php',
    ];

    private AuthorityControlService $authorities;
    private AuthorityHierarchyService $hierarchy;
    private AuthorityRecordService $records;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->authorities = app(AuthorityControlService::class);
        $this->hierarchy = app(AuthorityHierarchyService::class);
        $this->records = app(AuthorityRecordService::class);
    }

    private function term(string $heading, string $source = 'local'): int
    {
        return $this->authorities->create(['heading' => $heading, 'subject_type' => 'topic', 'source' => $source]);
    }

    private function concept(string $id, string $label, array $broader = [], array $narrower = []): array
    {
        $skos = 'http://www.w3.org/2004/02/skos/core#';
        $uri = fn ($x) => 'http://id.loc.gov/authorities/subjects/'.$x;
        $nodes = [[
            '@id' => $uri($id),
            $skos.'prefLabel' => [['@language' => 'en', '@value' => $label]],
            $skos.'broader' => array_map(fn ($x) => ['@id' => $uri($x)], array_keys($broader)),
            $skos.'narrower' => array_map(fn ($x) => ['@id' => $uri($x)], array_keys($narrower)),
        ]];
        foreach ($broader + $narrower as $otherId => $otherLabel) {
            $nodes[] = ['@id' => $uri($otherId), $skos.'prefLabel' => [['@language' => 'en', '@value' => $otherLabel]]];
        }

        return $nodes;
    }

    private function fakeConcept(string $id, array $nodes, int $status = 200): void
    {
        Http::fake(['https://id.loc.gov/authorities/subjects/'.$id.'.skos.json' => Http::response($nodes, $status)]);
    }

    private function linked(string $heading, string $id): int
    {
        $authorityId = $this->term($heading, 'lcsh');
        $this->records->linkExternal($authorityId, 'lcsh', $id);

        return $authorityId;
    }

    public function test_hierarchy_walks_both_ways_and_survives_depth(): void
    {
        [$a, $b, $c] = [$this->term('Arts'), $this->term('Painting'), $this->term('Watercolour')];
        $this->hierarchy->addRelation($a, $b);
        $this->hierarchy->addRelation($b, $c);

        $this->assertSame([$b, $c], $this->hierarchy->descendants($a));
        $this->assertSame([$b, $a], $this->hierarchy->ancestors($c));
        $this->assertSame([$b], $this->hierarchy->descendants($a, 1));
        $this->assertSame('Painting', $this->hierarchy->narrower($a)[0]->heading);
        $this->assertSame('Painting', $this->hierarchy->broader($c)[0]->heading);
    }

    public function test_self_links_cycles_and_unknown_terms_are_refused(): void
    {
        [$a, $b, $c] = [$this->term('A'), $this->term('B'), $this->term('C')];
        $this->hierarchy->addRelation($a, $b);
        $this->hierarchy->addRelation($b, $c);

        foreach ([[$a, $a], [$c, $a], [$a, 999999]] as [$broader, $narrower]) {
            try {
                $this->hierarchy->addRelation($broader, $narrower);
                $this->fail('Relation should have been refused');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(2, DB::table('library_authority_relation')->count());
    }

    public function test_search_expansion_includes_narrower_only_and_is_bounded_by_depth(): void
    {
        [$top, $mid, $low, $deep, $parent] = [$this->term('Arts'), $this->term('Painting'), $this->term('Watercolour'), $this->term('Botanical'), $this->term('Culture')];
        $this->hierarchy->addRelation($parent, $top);
        $this->hierarchy->addRelation($top, $mid);
        $this->hierarchy->addRelation($mid, $low);
        $this->hierarchy->addRelation($low, $deep);

        $two = $this->hierarchy->expandForSearch('  arts. ', 2);
        sort($two['headings']);
        $this->assertSame(['Arts', 'Painting', 'Watercolour'], $two['headings']);
        $this->assertNotContains($parent, $two['ids']);
        $this->assertSame([], $this->hierarchy->expandForSearch('no such term')['ids']);
    }

    public function test_record_type_filter_treats_untyped_rows_as_subject(): void
    {
        $untyped = $this->term('Untyped');
        $named = $this->term('Mandela, Nelson');
        $this->records->setType($named, 'name');

        $this->assertSame([$named], array_map(fn ($r) => (int) $r->id, $this->authorities->index(['record_type' => 'name'])['hits']));
        $this->assertSame([$untyped], array_map(fn ($r) => (int) $r->id, $this->authorities->index(['record_type' => 'subject'])['hits']));
    }

    public function test_sync_updates_record_and_builds_relations(): void
    {
        $id = $this->linked('Old label', 'sh85000001');
        $this->fakeConcept('sh85000001', $this->concept('sh85000001', 'New label', ['sh85000002' => 'Parent'], ['sh85000003' => 'Child']));

        $result = app(AuthorityVocabularySyncService::class)->sync($id);

        $this->assertTrue($result['ok'], (string) $result['error']);
        $this->assertSame(2, $result['created']);
        $row = $this->authorities->find($id);
        $this->assertSame('New label', $row->heading);
        $this->assertSame('http://id.loc.gov/authorities/subjects/sh85000001', $row->uri);
        $this->assertSame('Parent', $this->hierarchy->broader($id)[0]->heading);
        $this->assertSame('Child', $this->hierarchy->narrower($id)[0]->heading);
        $this->assertNotNull($this->records->get($id)->last_synced_at);
    }

    public function test_local_override_survives_sync_until_cleared(): void
    {
        $id = $this->linked('Original', 'sh85000001');
        $before = $this->authorities->find($id);
        $this->authorities->update($id, ['heading' => 'Our wording']);
        $this->records->recordLocalEdit($id, $before, ['heading' => 'Our wording']);
        $this->fakeConcept('sh85000001', $this->concept('sh85000001', 'Their wording'));

        $sync = app(AuthorityVocabularySyncService::class);
        $this->assertTrue($sync->sync($id)['ok']);
        $this->assertSame('Our wording', $this->authorities->find($id)->heading);

        $this->records->clearOverrides($id);
        $this->assertTrue($sync->sync($id)['ok']);
        $this->assertSame('Their wording', $this->authorities->find($id)->heading);
    }

    public function test_removed_synced_relation_stays_removed_and_local_relation_is_kept(): void
    {
        $id = $this->linked('Term', 'sh85000001');
        $local = $this->term('Local parent');
        $this->hierarchy->addRelation($local, $id);
        $this->fakeConcept('sh85000001', $this->concept('sh85000001', 'Term', ['sh85000002' => 'Remote parent']));
        $sync = app(AuthorityVocabularySyncService::class);
        $sync->sync($id);

        $remote = collect($this->hierarchy->broader($id))->firstWhere('heading', 'Remote parent');
        $this->hierarchy->removeRelation($remote->relation_id);
        $sync->sync($id);

        $this->assertSame(['Local parent'], array_map(fn ($t) => $t->heading, $this->hierarchy->broader($id)));
    }

    public function test_relation_dropped_by_the_vocabulary_is_removed(): void
    {
        $id = $this->linked('Term', 'sh85000001');
        $sync = app(AuthorityVocabularySyncService::class);
        Http::fakeSequence('id.loc.gov/authorities/subjects/sh85000001.skos.json')
            ->push($this->concept('sh85000001', 'Term', ['sh85000002' => 'Parent']))
            ->push($this->concept('sh85000001', 'Term'));
        // The other side no longer asserts it either.
        Http::fake(['id.loc.gov/authorities/subjects/sh85000002.skos.json' => Http::response($this->concept('sh85000002', 'Parent'))]);

        $sync->sync($id);
        $this->assertCount(1, $this->hierarchy->broader($id));
        $sync->sync($id);
        $this->assertCount(0, $this->hierarchy->broader($id));
    }

    public function test_removed_local_relation_the_vocabulary_also_asserts_stays_removed(): void
    {
        $id = $this->linked('Term', 'sh85000001');
        $parent = $this->linked('Parent', 'sh85000002');
        $this->hierarchy->addRelation($parent, $id); // local origin
        $this->fakeConcept('sh85000001', $this->concept('sh85000001', 'Term', ['sh85000002' => 'Parent']));
        $sync = app(AuthorityVocabularySyncService::class);

        $this->hierarchy->removeRelation($this->hierarchy->broader($id)[0]->relation_id);
        $this->assertTrue($sync->sync($id)['ok']);

        $this->assertSame([], $this->hierarchy->broader($id));
        $this->assertSame(1, DB::table('library_authority_relation')->where('suppressed', 1)->count());
    }

    public function test_synced_relation_re_added_by_staff_then_removed_stays_removed(): void
    {
        $id = $this->linked('Term', 'sh85000001');
        $this->fakeConcept('sh85000001', $this->concept('sh85000001', 'Term', ['sh85000002' => 'Parent']));
        $sync = app(AuthorityVocabularySyncService::class);
        $sync->sync($id);
        $parent = (int) $this->hierarchy->broader($id)[0]->id;

        $this->hierarchy->addRelation($parent, $id); // upgrades the origin to local
        $this->assertSame('local', $this->hierarchy->broader($id)[0]->origin);
        $this->hierarchy->removeRelation($this->hierarchy->broader($id)[0]->relation_id);
        $sync->sync($id);

        $this->assertSame([], $this->hierarchy->broader($id));
    }

    public function test_truncated_neighbour_list_does_not_delete_edges_beyond_the_cap(): void
    {
        $parent = $this->linked('Parent', 'sh85000001');
        $child = $this->linked('Child', 'sh85000999');
        $this->hierarchy->addRelation($parent, $child, AuthorityHierarchyService::ORIGIN_SYNC);

        $narrower = [];
        for ($i = 0; $i < 200; $i++) {
            $narrower['sh8600'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)] = 'Sibling '.$i;
        }
        $narrower['sh85000999'] = 'Child'; // the 201st, past the client's cap
        $this->fakeConcept('sh85000001', $this->concept('sh85000001', 'Parent', [], $narrower));

        $this->assertTrue(app(AuthorityVocabularySyncService::class)->sync($parent)['ok']);

        $this->assertContains($child, array_map(fn ($t) => (int) $t->id, $this->hierarchy->narrower($parent)));
    }

    public function test_edge_asserted_only_by_the_other_concept_is_kept(): void
    {
        $parent = $this->linked('Parent', 'sh85000001');
        $child = $this->linked('Child', 'sh85000002');
        $this->hierarchy->addRelation($parent, $child, AuthorityHierarchyService::ORIGIN_SYNC);
        Http::fake([
            'id.loc.gov/authorities/subjects/sh85000001.skos.json' => Http::response($this->concept('sh85000001', 'Parent')),
            'id.loc.gov/authorities/subjects/sh85000002.skos.json' => Http::response($this->concept('sh85000002', 'Child', ['sh85000001' => 'Parent'])),
        ]);

        $this->assertTrue(app(AuthorityVocabularySyncService::class)->sync($parent)['ok']);

        $this->assertCount(1, $this->hierarchy->narrower($parent));
    }

    public function test_edge_is_kept_when_the_other_concept_cannot_be_read(): void
    {
        $parent = $this->linked('Parent', 'sh85000001');
        $child = $this->linked('Child', 'sh85000002');
        $this->hierarchy->addRelation($parent, $child, AuthorityHierarchyService::ORIGIN_SYNC);
        Http::fake([
            'id.loc.gov/authorities/subjects/sh85000001.skos.json' => Http::response($this->concept('sh85000001', 'Parent')),
            'id.loc.gov/authorities/subjects/sh85000002.skos.json' => Http::response([], 503),
        ]);

        $this->assertTrue(app(AuthorityVocabularySyncService::class)->sync($parent)['ok']);

        $this->assertCount(1, $this->hierarchy->narrower($parent));
    }

    public function test_failed_fetch_of_a_new_identifier_keeps_the_working_link(): void
    {
        $id = $this->linked('Stable', 'sh20000001');
        $this->fakeConcept('sh29999999', [], 404);

        $result = app(AuthorityVocabularySyncService::class)->sync($id, 'sh29999999');

        $this->assertFalse($result['ok']);
        $record = $this->records->get($id);
        $this->assertSame('sh20000001', $record->external_id);
        $this->assertNull($record->last_sync_error);
    }

    public function test_new_identifier_is_linked_once_its_concept_is_read(): void
    {
        $id = $this->linked('Term', 'sh20000001');
        $this->fakeConcept('sh20000002', $this->concept('sh20000002', 'Term'));

        $this->assertTrue(app(AuthorityVocabularySyncService::class)->sync($id, 'sh20000002')['ok']);

        $this->assertSame('sh20000002', $this->records->get($id)->external_id);
    }

    public function test_untyped_rows_take_their_type_from_the_heading_type(): void
    {
        $person = $this->authorities->create(['heading' => 'Biko, Steve', 'subject_type' => 'personal', 'source' => 'local']);
        $this->assertNull($this->records->get($person));

        $ids = fn (string $type) => array_map(fn ($r) => (int) $r->id, $this->authorities->index(['record_type' => $type])['hits']);

        $this->assertSame([$person], $ids('name'));
        $this->assertSame([], $ids('subject'));
    }

    public function test_failed_fetch_changes_nothing_and_records_the_error(): void
    {
        $id = $this->linked('Stable', 'sh85000001');
        $this->fakeConcept('sh85000001', [], 503);

        $result = app(AuthorityVocabularySyncService::class)->sync($id);

        $this->assertFalse($result['ok']);
        $this->assertSame('Stable', $this->authorities->find($id)->heading);
        $this->assertStringContainsString('503', $this->records->get($id)->last_sync_error);
    }

    public function test_client_refuses_identifiers_that_could_steer_the_request(): void
    {
        foreach (['../../evil', 'sh1', 'sh85000001/../x', 'n79021164'] as $bad) {
            $this->assertFalse(LcshVocabularyClient::validId($bad), $bad);
        }
        $this->expectException(\RuntimeException::class);
        app(LcshVocabularyClient::class)->fetch('../../evil');
    }

    public function test_backfill_converts_flat_terms_and_reports_unresolved(): void
    {
        DB::statement('ALTER TABLE library_subject_authority ADD COLUMN broader_terms TEXT');
        DB::statement('ALTER TABLE library_subject_authority ADD COLUMN narrower_terms TEXT');
        [$parent, $child] = [$this->term('Parent'), $this->term('Child')];
        DB::table('library_subject_authority')->where('id', $child)->update(['broader_terms' => json_encode(['Parent', 'Missing'])]);

        $this->assertSame(['linked' => 1, 'unresolved' => 1, 'rejected' => 0], $this->hierarchy->backfillFlatTerms(true));
        $this->assertSame(0, DB::table('library_authority_relation')->count());
        $this->hierarchy->backfillFlatTerms();
        $this->assertSame([$parent], array_map(fn ($t) => (int) $t->id, $this->hierarchy->broader($child)));
    }
}
