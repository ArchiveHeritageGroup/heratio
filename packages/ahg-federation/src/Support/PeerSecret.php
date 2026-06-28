<?php

namespace AhgFederation\Support;

use Illuminate\Support\Facades\Crypt;

/**
 * #1380 — at-rest encryption for federation peer credentials
 * (federation_peer.api_key, federation_peer_search.search_api_key).
 *
 * Keys are wrapped with Laravel's Crypt (APP_KEY / AES-256) before they
 * are written and unwrapped at the point they're used to build an
 * outbound X-API-Key header. Decryption is back-compatible: rows that
 * predate this change hold plaintext, which Crypt::decryptString cannot
 * parse, so `decrypt()` falls back to returning the raw value verbatim.
 * That keeps existing peers working until they are next saved (which
 * re-writes the value encrypted).
 */
final class PeerSecret
{
    /**
     * Encrypt a secret for storage. Empty / null values pass through
     * unchanged so we never store an encrypted empty string (callers and
     * COALESCE fallbacks treat NULL/'' as "no key").
     */
    public static function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return Crypt::encryptString($value);
    }

    /**
     * Decrypt a stored secret, falling back to the raw value when it isn't
     * a valid ciphertext (legacy plaintext rows). Null/empty pass through.
     */
    public static function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }
}
