<?php

/**
 * DonorEncryptionTest - #1261 donor contact PII field-level encryption.
 *
 * Mirrors VendorEncryptionAuditTest. Runs against the pre-built heratio_test
 * DB and rolls back each test (DatabaseTransactions, NOT RefreshDatabase - the
 * ~995 AtoM base tables this exercises must survive).
 *
 * Donor contacts live in the shared, READ-ONLY base table contact_information
 * (+ contact_information_i18n). Only the columns wide enough to hold ENC2:
 * ciphertext are encrypted - email (VARCHAR(255)) + city (VARCHAR(1024)) -
 * matching RepositoryService and the ahg_encrypted_fields registry.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\EncryptionService;
use AhgDonorManage\Services\DonorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonorEncryptionTest extends TestCase
{
    use DatabaseTransactions;

    private function enableEncryption(bool $on): void
    {
        AhgSettingsService::set('encryption_enabled', $on ? '1' : '0', 'encryption');
        AhgSettingsService::set('encryption_field_contact_details', $on ? '1' : '0', 'encryption');
        AhgSettingsService::clearCache();
    }

    private function donorData(string $name): array
    {
        return [
            'authorized_form_of_name' => $name.' '.Str::random(5),
            'contacts' => [
                [
                    'primary_contact' => 1,
                    'contact_person' => 'Jane Donor',
                    'street_address' => '12 Archive Way',
                    'email' => 'donor@example.test',
                    'telephone' => '+27 11 555 1234',
                    'city' => 'Pretoria',
                    'region' => 'Gauteng',
                ],
            ],
        ];
    }

    public function test_donor_contact_pii_is_ciphertext_when_encryption_enabled(): void
    {
        $this->enableEncryption(true);
        $svc = new DonorService('en');

        $donorId = $svc->create($this->donorData('Enc Donor'));

        $ci = DB::table('contact_information')->where('actor_id', $donorId)->first();
        $i18n = DB::table('contact_information_i18n')->where('id', $ci->id)->first();
        $sentinel = EncryptionService::SENTINEL;

        $this->assertStringStartsWith($sentinel, $ci->email, 'email should be stored encrypted');
        $this->assertStringStartsWith($sentinel, $i18n->city, 'city should be stored encrypted');

        // Non-encrypted PII columns stay plaintext (mirror RepositoryService).
        $this->assertEquals('+27 11 555 1234', $ci->telephone);
        $this->assertEquals('Jane Donor', $ci->contact_person);

        // Read-back round-trips to plaintext.
        $contacts = $svc->getContacts($donorId);
        $this->assertCount(1, $contacts);
        $row = $contacts->first();
        $this->assertEquals('donor@example.test', $row->email);
        $this->assertEquals('Pretoria', $row->city);
    }

    public function test_donor_contact_pii_is_plaintext_when_encryption_disabled(): void
    {
        $this->enableEncryption(false);
        $svc = new DonorService('en');

        $donorId = $svc->create($this->donorData('Plain Donor'));

        $ci = DB::table('contact_information')->where('actor_id', $donorId)->first();
        $i18n = DB::table('contact_information_i18n')->where('id', $ci->id)->first();

        $this->assertEquals('donor@example.test', $ci->email);
        $this->assertEquals('Pretoria', $i18n->city);

        // getContacts pass-through still returns plaintext.
        $row = $svc->getContacts($donorId)->first();
        $this->assertEquals('donor@example.test', $row->email);
        $this->assertEquals('Pretoria', $row->city);
    }

    public function test_backfill_encrypts_legacy_plaintext_row_idempotently(): void
    {
        // Insert a plaintext donor contact (encryption off so it stores plain).
        $this->enableEncryption(false);
        $svc = new DonorService('en');
        $donorId = $svc->create($this->donorData('Backfill Donor'));

        $before = DB::table('contact_information')->where('actor_id', $donorId)->first();
        $beforeI18n = DB::table('contact_information_i18n')->where('id', $before->id)->first();
        $this->assertEquals('donor@example.test', $before->email, 'precondition: plaintext email');
        $this->assertEquals('Pretoria', $beforeI18n->city, 'precondition: plaintext city');

        // Turn encryption on and run the backfill.
        $this->enableEncryption(true);
        $this->artisan('ahg:donor-encrypt-backfill')->assertExitCode(0);

        $after = DB::table('contact_information')->where('id', $before->id)->first();
        $afterI18n = DB::table('contact_information_i18n')->where('id', $before->id)->first();
        $sentinel = EncryptionService::SENTINEL;
        $this->assertStringStartsWith($sentinel, $after->email, 'backfill should encrypt email');
        $this->assertStringStartsWith($sentinel, $afterI18n->city, 'backfill should encrypt city');

        $cipherEmail = $after->email;
        $cipherCity = $afterI18n->city;

        // Running twice is idempotent - already-ciphertext rows are skipped.
        $this->artisan('ahg:donor-encrypt-backfill')->assertExitCode(0);
        $after2 = DB::table('contact_information')->where('id', $before->id)->first();
        $after2I18n = DB::table('contact_information_i18n')->where('id', $before->id)->first();
        $this->assertEquals($cipherEmail, $after2->email, 'second run must not re-encrypt email');
        $this->assertEquals($cipherCity, $after2I18n->city, 'second run must not re-encrypt city');

        // Still decrypts to the original plaintext on read.
        $row = $svc->getContacts($donorId)->first();
        $this->assertEquals('donor@example.test', $row->email);
        $this->assertEquals('Pretoria', $row->city);
    }
}
