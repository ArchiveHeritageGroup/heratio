<?php

/**
 * AccessionDonorLinkTest - Feature test for Heratio
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
use Database\Factories\AccessionFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * #1267: persist the selected existing donor on accession save.
 *
 * Verifies the donor↔accession link is stored in the SAME representation the
 * read side (AccessionService::getDonors) expects: a relation row with
 * subject_id = accession.id, object_id = donor.id, type_id = RELATION_DONOR,
 * whose id is a parent QubitRelation object row. Runs against the pre-built
 * heratio_test DB and rolls back each test (DatabaseTransactions - NOT
 * RefreshDatabase, which would drop the ~995 base tables).
 */
class AccessionDonorLinkTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Create a minimal but valid donor (object QubitDonor + actor parent_id=3
     * + actor_i18n + slug) and return its id + slug. Mirrors the structure
     * DonorService::search / getBySlug query against.
     */
    private function makeDonor(string $name): array
    {
        $id = DB::table('object')->insertGetId([
            'class_name' => 'QubitDonor',
            'created_at' => now(),
            'updated_at' => now(),
            'serial_number' => 0,
        ]);

        DB::table('actor')->insert([
            'id' => $id,
            'parent_id' => 3,
            'source_culture' => 'en',
        ]);

        DB::table('actor_i18n')->insert([
            'id' => $id,
            'culture' => 'en',
            'authorized_form_of_name' => $name,
        ]);

        $slug = Str::slug($name).'-'.Str::random(5);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);

        return ['id' => $id, 'slug' => $slug];
    }

    public function test_link_donor_creates_relation_in_read_side_representation(): void
    {
        $accession = AccessionFactory::new()->withI18n(['title' => 'Gift '.Str::random(4)])->create();
        $donor = $this->makeDonor('Donor '.Str::random(4));

        $service = new AccessionService('en');

        // Before: no donors linked.
        $this->assertCount(0, $service->getDonors($accession->id));

        $created = $service->linkDonor($accession->id, $donor['id']);
        $this->assertTrue($created);

        // Relation row exists in the exact representation getDonors() reads:
        // subject=accession, object=donor, type=RELATION_DONOR.
        $this->assertDatabaseHas('relation', [
            'subject_id' => $accession->id,
            'object_id' => $donor['id'],
            'type_id' => TermId::RELATION_DONOR,
        ]);

        // The relation.id is a QubitRelation object row.
        $relationId = DB::table('relation')
            ->where('subject_id', $accession->id)
            ->where('object_id', $donor['id'])
            ->where('type_id', TermId::RELATION_DONOR)
            ->value('id');
        $this->assertEquals('QubitRelation', DB::table('object')->where('id', $relationId)->value('class_name'));

        // Read side now surfaces the donor.
        $donors = $service->getDonors($accession->id);
        $this->assertCount(1, $donors);
        $this->assertEquals($donor['id'], $donors->first()->id);
    }

    public function test_link_donor_is_idempotent_no_duplicate_relation_or_actor(): void
    {
        $accession = AccessionFactory::new()->create();
        $donor = $this->makeDonor('Donor '.Str::random(4));
        $service = new AccessionService('en');

        $actorCountBefore = DB::table('actor')->where('id', $donor['id'])->count();

        $this->assertTrue($service->linkDonor($accession->id, $donor['id']));
        // Second link of the same donor must NOT create a second relation row.
        $this->assertFalse($service->linkDonor($accession->id, $donor['id']));

        $relCount = DB::table('relation')
            ->where('subject_id', $accession->id)
            ->where('object_id', $donor['id'])
            ->where('type_id', TermId::RELATION_DONOR)
            ->count();
        $this->assertEquals(1, $relCount, 'No duplicate donor↔accession relation row');

        // No duplicate donor/actor was created.
        $this->assertEquals($actorCountBefore, DB::table('actor')->where('id', $donor['id'])->count());
        $this->assertCount(1, $service->getDonors($accession->id));
    }

    public function test_unlink_donor_removes_relation_but_keeps_actor(): void
    {
        $accession = AccessionFactory::new()->create();
        $donor = $this->makeDonor('Donor '.Str::random(4));
        $service = new AccessionService('en');

        $service->linkDonor($accession->id, $donor['id']);
        $relationId = DB::table('relation')
            ->where('subject_id', $accession->id)
            ->where('object_id', $donor['id'])
            ->value('id');

        $removed = $service->unlinkDonor($accession->id, $donor['id']);
        $this->assertEquals(1, $removed);

        // Relation row + its QubitRelation object row are gone.
        $this->assertDatabaseMissing('relation', [
            'subject_id' => $accession->id,
            'object_id' => $donor['id'],
            'type_id' => TermId::RELATION_DONOR,
        ]);
        $this->assertDatabaseMissing('object', ['id' => $relationId]);

        // The donor actor itself is left intact.
        $this->assertDatabaseHas('actor', ['id' => $donor['id']]);
        $this->assertCount(0, $service->getDonors($accession->id));
    }

    public function test_sync_donors_replaces_link_set(): void
    {
        $accession = AccessionFactory::new()->create();
        $a = $this->makeDonor('Donor A '.Str::random(4));
        $b = $this->makeDonor('Donor B '.Str::random(4));
        $service = new AccessionService('en');

        // Initial link to A only.
        $service->syncDonors($accession->id, [$a['id']]);
        $this->assertEquals([$a['id']], $service->getDonors($accession->id)->pluck('id')->all());

        // Sync to B only: A unlinked, B linked.
        $service->syncDonors($accession->id, [$b['id']]);
        $linked = $service->getDonors($accession->id)->pluck('id')->all();
        $this->assertEquals([$b['id']], $linked);
        $this->assertDatabaseMissing('relation', [
            'subject_id' => $accession->id,
            'object_id' => $a['id'],
            'type_id' => TermId::RELATION_DONOR,
        ]);

        // Sync to empty: all unlinked, actors intact.
        $service->syncDonors($accession->id, []);
        $this->assertCount(0, $service->getDonors($accession->id));
        $this->assertDatabaseHas('actor', ['id' => $b['id']]);
    }
}
