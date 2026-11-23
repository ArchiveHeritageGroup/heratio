<?php

namespace AhgLibrary\Tests\Feature;

use AhgLibrary\Services\AuthorityControlService;
use AhgCore\Services\DisclosureGate;
use AhgLibrary\Services\AuthorityHierarchyService;
use AhgLibrary\Services\AuthorityRecordService;
use AhgLibrary\Services\LibraryOpacSearchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * heratio#1475 item 3 against the real core-schema table (heading_type and
 * heading_normalized). Writes happen in a transaction that is rolled back.
 */
class AuthorityDepthCoreSchemaTest extends TestCase
{
    private bool $inTransaction = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('library_subject_authority')
            || ! Schema::hasColumn('library_subject_authority', 'heading_normalized')) {
            $this->markTestSkipped('Needs the core-schema authority table.');
        }
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_22_100000_create_library_authority_depth_tables.php')->up();
        DB::beginTransaction();
        $this->inTransaction = true;
    }

    protected function tearDown(): void
    {
        if ($this->inTransaction) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_create_find_and_search_work_on_the_core_schema(): void
    {
        $svc = new AuthorityControlService();
        $id = $svc->create(['heading' => 'Zzq  Test Heading.', 'subject_type' => 'topical', 'source' => 'local']);

        $row = $svc->find($id);
        $this->assertSame('topical', $row->subject_type);
        $this->assertSame('zzq test heading', $row->heading_normalized);
        $this->assertSame('topical', $svc->search('Zzq')[0]->subject_type);
        $this->assertCount(1, $svc->index(['subject_type' => 'topical', 'search' => 'Zzq'])['hits']);
    }

    public function test_hierarchy_and_expansion_on_the_core_schema(): void
    {
        $svc = new AuthorityControlService();
        $hierarchy = app(AuthorityHierarchyService::class);
        $parent = $svc->create(['heading' => 'Zzq Parent', 'subject_type' => 'topical', 'source' => 'local']);
        $child = $svc->create(['heading' => 'Zzq Child', 'subject_type' => 'topical', 'source' => 'local']);
        $hierarchy->addRelation($parent, $child);

        $expanded = $hierarchy->expandForSearch('zzq parent');
        sort($expanded['ids']);
        $this->assertSame([$parent, $child], $expanded['ids']);
    }

    public function test_every_route_the_authority_views_link_to_exists(): void
    {
        foreach (['edit', 'relation-add', 'relation-remove', 'sync', 'overrides-clear', 'view', 'update'] as $name) {
            $this->assertTrue(Route::has('library.authority-'.$name), $name);
        }
    }

    public function test_write_routes_require_a_signed_in_user(): void
    {
        $this->post('/library-manage/authority/1/sync')->assertRedirect();
        $this->post('/library-manage/authority/1/relations')->assertRedirect();
        $this->get('/library-manage/authority/1/edit')->assertRedirect();
    }

    public function test_staff_routes_carry_the_update_acl(): void
    {
        foreach (['edit', 'relation-add', 'relation-remove', 'sync', 'overrides-clear'] as $name) {
            $middleware = Route::getRoutes()->getByName('library.authority-'.$name)->gatherMiddleware();
            $this->assertContains('auth', $middleware, $name);
            $this->assertContains('acl:update', $middleware, $name);
        }
    }

    public function test_authority_pages_render_with_real_data(): void
    {
        $svc = new AuthorityControlService();
        $hierarchy = app(AuthorityHierarchyService::class);
        $parent = $svc->create(['heading' => 'Zzq Parent', 'subject_type' => 'topical', 'source' => 'local']);
        $child = $svc->create(['heading' => 'Zzq Child', 'subject_type' => 'topical', 'source' => 'local']);
        $hierarchy->addRelation($parent, $child);
        app(AuthorityRecordService::class)->setType($parent, 'genre');
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $controller = app(\AhgLibrary\Controllers\AuthorityControlController::class);

        $show = $controller->view($parent)->render();
        $this->assertStringContainsString('Zzq Child', $show);
        $this->assertStringContainsString('Genre / Form', $show);
        $edit = app(\AhgLibrary\Controllers\AuthorityHierarchyController::class)->edit($parent)->render();
        $this->assertStringContainsString('name="record_type"', $edit);
        $this->assertStringContainsString('value="Zzq Parent"', $edit);
        $this->assertStringContainsString('name="record_type"', $controller->create()->render());
        $this->assertStringContainsString('Zzq Parent', $controller->index(request()->create('/x', 'GET', ['search' => 'Zzq']))->render());
    }

    public function test_store_and_update_validate_type_and_record_an_override(): void
    {
        $controller = app(\AhgLibrary\Controllers\AuthorityControlController::class);
        $records = app(AuthorityRecordService::class);
        $form = ['heading' => 'Zzq Stored', 'record_type' => 'name', 'subject_type' => 'personal', 'source' => 'local'];

        $controller->store(\Illuminate\Http\Request::create('/x', 'POST', $form));
        $id = (int) DB::table('library_subject_authority')->where('heading', 'Zzq Stored')->value('id');
        $this->assertGreaterThan(0, $id);
        $this->assertSame('name', $records->get($id)->record_type);

        $records->linkExternal($id, 'lcsh', 'sh99999991');
        $controller->update(\Illuminate\Http\Request::create('/x', 'PUT', ['heading' => 'Zzq Edited'] + $form), $id);
        $this->assertSame(['heading'], $records->overriddenFields($records->get($id)));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->store(\Illuminate\Http\Request::create('/x', 'POST', ['record_type' => 'not-a-type'] + $form));
    }

    private function publishedItem(string $title, int $statusId = DisclosureGate::STATUS_PUBLISHED): int
    {
        $now = now();
        $lft = (int) DB::table('information_object')->max('rgt') + 100;
        $io = DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('information_object')->insert(['id' => $io, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $io, 'culture' => 'en', 'title' => $title]);
        DB::table('slug')->insert(['object_id' => $io, 'slug' => 'zzq-'.bin2hex(random_bytes(5))]);
        DB::table('status')->insert(['id' => \AhgCore\Support\StatusRow::allocateId(), 'object_id' => $io, 'type_id' => DisclosureGate::STATUS_TYPE_PUBLICATION, 'status_id' => $statusId]);

        return (int) DB::table('library_item')->insertGetId(['information_object_id' => $io, 'material_type' => 'monograph']);
    }

    public function test_opac_expansion_finds_narrower_items_and_never_drafts(): void
    {
        Http::fake(['*' => Http::response('', 503)]); // search index down: database path
        $svc = new AuthorityControlService();
        $parent = $svc->create(['heading' => 'Zzqparent', 'subject_type' => 'topical', 'source' => 'local']);
        $child = $svc->create(['heading' => 'Zzqchild', 'subject_type' => 'topical', 'source' => 'local']);
        app(AuthorityHierarchyService::class)->addRelation($parent, $child);
        $visible = $this->publishedItem('Unrelated visible book');
        $draft = $this->publishedItem('Unrelated draft book', 159);
        $svc->linkToItem($child, $visible);
        $svc->linkToItem($child, $draft);

        $search = new LibraryOpacSearchService();
        $ids = fn (array $f) => $search->search($f)['results']->getCollection()->pluck('id')->map(fn ($i) => (int) $i)->all();

        $this->assertSame([], $ids(['q' => 'Zzqparent']));
        $this->assertSame([$visible], $ids(['q' => 'Zzqparent', 'expand' => true]));
    }

    public function test_es_query_carries_expanded_headings_only_when_asked(): void
    {
        $bodies = [];
        Http::fake(function ($request) use (&$bodies) {
            if ($request->method() === 'POST') {
                $bodies[] = $request->body();

                return Http::response(['hits' => ['total' => ['value' => 0], 'hits' => []], 'aggregations' => []]);
            }

            return Http::response(['version' => ['number' => '8']]);
        });
        $svc = new AuthorityControlService();
        $parent = $svc->create(['heading' => 'Zzqparent', 'subject_type' => 'topical', 'source' => 'local']);
        $child = $svc->create(['heading' => 'Zzqchild', 'subject_type' => 'topical', 'source' => 'local']);
        app(AuthorityHierarchyService::class)->addRelation($parent, $child);

        $search = new LibraryOpacSearchService();
        $this->assertTrue($search->isAvailable());
        $search->search(['q' => 'Zzqparent']);
        $search->search(['q' => 'Zzqparent', 'expand' => true]);

        $this->assertCount(2, $bodies);
        $this->assertStringNotContainsString('subject_headings_exact', $bodies[0]);
        $should = json_decode($bodies[1], true)['query']['bool']['must'][0]['bool']['should'];
        $exact = collect($should)->firstWhere(fn ($clause) => isset($clause['terms']['subject_headings_exact']));
        $this->assertEqualsCanonicalizing(['zzqparent', 'zzqchild'], $exact['terms']['subject_headings_exact']);
        $this->assertEqualsCanonicalizing([$parent, $child], collect($should)
            ->firstWhere(fn ($clause) => isset($clause['terms']['authority_ids']))['terms']['authority_ids']);
        // No analysed-field phrase clause, which would also hit longer headings.
        $this->assertStringNotContainsString('match_phrase":{"subjects', $bodies[1]);
    }

    public function test_indexed_exact_subjects_keep_a_longer_heading_distinct(): void
    {
        $item = $this->publishedItem('Zzq indexed book');
        DB::table('library_item_subject')->insert(['library_item_id' => $item, 'heading' => 'Art history']);

        $method = new \ReflectionMethod(\AhgLibrary\Commands\LibraryReindexCommand::class, 'loadSubjectTerms');
        $terms = $method->invoke(app(\AhgLibrary\Commands\LibraryReindexCommand::class), [$item]);

        $this->assertSame(['art history'], $terms[$item]['subject_headings_exact']);
        $this->assertNotContains('art', $terms[$item]['subject_headings_exact']);
    }

    public function test_retired_record_type_is_kept_by_an_unrelated_edit(): void
    {
        $svc = new AuthorityControlService();
        $records = app(AuthorityRecordService::class);
        $id = $svc->create(['heading' => 'Zzq Retired', 'subject_type' => 'topical', 'source' => 'local']);
        $records->setType($id, 'retired-type');
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());

        $html = app(\AhgLibrary\Controllers\AuthorityHierarchyController::class)->edit($id)->render();
        $this->assertMatchesRegularExpression('/<option value="retired-type" selected>/', $html);

        app(\AhgLibrary\Controllers\AuthorityControlController::class)->update(\Illuminate\Http\Request::create('/x', 'PUT', [
            'heading' => 'Zzq Retired edited', 'record_type' => 'retired-type', 'subject_type' => 'topical', 'source' => 'local',
        ]), $id);

        $this->assertSame('Zzq Retired edited', $svc->find($id)->heading);
        $this->assertSame('retired-type', $records->get($id)->record_type);
    }

    public function test_neighbour_holding_the_lcsh_id_is_reused_not_duplicated(): void
    {
        Http::preventStrayRequests();
        $svc = new AuthorityControlService();
        $records = app(AuthorityRecordService::class);
        $id = $svc->create(['heading' => 'Zzq Term', 'subject_type' => 'topical', 'source' => 'lcsh']);
        $records->linkExternal($id, 'lcsh', 'sh99000001');
        // Different wording, source and type from the vocabulary's label.
        $legacy = $svc->create(['heading' => 'Zzq Local Wording', 'subject_type' => 'geographic', 'source' => 'local']);
        DB::table('library_subject_authority')->where('id', $legacy)->update(['lcsh_id' => 'sh99000002']);
        $skos = 'http://www.w3.org/2004/02/skos/core#';
        $uri = 'http://id.loc.gov/authorities/subjects/';
        Http::fake(['id.loc.gov/authorities/subjects/sh99000001.skos.json' => Http::response([
            ['@id' => $uri.'sh99000001', $skos.'prefLabel' => [['@language' => 'en', '@value' => 'Zzq Term']], $skos.'broader' => [['@id' => $uri.'sh99000002']]],
            ['@id' => $uri.'sh99000002', $skos.'prefLabel' => [['@language' => 'en', '@value' => 'Zzq Vocabulary Label']]],
        ])]);
        $before = DB::table('library_subject_authority')->count();

        $result = app(\AhgLibrary\Services\AuthorityVocabularySyncService::class)->sync($id);

        $this->assertTrue($result['ok'], (string) $result['error']);
        $this->assertSame(0, $result['created']);
        $this->assertSame($before, DB::table('library_subject_authority')->count());
        $this->assertSame($legacy, (int) app(AuthorityHierarchyService::class)->broader($id)[0]->id);
        $this->assertSame('sh99000002', $records->get($legacy)->external_id);
    }

    public function test_migration_backfills_links_from_lcsh_ids_and_keeps_the_oldest_on_duplicates(): void
    {
        $svc = new AuthorityControlService();
        $first = $svc->create(['heading' => 'Zzq First', 'subject_type' => 'topical', 'source' => 'local']);
        $second = $svc->create(['heading' => 'Zzq Second', 'subject_type' => 'topical', 'source' => 'local']);
        $viaUri = $svc->create(['heading' => 'Zzq Via Uri', 'subject_type' => 'topical', 'source' => 'local']);
        DB::table('library_subject_authority')->whereIn('id', [$first, $second])->update(['lcsh_id' => 'sh99000011']);
        DB::table('library_subject_authority')->where('id', $viaUri)->update(['lcsh_uri' => 'https://id.loc.gov/authorities/subjects/sh99000012']);

        (require dirname(__DIR__, 2).'/database/migrations/2026_11_22_100000_create_library_authority_depth_tables.php')->up();

        $records = app(AuthorityRecordService::class);
        $this->assertSame('sh99000011', $records->get($first)->external_id);
        $this->assertNull($records->get($second)->external_id);
        $this->assertSame('sh99000012', $records->get($viaUri)->external_id);
    }

    public function test_marc_imported_authority_is_typed_by_its_heading(): void
    {
        $importer = (new \ReflectionClass(\AhgMetadataExport\Services\Importers\MarcXmlImporter::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($importer, 'findOrCreateAuthority');
        $id = $method->invoke($importer, ['tag' => '655', 'heading' => 'Zzq Imported Genre', 'uri' => null, 'subject_type' => 'genre'], now()->format('Y-m-d H:i:s'));

        $row = DB::table('library_subject_authority')->where('id', $id)->first();
        $this->assertSame('genre', $row->heading_type);
        $this->assertSame('zzq imported genre', $row->heading_normalized);
        $svc = new AuthorityControlService();
        $this->assertContains($id, array_map(fn ($r) => (int) $r->id, $svc->index(['record_type' => 'genre', 'search' => 'Zzq Imported', 'limit' => 100])['hits']));
        $this->assertNotContains($id, array_map(fn ($r) => (int) $r->id, $svc->index(['record_type' => 'subject', 'search' => 'Zzq Imported', 'limit' => 100])['hits']));
    }
}
