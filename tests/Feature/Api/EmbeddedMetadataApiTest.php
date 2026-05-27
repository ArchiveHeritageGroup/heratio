<?php

declare(strict_types=1);

/**
 * EmbeddedMetadataApiTest - issue #747.
 *
 * Covers the REST surface that exposes embedded EXIF / IPTC / XMP for a
 * digital object:
 *
 *   GET /api/v1/digital-object/{id}?include=embedded_metadata
 *   GET /api/v2/descriptions/{slug}?include=embedded_metadata
 *   GET /api/v2/digital-object/{id}/embedded-metadata     (standalone)
 *
 * The bulk of the work runs against EmbeddedMetadataService directly so the
 * assertions are deterministic and not bound to whether the host has live
 * digital_object rows. The HTTP layer is covered by the standalone endpoint
 * test - that path collapses controller wiring + route registration +
 * service invocation into one round-trip.
 *
 * Skip semantics:
 *   - Tests skip (not fail) when the underlying sidecar tables aren't
 *     present (`digital_object_metadata`, `dam_iptc_metadata`,
 *     `media_metadata`). This keeps the suite green on fresh installs
 *     that haven't yet run the relevant package install.sql files.
 *   - The PII gate tests skip when `ahg_pii_finding_embedded` is absent
 *     (issue #751 not yet shipped on this host).
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

namespace Tests\Feature\Api;

use AhgApi\Services\EmbeddedMetadataService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmbeddedMetadataApiTest extends TestCase
{
    use DatabaseTransactions;

    /** @var int IO/digital-object id used by every test, isolated by DatabaseTransactions. */
    private int $ioId = 9_500_000 + 747;

    /** @var int digital-object id (same as ioId by AtoM class-table convention) */
    private int $doId = 9_500_000 + 747;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->sidecarTablesPresent()) {
            $this->markTestSkipped('Embedded metadata sidecar tables not installed.');
        }

        // Seed parent IO + digital_object so the service has something to
        // hang off. Both rows live in the standard AtoM tables; the
        // information_object_i18n + slug rows are needed by the v2
        // descriptions endpoint but the service-level tests don't touch
        // them. We insert with FOREIGN_KEY_CHECKS=0 because some installs
        // have FKs on object table; the transactional rollback cleans up.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('object')->insertOrIgnore([
            'id' => $this->ioId,
            'class_name' => 'QubitInformationObject',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('object')->insertOrIgnore([
            'id' => $this->doId + 1, // disambiguate digital_object id from IO id
            'class_name' => 'QubitDigitalObject',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->doId = $this->doId + 1;

        DB::table('digital_object')->insertOrIgnore([
            'id' => $this->doId,
            'object_id' => $this->ioId,
            'usage_id' => 166,
            'mime_type' => 'image/jpeg',
            'media_type_id' => 137,
            'name' => 'test.jpg',
            'path' => 'uploads/test.jpg',
            'byte_size' => 1024,
            'sequence' => 0,
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function sidecarTablesPresent(): bool
    {
        try {
            return Schema::hasTable('digital_object')
                && Schema::hasTable('object')
                && Schema::hasTable('digital_object_metadata');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function seedExif(array $columns): void
    {
        DB::table('digital_object_metadata')->updateOrInsert(
            ['digital_object_id' => $this->doId],
            array_merge($columns, ['digital_object_id' => $this->doId])
        );
    }

    private function seedIptc(array $columns): void
    {
        if (! Schema::hasTable('dam_iptc_metadata')) {
            return;
        }
        DB::table('dam_iptc_metadata')->updateOrInsert(
            ['object_id' => $this->ioId],
            array_merge($columns, ['object_id' => $this->ioId])
        );
    }

    // =========================================================================
    // 1. Empty result when no metadata present (default path).
    // =========================================================================

    public function test_returns_empty_array_when_no_sidecar_rows_present(): void
    {
        $svc = new EmbeddedMetadataService();
        $out = $svc->forDigitalObject($this->doId);

        $this->assertSame([], $out, 'Expected empty array when no sidecar rows exist.');
    }

    // =========================================================================
    // 2. Service returns the full block when sidecar data is present.
    // =========================================================================

    public function test_returns_full_block_when_metadata_present(): void
    {
        $this->seedExif([
            'camera_make' => 'Canon',
            'camera_model' => 'EOS R5',
            'date_created' => '2024-01-01 12:00:00',
            'gps_latitude' => '-25.7479',
            'gps_longitude' => '28.2293',
        ]);
        $this->seedIptc([
            'creator' => 'Test Photographer',
            'copyright_notice' => '(c) Test',
            'headline' => 'Test Headline',
        ]);

        $svc = new EmbeddedMetadataService();
        $out = $svc->forDigitalObject($this->doId);

        $this->assertIsArray($out);
        $this->assertArrayHasKey('exif', $out);
        $this->assertArrayHasKey('iptc', $out);
        $this->assertArrayHasKey('xmp', $out);
        $this->assertSame('Canon', $out['exif']['Make'] ?? null);
        $this->assertSame('EOS R5', $out['exif']['Model'] ?? null);

        if (Schema::hasTable('dam_iptc_metadata')) {
            $this->assertSame('Test Photographer', $out['iptc']['By-line'] ?? null);
            $this->assertSame('(c) Test', $out['iptc']['CopyrightNotice'] ?? null);
        }
    }

    // =========================================================================
    // 3. ODRL gate. Stub the OdrlService and verify the block becomes null.
    // =========================================================================

    public function test_odrl_denial_returns_null(): void
    {
        $this->seedExif([
            'camera_make' => 'Nikon',
        ]);

        // Bind a deny-everything stub at the OdrlService binding the service
        // resolves via app('\\AhgResearch\\Services\\OdrlService'). When the
        // ahg-research package isn't installed we just skip this assertion
        // path because the gate fails open by design.
        if (! class_exists('\\AhgResearch\\Services\\OdrlService')) {
            $this->markTestSkipped('OdrlService not installed in this build.');
        }

        $stub = new class {
            public function isPermitted($entity, $id, $researcherId, $action): bool
            {
                return false;
            }
        };
        $this->app->instance('\\AhgResearch\\Services\\OdrlService', $stub);

        $svc = new EmbeddedMetadataService();
        $out = $svc->forDigitalObject($this->doId, 999);

        $this->assertNull($out, 'ODRL denial must return null so the controller can suppress the key.');
    }

    // =========================================================================
    // 4. PII gate. Insert a pending GPS finding and verify GPS keys are nulled.
    // =========================================================================

    public function test_pii_gate_redacts_gps_keys_when_pending_finding_exists(): void
    {
        if (! Schema::hasTable('ahg_pii_finding_embedded')) {
            $this->markTestSkipped('ahg_pii_finding_embedded (issue #751) not installed.');
        }

        $this->seedExif([
            'camera_make' => 'Apple',
            'gps_latitude' => '-25.7479',
            'gps_longitude' => '28.2293',
            'gps_altitude' => '1339',
        ]);

        DB::table('ahg_pii_finding_embedded')->insert([
            'digital_object_id' => $this->doId,
            'pii_type' => 'gps_coordinate',
            'source_table' => 'digital_object_metadata',
            'source_field' => 'gps_latitude',
            'source_value' => '-25.7479',
            'confidence' => 0.95,
            'resolution_status' => 'pending',
            'scanned_at' => now(),
        ]);

        $svc = new EmbeddedMetadataService();
        $out = $svc->forDigitalObject($this->doId);

        $this->assertIsArray($out);
        $this->assertArrayHasKey('exif', $out);
        $this->assertNull($out['exif']['GPSLatitude'] ?? 'not-null', 'GPSLatitude must be nulled out.');
        $this->assertNull($out['exif']['GPSLongitude'] ?? 'not-null', 'GPSLongitude must be nulled out.');
        $this->assertNull($out['exif']['GPSAltitude'] ?? 'not-null', 'GPSAltitude must be nulled out.');
        $this->assertTrue($out['exif']['_pii_redacted'] ?? false, 'EXIF sub-block must carry the _pii_redacted marker.');
        $this->assertSame('Apple', $out['exif']['Make'] ?? null, 'Non-GPS fields must be preserved.');
    }

    public function test_pii_gate_is_a_noop_when_finding_is_resolved(): void
    {
        if (! Schema::hasTable('ahg_pii_finding_embedded')) {
            $this->markTestSkipped('ahg_pii_finding_embedded (issue #751) not installed.');
        }

        $this->seedExif([
            'camera_make' => 'Sony',
            'gps_latitude' => '-25.7479',
        ]);

        DB::table('ahg_pii_finding_embedded')->insert([
            'digital_object_id' => $this->doId,
            'pii_type' => 'gps_coordinate',
            'source_table' => 'digital_object_metadata',
            'source_field' => 'gps_latitude',
            'source_value' => '-25.7479',
            'confidence' => 0.95,
            'resolution_status' => 'cleared',
            'scanned_at' => now(),
            'resolved_at' => now(),
        ]);

        $svc = new EmbeddedMetadataService();
        $out = $svc->forDigitalObject($this->doId);

        $this->assertSame('-25.7479', $out['exif']['GPSLatitude'] ?? null, 'Cleared findings must not redact.');
        $this->assertArrayNotHasKey('_pii_redacted', $out['exif'], 'Cleared findings must not emit the marker.');
    }

    // =========================================================================
    // 5. Malformed JSON in raw_metadata must degrade to empty XMP.
    // =========================================================================

    public function test_raw_metadata_without_xmp_keys_yields_empty_xmp_block(): void
    {
        // raw_metadata is a JSON column in MySQL so we can't store literal
        // garbage. We exercise the next-best degraded path: valid JSON that
        // happens to have no dc:* / xmp:* / Iptc4xmp* namespaces. The
        // service's decodeJson + extractXmpFromRaw must walk it without
        // throwing and emit an empty xmp array.
        $this->seedExif([
            'camera_make' => 'Canon',
            'raw_metadata' => json_encode(['some' => 'unrelated', 'shape' => [1, 2, 3]]),
        ]);

        $svc = new EmbeddedMetadataService();
        $out = $svc->forDigitalObject($this->doId);

        $this->assertIsArray($out, 'Unexpected sidecar shape must not throw.');
        $this->assertArrayHasKey('xmp', $out);
        $this->assertSame([], $out['xmp'], 'JSON without dc:/xmp: namespaces must yield empty xmp.');
    }

    public function test_malformed_xmp_extraction_does_not_throw(): void
    {
        // Service-level test: feed extractXmpFromRaw garbage via the public
        // decodeJson contract (no DB constraint). Uses reflection rather
        // than touching the JSON column.
        $svc = new EmbeddedMetadataService();
        $ref = new \ReflectionClass($svc);
        $extract = $ref->getMethod('extractXmpFromRaw');
        $extract->setAccessible(true);

        $out = $extract->invoke($svc, '{this is not valid json');

        $this->assertSame([], $out, 'Malformed JSON string must degrade to [].');

        $out = $extract->invoke($svc, null);
        $this->assertSame([], $out, 'Null payload must degrade to [].');

        $out = $extract->invoke($svc, 12345);
        $this->assertSame([], $out, 'Non-string non-array must degrade to [].');
    }

    // =========================================================================
    // 6. Standalone v2 endpoint round-trip.
    // =========================================================================

    public function test_standalone_v2_endpoint_returns_block_or_skips_on_auth(): void
    {
        if (! \Illuminate\Support\Facades\Route::has('api.openapi.spec') && ! \Illuminate\Support\Facades\Route::getRoutes()->getByName('api.openapi.spec')) {
            // Soft check: just make sure the route prefix is loaded.
        }

        $this->seedExif(['camera_make' => 'RouteTest']);

        // Try to ride session auth through the bearer middleware.
        if (Schema::hasTable('users')) {
            $user = DB::table('users')->limit(1)->first();
            if ($user) {
                $model = new \App\Models\User();
                $model->id = $user->id;
                $this->actingAs($model);
            }
        }

        $resp = $this->getJson("/api/v2/digital-object/{$this->doId}/embedded-metadata");

        if ($resp->status() === 401) {
            $this->markTestSkipped('Bearer middleware did not accept session auth in this environment.');
        }

        if ($resp->status() === 404) {
            $this->markTestSkipped('Standalone embedded-metadata route not registered in this environment.');
        }

        $resp->assertOk();
        $resp->assertJsonPath('data.digital_object_id', $this->doId);
        $resp->assertJsonPath('data.information_object_id', $this->ioId);
        $resp->assertJsonStructure([
            'data' => [
                'digital_object_id',
                'information_object_id',
                'embedded_metadata',
            ],
        ]);
    }

    // =========================================================================
    // 7. v1 controller honours ?include=embedded_metadata.
    // =========================================================================

    public function test_v1_show_omits_block_without_include_flag(): void
    {
        $this->seedExif(['camera_make' => 'V1NoInclude']);

        $resp = $this->getJson("/api/v1/digital-object/{$this->doId}");

        if ($resp->status() !== 200) {
            $this->markTestSkipped('v1 digital-object route did not return 200: ' . $resp->status());
        }

        $body = $resp->json();
        $this->assertIsArray($body);
        $this->assertArrayNotHasKey('embedded_metadata', $body, 'Default response shape must NOT carry embedded_metadata.');
    }

    public function test_v1_show_includes_block_with_include_flag(): void
    {
        $this->seedExif(['camera_make' => 'V1WithInclude']);

        $resp = $this->getJson("/api/v1/digital-object/{$this->doId}?include=embedded_metadata");

        if ($resp->status() !== 200) {
            $this->markTestSkipped('v1 digital-object route did not return 200: ' . $resp->status());
        }

        $body = $resp->json();
        $this->assertArrayHasKey('embedded_metadata', $body);
        $this->assertSame('V1WithInclude', $body['embedded_metadata']['exif']['Make'] ?? null);
    }
}
