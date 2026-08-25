<?php

/**
 * ArchaeologyMatrixTest - #1482, transitive reduction of the Harris Matrix.
 *
 * Pure graph logic, deliberately tested without a database: the archaeology
 * tables are created by migration and the CI test database is built from
 * database/core/*.sql alone (#1471), so a DB-backed test here would skip in CI
 * and prove nothing. Removing implied relationships is the operation that makes
 * a Harris Matrix a Harris Matrix, so it is worth a test that actually runs.
 */

namespace Tests\Feature;

use AhgArchaeology\Services\ArchaeologyService;
use Tests\TestCase;

class ArchaeologyMatrixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(ArchaeologyService::class)) {
            $this->markTestSkipped('ahg-archaeology is not autoloaded in this install.');
        }
    }

    /** The case from the issue: 1001 over 1002 over 1003, plus the implied 1001 over 1003. */
    public function test_it_removes_an_edge_a_longer_path_already_implies(): void
    {
        [$edges, $removed] = ArchaeologyService::reduceTransitively([
            '1001|1002' => 'above',
            '1002|1003' => 'above',
            '1001|1003' => 'above',
        ]);

        $this->assertSame(1, $removed);
        $this->assertSame(['1001|1002', '1002|1003'], array_keys($edges));
        $this->assertArrayNotHasKey('1001|1003', $edges);
    }

    /** A chain with nothing implied must survive untouched. */
    public function test_it_leaves_a_plain_chain_alone(): void
    {
        [$edges, $removed] = ArchaeologyService::reduceTransitively([
            '1001|1002' => 'above',
            '1002|1003' => 'above',
        ]);

        $this->assertSame(0, $removed);
        $this->assertCount(2, $edges);
    }

    /**
     * Two contexts both over a third, with no path between them, is not
     * redundancy - it is the ordinary shape of a matrix and both arrows must
     * stay. This is the case a careless reduction breaks.
     */
    public function test_it_keeps_two_independent_parents(): void
    {
        [$edges, $removed] = ArchaeologyService::reduceTransitively([
            '1001|1003' => 'above',
            '1002|1003' => 'above',
        ]);

        $this->assertSame(0, $removed);
        $this->assertCount(2, $edges);
    }

    /** A longer implied span: the shortcut goes, every step of the chain stays. */
    public function test_it_removes_a_shortcut_across_a_longer_chain(): void
    {
        [$edges, $removed] = ArchaeologyService::reduceTransitively([
            'a|b' => 'above',
            'b|c' => 'above',
            'c|d' => 'above',
            'a|d' => 'above',
        ]);

        $this->assertSame(1, $removed);
        $this->assertArrayNotHasKey('a|d', $edges);
        $this->assertSame(['a|b', 'b|c', 'c|d'], array_keys($edges));
    }

    /**
     * Reachability is computed against the full edge set, never against a set
     * being mutated as the loop runs. With a diamond plus a shortcut, reducing
     * in place could drop an edge a later test still depended on and leave the
     * sequence disconnected.
     */
    public function test_it_reduces_against_the_full_edge_set_not_a_mutating_one(): void
    {
        [$edges, $removed] = ArchaeologyService::reduceTransitively([
            'a|b' => 'above',
            'a|c' => 'above',
            'b|d' => 'above',
            'c|d' => 'above',
            'a|d' => 'above',
        ]);

        // Only the shortcut a->d is implied; both branches of the diamond stay.
        $this->assertSame(1, $removed);
        $this->assertArrayNotHasKey('a|d', $edges);
        $this->assertCount(4, $edges);
    }

    public function test_an_empty_matrix_reduces_to_nothing(): void
    {
        [$edges, $removed] = ArchaeologyService::reduceTransitively([]);

        $this->assertSame(0, $removed);
        $this->assertSame([], $edges);
    }
}
