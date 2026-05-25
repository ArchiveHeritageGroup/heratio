<?php

declare(strict_types=1);

namespace AhgInferenceReceipts\Tests\Unit;

use AhgInferenceReceipts\JcsEncoder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class JcsEncoderTest extends TestCase
{
    public function testNullTrueFalse(): void
    {
        $this->assertSame('null', JcsEncoder::encode(null));
        $this->assertSame('true', JcsEncoder::encode(true));
        $this->assertSame('false', JcsEncoder::encode(false));
    }

    public function testIntegers(): void
    {
        $this->assertSame('0', JcsEncoder::encode(0));
        $this->assertSame('1', JcsEncoder::encode(1));
        $this->assertSame('-1', JcsEncoder::encode(-1));
        $this->assertSame('42', JcsEncoder::encode(42));
        $this->assertSame('9007199254740992', JcsEncoder::encode(9007199254740992));
    }

    public function testIntegerValuedFloats(): void
    {
        $this->assertSame('0', JcsEncoder::encode(0.0));
        $this->assertSame('0', JcsEncoder::encode(-0.0));
        $this->assertSame('1', JcsEncoder::encode(1.0));
        $this->assertSame('100', JcsEncoder::encode(100.0));
    }

    public function testFractionalFloats(): void
    {
        $this->assertSame('1.5', JcsEncoder::encode(1.5));
        $this->assertSame('-1.5', JcsEncoder::encode(-1.5));
        $this->assertSame('0.1', JcsEncoder::encode(0.1));
    }

    public function testStringBasics(): void
    {
        $this->assertSame('""', JcsEncoder::encode(''));
        $this->assertSame('"hello"', JcsEncoder::encode('hello'));
        $this->assertSame('" "', JcsEncoder::encode(' '));
    }

    public function testStringEscaping(): void
    {
        $this->assertSame('"a\"b"', JcsEncoder::encode('a"b'));
        $this->assertSame('"a\\\\b"', JcsEncoder::encode('a\\b'));
        $this->assertSame('"line\nbreak"', JcsEncoder::encode("line\nbreak"));
        $this->assertSame('"\b"', JcsEncoder::encode("\x08"));
        $this->assertSame('"\t"', JcsEncoder::encode("\t"));
        $this->assertSame('"\f"', JcsEncoder::encode("\x0C"));
        $this->assertSame('"\r"', JcsEncoder::encode("\r"));
    }

    public function testStringControlCharsBelow0x20EscapedAsUnicode(): void
    {
        $expectNul = '"' . '\\u0000' . '"';
        $expectSoh = '"' . '\\u0001' . '"';
        $expectStx = '"' . '\\u0002' . '"';
        $expectUs  = '"' . '\\u001f' . '"';
        $this->assertSame($expectNul, JcsEncoder::encode("\x00"));
        $this->assertSame($expectSoh, JcsEncoder::encode("\x01"));
        $this->assertSame($expectStx, JcsEncoder::encode("\x02"));
        $this->assertSame($expectUs,  JcsEncoder::encode("\x1F"));
    }

    public function testUnicodePassedThrough(): void
    {
        $this->assertSame('"Afrika"', JcsEncoder::encode('Afrika'));
        $this->assertSame('"æ"', JcsEncoder::encode("\xC3\xA6"));
        $this->assertNotEmpty(JcsEncoder::encode('Zimbabwe'));
    }

    public function testEmptyArrayAndObject(): void
    {
        $this->assertSame('[]', JcsEncoder::encode([]));
        $this->assertSame('{}', JcsEncoder::encode(new \stdClass()));
    }

    public function testFlatArrayPreservesOrder(): void
    {
        $this->assertSame('[1,2,3]', JcsEncoder::encode([1, 2, 3]));
        $this->assertSame('[3,1,2]', JcsEncoder::encode([3, 1, 2]));
        $this->assertSame('["a","b","c"]', JcsEncoder::encode(['a', 'b', 'c']));
    }

    public function testObjectKeysSortedLexicographically(): void
    {
        $this->assertSame(
            '{"a":1,"b":2,"c":3}',
            JcsEncoder::encode(['c' => 3, 'a' => 1, 'b' => 2])
        );

        $this->assertSame(
            '{"Z":1,"a":2}',
            JcsEncoder::encode(['a' => 2, 'Z' => 1])
        );
    }

    public function testNestedObjectsKeysSortedRecursively(): void
    {
        $payload = [
            'outer_z' => 1,
            'outer_a' => [
                'inner_z' => 'last',
                'inner_a' => 'first',
            ],
        ];
        $this->assertSame(
            '{"outer_a":{"inner_a":"first","inner_z":"last"},"outer_z":1}',
            JcsEncoder::encode($payload)
        );
    }

    public function testNanRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JcsEncoder::encode(NAN);
    }

    public function testInfRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JcsEncoder::encode(INF);
    }

    public function testResourceRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $fh = fopen('php://memory', 'r');
        try {
            JcsEncoder::encode($fh);
        } finally {
            fclose($fh);
        }
    }

    public static function rfc8785Examples(): array
    {
        return [
            'object key sort' => [
                ['z' => 1, 'a' => 2, 'm' => 3],
                '{"a":2,"m":3,"z":1}',
            ],
            'nested sort' => [
                ['outer' => ['z' => 1, 'a' => 2]],
                '{"outer":{"a":2,"z":1}}',
            ],
            'array order preserved' => [
                [3, 1, 4, 1, 5, 9, 2, 6],
                '[3,1,4,1,5,9,2,6]',
            ],
            'mixed payload' => [
                ['name' => 'test', 'count' => 0, 'flag' => true, 'nothing' => null],
                '{"count":0,"flag":true,"name":"test","nothing":null}',
            ],
        ];
    }

    /**
     * @dataProvider rfc8785Examples
     */
    public function testRfc8785Examples(mixed $input, string $expected): void
    {
        $this->assertSame($expected, JcsEncoder::encode($input));
    }

    public function testDeterminismAcrossDifferentKeyOrders(): void
    {
        $a = ['model' => 'llava', 'version' => 'v1.6', 'service' => 'htr'];
        $b = ['service' => 'htr', 'version' => 'v1.6', 'model' => 'llava'];
        $c = ['version' => 'v1.6', 'model' => 'llava', 'service' => 'htr'];

        $aE = JcsEncoder::encode($a);
        $bE = JcsEncoder::encode($b);
        $cE = JcsEncoder::encode($c);

        $this->assertSame($aE, $bE);
        $this->assertSame($bE, $cE);
    }
}
