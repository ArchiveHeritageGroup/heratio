<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * The museum provenance display.
 *
 * MuseumController::provenance() used to be `$provenanceChain = collect();` -
 * a hardcoded empty collection, so the page said "No provenance data." for
 * every object forever while provenance_entry held real chains keyed on those
 * same object ids. That is the #1478 defect class: a view bound to something
 * the controller never produces, failing silently into an empty state rather
 * than an error.
 *
 * These tests exercise the view directly with constructed rows, so they neither
 * depend on seeded data nor touch the network, and they pin the two judgements
 * that are easy to get wrong.
 */
class MuseumProvenanceDisplayTest extends TestCase
{
    use DatabaseTransactions;

    private function render(array $chain = [], ?object $overview = null): string
    {
        return View::make('ahg-museum::museum.provenance', [
            'resource'        => (object) ['title' => 'Test object', 'slug' => 'test-object'],
            'provenanceChain' => collect($chain),
            'overview'        => $overview,
            'documents'       => collect(),
        ])->render();
    }

    private function entry(array $overrides = []): object
    {
        return (object) array_merge([
            'sequence' => 1, 'owner_name' => 'Johannes Vermeer', 'owner_type' => 'artist',
            'owner_location' => 'Delft', 'start_date' => '1665', 'start_date_qualifier' => 'exact',
            'end_date' => '1675', 'end_date_qualifier' => 'exact', 'transfer_type' => 'created',
            'transfer_details' => null, 'sale_price' => null, 'sale_currency' => null,
            'auction_house' => null, 'auction_lot' => null, 'certainty' => 'certain',
            'sources' => null, 'evidence_type' => null, 'evidence_description' => null,
            'notes' => null, 'is_gap' => 0, 'gap_explanation' => null,
        ], $overrides);
    }

    private function overview(array $overrides = []): object
    {
        return (object) array_merge([
            'current_status' => null, 'custody_type' => null, 'acquisition_type' => null,
            'acquisition_date' => null, 'acquisition_date_text' => null, 'acquisition_price' => null,
            'acquisition_currency' => null, 'certainty_level' => null, 'has_gaps' => 0,
            'gap_description' => null, 'research_status' => null, 'nazi_era_provenance_checked' => 0,
            'nazi_era_provenance_clear' => 0, 'nazi_era_notes' => null,
            'cultural_property_status' => null, 'cultural_property_notes' => null,
            'provenance_summary' => null, 'is_complete' => 0, 'is_public' => 1,
        ], $overrides);
    }

    public function test_it_renders_a_real_chain_rather_than_the_empty_state(): void
    {
        $html = $this->render([$this->entry()]);

        $this->assertStringContainsString('Johannes Vermeer', $html);
        $this->assertStringContainsString('Chain of ownership', $html);
        $this->assertStringNotContainsString('No provenance has been recorded', $html);
    }

    public function test_an_empty_chain_still_says_so_plainly(): void
    {
        $this->assertStringContainsString('No provenance has been recorded', $this->render([]));
    }

    /**
     * A gap is a first-class state. An honest provenance says "ownership here
     * is unknown" rather than quietly closing the chain over it.
     */
    public function test_a_gap_is_rendered_as_a_gap(): void
    {
        $html = $this->render([
            $this->entry(['is_gap' => 1, 'owner_name' => 'Unknown', 'gap_explanation' => 'No records survive.']),
        ]);

        $this->assertStringContainsString('Gap in provenance', $html);
        $this->assertStringContainsString('No records survive.', $html);
    }

    /**
     * 'none' is the vocabulary's no-concern default, not a finding. Showing a
     * due-diligence card for it implies a determination the curator never made.
     */
    public function test_the_none_sentinel_does_not_raise_a_due_diligence_card(): void
    {
        $html = $this->render([], $this->overview(['cultural_property_status' => 'none']));

        $this->assertStringNotContainsString('Due diligence', $html);
    }

    public function test_a_real_cultural_property_claim_is_shown_and_flagged_open(): void
    {
        $html = $this->render([], $this->overview([
            'cultural_property_status'    => 'claimed',
            'nazi_era_provenance_checked' => 1,
            'nazi_era_provenance_clear'   => 0,
        ]));

        $this->assertStringContainsString('Due diligence', $html);
        $this->assertStringContainsString('Claimed', $html);
        $this->assertStringContainsString('Unresolved', $html);
        $this->assertStringContainsString('border-warning', $html);
    }

    /** A resolved finding is still shown, but must not be coloured as a caution. */
    public function test_a_cleared_finding_is_not_styled_as_a_warning(): void
    {
        $html = $this->render([], $this->overview([
            'nazi_era_provenance_checked' => 1,
            'nazi_era_provenance_clear'   => 1,
        ]));

        $this->assertStringContainsString('Due diligence', $html);
        $this->assertStringContainsString('Clear', $html);
        $this->assertStringNotContainsString('border-warning', $html);
    }
}
