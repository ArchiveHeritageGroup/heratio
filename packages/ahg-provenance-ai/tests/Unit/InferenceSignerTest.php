<?php

/**
 * InferenceSignerTest - Unit test for Heratio
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

namespace AhgProvenanceAi\Tests\Unit;

use AhgProvenanceAi\Services\InferenceSigner;
use PHPUnit\Framework\TestCase;

/**
 * heratio#136 - Ed25519 inference-manifest signing. Pure unit test: the
 * signer is given a temp key directory so no Laravel bootstrap is needed.
 */
class InferenceSignerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ahg-sign-test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
    }

    public function testSigningIsOptInAndReturnsNullBeforeKeygen(): void
    {
        $signer = new InferenceSigner($this->tmpDir);

        $this->assertFalse($signer->isEnabled());
        $this->assertNull($signer->keyId());
        $this->assertNull($signer->sign(['id' => 1]));
    }

    public function testGenerateSignVerifyRoundTrip(): void
    {
        $signer = new InferenceSigner($this->tmpDir);
        $keyId  = $signer->generateKeypair();

        $this->assertStringStartsWith('ed25519:', $keyId);
        $this->assertTrue($signer->isEnabled());
        $this->assertSame($keyId, $signer->keyId());

        $manifest = [
            'id' => 42, 'uuid' => 'abc-123', 'input_hash' => 'aa', 'output_hash' => 'bb',
            'confidence' => 0.91, 'service_name' => 'NER',
        ];
        $signature = $signer->sign($manifest);

        $this->assertIsString($signature);
        $this->assertTrue($signer->verify($signature, $manifest, $signer->publicKey()));
    }

    public function testVerifyFailsOnTamperedManifest(): void
    {
        $signer = new InferenceSigner($this->tmpDir);
        $signer->generateKeypair();

        $manifest  = ['id' => 42, 'output_hash' => 'bb'];
        $signature = $signer->sign($manifest);

        $tampered = ['id' => 42, 'output_hash' => 'CC'];
        $this->assertFalse($signer->verify($signature, $tampered, $signer->publicKey()));
    }

    public function testVerifyFailsAgainstWrongPublicKey(): void
    {
        $signer = new InferenceSigner($this->tmpDir);
        $signer->generateKeypair();

        $manifest  = ['id' => 7];
        $signature = $signer->sign($manifest);

        $wrongPublicKey = sodium_crypto_sign_publickey(sodium_crypto_sign_keypair());
        $this->assertFalse($signer->verify($signature, $manifest, $wrongPublicKey));
    }

    public function testCanonicalizeIsKeyOrderIndependent(): void
    {
        $signer = new InferenceSigner($this->tmpDir);

        $this->assertSame(
            $signer->canonicalize(['b' => 2, 'a' => 1, 'c' => ['z' => 9, 'y' => 8]]),
            $signer->canonicalize(['c' => ['y' => 8, 'z' => 9], 'a' => 1, 'b' => 2])
        );
    }

    public function testKeygenRefusesToClobberExistingKeyWithoutForce(): void
    {
        $signer = new InferenceSigner($this->tmpDir);
        $first  = $signer->generateKeypair();

        try {
            $signer->generateKeypair();
            $this->fail('Expected generateKeypair() to refuse overwriting an existing key.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already exists', $e->getMessage());
        }

        $regenerated = $signer->generateKeypair(true);
        $this->assertStringStartsWith('ed25519:', $regenerated);
        $this->assertNotSame($first, $regenerated, 'force regeneration mints a fresh keypair');
    }
}
