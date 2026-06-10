<?php

/**
 * RicToCrmMapper - Static crosswalk table between RiC-O and CIDOC-CRM 7.1.3.
 *
 * RiC-O ("Records in Contexts") is ICA's records-centric ontology;
 * CIDOC-CRM ("Conceptual Reference Model") is ICOM's event-centric
 * museum/heritage ontology. They overlap on actors, places, dates and
 * activities but use different IRIs for the same idea. This class
 * holds the canonical RiC -> CRM map used by CrmSerializer (per-IO
 * export) and any future Fuseki rule pack.
 *
 * Spec references:
 *   - RiC-O 1.0:        https://www.ica.org/standards/RiC/RiC-O_v1.0
 *   - CIDOC-CRM 7.1.3:  https://www.cidoc-crm.org/sites/default/files/cidoc_crm_v7.1.3.pdf
 *
 * The mapper is deliberately read-only and side-effect-free. Tables
 * are PHP constants so the test suite can assert every row by name.
 *
 * Phase 1 of issue #659 (CIDOC-CRM v7 - class/property completeness +
 * RiC <-> CRM bridge serialiser).
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

namespace AhgRic\Crm;

class RicToCrmMapper
{
    public const NS_RIC = 'https://www.ica.org/standards/RiC/ontology#';
    public const NS_CRM = 'http://www.cidoc-crm.org/cidoc-crm/';
    public const NS_RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    public const NS_RDFS = 'http://www.w3.org/2000/01/rdf-schema#';
    public const NS_XSD = 'http://www.w3.org/2001/XMLSchema#';

    /**
     * RiC-O class -> CIDOC-CRM class. The right-hand side is the most
     * specific CRM class that still subsumes every instance of the
     * left-hand side. Where RiC is broader than CRM (e.g. rico:Agent
     * covers persons, groups and corporate bodies) the AGENT_SUBCLASS
     * table below carries the finer-grained mapping by sub-type.
     *
     * Coverage rationale (15 classes - the bulk of every Heratio export):
     *   1.  Record / Information Object - the central CHO.
     *   2.  RecordSet / Curated Holding - fonds/series wrappers.
     *   3.  RecordPart - itemised components inside an IO.
     *   4.  Instantiation - manifestation/digital surrogate.
     *   5.  Activity - any event (creation, modification, custody).
     *   6.  CarrierType - physical carrier (paper, parchment, file).
     *   7.  Agent - generic provenance actor.
     *   8.  Person / Group / CorporateBody - actor sub-types.
     *   9.  Place - spatial extent.
     *  10.  Date - time span.
     *  11.  Mandate / Rule - normative rule governing the activity.
     *  12.  Occupation - role/profession held over a time span.
     */
    public const CLASS_MAP = [
        // RiC class                              CRM class                        Notes
        'rico:Record'                         => 'crm:E73_Information_Object',
        // Museum object - a physical, human-made artefact catalogued in
        // ahg-museum. Distinct from an archival Record: a museum Item is
        // the made thing itself, so CRM types it as E22 rather than the
        // information-carrier E73. CrmSerializer selects this class when a
        // caller passes the museum record-class override.
        'rico:Item'                           => 'crm:E22_Human-Made_Object',
        'rico:RecordSet'                      => 'crm:E78_Curated_Holding',
        'rico:RecordPart'                     => 'crm:E73_Information_Object',
        'rico:Instantiation'                  => 'crm:E84_Information_Carrier',
        'rico:Activity'                       => 'crm:E7_Activity',
        'rico:CarrierType'                    => 'crm:E55_Type',
        'rico:Agent'                          => 'crm:E39_Actor',
        'rico:Person'                         => 'crm:E21_Person',
        'rico:Family'                         => 'crm:E74_Group',
        'rico:Group'                          => 'crm:E74_Group',
        'rico:CorporateBody'                  => 'crm:E40_Legal_Body',
        'rico:Place'                          => 'crm:E53_Place',
        'rico:Date'                           => 'crm:E52_Time-Span',
        'rico:Mandate'                        => 'crm:E30_Right',
        'rico:Rule'                           => 'crm:E30_Right',
        'rico:Occupation'                     => 'crm:E55_Type',
    ];

    /**
     * Heratio actor.entity_type_id -> CRM class for fine-grained
     * sub-typing when serialising creators. The keys mirror the
     * fixed actor type ids in the AtoM-inherited schema:
     *   131 = Person, 132 = Corporate Body, 133 = Family.
     */
    public const AGENT_SUBCLASS = [
        131 => 'crm:E21_Person',
        132 => 'crm:E40_Legal_Body',
        133 => 'crm:E74_Group',
    ];

    /**
     * RiC-O property -> CIDOC-CRM property. We keep the map narrow on
     * purpose: only predicates we can mechanically derive from the
     * relational schema today end up here. Cross-domain inferences
     * (e.g. rico:hasOrHadFunction -> P14/P107 chains) are documented
     * in the reference doc as Phase 2 work.
     *
     * Direction note: the value is the OUT-going predicate from the
     * subject of the RiC triple. Inverse predicates (P14i_performed,
     * P4i_is_time-span_of) are emitted only when CRM consumers expect
     * round-trip closure; the per-IO serializer uses the forward form.
     */
    public const PROPERTY_MAP = [
        // RiC predicate                         CRM predicate                  Comment
        'rico:hasCreator'                     => 'crm:P14_carried_out_by',
        'rico:isAssociatedWithDate'           => 'crm:P4_has_time-span',
        'rico:isAssociatedWithPlace'          => 'crm:P7_took_place_at',
        'rico:hasOrHadHolder'                 => 'crm:P52_has_current_owner',
        'rico:isOrWasHeldBy'                  => 'crm:P50_has_current_keeper',
        'rico:hasSubject'                     => 'crm:P129_is_about',
        'rico:hasLanguage'                    => 'crm:P72_has_language',
        'rico:hasOrHadIdentifier'             => 'crm:P1_is_identified_by',
        'rico:title'                          => 'crm:P102_has_title',
        'rico:hasBeginningDate'               => 'crm:P82a_begin_of_the_begin',
        'rico:hasEndDate'                     => 'crm:P82b_end_of_the_end',
        'rico:descriptiveNote'                => 'crm:P3_has_note',
        'rico:hasOrHadPart'                   => 'crm:P46_is_composed_of',
        'rico:isPartOf'                       => 'crm:P46i_forms_part_of',
        'rico:hasCarrierType'                 => 'crm:P2_has_type',
        'rico:hasInstantiation'               => 'crm:P128_carries',
        'rico:isInstantiationOf'              => 'crm:P128i_is_carried_by',
        'rico:hasOrHadAgent'                  => 'crm:P11_had_participant',
        'rico:hasMandate'                     => 'crm:P104_is_subject_to',
        'rico:performs'                       => 'crm:P14i_performed',
    ];

    /**
     * RiC concepts that have NO direct CIDOC-CRM equivalent. Recorded
     * here so the reference doc + the test suite both stay honest
     * about what the bridge silently drops. Each line should also be
     * mentioned in docs/reference/cidoc-crm-phase-1-bridge.md so
     * downstream operators know what to expect.
     */
    public const RIC_ONLY = [
        'rico:RecordResource'                 => 'RiC abstract super-class; CRM has no abstract record concept.',
        'rico:hasOrHadSubject'                => 'Subsumed by crm:P129_is_about; kept distinct in RiC for archival vs museum subjecting.',
        'rico:isOrWasRegulatedBy'             => 'Closest CRM: P104_is_subject_to; weaker than RiC regulation semantics.',
        'rico:hasProvenance'                  => 'CRM models provenance via E7_Activity chains, not a single predicate.',
        'rico:hasOrHadConstitutiveActivity'   => 'CRM uses P108_has_produced; constitutive vs productive is RiC-specific.',
    ];

    /**
     * CRM concepts that RiC does not currently surface. Lets the
     * reference doc explain what the bridge can NOT lossless
     * round-trip back to RiC after a CRM consumer enriches the graph.
     */
    public const CRM_ONLY = [
        'crm:E12_Production'                  => 'Specialised activity sub-class; RiC keeps creation under rico:Activity.',
        'crm:E83_Type_Creation'               => 'Used for museum typology events; out of scope for archival records.',
        'crm:P108_has_produced'               => 'Use rico:hasOrHadConstitutiveActivity (lossy) on import.',
        // NB: now also reachable as a mapped class via rico:Item (see
        // CLASS_MAP) for ahg-museum CIDOC-CRM export. The note below
        // applies only to CRM consumers that mint E22 typology/production
        // events RiC does not surface.
        'crm:E22_Human-Made_Object'           => 'Three-dimensional objects; museum export maps rico:Item here, but CRM E12/E83 production events around them stay RiC-only.',
    ];

    /**
     * Look up the CRM class for a given RiC class identifier. Returns
     * null when no mapping exists - callers may then check RIC_ONLY
     * to log the drop or to fall back to a parent class.
     */
    public static function classFor(string $ricClass): ?string
    {
        return self::CLASS_MAP[$ricClass] ?? null;
    }

    /**
     * Look up the CRM predicate for a given RiC predicate. Same
     * null-on-miss contract as classFor().
     */
    public static function propertyFor(string $ricProperty): ?string
    {
        return self::PROPERTY_MAP[$ricProperty] ?? null;
    }

    /**
     * Pick the most-specific CRM Actor class for a Heratio actor row,
     * using the entity_type_id when available, otherwise the generic
     * crm:E39_Actor. Used by CrmSerializer when emitting creators.
     */
    public static function agentClassFor(?int $entityTypeId): string
    {
        if ($entityTypeId !== null && isset(self::AGENT_SUBCLASS[$entityTypeId])) {
            return self::AGENT_SUBCLASS[$entityTypeId];
        }
        return self::CLASS_MAP['rico:Agent'];
    }

    /**
     * Expand a 'crm:E73_Information_Object' style CURIE into an
     * absolute IRI usable as an rdf:type object. Returns the input
     * unchanged when it is not a CRM CURIE (lets callers pass
     * already-absolute IRIs through).
     */
    public static function expand(string $curie): string
    {
        if (str_starts_with($curie, 'crm:')) {
            return self::NS_CRM . substr($curie, 4);
        }
        if (str_starts_with($curie, 'rico:')) {
            return self::NS_RIC . substr($curie, 5);
        }
        return $curie;
    }

    /**
     * Total number of class + property mappings. Documented + asserted
     * so accidental row deletions surface in the unit test.
     */
    public static function mappingCount(): int
    {
        return count(self::CLASS_MAP) + count(self::PROPERTY_MAP);
    }
}
