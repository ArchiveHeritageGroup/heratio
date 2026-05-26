<?php

/**
 * RicToCrmMapperTest - Row-by-row assertions on the RiC <-> CIDOC-CRM
 * crosswalk. The mapper is the single source of truth for the bridge,
 * so every documented mapping line gets a named test - silent rename
 * or accidental deletion of a row fails CI.
 *
 * Phase 1 of issue #659.
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

namespace Tests\Unit;

use AhgRic\Crm\RicToCrmMapper;
use Tests\TestCase;

class RicToCrmMapperTest extends TestCase
{
    /**
     * Every class-level mapping line is asserted by name. New rows
     * need a matching entry here - keeps the docs/reference doc and
     * the source of truth in lockstep.
     *
     * @dataProvider classMappingProvider
     */
    public function test_class_mapping(string $ricClass, string $expectedCrm): void
    {
        $this->assertSame(
            $expectedCrm,
            RicToCrmMapper::classFor($ricClass),
            "RiC class {$ricClass} must map to {$expectedCrm}"
        );
    }

    public static function classMappingProvider(): array
    {
        return [
            'Record -> Information Object'         => ['rico:Record',         'crm:E73_Information_Object'],
            'RecordSet -> Curated Holding'         => ['rico:RecordSet',      'crm:E78_Curated_Holding'],
            'RecordPart -> Information Object'     => ['rico:RecordPart',     'crm:E73_Information_Object'],
            'Instantiation -> Information Carrier' => ['rico:Instantiation',  'crm:E84_Information_Carrier'],
            'Activity -> Activity'                 => ['rico:Activity',       'crm:E7_Activity'],
            'CarrierType -> Type'                  => ['rico:CarrierType',    'crm:E55_Type'],
            'Agent -> Actor'                       => ['rico:Agent',          'crm:E39_Actor'],
            'Person -> Person'                     => ['rico:Person',         'crm:E21_Person'],
            'Family -> Group'                      => ['rico:Family',         'crm:E74_Group'],
            'Group -> Group'                       => ['rico:Group',          'crm:E74_Group'],
            'CorporateBody -> Legal Body'          => ['rico:CorporateBody',  'crm:E40_Legal_Body'],
            'Place -> Place'                       => ['rico:Place',          'crm:E53_Place'],
            'Date -> Time-Span'                    => ['rico:Date',           'crm:E52_Time-Span'],
            'Mandate -> Right'                     => ['rico:Mandate',        'crm:E30_Right'],
            'Rule -> Right'                        => ['rico:Rule',           'crm:E30_Right'],
            'Occupation -> Type'                   => ['rico:Occupation',     'crm:E55_Type'],
        ];
    }

    /**
     * Every property-level mapping line is asserted by name.
     *
     * @dataProvider propertyMappingProvider
     */
    public function test_property_mapping(string $ricProp, string $expectedCrm): void
    {
        $this->assertSame(
            $expectedCrm,
            RicToCrmMapper::propertyFor($ricProp),
            "RiC predicate {$ricProp} must map to {$expectedCrm}"
        );
    }

    public static function propertyMappingProvider(): array
    {
        return [
            'hasCreator -> P14_carried_out_by'           => ['rico:hasCreator',           'crm:P14_carried_out_by'],
            'isAssociatedWithDate -> P4_has_time-span'   => ['rico:isAssociatedWithDate', 'crm:P4_has_time-span'],
            'isAssociatedWithPlace -> P7_took_place_at'  => ['rico:isAssociatedWithPlace', 'crm:P7_took_place_at'],
            'hasOrHadHolder -> P52_has_current_owner'    => ['rico:hasOrHadHolder',       'crm:P52_has_current_owner'],
            'isOrWasHeldBy -> P50_has_current_keeper'    => ['rico:isOrWasHeldBy',        'crm:P50_has_current_keeper'],
            'hasSubject -> P129_is_about'                => ['rico:hasSubject',           'crm:P129_is_about'],
            'hasLanguage -> P72_has_language'            => ['rico:hasLanguage',          'crm:P72_has_language'],
            'hasOrHadIdentifier -> P1_is_identified_by'  => ['rico:hasOrHadIdentifier',   'crm:P1_is_identified_by'],
            'title -> P102_has_title'                    => ['rico:title',                'crm:P102_has_title'],
            'hasBeginningDate -> P82a'                   => ['rico:hasBeginningDate',     'crm:P82a_begin_of_the_begin'],
            'hasEndDate -> P82b'                         => ['rico:hasEndDate',           'crm:P82b_end_of_the_end'],
            'descriptiveNote -> P3_has_note'             => ['rico:descriptiveNote',      'crm:P3_has_note'],
            'hasOrHadPart -> P46_is_composed_of'         => ['rico:hasOrHadPart',         'crm:P46_is_composed_of'],
            'isPartOf -> P46i_forms_part_of'             => ['rico:isPartOf',             'crm:P46i_forms_part_of'],
            'hasCarrierType -> P2_has_type'              => ['rico:hasCarrierType',       'crm:P2_has_type'],
            'hasInstantiation -> P128_carries'           => ['rico:hasInstantiation',     'crm:P128_carries'],
            'isInstantiationOf -> P128i_is_carried_by'   => ['rico:isInstantiationOf',    'crm:P128i_is_carried_by'],
            'hasOrHadAgent -> P11_had_participant'       => ['rico:hasOrHadAgent',        'crm:P11_had_participant'],
            'hasMandate -> P104_is_subject_to'           => ['rico:hasMandate',           'crm:P104_is_subject_to'],
            'performs -> P14i_performed'                 => ['rico:performs',             'crm:P14i_performed'],
        ];
    }

    public function test_class_for_unknown_returns_null(): void
    {
        $this->assertNull(RicToCrmMapper::classFor('rico:DoesNotExist'));
    }

    public function test_property_for_unknown_returns_null(): void
    {
        $this->assertNull(RicToCrmMapper::propertyFor('rico:thisIsNotAPredicate'));
    }

    public function test_agent_subclass_for_known_actor_types(): void
    {
        $this->assertSame('crm:E21_Person', RicToCrmMapper::agentClassFor(131));
        $this->assertSame('crm:E40_Legal_Body', RicToCrmMapper::agentClassFor(132));
        $this->assertSame('crm:E74_Group', RicToCrmMapper::agentClassFor(133));
    }

    public function test_agent_subclass_for_null_falls_back_to_actor(): void
    {
        $this->assertSame('crm:E39_Actor', RicToCrmMapper::agentClassFor(null));
        $this->assertSame('crm:E39_Actor', RicToCrmMapper::agentClassFor(999999));
    }

    public function test_expand_resolves_crm_curie(): void
    {
        $this->assertSame(
            'http://www.cidoc-crm.org/cidoc-crm/E73_Information_Object',
            RicToCrmMapper::expand('crm:E73_Information_Object')
        );
    }

    public function test_expand_resolves_rico_curie(): void
    {
        $this->assertSame(
            'https://www.ica.org/standards/RiC/ontology#Record',
            RicToCrmMapper::expand('rico:Record')
        );
    }

    public function test_expand_leaves_absolute_iri_unchanged(): void
    {
        $iri = 'http://example.org/foo';
        $this->assertSame($iri, RicToCrmMapper::expand($iri));
    }

    public function test_namespace_constants(): void
    {
        $this->assertSame('https://www.ica.org/standards/RiC/ontology#', RicToCrmMapper::NS_RIC);
        $this->assertSame('http://www.cidoc-crm.org/cidoc-crm/', RicToCrmMapper::NS_CRM);
    }

    public function test_mapping_count_matches_expectation(): void
    {
        // Sanity: 16 classes + 20 properties = 36. Update this number
        // intentionally whenever a row is added/removed (and document
        // the move in docs/reference/cidoc-crm-phase-1-bridge.md).
        $this->assertSame(36, RicToCrmMapper::mappingCount());
    }

    public function test_documented_gaps_are_non_empty(): void
    {
        // The bridge silently drops some RiC-only / CRM-only concepts;
        // the inline tables document them. Catch accidental wipes.
        $this->assertNotEmpty(RicToCrmMapper::RIC_ONLY);
        $this->assertNotEmpty(RicToCrmMapper::CRM_ONLY);
    }
}
