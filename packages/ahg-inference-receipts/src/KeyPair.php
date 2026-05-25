<?php

declare(strict_types=1);

namespace AhgInferenceReceipts;

use RuntimeException;
use InvalidArgumentException;
use SodiumException;

/**
 * Ed25519 key pair held in libsodium binary form.
 *
 * - generate(): fresh 32-byte secret + 32-byte public via ext-sodium
 * - fromSecretKey(): rebuild from a stored 64-byte secret-key (libsodium concat)
 * - kid(): SHA-256(publicKey) truncated to 16 hex chars - a stable short id
 *   that travels with every signed receipt.
 *
 * Storage helpers write the secret key 0600 and the public key 0644.
 * No PEM here; raw bytes + hex are simpler and equally interchangeable
 * with any sodium-compatible verifier.
 */
final class KeyPair
{
    /** @var string raw 64-byte libsodium secret key (includes public key suffix) */
    private string $secretKey;

    /** @var string raw 32-byte libsodium public key */
    private string $publicKey;

    private function __construct(string $secretKey, string $publicKey)
    {
        if (strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new InvalidArgumentException('KeyPair: secret key must be ' . SODIUM_CRYPTO_SIGN_SECRETKEYBYTES . ' bytes');
        }
        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new InvalidArgumentException('KeyPair: public key must be ' . SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES . ' bytes');
        }
        $this->secretKey = $secretKey;
        $this->publicKey = $publicKey;
    }

    public static function generate(): self
    {
        try {
            $kp = sodium_crypto_sign_keypair();
            $sk = sodium_crypto_sign_secretkey($kp);
            $pk = sodium_crypto_sign_publickey($kp);
        } catch (SodiumException $e) {
            throw new RuntimeException('KeyPair: sodium failure: ' . $e->getMessage(), 0, $e);
        }

        return new self($sk, $pk);
    }

    public static function fromSecretKey(string $secretKey): self
    {
        if (strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new InvalidArgumentException('KeyPair: secret key must be ' . SODIUM_CRYPTO_SIGN_SECRETKEYBYTES . ' bytes');
        }
        try {
            $pk = sodium_crypto_sign_publickey_from_secretkey($secretKey);
        } catch (SodiumException $e) {
            throw new RuntimeException('KeyPair: sodium failure deriving public key: ' . $e->getMessage(), 0, $e);
        }

        return new self($secretKey, $pk);
    }

    public function secretKey(): string
    {
        return $this->secretKey;
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    public function publicKeyHex(): string
    {
        return bin2hex($this->publicKey);
    }

    public function publicKeyBase64(): string
    {
        return base64_encode($this->publicKey);
    }

    public function publicKeyBase64Url(): string
    {
        return rtrim(strtr(base64_encode($this->publicKey), '+/', '-_'), '=');
    }

    /**
     * Stable short key id - first 16 hex chars of SHA-256 over the public key.
     * Travels with every signed receipt; lets verifiers look up the right key.
     */
    public function kid(): string
    {
        return substr(hash('sha256', $this->publicKey), 0, 16);
    }

    public function saveTo(string $secretPath, string $publicPath): void
    {
        $secretDir = dirname($secretPath);
        $publicDir = dirname($publicPath);
        if (!is_dir($secretDir) && !mkdir($secretDir, 0700, true) && !is_dir($secretDir)) {
            throw new RuntimeException("KeyPair: cannot create directory {$secretDir}");
        }
        if (!is_dir($publicDir) && !mkdir($publicDir, 0755, true) && !is_dir($publicDir)) {
            throw new RuntimeException("KeyPair: cannot create directory {$publicDir}");
        }

        if (file_put_contents($secretPath, $this->secretKey, LOCK_EX) === false) {
            throw new RuntimeException("KeyPair: cannot write secret key to {$secretPath}");
        }
        @chmod($secretPath, 0600);

        if (file_put_contents($publicPath, $this->publicKey, LOCK_EX) === false) {
            throw new RuntimeException("KeyPair: cannot write public key to {$publicPath}");
        }
        @chmod($publicPath, 0644);
    }

    public static function loadFrom(string $secretPath): self
    {
        if (!is_readable($secretPath)) {
            throw new RuntimeException("KeyPair: cannot read secret key from {$secretPath}");
        }
        $sk = file_get_contents($secretPath);
        if ($sk === false || strlen($sk) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException("KeyPair: secret key at {$secretPath} is invalid");
        }
        return self::fromSecretKey($sk);
    }
}
