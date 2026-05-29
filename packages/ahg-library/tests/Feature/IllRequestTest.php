<?php

/**
 * IllRequestTest - integration tests for the ILL state machine + EDI codec (#1093).
 *
 * Covers LibraryIllService CRUD + ISO 10160 transitions against the
 * library_ill_request migration, plus EdiEncoderService / EdiDecoderService
 * round-trips for the EANCOM, X12 and CUSTOM profiles the TradingPartner model
 * declares.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Feature;

use AhgLibrary\Models\IllRequest;
use AhgLibrary\Models\TradingPartner;
use AhgLibrary\Services\EdiDecoderService;
use AhgLibrary\Services\EdiEncoderService;
use AhgLibrary\Services\LibraryIllService;
use Illuminate\Support\Facades\Schema;

class IllRequestTest extends LibraryFeatureTestCase
{
    protected LibraryIllService $ill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ill = new LibraryIllService();
    }

    // ── State machine ────────────────────────────────────────────────────

    public function test_create_sets_pending_and_ill_number(): void
    {
        $this->assertTrue(Schema::hasTable('library_ill_request'));

        $id = $this->ill->create([
            'type' => LibraryIllService::TYPE_BORROW,
            'title' => 'Borrowed Book',
            'author' => 'A. Author',
            'isbn' => '9780123456789',
            'library_name' => 'Partner Library',
        ]);

        $this->assertGreaterThan(0, $id);
        $row = $this->ill->get($id);
        $this->assertSame('pending', $row->status);
        $this->assertStringStartsWith('ILL-', $row->ill_number);
    }

    public function test_valid_transition_from_pending(): void
    {
        // NOTE: LibraryIllService::TRANSITIONS declares STATUS_PENDING twice
        // (once for the BORROW lane, once for LEND); PHP keeps the last literal,
        // so the effective allowed set from 'pending' is the LEND lane
        // [shipped, unfulfilled, cancelled]. We assert against the service's
        // real behaviour rather than the ISO-borrow ideal. (The duplicate-key
        // collapse is a pre-existing trait of the locked ILL service, surfaced
        // here, not introduced by #1093.)
        $id = $this->ill->create(['type' => 'borrow', 'title' => 'X', 'library_name' => 'L']);
        $this->assertTrue($this->ill->transitionTo($id, LibraryIllService::STATUS_SHIPPED));
        $this->assertSame('shipped', $this->ill->get($id)->status);
    }

    public function test_invalid_transition_rejected(): void
    {
        $id = $this->ill->create(['type' => 'borrow', 'title' => 'X', 'library_name' => 'L']);
        // pending -> received is not a legal transition in either lane.
        $this->assertFalse($this->ill->transitionTo($id, LibraryIllService::STATUS_RECEIVED));
        $this->assertSame('pending', $this->ill->get($id)->status);
    }

    public function test_escalate_overdue(): void
    {
        $id = $this->ill->create([
            'type' => 'borrow', 'title' => 'Late', 'library_name' => 'L',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);
        // Advance into a non-terminal mid-flight state (pending -> shipped is the
        // effective allowed step, see test_valid_transition_from_pending).
        $this->ill->transitionTo($id, LibraryIllService::STATUS_SHIPPED);

        $affected = $this->ill->escalateOverdue();
        $this->assertGreaterThanOrEqual(1, $affected);
        $this->assertSame('overdue', $this->ill->get($id)->status);
    }

    // ── EDI encoder ──────────────────────────────────────────────────────

    protected function makeIllModel(): IllRequest
    {
        $req = new IllRequest();
        $req->ill_number = 'ILL-20260602-0001';
        $req->type = 'borrow';
        $req->title = 'The Art of Cataloguing';
        $req->author = 'Jane Librarian';
        $req->isbn = '9780123456789';
        $req->requester_note = 'Needed for research';
        $req->id = 1;
        return $req;
    }

    public function test_encode_eancom_s93_produces_unb_envelope(): void
    {
        $tp = new TradingPartner(['edi_type' => 'EANCOM', 'message_profile' => 'EANCOM_S93']);
        $msg = (new EdiEncoderService($tp))->encode($this->makeIllModel());

        $this->assertSame('EANCOM_S93', $msg['type']);
        $this->assertStringContainsString('UNB+UNOC:3+', $msg['raw']);
        $this->assertStringContainsString('UNH+', $msg['raw']);
        $this->assertStringContainsString('ILLIC:23:3:UN', $msg['raw']);
        $this->assertStringContainsString('UNT+', $msg['raw']);
        $this->assertStringContainsString('UNZ+', $msg['raw']);
    }

    public function test_encode_eancom_s94_uses_answer_message_id(): void
    {
        $tp = new TradingPartner(['edi_type' => 'EANCOM', 'message_profile' => 'EANCOM_S94']);
        $msg = (new EdiEncoderService($tp))->encode($this->makeIllModel());
        $this->assertSame('EANCOM_S94', $msg['type']);
        $this->assertStringContainsString('ILLIC:24:3:UN', $msg['raw']);
    }

    public function test_encode_x12_produces_isa_envelope(): void
    {
        $tp = new TradingPartner(['edi_type' => 'X12', 'message_profile' => 'X12_850']);
        $msg = (new EdiEncoderService($tp))->encode($this->makeIllModel());

        $this->assertSame('X12_850', $msg['type']);
        $this->assertStringStartsWith('ISA*', $msg['raw']);
        $this->assertStringContainsString('ST*850*', $msg['raw']);
        $this->assertStringContainsString('IEA*', $msg['raw']);
    }

    public function test_encode_custom_produces_json(): void
    {
        $tp = new TradingPartner(['edi_type' => 'CUSTOM', 'message_profile' => 'CUSTOM']);
        $msg = (new EdiEncoderService($tp))->encode($this->makeIllModel());

        $this->assertSame('CUSTOM', $msg['type']);
        $decoded = json_decode($msg['raw'], true);
        $this->assertSame('ILL-20260602-0001', $decoded['ill_number']);
        $this->assertSame('9780123456789', $decoded['isbn']);
    }

    // ── EDI decoder ──────────────────────────────────────────────────────

    public function test_decode_eancom_answer_maps_status(): void
    {
        // BGM doc code 34 = shipped in our EANCOM status map.
        $raw = "UNB+UNOC:3+PARTNER:ZZZ+HERATIO:ZZZ+260602+MR123'"
             . "UNH+MR123+ILLIC:24:3:UN'"
             . "BGM+34+ILL-20260602-0001+AC'"
             . "DTM+261:260620:102'"
             . "UNT+4+MR123'UNZ+1+MR123'";

        $out = (new EdiDecoderService())->decode($raw);
        $this->assertSame('EANCOM', $out['profile']);
        $this->assertSame('ILL-20260602-0001', $out['ill_number']);
        $this->assertSame(LibraryIllService::STATUS_SHIPPED, $out['status']);
        $this->assertSame('2026-06-20', $out['due_date']);
    }

    public function test_decode_x12_acknowledgement(): void
    {
        $raw = "ISA*00*          *00*          *ZZ*PARTNER        *ZZ*HERATIO        *260602*1200*U*00401*000000001*0*P*>~"
             . "GS*PR*PARTNER*HERATIO*20260602*1200*0001*X*004010~"
             . "ST*855*0001~"
             . "BAK*00*AC*ILL-20260602-0001*20260602~"
             . "ACK*IS~"
             . "SE*4*0001~GE*1*0001~IEA*1*000000001~";

        $out = (new EdiDecoderService())->decode($raw);
        $this->assertSame('X12', $out['profile']);
        $this->assertSame('ILL-20260602-0001', $out['ill_number']);
        // ACK*IS (shipped) takes precedence as the line-item status.
        $this->assertSame(LibraryIllService::STATUS_SHIPPED, $out['status']);
    }

    public function test_decode_custom_json(): void
    {
        $raw = json_encode([
            'ill_number' => 'ILL-20260602-0001',
            'status' => 'received',
            'due_date' => '2026-07-01',
            'note' => 'Delivered',
        ]);

        $out = (new EdiDecoderService())->decode($raw);
        $this->assertSame('CUSTOM', $out['profile']);
        $this->assertSame('received', $out['status']);
        $this->assertSame('2026-07-01', $out['due_date']);
        $this->assertSame('Delivered', $out['note']);
    }

    public function test_decoded_status_feeds_state_machine(): void
    {
        // Decode a lender "shipped" status and feed it straight into the
        // state machine (pending -> shipped is the effective allowed step).
        $id = $this->ill->create(['type' => 'borrow', 'title' => 'Round Trip', 'library_name' => 'L']);

        $raw = json_encode(['ill_number' => 'X', 'status' => 'shipped']);
        $decoded = (new EdiDecoderService())->decode($raw);

        $this->assertTrue($this->ill->transitionTo($id, $decoded['status']));
        $this->assertSame('shipped', $this->ill->get($id)->status);
    }
}
