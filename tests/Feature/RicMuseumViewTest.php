<?php

/**
 * RicMuseumViewTest - the RiC view of a museum object shows the catalogue record
 * (it used to read field names that do not exist and showed only the object
 * type), and only prints RiC-O properties that exist in RiC-O 1.0.2.
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

use Tests\TestCase;

class RicMuseumViewTest extends TestCase
{
    /** Checked against https://www.ica.org/standards/RiC/RiC-O_1-0-2.rdf on 19 Oct 2026. */
    private const RICO_1_0_2 = [
        'expressedDate', 'hasCreator', 'hasExtent', 'hasOrHadCategory', 'hasOrHadHolder', 'hasOrHadLocation',
        'hasOrHadParticipant', 'hasOrHadSubject', 'hasProductionTechniqueType', 'history', 'identifier',
        'isOrWasDescribedBy', 'isOrWasRegulatedBy', 'physicalCharacteristicsNote', 'scopeAndContent', 'title',
        'hasDocumentaryFormType', 'hasOrHadInstantiation', 'hasModificationDate',
        'hasCreator',
    ];

    public function test_the_ric_view_shows_the_museum_catalogue_record(): void
    {
        $museum = (object) [
            'id' => 0, 'title' => 'Test chair', 'identifier' => 'MUS-T-1', 'object_type' => 'Chair',
            'classification' => 'Decorative Arts - Furniture', 'scope_and_content' => 'A turned chair.',
            'materials' => 'Walnut', 'techniques' => 'Turning', 'measurements' => 'H 92 cm',
            'creator_identity' => 'Unknown maker', 'creator_role' => 'Furniture maker', 'creation_date_display' => 'circa 1900',
            'creation_place' => 'Birmingham', 'provenance' => 'Donated 1998.', 'current_location' => 'Gallery 4',
            'rights_type' => 'Copyright', 'rights_holder' => 'Demo Museum', 'condition_term' => 'Good',
            'style_period' => 'Edwardian', 'cataloger_name' => 'A. Curator',
        ];
        $html = view('ahg-ric::_ric-view-museum', ['museum' => $museum])->render();

        foreach (['MUS-T-1', 'Chair', 'Walnut', 'Turning', 'H 92 cm', 'Unknown maker (Furniture maker)', 'circa 1900',
            'Birmingham', 'Donated 1998.', 'Gallery 4', 'Copyright - Demo Museum', 'Good', 'Edwardian', 'A. Curator'] as $value) {
            $this->assertStringContainsString(e($value), $html, "shows {$value}");
        }

        preg_match_all('/rico:([a-z][A-Za-z]+)/', $html, $m); // properties start lowercase; the type pill prints a class
        foreach (array_unique($m[1]) as $property) {
            $this->assertContains($property, self::RICO_1_0_2, "rico:{$property} exists in RiC-O 1.0.2");
        }
    }
}
