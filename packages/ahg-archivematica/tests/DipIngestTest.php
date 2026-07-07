<?php

/**
 * DipIngestTest - unit tests for the DIP METS parser + matcher.
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

namespace AhgArchivematica\Tests;

use AhgArchivematica\Services\ArchivematicaSsClient;
use AhgArchivematica\Services\DipMatcher;
use AhgArchivematica\Services\Mets\MetsParser;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests: the METS parser and the matcher's candidate-extraction are
 * framework-free, so these run without booting Laravel or touching a DB. The
 * Storage Service client is exercised as a mock to show the ingest pipeline
 * depends only on its public contract.
 */
class DipIngestTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/fixtures/sample-dip/METS.xml';

    private function parseFixture(): array
    {
        return (new MetsParser())->parseFile(self::FIXTURE);
    }

    public function testMetsParserExtractsObjidAndDublinCore(): void
    {
        $parsed = $this->parseFixture();

        $this->assertSame('f0c9a5b1-1111-4a2b-8c3d-000000000001', $parsed['objid']);
        $this->assertSame('Minutes of the founding meeting', $parsed['dublin_core']['title']);
        $this->assertSame('ZA-HER-2026-0001', $parsed['dublin_core']['identifier']);
        $this->assertSame('1926-03-14', $parsed['dublin_core']['date']);
    }

    public function testMetsParserExtractsAccessFileWithFixity(): void
    {
        $parsed = $this->parseFixture();

        $this->assertCount(1, $parsed['access_files']);
        $file = $parsed['access_files'][0];

        $this->assertSame('objects/access/minutes-access.jpg', $file['href']);
        $this->assertSame('image/jpeg', $file['mimetype']);
        $this->assertSame('SHA-256', $file['checksum_type']);
        $this->assertSame(
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            $file['checksum']
        );
        $this->assertSame(1024, $file['size']);
        $this->assertSame('techMD_1', $file['admid']);
    }

    public function testMetsParserExtractsPremisObject(): void
    {
        $parsed = $this->parseFixture();

        $this->assertCount(1, $parsed['premis']);
        $premis = $parsed['premis'][0];

        $this->assertSame('sha256', $premis['message_digest_algorithm']);
        $this->assertSame(
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            $premis['message_digest']
        );
        $this->assertSame('fmt/44', $premis['puid']);
        $this->assertSame('minutes-access.jpg', $premis['original_name']);
        $this->assertSame('techMD_1', $premis['admid']);
    }

    public function testMatcherExtractsUuidCandidates(): void
    {
        $parsed = $this->parseFixture();
        $candidates = (new DipMatcher())->extractUuidCandidates($parsed);

        // Both the METS OBJID (AIP UUID) and the PREMIS object UUID qualify.
        $this->assertContains('f0c9a5b1-1111-4a2b-8c3d-000000000001', $candidates);
        $this->assertContains('a1b2c3d4-2222-4a2b-8c3d-000000000002', $candidates);
        // The plain-text identifier is NOT a UUID and must be excluded.
        $this->assertNotContains('za-her-2026-0001', $candidates);
    }

    public function testMatcherExtractsIdentifier(): void
    {
        $parsed = $this->parseFixture();

        $this->assertSame('ZA-HER-2026-0001', (new DipMatcher())->extractIdentifier($parsed));
    }

    public function testSsClientBuildsApiKeyAuthHeader(): void
    {
        $this->assertSame(
            'ApiKey am-user:secret-key',
            ArchivematicaSsClient::buildAuthHeader('am-user', 'secret-key')
        );
    }

    public function testSsClientContractIsMockable(): void
    {
        // The ingest pipeline only depends on the SS client's public surface,
        // so it can be fully faked without hitting a real Archivematica host.
        $ss = $this->createMock(ArchivematicaSsClient::class);
        $ss->method('listDipPackages')->willReturn([
            ['uuid' => 'f0c9a5b1-1111-4a2b-8c3d-000000000001', 'package_type' => 'DIP'],
        ]);
        $ss->method('isConfigured')->willReturn(true);

        $packages = $ss->listDipPackages();

        $this->assertTrue($ss->isConfigured());
        $this->assertCount(1, $packages);
        $this->assertSame('f0c9a5b1-1111-4a2b-8c3d-000000000001', $packages[0]['uuid']);
    }
}
