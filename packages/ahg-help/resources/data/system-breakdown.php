<?php

/**
 * system-breakdown.php - Data model for the interactive System Breakdown tree
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
 *
 * ---------------------------------------------------------------------------
 * MAINTAINER NOTES
 * ---------------------------------------------------------------------------
 * This is the single source of truth for the help-system "System Breakdown"
 * (route help.system-breakdown). It is data-driven on purpose - the diagram is
 * NOT a hand-drawn picture. To change the tree you edit this array; nothing in
 * the controller, view, or JS needs to change.
 *
 * This is a FOUR-LEVEL FUNCTIONAL CAPABILITY tree, deliberately separate from
 * (and deeper than) the three-level WORKFLOW System Map (system-map.php). It
 * answers "what can Heratio do, broken down by record type" rather than "how
 * does a record flow through the platform".
 *
 *   L1  root      "Heratio"               (one node - the whole product)
 *   L2  entity    the GLAM record types   (Archival descriptions, Actors, ...)
 *   L3  aspect    a functional area on     (Description, Provenance, Condition,
 *                 that entity              PII / Privacy, AI, Rights, Relations,
 *                                          Digital objects, Search, Preservation)
 *   L4  feature   a concrete tool / screen (Provenance chain, AI condition scan,
 *                 under that aspect        Field-level redaction, HTR, ...)
 *
 * The diagram therefore drills FOUR levels:
 *   Heratio -> entity (L2) -> aspect (L3) -> feature (L4)
 * e.g. Heratio -> Archival descriptions -> Provenance ->
 *        [Provenance chain, Custody history, C2PA content credentials].
 *
 * NODE FIELDS (every level uses the same shape)
 *   id       (string, required)  unique slug-id, used as the Cytoscape node id.
 *                                Keep ids hierarchical: entity.aspect.feature
 *   label    (string, required)  text shown on the node
 *   sub      (string, optional)  one-line subtitle / standards line
 *   color    (string, optional)  L2 entities carry a colour; descendants inherit
 *   help     (string, optional)  help_article slug -> deep-links to
 *                                /help/article/{slug}. Omit if no article yet.
 *                                The service nulls any slug the viewer cannot
 *                                open, so an absent/unknown slug degrades to
 *                                plain text - never a dead link.
 *   children (array, optional)   the next level down (same field shape). An
 *                                entity's children are aspects; an aspect's
 *                                children are features.
 *
 * Help-article slugs below are drawn from the set already resolved against the
 * live help_article table for the System Map. The service re-checks each slug
 * at render time and nulls any that the current viewer cannot open, so an
 * absent / admin-only slug degrades to plain text rather than a 404.
 *
 * Only include an aspect on an entity where it genuinely applies, and keep
 * each node to 2-6 children - this is a clean capability tree, not a dump.
 */

return [
    // -----------------------------------------------------------------
    // L1 root - the whole product. One node; every L2 entity hangs off it.
    // -----------------------------------------------------------------
    'root' => [
        'id'    => 'heratio',
        'label' => 'Heratio',
        'sub'   => 'GLAM platform - capability breakdown',
        'color' => '#212529',
    ],

    // -----------------------------------------------------------------
    // L2 - the GLAM entities / core record types. Each carries its own
    // colour, inherited by its aspects (L3) and features (L4).
    // -----------------------------------------------------------------
    'entities' => [

        // =============================================================
        // Archival descriptions (ISAD(G) / RiC) - the richest entity.
        // =============================================================
        [
            'id'    => 'io',
            'label' => 'Archival descriptions',
            'sub'   => 'ISAD(G) / RiC archival units',
            'color' => '#1b998b',
            'help'  => 'information-object-manage-user-guide',
            'children' => [
                [
                    'id' => 'io.description', 'label' => 'Description', 'sub' => 'ISAD(G) / RiC fields', 'help' => 'information-object-manage-user-guide',
                    'children' => [
                        ['id' => 'io.description.isad',     'label' => 'ISAD(G) fields', 'sub' => 'Identity / context / content', 'help' => 'information-object-manage-user-guide'],
                        ['id' => 'io.description.levels',   'label' => 'Levels',         'sub' => 'Fonds -> item hierarchy'],
                        ['id' => 'io.description.ric',      'label' => 'RiC entities',   'sub' => 'Records in Contexts', 'help' => 'ric-user-guide'],
                        ['id' => 'io.description.custom',   'label' => 'Custom fields',  'sub' => 'Extra metadata'],
                    ],
                ],
                [
                    'id' => 'io.provenance', 'label' => 'Provenance', 'sub' => 'Custody and origin', 'help' => 'provenance-user-guide',
                    'children' => [
                        ['id' => 'io.provenance.chain',  'label' => 'Provenance chain',     'sub' => 'Chain of ownership', 'help' => 'provenance-user-guide'],
                        ['id' => 'io.provenance.custody','label' => 'Custody history',       'sub' => 'Holdings over time'],
                        ['id' => 'io.provenance.c2pa',   'label' => 'C2PA credentials',      'sub' => 'Content provenance assertions'],
                    ],
                ],
                [
                    'id' => 'io.condition', 'label' => 'Condition', 'sub' => 'Condition reporting', 'help' => 'condition-reports',
                    'children' => [
                        ['id' => 'io.condition.reports', 'label' => 'Condition reports', 'sub' => 'Assessment + grade', 'help' => 'condition-reports'],
                        ['id' => 'io.condition.photos',  'label' => 'Condition photos',  'sub' => 'Visual record'],
                        ['id' => 'io.condition.aiscan',  'label' => 'AI condition scan', 'sub' => 'Vision model assessment', 'help' => 'ai-condition-user-guide'],
                    ],
                ],
                [
                    'id' => 'io.privacy', 'label' => 'PII / Privacy', 'sub' => 'Privacy compliance', 'help' => 'odrl-rights-policies',
                    'children' => [
                        ['id' => 'io.privacy.redaction', 'label' => 'Field-level redaction', 'sub' => 'Hide sensitive values'],
                        ['id' => 'io.privacy.dpia',      'label' => 'DPIA / ROPA',           'sub' => 'Risk + processing record'],
                        ['id' => 'io.privacy.compliance','label' => 'Privacy compliance',    'sub' => 'Pluggable per-market'],
                    ],
                ],
                [
                    'id' => 'io.ai', 'label' => 'AI', 'sub' => 'AI enrichment', 'help' => 'ner-user-guide',
                    'children' => [
                        ['id' => 'io.ai.htr',        'label' => 'HTR / OCR',       'sub' => 'Text from images', 'help' => 'htr-user-guide'],
                        ['id' => 'io.ai.ner',        'label' => 'NER',             'sub' => 'Entity extraction', 'help' => 'ner-user-guide'],
                        ['id' => 'io.ai.summarize',  'label' => 'Summarize',       'sub' => 'Scope-note draft'],
                        ['id' => 'io.ai.provenance', 'label' => 'AI provenance',   'sub' => 'Inference recorded', 'help' => 'ai-inference-provenance-user-guide'],
                    ],
                ],
                [
                    'id' => 'io.rights', 'label' => 'Rights / ODRL', 'sub' => 'Access and reuse policy', 'help' => 'odrl-rights-policies',
                    'children' => [
                        ['id' => 'io.rights.odrl',     'label' => 'ODRL policies', 'sub' => 'use / reproduce rules', 'help' => 'odrl-rights-policies'],
                        ['id' => 'io.rights.embargo',  'label' => 'Embargo',       'sub' => 'Time-limited access'],
                        ['id' => 'io.rights.icip',     'label' => 'ICIP',          'sub' => 'Indigenous cultural rights'],
                    ],
                ],
                [
                    'id' => 'io.relations', 'label' => 'Relations', 'sub' => 'Links to other entities', 'help' => 'knowledge-graph-user-guide',
                    'children' => [
                        ['id' => 'io.relations.actors', 'label' => 'Linked actors', 'sub' => 'Creators / subjects', 'help' => 'authority-resolution-user-guide'],
                        ['id' => 'io.relations.terms',  'label' => 'Subjects / places', 'sub' => 'Indexed terms'],
                        ['id' => 'io.relations.ric',    'label' => 'RiC relations', 'sub' => 'rico:* predicates', 'help' => 'knowledge-graph-user-guide'],
                    ],
                ],
                [
                    'id' => 'io.digital', 'label' => 'Digital objects', 'sub' => 'Attached media', 'help' => 'dam-module-user-guide',
                    'children' => [
                        ['id' => 'io.digital.master',  'label' => 'Master + derivatives', 'sub' => 'Original + surrogates'],
                        ['id' => 'io.digital.iiif',    'label' => 'IIIF deep-zoom',        'sub' => 'Mirador / Cantaloupe', 'help' => 'mirador-user-guide'],
                        ['id' => 'io.digital.3d',      'label' => '3D / splat',            'sub' => 'glTF mesh + Gaussian splat', 'help' => '3d-model-viewer-user-guide'],
                    ],
                ],
                [
                    'id' => 'io.search', 'label' => 'Search', 'sub' => 'Find and refine', 'help' => 'advanced-search-user-guide',
                    'children' => [
                        ['id' => 'io.search.es',       'label' => 'Elasticsearch', 'sub' => 'heratio_informationobject', 'help' => 'elasticsearch-configuration'],
                        ['id' => 'io.search.advanced', 'label' => 'Advanced search', 'sub' => 'Facets + ancestor subtree', 'help' => 'advanced-search-user-guide'],
                        ['id' => 'io.search.semantic', 'label' => 'Semantic',      'sub' => 'Vector / KM RAG', 'help' => 'semantic-search-user-guide'],
                    ],
                ],
                [
                    'id' => 'io.preservation', 'label' => 'Preservation', 'sub' => 'OAIS / PREMIS / fixity', 'help' => 'preservation-user-guide',
                    'children' => [
                        ['id' => 'io.preservation.oais',   'label' => 'OAIS packages', 'sub' => 'SIP / AIP / DIP', 'help' => 'preservation-user-guide'],
                        ['id' => 'io.preservation.premis', 'label' => 'PREMIS events', 'sub' => 'Preservation actions', 'help' => 'preservation-user-guide'],
                        ['id' => 'io.preservation.fixity', 'label' => 'Fixity',        'sub' => 'Checksums + audits'],
                    ],
                ],
            ],
        ],

        // =============================================================
        // Actors / authorities (ISAAR).
        // =============================================================
        [
            'id'    => 'actor',
            'label' => 'Actors / authorities',
            'sub'   => 'ISAAR persons, families, bodies',
            'color' => '#2a6f97',
            'help'  => 'authority-resolution-user-guide',
            'children' => [
                [
                    'id' => 'actor.description', 'label' => 'Description', 'sub' => 'ISAAR(CPF) fields', 'help' => 'authority-resolution-user-guide',
                    'children' => [
                        ['id' => 'actor.description.identity', 'label' => 'Identity',  'sub' => 'Authorized form of name'],
                        ['id' => 'actor.description.history',  'label' => 'History',    'sub' => 'Biography / administrative'],
                        ['id' => 'actor.description.mandate',  'label' => 'Mandates',   'sub' => 'Functions / legal basis'],
                    ],
                ],
                [
                    'id' => 'actor.authority', 'label' => 'Authority control', 'sub' => 'Resolve and de-duplicate', 'help' => 'authority-resolution-user-guide',
                    'children' => [
                        ['id' => 'actor.authority.match',   'label' => 'Match / resolve', 'sub' => 'External authorities', 'help' => 'authority-resolution-user-guide'],
                        ['id' => 'actor.authority.merge',   'label' => 'Merge duplicates','sub' => 'Consolidate records', 'help' => 'duplicate-detection-user-guide'],
                    ],
                ],
                [
                    'id' => 'actor.relations', 'label' => 'Relations', 'sub' => 'Links and graph', 'help' => 'knowledge-graph-user-guide',
                    'children' => [
                        ['id' => 'actor.relations.records', 'label' => 'Linked records', 'sub' => 'Created / referenced'],
                        ['id' => 'actor.relations.actors',  'label' => 'Related actors', 'sub' => 'Hierarchical / associative'],
                        ['id' => 'actor.relations.graph',   'label' => 'Knowledge graph','sub' => 'rico:* edges', 'help' => 'knowledge-graph-user-guide'],
                    ],
                ],
                [
                    'id' => 'actor.privacy', 'label' => 'PII / Privacy', 'sub' => 'Living-person data', 'help' => 'odrl-rights-policies',
                    'children' => [
                        ['id' => 'actor.privacy.redaction', 'label' => 'Field-level redaction', 'sub' => 'Hide personal data'],
                        ['id' => 'actor.privacy.compliance','label' => 'Privacy compliance',    'sub' => 'Pluggable per-market'],
                    ],
                ],
                [
                    'id' => 'actor.search', 'label' => 'Search', 'sub' => 'Find authorities', 'help' => 'advanced-search-user-guide',
                    'children' => [
                        ['id' => 'actor.search.es',       'label' => 'Elasticsearch',  'sub' => 'heratio_actor'],
                        ['id' => 'actor.search.advanced', 'label' => 'Advanced search', 'sub' => 'Facets + filters', 'help' => 'advanced-search-user-guide'],
                    ],
                ],
            ],
        ],

        // =============================================================
        // Repositories / institutions (ISDIAH).
        // =============================================================
        [
            'id'    => 'repo',
            'label' => 'Repositories',
            'sub'   => 'ISDIAH holding institutions',
            'color' => '#6c757d',
            'children' => [
                [
                    'id' => 'repo.description', 'label' => 'Description', 'sub' => 'ISDIAH fields',
                    'children' => [
                        ['id' => 'repo.description.identity', 'label' => 'Identity',  'sub' => 'Institution name / type'],
                        ['id' => 'repo.description.contact',  'label' => 'Contact',    'sub' => 'Address / access'],
                        ['id' => 'repo.description.services', 'label' => 'Services',   'sub' => 'Access + reproduction'],
                    ],
                ],
                [
                    'id' => 'repo.relations', 'label' => 'Relations', 'sub' => 'Holdings + staff',
                    'children' => [
                        ['id' => 'repo.relations.holdings', 'label' => 'Held records', 'sub' => 'Repository holdings'],
                        ['id' => 'repo.relations.actors',   'label' => 'Linked actors','sub' => 'Custodians / contacts'],
                    ],
                ],
                [
                    'id' => 'repo.rights', 'label' => 'Rights / ODRL', 'sub' => 'Repository policy', 'help' => 'odrl-rights-policies',
                    'children' => [
                        ['id' => 'repo.rights.odrl',  'label' => 'Access policy', 'sub' => 'Default reuse rules', 'help' => 'odrl-rights-policies'],
                    ],
                ],
                [
                    'id' => 'repo.search', 'label' => 'Search', 'sub' => 'Find institutions', 'help' => 'advanced-search-user-guide',
                    'children' => [
                        ['id' => 'repo.search.es',       'label' => 'Elasticsearch', 'sub' => 'heratio_repository'],
                        ['id' => 'repo.search.advanced', 'label' => 'Advanced search', 'sub' => 'Facets + filters', 'help' => 'advanced-search-user-guide'],
                    ],
                ],
            ],
        ],

        // =============================================================
        // Accessions.
        // =============================================================
        [
            'id'    => 'accession',
            'label' => 'Accessions',
            'sub'   => 'Acquisition records',
            'color' => '#264653',
            'help'  => 'accession-v2-user-guide',
            'children' => [
                [
                    'id' => 'accession.description', 'label' => 'Description', 'sub' => 'Accession Management V2', 'help' => 'accession-v2-user-guide',
                    'children' => [
                        ['id' => 'accession.description.id',    'label' => 'Accession number', 'sub' => 'Auto-generated identifier'],
                        ['id' => 'accession.description.scope', 'label' => 'Scope & content',  'sub' => 'What was accessioned'],
                        ['id' => 'accession.description.create','label' => 'Spawn record',     'sub' => 'Create archival description', 'help' => 'information-object-manage-user-guide'],
                    ],
                ],
                [
                    'id' => 'accession.provenance', 'label' => 'Provenance', 'sub' => 'Source and method', 'help' => 'provenance-user-guide',
                    'children' => [
                        ['id' => 'accession.provenance.donor',  'label' => 'Donor / source', 'sub' => 'Person / organisation', 'help' => 'authority-resolution-user-guide'],
                        ['id' => 'accession.provenance.method', 'label' => 'Method',         'sub' => 'Purchase / gift / loan'],
                        ['id' => 'accession.provenance.chain',  'label' => 'Provenance chain','sub' => 'Custody history', 'help' => 'provenance-user-guide'],
                    ],
                ],
                [
                    'id' => 'accession.rights', 'label' => 'Rights / ODRL', 'sub' => 'Conditions of gift', 'help' => 'odrl-rights-policies',
                    'children' => [
                        ['id' => 'accession.rights.conditions', 'label' => 'Gift conditions', 'sub' => 'Donor restrictions'],
                        ['id' => 'accession.rights.odrl',       'label' => 'ODRL policy',     'sub' => 'Reuse rules', 'help' => 'odrl-rights-policies'],
                    ],
                ],
                [
                    'id' => 'accession.relations', 'label' => 'Relations', 'sub' => 'What it produced',
                    'children' => [
                        ['id' => 'accession.relations.records', 'label' => 'Created records', 'sub' => 'Spawned descriptions'],
                        ['id' => 'accession.relations.donor',   'label' => 'Linked donor',    'sub' => 'Source actor'],
                    ],
                ],
            ],
        ],

        // =============================================================
        // Terms / subjects / places (taxonomies).
        // =============================================================
        [
            'id'    => 'term',
            'label' => 'Terms / subjects / places',
            'sub'   => 'Taxonomies and thesauri',
            'color' => '#bc6c25',
            'children' => [
                [
                    'id' => 'term.description', 'label' => 'Description', 'sub' => 'Term records',
                    'children' => [
                        ['id' => 'term.description.label',  'label' => 'Preferred label', 'sub' => 'Authorized form'],
                        ['id' => 'term.description.scope',  'label' => 'Scope note',      'sub' => 'Usage guidance'],
                        ['id' => 'term.description.alt',    'label' => 'Alternates',      'sub' => 'Synonyms / variants'],
                    ],
                ],
                [
                    'id' => 'term.relations', 'label' => 'Relations', 'sub' => 'Thesaurus structure', 'help' => 'knowledge-graph-user-guide',
                    'children' => [
                        ['id' => 'term.relations.hierarchy', 'label' => 'Broader / narrower', 'sub' => 'SKOS hierarchy'],
                        ['id' => 'term.relations.related',   'label' => 'Related terms',      'sub' => 'Associative links'],
                        ['id' => 'term.relations.usage',     'label' => 'Indexed records',    'sub' => 'Where the term is used'],
                    ],
                ],
                [
                    'id' => 'term.search', 'label' => 'Search', 'sub' => 'Browse taxonomies', 'help' => 'advanced-search-user-guide',
                    'children' => [
                        ['id' => 'term.search.es',     'label' => 'Elasticsearch',  'sub' => 'heratio_term'],
                        ['id' => 'term.search.browse', 'label' => 'Taxonomy browse', 'sub' => 'Term hierarchy', 'help' => 'advanced-search-user-guide'],
                    ],
                ],
                [
                    'id' => 'term.gis', 'label' => 'Places / GIS', 'sub' => 'Geospatial terms',
                    'children' => [
                        ['id' => 'term.gis.coords', 'label' => 'Coordinates', 'sub' => 'Lat / long'],
                        ['id' => 'term.gis.map',    'label' => 'Map view',    'sub' => 'Place on a map'],
                    ],
                ],
            ],
        ],

        // =============================================================
        // Museum objects (Spectrum 5.1).
        // =============================================================
        [
            'id'    => 'museum',
            'label' => 'Museum objects',
            'sub'   => 'Spectrum 5.1 procedures',
            'color' => '#9e2a2b',
            'help'  => 'museum-module-user-guide',
            'children' => [
                [
                    'id' => 'museum.description', 'label' => 'Description', 'sub' => 'Object identification', 'help' => 'spectrum-user-guide',
                    'children' => [
                        ['id' => 'museum.description.object',  'label' => 'Object entry', 'sub' => 'Identification + number', 'help' => 'spectrum-user-guide'],
                        ['id' => 'museum.description.location','label' => 'Location',     'sub' => 'Movement control'],
                        ['id' => 'museum.description.measure', 'label' => 'Measurements', 'sub' => 'Dimensions / materials'],
                    ],
                ],
                [
                    'id' => 'museum.condition', 'label' => 'Condition', 'sub' => 'Condition checking', 'help' => 'condition-reports',
                    'children' => [
                        ['id' => 'museum.condition.reports', 'label' => 'Condition reports', 'sub' => 'Spectrum assessment', 'help' => 'condition-reports'],
                        ['id' => 'museum.condition.photos',  'label' => 'Condition photos',  'sub' => 'Visual record'],
                        ['id' => 'museum.condition.aiscan',  'label' => 'AI condition scan', 'sub' => 'Vision model', 'help' => 'ai-condition-user-guide'],
                    ],
                ],
                [
                    'id' => 'museum.provenance', 'label' => 'Provenance', 'sub' => 'Ownership history', 'help' => 'provenance-user-guide',
                    'children' => [
                        ['id' => 'museum.provenance.chain',  'label' => 'Provenance chain', 'sub' => 'Chain of ownership', 'help' => 'provenance-user-guide'],
                        ['id' => 'museum.provenance.acquire','label' => 'Acquisition',      'sub' => 'How it was acquired'],
                    ],
                ],
                [
                    'id' => 'museum.privacy', 'label' => 'PII / Privacy', 'sub' => 'Sensitive objects', 'help' => 'odrl-rights-policies',
                    'children' => [
                        ['id' => 'museum.privacy.compliance','label' => 'Privacy compliance', 'sub' => 'Spectrum privacy hooks'],
                        ['id' => 'museum.privacy.icip',      'label' => 'ICIP',               'sub' => 'Indigenous cultural rights'],
                    ],
                ],
                [
                    'id' => 'museum.digital', 'label' => 'Digital objects', 'sub' => 'Object media', 'help' => 'dam-module-user-guide',
                    'children' => [
                        ['id' => 'museum.digital.images', 'label' => 'Images',     'sub' => 'Views / surrogates'],
                        ['id' => 'museum.digital.3d',     'label' => '3D / splat', 'sub' => 'Captured object scan', 'help' => '3d-model-viewer-user-guide'],
                    ],
                ],
                [
                    'id' => 'museum.relations', 'label' => 'Relations', 'sub' => 'Loans + exhibitions',
                    'children' => [
                        ['id' => 'museum.relations.loans',     'label' => 'Loans',       'sub' => 'Inbound / outbound'],
                        ['id' => 'museum.relations.exhibits',  'label' => 'Exhibitions', 'sub' => 'Displayed in'],
                    ],
                ],
            ],
        ],

        // =============================================================
        // Library items (bibliographic).
        // =============================================================
        [
            'id'    => 'library',
            'label' => 'Library items',
            'sub'   => 'Bibliographic records',
            'color' => '#3a7d44',
            'help'  => 'library-user-guide',
            'children' => [
                [
                    'id' => 'library.description', 'label' => 'Description', 'sub' => 'MARC / BIBFRAME / FRBR', 'help' => 'library-user-guide',
                    'children' => [
                        ['id' => 'library.description.bib',     'label' => 'Bibliographic', 'sub' => 'MARC item record'],
                        ['id' => 'library.description.holdings','label' => 'Holdings',      'sub' => 'Copies / availability'],
                        ['id' => 'library.description.frbr',    'label' => 'FRBR / BIBFRAME','sub' => 'Work / expression model'],
                    ],
                ],
                [
                    'id' => 'library.acquire', 'label' => 'Acquisition', 'sub' => 'Order and receive', 'help' => 'library-acquisitions-user-guide',
                    'children' => [
                        ['id' => 'library.acquire.order',   'label' => 'Order / request', 'sub' => 'Purchase or gift', 'help' => 'library-acquisitions-user-guide'],
                        ['id' => 'library.acquire.receive', 'label' => 'Receive',         'sub' => 'Mark items received'],
                    ],
                ],
                [
                    'id' => 'library.search', 'label' => 'Search', 'sub' => 'Discover items', 'help' => 'advanced-search-user-guide',
                    'children' => [
                        ['id' => 'library.search.es',       'label' => 'Elasticsearch',  'sub' => 'Catalogue index'],
                        ['id' => 'library.search.z3950',    'label' => 'Z39.50 / SRU',   'sub' => 'External catalogue search'],
                        ['id' => 'library.search.advanced', 'label' => 'Advanced search', 'sub' => 'Facets + filters', 'help' => 'advanced-search-user-guide'],
                    ],
                ],
                [
                    'id' => 'library.digital', 'label' => 'Digital objects', 'sub' => 'Full text + scans', 'help' => 'dam-module-user-guide',
                    'children' => [
                        ['id' => 'library.digital.fulltext', 'label' => 'Full text',  'sub' => 'PDF / e-book'],
                        ['id' => 'library.digital.iiif',     'label' => 'IIIF deep-zoom', 'sub' => 'Page viewer', 'help' => 'mirador-user-guide'],
                    ],
                ],
            ],
        ],

        // =============================================================
        // Digital objects (DAM master + derivatives).
        // =============================================================
        [
            'id'    => 'dam',
            'label' => 'Digital objects',
            'sub'   => 'Digital asset management',
            'color' => '#5a189a',
            'help'  => 'dam-module-user-guide',
            'children' => [
                [
                    'id' => 'dam.description', 'label' => 'Description', 'sub' => 'Asset metadata', 'help' => 'dam-module-user-guide',
                    'children' => [
                        ['id' => 'dam.description.tech',    'label' => 'Technical metadata', 'sub' => 'Format / size / EXIF'],
                        ['id' => 'dam.description.formats', 'label' => 'Formats',            'sub' => 'Image / AV / docs'],
                    ],
                ],
                [
                    'id' => 'dam.digital', 'label' => 'Derivatives & viewers', 'sub' => 'Surrogates + display', 'help' => 'mirador-user-guide',
                    'children' => [
                        ['id' => 'dam.digital.master',  'label' => 'Master + derivatives', 'sub' => 'Preservation + access'],
                        ['id' => 'dam.digital.iiif',    'label' => 'IIIF deep-zoom',        'sub' => 'Mirador / Cantaloupe', 'help' => 'mirador-user-guide'],
                        ['id' => 'dam.digital.3d',      'label' => '3D / splat',            'sub' => 'glTF + Gaussian splat', 'help' => '3d-model-viewer-user-guide'],
                        ['id' => 'dam.digital.stream',  'label' => 'AV streaming',          'sub' => 'Audio / video player'],
                    ],
                ],
                [
                    'id' => 'dam.ai', 'label' => 'AI', 'sub' => 'Media AI', 'help' => 'ner-user-guide',
                    'children' => [
                        ['id' => 'dam.ai.htr',     'label' => 'HTR / OCR',    'sub' => 'Text from images', 'help' => 'htr-user-guide'],
                        ['id' => 'dam.ai.extract', 'label' => 'Metadata extraction', 'sub' => 'Auto-derived fields'],
                        ['id' => 'dam.ai.dedupe',  'label' => 'Duplicate detection', 'sub' => 'Similarity match', 'help' => 'duplicate-detection-user-guide'],
                    ],
                ],
                [
                    'id' => 'dam.rights', 'label' => 'Rights / ODRL', 'sub' => 'Reuse + watermarks', 'help' => 'odrl-rights-policies',
                    'children' => [
                        ['id' => 'dam.rights.odrl',      'label' => 'ODRL policy',  'sub' => 'reproduce rules', 'help' => 'odrl-rights-policies'],
                        ['id' => 'dam.rights.watermark', 'label' => 'Watermarks',   'sub' => 'Derivative protection'],
                        ['id' => 'dam.rights.c2pa',      'label' => 'C2PA',         'sub' => 'Signed content credentials'],
                    ],
                ],
                [
                    'id' => 'dam.preservation', 'label' => 'Preservation', 'sub' => 'Fixity + OCFL', 'help' => 'preservation-user-guide',
                    'children' => [
                        ['id' => 'dam.preservation.fixity', 'label' => 'Fixity',  'sub' => 'Checksums + audits'],
                        ['id' => 'dam.preservation.ocfl',   'label' => 'OCFL',    'sub' => 'Object storage layout'],
                    ],
                ],
            ],
        ],

        // =============================================================
        // Exhibitions / digital twin.
        // =============================================================
        [
            'id'    => 'exhibit',
            'label' => 'Exhibitions / digital twin',
            'sub'   => 'Curated shows + 3D walkthrough',
            'color' => '#e09f3e',
            'help'  => 'exhibition-user-guide',
            'children' => [
                [
                    'id' => 'exhibit.description', 'label' => 'Description', 'sub' => 'Exhibition builder', 'help' => 'exhibition-user-guide',
                    'children' => [
                        ['id' => 'exhibit.description.curate', 'label' => 'Curate',  'sub' => 'Pick + order items', 'help' => 'exhibition-user-guide'],
                        ['id' => 'exhibit.description.layout', 'label' => 'Layout',  'sub' => 'Landing-page design'],
                        ['id' => 'exhibit.description.publish','label' => 'Publish', 'sub' => 'Go live'],
                    ],
                ],
                [
                    'id' => 'exhibit.twin', 'label' => 'Digital twin', 'sub' => '3D walkthrough', 'help' => 'exhibition-digital-twin',
                    'children' => [
                        ['id' => 'exhibit.twin.scene', 'label' => 'Scene',       'sub' => 'Interior walls / scale', 'help' => 'exhibition-digital-twin'],
                        ['id' => 'exhibit.twin.walk',  'label' => 'Walkthrough', 'sub' => 'First-person navigation'],
                    ],
                ],
                [
                    'id' => 'exhibit.relations', 'label' => 'Relations', 'sub' => 'Items on show',
                    'children' => [
                        ['id' => 'exhibit.relations.items', 'label' => 'Exhibited items', 'sub' => 'Records / objects'],
                        ['id' => 'exhibit.relations.tour',  'label' => 'Tour order',      'sub' => 'Sequence of stops'],
                    ],
                ],
                [
                    'id' => 'exhibit.engage', 'label' => 'Engagement', 'sub' => 'Cart + marketplace', 'help' => 'marketplace-flow-guide',
                    'children' => [
                        ['id' => 'exhibit.engage.cart',  'label' => 'Cart',          'sub' => 'Add to cart', 'help' => 'marketplace-flow-guide'],
                        ['id' => 'exhibit.engage.repro', 'label' => 'Reproduction',  'sub' => 'Order copies'],
                    ],
                ],
            ],
        ],
    ],
];
