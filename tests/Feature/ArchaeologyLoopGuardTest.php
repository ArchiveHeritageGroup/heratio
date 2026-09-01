<?php

/**
 * ArchaeologyLoopGuardTest - #1499, the stratigraphic loop guard.
 *
 * Pure graph logic, deliberately tested without a database, for the same reason
 * ArchaeologyMatrixTest is: the archaeology tables are created by migration and
 * the CI test database is built from database/core/*.sql alone (#1471), so a
 * DB-backed test here would skip in CI and prove nothing.
 *
 * The guard's job is to refuse a relationship that would make a context later
 * than itself. It has to reason over the SAME node graph the matrix is built
 * from, which means applying the same_as union-find first - the two cases below
 * that carry an issue number are the ones the pre-#1499 guard let through.
 */

namespace Tests\Feature;

use AhgArchaeology\Services\ArchaeologyService as A;
use Tests\TestCase;

class ArchaeologyLoopGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(A::class)) {
            $this->markTestSkipped('ahg-archaeology is not autoloaded in this install.');
        }
    }

    /** #1499: same_as merges two contexts, so it can close a loop the old guard never checked. */
    public function test_same_as_between_two_superposed_contexts_is_refused(): void
    {
        // 1 above 2, then "2 is the same unit as 1" - which would make the merged
        // node later than itself.
        $this->assertTrue(A::closesLoop([[1, 2, 'above']], 2, 1, 'same_as'));
    }

    /** The same contradiction reached along a chain rather than a single edge. */
    public function test_same_as_across_a_chain_is_refused(): void
    {
        $rels = [[1, 2, 'above'], [2, 3, 'above']];

        $this->assertTrue(A::closesLoop($rels, 3, 1, 'same_as'));
    }

    /**
     * #1499: a DIRECTIONAL edge tested on raw ids missed the union too. Context 1
     * on its own reaches nothing; the node {1,2} it belongs to is above 3.
     */
    public function test_directional_edge_through_an_existing_same_as_union_is_refused(): void
    {
        $rels = [[1, 2, 'same_as'], [2, 3, 'above']];

        $this->assertTrue(A::closesLoop($rels, 3, 1, 'above'));
    }

    /** The plain case the guard always caught, which must keep working. */
    public function test_a_backwards_edge_closing_a_chain_is_refused(): void
    {
        $rels = [[1, 2, 'above'], [2, 3, 'above']];

        $this->assertTrue(A::closesLoop($rels, 3, 1, 'above'));
    }

    /** cuts and fills carry the sequence exactly as above does. */
    public function test_cuts_and_fills_are_ordering_relationships_too(): void
    {
        $this->assertTrue(A::closesLoop([[1, 2, 'cuts']], 2, 1, 'cuts'));
        $this->assertTrue(A::closesLoop([[1, 2, 'fills']], 2, 1, 'above'));
    }

    /** A context cannot be later than, or the same as, itself. */
    public function test_a_self_relation_is_refused(): void
    {
        $this->assertTrue(A::closesLoop([], 1, 1, 'above'));
        $this->assertTrue(A::closesLoop([], 1, 1, 'same_as'));
    }

    /** An ordinary chain must still be accepted, or the guard is useless. */
    public function test_a_legitimate_chain_is_allowed(): void
    {
        $this->assertFalse(A::closesLoop([[1, 2, 'above']], 2, 3, 'above'));
    }

    /** same_as between contexts with no ordering between them is perfectly normal. */
    public function test_same_as_between_unordered_contexts_is_allowed(): void
    {
        $rels = [[1, 2, 'above'], [3, 4, 'above']];

        $this->assertFalse(A::closesLoop($rels, 2, 4, 'same_as'));
    }

    /**
     * bonds_with and abuts assert physical contact, not identity or order. The
     * matrix builder neither unions them nor draws a later-than edge for them, so
     * they cannot create a loop and must never be refused as though they could.
     */
    public function test_contact_relationships_are_never_refused(): void
    {
        $rels = [[1, 2, 'above'], [2, 3, 'above']];

        $this->assertFalse(A::closesLoop($rels, 3, 1, 'bonds_with'));
        $this->assertFalse(A::closesLoop($rels, 3, 1, 'abuts'));
    }

    /** An unknown type is not this method's error to raise; addRelationship rejects it. */
    public function test_an_unknown_type_is_not_treated_as_a_loop(): void
    {
        $this->assertFalse(A::closesLoop([[1, 2, 'above']], 2, 1, 'frobnicates'));
    }
}
