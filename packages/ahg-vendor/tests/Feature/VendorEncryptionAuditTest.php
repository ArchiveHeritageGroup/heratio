<?php

/**
 * VendorEncryptionAuditTest - #1264 PII encryption + #1263 state-change audit.
 *
 * Runs against the pre-built heratio_test DB and rolls back each test
 * (DatabaseTransactions, NOT RefreshDatabase - the base tables must survive).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\EncryptionService;
use AhgVendor\Services\VendorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendorEncryptionAuditTest extends TestCase
{
    use DatabaseTransactions;

    private function enableEncryption(bool $on): void
    {
        AhgSettingsService::set('encryption_enabled', $on ? '1' : '0', 'encryption');
        AhgSettingsService::set('encryption_field_contact_details', $on ? '1' : '0', 'encryption');
        AhgSettingsService::set('encryption_field_financial_data', $on ? '1' : '0', 'encryption');
        AhgSettingsService::clearCache();
    }

    private function vendorData(string $name): array
    {
        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'vendor_type' => 'company',
            'email' => 'vendor@example.test',
            'phone' => '+27 11 555 1234',
            'phone_alt' => '+27 11 555 9999',
            'fax' => '+27 11 555 0000',
            'bank_name' => 'Test Bank',
            'bank_branch' => 'Sandton',
            'bank_account_number' => '1234567890',
            'bank_branch_code' => '250655',
            'bank_account_type' => 'cheque',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    // ---------------------------------------------------------------------
    // #1264 encryption
    // ---------------------------------------------------------------------

    public function test_vendor_pii_is_ciphertext_when_encryption_enabled(): void
    {
        $this->enableEncryption(true);
        $svc = new VendorService;

        $vendorId = $svc->createVendor($this->vendorData('Enc Vendor'));

        $raw = DB::table('ahg_vendors')->where('id', $vendorId)->first();
        $sentinel = EncryptionService::SENTINEL;

        $this->assertStringStartsWith($sentinel, $raw->email, 'email should be stored encrypted');
        $this->assertStringStartsWith($sentinel, $raw->phone, 'phone should be stored encrypted');
        $this->assertStringStartsWith($sentinel, $raw->bank_account_number, 'bank_account_number should be stored encrypted');
        $this->assertStringStartsWith($sentinel, $raw->bank_branch_code, 'bank_branch_code should be stored encrypted');

        // Read-back round-trips to plaintext.
        $svc->decryptVendor($raw);
        $this->assertEquals('vendor@example.test', $raw->email);
        $this->assertEquals('1234567890', $raw->bank_account_number);
    }

    public function test_contact_pii_is_ciphertext_when_encryption_enabled(): void
    {
        $this->enableEncryption(true);
        $svc = new VendorService;

        $vendorId = $svc->createVendor($this->vendorData('Enc Vendor C'));
        $contactId = $svc->createContact([
            'vendor_id' => $vendorId,
            'name' => 'Jane Contact',
            'phone' => '+27 21 111 2222',
            'mobile' => '+27 82 333 4444',
            'email' => 'jane@example.test',
            'created_at' => now(),
        ]);

        $raw = DB::table('ahg_vendor_contacts')->where('id', $contactId)->first();
        $sentinel = EncryptionService::SENTINEL;
        $this->assertStringStartsWith($sentinel, $raw->email);
        $this->assertStringStartsWith($sentinel, $raw->phone);
        $this->assertStringStartsWith($sentinel, $raw->mobile);
        // Non-PII column stays plaintext.
        $this->assertEquals('Jane Contact', $raw->name);

        $svc->decryptContact($raw);
        $this->assertEquals('jane@example.test', $raw->email);
    }

    public function test_vendor_pii_is_plaintext_when_encryption_disabled(): void
    {
        $this->enableEncryption(false);
        $svc = new VendorService;

        $vendorId = $svc->createVendor($this->vendorData('Plain Vendor'));

        $raw = DB::table('ahg_vendors')->where('id', $vendorId)->first();
        $this->assertEquals('vendor@example.test', $raw->email);
        $this->assertEquals('1234567890', $raw->bank_account_number);
    }

    // ---------------------------------------------------------------------
    // #1263 audit
    // ---------------------------------------------------------------------
    //
    // AuditLog::capture* writes a security_audit_log row directly via
    // writeDirect() when no request is bound (CLI / queue context), and
    // otherwise stashes onto the bound request for the audit middleware to
    // persist on the real HTTP response. The PHPUnit process has a bound
    // request, so we assert BOTH:
    //   - the persisted row, by exercising writeDirect through the service in
    //     a no-request context (a real Job/closure dispatched on the sync
    //     queue runs with the request unbound for the closure's lifetime); and
    //   - here, more simply, the stashed audit.diff payload the middleware
    //     would persist verbatim - that is the service's actual output and is
    //     where redaction lives.

    private function freshRequest(): void
    {
        // Reset audit.* attributes between capture calls so each assertion
        // reads the latest stash.
        $req = $this->app['request'];
        $req->attributes->remove('audit.diff');
    }

    private function stashedDiff(): ?array
    {
        return $this->app['request']->attributes->get('audit.diff');
    }

    public function test_create_edit_delete_emit_audit_payload_without_raw_pii(): void
    {
        $this->enableEncryption(true);
        $svc = new VendorService;

        // CREATE -> a request is bound, so the payload is stashed onto it.
        $vendorId = $svc->createVendor($this->vendorData('Audit Vendor'));

        $createDiff = $this->stashedDiff();
        $this->assertNotNull($createDiff, 'create should stash an audit payload');
        $encoded = json_encode($createDiff);
        $this->assertStringNotContainsString('1234567890', $encoded, 'no raw bank number in snapshot');
        $this->assertStringNotContainsString('vendor@example.test', $encoded, 'no raw email in snapshot');
        $this->assertStringContainsString('[redacted]', $encoded);
        $this->assertTrue(! empty($createDiff['created']));

        // EDIT -> assert changed_fields tracks the non-PII change.
        $this->freshRequest();
        $svc->updateVendor($vendorId, ['notes' => 'changed note', 'updated_at' => now()]);
        $editDiff = $this->stashedDiff();
        $this->assertNotNull($editDiff);
        $this->assertContains('notes', $editDiff['changed_fields'] ?? []);

        // DELETE -> assert the delete payload + row removal.
        $this->freshRequest();
        $svc->deleteVendor($vendorId);
        $delDiff = $this->stashedDiff();
        $this->assertNotNull($delDiff);
        $this->assertTrue(! empty($delDiff['deleted']));
        $this->assertStringNotContainsString('1234567890', json_encode($delDiff));
        $this->assertDatabaseMissing('ahg_vendors', ['id' => $vendorId]);
    }

    /**
     * Exercises the persisted-row path: with the request binding removed,
     * AuditLog writes straight into security_audit_log via writeDirect().
     */
    public function test_audit_persists_a_row_in_no_request_context(): void
    {
        $this->enableEncryption(true);
        $svc = new VendorService;

        // actingAs sets the guard user in memory so auth()->id() resolves
        // without re-reading the request - safe to forget the bound request
        // afterwards (which forces AuditLog::stash -> writeDirect).
        $user = new \App\Models\User;
        $user->id = 1;
        $this->actingAs($user);

        // Capture + restore the bound request so other tests are unaffected.
        $saved = $this->app->bound('request') ? $this->app['request'] : null;
        $this->app->forgetInstance('request');

        try {
            $vendorId = $svc->createVendor($this->vendorData('Direct Audit Vendor'));
        } finally {
            if ($saved !== null) {
                $this->app->instance('request', $saved);
            }
        }

        $row = DB::table('security_audit_log')
            ->where('object_type', 'vendor')
            ->where('object_id', $vendorId)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($row, 'no-request create should write a security_audit_log row');
        $this->assertEquals('create', $row->action);
        $details = (string) $row->details;
        $this->assertStringNotContainsString('1234567890', $details);
        $this->assertStringNotContainsString('vendor@example.test', $details);
        $this->assertStringContainsString('[redacted]', $details);
    }

    public function test_contact_changes_emit_audit_payload(): void
    {
        $this->enableEncryption(true);
        $svc = new VendorService;

        $vendorId = $svc->createVendor($this->vendorData('Audit Vendor Contact'));
        $this->freshRequest();
        $contactId = $svc->createContact([
            'vendor_id' => $vendorId,
            'name' => 'Bob',
            'email' => 'bob@example.test',
            'created_at' => now(),
        ]);

        $diff = $this->stashedDiff();
        $this->assertNotNull($diff, 'contact create should stash an audit payload');
        $this->assertStringNotContainsString('bob@example.test', json_encode($diff));
        $this->assertTrue(! empty($diff['created']));
    }

    // ---------------------------------------------------------------------
    // backfill command
    // ---------------------------------------------------------------------

    public function test_backfill_encrypts_plaintext_rows_idempotently(): void
    {
        // Insert a plaintext row (encryption off so the service stores plain).
        $this->enableEncryption(false);
        $svc = new VendorService;
        $vendorId = $svc->createVendor($this->vendorData('Backfill Vendor'));

        $before = DB::table('ahg_vendors')->where('id', $vendorId)->first();
        $this->assertEquals('1234567890', $before->bank_account_number, 'precondition: plaintext');

        // Now turn encryption on and run the backfill.
        $this->enableEncryption(true);
        $this->artisan('ahg:vendor-encrypt-backfill')->assertExitCode(0);

        $after = DB::table('ahg_vendors')->where('id', $vendorId)->first();
        $sentinel = EncryptionService::SENTINEL;
        $this->assertStringStartsWith($sentinel, $after->bank_account_number, 'backfill should encrypt the row');
        $this->assertStringStartsWith($sentinel, $after->email);

        $cipher = $after->bank_account_number;

        // Running twice is idempotent - already-ciphertext rows are skipped and
        // the stored value is unchanged.
        $this->artisan('ahg:vendor-encrypt-backfill')->assertExitCode(0);
        $after2 = DB::table('ahg_vendors')->where('id', $vendorId)->first();
        $this->assertEquals($cipher, $after2->bank_account_number, 'second run must not re-encrypt');

        // And it still decrypts to the original plaintext.
        $svc->decryptVendor($after2);
        $this->assertEquals('1234567890', $after2->bank_account_number);
    }
}
