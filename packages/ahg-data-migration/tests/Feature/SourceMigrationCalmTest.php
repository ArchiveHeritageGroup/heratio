<?php

/**
 * heratio#1495: a Calm export through preflight, acknowledgement and commit.
 * Tree assertions read information_object_closure: a record that exists but is
 * outside the closure must fail these tests.
 *
 * @license AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Constants\TermId;
use AhgCore\Models\AclGroup;
use AhgCore\Services\HierarchyQueryService;
use AhgDataMigration\SourceMigration\SourceDetector;
use AhgDataMigration\SourceMigration\SourceMigrationPreflight;
use AhgDataMigration\SourceMigration\SourceMigrationRunner;
use AhgUserManage\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SourceMigrationCalmTest extends TestCase
{
    use DatabaseTransactions;

    private string $tag;

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tag = 'T'.strtoupper(bin2hex(random_bytes(4)));
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function csv(array $rows): string
    {
        $base = tempnam(sys_get_temp_dir(), 'calm');
        $file = $base.'.csv';
        array_push($this->files, $base, $file);
        $fh = fopen($file, 'w');
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);

        return $file;
    }

    private function admin(bool $administrator = true): int
    {
        $id = app(UserService::class)->create(['username' => 'sm'.bin2hex(random_bytes(4)), 'email' => 'sm'.bin2hex(random_bytes(4)).'@test.invalid',
            'password' => 'x'.bin2hex(random_bytes(8)), 'authorized_form_of_name' => 'Migration tester', 'active' => 1, 'groups' => []]);
        if ($administrator) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => AclGroup::ADMINISTRATOR_ID]);
        }
        Cache::forget("acl_groups_{$id}");

        return $id;
    }

    private function gappyExport(): string
    {
        $t = $this->tag;

        return $this->csv([
            ['RefNo', 'AltRefNo', 'Level', 'Title', 'Date', 'AdminHistory', 'CustodialHistory', 'PersonName', 'CorpName', 'CreatorName', 'Place', 'Subject', 'ClosedUntil', 'AccessConditions', 'LocalField'],
            // Child first, and GB/1/2 is deliberately missing.
            ["GB {$t}/1/2/3", 'OLD-3', 'Piece', 'Letter', 'circa 1890s', '', '', "Smith, Jane {$t}; Doe, John {$t}", "Acme Mining {$t}", "Creator {$t}", "Durban {$t}", "Mining {$t}", '2050', 'Closed', 'x'],
            ["GB {$t}/1", '', 'Series', 'Correspondence', 'undated', 'Admin text', 'Custody text', '', '', '', '', '', '', '', ''],
            ["GB {$t}", '', 'Fonds', 'Papers', '1880-1910', '', '', '', '', '', '', '', '', '', ''],
        ]);
    }

    private function ioFor(int $run, string $ref): int
    {
        return (int) DB::table('ahg_source_migration_record')->where('run_id', $run)->where('ref_no', $ref)->value('information_object_id');
    }

    private function assertInTreeUnder(int $ancestor, int $id, int $depth): void
    {
        $this->assertSame($depth, (int) DB::table('information_object_closure')
            ->where('ancestor', $ancestor)->where('descendant', $id)->value('depth'), "Record {$id} is not in the closure at depth {$depth}");
    }

    public function test_the_calm_signature_is_detected(): void
    {
        $this->assertSame('calm', (new SourceDetector)->detect(['refno', 'AltRefNo', 'Level', 'AdminHistory'])['source']);
        $this->assertNull((new SourceDetector)->detect(['Ref', 'Title', 'Level']));
    }

    public function test_a_gappy_export_lands_entirely_in_the_closure(): void
    {
        $run = (new SourceMigrationPreflight)->run($this->gappyExport(), 'calm');
        $runner = new SourceMigrationRunner;
        $runner->acknowledge($run, $this->admin(), ['level_piece' => 'fold_to_item', 'date_policy' => 'keep_both']);
        $this->assertSame(4, $runner->commit($run));

        $t = $this->tag;
        $fonds = $this->ioFor($run, "GB {$t}");
        $series = $this->ioFor($run, "GB {$t}/1");
        $synth = $this->ioFor($run, "GB {$t}/1/2");
        $piece = $this->ioFor($run, "GB {$t}/1/2/3");

        $this->assertInTreeUnder(1, $fonds, 1);
        $this->assertInTreeUnder($fonds, $series, 1);
        $this->assertInTreeUnder($fonds, $synth, 2);
        $this->assertInTreeUnder($fonds, $piece, 3);
        $this->assertInTreeUnder($series, $piece, 2);
        $this->assertEqualsCanonicalizing([$series, $synth, $piece], app(HierarchyQueryService::class)->descendantIds('information_object', $fonds));
        $this->assertSame(1, (int) DB::table('ahg_source_migration_record')->where('run_id', $run)->where('synthesised', 1)->count());
        $this->assertSame($synth, (int) DB::table('information_object')->where('id', $piece)->value('parent_id'));

        // Imported as Draft.
        $this->assertSame(159, (int) DB::table('status')->where('object_id', $piece)->where('type_id', 158)->value('status_id'));

        // Authorities are links, proved by relationship rows.
        $linked = DB::table('relation')->join('actor_i18n', 'actor_i18n.id', '=', 'relation.object_id')
            ->where('relation.subject_id', $piece)->where('relation.type_id', TermId::RELATION_NAME_ACCESS_POINT)
            ->pluck('actor_i18n.authorized_form_of_name')->all();
        $this->assertEqualsCanonicalizing(["Smith, Jane {$t}", "Doe, John {$t}", "Acme Mining {$t}"], $linked);
        $corp = DB::table('actor_i18n')->where('authorized_form_of_name', "Acme Mining {$t}")->value('id');
        $this->assertSame(TermId::ACTOR_ENTITY_CORPORATE_BODY, (int) DB::table('actor')->where('id', $corp)->value('entity_type_id'));

        $event = DB::table('event')->join('event_i18n', 'event_i18n.id', '=', 'event.id')->where('event.object_id', $piece)->first();
        $this->assertSame('circa 1890s', $event->date);
        $this->assertSame('1890-01-01', $event->start_date);
        $this->assertSame('1899-12-31', $event->end_date);
        $this->assertSame("Creator {$t}", DB::table('actor_i18n')->where('id', $event->actor_id)->value('authorized_form_of_name'));

        $this->assertTrue(DB::table('object_term_relation')->join('term', 'term.id', '=', 'object_term_relation.term_id')
            ->join('term_i18n', 'term_i18n.id', '=', 'term.id')->where('object_term_relation.object_id', $piece)
            ->where('term.taxonomy_id', 42)->where('term_i18n.name', "Durban {$t}")->exists());

        // Piece folded by decision, with the source level kept.
        $this->assertSame('Item', DB::table('term_i18n')->where('culture', 'en')
            ->where('id', DB::table('information_object')->where('id', $piece)->value('level_of_description_id'))->value('name'));
        $this->assertSame('Piece', DB::table('property')->join('property_i18n', 'property_i18n.id', '=', 'property.id')
            ->where('property.object_id', $piece)->where('property.name', 'sourceLevelOfDescription')->value('property_i18n.value'));
    }

    public function test_the_register_names_every_loss_and_both_decisions(): void
    {
        $before = DB::table('information_object')->count();
        $run = (new SourceMigrationPreflight)->run($this->gappyExport(), 'calm');
        $this->assertSame($before, DB::table('information_object')->count(), 'Preflight wrote to the catalogue');

        $register = json_decode(DB::table('ahg_source_migration_run')->where('id', $run)->value('register_json'), true);
        $fields = array_column($register['fields'], null, 'field');
        $this->assertSame('dropped', $fields['LocalField']['action']);
        $this->assertSame('flattened', $fields['Level']['action']);
        $this->assertSame('flattened', $fields['ClosedUntil']['action']);
        $this->assertSame('flattened', $fields['AdminHistory']['action']);
        $this->assertSame('folded', $fields['CustodialHistory']['action']);
        $this->assertSame(["GB {$this->tag}/1/2"], $register['synthesised_levels']);
        $this->assertEqualsCanonicalizing(['level_piece', 'date_policy'], array_column($register['open_decisions'], 'key'));
        foreach ($register['open_decisions'] as $decision) {
            $this->assertSame('open', $decision['status']);
        }
        $this->assertSame(1, $register['dates']['display_only']);
    }

    public function test_commit_fails_closed_without_acknowledgement(): void
    {
        $before = DB::table('information_object')->count();
        $run = (new SourceMigrationPreflight)->run($this->gappyExport(), 'calm');
        try {
            (new SourceMigrationRunner)->commit($run);
            $this->fail('Commit ran without acknowledgement.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not been acknowledged', $e->getMessage());
        }
        $this->assertSame($before, DB::table('information_object')->count());
    }

    public function test_acknowledgement_needs_every_decision_and_the_import_right(): void
    {
        $run = (new SourceMigrationPreflight)->run($this->gappyExport(), 'calm');
        $runner = new SourceMigrationRunner;
        foreach ([
            [$this->admin(), ['date_policy' => 'keep_both']],
            [$this->admin(), ['level_piece' => 'invented', 'date_policy' => 'keep_both']],
            [$this->admin(false), ['level_piece' => 'fold_to_item', 'date_policy' => 'keep_both']],
        ] as [$user, $decisions]) {
            try {
                $runner->acknowledge($run, $user, $decisions);
                $this->fail('Acknowledgement should have been refused.');
            } catch (\RuntimeException $e) {
                $this->assertSame('preflighted', DB::table('ahg_source_migration_run')->where('id', $run)->value('status'));
            }
        }
    }

    public function test_a_file_changed_after_acknowledgement_is_refused(): void
    {
        $file = $this->gappyExport();
        $run = (new SourceMigrationPreflight)->run($file, 'calm');
        $runner = new SourceMigrationRunner;
        $runner->acknowledge($run, $this->admin(), ['level_piece' => 'fold_to_item', 'date_policy' => 'keep_both']);
        file_put_contents($file, "GB {$this->tag}/9,,Item,Swapped,,,,,,,,,,,\n", FILE_APPEND);
        $before = DB::table('information_object')->count();

        $this->expectException(\RuntimeException::class);
        try {
            $runner->commit($run);
        } finally {
            $this->assertSame($before, DB::table('information_object')->count());
        }
    }

    public function test_cyclic_and_self_referencing_sets_are_rejected_before_any_write(): void
    {
        $t = $this->tag;
        foreach ([
            [["{$t}/A", "{$t}/B"], ["{$t}/B", "{$t}/A"]],
            [["{$t}/A", "{$t}/A"]],
        ] as $pairs) {
            $rows = [['RefNo', 'AltRefNo', 'ParentRefNo', 'Level', 'Title']];
            foreach ($pairs as [$ref, $parent]) {
                $rows[] = [$ref, '', $parent, 'File', 'x'];
            }
            $before = DB::table('information_object')->count();
            $run = (new SourceMigrationPreflight)->run($this->csv($rows), 'calm');
            $row = DB::table('ahg_source_migration_run')->where('id', $run)->first();
            $this->assertSame('rejected', $row->status);
            $this->assertGreaterThan(0, $row->blocker_count);
            $this->assertSame($before, DB::table('information_object')->count());
            try {
                (new SourceMigrationRunner)->acknowledge($run, $this->admin(), []);
                $this->fail('A rejected run was acknowledged.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('rejected', $e->getMessage());
            }
        }
    }

    public function test_an_ambiguous_authority_blocks_the_run(): void
    {
        $name = "Twin {$this->tag}";
        foreach ([1, 2] as $n) {
            (new \AhgActorManage\Services\ActorService('en'))->create(['authorized_form_of_name' => $name, 'entity_type_id' => TermId::ACTOR_ENTITY_PERSON]);
        }
        $run = (new SourceMigrationPreflight)->run($this->csv([
            ['RefNo', 'AltRefNo', 'Level', 'Title', 'PersonName'],
            ["{$this->tag}", '', 'Fonds', 'x', $name],
        ]), 'calm');
        $register = json_decode(DB::table('ahg_source_migration_run')->where('id', $run)->value('register_json'), true);
        $this->assertContains('ambiguous_authority', array_column($register['blockers'], 'code'));
    }

    public function test_the_same_references_cannot_be_imported_twice(): void
    {
        $rows = [['RefNo', 'AltRefNo', 'Level', 'Title'], ["{$this->tag}", '', 'Fonds', 'Once']];
        $runner = new SourceMigrationRunner;
        $first = (new SourceMigrationPreflight)->run($this->csv($rows), 'calm');
        $runner->acknowledge($first, $this->admin(), []);
        $runner->commit($first);

        $second = (new SourceMigrationPreflight)->run($this->csv($rows), 'calm');
        $this->assertSame('rejected', DB::table('ahg_source_migration_run')->where('id', $second)->value('status'));
    }

    public function test_the_other_decision_answers_are_honoured(): void
    {
        $run = (new SourceMigrationPreflight)->run($this->gappyExport(), 'calm');
        $runner = new SourceMigrationRunner;
        $runner->acknowledge($run, $this->admin(), ['level_piece' => 'create_piece_term', 'date_policy' => 'display_only']);
        $runner->commit($run);

        $piece = $this->ioFor($run, "GB {$this->tag}/1/2/3");
        $level = DB::table('information_object')->where('id', $piece)->value('level_of_description_id');
        $this->assertSame('Piece', DB::table('term_i18n')->where('id', $level)->where('culture', 'en')->value('name'));
        $this->assertSame(34, (int) DB::table('term')->where('id', $level)->value('taxonomy_id'));
        $event = DB::table('event')->join('event_i18n', 'event_i18n.id', '=', 'event.id')->where('event.object_id', $piece)->first();
        $this->assertSame('circa 1890s', $event->date);
        $this->assertNull($event->start_date);
    }

    public function test_the_command_runs_the_whole_flow(): void
    {
        $file = $this->gappyExport();
        $this->artisan('ahg:source-migrate', ['action' => 'detect', '--file' => $file])->expectsOutputToContain('Axiell Calm')->assertSuccessful();
        $this->artisan('ahg:source-migrate', ['action' => 'preflight', '--file' => $file])->assertSuccessful();
        $run = (int) DB::table('ahg_source_migration_run')->max('id');
        $this->artisan('ahg:source-migrate', ['action' => 'commit', '--run' => $run])->assertFailed();
        $this->artisan('ahg:source-migrate', ['action' => 'acknowledge', '--run' => $run, '--user' => $this->admin(),
            '--decide' => ['level_piece=fold_to_item', 'date_policy=keep_both']])->assertSuccessful();
        $this->artisan('ahg:source-migrate', ['action' => 'commit', '--run' => $run])->assertSuccessful();
        $this->assertInTreeUnder(1, $this->ioFor($run, "GB {$this->tag}/1/2/3"), 4);
    }

    private function register(int $run): array
    {
        return json_decode(DB::table('ahg_source_migration_run')->where('id', $run)->value('register_json'), true);
    }

    private function blockerCodes(int $run): array
    {
        return array_column($this->register($run)['blockers'], 'code');
    }

    private function nameRun(array $columns): int
    {
        return (new SourceMigrationPreflight)->run($this->csv([
            array_merge(['RefNo', 'AltRefNo', 'Level', 'Title'], array_keys($columns)),
            array_merge([$this->tag, '', 'Fonds', 'x'], array_values($columns)),
        ]), 'calm');
    }

    private function repository(): array
    {
        $slug = 'repo-'.strtolower($this->tag).bin2hex(random_bytes(2));
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitRepository', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('repository')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);

        return [$id, $slug];
    }

    private function grantImport(int $user, int $grant = 1, ?string $repositorySlug = null): void
    {
        DB::table('acl_permission')->insert(['user_id' => $user, 'action' => 'import', 'grant_deny' => $grant,
            'constants' => $repositorySlug === null ? null : json_encode(['repository' => $repositorySlug]),
            'created_at' => now(), 'updated_at' => now()]);
        Cache::forget("acl_repo_rules_{$user}");
    }

    public function test_an_accent_variant_of_an_existing_authority_blocks_and_is_not_linked(): void
    {
        (new \AhgActorManage\Services\ActorService('en'))->create(['authorized_form_of_name' => "Müller {$this->tag}", 'entity_type_id' => TermId::ACTOR_ENTITY_PERSON]);

        $this->assertContains('ambiguous_authority', $this->blockerCodes($this->nameRun(['PersonName' => "Muller {$this->tag}"])));
        $this->assertNotContains('ambiguous_authority', $this->blockerCodes($this->nameRun(['PersonName' => "MÜLLER {$this->tag}"])));
    }

    public function test_two_accent_spellings_in_one_file_block(): void
    {
        $run = $this->nameRun(['PersonName' => "Muller {$this->tag}; Müller {$this->tag}"]);
        $this->assertContains('ambiguous_authority', $this->blockerCodes($run));
    }

    public function test_a_row_wider_than_the_headings_blocks_the_run(): void
    {
        $run = (new SourceMigrationPreflight)->run($this->csv([
            ['RefNo', 'AltRefNo', 'Level', 'Title'],
            [$this->tag, '', 'Fonds', 'Smith', 'Jane'],
        ]), 'calm');
        $this->assertContains('malformed_row', $this->blockerCodes($run));
        $this->assertSame('rejected', DB::table('ahg_source_migration_run')->where('id', $run)->value('status'));
    }

    public function test_a_short_row_is_counted_and_does_not_block(): void
    {
        $run = (new SourceMigrationPreflight)->run($this->csv([
            ['RefNo', 'AltRefNo', 'Level', 'Title', 'Notes'],
            [$this->tag, '', 'Fonds', 'Short'],
        ]), 'calm');
        $this->assertSame([], $this->blockerCodes($run));
        $this->assertSame(1, $this->register($run)['short_rows']);
    }

    public function test_a_name_used_as_person_and_corporate_body_blocks(): void
    {
        $name = "Acme {$this->tag}";
        $run = $this->nameRun(['CreatorName' => $name, 'PersonName' => $name, 'CorpName' => $name]);
        $this->assertContains('authority_type_conflict', $this->blockerCodes($run));
    }

    public function test_a_creator_takes_the_type_of_its_typed_column(): void
    {
        $name = "Acme {$this->tag}";
        $run = $this->nameRun(['CreatorName' => $name, 'CorpName' => $name]);
        $this->assertSame([], $this->blockerCodes($run));
        $runner = new SourceMigrationRunner;
        $runner->acknowledge($run, $this->admin(), []);
        $runner->commit($run);

        $ids = DB::table('actor_i18n')->where('authorized_form_of_name', $name)->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertSame(TermId::ACTOR_ENTITY_CORPORATE_BODY, (int) DB::table('actor')->where('id', $ids[0])->value('entity_type_id'));
        $io = $this->ioFor($run, $this->tag);
        $this->assertSame((int) $ids[0], (int) DB::table('event')->where('object_id', $io)->value('actor_id'));
    }

    public function test_a_non_administrator_with_the_import_grant_may_acknowledge(): void
    {
        $user = $this->admin(false);
        $this->grantImport($user);
        $run = $this->nameRun([]);
        (new SourceMigrationRunner)->acknowledge($run, $user, []);
        $this->assertSame('acknowledged', DB::table('ahg_source_migration_run')->where('id', $run)->value('status'));
    }

    public function test_a_repository_deny_refuses_acknowledgement(): void
    {
        [$repository, $slug] = $this->repository();
        $user = $this->admin(false);
        $this->grantImport($user);
        $this->grantImport($user, 0, $slug);
        $run = (new SourceMigrationPreflight)->run($this->csv([
            ['RefNo', 'AltRefNo', 'Level', 'Title'], [$this->tag, '', 'Fonds', 'x'],
        ]), 'calm', ['repository_id' => $repository]);
        $this->assertSame('preflighted', DB::table('ahg_source_migration_run')->where('id', $run)->value('status'));

        try {
            (new SourceMigrationRunner)->acknowledge($run, $user, []);
            $this->fail('A repository deny was ignored.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('may not acknowledge', $e->getMessage());
        }
    }

    public function test_a_failed_status_write_rolls_the_records_back(): void
    {
        $run = $this->nameRun([]);
        $runner = new SourceMigrationRunner;
        $runner->acknowledge($run, $this->admin(), []);
        $before = DB::table('information_object')->count();

        DB::listen(function ($query) {
            if (str_starts_with($query->sql, 'update `ahg_source_migration_run`') && in_array('committed', $query->bindings, true)) {
                throw new \RuntimeException('Simulated failure at the status write.');
            }
        });
        try {
            $runner->commit($run);
            $this->fail('Commit should have failed.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Simulated failure', $e->getMessage());
        }
        $this->assertSame($before, DB::table('information_object')->count());
        $this->assertSame(0, DB::table('ahg_source_migration_record')->where('run_id', $run)->count());
        $this->assertSame('failed', DB::table('ahg_source_migration_run')->where('id', $run)->value('status'));
    }

    public function test_other_record_types_are_named_not_imported(): void
    {
        $run = (new SourceMigrationPreflight)->run($this->csv([
            ['RefNo', 'AltRefNo', 'RecordType', 'Level', 'Title'],
            ["{$this->tag}", '', 'Archive', 'Fonds', 'Kept'],
            ["{$this->tag}/P", '', 'Persons', '', 'Not a description'],
        ]), 'calm');
        $register = json_decode(DB::table('ahg_source_migration_run')->where('id', $run)->value('register_json'), true);
        $this->assertSame(1, $register['records_to_create']);
        $this->assertSame(['Persons' => 1], $register['excluded_record_types']);
    }
}
