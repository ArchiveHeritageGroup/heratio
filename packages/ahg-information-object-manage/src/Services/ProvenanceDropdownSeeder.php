<?php

/**
 * ProvenanceDropdownSeeder - Idempotent seed for provenance controlled vocabularies
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

namespace AhgInformationObjectManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the provenance controlled vocabularies into ahg_dropdown so site
 * admins can manage them via the Dropdown Manager instead of the terms
 * living as hardcoded arrays/<option> lists (#1355). ProvenanceService
 * reads these taxonomies back with a hardcoded fallback, so a site that
 * hasn't seeded yet keeps working.
 */
class ProvenanceDropdownSeeder
{
    public static function seed(): void
    {
        if (!Schema::hasTable('ahg_dropdown')) {
            return;
        }

        $rows = [];
        $vocabularies = [
            'provenance_acquisition_type' => ['Acquisition Type', [
                'purchase'         => 'Purchase',
                'gift'             => 'Gift / Donation',
                'bequest'          => 'Bequest',
                'exchange'         => 'Exchange',
                'transfer'         => 'Transfer',
                'field_collection' => 'Field collection',
                'excavation'       => 'Excavation',
                'commission'       => 'Commission',
                'loan'             => 'Loan',
                'found'            => 'Found in collection',
                'unknown'          => 'Unknown',
            ]],
            'provenance_current_status' => ['Provenance Current Status', [
                'owned'     => 'Owned',
                'on_loan'   => 'On loan',
                'deposited' => 'Deposited',
                'disputed'  => 'Disputed',
                'unknown'   => 'Unknown',
            ]],
            'provenance_custody_type' => ['Custody Type', [
                'permanent' => 'Permanent',
                'temporary' => 'Temporary',
                'loan'      => 'Loan',
                'deposit'   => 'Deposit',
            ]],
            'provenance_research_status' => ['Provenance Research Status', [
                'not_started' => 'Not started',
                'in_progress' => 'In progress',
                'complete'    => 'Complete',
                'blocked'     => 'Blocked',
            ]],
            'provenance_cultural_property_status' => ['Cultural Property Status', [
                'none'       => 'None — no concern',
                'flagged'    => 'Flagged for review',
                'claimed'    => 'Subject to a restitution claim',
                'restituted' => 'Restituted / repatriated',
                'cleared'    => 'Reviewed — cleared',
            ]],
            'provenance_currency' => ['Provenance Currency', [
                'ZAR' => 'ZAR — South African Rand',
                'USD' => 'USD — US Dollar',
                'GBP' => 'GBP — British Pound',
                'EUR' => 'EUR — Euro',
            ]],
            'provenance_certainty' => ['Provenance Certainty', [
                'certain'   => 'Certain - Documented evidence',
                'probable'  => 'Probable - Strong circumstantial evidence',
                'possible'  => 'Possible - Some supporting evidence',
                'uncertain' => 'Uncertain - Limited evidence',
                'unknown'   => 'Unknown - No evidence',
            ]],
            'provenance_owner_type' => ['Provenance Owner Type', [
                'unknown'       => 'Unknown',
                'person'        => 'Person',
                'family'        => 'Family',
                'organization'  => 'Organization',
                'institution'   => 'Institution',
                'dealer'        => 'Dealer',
                'auction_house' => 'Auction House',
                'museum'        => 'Museum',
                'corporate'     => 'Corporate',
                'government'    => 'Government',
                'religious'     => 'Religious',
                'artist'        => 'Artist',
            ]],
            'provenance_transfer_type' => ['Provenance Transfer Type', [
                'unknown'        => 'Unknown',
                'sale'           => 'Sale',
                'purchase'       => 'Purchase',
                'auction'        => 'Auction Sale',
                'gift'           => 'Gift',
                'donation'       => 'Donation',
                'bequest'        => 'Bequest',
                'inheritance'    => 'Inheritance',
                'descent'        => 'By Descent',
                'transfer'       => 'Transfer',
                'exchange'       => 'Exchange',
                'loan_out'       => 'Loan Out',
                'loan_return'    => 'Loan Return',
                'deposit'        => 'Deposit',
                'withdrawal'     => 'Withdrawal',
                'creation'       => 'Creation',
                'commission'     => 'Commission',
                'discovery'      => 'Discovery',
                'found'          => 'Found/Discovery',
                'excavation'     => 'Excavation',
                'theft'          => 'Theft',
                'recovery'       => 'Recovery',
                'confiscation'   => 'Confiscation',
                'restitution'    => 'Restitution',
                'repatriation'   => 'Repatriation',
                'accessioning'   => 'Accessioning',
                'deaccessioning' => 'Deaccessioning',
                'other'          => 'Other',
            ]],
            'provenance_document_type' => ['Provenance Document Type', [
                'deed_of_gift'       => 'Deed of Gift',
                'bill_of_sale'       => 'Bill of Sale',
                'invoice'            => 'Invoice',
                'receipt'            => 'Receipt',
                'auction_catalog'    => 'Auction Catalogue',
                'exhibition_catalog' => 'Exhibition Catalogue',
                'inventory'          => 'Inventory',
                'insurance_record'   => 'Insurance Record',
                'photograph'         => 'Photograph',
                'correspondence'     => 'Correspondence',
                'certificate'        => 'Certificate',
                'customs_document'   => 'Customs Document',
                'export_license'     => 'Export Licence',
                'import_permit'      => 'Import Permit',
                'appraisal'          => 'Appraisal',
                'condition_report'   => 'Condition Report',
                'newspaper_clipping' => 'Newspaper Clipping',
                'publication'        => 'Publication',
                'oral_history'       => 'Oral History',
                'affidavit'          => 'Affidavit',
                'legal_document'     => 'Legal Document',
                'other'              => 'Other',
            ]],
        ];

        // Per-taxonomy sentinel: a taxonomy that already exists (e.g. seeded by
        // the retired provenance stack, or curated by a site admin) is admin-
        // owned — never re-merge the shipped terms into it. Only missing
        // taxonomies are seeded.
        $existing = DB::table('ahg_dropdown')
            ->whereIn('taxonomy', array_keys($vocabularies))
            ->distinct()
            ->pluck('taxonomy')
            ->all();

        foreach ($vocabularies as $taxonomy => [$taxonomyLabel, $terms]) {
            if (in_array($taxonomy, $existing, true)) {
                continue;
            }
            $sort = 10;
            $first = true;
            foreach ($terms as $code => $label) {
                $rows[] = [
                    'taxonomy'         => $taxonomy,
                    'taxonomy_label'   => $taxonomyLabel,
                    'taxonomy_section' => 'provenance',
                    'code'             => $code,
                    'label'            => $label,
                    'sort_order'       => $sort,
                    'is_default'       => $first ? 1 : 0,
                    'is_active'        => 1,
                ];
                $sort += 10;
                $first = false;
            }
        }

        if ($rows !== []) {
            DB::table('ahg_dropdown')->insertOrIgnore($rows);
        }
    }
}
