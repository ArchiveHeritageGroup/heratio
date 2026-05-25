<?php

declare(strict_types=1);

namespace AhgInferenceReceipts\Tests\Unit;

use AhgInferenceReceipts\KeyPair;
use PHPUnit\Framework\TestCase;

final class KeyPairTest extends TestCase
{
    public function testGenerateProducesValidPair(): void
    {
        $kp = KeyPair::generate();

        $this->assertSame(SODIUM_CRYPTO_SIGN_SECRETKEYBYTES, strlen($kp->secretKey()));
        $this->assertSame(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, strlen($kp->publicKey()));
        $this->assertSame(64, strlen($kp->publicKeyHex()));
    }

    public function testFromSecretKeyRecoversSamePublicKey(): void
    {
        $kp1 = KeyPair::generate();
        $kp2 = KeyPair::fromSecretKey($kp1->secretKey());

        $this->assertSame($kp1->publicKey(), $kp2->publicKey());
        $this->assertSame($kp1->kid(), $kp2->kid());
    }

    public function testKidIsStable(): void
    {
        $kp = KeyPair::generate();
        $kid1 = $kp->kid();
        $kid2 = $kp->kid();

        $this->assertSame($kid1, $kid2);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $kid1);
    }

    public function testKidIsDeterministicFromPublicKey(): void
    {
        $kp1 = KeyPair::generate();
        $kp2 = KeyPair::fromSecretKey($kp1->secretKey());

        $this->assertSame($kp1->kid(), $kp2->kid());
    }

    public function testKidDiffersAcrossKeyPairs(): void
    {
        $kp1 = KeyPair::generate();
        $kp2 = KeyPair::generate();

        $this->assertNotSame($kp1->kid(), $kp2->kid());
    }

    public function testBase64Roundtrip(): void
    {
        $kp = KeyPair::generate();
        $decoded = base64_decode($kp->publicKeyBase64(), true);

        $this->assertSame($kp->publicKey(), $decoded);
    }

    public function testBase64UrlIsUrlSafe(): void
    {
        $kp = KeyPair::generate();
        $b64u = $kp->publicKeyBase64Url();

        $this->assertStringNotContainsString('+', $b64u);
        $this->assertStringNotContainsString('/', $b64u);
        $this->assertStringNotContainsString('=', $b64u);
    }

    public function testSaveAndLoadRoundtrip(): void
    {
        $kp = KeyPair::generate();
        $dir = sys_get_temp_dir() . '/ahg-inference-receipts-' . bin2hex(random_bytes(4));
        $secretPath = $dir . '/inference-signing.sk';
        $publicPath = $dir . '/inference-signing.pk';

        try {
            $kp->saveTo($secretPath, $publicPath);

            $this->assertFileExists($secretPath);
            $this->assertFileExists($publicPath);

            $loaded = KeyPair::loadFrom($secretPath);
            $this->assertSame($kp->publicKey(), $loaded->publicKey());
            $this->assertSame($kp->kid(), $loaded->kid());
        } finally {
            @unlink($secretPath);
            @unlink($publicPath);
            @rmdir($dir);
        }
    }
}
