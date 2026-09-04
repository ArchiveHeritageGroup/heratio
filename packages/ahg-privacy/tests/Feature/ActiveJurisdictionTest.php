<?php

/**
 * ActiveJurisdictionTest - the registry has one reader, and it picks one row.
 *
 * PrivacyService::activeJurisdiction() replaced three inline copies of the same
 * query in PrivacyController and a fourth in SpectrumController. The Spectrum
 * one was not merely duplicated, it was wrong: `->where('is_active', 1)->first()`
 * with no code filter and no ordering returned whichever active row MySQL
 * happened to yield, so the header naming the regime could disagree with the
 * figures underneath it, and could differ between two loads of the same page.
 *
 * The selection contract is what those callers depend on, so it is pinned here:
 * the named code when the registry has it, the first active row otherwise. DB
 * backed by necessity - the point is the query - and privacy_jurisdiction is in
 * database/core/*.sql, so it exists in CI's test database.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

namespace AhgPrivacy\Tests\Feature;

use AhgPrivacy\Services\PrivacyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActiveJurisdictionTest extends TestCase
{
    use DatabaseTransactions;

    private function seedOnly(array $codes): void
    {
        // Own the fixture: the test database ships its own registry rows, and a
        // test that assumes an empty table is the exact mistake that kept the
        // Package suite red for five weeks (v1.154.715).
        DB::table('privacy_jurisdiction')->update(['is_active' => 0]);

        $order = 10;
        foreach ($codes as $code) {
            $order++;
            DB::table('privacy_jurisdiction')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => strtoupper($code),
                    'full_name' => 'Full '.$code,
                    'country' => 'Country '.$code,
                    'region' => 'Testland',
                    'is_active' => 1,
                    'sort_order' => $order,
                ]
            );
        }
    }

    public function test_it_returns_the_named_jurisdiction(): void
    {
        $this->seedOnly(['popia', 'gdpr']);

        $this->assertSame('gdpr', PrivacyService::activeJurisdiction('gdpr')->code);
    }

    public function test_it_falls_back_to_the_first_active_row_for_an_unknown_code(): void
    {
        $this->seedOnly(['popia', 'gdpr']);

        $this->assertSame('popia', PrivacyService::activeJurisdiction('no_such_regime')->code);
    }

    public function test_no_code_means_the_first_active_row(): void
    {
        $this->seedOnly(['popia', 'gdpr']);

        $this->assertSame('popia', PrivacyService::activeJurisdiction()->code);
    }

    /**
     * The Spectrum defect. Order comes from sort_order, not from whatever the
     * database returns first, so the answer is the same on every request.
     */
    public function test_the_first_active_row_is_decided_by_sort_order(): void
    {
        $this->seedOnly(['gdpr', 'popia']);

        $this->assertSame('gdpr', PrivacyService::activeJurisdiction()->code);
        $this->assertSame('gdpr', array_key_first(PrivacyService::activeJurisdictions()));
    }

    public function test_a_deactivated_jurisdiction_is_never_selected(): void
    {
        $this->seedOnly(['popia', 'gdpr']);
        DB::table('privacy_jurisdiction')->where('code', 'popia')->update(['is_active' => 0]);

        $this->assertSame('gdpr', PrivacyService::activeJurisdiction('popia')->code);
    }

    /** The dashboard reads name, full_name and country off this as an object. */
    public function test_it_carries_the_fields_the_views_read(): void
    {
        $this->seedOnly(['popia']);
        $active = PrivacyService::activeJurisdiction('popia');

        foreach (['code', 'name', 'full_name', 'country', 'icon'] as $field) {
            $this->assertObjectHasProperty($field, $active);
        }
    }
}
