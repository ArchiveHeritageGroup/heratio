<?php

declare(strict_types=1);

namespace AhgInferenceReceipts\Tests\Conformance;

use AhgInferenceReceipts\JcsEncoder;
use AhgInferenceReceipts\KeyPair;
use AhgInferenceReceipts\Receipt;
use AhgInferenceReceipts\ReceiptChain;
use AhgInferenceReceipts\Signer;
use AhgInferenceReceipts\Storage\ArrayChainStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Conformance tests against the nobulex test vectors
 * (https://github.com/arian-gogani/nobulex/tree/main/spec/vectors).
 *
 * The vectors live in tests/fixtures/nobulex/. Run
 *   ./tools/fetch-nobulex-vectors.sh
 * to refresh from upstream before running this suite.
 *
 * If the fixtures are absent the relevant tests are marked skipped, so
 * the suite stays green for downstream consumers who don't pull the
 * vectors. CI on this repo pulls them as part of its setup step.
 */
final class NobulexVectorsTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/../fixtures/nobulex';

    public static function jcsVectors(): array
    {
        $path = self::FIXTURES_DIR . '/jcs-vectors.json';
        if (!is_readable($path)) {
            return [
                'fixtures-missing' => [null, null],
            ];
        }
        $rows = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $out = [];
        foreach ($rows as $i => $row) {
            $out["vector-{$i}"] = [$row['input'], $row['expected']];
        }
        return $out;
    }

    #[DataProvider('jcsVectors')]
    public function testJcsVectorRoundtrip(mixed $input, ?string $expected): void
    {
        if ($expected === null) {
            $this->markTestSkipped(
                'nobulex JCS vectors not present at tests/fixtures/nobulex/jcs-vectors.json '
                . '(run tools/fetch-nobulex-vectors.sh to populate)'
            );
        }
        $this->assertSame($expected, JcsEncoder::encode($input));
    }

    public function testReceiptShapeMatchesNobulexExpectation(): void
    {
        $store = new ArrayChainStore();
        $kp = KeyPair::generate();
        $chain = ReceiptChain::withSingleKey($store, new Signer($kp));

        $r = $chain->append([
            'service'             => 'llm',
            'model_id'            => 'mistral:7b-instruct-v0.2',
            'model_version'       => 'mistral-7b@2024-01',
            'input_fingerprint'   => str_repeat('a', 64),
            'output_fingerprint'  => str_repeat('b', 64),
            'request_id'          => 'req_01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'user_id'             => 42,
            'tenant_id'           => 1,
        ]);

        $arr = $r->toArray();
        $this->assertArrayHasKey('v', $arr);
        $this->assertArrayHasKey('seq', $arr);
        $this->assertArrayHasKey('ts', $arr);
        $this->assertArrayHasKey('prev_hash', $arr);
        $this->assertArrayHasKey('payload', $arr);
        $this->assertArrayHasKey('kid', $arr);
        $this->assertArrayHasKey('alg', $arr);
        $this->assertArrayHasKey('entry_hash', $arr);
        $this->assertArrayHasKey('signature', $arr);

        $this->assertSame(1, $arr['v']);
        $this->assertSame('ed25519', $arr['alg']);
        $this->assertSame(64, strlen($arr['entry_hash']));
        $this->assertSame(64, strlen($arr['prev_hash']));
    }

    public function testEntryHashIsStableWhenPayloadKeyOrderChanges(): void
    {
        $kp = KeyPair::generate();

        $signingViewA = [
            'v'         => Receipt::VERSION,
            'seq'       => 0,
            'ts'        => '2026-05-25T12:00:00.000Z',
            'prev_hash' => Receipt::GENESIS_PREV_HASH,
            'payload'   => ['z' => 1, 'a' => 2, 'm' => 3],
            'kid'       => $kp->kid(),
            'alg'       => Receipt::ALG,
        ];
        $signingViewB = [
            'v'         => Receipt::VERSION,
            'seq'       => 0,
            'ts'        => '2026-05-25T12:00:00.000Z',
            'prev_hash' => Receipt::GENESIS_PREV_HASH,
            'payload'   => ['a' => 2, 'm' => 3, 'z' => 1],
            'kid'       => $kp->kid(),
            'alg'       => Receipt::ALG,
        ];

        $this->assertSame(
            Receipt::computeEntryHash($signingViewA),
            Receipt::computeEntryHash($signingViewB),
            'JCS canonicalization must produce the same hash regardless of payload key order'
        );
    }
}
