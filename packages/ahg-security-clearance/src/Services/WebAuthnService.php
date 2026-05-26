<?php

/**
 * WebAuthnService — FIDO2 / WebAuthn / passkey MFA backend for Heratio (issue
 * #721).
 *
 * Wraps web-auth/webauthn-lib ^5.3 with the ahg_webauthn_credential table.
 * Provides per-user enrolment (registration) and login-time assertion. Lives
 * alongside TotpService (issue #690) — a user can enrol either factor, or
 * both. The LoginController + RequireMfaCompletion middleware decide which
 * factor to present at sign-in.
 *
 * The library expects a "credential source repository" so it can look up a
 * stored credential during assertion and persist a new one after attestation.
 * We implement that repository inline against the ahg_webauthn_credential
 * table — credentials are serialised to JSON via Symfony Serializer
 * (the library ships the SerializerFactory that builds a normaliser stack for
 * its DTOs) and stored as a blob.
 *
 * Browser-side flow:
 *   1. JS calls POST /security/2fa/webauthn/register/begin
 *   2. Server returns PublicKeyCredentialCreationOptions JSON
 *   3. JS feeds it to navigator.credentials.create()
 *   4. JS POSTs the AuthenticatorAttestationResponse JSON to
 *      /security/2fa/webauthn/register/complete
 *   5. Server validates, persists the credential row.
 *
 * Login flow mirrors that with /assert/begin + /assert/complete.
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

declare(strict_types=1);

namespace AhgSecurityClearance\Services;

use Cose\Algorithm\Manager as CoseAlgorithmManager;
use Cose\Algorithm\Signature\ECDSA;
use Cose\Algorithm\Signature\EdDSA;
use Cose\Algorithm\Signature\RSA;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

// web-auth/webauthn-lib v5 removed the PublicKeyCredentialSourceRepository
// interface; the three repository methods below are still implemented and
// used directly by the v5 ceremony validators.
class WebAuthnService
{
    /** Session key under which the pending registration challenge is stashed. */
    private const SESSION_REGISTER_OPTIONS = 'webauthn_register_options';

    /** Session key under which the pending assertion challenge is stashed. */
    private const SESSION_ASSERT_OPTIONS = 'webauthn_assert_options';

    private SerializerInterface $serializer;
    private AttestationStatementSupportManager $attestationManager;

    public function __construct()
    {
        // The library's "no-framework" wiring: build an AttestationStatementSupportManager
        // (we accept "none" attestation only — every browser/platform passkey
        // supports it and we don't need MDS-backed verification for a self-hosted
        // archival platform), then hand it to the serializer factory.
        $this->attestationManager = AttestationStatementSupportManager::create();
        $this->attestationManager->add(NoneAttestationStatementSupport::create());

        $this->serializer = (new WebauthnSerializerFactory($this->attestationManager))->create();
    }

    // ─── public API ──────────────────────────────────────────────────────────

    /**
     * Begin a registration ceremony for the given user. Returns a JSON-shaped
     * array suitable for handing straight to navigator.credentials.create().
     * The PublicKeyCredentialCreationOptions object itself is serialised back
     * onto the session so completeRegistration() can verify the response
     * against the exact challenge we emitted.
     *
     * @return array<string, mixed>
     */
    public function beginRegistration(int $userId, string $username, string $displayName, string $rpId, string $rpName): array
    {
        $userEntity = PublicKeyCredentialUserEntity::create(
            $username,
            (string) $userId,
            $displayName,
        );

        $rpEntity = PublicKeyCredentialRpEntity::create($rpName, $rpId);

        $publicKeyCredentialParameters = [
            PublicKeyCredentialParameters::create('public-key', -7),    // ES256
            PublicKeyCredentialParameters::create('public-key', -257),  // RS256
            PublicKeyCredentialParameters::create('public-key', -8),    // EdDSA
        ];

        // Exclude credentials already enrolled by this user so the browser
        // refuses to re-register the same authenticator silently.
        $excludeCredentials = array_map(
            static fn (PublicKeyCredentialSource $src) => $src->getPublicKeyCredentialDescriptor(),
            $this->findAllForUserHandle((string) $userId),
        );

        $challenge = random_bytes(32);

        $authenticatorSelection = AuthenticatorSelectionCriteria::create(
            attachment: null,
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            requireResidentKey: false,
        );

        $options = PublicKeyCredentialCreationOptions::create(
            $rpEntity,
            $userEntity,
            $challenge,
            $publicKeyCredentialParameters,
            authenticatorSelection: $authenticatorSelection,
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $excludeCredentials,
            timeout: 60000,
        );

        $json = $this->serializer->serialize($options, 'json', [
            'json_encode_options' => JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ]);

        session()->put(self::SESSION_REGISTER_OPTIONS, $json);

        // Decode + return as array so the controller can json_encode() it
        // through Laravel's response helpers with consistent CSRF behaviour.
        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }

    /**
     * Validate the AuthenticatorAttestationResponse the browser produced and,
     * on success, persist the new credential row.
     *
     * @param  array<string, mixed>  $browserResponse  PublicKeyCredential JSON
     */
    public function completeRegistration(int $userId, array $browserResponse, string $label, string $rpId): bool
    {
        $optionsJson = session()->pull(self::SESSION_REGISTER_OPTIONS);
        if (! $optionsJson) {
            return false;
        }

        /** @var PublicKeyCredentialCreationOptions $options */
        $options = $this->serializer->deserialize($optionsJson, PublicKeyCredentialCreationOptions::class, 'json');

        $credentialLoader = PublicKeyCredentialLoader::create(
            AttestationObjectLoader::create($this->attestationManager),
        );

        $credential = $credentialLoader->loadArray($browserResponse);
        $response = $credential->getResponse();

        if (! $response instanceof AuthenticatorAttestationResponse) {
            return false;
        }

        $validator = AuthenticatorAttestationResponseValidator::create(
            $this->attestationManager,
            $this,
            null,
            ExtensionOutputCheckerHandler::create(),
        );

        try {
            $source = $validator->check($response, $options, $rpId);
        } catch (\Throwable $e) {
            \Log::warning('webauthn.register.invalid', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $this->persistSource($source, $userId, $label);

        \Log::info('webauthn.enrolled', ['user_id' => $userId, 'label' => $label]);

        return true;
    }

    /**
     * Begin an assertion ceremony for the given user. Returns the
     * PublicKeyCredentialRequestOptions as an array for the browser.
     *
     * @return array<string, mixed>
     */
    public function beginAssertion(int $userId, string $rpId): array
    {
        $allowedCredentials = array_map(
            static fn (PublicKeyCredentialSource $src) => $src->getPublicKeyCredentialDescriptor(),
            $this->findAllForUserHandle((string) $userId),
        );

        $challenge = random_bytes(32);

        $options = PublicKeyCredentialRequestOptions::create(
            $challenge,
            rpId: $rpId,
            allowCredentials: $allowedCredentials,
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            timeout: 60000,
        );

        $json = $this->serializer->serialize($options, 'json', [
            'json_encode_options' => JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ]);

        session()->put(self::SESSION_ASSERT_OPTIONS, $json);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }

    /**
     * Validate the AuthenticatorAssertionResponse the browser produced. On
     * success, updates sign_count + last_used_at on the matched credential.
     *
     * @param  array<string, mixed>  $browserResponse
     */
    public function completeAssertion(int $userId, array $browserResponse, string $rpId): bool
    {
        $optionsJson = session()->pull(self::SESSION_ASSERT_OPTIONS);
        if (! $optionsJson) {
            return false;
        }

        /** @var PublicKeyCredentialRequestOptions $options */
        $options = $this->serializer->deserialize($optionsJson, PublicKeyCredentialRequestOptions::class, 'json');

        $credentialLoader = PublicKeyCredentialLoader::create(
            AttestationObjectLoader::create($this->attestationManager),
        );

        $credential = $credentialLoader->loadArray($browserResponse);
        $response = $credential->getResponse();

        if (! $response instanceof AuthenticatorAssertionResponse) {
            return false;
        }

        $algorithmManager = CoseAlgorithmManager::create();
        $algorithmManager->add(ECDSA\ES256::create());
        $algorithmManager->add(ECDSA\ES512::create());
        $algorithmManager->add(EdDSA\EdDSA::create());
        $algorithmManager->add(RSA\RS256::create());

        $validator = AuthenticatorAssertionResponseValidator::create(
            $this,
            null,
            ExtensionOutputCheckerHandler::create(),
            $algorithmManager,
        );

        try {
            $updatedSource = $validator->check(
                $credential->getRawId(),
                $response,
                $options,
                $rpId,
                (string) $userId,
            );
        } catch (\Throwable $e) {
            \Log::warning('webauthn.assert.invalid', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $this->saveCredentialSource($updatedSource);

        DB::table('ahg_webauthn_credential')
            ->where('credential_id', $updatedSource->getPublicKeyCredentialId())
            ->update(['last_used_at' => now()]);

        return true;
    }

    /** True if the user has at least one enrolled WebAuthn credential. */
    public function userHasCredential(int $userId): bool
    {
        return DB::table('ahg_webauthn_credential')
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * List rows for the management UI.
     *
     * @return array<int, object>
     */
    public function listForUser(int $userId): array
    {
        return DB::table('ahg_webauthn_credential')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get(['id', 'label', 'aaguid', 'sign_count', 'transports', 'last_used_at', 'created_at'])
            ->all();
    }

    /** Delete a single credential by row id (after ownership check). */
    public function deleteCredential(int $userId, int $credentialRowId): bool
    {
        return DB::table('ahg_webauthn_credential')
            ->where('id', $credentialRowId)
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    /** Drop every WebAuthn credential for the user (admin disable, account close, etc.). */
    public function disable(int $userId): void
    {
        DB::table('ahg_webauthn_credential')->where('user_id', $userId)->delete();
    }

    // ─── PublicKeyCredentialSourceRepository implementation ──────────────────

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $row = DB::table('ahg_webauthn_credential')
            ->where('credential_id', $publicKeyCredentialId)
            ->first();

        if (! $row) {
            return null;
        }

        return $this->hydrateSource($row->public_key);
    }

    /**
     * @return array<int, PublicKeyCredentialSource>
     */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        return $this->findAllForUserHandle($publicKeyCredentialUserEntity->getId());
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        // We only update the columns the library mutates on assertion
        // (counter). Persistence of brand-new sources goes through
        // persistSource() so we can attach the user-supplied label.
        $serialized = $this->serializer->serialize($publicKeyCredentialSource, 'json', [
            'json_encode_options' => JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ]);

        DB::table('ahg_webauthn_credential')
            ->where('credential_id', $publicKeyCredentialSource->getPublicKeyCredentialId())
            ->update([
                'public_key' => $serialized,
                'sign_count' => $publicKeyCredentialSource->getCounter(),
            ]);
    }

    // ─── internals ───────────────────────────────────────────────────────────

    /**
     * @return array<int, PublicKeyCredentialSource>
     */
    private function findAllForUserHandle(string $userHandle): array
    {
        $rows = DB::table('ahg_webauthn_credential')
            ->where('user_id', (int) $userHandle)
            ->get(['public_key']);

        $sources = [];
        foreach ($rows as $row) {
            $source = $this->hydrateSource($row->public_key);
            if ($source) {
                $sources[] = $source;
            }
        }

        return $sources;
    }

    private function hydrateSource(string $serialized): ?PublicKeyCredentialSource
    {
        try {
            /** @var PublicKeyCredentialSource $source */
            $source = $this->serializer->deserialize($serialized, PublicKeyCredentialSource::class, 'json');

            return $source;
        } catch (\Throwable $e) {
            \Log::warning('webauthn.hydrate.failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function persistSource(PublicKeyCredentialSource $source, int $userId, string $label): void
    {
        $serialized = $this->serializer->serialize($source, 'json', [
            'json_encode_options' => JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ]);

        $aaguid = $source->getAaguid()?->__toString();

        DB::table('ahg_webauthn_credential')->insert([
            'user_id' => $userId,
            'credential_id' => $source->getPublicKeyCredentialId(),
            'public_key' => $serialized,
            'attestation_type' => $source->getAttestationType(),
            'aaguid' => $aaguid,
            'sign_count' => $source->getCounter(),
            'transports' => json_encode($source->getTransports(), JSON_UNESCAPED_SLASHES),
            'label' => $label !== '' ? $label : 'Passkey',
            'last_used_at' => null,
            'created_at' => now(),
        ]);
    }
}
