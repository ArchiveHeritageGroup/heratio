<?php
/**
 * Heratio - hash-chain writer for ahg_audit_log rows (issue #676 Phase 5).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAuditTrail\Services;

use AhgInferenceReceipts\JcsEncoder;
use AhgInferenceReceipts\KeyPair;
use AhgInferenceReceipts\Receipt;
use AhgInferenceReceipts\Signer;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wraps a single insert into ahg_audit_log with:
 *
 *   - a row-level DB transaction
 *   - a SELECT ... FOR UPDATE on the current head's (seq, entry_hash) so
 *     concurrent writers serialise on the chain tip
 *   - RFC 8785 JCS canonicalisation of the signing view
 *   - SHA-256(JCS) -> entry_hash (hex)
 *   - Ed25519 detached signature over hex2bin(entry_hash) -> base64 signature
 *   - kid copied from the active signing key (the SAME key used by
 *     ahg-ai-compliance for EU AI Act Article 12 inference receipts)
 *
 * If the signing key is not available (fresh install, key path unreadable,
 * libsodium missing) the writer falls back to unsigned-mode: the row is
 * still inserted with the legacy non-chained columns, chain columns NULL,
 * and a single warning is emitted to the application log. This keeps the
 * existing AuditLogger contract (returning the inserted id, never breaking
 * the calling code path).
 */
final class ChainedAuditWriter
{
    /** @var string sentinel used in the chain when no head row exists yet */
    private const GENESIS_PREV_HASH = Receipt::GENESIS_PREV_HASH;

    /** @var bool one-shot guard so we only warn once per process about an unsigned fallback */
    private static bool $fallbackWarned = false;

    /** @var Signer|null lazily resolved per request */
    private ?Signer $signer = null;

    /** @var string|null cached kid for the current Signer */
    private ?string $kid = null;

    /** @var bool true once we have tried (and possibly failed) to resolve the signer */
    private bool $signerResolved = false;

    /**
     * @param array<string,mixed> $row complete row about to be written (incl. uuid + context cols)
     * @return int|null inserted id, or null on insert failure
     */
    public function append(array $row): ?int
    {
        $this->resolveSigner();

        if ($this->signer === null) {
            // Unsigned fallback - chain columns left NULL, behaviour matches
            // pre-#676-phase-5 inserts so the legacy backlog continues to grow.
            return $this->insertUnsigned($row);
        }

        try {
            return DB::transaction(function () use ($row) {
                $head = DB::table('ahg_audit_log')
                    ->whereNotNull('seq')
                    ->orderByDesc('seq')
                    ->lockForUpdate()
                    ->first(['seq', 'entry_hash']);

                $seq = $head === null ? 0 : ((int) $head->seq) + 1;
                $prevHash = $head === null ? self::GENESIS_PREV_HASH : (string) $head->entry_hash;

                // Pin created_at to a UTC second-precision string we control,
                // overriding any Carbon `now()` the caller already set. The
                // verifier rebuilds the signing-view ts from this exact value
                // so the signature stays reproducible.
                $ts = $this->utcTimestamp();
                $row['created_at'] = str_replace(['T', 'Z'], [' ', ''], substr($ts, 0, 19));

                $payload = $this->payloadFromRow($row);

                $signingView = [
                    'v'         => Receipt::VERSION,
                    'seq'       => $seq,
                    'ts'        => $ts,
                    'prev_hash' => $prevHash,
                    'payload'   => $payload,
                    'kid'       => $this->kid,
                    'alg'       => Receipt::ALG,
                ];

                $entryHash = hash('sha256', JcsEncoder::encode($signingView));
                $signature = $this->signer->signBase64(hex2bin($entryHash));

                $row['seq']        = $seq;
                $row['prev_hash']  = $prevHash;
                $row['entry_hash'] = $entryHash;
                $row['signature']  = $signature;
                $row['kid']        = $this->kid;

                return (int) DB::table('ahg_audit_log')->insertGetId($row);
            });
        } catch (Throwable $e) {
            // Defence in depth: even with a transaction the insert might fail
            // (UNIQUE collision on entry_hash, DB outage, etc). Drop down to
            // unsigned-mode so the caller still gets a row id where possible,
            // matching the AuditLogger "never break the call path" contract.
            Log::warning('audit-trail: chained insert failed, falling back to unsigned row', [
                'error' => $e->getMessage(),
            ]);
            return $this->insertUnsigned($row);
        }
    }

    /**
     * Build the canonical signing payload from the row about to be inserted.
     * Any column that IS a chain column or a row-id is excluded; everything
     * else - including JSON columns, which we decode back into PHP arrays
     * so the JCS encoder sees structured data rather than opaque strings - is
     * in scope.
     *
     * @return array<string,mixed>
     */
    /**
     * Build the canonical signing payload.
     *
     * Rules (must match VerifyChainCommand::payloadFromRow):
     *   - exclude row-id + chain columns + created_at (ts is signed separately)
     *   - drop keys whose value is NULL (schema defaults vary between writers
     *     and reads; only PRESENT data is signed)
     *   - decode JSON-typed string values back into PHP arrays so JCS sees
     *     structured data, not opaque strings
     *   - normalise DateTimeInterface to ISO second-precision UTC
     */
    private function payloadFromRow(array $row): array
    {
        static $excluded = ['id', 'seq', 'prev_hash', 'entry_hash', 'signature', 'kid', 'created_at'];

        $payload = [];
        foreach ($row as $k => $v) {
            if (in_array($k, $excluded, true)) {
                continue;
            }
            if ($v === null) {
                continue;
            }
            if (is_string($v) && $v !== '' && ($v[0] === '{' || $v[0] === '[')) {
                $decoded = json_decode($v, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload[$k] = $decoded;
                    continue;
                }
            }
            if ($v instanceof \DateTimeInterface) {
                $payload[$k] = $v->format('Y-m-d\TH:i:s\Z');
                continue;
            }
            $payload[$k] = $v;
        }

        return $payload;
    }

    private function utcTimestamp(): string
    {
        // Second-precision UTC ISO-8601. We deliberately use seconds rather
        // than milliseconds so we can round-trip cleanly through a MySQL
        // TIMESTAMP column (no separate "signed_ts" column required).
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Insert with NULL chain columns. Used both as the no-key fallback and as
     * the recovery path if the chained insert fails for any reason.
     */
    private function insertUnsigned(array $row): ?int
    {
        try {
            return (int) DB::table('ahg_audit_log')->insertGetId($row);
        } catch (Throwable $e) {
            Log::warning('audit-trail: unsigned insert failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Resolve the Ed25519 signer.
     *
     * We deliberately do NOT depend on ahg-ai-compliance's service container
     * binding - this package's writer is callable from contexts (queues, CLI
     * bootstrap, install scripts) where the compliance provider has not yet
     * registered. Instead we read storage/keys/inference-signing.{sk,pk}
     * directly via the shared library. ahg-ai-compliance writes the same key
     * to the same path, so the kid stays in lock-step with the inference log.
     */
    private function resolveSigner(): void
    {
        if ($this->signerResolved) {
            return;
        }
        $this->signerResolved = true;

        try {
            if (!function_exists('sodium_crypto_sign_detached')) {
                throw new \RuntimeException('libsodium not available');
            }
            $secretPath = function_exists('storage_path')
                ? storage_path('keys/inference-signing.sk')
                : null;

            if ($secretPath === null || !is_readable($secretPath)) {
                throw new \RuntimeException('signing key not found at ' . (string) $secretPath);
            }

            $keyPair = KeyPair::loadFrom($secretPath);
            $this->signer = new Signer($keyPair);
            $this->kid = $keyPair->kid();
        } catch (Throwable $e) {
            if (!self::$fallbackWarned) {
                self::$fallbackWarned = true;
                Log::warning('audit-trail: signing key unavailable, audit rows will be unsigned', [
                    'error' => $e->getMessage(),
                    'hint'  => 'run "php artisan ai-compliance:install-key" to provision the shared Ed25519 keypair',
                ]);
            }
            $this->signer = null;
            $this->kid = null;
        }
    }
}
