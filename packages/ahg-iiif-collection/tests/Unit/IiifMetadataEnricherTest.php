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

    // ====================================================================
    // Issue #1101 - AtoM parity: Camera, GPS, Location, separate Copyright
    // ====================================================================

    public function test_camera_make_and_model_join_with_space(): void
    {
        $row = IiifMetadataEnricher::fromCamera(['Make' => 'Hasselblad', 'Model' => '500C/M']);

        $this->assertNotNull($row);
        $this->assertSame(['en' => ['Camera']], $row['label']);
        $this->assertSame(['en' => ['Hasselblad 500C/M']], $row['value']);
    }

    public function test_camera_make_only_or_model_only(): void
    {
        $makeOnly = IiifMetadataEnricher::fromCamera(['Make' => 'Leica']);
        $this->assertSame(['en' => ['Leica']], $makeOnly['value']);

        $modelOnly = IiifMetadataEnricher::fromCamera(['Model' => 'M6']);
        $this->assertSame(['en' => ['M6']], $modelOnly['value']);
    }

    public function test_camera_reads_nested_and_prefixed_exif_shapes(): void
    {
        $nested = IiifMetadataEnricher::fromCamera(['exif' => ['Make' => 'Nikon', 'Model' => 'D850']]);
        $this->assertSame(['en' => ['Nikon D850']], $nested['value']);

        $prefixed = IiifMetadataEnricher::fromCamera(['EXIF:Make' => 'Canon', 'EXIF:Model' => 'EOS 5D']);
        $this->assertSame(['en' => ['Canon EOS 5D']], $prefixed['value']);
    }

    public function test_camera_absent_or_malformed_returns_null(): void
    {
        $this->assertNull(IiifMetadataEnricher::fromCamera(null));
        $this->assertNull(IiifMetadataEnricher::fromCamera([]));
        $this->assertNull(IiifMetadataEnricher::fromCamera(['Make' => '', 'Model' => '  ']));
        $this->assertNull(IiifMetadataEnricher::fromCamera(['ISO' => 400]));
    }

    public function test_gps_from_prebuilt_decimal_string(): void
    {
        $row = IiifMetadataEnricher::fromGpsCoordinates(['gps' => ['decimal' => '-25.746111, 28.188056']]);

        $this->assertNotNull($row);
        $this->assertSame(['en' => ['GPS Coordinates']], $row['label']);
        $this->assertSame(['en' => ['-25.746111, 28.188056']], $row['value']);
    }

    public function test_gps_from_consolidated_numeric_pair_formats_six_decimals(): void
    {
        $row = IiifMetadataEnricher::fromGpsCoordinates(['latitude' => -33.9249, 'longitude' => 18.4241]);

        $this->assertNotNull($row);
        $this->assertSame(['en' => ['-33.924900, 18.424100']], $row['value']);
    }

    public function test_gps_from_raw_exif_dms_rationals_with_hemisphere(): void
    {
        // 33 deg 55' 29.64" S, 18 deg 25' 26.76" E (Cape Town-ish).
        $exif = [
            'GPSLatitude' => ['33/1', '55/1', '2964/100'],
            'GPSLatitudeRef' => 'S',
            'GPSLongitude' => ['18/1', '25/1', '2676/100'],
            'GPSLongitudeRef' => 'E',
        ];

        $row = IiifMetadataEnricher::fromGpsCoordinates($exif);

        $this->assertNotNull($row);
        // South -> negative latitude; East -> positive longitude.
        $this->assertSame(['en' => ['-33.924900, 18.424100']], $row['value']);
    }

    public function test_gps_absent_or_partial_returns_null(): void
    {
        $this->assertNull(IiifMetadataEnricher::fromGpsCoordinates(null));
        $this->assertNull(IiifMetadataEnricher::fromGpsCoordinates([]));
        $this->assertNull(IiifMetadataEnricher::fromGpsCoordinates(['latitude' => -25.0]));
        $this->assertNull(IiifMetadataEnricher::fromGpsCoordinates(['GPSLatitude' => ['1/1', '2/1', '3/1']]));
    }

    public function test_location_joins_city_state_country(): void
    {
        $row = IiifMetadataEnricher::fromLocation([
            'city' => 'Pretoria',
            'province_state' => 'Gauteng',
            'country' => 'South Africa',
        ]);

        $this->assertNotNull($row);
        $this->assertSame(['en' => ['Location']], $row['label']);
        $this->assertSame(['en' => ['Pretoria, Gauteng, South Africa']], $row['value']);
    }

    public function test_location_partial_parts_skip_missing(): void
    {
        $cityCountry = IiifMetadataEnricher::fromLocation(['city' => 'Harare', 'country' => 'Zimbabwe']);
        $this->assertSame(['en' => ['Harare, Zimbabwe']], $cityCountry['value']);

        $countryOnly = IiifMetadataEnricher::fromLocation(['country' => 'Namibia']);
        $this->assertSame(['en' => ['Namibia']], $countryOnly['value']);
    }

    public function test_location_reads_nested_consolidated_block(): void
    {
        $row = IiifMetadataEnricher::fromLocation([
            'location' => ['city' => 'Lisbon', 'state' => null, 'country' => 'Portugal'],
        ]);

        $this->assertSame(['en' => ['Lisbon, Portugal']], $row['value']);
    }

    public function test_location_state_column_name_variants(): void
    {
        $stateKey = IiifMetadataEnricher::fromLocation(['city' => 'A', 'state' => 'B', 'country' => 'C']);
        $this->assertSame(['en' => ['A, B, C']], $stateKey['value']);

        $provinceKey = IiifMetadataEnricher::fromLocation(['city' => 'A', 'province' => 'B', 'country' => 'C']);
        $this->assertSame(['en' => ['A, B, C']], $provinceKey['value']);
    }

    public function test_location_empty_returns_null(): void
    {
        $this->assertNull(IiifMetadataEnricher::fromLocation([]));
        $this->assertNull(IiifMetadataEnricher::fromLocation(['city' => '', 'country' => '   ']));
    }

    public function test_copyright_metadata_row_emitted_unconditionally(): void
    {
        $row = IiifMetadataEnricher::buildCopyrightMetadata(['copyright_notice' => '(c) 1986 Studio Photo']);

        $this->assertNotNull($row);
        $this->assertSame(['en' => ['Copyright']], $row['label']);
        $this->assertSame(['en' => ['(c) 1986 Studio Photo']], $row['value']);
    }

    public function test_copyright_metadata_row_null_when_absent(): void
    {
        $this->assertNull(IiifMetadataEnricher::buildCopyrightMetadata([]));
        $this->assertNull(IiifMetadataEnricher::buildCopyrightMetadata(['copyright_notice' => '   ']));
    }
}
