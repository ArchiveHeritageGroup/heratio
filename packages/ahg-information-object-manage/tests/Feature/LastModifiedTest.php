<?php

/**
 * LastModifiedTest - "last modified" moves only when a description changes.
 *
 * heratio#1536: object.updated_at was set on every save, so an unchanged save
 * still bumped the date OAI-PMH datestamps and harvest deltas are built from.
 * It is now moved only when InformationObjectService::contentFingerprint()
 * differs before and after the save.
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

use AhgInformationObjectManage\Services\InformationObjectService as Io;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LastModifiedTest extends TestCase
{
    use DatabaseTransactions;

    private const LONG_AGO = '2000-01-01 00:00:00';

    private int $id;

    /**
     * auditSnapshot() reads information_object.icip_sensitivity, which the ICIP
     * install adds on live instances but heratio_test may lack. Add it before
     * DatabaseTransactions opens (DDL would commit the transaction), matching
     * the live definition.
     */
    protected function setUpTraits()
    {
        if (! Schema::hasColumn('information_object', 'icip_sensitivity')) {
            Schema::table('information_object', fn ($t) => $t->string('icip_sensitivity', 512)->nullable());
        }

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->id = Io::create(['title' => 'ZZ last-modified test', 'scope_and_content' => 'Original scope.'], 'en');
        DB::table('object')->where('id', $this->id)->update(['updated_at' => self::LONG_AGO]);
    }

    private function lastModified(): string
    {
        return (string) DB::table('object')->where('id', $this->id)->value('updated_at');
    }

    public function test_saving_unchanged_content_leaves_last_modified_alone(): void
    {
        Io::update($this->id, ['title' => 'ZZ last-modified test', 'scope_and_content' => 'Original scope.'], 'en');

        $this->assertSame(self::LONG_AGO, $this->lastModified());
    }

    public function test_a_real_change_moves_last_modified(): void
    {
        Io::update($this->id, ['scope_and_content' => 'Edited scope.'], 'en');

        $this->assertNotSame(self::LONG_AGO, $this->lastModified());
    }

    public function test_reinserting_identical_sub_entities_is_not_a_change(): void
    {
        // The edit form deletes and re-inserts events on every save, so ids
        // change while content does not.
        $makeEvent = function (string $date) {
            $eid = (int) DB::table('object')->insertGetId(['class_name' => 'QubitEvent', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('event')->insert(['id' => $eid, 'object_id' => $this->id, 'type_id' => 111, 'start_date' => $date, 'source_culture' => 'en']);
            DB::table('event_i18n')->insert(['id' => $eid, 'culture' => 'en', 'date' => $date]);

            return $eid;
        };
        $old = $makeEvent('1922-02-06');
        $before = Io::contentFingerprint($this->id);

        DB::table('event_i18n')->where('id', $old)->delete();
        DB::table('event')->where('id', $old)->delete();
        $makeEvent('1922-02-06');
        $this->assertFalse(Io::touchIfChanged($this->id, $before), 'same event under a new id counted as a change');
        $this->assertSame(self::LONG_AGO, $this->lastModified());

        DB::table('event')->where('object_id', $this->id)->update(['start_date' => '1923-01-01']);
        $this->assertTrue(Io::touchIfChanged($this->id, $before), 'an edited event date was not seen');
        $this->assertNotSame(self::LONG_AGO, $this->lastModified());
    }
}
