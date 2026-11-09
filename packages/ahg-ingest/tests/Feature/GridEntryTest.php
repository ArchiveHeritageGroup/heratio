<?php

/**
 * GridEntryTest - grid entry (heratio#1581): rows typed or pasted under one
 * parent are checked, staged as an ingest session and created by the ingest
 * commit, with their creation dates.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Constants\TermId;
use AhgCore\Models\User;
use AhgIngest\Services\GridEntryService;
use AhgIngest\Services\IngestCommitRunner;
use AhgIngest\Services\IngestService;
use AhgInformationObjectManage\Services\InformationObjectService as Io;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GridEntryTest extends TestCase
{
    use DatabaseTransactions;

    private function grid(): GridEntryService
    {
        return new GridEntryService(new IngestService());
    }

    private function row(array $v): array
    {
        return $v + array_fill_keys(array_keys(GridEntryService::COLUMNS), '');
    }

    public function test_rows_are_cleaned_and_checked_as_on_the_atom_side(): void
    {
        $grid = $this->grid();
        $levels = $grid->levelNames();
        $this->assertNotEmpty($levels, 'level of description terms are seeded');
        $file = $levels[array_key_last($levels)];

        $rows = $grid->normalise([
            ['title' => '  Letter  ', 'levelOfDescription' => mb_strtoupper($file), 'creationDatesStart' => '1954', 'creationDatesEnd' => '1955-03'],
            ['title' => '', 'identifier' => '', 'scopeAndContent' => ''], // empty: dropped
            ['title' => '', 'identifier' => 'X1'],
            ['title' => 'Bad', 'levelOfDescription' => 'Not a level', 'creationDatesStart' => '1954-13', 'creationDatesEnd' => '1950'],
            ['title' => 'Backwards', 'creationDatesStart' => '1960', 'creationDatesEnd' => '1950'],
        ]);
        $this->assertSame([0, 2, 3, 4], array_keys($rows), 'the grid position is kept; empty rows go');
        $this->assertSame('Letter', $rows[0]['title']);
        $this->assertSame('1954 - 1955-03', $rows[0]['creationDates'], 'display date derived from start and end');

        $errors = $grid->validate($rows, $levels);
        $this->assertSame($file, $rows[0]['levelOfDescription'], 'level matched without regard to case');
        $this->assertArrayNotHasKey(0, $errors);
        $this->assertArrayHasKey('title', $errors[2]);
        $this->assertSame(['levelOfDescription', 'creationDatesStart'], array_keys($errors[3]));
        $this->assertArrayHasKey('creationDatesEnd', $errors[4], 'end before start');

        $this->assertSame('1954-12-31', GridEntryService::fullDate('1954', true));
        $this->assertSame('1955-02-28', GridEntryService::fullDate('1955-02', true));
        $this->assertSame('1955-02-01', GridEntryService::fullDate('1955-2', false));
        $this->assertNull(GridEntryService::fullDate('c. 1950', false));
    }

    public function test_saving_stages_an_ingest_session_that_commits_the_records_with_their_dates(): void
    {
        $parent = Io::create(['title' => 'ZZ Grid parent', 'identifier' => 'ZZG'], 'en');
        $admin = $this->makeAdmin();
        $rows = [
            $this->row(['title' => 'Société letter', 'identifier' => 'ZZG/1', 'creationDatesStart' => '1954', 'creationDatesEnd' => '1955']),
            $this->row(['title' => 'Second item', 'identifier' => 'ZZG/2', 'creationDates' => 'about 1960']),
        ];

        // A row without a title is refused before anything is written.
        $this->actingAs($admin)->postJson(route('ingest.grid.save'), ['parent_id' => $parent, 'rows' => [$this->row(['identifier' => 'X'])]])
            ->assertStatus(422)->assertJsonPath('errors.0.title', __('Title is required.'));

        $res = $this->actingAs($admin)->postJson(route('ingest.grid.save'), ['parent_id' => $parent, 'rows' => $rows])->assertOk();
        $sessionId = (int) $res->json('session_id');
        $this->assertSame(route('ingest.commit', $sessionId), $res->json('commit_url'));
        $session = DB::table('ingest_session')->where('id', $sessionId)->first();
        $this->assertSame([$parent, 'commit'], [(int) $session->parent_id, $session->status]);
        $this->assertSame(2, DB::table('ingest_row')->where('session_id', $sessionId)->count());

        // Typed rows have no files: nothing to package or process, so no errors.
        $this->assertSame([0, 0, 0, 0, 0], [(int) $session->output_generate_sip, (int) $session->output_generate_aip,
            (int) $session->output_generate_dip, (int) $session->process_ner, (int) $session->process_summarize]);
        $this->assertSame(0, app(IngestCommitRunner::class)->run($sessionId)['errors']);

        $kids = DB::table('information_object as io')->join('information_object_i18n as i', fn ($j) => $j->on('i.id', '=', 'io.id')->where('i.culture', '=', 'en'))
            ->where('io.parent_id', $parent)->pluck('i.title', 'io.identifier')->all();
        $this->assertSame(['ZZG/1' => 'Société letter', 'ZZG/2' => 'Second item'], $kids);

        $event = fn (string $ident) => DB::table('event as e')->join('information_object as io', 'io.id', '=', 'e.object_id')
            ->leftJoin('event_i18n as ei', 'ei.id', '=', 'e.id')->where('io.identifier', $ident)->where('io.parent_id', $parent)
            ->where('e.type_id', TermId::EVENT_TYPE_CREATION)->first(['e.start_date', 'e.end_date', 'ei.date']);
        $first = $event('ZZG/1');
        $this->assertSame(['1954-01-01', '1955-12-31', '1954 - 1955'], [(string) $first->start_date, (string) $first->end_date, $first->date]);
        $second = $event('ZZG/2');
        $this->assertSame([null, null, 'about 1960'], [$second->start_date, $second->end_date, $second->date]);

        // The page lists them beside the grid.
        $this->actingAs($admin)->get(route('ingest.grid', ['parent' => $parent]))->assertOk()->assertSee('Société letter');
    }

    private function makeAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id, 'username' => 'grid-admin-'.$id, 'email' => uniqid('grid-', true).'@example.test',
            'password_hash' => Hash::make('secret'), 'active' => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }
}
