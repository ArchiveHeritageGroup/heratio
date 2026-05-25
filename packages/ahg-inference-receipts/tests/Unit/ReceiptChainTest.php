<?php

declare(strict_types=1);

namespace AhgInferenceReceipts\Tests\Unit;

use AhgInferenceReceipts\KeyPair;
use AhgInferenceReceipts\Receipt;
use AhgInferenceReceipts\ReceiptChain;
use AhgInferenceReceipts\Signer;
use AhgInferenceReceipts\Storage\ArrayChainStore;
use PHPUnit\Framework\TestCase;

final class ReceiptChainTest extends TestCase
{
    private ArrayChainStore $store;
    private KeyPair $keyPair;
    private ReceiptChain $chain;

    protected function setUp(): void
    {
        $this->store = new ArrayChainStore();
        $this->keyPair = KeyPair::generate();
        $this->chain = ReceiptChain::withSingleKey($this->store, new Signer($this->keyPair));
    }

    public function testFirstReceiptHasGenesisPrevHash(): void
    {
        $r = $this->chain->append(['msg' => 'hello']);

        $this->assertSame(0, $r->seq);
        $this->assertSame(Receipt::GENESIS_PREV_HASH, $r->prevHash);
        $this->assertSame($this->keyPair->kid(), $r->kid);
    }

    public function testSequentialReceiptsChain(): void
    {
        $r0 = $this->chain->append(['n' => 0]);
        $r1 = $this->chain->append(['n' => 1]);
        $r2 = $this->chain->append(['n' => 2]);

        $this->assertSame(0, $r0->seq);
        $this->assertSame(1, $r1->seq);
        $this->assertSame(2, $r2->seq);

        $this->assertSame(Receipt::GENESIS_PREV_HASH, $r0->prevHash);
        $this->assertSame($r0->entryHash, $r1->prevHash);
        $this->assertSame($r1->entryHash, $r2->prevHash);
    }

    public function testEntryHashIsDeterministic(): void
    {
        $r = $this->chain->append(['service' => 'llm', 'model' => 'mistral']);

        $recomputed = Receipt::computeEntryHash($r->toSigningView());
        $this->assertSame($r->entryHash, $recomputed);
    }

    public function testVerifyEmptyChain(): void
    {
        $result = $this->chain->verify();

        $this->assertTrue($result->ok);
        $this->assertSame(0, $result->checkedCount);
    }

    public function testVerifySingleReceipt(): void
    {
        $this->chain->append(['msg' => 'one']);

        $result = $this->chain->verify();

        $this->assertTrue($result->ok);
        $this->assertSame(1, $result->checkedCount);
    }

    public function testVerifyManyReceipts(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->chain->append(['i' => $i, 'data' => str_repeat('x', $i)]);
        }

        $result = $this->chain->verify();

        $this->assertTrue($result->ok);
        $this->assertSame(25, $result->checkedCount);
    }

    public function testVerifyDetectsTamperedPayload(): void
    {
        $this->chain->append(['msg' => 'original']);
        $this->chain->append(['msg' => 'two']);
        $this->chain->append(['msg' => 'three']);

        $reflection = new \ReflectionClass($this->store);
        $prop = $reflection->getProperty('receipts');
        $prop->setAccessible(true);
        /** @var Receipt[] $receipts */
        $receipts = $prop->getValue($this->store);

        $tampered = new Receipt(
            seq:       $receipts[0]->seq,
            ts:        $receipts[0]->ts,
            prevHash:  $receipts[0]->prevHash,
            payload:   ['msg' => 'TAMPERED'],
            kid:       $receipts[0]->kid,
            entryHash: $receipts[0]->entryHash,
            signature: $receipts[0]->signature,
        );
        $receipts[0] = $tampered;
        $prop->setValue($this->store, $receipts);

        $result = $this->chain->verify();

        $this->assertFalse($result->ok);
        $this->assertSame(0, $result->brokenAtSeq);
    }

    public function testVerifyDetectsTamperedSignature(): void
    {
        $this->chain->append(['msg' => 'one']);
        $this->chain->append(['msg' => 'two']);

        $reflection = new \ReflectionClass($this->store);
        $prop = $reflection->getProperty('receipts');
        $prop->setAccessible(true);
        $receipts = $prop->getValue($this->store);

        $tamperedSig = base64_encode(str_repeat("\0", SODIUM_CRYPTO_SIGN_BYTES));
        $tampered = new Receipt(
            seq:       $receipts[1]->seq,
            ts:        $receipts[1]->ts,
            prevHash:  $receipts[1]->prevHash,
            payload:   $receipts[1]->payload,
            kid:       $receipts[1]->kid,
            entryHash: $receipts[1]->entryHash,
            signature: $tamperedSig,
        );
        $receipts[1] = $tampered;
        $prop->setValue($this->store, $receipts);

        $result = $this->chain->verify();

        $this->assertFalse($result->ok);
        $this->assertSame(1, $result->brokenAtSeq);
    }

    public function testVerifyDetectsBrokenPrevHash(): void
    {
        $this->chain->append(['n' => 0]);
        $this->chain->append(['n' => 1]);
        $this->chain->append(['n' => 2]);

        $reflection = new \ReflectionClass($this->store);
        $prop = $reflection->getProperty('receipts');
        $prop->setAccessible(true);
        $receipts = $prop->getValue($this->store);

        $original = $receipts[2];
        $brokenPrev = str_repeat('a', 64);

        $signingView = [
            'v'         => Receipt::VERSION,
            'seq'       => $original->seq,
            'ts'        => $original->ts,
            'prev_hash' => $brokenPrev,
            'payload'   => $original->payload,
            'kid'       => $original->kid,
            'alg'       => Receipt::ALG,
        ];
        $newHash = Receipt::computeEntryHash($signingView);
        $signer = new Signer($this->keyPair);
        $newSig = $signer->signBase64(hex2bin($newHash));

        $receipts[2] = new Receipt(
            seq:       $original->seq,
            ts:        $original->ts,
            prevHash:  $brokenPrev,
            payload:   $original->payload,
            kid:       $original->kid,
            entryHash: $newHash,
            signature: $newSig,
        );
        $prop->setValue($this->store, $receipts);

        $result = $this->chain->verify();

        $this->assertFalse($result->ok);
        $this->assertSame(2, $result->brokenAtSeq);
    }

    public function testVerifyRejectsUnknownKid(): void
    {
        $this->chain->append(['msg' => 'one']);

        $resolverFails = static fn (string $kid): ?string => null;
        $strictChain = new ReceiptChain($this->store, new Signer($this->keyPair), $resolverFails);

        $result = $strictChain->verify();

        $this->assertFalse($result->ok);
        $this->assertSame(0, $result->brokenAtSeq);
    }

    public function testReceiptCanRoundtripViaArray(): void
    {
        $r = $this->chain->append(['service' => 'llm', 'model_id' => 'mistral:7b', 'tokens' => 1024]);

        $arr = $r->toArray();
        $r2 = Receipt::fromArray($arr);

        $this->assertSame($r->seq, $r2->seq);
        $this->assertSame($r->entryHash, $r2->entryHash);
        $this->assertSame($r->signature, $r2->signature);
        $this->assertSame($r->payload, $r2->payload);
    }
}
