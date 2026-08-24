<?php

/**
 * ResearchCustodyTest - #1478, chain of custody for reading-room material.
 *
 * The point of a custody log is that it is append-only and complete. These
 * pin the two properties that make it evidence rather than decoration: a
 * movement always writes a row, and the request's current state and the log
 * never disagree.
 */

namespace Tests\Feature;

use AhgResearch\Services\ResearchCustodyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResearchCustodyTest extends TestCase
{
    use DatabaseTransactions;

    private ResearchCustodyService $custody;

    private int $requestId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['research_custody_handoff', 'research_material_request', 'research_booking', 'research_researcher'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->markTestSkipped("$table is not present in this install.");
            }
        }

        $this->custody = app(ResearchCustodyService::class);

        // These tables carry NOT NULL columns with no default, so every one of
        // them is supplied rather than leaned on.
        $researcherId = DB::table('research_researcher')->insertGetId([
            'user_id' => 0,
            'first_name' => 'Custody',
            'last_name' => 'Tester',
            'email' => 'custody.tester@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bookingId = DB::table('research_booking')->insertGetId([
            'researcher_id' => $researcherId,
            'reading_room_id' => 0,
            'booking_date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->requestId = DB::table('research_material_request')->insertGetId([
            'booking_id' => $bookingId,
            'object_id' => 0,
            'status' => 'requested',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_an_unmoved_item_sits_in_the_store_with_no_chain(): void
    {
        $item = $this->custody->getRequestForCustody($this->requestId);

        $this->assertNotNull($item);
        $this->assertSame('Repository store', $item->current_location);
        $this->assertSame('Repository', $item->current_holder);
        $this->assertCount(0, $this->custody->getChain($this->requestId));
    }

    public function test_a_checkout_writes_a_handoff_row_and_moves_the_item(): void
    {
        $this->custody->recordCheckout($this->requestId, [
            'checkout_date' => now()->toDateString(),
            'expected_return' => now()->addDays(3)->toDateString(),
            'condition' => 'fair',
            'notes' => 'Spine fragile',
        ], 1);

        $chain = $this->custody->getChain($this->requestId);
        $this->assertCount(1, $chain);
        $this->assertSame('checkout', $chain[0]->action);
        $this->assertSame('Repository store', $chain[0]->from_location);
        $this->assertSame('fair', $chain[0]->condition_at_handoff);
        $this->assertSame('Spine fragile', $chain[0]->notes);

        // The request's own state must agree with the log.
        $request = DB::table('research_material_request')->where('id', $this->requestId)->first();
        $this->assertSame('delivered', $request->status);
        $this->assertNotNull($request->checkout_confirmed_at);
        $this->assertSame('Custody Tester', $request->location_current);
    }

    public function test_the_expected_return_date_is_stored_rather_than_discarded(): void
    {
        if (! Schema::hasColumn('research_material_request', 'expected_return')) {
            $this->markTestSkipped('expected_return column not applied in this install.');
        }

        $due = now()->addDays(5)->toDateString();

        $this->custody->recordCheckout($this->requestId, [
            'checkout_date' => now()->toDateString(),
            'expected_return' => $due,
        ], 1);

        // The verification screen reads it back; collecting it and losing it is
        // the defect this covers.
        $checkout = $this->custody->getCheckoutForVerification($this->requestId);
        $this->assertSame($due, (string) $checkout->expected_return);
    }

    public function test_a_return_appends_rather_than_editing_the_checkout(): void
    {
        $this->custody->recordCheckout($this->requestId, ['condition' => 'good'], 1);
        $this->custody->recordReturn($this->requestId, [
            'return_condition' => 'damaged',
            'return_notes' => 'Corner torn',
        ], 1);

        $chain = $this->custody->getChain($this->requestId);

        // Two rows, oldest first - the checkout is still there, unedited.
        $this->assertCount(2, $chain);
        $this->assertSame('checkout', $chain[0]->action);
        $this->assertSame('good', $chain[0]->condition_at_handoff);
        $this->assertSame('return', $chain[1]->action);
        $this->assertSame('damaged', $chain[1]->condition_at_handoff);

        // A return's note is recorded against the condition, and the chain's
        // Notes column must still show it.
        $this->assertSame('Corner torn', $chain[1]->notes);

        $request = DB::table('research_material_request')->where('id', $this->requestId)->first();
        $this->assertSame('returned', $request->status);
        $this->assertSame('damaged', $request->return_condition);
        $this->assertNotNull($request->return_verified_at);

        $item = $this->custody->getRequestForCustody($this->requestId);
        $this->assertSame('Repository store', $item->current_location);
        $this->assertSame('Repository', $item->current_holder);
    }

    public function test_the_researcher_comes_from_the_booking(): void
    {
        $researcher = $this->custody->getResearcherForRequest($this->requestId);

        $this->assertNotNull($researcher);
        $this->assertSame('Custody', $researcher->first_name);
        $this->assertSame('Tester', $researcher->last_name);
    }
}
