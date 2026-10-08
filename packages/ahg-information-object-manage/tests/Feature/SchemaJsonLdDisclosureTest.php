<?php

/**
 * SchemaJsonLdDisclosureTest - Schema.org JSON-LD only for public records.
 *
 * The JSON-LD injector added a description's title and scope and content to
 * any HTML response whose last path segment was its slug, including the 404 a
 * guest gets for a draft. It now injects only into served (200) pages, and only
 * for a published description the DisclosureGate releases.
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
use Tests\TestCase;

class SchemaJsonLdDisclosureTest extends TestCase
{
    use DatabaseTransactions;

    private function record(int $status): string
    {
        $id = Io::create(['title' => 'ZZ JsonLd secret title', 'scope_and_content' => 'ZZ secret scope'], 'en');
        \AhgCore\Support\StatusRow::set($id, 158, $status);

        return (string) DB::table('slug')->where('object_id', $id)->value('slug');
    }

    public function test_a_guest_404_for_a_draft_carries_no_record_data(): void
    {
        $html = $this->get('/'.$this->record(159))->assertNotFound()->getContent();

        $this->assertStringNotContainsString('ZZ JsonLd secret title', $html);
        $this->assertStringNotContainsString('ArchiveComponent', $html);
    }

    public function test_a_published_record_page_keeps_its_schema_markup(): void
    {
        $this->get('/'.$this->record(160))->assertOk()->assertSee('"ArchiveComponent"', false);
    }
}
