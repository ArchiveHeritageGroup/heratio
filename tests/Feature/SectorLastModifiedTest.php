<?php

/**
 * SectorLastModifiedTest - library and DAM saves move "last modified" only on
 * a real change (heratio#1549).
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

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SectorLastModifiedTest extends TestCase
{
    use DatabaseTransactions;

    private const LONG_AGO = '2000-01-01 00:00:00';

    private function slugAndAge(int $id): string
    {
        DB::table('object')->where('id', $id)->update(['updated_at' => self::LONG_AGO]);

        return (string) DB::table('slug')->where('object_id', $id)->value('slug');
    }

    private function modified(int $id): string
    {
        return (string) DB::table('object')->where('id', $id)->value('updated_at');
    }

    public function test_library_item(): void
    {
        $svc = new \AhgLibrary\Services\LibraryService('en');
        $data = ['title' => 'ZZ Library item', 'identifier' => 'ZZ-LIB-1', 'subtitle' => 'A subtitle', 'publisher' => 'ZZ Press'];
        $id = $svc->create($data);
        $slug = $this->slugAndAge($id);

        $svc->update($slug, $data);
        $this->assertSame(self::LONG_AGO, $this->modified($id), 'an unchanged library save moved last modified');

        $svc->update($slug, ['subtitle' => 'A new subtitle'] + $data);
        $this->assertNotSame(self::LONG_AGO, $this->modified($id), 'a library-only field change was not seen');
    }

    public function test_dam_asset(): void
    {
        $svc = new \AhgDam\Services\DamService('en');
        $data = ['title' => 'ZZ DAM asset', 'identifier' => 'ZZ-DAM-1', 'creator' => 'ZZ Photographer'];
        $id = $svc->create($data);
        $slug = $this->slugAndAge($id);

        $svc->update($slug, $data);
        $this->assertSame(self::LONG_AGO, $this->modified($id), 'an unchanged DAM save moved last modified');

        $svc->update($slug, ['creator' => 'ZZ Another Photographer'] + $data);
        $this->assertNotSame(self::LONG_AGO, $this->modified($id), 'an IPTC-only change was not seen');
    }
}
