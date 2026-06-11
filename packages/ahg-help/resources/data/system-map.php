<?php

/**
 * system-map.php - Data model for the interactive system flow map
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
 * This is the single source of truth for the help-system "System Map"
 * (route help.system-map). It is data-driven on purpose - the map is NOT a
 * hand-drawn picture. To change the diagram you edit this array; nothing in
 * the controller, view, or JS needs to change.
 *
 * Each TOP-LEVEL stage is a compound (parent) node. Its `children` are the
 * sub-flow nodes that the user reveals by drilling INTO the stage. `edges`
 * declares the directed flow between top-level stages; child-level edges are
 * declared inside each stage under `child_edges` and are only shown while
 * that stage is expanded.
 *
 * NODE FIELDS
 *   id       (string, required)  unique slug-id, used as the Cytoscape node id
 *   label    (string, required)  text shown on the node
 *   sub      (string, optional)  one-line subtitle / standards line
 *   band     (string, optional)  cross-cutting band id this node belongs to
 *                                (auth | settings | rights) - drawn as a tint
 *   help     (string, optional)  help_article slug -> deep-links to
 *                                /help/article/{slug}. Omit if no article yet.
 *   children (array, optional)   sub-flow nodes (same field shape, minus
 *                                children); revealed on drill-in
 *   child_edges (array, optional) [from-child-id, to-child-id] pairs inside
 *                                the stage
 *
 * Help-article slugs below were resolved against the live help_article table
 * (is_published=1). If you rename/retire an article, update or drop the
 * `help` key here so the node stops deep-linking to a 404.
 */

return [
    // -----------------------------------------------------------------
    // Cross-cutting bands. These are concerns that touch every stage.
    // Rendered as a legend + an optional tint on member nodes.
    // -----------------------------------------------------------------
    'bands' => [
        'auth'     => ['label' => 'Auth / ACL',           'color' => '#6c757d', 'help' => 'shacl-validation-howto'],
        'settings' => ['label' => 'Settings / Dropdowns',  'color' => '#0d6efd', 'help' => 'ahg-settings-user-guide'],
        'rights'   => ['label' => 'Rights / ODRL',         'color' => '#b5179e', 'help' => 'odrl-rights-policies'],
    ],

    // -----------------------------------------------------------------
    // The platform journey. Each entry is a top-level stage (compound node).
    // -----------------------------------------------------------------
    'stages' => [
        [
            'id'    => 'acquire',
            'label' => 'Acquisition & Accession',
            'sub'   => 'Acquire, accession, donors',
            'color' => '#264653',
            'help'  => 'accession-v2-user-guide',
            'children' => [
                ['id' => 'acquire.acquisition', 'label' => 'Acquisition',     'sub' => 'Library / archival intake', 'help' => 'library-acquisitions-user-guide'],
                ['id' => 'acquire.accession',   'label' => 'Accession record', 'sub' => 'Accession Management V2',    'help' => 'accession-v2-user-guide'],
                ['id' => 'acquire.donor',       'label' => 'Donors & source',  'sub' => 'Provenance origin',          'help' => 'ahgprovenanceplugin'],
            ],
            'child_edges' => [
                ['acquire.acquisition', 'acquire.accession'],
                ['acquire.accession', 'acquire.donor'],
            ],
        ],
        [
            'id'    => 'ingest',
            'label' => 'Ingest / Scan',
            'sub'   => 'CSV / file batch, watched folders',
            'color' => '#2a6f97',
            'help'  => 'data-ingest-user-guide',
            'children' => [
                ['id' => 'ingest.configure', 'label' => 'Configure',  'sub' => 'Pick source / template',      'help' => 'data-ingest-user-guide'],
                ['id' => 'ingest.upload',    'label' => 'Upload',      'sub' => 'CSV + files / scan capture',   'help' => 'scanner-capture-user-guide'],
                ['id' => 'ingest.map',       'label' => 'Map',         'sub' => 'Column -> field mapping',      'help' => 'data-ingest-user-guide'],
                ['id' => 'ingest.validate',  'label' => 'Validate',    'sub' => 'File + metadata checks',       'help' => 'atom-heratio-filevalidationservice-feature-overview'],
                ['id' => 'ingest.ai',        'label' => 'AI steps',    'sub' => 'OCR / NER / summarize / scan', 'help' => 'ner-user-guide'],
                ['id' => 'ingest.commit',    'label' => 'Commit',      'sub' => 'Create records',               'help' => 'data-ingest-user-guide'],
            ],
            'child_edges' => [
                ['ingest.configure', 'ingest.upload'],
                ['ingest.upload', 'ingest.map'],
                ['ingest.map', 'ingest.validate'],
                ['ingest.validate', 'ingest.ai'],
                ['ingest.ai', 'ingest.commit'],
            ],
        ],
        [
            'id'    => 'describe',
            'label' => 'Description',
            'sub'   => 'ISAD(G) / RiC / Spectrum / CCO',
            'color' => '#1b998b',
            'help'  => 'information-object-manage-user-guide',
            'children' => [
                ['id' => 'describe.isad',     'label' => 'ISAD(G)',     'sub' => 'Archival description',      'help' => 'information-object-manage-user-guide'],
                ['id' => 'describe.ric',      'label' => 'RiC',         'sub' => 'Records in Contexts',      'help' => 'ric-user-guide'],
                ['id' => 'describe.spectrum', 'label' => 'Spectrum',    'sub' => 'Museum procedures 5.1',    'help' => 'spectrum-user-guide'],
                ['id' => 'describe.cco',      'label' => 'CCO / VRA',   'sub' => 'Cultural objects / works', 'help' => 'gallery-module-user-guide'],
                ['id' => 'describe.authority','label' => 'Authorities', 'sub' => 'Actors, terms, places',    'help' => 'authority-resolution-user-guide'],
                ['id' => 'describe.condition','label' => 'Condition',   'sub' => 'Condition reports',        'help' => 'condition-reports'],
            ],
            'child_edges' => [
                ['describe.isad', 'describe.authority'],
                ['describe.ric', 'describe.authority'],
                ['describe.spectrum', 'describe.condition'],
                ['describe.cco', 'describe.authority'],
            ],
        ],
        [
            'id'    => 'preserve',
            'label' => 'Preservation',
            'sub'   => 'OAIS / PREMIS / fixity',
            'color' => '#3a7d44',
            'help'  => 'preservation-user-guide',
            'children' => [
                ['id' => 'preserve.oais',  'label' => 'OAIS ingest',  'sub' => 'AIP / SIP / DIP',     'help' => 'preservation-user-guide'],
                ['id' => 'preserve.premis','label' => 'PREMIS',       'sub' => 'Preservation events', 'help' => 'ahgpreservationplugin'],
                ['id' => 'preserve.fixity','label' => 'Fixity',       'sub' => 'Checksums / audits',  'help' => 'preservation-user-guide'],
                ['id' => 'preserve.3d',    'label' => '3D / media',   'sub' => '3D model preservation','help' => '3d-preservation'],
            ],
            'child_edges' => [
                ['preserve.oais', 'preserve.premis'],
                ['preserve.premis', 'preserve.fixity'],
            ],
        ],
        [
            'id'    => 'search',
            'label' => 'Search',
            'sub'   => 'Elasticsearch + semantic / KM',
            'color' => '#bc6c25',
            'help'  => 'advanced-search-user-guide',
            'children' => [
                ['id' => 'search.es',       'label' => 'Elasticsearch', 'sub' => 'Index + advanced search', 'help' => 'elasticsearch-configuration'],
                ['id' => 'search.advanced', 'label' => 'Advanced',      'sub' => 'Facets / filters',        'help' => 'advanced-search-user-guide'],
                ['id' => 'search.semantic', 'label' => 'Semantic',      'sub' => 'Vector / KM RAG',         'help' => 'semantic-search-user-guide'],
                ['id' => 'search.graph',    'label' => 'Knowledge graph','sub' => 'Entity links',           'help' => 'knowledge-graph-user-guide'],
            ],
            'child_edges' => [
                ['search.es', 'search.advanced'],
                ['search.es', 'search.semantic'],
                ['search.semantic', 'search.graph'],
            ],
        ],
        [
            'id'    => 'display',
            'label' => 'Display',
            'sub'   => 'GLAM browse, sector shows, IIIF / 3D / splat',
            'color' => '#e09f3e',
            'help'  => 'glam-browse-user-guide',
            'children' => [
                ['id' => 'display.glam',    'label' => 'GLAM browse',  'sub' => 'Card / grid / table',    'help' => 'glam-browse-user-guide'],
                ['id' => 'display.library', 'label' => 'Library',      'sub' => 'Library sector show',    'help' => 'ahglibraryplugin'],
                ['id' => 'display.museum',  'label' => 'Museum',       'sub' => 'Museum sector show',     'help' => 'museum-module-user-guide'],
                ['id' => 'display.gallery', 'label' => 'Gallery',      'sub' => 'Gallery sector show',    'help' => 'gallery-module-user-guide'],
                ['id' => 'display.dam',     'label' => 'DAM',          'sub' => 'Digital asset show',     'help' => 'dam-module-user-guide'],
                ['id' => 'display.iiif',    'label' => 'IIIF viewer',  'sub' => 'Mirador / OpenSeadragon','help' => 'mirador-user-guide'],
                ['id' => 'display.3d',      'label' => '3D / splat',   'sub' => '3D + Gaussian splat',    'help' => '3d-model-viewer-user-guide'],
            ],
            'child_edges' => [
                ['display.glam', 'display.library'],
                ['display.glam', 'display.museum'],
                ['display.glam', 'display.gallery'],
                ['display.glam', 'display.dam'],
                ['display.library', 'display.iiif'],
                ['display.dam', 'display.3d'],
            ],
        ],
        [
            'id'    => 'exhibit',
            'label' => 'Exhibitions / Digital Twin',
            'sub'   => 'Exhibition builder + 3D walkthrough',
            'color' => '#9e2a2b',
            'help'  => 'exhibition-user-guide',
            'children' => [
                ['id' => 'exhibit.builder', 'label' => 'Exhibition builder', 'sub' => 'Curate + landing page', 'help' => 'exhibition-user-guide'],
                ['id' => 'exhibit.twin',    'label' => 'Digital twin',       'sub' => '3D walkthrough',        'help' => 'exhibition-digital-twin'],
                ['id' => 'exhibit.engage',  'label' => 'Engage',             'sub' => 'Cart / marketplace',    'help' => 'marketplace-flow-guide'],
            ],
            'child_edges' => [
                ['exhibit.builder', 'exhibit.twin'],
                ['exhibit.builder', 'exhibit.engage'],
            ],
        ],
        [
            'id'    => 'ai',
            'label' => 'AI Services',
            'sub'   => 'HTR / NER / condition / translate',
            'color' => '#5a189a',
            'help'  => 'ner-user-guide',
            'children' => [
                ['id' => 'ai.ner',       'label' => 'NER',          'sub' => 'Entity extraction',     'help' => 'ner-user-guide'],
                ['id' => 'ai.condition', 'label' => 'AI condition', 'sub' => 'Condition assessment',  'help' => 'ai-condition-user-guide'],
                ['id' => 'ai.translate', 'label' => 'Translate',    'sub' => 'Machine translation',   'help' => 'translation-user-guide'],
                ['id' => 'ai.dedupe',    'label' => 'Duplicates',   'sub' => 'Duplicate detection',   'help' => 'duplicate-detection-user-guide'],
                ['id' => 'ai.provenance','label' => 'AI provenance','sub' => 'Inference provenance',  'help' => 'ai-inference-provenance-user-guide'],
            ],
            'child_edges' => [
                ['ai.ner', 'ai.provenance'],
                ['ai.condition', 'ai.provenance'],
                ['ai.translate', 'ai.provenance'],
            ],
        ],
        [
            'id'    => 'interop',
            'label' => 'APIs & Interoperability',
            'sub'   => 'IIIF, Linked Data, OAI, C2PA',
            'color' => '#0b525b',
            'help'  => 'api-user-guide',
            'children' => [
                ['id' => 'interop.rest',   'label' => 'REST / GraphQL', 'sub' => 'API v1 / v2 + GraphQL', 'help' => 'api-user-guide'],
                ['id' => 'interop.iiif',   'label' => 'IIIF API',       'sub' => 'Presentation + Image',  'help' => 'ahgiiifplugin'],
                ['id' => 'interop.ld',     'label' => 'Linked Data',    'sub' => 'RiC JSON-LD / SPARQL',  'help' => 'ahgricexplorerplugin'],
                ['id' => 'interop.oai',    'label' => 'OAI-PMH',        'sub' => 'Metadata harvesting'],
                ['id' => 'interop.c2pa',   'label' => 'C2PA',           'sub' => 'Content credentials'],
                ['id' => 'interop.doi',    'label' => 'DOI',            'sub' => 'Persistent identifiers','help' => 'doi-user-guide'],
                ['id' => 'interop.federation','label' => 'Federation',  'sub' => 'Federated search',      'help' => 'federation-user-guide'],
            ],
            'child_edges' => [
                ['interop.rest', 'interop.oai'],
                ['interop.iiif', 'interop.ld'],
            ],
        ],
    ],

    // -----------------------------------------------------------------
    // Directed flow between top-level stages (the spine of the journey).
    // -----------------------------------------------------------------
    'edges' => [
        ['acquire', 'ingest'],
        ['ingest', 'describe'],
        ['describe', 'preserve'],
        ['preserve', 'search'],
        ['search', 'display'],
        ['display', 'exhibit'],
        ['display', 'ai'],
        ['ai', 'describe'],     // AI feeds enrichment back into description
        ['display', 'interop'],
        ['exhibit', 'interop'],
    ],
];
