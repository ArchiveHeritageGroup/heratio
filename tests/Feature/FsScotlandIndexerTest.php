<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgAiServices\Support\FsDataSafeCsv;
use AhgAiServices\Support\FsKeyingRules;
use AhgAiServices\Support\FsScotlandProfile;
use Tests\TestCase;

/**
 * Deterministic core of the FS-Scotland indexer (no model / gateway needed):
 * the field-by-event matrix, the keying-rule normalisers, ditto inheritance,
 * and the Data Safe CSV. Guards the spec rules against regression.
 */
class FsScotlandIndexerTest extends TestCase
{
    public function test_field_matrix_is_event_type_aware(): void
    {
        $marriage = FsScotlandProfile::fieldsFor('Marriage');
        $this->assertContains('SP_FTHR_NAME_GN_ORIG', $marriage);   // spouse-parents are Marriage-only
        $this->assertContains('PR_MARITAL_STATUS_ORIG', $marriage);
        $this->assertNotContains('PR_SEX_CODE_ORIG', $marriage);     // Sex not keyed for Marriage
        $this->assertNotContains('PR_BIR_DAY_ORIG', $marriage);      // Birth-day is Baptism-only

        $birth = FsScotlandProfile::fieldsFor('Birth');
        $this->assertContains('PR_SEX_CODE_ORIG', $birth);
        $this->assertNotContains('SP_NAME_GN_ORIG', $birth);         // no spouse on a Birth
        $this->assertNotContains('PR_AGE_ORIG', $birth);

        $this->assertContains('PR_BIR_YEAR_ORIG', FsScotlandProfile::fieldsFor('Baptism'));
        $this->assertContains('PR_DEA_YEAR_ORIG', FsScotlandProfile::fieldsFor('Burial'));
    }

    public function test_keying_rule_normalisers(): void
    {
        $this->assertSame('Mar', FsKeyingRules::month('March'));
        $this->assertSame('Mar', FsKeyingRules::month('03'));
        $this->assertSame('Sep', FsKeyingRules::month('9'));
        $this->assertSame('7', FsKeyingRules::day('07'));     // drop leading zero 01-09
        $this->assertSame('15', FsKeyingRules::day('15'));
        $this->assertSame('W', FsKeyingRules::marital('Widow'));
        $this->assertSame('S', FsKeyingRules::marital('S'));
        $this->assertSame('M', FsKeyingRules::sex('male'));
        $this->assertSame('', FsKeyingRules::sex('George'));   // never infer sex from a name
        $this->assertSame('George Smith', FsKeyingRules::name('Mr. George Smith'));
        $this->assertSame("O'Brien", FsKeyingRules::name("O'Brien,"));   // keep apostrophe, drop comma
        $this->assertTrue(FsKeyingRules::isDitto('do'));
        $this->assertTrue(FsKeyingRules::isDitto('"'));
        $this->assertFalse(FsKeyingRules::isDitto('Smith'));
    }

    public function test_ditto_inheritance(): void
    {
        $records = [
            ['PR_NAME_GN_ORIG' => 'John', 'PR_NAME_SURN_ORIG' => 'Fraser'],
            ['PR_NAME_GN_ORIG' => 'Mary', 'PR_NAME_SURN_ORIG' => '"'],   // ditto -> Fraser
        ];
        $out = FsKeyingRules::applyDitto($records);
        $this->assertSame('Fraser', $out[1]['PR_NAME_SURN_ORIG']);
    }

    public function test_process_records_to_data_safe_rows(): void
    {
        $svc = app(\AhgAiServices\Services\FsScotlandIndexerService::class);
        $meta = $svc->parseImageMeta('/x/008066403/008066403_00015.jpg');
        $this->assertSame('008066403', $meta['dgs']);
        $this->assertSame('00015', $meta['image_nbr']);

        $base = $svc->baseFields($meta['dgs'], $meta['image_nbr'], ['collection_id' => 'C1', 'ppq_id' => 'P1']);
        $extracted = [
            ['PR_NAME_GN_ORIG' => 'James', 'PR_NAME_SURN_ORIG' => 'Reid', 'EVENT_MONTH_ORIG' => 'July', 'EVENT_DAY_ORIG' => '03', 'PR_BIR_DAY_ORIG' => '12'],
            ['PR_NAME_GN_ORIG' => 'Ann',   'PR_NAME_SURN_ORIG' => 'do',   'EVENT_MONTH_ORIG' => '"'],
        ];
        $rows = $svc->processRecords($extracted, 'Marriage', $base);

        $this->assertCount(2, $rows);
        $this->assertSame('0', $rows[0]['FS_RECORD_NBR']);
        $this->assertSame('1', $rows[1]['FS_RECORD_NBR']);
        $this->assertSame('Marriage', $rows[0]['EVENT_TYPE']);
        $this->assertSame('en', $rows[0]['FS_LANGUAGE']);
        $this->assertSame('008066403', $rows[0]['FS_DIGITAL_FILM_NBR']);
        $this->assertSame('Jul', $rows[0]['EVENT_MONTH_ORIG']);   // normalised
        $this->assertSame('3', $rows[0]['EVENT_DAY_ORIG']);       // leading zero dropped
        $this->assertSame('Reid', $rows[1]['PR_NAME_SURN_ORIG']); // ditto inherited
        $this->assertSame('Jul', $rows[1]['EVENT_MONTH_ORIG']);   // ditto + normalised
        // Birth-day is not a Marriage field -> must not leak through.
        $this->assertArrayNotHasKey('PR_BIR_DAY_ORIG', $rows[0]);
    }

    public function test_model_field_adapter(): void
    {
        $map = \AhgAiServices\Support\FsModelFieldMap::class;
        $this->assertSame('type_c', $map::docTypeForEvent('Marriage'));
        $this->assertSame('type_a', $map::docTypeForEvent('Birth'));
        $this->assertSame('type_b', $map::docTypeForEvent('Death'));

        // Marriage (type_c): groom -> principal, bride -> spouse, witnesses dropped.
        $m = $map::toFsRecord([
            ['name' => 'groom_surname', 'value' => 'Reid'],
            ['name' => 'groom_first_names', 'value' => 'James'],
            ['name' => 'bride_surname', 'value' => 'Fraser'],
            ['name' => 'bride_first_names', 'value' => 'Ann'],
            ['name' => 'date_of_marriage', 'value' => '12 July 1870'],
            ['name' => 'witness_1', 'value' => 'Someone'],
        ], 'Marriage');
        $this->assertSame('Reid', $m['PR_NAME_SURN_ORIG']);
        $this->assertSame('James', $m['PR_NAME_GN_ORIG']);
        $this->assertSame('Fraser', $m['SP_NAME_SURN_ORIG']);
        $this->assertSame('Ann', $m['SP_NAME_GN_ORIG']);
        $this->assertSame('12', $m['EVENT_DAY_ORIG']);
        $this->assertSame('Jul', $m['EVENT_MONTH_ORIG']);  // splitDate captures the 3-letter stem
        $this->assertSame('1870', $m['EVENT_YEAR_ORIG']);
        $this->assertArrayNotHasKey('witness_1', $m);       // no Data Safe home

        // Birth (type_a): father_name split into given/surname.
        $b = $map::toFsRecord([
            ['name' => 'surname', 'value' => 'Smith'],
            ['name' => 'first_names', 'value' => 'Mary'],
            ['name' => 'father_name', 'value' => 'John Smith'],
            ['name' => 'date_of_birth', 'value' => '3 Mar 1861'],
        ], 'Birth');
        $this->assertSame('John', $b['PR_FTHR_NAME_GN_ORIG']);
        $this->assertSame('Smith', $b['PR_FTHR_NAME_SURN_ORIG']);
        $this->assertSame('1861', $b['EVENT_YEAR_ORIG']);
    }

    public function test_data_safe_csv_shape(): void
    {
        $csv = FsDataSafeCsv::toString([
            ['FS_DIGITAL_FILM_NBR' => '008066403', 'EVENT_TYPE' => 'Marriage', 'PR_NAME_GN_ORIG' => 'James'],
        ]);
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertStringContainsString('FS_COLLECTION_ID', $lines[0]);
        $this->assertStringContainsString('PR_MTHR_NAME_SURN_ORIG', $lines[0]);
        $this->assertCount(count(FsScotlandProfile::COLUMNS), str_getcsv($lines[0]));
        $this->assertStringContainsString('008066403', $lines[1]);
    }
}
