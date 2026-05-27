<?php

/**
 * IiifMetadataEnricherTest - Unit tests for the IIIF IPTC / EXIF enricher.
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
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

namespace AhgIiifCollection\Tests\Unit;

use AhgIiifCollection\Services\IiifMetadataEnricher;
use PHPUnit\Framework\TestCase;

/**
 * Issue #748. Pure-PHP tests for the IPTC/EXIF -> Pres 3 manifest
 * enrichment helper. The class has no framework deps, so we use the
 * bare PHPUnit TestCase rather than the Laravel one (faster + DB-free).
 *
 * Covers:
 *   - byline present + ISAD already supplies an Author -> Creator row
 *     is still emitted (the IIIF metadata block is additive; the dual
 *     creator/author distinction is preserved for downstream viewers)
 *   - IPTC copyright_notice with empty IO rights -> requiredStatement
 *   - IPTC copyright_notice with ISAD rights present -> no statement
 *   - Keywords as comma string / JSON array / pipe-delimited -> array
 *   - Malformed sidecar (non-array, garbage, missing keys) -> graceful
 *     skip with no exception
 *   - EXIF DateTimeOriginal honoured only when IO has no dateCreated
 */
class IiifMetadataEnricherTest extends TestCase
{
    public function test_byline_emits_creator_metadata_row_even_when_isad_author_exists(): void
    {
        // Simulate an IO that already carries an ISAD-level author. The
        // enricher's job is only to surface the file-level IPTC Creator.
        // It does NOT inspect ISAD `name_access_points`; the manifest
        // builder is responsible for merging without duplication later.
        $iptc = ['creator' => 'Annemarie van Heerden'];

        $rows = IiifMetadataEnricher::fromIptc($iptc);

        $this->assertCount(1, $rows);
        $this->assertSame(['en' => ['Creator']], $rows[0]['label']);
        $this->assertSame(['en' => ['Annemarie van Heerden']], $rows[0]['value']);
    }

    public function test_isad_rights_present_blocks_iptc_requiredstatement(): void
    {
        // ISAD wins: when the IO has reproduction_conditions, the file-
        // level IPTC copyright must NOT clobber it.
        $iptc = ['copyright_notice' => '(c) 1986 Studio Photo'];
        $isadRights = 'Public domain after 2030 per donor agreement.';

        $statement = IiifMetadataEnricher::buildRequiredStatement($iptc, $isadRights);

        $this->assertNull($statement);
    }

    public function test_empty_isad_rights_lets_iptc_copyright_populate_requiredstatement(): void
    {
        $iptc = ['copyright_notice' => '(c) 1986 Studio Photo. All rights reserved.'];

        $statement = IiifMetadataEnricher::buildRequiredStatement($iptc, null);

        $this->assertNotNull($statement);
        $this->assertSame(['en' => ['Attribution']], $statement['label']);
        $this->assertSame(
            ['en' => ['(c) 1986 Studio Photo. All rights reserved.']],
            $statement['value']
        );
    }

    public function test_whitespace_only_isad_rights_does_not_block_iptc(): void
    {
        // A row of spaces / newlines should be treated as "no rights".
        $iptc = ['copyright_notice' => '(c) 2020 Heritage Trust'];

        $statement = IiifMetadataEnricher::buildRequiredStatement($iptc, "   \n  ");

        $this->assertNotNull($statement);
        $this->assertSame(
            ['en' => ['(c) 2020 Heritage Trust']],
            $statement['value']
        );
    }

    public function test_keywords_comma_string_splits_into_array_value(): void
    {
        $iptc = ['keywords' => 'archive, manuscript, 1923, Cape Town'];

        $rows = IiifMetadataEnricher::fromIptc($iptc);

        $this->assertCount(1, $rows);
        $this->assertSame(['en' => ['Keywords']], $rows[0]['label']);
        $this->assertSame(
            ['en' => ['archive', 'manuscript', '1923', 'Cape Town']],
            $rows[0]['value']
        );
    }

    public function test_keywords_as_json_array_parses_through(): void
    {
        $iptc = ['keywords' => '["mining","Witwatersrand","1900s"]'];

        $rows = IiifMetadataEnricher::fromIptc($iptc);

        $this->assertCount(1, $rows);
        $this->assertSame(
            ['en' => ['mining', 'Witwatersrand', '1900s']],
            $rows[0]['value']
        );
    }

    public function test_keywords_pipe_or_newline_separated(): void
    {
        $iptc = ['keywords' => "war\n1944|aerial photo;reconnaissance"];

        $rows = IiifMetadataEnricher::fromIptc($iptc);

        $this->assertCount(1, $rows);
        $this->assertSame(
            ['en' => ['war', '1944', 'aerial photo', 'reconnaissance']],
            $rows[0]['value']
        );
    }

    public function test_malformed_sidecar_keywords_dont_throw(): void
    {
        // stdClass instead of string / array - must be ignored, not
        // serialised to "Object\n".
        $iptc = ['keywords' => (object) ['nope' => 'nope']];

        $rows = IiifMetadataEnricher::fromIptc($iptc);

        $this->assertSame([], $rows);
    }

    public function test_completely_empty_iptc_returns_no_rows(): void
    {
        $rows = IiifMetadataEnricher::fromIptc([]);
        $this->assertSame([], $rows);

        $statement = IiifMetadataEnricher::buildRequiredStatement([], null);
        $this->assertNull($statement);

        $this->assertNull(IiifMetadataEnricher::bylineFromIptc([]));
    }

    public function test_exif_datetime_original_populates_when_io_has_no_date(): void
    {
        $exif = [
            'DateTimeOriginal' => '1986:07:12 14:32:01',
            'Make' => 'Hasselblad',
        ];

        $row = IiifMetadataEnricher::fromExifDateTimeOriginal($exif, false);

        $this->assertNotNull($row);
        $this->assertSame(['en' => ['Date of capture']], $row['label']);
        $this->assertSame(['en' => ['1986:07:12 14:32:01']], $row['value']);
    }

    public function test_exif_datetime_original_suppressed_when_io_has_isad_date(): void
    {
        $exif = ['DateTimeOriginal' => '1986:07:12 14:32:01'];

        $row = IiifMetadataEnricher::fromExifDateTimeOriginal($exif, true);

        $this->assertNull($row);
    }

    public function test_exif_datetime_handles_nested_or_prefixed_keys(): void
    {
        // ExifTool flattens with "EXIF:" prefix; PEL nests under "exif".
        $nested = ['exif' => ['DateTimeOriginal' => '2001:01:01 09:00:00']];
        $prefixed = ['EXIF:DateTimeOriginal' => '2002:02:02 10:00:00'];

        $rowA = IiifMetadataEnricher::fromExifDateTimeOriginal($nested, false);
        $rowB = IiifMetadataEnricher::fromExifDateTimeOriginal($prefixed, false);

        $this->assertNotNull($rowA);
        $this->assertSame(['en' => ['2001:01:01 09:00:00']], $rowA['value']);
        $this->assertNotNull($rowB);
        $this->assertSame(['en' => ['2002:02:02 10:00:00']], $rowB['value']);
    }

    public function test_exif_malformed_or_missing_input_returns_null(): void
    {
        $this->assertNull(IiifMetadataEnricher::fromExifDateTimeOriginal(null, false));
        $this->assertNull(IiifMetadataEnricher::fromExifDateTimeOriginal([], false));
        $this->assertNull(IiifMetadataEnricher::fromExifDateTimeOriginal(['Make' => 'X'], false));
        $this->assertNull(IiifMetadataEnricher::fromExifDateTimeOriginal(['DateTimeOriginal' => ''], false));
        $this->assertNull(IiifMetadataEnricher::fromExifDateTimeOriginal(['DateTimeOriginal' => '   '], false));
    }

    public function test_byline_helper_returns_trimmed_value_or_null(): void
    {
        $this->assertSame(
            'Annemarie van Heerden',
            IiifMetadataEnricher::bylineFromIptc(['creator' => '  Annemarie van Heerden  '])
        );
        $this->assertNull(IiifMetadataEnricher::bylineFromIptc(['creator' => '']));
        $this->assertNull(IiifMetadataEnricher::bylineFromIptc(['creator' => null]));
    }
}
