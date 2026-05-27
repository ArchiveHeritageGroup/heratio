<?php

/**
 * PiiScanEmbeddedMetadataTest - unit coverage for
 * PiiScanService::scanEmbeddedMetadata + persistEmbeddedFindings.
 *
 * Heratio Issue #751. Mocks the DB + Schema facades so the test runs without
 * a database, lets the assertions concentrate on the detection rules and
 * idempotent dedup of the persistence layer.
 *
 * (c) 2026 Johan Pieterse / Plain Sailing Information Systems.
 * Released under AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace Tests\Unit;

use AhgPrivacy\Services\PiiScanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PiiScanEmbeddedMetadataTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Build a Schema mock that reports every table referenced by
     * scanEmbeddedMetadata as present.
     */
    private function expectSchemaTablesPresent(): void
    {
        Schema::shouldReceive('hasTable')
            ->andReturnUsing(fn ($t) => in_array($t, [
                'digital_object_metadata',
                'dam_iptc_metadata',
                'media_metadata',
                'digital_object',
                'ahg_pii_finding_embedded',
            ], true));
    }

    /**
     * Stub DB::table to return a query-builder-like Mockery object whose
     * ->where()->first() / ->where()->value() returns the supplied payloads.
     *
     * @param array<string,mixed> $perTable  table_name => stdClass|null|scalar
     */
    private function stubDbTables(array $perTable): void
    {
        DB::shouldReceive('table')->andReturnUsing(function ($name) use ($perTable) {
            $builder = Mockery::mock();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('whereIn')->andReturnSelf();
            $builder->shouldReceive('orderBy')->andReturnSelf();
            $builder->shouldReceive('limit')->andReturnSelf();
            $builder->shouldReceive('first')->andReturn($perTable[$name] ?? null);
            $builder->shouldReceive('value')->andReturnUsing(function ($col) use ($perTable, $name) {
                $row = $perTable[$name] ?? null;
                if (! $row) return null;
                return is_object($row) ? ($row->{$col} ?? null) : ($row[$col] ?? null);
            });
            $builder->shouldReceive('exists')->andReturn(false);
            $builder->shouldReceive('insert')->andReturn(true);
            $builder->shouldReceive('update')->andReturn(1);
            $builder->shouldReceive('pluck')->andReturn(collect());
            $builder->shouldReceive('get')->andReturn(collect());
            return $builder;
        });
    }

    public function test_gps_coordinates_are_detected_from_digital_object_metadata(): void
    {
        $this->expectSchemaTablesPresent();
        $this->stubDbTables([
            'digital_object_metadata' => (object) [
                'digital_object_id' => 42,
                'gps_latitude'      => '-25.74610000',
                'gps_longitude'     => '28.18810000',
                'gps_altitude'      => '1339.50',
            ],
            'dam_iptc_metadata'       => null,
            'media_metadata'          => null,
            'digital_object'          => null,
        ]);

        $service = new PiiScanService('gdpr');
        $findings = $service->scanEmbeddedMetadata(42);

        $types = array_column($findings, 'pii_type');
        $this->assertSame(['gps_coordinate', 'gps_coordinate', 'gps_coordinate'], $types);

        $fields = array_column($findings, 'field');
        $this->assertContains('gps_latitude', $fields);
        $this->assertContains('gps_longitude', $fields);
        $this->assertContains('gps_altitude', $fields);

        $this->assertEqualsWithDelta(0.95, $findings[0]['confidence'], 0.001);
        $this->assertSame('digital_object_metadata', $findings[0]['source_table']);
    }

    public function test_byline_creator_is_flagged_as_person_name(): void
    {
        $this->expectSchemaTablesPresent();
        $this->stubDbTables([
            'digital_object_metadata' => (object) [
                'digital_object_id' => 7,
                'creator'           => 'Jane Photographer',
            ],
            'dam_iptc_metadata'       => null,
            'media_metadata'          => null,
            'digital_object'          => null,
        ]);

        $service = new PiiScanService('gdpr');
        $findings = $service->scanEmbeddedMetadata(7);

        $this->assertNotEmpty($findings);
        $hit = $findings[0];
        $this->assertSame('person_name', $hit['pii_type']);
        $this->assertSame('creator', $hit['field']);
        $this->assertSame('Jane Photographer', $hit['value']);
        $this->assertGreaterThanOrEqual(0.85, $hit['confidence']);
    }

    public function test_iptc_creator_email_is_flagged_as_person_contact(): void
    {
        // dam_iptc_metadata.object_id keys to information_object.id, so the
        // scanner walks digital_object.object_id back to the IO. The DB stub
        // here flattens that walk to a single value() return.
        Schema::shouldReceive('hasTable')->andReturn(true);

        DB::shouldReceive('table')->andReturnUsing(function ($name) {
            $builder = Mockery::mock();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('whereIn')->andReturnSelf();
            $builder->shouldReceive('orderBy')->andReturnSelf();
            $builder->shouldReceive('limit')->andReturnSelf();
            $builder->shouldReceive('value')->andReturn($name === 'digital_object' ? 999 : null);
            $builder->shouldReceive('first')->andReturn(
                $name === 'dam_iptc_metadata'
                    ? (object) [
                        'object_id'      => 999,
                        'creator'        => 'Alex Photographer',
                        'creator_email'  => 'alex@example.com',
                        'creator_phone'  => '+27 21 555 1234',
                    ]
                    : null
            );
            $builder->shouldReceive('exists')->andReturn(false);
            $builder->shouldReceive('insert')->andReturn(true);
            return $builder;
        });

        $service = new PiiScanService('gdpr');
        $findings = $service->scanEmbeddedMetadata(123);

        $byField = [];
        foreach ($findings as $f) {
            $byField[$f['field']] = $f;
        }
        $this->assertArrayHasKey('creator_email', $byField);
        $this->assertSame('person_contact', $byField['creator_email']['pii_type']);
        $this->assertSame('alex@example.com', $byField['creator_email']['value']);

        $this->assertArrayHasKey('creator_phone', $byField);
        $this->assertSame('person_contact', $byField['creator_phone']['pii_type']);
    }

    public function test_returns_empty_array_when_no_metadata_rows_exist(): void
    {
        Schema::shouldReceive('hasTable')->andReturn(true);
        $this->stubDbTables([
            'digital_object_metadata' => null,
            'dam_iptc_metadata'       => null,
            'media_metadata'          => null,
            'digital_object'          => null,
        ]);

        $service = new PiiScanService('gdpr');
        $findings = $service->scanEmbeddedMetadata(99999);

        $this->assertSame([], $findings);
    }

    public function test_persist_skips_when_finding_already_exists(): void
    {
        // First call: exists() returns true (dedup), so insert is NOT called.
        Schema::shouldReceive('hasTable')->andReturn(true);

        $insertCalls = 0;
        $updateCalls = 0;
        DB::shouldReceive('table')->andReturnUsing(function () use (&$insertCalls, &$updateCalls) {
            $builder = Mockery::mock();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('exists')->andReturn(true);
            $builder->shouldReceive('update')->andReturnUsing(function () use (&$updateCalls) {
                $updateCalls++;
                return 1;
            });
            $builder->shouldReceive('insert')->andReturnUsing(function () use (&$insertCalls) {
                $insertCalls++;
                return true;
            });
            return $builder;
        });

        $service = new PiiScanService('gdpr');
        $findings = [[
            'field'         => 'gps_latitude',
            'value'         => '-25.7461',
            'pii_type'      => 'gps_coordinate',
            'confidence'    => 0.95,
            'source_table'  => 'digital_object_metadata',
            'source_column' => 'gps_latitude',
        ]];

        $inserted = $service->persistEmbeddedFindings(42, $findings);

        $this->assertSame(0, $inserted, 'Re-scan must not insert duplicate finding rows.');
        $this->assertSame(0, $insertCalls);
        $this->assertSame(1, $updateCalls, 'Re-scan must refresh scanned_at on the existing row.');
    }
}
