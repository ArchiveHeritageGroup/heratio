<?php

/**
 * HarrisMatrixTest - #1483, the stratigraphic interchange plugin.
 *
 * The parser and the typing carry the load here: a Harris Matrix that imports
 * a site archive slightly wrong is worse than one that refuses to import it.
 */

namespace Tests\Feature;

use AhgHarrisMatrix\Services\HarrisMatrixService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class HarrisMatrixTest extends TestCase
{
    use DatabaseTransactions;

    private HarrisMatrixService $harris;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(HarrisMatrixService::class)) {
            $this->markTestSkipped('ahg-harris-matrix is not autoloaded in this install.');
        }

        $this->harris = app(HarrisMatrixService::class);
    }

    /** A well-formed LST, three units in blocks of five. */
    private function lst(): string
    {
        return "BASP Harris\nSite: Test\n---\n"
            . "1001\n\n\n\n1002\n"
            . "1002\n1001\n\n\n1003\n"
            . "1003\n1002\n\n1003b\n\n";
    }

    public function test_it_parses_an_lst_into_relationships(): void
    {
        $r = $this->harris->parseLst($this->lst());

        $this->assertNull($r['error']);
        $this->assertSame(['1001', '1002', '1003'], $r['units']);

        $types = array_count_values(array_column($r['rows'], 'type'));
        $this->assertSame(2, $types['above'] ?? 0);
        $this->assertSame(2, $types['below'] ?? 0);
        $this->assertSame(1, $types['same_as'] ?? 0, 'equal_to should become same_as');
    }

    public function test_a_truncated_lst_is_refused_rather_than_half_imported(): void
    {
        // A unit whose four relationship lines are missing. Importing what is
        // there and stopping quietly would leave a site archive silently
        // half-loaded, which is worse than refusing it.
        $r = $this->harris->parseLst("h1\nh2\nh3\n2001\n\n\n");

        $this->assertNotNull($r['error']);
        $this->assertStringContainsString('2001', $r['error'], 'the error should name the offending unit');
        $this->assertSame([], $r['rows']);
    }

    public function test_a_file_with_no_units_is_refused(): void
    {
        $r = $this->harris->parseLst("one\ntwo\n");

        $this->assertNotNull($r['error']);
    }

    public function test_contemporary_with_is_reported_but_never_imported(): void
    {
        // "Contemporary with" is a chronological claim about two DISTINCT units.
        // same_as means one unit recorded twice. Importing the first as the
        // second would merge contexts that are not the same context.
        $lst = "h1\nh2\nh3\n3001\n\n3002\n\n\n";
        $r = $this->harris->parseLst($lst);

        $this->assertNull($r['error']);
        $this->assertCount(1, $r['contemporary']);
        $this->assertSame(['3001', '3002'], $r['contemporary'][0]);
        $this->assertNotContains('same_as', array_column($r['rows'], 'type'));
    }

    public function test_a_cut_is_an_interface_and_everything_else_a_deposit(): void
    {
        // Harris divides units into deposits and interfaces; a cut is the
        // surface left by an act of removal, not a body of material.
        $this->assertTrue($this->harris->isInterface((object) ['type_name' => 'Cut']));
        $this->assertTrue($this->harris->isInterface((object) ['type_name' => 'Interface']));

        foreach (['Layer', 'Deposit', 'Fill', 'Masonry', 'Skeleton', 'Structure', 'Surface', ''] as $type) {
            $this->assertFalse(
                $this->harris->isInterface((object) ['type_name' => $type]),
                "{$type} should be a deposit"
            );
            $this->assertSame('deposit', $this->harris->unitType((object) ['type_name' => $type]));
        }

        $this->assertSame('interface', $this->harris->unitType((object) ['type_name' => 'Cut']));
    }
}
