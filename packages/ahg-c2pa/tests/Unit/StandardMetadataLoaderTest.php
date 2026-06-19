<?php
/**
 * Heratio - StandardMetadataLoader unit tests (issue #749).
 *
 * Covers:
 *   - manifest includes stds.exif / stds.iptc / stds.xmp when sidecars have data
 *   - manifest omits them cleanly when sidecars are empty
 *   - round-trip C2paService::verify() passes with all three assertions
 *   - PII gate redacts GPS when ahg_pii_finding_embedded has a pending
 *     gps_coordinate finding and adds `_pii_redacted: true`
 *   - malformed / missing sidecar table is degraded gracefully
 *
 * Uses an in-memory SQLite DB via Illuminate's Capsule manager so the DB
 * facade is reachable without touching MySQL.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Tests\Unit;

use AhgC2pa\Manifest\Assertion;
use AhgC2pa\Manifest\ManifestBuilder;
use AhgC2pa\Manifest\StandardMetadataLoader;
use AhgC2pa\Services\C2paService;
use AhgInferenceReceipts\KeyPair;
use AhgInferenceReceipts\Signer as ReceiptSigner;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

final class StandardMetadataLoaderTest extends TestCase
{
    private Capsule $capsule;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
        Container::setInstance($this->container);

        $this->capsule = new Capsule();
        $this->capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        $this->container->instance('db', $this->capsule->getDatabaseManager());
        $this->container->instance('db.connection', $this->capsule->getConnection());
        $this->container->bind('db.schema', fn () => $this->capsule->getConnection()->getSchemaBuilder());

        // C2paService::autodetectBinary() calls config('heratio.c2patool_bin');
        // bind a minimal config repository so the helper resolves in this
        // hand-built (non-Laravel) container instead of throwing
        // "Target class [config] does not exist".
        $this->container->instance('config', new \Illuminate\Config\Repository([
            'heratio' => ['c2patool_bin' => null],
        ]));

        Facade::setFacadeApplication($this->container);

        $this->createTables();
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    private function createTables(): void
    {
        $schema = $this->capsule->getConnection()->getSchemaBuilder();

        $schema->create('digital_object_metadata', function (Blueprint $t) {
            $t->integer('digital_object_id')->primary();
            $t->string('date_created', 64)->nullable();
            $t->string('camera_make', 64)->nullable();
            $t->string('camera_model', 64)->nullable();
            $t->integer('image_width')->nullable();
            $t->integer('image_height')->nullable();
            $t->string('creator', 128)->nullable();
            $t->string('copyright', 255)->nullable();
            $t->text('description')->nullable();
            $t->string('title', 255)->nullable();
            $t->text('keywords')->nullable();
            $t->decimal('gps_latitude', 10, 6)->nullable();
            $t->decimal('gps_longitude', 10, 6)->nullable();
        });

        $schema->create('media_metadata', function (Blueprint $t) {
            $t->integer('digital_object_id')->primary();
            $t->string('make', 64)->nullable();
            $t->string('model', 64)->nullable();
            $t->string('software', 128)->nullable();
            $t->decimal('duration', 12, 3)->nullable();
        });

        $schema->create('dam_iptc_metadata', function (Blueprint $t) {
            $t->integer('object_id')->primary();
            $t->string('creator', 128)->nullable();
            $t->string('creator_job_title', 128)->nullable();
            $t->text('copyright_notice')->nullable();
            $t->string('headline', 255)->nullable();
            $t->text('caption')->nullable();
            $t->string('title', 255)->nullable();
            $t->string('city', 128)->nullable();
            $t->string('state_province', 128)->nullable();
            $t->string('country', 128)->nullable();
            $t->string('country_code', 8)->nullable();
            $t->string('sublocation', 128)->nullable();
            $t->string('credit_line', 255)->nullable();
            $t->string('source', 255)->nullable();
            $t->text('instructions')->nullable();
            $t->string('intellectual_genre', 128)->nullable();
            $t->string('iptc_subject_code', 64)->nullable();
            $t->string('iptc_scene', 64)->nullable();
            $t->string('date_created', 64)->nullable();
            $t->text('keywords')->nullable();
            $t->text('rights_usage_terms')->nullable();
        });

        $schema->create('ahg_pii_finding_embedded', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('digital_object_id');
            $t->string('pii_type', 64);
            $t->string('source_table', 64);
            $t->string('source_field', 128);
            $t->text('source_value')->nullable();
            $t->decimal('confidence', 3, 2)->default(0.70);
            $t->string('resolution_status', 32)->default('pending');
            $t->dateTime('scanned_at')->nullable();
        });
    }

    private function seedFullSidecars(int $digitalObjectId, int $informationObjectId): void
    {
        DB::table('digital_object_metadata')->insert([
            'digital_object_id' => $digitalObjectId,
            'date_created'      => '2026-04-01T12:00:00Z',
            'camera_make'       => 'Canon',
            'camera_model'      => 'EOS R5',
            'image_width'       => 4096,
            'image_height'      => 2731,
            'creator'           => 'Alice Photographer',
            'copyright'         => '(c) 2026 Alice',
            'description'       => 'Cape Town harbour at dawn',
            'title'             => 'Harbour',
            'keywords'          => 'harbour, sunrise, cape town',
            'gps_latitude'      => -33.918861,
            'gps_longitude'     => 18.423300,
        ]);

        DB::table('media_metadata')->insert([
            'digital_object_id' => $digitalObjectId,
            'make'              => 'Canon',
            'model'             => 'EOS R5',
            'software'          => 'DPP 4.16',
            'duration'          => null,
        ]);

        DB::table('dam_iptc_metadata')->insert([
            'object_id'         => $informationObjectId,
            'creator'           => 'Alice Photographer',
            'copyright_notice'  => '(c) 2026 Alice. All rights reserved.',
            'headline'          => 'Cape Town harbour at dawn',
            'caption'           => 'Long-exposure of Table Bay at first light.',
            'title'             => 'Harbour at Dawn',
            'city'              => 'Cape Town',
            'country'           => 'South Africa',
            'country_code'      => 'ZA',
            'credit_line'       => 'Alice Photographer / Heratio Trust',
            'source'            => 'Heritage Trust collection',
            'date_created'      => '2026-04-01',
            'keywords'          => 'harbour, sunrise, cape town',
            'rights_usage_terms' => 'Editorial use only',
        ]);
    }

    // ----------------------------------------------------------------------

    public function test_loader_returns_three_arrays_when_sidecars_populated(): void
    {
        $this->seedFullSidecars(10, 99);

        $loader = new StandardMetadataLoader();
        $exif = $loader->loadExif(10);
        $iptc = $loader->loadIptc(10, 99);
        $xmp  = $loader->loadXmp(10, 99);

        $this->assertSame('Canon',         $exif['Exif/Make']);
        $this->assertSame('EOS R5',        $exif['Exif/Model']);
        $this->assertSame(4096,            $exif['Exif/ImageWidth']);
        $this->assertSame(2731,            $exif['Exif/ImageHeight']);
        $this->assertSame(-33.918861,      $exif['Exif/GPSLatitude']);
        $this->assertSame('S',             $exif['Exif/GPSLatitudeRef']);
        $this->assertSame(18.4233,         $exif['Exif/GPSLongitude']);
        $this->assertSame('E',             $exif['Exif/GPSLongitudeRef']);
        $this->assertSame('DPP 4.16',      $exif['Exif/Software']);
        $this->assertArrayNotHasKey('_pii_redacted', $exif);

        $this->assertSame('Alice Photographer',          $iptc['By-line']);
        $this->assertSame('Cape Town harbour at dawn',    $iptc['Headline']);
        $this->assertSame(['harbour', 'sunrise', 'cape town'], $iptc['Keywords']);
        $this->assertSame('Cape Town',                    $iptc['City']);

        $this->assertSame(['Alice Photographer'],         $xmp['dc:creator']);
        $this->assertSame(['x-default' => 'Harbour at Dawn'], $xmp['dc:title']);
        $this->assertSame(['harbour', 'sunrise', 'cape town'], $xmp['dc:subject']);
        $this->assertTrue($xmp['xmpRights:Marked']);
    }

    public function test_manifest_includes_three_stds_assertions_when_sidecar_has_data(): void
    {
        $this->seedFullSidecars(10, 99);

        $manifest = (new ManifestBuilder())
            ->withTitle('Test stds round-trip')
            ->withFormat('image/jpeg')
            ->withClaimGenerator('Heratio/test')
            ->withAssetString('fake-asset-bytes')
            ->addAssertion(Assertion::action('placed', ['heratioVersion' => 'test']))
            ->withStandardMetadata(10, 99)
            ->build();

        $labels = array_map(fn ($a) => $a['label'], $manifest['assertions']);
        $this->assertContains('stds.exif', $labels);
        $this->assertContains('stds.iptc', $labels);
        $this->assertContains('stds.xmp',  $labels);
    }

    public function test_manifest_omits_stds_assertions_when_sidecars_empty(): void
    {
        // No sidecar rows inserted. Loader must return [] for all three, and
        // ManifestBuilder must not emit empty assertions.
        $manifest = (new ManifestBuilder())
            ->withTitle('No-sidecar case')
            ->withFormat('text/plain')
            ->withClaimGenerator('Heratio/test')
            ->withAssetString('hello')
            ->addAssertion(Assertion::action('placed', ['heratioVersion' => 'test']))
            ->withStandardMetadata(7777, 8888)
            ->build();

        $labels = array_map(fn ($a) => $a['label'], $manifest['assertions']);
        $this->assertNotContains('stds.exif', $labels);
        $this->assertNotContains('stds.iptc', $labels);
        $this->assertNotContains('stds.xmp',  $labels);
        // The single c2pa.actions.v2 assertion we added survives.
        $this->assertSame(['c2pa.actions.v2'], $labels);
    }

    public function test_pii_gate_redacts_gps_when_pending_finding_exists(): void
    {
        $this->seedFullSidecars(20, 21);

        DB::table('ahg_pii_finding_embedded')->insert([
            'digital_object_id' => 20,
            'pii_type'          => 'gps_coordinate',
            'source_table'      => 'digital_object_metadata',
            'source_field'      => 'gps_latitude',
            'source_value'      => '-33.918861',
            'confidence'        => 0.95,
            'resolution_status' => 'pending',
            'scanned_at'        => '2026-05-27 00:00:00',
        ]);

        $exif = (new StandardMetadataLoader())->loadExif(20);

        $this->assertArrayNotHasKey('Exif/GPSLatitude', $exif);
        $this->assertArrayNotHasKey('Exif/GPSLongitude', $exif);
        $this->assertArrayNotHasKey('Exif/GPSLatitudeRef', $exif);
        $this->assertArrayNotHasKey('Exif/GPSLongitudeRef', $exif);
        $this->assertTrue($exif['_pii_redacted'] ?? false);
        // Other EXIF data still passes through.
        $this->assertSame('Canon', $exif['Exif/Make']);
        $this->assertSame(4096,    $exif['Exif/ImageWidth']);
    }

    public function test_pii_gate_does_not_fire_when_finding_resolution_is_cleared(): void
    {
        $this->seedFullSidecars(30, 31);

        DB::table('ahg_pii_finding_embedded')->insert([
            'digital_object_id' => 30,
            'pii_type'          => 'gps_coordinate',
            'source_table'      => 'digital_object_metadata',
            'source_field'      => 'gps_latitude',
            'confidence'        => 0.95,
            'resolution_status' => 'cleared',
            'scanned_at'        => '2026-05-27 00:00:00',
        ]);

        $exif = (new StandardMetadataLoader())->loadExif(30);

        $this->assertArrayHasKey('Exif/GPSLatitude', $exif);
        $this->assertArrayNotHasKey('_pii_redacted', $exif);
    }

    public function test_pii_gate_table_absent_proceeds_without_redaction(): void
    {
        // Simulate a fresh install where #751 phase-2 schema has not landed.
        $this->capsule->getConnection()->getSchemaBuilder()->drop('ahg_pii_finding_embedded');

        $this->seedFullSidecars(40, 41);

        $exif = (new StandardMetadataLoader())->loadExif(40);
        $this->assertArrayHasKey('Exif/GPSLatitude', $exif);
        $this->assertArrayNotHasKey('_pii_redacted', $exif);
    }

    public function test_round_trip_verify_passes_with_three_stds_assertions(): void
    {
        $this->seedFullSidecars(50, 51);

        $kp = KeyPair::generate();
        $receiptSigner = new ReceiptSigner($kp);
        $service = new C2paService($receiptSigner);

        $tmp = tempnam(sys_get_temp_dir(), 'c2pa-asset-');
        file_put_contents((string) $tmp, str_repeat("abc", 64));

        try {
            $manifest = $service->manifestForDigitalObject(
                informationObjectId: 51,
                digitalObjectId:     50,
                assetPath:           (string) $tmp,
                heratioVersion:      'test',
            );

            // Sanity - stds.* are in there.
            $labels = array_map(fn ($a) => $a['label'], $manifest['assertions']);
            $this->assertContains('stds.exif', $labels);
            $this->assertContains('stds.iptc', $labels);
            $this->assertContains('stds.xmp',  $labels);

            $signed = $service->signManifest($manifest);

            $serialised = ManifestBuilder::toCanonicalJson($signed);
            $decoded = json_decode($serialised, true, 512, JSON_THROW_ON_ERROR);

            $publicKeyResolver = fn (string $kid): ?string => $kid === $kp->kid() ? $kp->publicKey() : null;
            $result = C2paService::verify($decoded, $publicKeyResolver);

            $this->assertTrue($result['ok'], 'verify must pass: ' . implode(' | ', $result['errors']));
            $this->assertSame([], $result['errors']);

            // Every assertion is referenced by hash in the claim.
            $this->assertGreaterThanOrEqual(3, count($result['assertion_hashes']));
        } finally {
            @unlink((string) $tmp);
        }
    }

    public function test_pii_redaction_marker_reflected_in_signed_manifest_payload(): void
    {
        $this->seedFullSidecars(60, 61);
        DB::table('ahg_pii_finding_embedded')->insert([
            'digital_object_id' => 60,
            'pii_type'          => 'gps_coordinate',
            'source_table'      => 'digital_object_metadata',
            'source_field'      => 'gps_latitude',
            'confidence'        => 0.95,
            'resolution_status' => 'escalated',
            'scanned_at'        => '2026-05-27 00:00:00',
        ]);

        $kp = KeyPair::generate();
        $service = new C2paService(new ReceiptSigner($kp));

        $tmp = tempnam(sys_get_temp_dir(), 'c2pa-pii-');
        file_put_contents((string) $tmp, 'asset-bytes');

        try {
            $manifest = $service->manifestForDigitalObject(61, 60, (string) $tmp, 'test');
            $signed = $service->signManifest($manifest);

            $exifBlob = null;
            foreach ($signed['assertions'] as $a) {
                if ($a['label'] === 'stds.exif') {
                    $exifBlob = $a['data'];
                    break;
                }
            }
            $this->assertNotNull($exifBlob, 'stds.exif must be present');
            $this->assertTrue($exifBlob['_pii_redacted'] ?? false, 'PII redaction marker must be set');
            $this->assertArrayNotHasKey('Exif/GPSLatitude', $exifBlob);

            // Manifest must still verify even with redacted GPS - the
            // assertion's hash is over the redacted bytes, claim signature
            // covers the assertion hash, all good.
            $decoded = json_decode(ManifestBuilder::toCanonicalJson($signed), true, 512, JSON_THROW_ON_ERROR);
            $resolver = fn (string $kid): ?string => $kid === $kp->kid() ? $kp->publicKey() : null;
            $result = C2paService::verify($decoded, $resolver);
            $this->assertTrue($result['ok'], 'redacted-GPS manifest must still verify: ' . implode(' | ', $result['errors']));
        } finally {
            @unlink((string) $tmp);
        }
    }

    public function test_malformed_sidecar_row_degrades_gracefully(): void
    {
        // Row exists but every field is null - loader must skip every key
        // and return empty arrays so ManifestBuilder doesn't emit empty
        // assertions.
        DB::table('digital_object_metadata')->insert(['digital_object_id' => 70]);
        DB::table('dam_iptc_metadata')->insert(['object_id' => 71]);

        $loader = new StandardMetadataLoader();
        $this->assertSame([], $loader->loadExif(70));
        $this->assertSame([], $loader->loadIptc(70, 71));
        $this->assertSame([], $loader->loadXmp(70, 71));
    }

    public function test_partial_sidecar_still_emits_assertion_with_subset(): void
    {
        // Only camera make + image dims, no GPS, no copyright. Should still
        // emit stds.exif with the keys that exist.
        DB::table('digital_object_metadata')->insert([
            'digital_object_id' => 80,
            'camera_make'       => 'Nikon',
            'image_width'       => 1920,
            'image_height'      => 1080,
        ]);

        $exif = (new StandardMetadataLoader())->loadExif(80);
        $this->assertSame('Nikon', $exif['Exif/Make']);
        $this->assertSame(1920,    $exif['Exif/ImageWidth']);
        $this->assertArrayNotHasKey('Exif/GPSLatitude', $exif);
        $this->assertArrayNotHasKey('Exif/Copyright',   $exif);
    }
}
