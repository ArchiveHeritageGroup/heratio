<?php

/**
 * DonorServiceTest - #1259 behavioural coverage for the donor CRUD service.
 *
 * Exercises the class-table-inheritance write shape (object/QubitDonor +
 * actor[parent_id=3] + donor + actor_i18n + slug), slug dedupe, update +
 * serial bump, contact create/sync round-trips, donor<->IO relation sync,
 * related accessions/IO reads, the #1257 typeahead search, and the full
 * delete cascade.
 *
 * Runs against the pre-built heratio_test DB and rolls back each test
 * (DatabaseTransactions, NOT RefreshDatabase - the ~995 AtoM base tables this
 * exercises must survive). See #1136. Encryption coverage lives in
 * DonorEncryptionTest (#1261) and is NOT duplicated here.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgCore\Constants\TermId;
use AhgCore\Services\AhgSettingsService;
use AhgDonorManage\Services\DonorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonorServiceTest extends TestCase
{
    use DatabaseTransactions;

    private DonorService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new DonorService('en');
        // Keep PII plaintext for the non-encryption assertions in this suite
        // (encryption round-trip is covered by DonorEncryptionTest).
        AhgSettingsService::set('encryption_enabled', '0', 'encryption');
        AhgSettingsService::set('encryption_field_contact_details', '0', 'encryption');
        AhgSettingsService::clearCache();
    }

    private function uniqueName(string $prefix): string
    {
        return $prefix.' '.Str::random(8);
    }

    public function test_create_writes_full_cti_shape_and_returns_int_id(): void
    {
        $name = $this->uniqueName('CTI Donor');
        $id = $this->svc->create(['authorized_form_of_name' => $name]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        // object row is a QubitDonor
        $object = DB::table('object')->where('id', $id)->first();
        $this->assertNotNull($object);
        $this->assertEquals('QubitDonor', $object->class_name);

        // actor row hangs off the donor root (parent_id = 3)
        $actor = DB::table('actor')->where('id', $id)->first();
        $this->assertNotNull($actor);
        $this->assertEquals(3, (int) $actor->parent_id);
        $this->assertEquals('en', $actor->source_culture);

        // donor leaf row
        $this->assertTrue(DB::table('donor')->where('id', $id)->exists());

        // actor_i18n carries the name in the requested culture
        $i18n = DB::table('actor_i18n')->where('id', $id)->where('culture', 'en')->first();
        $this->assertNotNull($i18n);
        $this->assertEquals($name, $i18n->authorized_form_of_name);

        // slug minted from the name
        $slug = DB::table('slug')->where('object_id', $id)->value('slug');
        $this->assertNotEmpty($slug);
        $this->assertEquals(Str::slug($name), $slug);
    }

    public function test_create_dedupes_slug_with_collision_suffix(): void
    {
        // Force a deterministic base name so the slugs collide.
        $base = 'Collision Donor '.Str::random(6);

        $id1 = $this->svc->create(['authorized_form_of_name' => $base]);
        $id2 = $this->svc->create(['authorized_form_of_name' => $base]);

        $slug1 = $this->svc->getSlug($id1);
        $slug2 = $this->svc->getSlug($id2);

        $this->assertEquals(Str::slug($base), $slug1);
        $this->assertEquals(Str::slug($base).'-1', $slug2);
        $this->assertNotEquals($slug1, $slug2);
    }

    public function test_update_modifies_i18n_and_bumps_serial_number(): void
    {
        $id = $this->svc->create(['authorized_form_of_name' => $this->uniqueName('Update Donor')]);
        $serialBefore = (int) DB::table('object')->where('id', $id)->value('serial_number');

        $newName = $this->uniqueName('Renamed Donor');
        $this->svc->update($id, [
            'authorized_form_of_name' => $newName,
            'history' => 'A revised administrative history.',
        ]);

        $i18n = DB::table('actor_i18n')->where('id', $id)->where('culture', 'en')->first();
        $this->assertEquals($newName, $i18n->authorized_form_of_name);
        $this->assertEquals('A revised administrative history.', $i18n->history);

        $serialAfter = (int) DB::table('object')->where('id', $id)->value('serial_number');
        $this->assertEquals($serialBefore + 1, $serialAfter);
    }

    public function test_create_and_sync_contacts_round_trip(): void
    {
        // Create with one populated contact + one empty (skip-empty) contact.
        $id = $this->svc->create([
            'authorized_form_of_name' => $this->uniqueName('Contact Donor'),
            'contacts' => [
                [
                    'primary_contact' => 1,
                    'contact_person' => 'Alice Archivist',
                    'email' => 'alice@example.test',
                    'telephone' => '+27 12 000 0001',
                    'city' => 'Cape Town',
                ],
                // empty -> skipped
                ['contact_person' => '', 'email' => '', 'city' => ''],
            ],
        ]);

        $contacts = $this->svc->getContacts($id);
        $this->assertCount(1, $contacts, 'empty contact should be skipped on create');
        $first = $contacts->first();
        $this->assertEquals('Alice Archivist', $first->contact_person);
        $this->assertEquals('alice@example.test', $first->email);
        $this->assertEquals('Cape Town', $first->city);

        // Update path: edit the existing contact + delete-flag it in a 2nd call.
        $existingId = $first->id;
        $this->svc->update($id, [
            'authorized_form_of_name' => DB::table('actor_i18n')->where('id', $id)->where('culture', 'en')->value('authorized_form_of_name'),
            'contacts' => [
                [
                    'id' => $existingId,
                    'primary_contact' => 1,
                    'contact_person' => 'Alice Archivist (edited)',
                    'email' => 'alice2@example.test',
                    'city' => 'Durban',
                ],
            ],
        ]);

        $edited = $this->svc->getContacts($id)->first();
        $this->assertEquals('Alice Archivist (edited)', $edited->contact_person);
        $this->assertEquals('alice2@example.test', $edited->email);
        $this->assertEquals('Durban', $edited->city);

        // delete-flag removes the contact row entirely.
        $this->svc->update($id, [
            'authorized_form_of_name' => DB::table('actor_i18n')->where('id', $id)->where('culture', 'en')->value('authorized_form_of_name'),
            'contacts' => [
                ['id' => $existingId, 'delete' => 1],
            ],
        ]);
        $this->assertCount(0, $this->svc->getContacts($id), 'delete-flag should remove the contact');
        $this->assertFalse(DB::table('contact_information')->where('id', $existingId)->exists());
        $this->assertFalse(DB::table('contact_information_i18n')->where('id', $existingId)->exists());
    }

    public function test_sync_information_objects_replaces_donor_io_relations(): void
    {
        $id = $this->svc->create(['authorized_form_of_name' => $this->uniqueName('IO Donor')]);

        // Two real information_object ids to link.
        $ioIds = DB::table('information_object')->where('id', '>', 1)->limit(2)->pluck('id')->toArray();
        if (count($ioIds) < 2) {
            $this->markTestSkipped('Need at least 2 information_object rows in heratio_test.');
        }

        $this->svc->syncInformationObjects($id, $ioIds);
        $linked = $this->svc->getInformationObjects($id);
        $this->assertCount(2, $linked);

        // Every relation is the canonical donor relation type.
        $types = DB::table('relation')->where('object_id', $id)->pluck('type_id')->unique()->all();
        $this->assertEquals([TermId::RELATION_DONOR], array_values($types));

        // Re-sync with a single id replaces (not appends) the relations.
        $this->svc->syncInformationObjects($id, [$ioIds[0]]);
        $relinked = $this->svc->getInformationObjects($id);
        $this->assertCount(1, $relinked);
        $this->assertEquals($ioIds[0], (int) $relinked->first()->id);
    }

    public function test_get_related_accessions_returns_linked_rows(): void
    {
        $id = $this->svc->create(['authorized_form_of_name' => $this->uniqueName('Accession Donor')]);

        $accId = DB::table('accession')->value('id');
        if (! $accId) {
            $this->markTestSkipped('No accession rows in heratio_test.');
        }

        // Wire a donor->accession relation (object_id = donor, subject_id = accession).
        $relObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitRelation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('relation')->insert([
            'id' => $relObjectId,
            'subject_id' => $accId,
            'object_id' => $id,
            'type_id' => TermId::RELATION_DONOR,
            'source_culture' => 'en',
        ]);

        $accessions = $this->svc->getRelatedAccessions($id);
        $this->assertCount(1, $accessions);
        $this->assertEquals($accId, (int) $accessions->first()->id);
    }

    public function test_delete_cascades_all_dependent_rows(): void
    {
        $id = $this->svc->create([
            'authorized_form_of_name' => $this->uniqueName('Delete Donor'),
            'contacts' => [
                ['contact_person' => 'Temp Contact', 'email' => 'temp@example.test', 'city' => 'Bloemfontein'],
            ],
        ]);

        // Link an IO relation too so the cascade has a relation row to clear.
        $ioId = DB::table('information_object')->where('id', '>', 1)->value('id');
        if ($ioId) {
            $this->svc->syncInformationObjects($id, [$ioId]);
        }

        $this->assertTrue(DB::table('donor')->where('id', $id)->exists());
        $contactId = DB::table('contact_information')->where('actor_id', $id)->value('id');
        $this->assertNotNull($contactId);

        $this->svc->delete($id);

        $this->assertFalse(DB::table('object')->where('id', $id)->exists(), 'object should be gone');
        $this->assertFalse(DB::table('actor')->where('id', $id)->exists(), 'actor should be gone');
        $this->assertFalse(DB::table('donor')->where('id', $id)->exists(), 'donor should be gone');
        $this->assertFalse(DB::table('actor_i18n')->where('id', $id)->exists(), 'actor_i18n should be gone');
        $this->assertFalse(DB::table('slug')->where('object_id', $id)->exists(), 'slug should be gone');
        $this->assertFalse(DB::table('contact_information')->where('actor_id', $id)->exists(), 'contacts should be gone');
        if ($contactId) {
            $this->assertFalse(DB::table('contact_information_i18n')->where('id', $contactId)->exists(), 'contact i18n should be gone');
        }
        $this->assertFalse(DB::table('relation')->where('object_id', $id)->exists(), 'donor relations should be gone');
    }

    public function test_search_matches_created_donor_and_rejects_short_terms(): void
    {
        $token = 'Zqx'.Str::random(6); // unlikely to collide with seed data
        $name = $token.' Searchable Donor';
        $id = $this->svc->create(['authorized_form_of_name' => $name]);

        $results = $this->svc->search($token);
        $ids = array_column($results, 'id');
        $this->assertContains($id, $ids, 'search should return the freshly created donor');

        $match = collect($results)->firstWhere('id', $id);
        $this->assertStringContainsString($token, $match['label']);
        $this->assertNotEmpty($match['slug']);

        // < 2 chars returns [] without touching the DB.
        $this->assertSame([], $this->svc->search('a'));
        $this->assertSame([], $this->svc->search(''));
    }
}
