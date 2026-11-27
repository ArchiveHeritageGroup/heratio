<?php

/**
 * CsvImportTermSlugTest - a subject or place term created by a CSV import gets a
 * slug (27 Nov 2026). SectorCsvImporter::findOrCreateTerm() made the term with
 * no slug, so imported subjects and places had no page and were missing from
 * the Subjects and Places lists.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgInformationObjectManage\Services\ArchivesCsvImporter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CsvImportTermSlugTest extends TestCase
{
    use DatabaseTransactions;

    public function test_an_imported_place_term_has_a_slug(): void
    {
        $importer = new class extends ArchivesCsvImporter {
            public function term(string $name, int $taxonomy): ?int
            {
                return $this->findOrCreateTerm($name, $taxonomy);
            }
        };
        $name = 'Slugtest Place '.uniqid();
        $id = $importer->term($name, 42);

        $this->assertNotNull($id);
        $this->assertSame(\Illuminate\Support\Str::slug($name), DB::table('slug')->where('object_id', $id)->value('slug'));
        $this->assertSame($id, $importer->term($name, 42), 'the same name finds the same term');
    }
}
