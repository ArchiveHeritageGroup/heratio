<?php

/**
 * AccessionEventsAndContactsTest - heratio#1520 and heratio#1521.
 *
 * #1520: the accession form's Event(s) table was never saved. store() and
 * update() neither validated nor persisted events[], so whatever was typed was
 * discarded. It now saves the way the AtoM accession events component does.
 *
 * #1521: the accession page read donor contacts with a raw query, so the email
 * and city - encrypted at rest - showed as ciphertext. It now reads them
 * through DonorService::getContacts(), which decrypts.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Tests\Feature;

use AhgAccessionManage\Services\AccessionService;
use AhgCore\Constants\TermId;
use AhgCore\Models\User;
use AhgCore\Services\EncryptionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessionEventsAndContactsTest extends TestCase
{
    use DatabaseTransactions;

    private AccessionService $svc;

    private int $eventType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new AccessionService('en');
        $this->eventType = (int) DB::table('term')->where('taxonomy_id', 83)->value('id');
        if ($this->eventType === 0) {
            $this->markTestSkipped('no accession event type (taxonomy 83) seeded');
        }
    }

    private function accession(): int
    {
        return $this->svc->create(['identifier' => 'ZZ-EVT-'.Str::random(8), 'title' => 'ZZ events test']);
    }

    private function events(int $accessionId): array
    {
        return $this->svc->getAccessionEvents($accessionId)->map(fn ($e) => [
            'type' => (int) $e->type_id, 'date' => $e->date, 'agent' => $e->agent, 'note' => $e->note,
        ])->sortBy('date')->values()->all();
    }

    public function test_events_are_saved_with_agent_and_note_and_incomplete_rows_skipped(): void
    {
        $id = $this->accession();

        $this->svc->saveAccessionEvents($id, [
            ['eventType' => $this->eventType, 'date' => '2026-09-02', 'agent' => 'Registrar', 'note' => 'Boxes 1-4 received'],
            ['eventType' => $this->eventType, 'date' => '', 'agent' => 'No date, skipped'],
            ['eventType' => '', 'date' => '2026-09-03', 'agent' => 'No type, skipped'],
        ]);

        $this->assertSame([
            ['type' => $this->eventType, 'date' => '2026-09-02', 'agent' => 'Registrar', 'note' => 'Boxes 1-4 received'],
        ], $this->events($id));
        $eventId = (int) DB::table('accession_event')->where('accession_id', $id)->value('id');
        $this->assertSame('QubitAccessionEvent', DB::table('object')->where('id', $eventId)->value('class_name'));
    }

    public function test_resubmitting_updates_in_place_and_omitted_events_are_deleted_with_their_note(): void
    {
        $id = $this->accession();
        $this->svc->saveAccessionEvents($id, [
            ['eventType' => $this->eventType, 'date' => '2026-09-02', 'agent' => 'A', 'note' => 'first'],
            ['eventType' => $this->eventType, 'date' => '2026-09-05', 'agent' => 'B', 'note' => 'second'],
        ]);
        $ids = DB::table('accession_event')->where('accession_id', $id)->orderBy('date')->pluck('id')->all();

        // Keep the first (edited, note cleared), drop the second, add a third.
        $this->svc->saveAccessionEvents($id, [
            ['id' => $ids[0], 'eventType' => $this->eventType, 'date' => '2026-09-02', 'agent' => 'A edited', 'note' => ''],
            ['eventType' => $this->eventType, 'date' => '2026-09-09', 'agent' => 'C', 'note' => 'third'],
        ]);

        $this->assertSame([
            ['type' => $this->eventType, 'date' => '2026-09-02', 'agent' => 'A edited', 'note' => null],
            ['type' => $this->eventType, 'date' => '2026-09-09', 'agent' => 'C', 'note' => 'third'],
        ], $this->events($id));
        $this->assertTrue(DB::table('accession_event')->where('id', $ids[0])->exists(), 'the kept event was recreated, not updated');
        $this->assertFalse(DB::table('object')->where('id', $ids[1])->exists());
        $this->assertFalse(DB::table('note')->where('object_id', $ids[1])->where('type_id', TermId::ACCESSION_EVENT_NOTE)->exists());
    }

    public function test_an_id_from_another_accession_cannot_be_hijacked(): void
    {
        $mine = $this->accession();
        $theirs = $this->accession();
        $this->svc->saveAccessionEvents($theirs, [['eventType' => $this->eventType, 'date' => '2026-01-01', 'agent' => 'Theirs']]);
        $theirEvent = (int) DB::table('accession_event')->where('accession_id', $theirs)->value('id');

        $this->svc->saveAccessionEvents($mine, [['id' => $theirEvent, 'eventType' => $this->eventType, 'date' => '2026-02-02', 'agent' => 'Mine']]);

        $this->assertSame('Theirs', $this->events($theirs)[0]['agent']);
        $this->assertSame('Mine', $this->events($mine)[0]['agent']);
    }

    public function test_the_form_saves_events_and_shows_them_again_on_edit(): void
    {
        $id = $this->accession();
        $slug = $this->svc->getSlug($id);
        $identifier = DB::table('accession')->where('id', $id)->value('identifier');
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('accession.update', $slug), [
            'identifier' => $identifier,
            'events' => [['eventType' => $this->eventType, 'date' => '2026-09-02', 'agent' => 'Via the form', 'note' => 'Form note']],
        ])->assertRedirect(route('accession.show', $slug));

        $this->assertSame('Via the form', $this->events($id)[0]['agent'] ?? null);
        $eventId = DB::table('accession_event')->where('accession_id', $id)->value('id');
        $this->actingAs($admin)->get(route('accession.edit', $slug))
            ->assertOk()
            ->assertSee('name="events[0][id]" value="'.$eventId.'"', false)
            ->assertSee('Via the form');
    }

    public function test_donor_contact_email_and_city_are_decrypted(): void
    {
        $donorId = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDonor', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $donorId, 'source_culture' => 'en']);
        $contactId = (int) DB::table('contact_information')->insertGetId([
            'actor_id' => $donorId, 'source_culture' => 'en', 'created_at' => now(), 'updated_at' => now(),
            'email' => EncryptionService::SENTINEL.Crypt::encryptString('donor@example.org'),
        ]);
        DB::table('contact_information_i18n')->insert([
            'id' => $contactId, 'culture' => 'en', 'city' => EncryptionService::SENTINEL.Crypt::encryptString('Pretoria'),
        ]);

        $contact = $this->svc->getDonorContacts($donorId)->first();

        $this->assertSame('donor@example.org', $contact->email);
        $this->assertSame('Pretoria', $contact->city);
    }

    private function makeAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id, 'username' => 'evt-admin-'.$id, 'email' => uniqid('evt-', true).'@example.test',
            'password_hash' => Hash::make('secret'), 'active' => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }
}
