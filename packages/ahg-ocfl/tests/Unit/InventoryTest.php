<?php

/**
 * InventoryTest - deterministic JSON round-trip + ordering guarantees.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgOcfl\Tests\Unit;

use AhgOcfl\Layout\Inventory;
use AhgOcfl\Layout\Version;
use PHPUnit\Framework\TestCase;

final class InventoryTest extends TestCase
{
    public function test_round_trip_is_deterministic(): void
    {
        $v1 = new Version(
            created:   '2026-05-26T08:00:00+02:00',
            state:     [
                'zzz...' => ['photo.jpg'],
                'aaa...' => ['document.pdf'],
            ],
            message:   'initial ingest',
            userName:  'johan',
            userAddress: 'mailto:johan@theahg.co.za',
        );

        $inv = Inventory::initial(
            id:              'urn:heratio:io:42',
            v1:              $v1,
            manifest:        [
                'zzz...' => ['v1/content/photo.jpg'],
                'aaa...' => ['v1/content/document.pdf'],
            ],
            digestAlgorithm: 'sha512',
        );

        $json1 = $inv->toJson();
        $json2 = Inventory::fromJson($json1)->toJson();

        $this->assertSame(
            $json1,
            $json2,
            'Inventory must round-trip JSON byte-for-byte (sorted keys, stable indent).',
        );

        // Manifest digest keys are sorted in the output, regardless of
        // the order the caller supplied them.
        $decoded = json_decode($json1, true);
        $this->assertSame(['aaa...', 'zzz...'], array_keys($decoded['manifest']));
        $this->assertSame(['aaa...', 'zzz...'], array_keys($decoded['versions']['v1']['state']));

        // Top-level keys are alpha-sorted too (matches OCFL spec example
        // output and keeps two implementations byte-identical).
        $this->assertSame(
            ['digestAlgorithm', 'head', 'id', 'manifest', 'type', 'versions'],
            array_keys($decoded),
        );
    }

    public function test_with_new_version_appends_v2_and_reuses_content(): void
    {
        $v1 = new Version(
            created: '2026-05-26T08:00:00+02:00',
            state:   ['aaa...' => ['document.pdf']],
            message: 'v1',
        );
        $inv = Inventory::initial('urn:heratio:io:42', $v1, [
            'aaa...' => ['v1/content/document.pdf'],
        ]);

        $v2 = new Version(
            created: '2026-05-26T09:00:00+02:00',
            state:   [
                'aaa...' => ['document.pdf'],
                'bbb...' => ['extra-page.jpg'],
            ],
            message: 'add scan',
        );
        $inv2 = $inv->withNewVersion($v2, [
            'bbb...' => ['v2/content/extra-page.jpg'],
        ]);

        $this->assertSame('v2', $inv2->head);
        $this->assertArrayHasKey('aaa...', $inv2->manifest);
        $this->assertSame(['v1/content/document.pdf'], $inv2->manifest['aaa...'],
            'Existing digest must keep its original content path (OCFL §3.5.3.1 reuse).');
        $this->assertArrayHasKey('bbb...', $inv2->manifest);
        $this->assertSame(['v2/content/extra-page.jpg'], $inv2->manifest['bbb...']);
    }

    public function test_constructor_rejects_missing_head_version(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Inventory(
            id:       'urn:heratio:io:1',
            head:     'v5',
            manifest: [],
            versions: ['v1' => Version::now([], '')],
        );
    }
}
