<?php
/**
 * Heratio - manifest determinism + signature round-trip tests.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Tests\Unit;

use AhgC2pa\Manifest\Assertion;
use AhgC2pa\Manifest\C2paSigner;
use AhgC2pa\Manifest\ManifestBuilder;
use AhgInferenceReceipts\JcsEncoder;
use AhgInferenceReceipts\KeyPair;
use AhgInferenceReceipts\Signer as ReceiptSigner;
use PHPUnit\Framework\TestCase;

final class ManifestBuilderTest extends TestCase
{
    public function test_manifest_serialises_deterministically_for_identical_input(): void
    {
        // Two builders with identical input, identical fixed timestamp,
        // identical fixed manifest label - canonical bytes must match.
        $a = $this->buildFixed('label-x');
        $b = $this->buildFixed('label-x');

        $this->assertSame(
            ManifestBuilder::toCanonicalJson($a),
            ManifestBuilder::toCanonicalJson($b),
            'JCS-canonical manifest must be byte-identical for identical input',
        );
    }

    public function test_claim_hash_matches_what_the_claim_pins(): void
    {
        $manifest = $this->buildFixed('label-y');

        // Each assertion's hashed-uri (computed at claim build time) must
        // exactly equal the hash of the canonical assertion bytes.
        foreach ($manifest['assertions'] as $i => $a) {
            $assertion = new Assertion($a['label'], $a['data'], $a['instance']);
            $expectedHash = $assertion->hashHex();
            $claimRef = $manifest['claim']['assertions'][$i] ?? null;

            $this->assertIsArray($claimRef);
            $this->assertSame('sha256', $claimRef['alg']);
            $this->assertSame($expectedHash, $claimRef['hash'], "Assertion {$i} hash mismatch");
            $this->assertSame($assertion->uri(), $claimRef['url']);
        }
    }

    public function test_signature_verifies_under_the_kid(): void
    {
        $kp = KeyPair::generate();
        $receiptSigner = new ReceiptSigner($kp);
        $signer = new C2paSigner($receiptSigner);

        $manifest = $this->buildFixed('label-z');
        $claim = $manifest['_claim_object'];
        $signed = $signer->sign($claim);

        $fullManifest = [
            'manifest_label'  => $manifest['manifest_label'],
            'assertions'      => $manifest['assertions'],
            'claim'           => $signed['claim'],
            'claim_signature' => $signed['claim_signature'],
        ];

        // Round-trip through JSON like a real verifier would.
        $serialised = ManifestBuilder::toCanonicalJson($fullManifest);
        $decoded = json_decode($serialised, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($kp->kid(), $decoded['claim_signature']['kid']);

        $publicKeyResolver = fn (string $kid): ?string => $kid === $kp->kid() ? $kp->publicKey() : null;
        $this->assertTrue(
            C2paSigner::verify($decoded, $publicKeyResolver),
            'Signed manifest must verify with the matching public key',
        );

        // Tamper #1: mutate the signed claim itself - signature must fail.
        $tamperedClaim = $decoded;
        $tamperedClaim['claim']['title'] = 'spoofed';
        $this->assertFalse(
            C2paSigner::verify($tamperedClaim, $publicKeyResolver),
            'Mutated claim must fail signature verification',
        );

        // Tamper #2: mutate an assertion payload. The claim signature
        // alone may still verify (the assertion blob is referenced by
        // hash, not signed directly), but re-hashing the assertion no
        // longer matches the hash the claim pinned. This is the integrity
        // path the c2pa:verify command exercises.
        $assertionTampered = $decoded;
        $assertionTampered['assertions'][0]['data']['actions'][0]['action'] = 'edited';
        $recomputed = (new Assertion(
            $assertionTampered['assertions'][0]['label'],
            $assertionTampered['assertions'][0]['data'],
            (int) ($assertionTampered['assertions'][0]['instance'] ?? 1),
        ))->hashHex();
        $pinned = $assertionTampered['claim']['assertions'][0]['hash'] ?? null;
        $this->assertNotSame($recomputed, $pinned, 'Recomputed assertion hash must differ from pinned hash after tampering');
    }

    public function test_jcs_canonical_form_is_stable_across_key_orderings(): void
    {
        // Same logical assertion, different PHP array key insertion order.
        $a = new Assertion('c2pa.actions.v2', [
            'actions' => [['action' => 'ai-generated', 'when' => '2026-05-26T10:00:00Z']],
            'extra'   => 'z',
        ]);
        $b = new Assertion('c2pa.actions.v2', [
            'extra'   => 'z',
            'actions' => [['when' => '2026-05-26T10:00:00Z', 'action' => 'ai-generated']],
        ]);

        $this->assertSame($a->canonicalBytes(), $b->canonicalBytes(), 'JCS must normalise key order');
        $this->assertSame($a->hashHex(), $b->hashHex(), 'JCS-derived hashes must match');
    }

    /**
     * @return array<string,mixed>
     */
    private function buildFixed(string $label): array
    {
        $b = (new ManifestBuilder())
            ->withTitle('test')
            ->withFormat('text/plain')
            ->withClaimGenerator('Heratio/test')
            ->withAssetString('hello')
            ->withManifestLabel($label)
            ->withClaimExtra('instanceID', 'xmp:iid:fixed-12345')
            ->addAssertion(new Assertion('c2pa.actions.v2', [
                'actions' => [['action' => 'ai-generated', 'when' => '2026-05-26T10:00:00Z']],
            ]))
            ->addAssertion(Assertion::trainingMining(false, 'archival custody'));

        // ManifestBuilder writes a 'created' timestamp inside the claim.
        // Patch it post-build so the canonical bytes are identical.
        $built = $b->build();
        $built['claim']['created'] = '2026-05-26T10:00:00Z';

        return $built;
    }
}
