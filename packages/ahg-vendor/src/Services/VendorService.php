<?php

/**
 * VendorService - owns vendor + vendor-contact write paths.
 *
 * Centralises two cross-cutting concerns the controller used to lack:
 *
 *   #1264 - field-level PII encryption of contact + bank columns via
 *           \AhgCore\Services\EncryptionService. Contact-ish columns
 *           (email/phone/phone_alt/fax + contact name?/phone/mobile/email)
 *           use CATEGORY_CONTACT_DETAILS; the bank_* columns use
 *           CATEGORY_FINANCIAL_DATA. Both categories are settings-gated, so
 *           writes stay plaintext when the operator has encryption off, and
 *           decrypt() round-trips plaintext safely so legacy rows read fine.
 *
 *   #1263 - state-change audit logging via \AhgCore\Support\AuditLog.
 *           create/update/delete on the vendor, and add/update/delete on a
 *           vendor contact, emit a security_audit_log row. Snapshots are
 *           PII-redacted: a field that holds contact/bank PII is logged as
 *           the marker '[redacted]' (when set) or null, never the plaintext
 *           value - mirroring how RepositoryService keeps raw email/city out
 *           of its diff.
 *
 * Mirrors packages/ahg-repository-manage/src/Services/RepositoryService.php.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgVendor\Services;

use AhgCore\Services\EncryptionService;
use AhgCore\Support\AuditLog;
use Illuminate\Support\Facades\DB;

class VendorService
{
    protected EncryptionService $enc;

    /**
     * ahg_vendors columns encrypted under CATEGORY_CONTACT_DETAILS.
     */
    private const VENDOR_CONTACT_FIELDS = ['email', 'phone', 'phone_alt', 'fax'];

    /**
     * ahg_vendors columns encrypted under CATEGORY_FINANCIAL_DATA.
     */
    private const VENDOR_BANK_FIELDS = [
        'bank_name', 'bank_branch', 'bank_account_number', 'bank_branch_code', 'bank_account_type',
    ];

    /**
     * ahg_vendor_contacts columns encrypted under CATEGORY_CONTACT_DETAILS.
     */
    private const CONTACT_FIELDS = ['phone', 'mobile', 'email'];

    public function __construct(?EncryptionService $enc = null)
    {
        $this->enc = $enc ?? new EncryptionService;
    }

    // =========================================================================
    //  Category lookup
    // =========================================================================

    private function vendorCategory(string $column): string
    {
        return in_array($column, self::VENDOR_BANK_FIELDS, true)
            ? EncryptionService::CATEGORY_FINANCIAL_DATA
            : EncryptionService::CATEGORY_CONTACT_DETAILS;
    }

    /**
     * Whether a given ahg_vendors column is a PII column (encrypted +
     * redacted in the audit snapshot).
     */
    private function vendorIsPii(string $column): bool
    {
        return in_array($column, self::VENDOR_CONTACT_FIELDS, true)
            || in_array($column, self::VENDOR_BANK_FIELDS, true);
    }

    // =========================================================================
    //  Encryption helpers (write)
    // =========================================================================

    /**
     * Encrypt the registered PII columns of a vendor data row in place.
     * No-op per column when encryption / the category is disabled (encrypt()
     * passes the plaintext through).
     */
    private function encryptVendorRow(array $data, $rowId = null): array
    {
        foreach (array_merge(self::VENDOR_CONTACT_FIELDS, self::VENDOR_BANK_FIELDS) as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null && $data[$col] !== '') {
                $data[$col] = $this->enc->encrypt(
                    $this->vendorCategory($col),
                    (string) $data[$col],
                    'ahg_vendors',
                    $col,
                    $rowId,
                );
            }
        }

        return $data;
    }

    /**
     * Encrypt the registered PII columns of a contact data row in place.
     */
    private function encryptContactRow(array $data, $rowId = null): array
    {
        foreach (self::CONTACT_FIELDS as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null && $data[$col] !== '') {
                $data[$col] = $this->enc->encrypt(
                    EncryptionService::CATEGORY_CONTACT_DETAILS,
                    (string) $data[$col],
                    'ahg_vendor_contacts',
                    $col,
                    $rowId,
                );
            }
        }

        return $data;
    }

    // =========================================================================
    //  Decryption helpers (read) - callers pass a fetched stdClass row.
    // =========================================================================

    /**
     * Decrypt the registered PII columns of a vendor row object. Safe on
     * plaintext rows (decrypt() pass-through). Returns the same object.
     */
    public function decryptVendor(?object $vendor): ?object
    {
        if (! $vendor) {
            return $vendor;
        }
        foreach (array_merge(self::VENDOR_CONTACT_FIELDS, self::VENDOR_BANK_FIELDS) as $col) {
            if (isset($vendor->{$col}) && $vendor->{$col} !== '') {
                $vendor->{$col} = $this->enc->decrypt(
                    $this->vendorCategory($col),
                    (string) $vendor->{$col},
                    'ahg_vendors',
                    $col,
                    $vendor->id ?? null,
                );
            }
        }

        return $vendor;
    }

    /**
     * Decrypt every vendor row in an iterable (list page). Mutates in place.
     */
    public function decryptVendors(iterable $vendors): iterable
    {
        foreach ($vendors as $v) {
            $this->decryptVendor($v);
        }

        return $vendors;
    }

    /**
     * Decrypt the registered PII columns of a contact row object.
     */
    public function decryptContact(?object $contact): ?object
    {
        if (! $contact) {
            return $contact;
        }
        foreach (self::CONTACT_FIELDS as $col) {
            if (isset($contact->{$col}) && $contact->{$col} !== '') {
                $contact->{$col} = $this->enc->decrypt(
                    EncryptionService::CATEGORY_CONTACT_DETAILS,
                    (string) $contact->{$col},
                    'ahg_vendor_contacts',
                    $col,
                    $contact->id ?? null,
                );
            }
        }

        return $contact;
    }

    /**
     * Decrypt every contact row in an iterable. Mutates in place.
     */
    public function decryptContacts(iterable $contacts): iterable
    {
        foreach ($contacts as $c) {
            $this->decryptContact($c);
        }

        return $contacts;
    }

    // =========================================================================
    //  Audit snapshots (PII-redacted)
    // =========================================================================

    /**
     * Flat snapshot of a vendor row for the security_audit_log before/after
     * diff. PII columns are redacted: a populated PII field is logged as
     * '[redacted]', an empty one as null, so the diff records *that* a PII
     * field changed without leaking the plaintext value.
     */
    private function vendorAuditSnapshot(int $id): array
    {
        $row = DB::table('ahg_vendors')->where('id', $id)->first();
        if (! $row) {
            return [];
        }
        $arr = (array) $row;
        unset($arr['created_at'], $arr['updated_at']);

        return $this->redactVendorPii($arr);
    }

    /**
     * Redact PII columns in a vendor row array (raw values out, marker in).
     */
    private function redactVendorPii(array $arr): array
    {
        foreach ($arr as $k => $v) {
            if ($this->vendorIsPii($k)) {
                $arr[$k] = ($v === null || $v === '') ? null : '[redacted]';
            }
        }

        return $arr;
    }

    /**
     * Flat snapshot of a vendor-contact row, PII redacted.
     */
    private function contactAuditSnapshot(int $contactId): array
    {
        $row = DB::table('ahg_vendor_contacts')->where('id', $contactId)->first();
        if (! $row) {
            return [];
        }
        $arr = (array) $row;
        unset($arr['created_at'], $arr['updated_at']);

        return $this->redactContactPii($arr);
    }

    private function redactContactPii(array $arr): array
    {
        foreach ($arr as $k => $v) {
            if (in_array($k, self::CONTACT_FIELDS, true)) {
                $arr[$k] = ($v === null || $v === '') ? null : '[redacted]';
            }
        }

        return $arr;
    }

    // =========================================================================
    //  Vendor CRUD (encrypt-on-write + audit)
    // =========================================================================

    /**
     * Insert a vendor. $data is the already-validated column array (the
     * controller still resolves slug / vendor_code / created_by / timestamps).
     * Returns the new vendor id.
     */
    public function createVendor(array $data): int
    {
        // Encrypt without a row id first; encryption is keyed on category, the
        // target id is only audit metadata, so a null id is fine on insert.
        $data = $this->encryptVendorRow($data, null);

        $vendorId = (int) DB::table('ahg_vendors')->insertGetId($data);

        AuditLog::captureCreate($vendorId, 'vendor', $this->vendorAuditSnapshot($vendorId));

        return $vendorId;
    }

    /**
     * Update a vendor by id. $data is the validated column array.
     */
    public function updateVendor(int $vendorId, array $data): void
    {
        $before = $this->vendorAuditSnapshot($vendorId);

        $data = $this->encryptVendorRow($data, $vendorId);
        DB::table('ahg_vendors')->where('id', $vendorId)->update($data);

        $after = $this->vendorAuditSnapshot($vendorId);
        AuditLog::captureEdit($vendorId, 'vendor', $before, $after);
    }

    /**
     * Delete a vendor + its contacts + services. Audit fires before the row
     * is gone. Caller is responsible for the active-transaction guard.
     */
    public function deleteVendor(int $vendorId): void
    {
        AuditLog::captureDelete($vendorId, 'vendor', $this->vendorAuditSnapshot($vendorId));

        DB::transaction(function () use ($vendorId) {
            DB::table('ahg_vendor_services')->where('vendor_id', $vendorId)->delete();
            DB::table('ahg_vendor_contacts')->where('vendor_id', $vendorId)->delete();
            DB::table('ahg_vendors')->where('id', $vendorId)->delete();
        });
    }

    // =========================================================================
    //  Vendor-contact CRUD (encrypt-on-write + audit)
    // =========================================================================

    /**
     * Insert a vendor contact. $data is the validated column array including
     * vendor_id. Returns the new contact id.
     */
    public function createContact(array $data): int
    {
        $data = $this->encryptContactRow($data, null);

        $contactId = (int) DB::table('ahg_vendor_contacts')->insertGetId($data);

        AuditLog::captureCreate($contactId, 'vendor_contact', $this->contactAuditSnapshot($contactId));

        return $contactId;
    }

    /**
     * Update a vendor contact by id.
     */
    public function updateContact(int $contactId, array $data): void
    {
        $before = $this->contactAuditSnapshot($contactId);

        $data = $this->encryptContactRow($data, $contactId);
        DB::table('ahg_vendor_contacts')->where('id', $contactId)->update($data);

        $after = $this->contactAuditSnapshot($contactId);
        AuditLog::captureEdit($contactId, 'vendor_contact', $before, $after);
    }

    /**
     * Delete a vendor contact by id. Audit fires before the row is gone.
     */
    public function deleteContact(int $contactId): void
    {
        $snapshot = $this->contactAuditSnapshot($contactId);
        if (! empty($snapshot)) {
            AuditLog::captureDelete($contactId, 'vendor_contact', $snapshot);
        }

        DB::table('ahg_vendor_contacts')->where('id', $contactId)->delete();
    }
}
