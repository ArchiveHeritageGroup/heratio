<?php

namespace AhgLibrary\Tests\Unit;

use AhgLibrary\Services\ClassificationNotation as N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClassificationNotationTest extends TestCase
{
    public static function shelfOrders(): array
    {
        return [
            'LC numeric, not string' => ['LC', ['QA9', 'QA76', 'QA100']],
            'LC whole before decimal' => ['LC', ['QA76', 'QA76 .J38', 'QA76.5', 'QA76.73', 'QA76.73 .J38', 'QA76.73 .J38 2010', 'QA76.73 .J4']],
            'LC letters' => ['LC', ['Q1', 'QA1', 'QB1', 'R1']],
            'Dewey' => ['Dewey', ['005', '005.1', '005.133', '005.2', '050', '500']],
            'Dewey cutter' => ['Dewey', ['005.133', '005.133 ABC', '005.133 ABD']],
            'UDC filing' => ['UDC', ['5+6', '5/6', '5', '5:6', '5=111', '5(03)', '5(680)', '5"19"', '5-1', '5.01', '51']],
            'UDC point ignored' => ['UDC', ['821.1', '821.111', '821.2']],
            'Local natural' => ['Local', ['A2', 'A10', 'B1']],
        ];
    }

    #[DataProvider('shelfOrders')]
    public function test_shelf_order(string $scheme, array $expected): void
    {
        $shuffled = array_reverse($expected);
        usort($shuffled, fn ($a, $b) => strcmp(N::sortKey($scheme, $a), N::sortKey($scheme, $b)));
        $this->assertSame($expected, $shuffled);
    }

    public function test_aliases_map_to_stored_codes(): void
    {
        $this->assertSame('LC', N::canonicalScheme('lcc'));
        $this->assertSame('Dewey', N::canonicalScheme('DDC'));
        $this->assertSame('UDC', N::canonicalScheme('udc'));
        $this->assertNull(N::canonicalScheme('  '));
    }

    public function test_class_portion_strips_cutters_and_rejects_malformed(): void
    {
        $this->assertSame('QA76.73', N::classPortion('LC', 'qa 76.73 .J38 2010'));
        $this->assertSame('005.133', N::classPortion('Dewey', "005.1'33 ABC"));
        $this->assertSame('821.111', N::classPortion('UDC', '821.111-31'));
        $this->assertNull(N::classPortion('Dewey', 'ABC'));
        $this->assertNull(N::classPortion('LC', '123'));
    }

    public function test_malformed_numbers_have_no_class_portion(): void
    {
        $this->assertNull(N::classPortion('LC', 'NONSENSE'));
        $this->assertNull(N::classPortion('Dewey', '0051'));
        $this->assertNull(N::classPortion('Dewey', '005.1.2'));
    }

    public function test_detect_scheme_is_conservative(): void
    {
        $this->assertSame('LC', N::detectScheme('QA76.73 .J38'));
        $this->assertSame('LC', N::detectScheme('qa 9'));
        $this->assertSame('Dewey', N::detectScheme('005.133 ABC'));
        $this->assertNull(N::detectScheme('NONSENSE'));
        $this->assertNull(N::detectScheme('shelf by door'));
        $this->assertNull(N::detectScheme('12'));
    }

    public function test_local_keys_keep_non_latin_notation(): void
    {
        foreach (['甲一', '乙二', 'אב', 'Б12', 'ক১'] as $notation) {
            $key = N::sortKey('Local', $notation);
            $this->assertNotSame('', $key, $notation);
            $this->assertTrue(mb_check_encoding($key, 'UTF-8'), $notation);
        }
        $this->assertNotSame(N::sortKey('Local', '甲一'), N::sortKey('Local', '乙二'));
        // Digits in any script compare as numbers.
        $this->assertLessThan(0, strcmp(N::sortKey('Local', 'ক২'), N::sortKey('Local', 'ক১০')));
        // Latin accents still fold.
        $this->assertSame(N::sortKey('Local', 'E1'), N::sortKey('Local', 'é1'));
    }

    public function test_long_multibyte_key_is_cut_on_a_character_boundary(): void
    {
        $key = N::sortKey('Local', str_repeat('甲', 100));
        $this->assertLessThanOrEqual(191, strlen($key));
        $this->assertTrue(mb_check_encoding($key, 'UTF-8'));
    }

    public function test_broader_candidates(): void
    {
        $this->assertSame(['QA76.7', 'QA76', 'QA', 'Q'], N::broaderCandidates('LC', 'QA76.73'));
        $this->assertSame(['655.1', '655', '650', '600'], N::broaderCandidates('Dewey', '655.12'));
        $this->assertSame(['821.11', '821.1', '821', '82', '8'], N::broaderCandidates('UDC', '821.111'));
        $this->assertSame(['ABC.12', 'ABC'], N::broaderCandidates('Local', 'ABC.12.3'));
    }
}
