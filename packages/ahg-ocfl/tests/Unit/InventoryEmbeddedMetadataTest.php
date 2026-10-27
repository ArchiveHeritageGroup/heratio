<?php

/**
 * InventoryEmbeddedMetadataTest - issue #753.
 *
 * Coverage:
 *   - withExtension() + toJson() round-trip emits the extensions block
 *   - empty-sidecar omits the extension cleanly (no `extensions` key)
 *   - StorageRoot::applyEmbeddedMetadataExtension() honours a stub source
 *   - PII gate redacts GPS-shaped EXIF fields when a pending finding exists
 *   - backfill helper is idempotent (re-running an already-tagged inventory
 *     is a no-op at the bumpInventory layer)
 *   - invalid sidecar JSON degrades gracefully (source returns []; no block)
 *
 * No Laravel boot, no MySQL. Uses the InMemoryOcflAdapter pattern from
 * StorageRootTest plus an in-memory EmbeddedMetadataSource stub.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgOcfl\Tests\Unit;

use AhgOcfl\Layout\ContentAddressing;
use AhgOcfl\Layout\Inventory;
use AhgOcfl\Layout\OcflObject;
use AhgOcfl\Layout\StorageLayout;
use AhgOcfl\Layout\StorageRoot;
use AhgOcfl\Layout\Version;
use AhgOcfl\Metadata\DbEmbeddedMetadataPiiGate;
use AhgOcfl\Metadata\EmbeddedMetadataExtension;
use AhgOcfl\Metadata\EmbeddedMetadataPiiGate;
use AhgOcfl\Metadata\EmbeddedMetadataSource;
use PHPUnit\Framework\TestCase;

/**
 * In-memory metadata source. Lets each test scope a known answer for
 * one or more OCFL object ids without hitting MySQL.
 */
final class FixtureMetadataSource implements EmbeddedMetadataSource
{
    /** @var array<string, array> */
    public array $answers = [];

    public bool $shouldThrow = false;

    public function fetch(string $ocflObjectId): array
    {
        if ($this->shouldThrow) {
            // The contract says implementations MUST NOT throw; the
            // production DbEmbeddedMetadataSource catches and returns
            // []. Mirror that here so the test exercises the
            // "gracefully degraded" branch.
            return [];
        }
        return $this->answers[$ocflObjectId] ?? [];
    }
}

/**
 * Test-side PII gate that flags a given object id as having a pending
 * GPS finding. Lets us exercise the gate path without booting the
 * Schema facade against a real ahg_pii_finding_embedded table.
 */
final class FixturePiiGate implements EmbeddedMetadataPiiGate
{
    /** @var array<string,bool> */
    public array $flagged = [];

    public function redact(string $ocflObjectId, array $block): ?array
    {
        if (! ($this->flagged[$ocflObjectId] ?? false)) {
            return $block;
        }
        // Delegate to the pure helper on the DB-backed gate so we
        // exercise the same redaction logic the production path uses.
        return DbEmbeddedMetadataPiiGate::stripGpsFromBlock($block);
    }
}

final class InventoryEmbeddedMetadataTest extends TestCase
{
    private string $scratchDir;

    protected function setUp(): void
    {
        $this->scratchDir = sys_get_temp_dir().'/ahg-ocfl-ext-'.bin2hex(random_bytes(4));
        @mkdir($this->scratchDir, 0775, true);
    }

    protected function tearDown(): void
    {
        if (! is_dir($this->scratchDir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->scratchDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
        }
        @rmdir($this->scratchDir);
    }

    private function writeTempFile(string $name, string $contents): string
    {
        $path = $this->scratchDir.'/'.$name;
        file_put_contents($path, $contents);
        return $path;
    }

    public function test_inventory_with_extension_round_trips_through_json(): void
    {
        $v1 = new Version(
            created:   '2026-05-27T08:00:00+02:00',
            state:     ['aaa...' => ['photo.jpg']],
            message:   'initial',
            userName:  'tester',
        );
        $inv = Inventory::initial(
            id:        'urn:heratio:io:42',
            v1:        $v1,
            manifest:  ['aaa...' => ['v1/content/photo.jpg']],
        );

        $block = EmbeddedMetadataExtension::build(
            [
                'exif' => ['Make' => 'Nikon', 'Model' => 'D850'],
                'iptc' => ['byline' => 'J. Pieterse'],
            ],
            extractorVersion: 'ahg-metadata-extraction@1.0',
            capturedAt: new \DateTimeImmutable('2026-05-27T08:00:00+02:00'),
        );
        $this->assertNotNull($block, 'build() should return a populated block');

        $inv2 = $inv->withExtension(EmbeddedMetadataExtension::NAME, $block);

        $json = $inv2->toJson();
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('extensions', $decoded);
        $this->assertArrayHasKey('ahg-embedded-metadata', $decoded['extensions']);
        $this->assertSame('Nikon', $decoded['extensions']['ahg-embedded-metadata']['exif']['Make']);
        $this->assertSame('J. Pieterse', $decoded['extensions']['ahg-embedded-metadata']['iptc']['byline']);
        $this->assertSame('ahg-metadata-extraction@1.0', $decoded['extensions']['ahg-embedded-metadata']['extractor_version']);

        // Top-level alpha-sort must place `extensions` between
        // `digestAlgorithm` and `head`.
        $keys = array_keys($decoded);
        $this->assertSame(
            ['digestAlgorithm', 'extensions', 'head', 'id', 'manifest', 'type', 'versions'],
            $keys,
        );

        // Byte-for-byte round-trip with the extension.
        $reparsed = Inventory::fromJson($json);
        $this->assertSame($json, $reparsed->toJson(), 'Round-trip with extensions must be byte-stable.');
        $this->assertTrue($reparsed->hasExtension('ahg-embedded-metadata'));
    }

    public function test_empty_sidecar_omits_extensions_key_cleanly(): void
    {
        $v1 = new Version(
            created: '2026-05-27T08:00:00+02:00',
            state:   ['aaa...' => ['photo.jpg']],
            message: 'initial',
        );
        $inv = Inventory::initial(
            id:       'urn:heratio:io:1',
            v1:       $v1,
            manifest: ['aaa...' => ['v1/content/photo.jpg']],
        );

        // build() with all blocks empty must return null.
        $built = EmbeddedMetadataExtension::build(['exif' => [], 'iptc' => [], 'xmp' => []]);
        $this->assertNull($built, 'Empty sidecar must produce null block.');

        $decoded = json_decode($inv->toJson(), true);
        $this->assertArrayNotHasKey('extensions', $decoded, 'No `extensions` key when block omitted.');
    }

    public function test_storage_root_emits_extension_when_source_wired(): void
    {
        $adapter = new InMemoryOcflAdapter();
        $root    = new StorageRoot($adapter, StorageLayout::FLAT_ID, ContentAddressing::ALG_SHA512);

        $src = new FixtureMetadataSource();
        $src->answers['urn:heratio:io:42'] = [
            'exif' => ['Make' => 'Nikon', 'Model' => 'D850'],
            'iptc' => ['byline' => 'J. Pieterse'],
        ];
        $root->withEmbeddedMetadataSource($src);

        $local = $this->writeTempFile('master.jpg', random_bytes(256));
        $obj   = OcflObject::fresh('urn:heratio:io:42');
        $obj->stageContent('master.jpg', $local);

        $inv = $root->write($obj, 'ingest', 'tester', null);

        $this->assertTrue($inv->hasExtension('ahg-embedded-metadata'),
            'Wired source + populated sidecar must yield an extension block on the new inventory.');
        $payload = $inv->getExtension('ahg-embedded-metadata');
        $this->assertNotNull($payload);
        $this->assertSame('Nikon', $payload['exif']['Make']);

        // On-disk inventory.json must carry the block too.
        $objectRoot = $root->objectRoot($inv->id);
        $reparsed = Inventory::fromJson($adapter->files[$objectRoot.'/inventory.json']);
        $this->assertTrue($reparsed->hasExtension('ahg-embedded-metadata'));
    }

    public function test_pii_gate_strips_gps_when_flagged(): void
    {
        $adapter = new InMemoryOcflAdapter();
        $root    = new StorageRoot($adapter, StorageLayout::FLAT_ID, ContentAddressing::ALG_SHA512);

        $src = new FixtureMetadataSource();
        $src->answers['urn:heratio:io:7'] = [
            'exif' => [
                'Make'           => 'Nikon',
                'Model'          => 'D850',
                'GPSLatitude'    => '-26.20',
                'GPSLongitude'   => '28.04',
                'GPSAltitude'    => '1750',
            ],
        ];

        $gate = new FixturePiiGate();
        $gate->flagged['urn:heratio:io:7'] = true;

        $root->withEmbeddedMetadataSource($src)->withPiiGate($gate);

        $local = $this->writeTempFile('a.jpg', "with gps\n");
        $obj   = OcflObject::fresh('urn:heratio:io:7');
        $obj->stageContent('a.jpg', $local);

        $inv = $root->write($obj, 'ingest', 'tester', null);

        $payload = $inv->getExtension('ahg-embedded-metadata');
        $this->assertNotNull($payload);
        $this->assertArrayHasKey('exif', $payload);
        $this->assertSame('Nikon', $payload['exif']['Make']);
        $this->assertArrayNotHasKey('GPSLatitude', $payload['exif']);
        $this->assertArrayNotHasKey('GPSLongitude', $payload['exif']);
        $this->assertArrayNotHasKey('GPSAltitude', $payload['exif']);
    }

    public function test_pii_gate_passive_when_unflagged(): void
    {
        $adapter = new InMemoryOcflAdapter();
        $root    = new StorageRoot($adapter, StorageLayout::FLAT_ID, ContentAddressing::ALG_SHA512);

        $src = new FixtureMetadataSource();
        $src->answers['urn:heratio:io:8'] = [
            'exif' => ['Make' => 'Nikon', 'GPSLatitude' => '-26.20'],
        ];
        $gate = new FixturePiiGate(); // nothing flagged

        $root->withEmbeddedMetadataSource($src)->withPiiGate($gate);

        $local = $this->writeTempFile('b.jpg', "no gate\n");
        $obj   = OcflObject::fresh('urn:heratio:io:8');
        $obj->stageContent('b.jpg', $local);

        $inv = $root->write($obj, 'ingest', 'tester', null);
        $payload = $inv->getExtension('ahg-embedded-metadata');

        // Unflagged objects keep GPS.
        $this->assertArrayHasKey('GPSLatitude', $payload['exif']);
    }

    public function test_strip_gps_helper_drops_all_gps_shaped_keys(): void
    {
        $block = [
            'exif' => [
                'Make'              => 'Canon',
                'GPSLatitude'       => '0.0',
                'GPSLongitude'      => '0.0',
                'GPSImgDirection'   => '180',
                'Geolocation'       => 'home',
                'LocationCreated'   => '...',
            ],
            'iptc' => ['byline' => 'J.P.'],
        ];
        $out = DbEmbeddedMetadataPiiGate::stripGpsFromBlock($block);

        $this->assertSame(['Make' => 'Canon'], $out['exif']);
        $this->assertSame(['byline' => 'J.P.'], $out['iptc']);
    }

    public function test_strip_gps_returns_null_when_block_emptied(): void
    {
        // EXIF was all-GPS; no IPTC / XMP siblings; helper must return
        // null so the caller drops the extension entirely.
        $out = DbEmbeddedMetadataPiiGate::stripGpsFromBlock([
            'exif' => ['GPSLatitude' => '0.0', 'GPSLongitude' => '0.0'],
        ]);
        $this->assertNull($out);
    }

    public function test_source_failure_is_swallowed_and_extension_omitted(): void
    {
        $adapter = new InMemoryOcflAdapter();
        $root    = new StorageRoot($adapter, StorageLayout::FLAT_ID, ContentAddressing::ALG_SHA512);

        $src = new FixtureMetadataSource();
        $src->shouldThrow = true; // resolver yields [] (degraded path)

        $root->withEmbeddedMetadataSource($src);

        $local = $this->writeTempFile('c.jpg', "no metadata\n");
        $obj   = OcflObject::fresh('urn:heratio:io:99');
        $obj->stageContent('c.jpg', $local);

        $inv = $root->write($obj, 'ingest', 'tester', null);
        $this->assertFalse($inv->hasExtension('ahg-embedded-metadata'),
            'Degraded sidecar must not produce an extension block.');
    }

    public function test_apply_helper_is_idempotent_when_already_tagged(): void
    {
        $adapter = new InMemoryOcflAdapter();
        $root    = new StorageRoot($adapter, StorageLayout::FLAT_ID, ContentAddressing::ALG_SHA512);

        $src = new FixtureMetadataSource();
        $src->answers['urn:heratio:io:13'] = [
            'exif' => ['Make' => 'Nikon'],
        ];
        $root->withEmbeddedMetadataSource($src);

        $local = $this->writeTempFile('d.jpg', "rev1\n");
        $obj   = OcflObject::fresh('urn:heratio:io:13');
        $obj->stageContent('d.jpg', $local);
        $inv1  = $root->write($obj, 'ingest', 'tester', null);

        // Apply the helper a second time to the same inventory: same
        // block content, same JSON bytes. Idempotent at this layer.
        $inv2 = $root->applyEmbeddedMetadataExtension($inv1, $inv1->id);
        $this->assertSame($inv1->toJson(), $inv2->toJson(),
            'Applying the same block twice must yield byte-identical inventory JSON.');
    }

    private const XMP_PACKET = <<<'XML'
<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
 <rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:exif="http://ns.adobe.com/exif/1.0/"
   xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/" exif:GPSLatitude="33,55.2S">
  <dc:title><rdf:Alt><rdf:li xml:lang="x-default">Wild mushroom</rdf:li><rdf:li xml:lang="af">Wilde sampioen</rdf:li></rdf:Alt></dc:title>
  <dc:creator><rdf:Seq><rdf:li>First</rdf:li><rdf:li>Second</rdf:li></rdf:Seq></dc:creator>
  <Iptc4xmpExt:LocationShown><rdf:Bag><rdf:li Iptc4xmpExt:City="Cape Town" exif:GPSLongitude="18,25.4E"/></rdf:Bag></Iptc4xmpExt:LocationShown>
 </rdf:Description>
</rdf:RDF></x:xmpmeta>
XML;

    /** heratio#1557: the packet is kept as JSON-LD next to the flat xmp block. */
    public function test_xmp_packets_become_a_json_ld_block_that_survives_the_inventory(): void
    {
        $block = EmbeddedMetadataExtension::build(
            ['xmp' => ['dc:title' => 'Wild mushroom'], 'xmp_packets' => [77 => self::XMP_PACKET, 78 => self::XMP_PACKET]],
            capturedAt: new \DateTimeImmutable('2026-10-27T08:00:00+02:00'),
        );

        $this->assertSame('Wild mushroom', $block['xmp']['dc:title'], 'the flat block stays for existing readers');
        $this->assertSame('ahg-metadata-extraction@0.2', $block['extractor_version']);
        $ld = $block['xmp_jsonld'];
        $this->assertSame('urn:heratio:digital-object:77', $ld['@graph'][0]['@id'], 'each file is its own node');
        $this->assertSame('urn:heratio:digital-object:78', $ld['@graph'][1]['@id']);
        $this->assertSame(['@value' => 'Wilde sampioen', '@language' => 'af'], $ld['@graph'][0]['dc:title']['rdf:_2']);
        $this->assertSame(['First', 'Second'], $ld['@graph'][0]['dc:creator']['@list']);

        $v1 = new Version(created: '2026-10-27T08:00:00+02:00', state: ['aaa...' => ['photo.jpg']], message: 'initial', userName: 'tester');
        $inv = Inventory::initial(id: 'urn:heratio:io:42', v1: $v1, manifest: ['aaa...' => ['v1/content/photo.jpg']])
            ->withExtension(EmbeddedMetadataExtension::NAME, $block);
        $json = $inv->toJson();
        $this->assertSame($json, Inventory::fromJson($json)->toJson(), 'byte-stable with the JSON-LD block');
        $this->assertSame(['First', 'Second'],
            json_decode($json, true)['extensions']['ahg-embedded-metadata']['xmp_jsonld']['@graph'][0]['dc:creator']['@list'], 'list order survives');
    }

    /** The gate reaches GPS in both XMP blocks, even with no EXIF block at all. */
    public function test_pii_gate_strips_gps_from_both_xmp_blocks(): void
    {
        $block = EmbeddedMetadataExtension::build([
            'xmp' => ['exif:GPSLatitude' => '33,55.2S', 'dc:title' => 'Wild mushroom'],
            'xmp_packets' => [77 => self::XMP_PACKET],
        ]);
        $out = DbEmbeddedMetadataPiiGate::stripGpsFromBlock($block);

        $this->assertSame(['dc:title' => 'Wild mushroom'], $out['xmp']);
        $this->assertArrayNotHasKey('exif:GPSLatitude', $out['xmp_jsonld']);
        $this->assertArrayNotHasKey('exif:GPSLongitude', $out['xmp_jsonld']['Iptc4xmpExt:LocationShown']['@set'][0], 'GPS inside a structure');
        $this->assertSame('Cape Town', $out['xmp_jsonld']['Iptc4xmpExt:LocationShown']['@set'][0]['Iptc4xmpExt:City']);
    }
}
