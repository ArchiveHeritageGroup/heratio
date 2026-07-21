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
 * sub-flow nodes that the user reveals by drilling INTO the stage. Each child
 * may in turn carry its own `children` (the third level: concrete detail nodes
 * - the bits of plumbing or sub-steps behind that sub-area). `edges` declares
 * the directed flow between top-level stages; child-level edges are declared
 * inside each stage under `child_edges`, and grandchild-level edges inside each
 * child under its own `child_edges`. Each level's edges are only shown while
 * that level is expanded.
 *
 * The map therefore drills THREE levels:
 *   stage  ->  child (sub-area)  ->  grandchild (detail)
 * e.g. AI Services -> NER -> [Gateway, Models, Provenance logged].
 *
 * NODE FIELDS
 *   id       (string, required)  unique slug-id, used as the Cytoscape node id.
 *                                Keep ids hierarchical: stage.child.detail
 *   label    (string, required)  text shown on the node
 *   sub      (string, optional)  one-line subtitle / standards line
 *   band     (string, optional)  cross-cutting band id this node belongs to
 *                                (auth | settings | rights) - drawn as a tint
 *   help     (string, optional)  help_article slug -> deep-links to
 *                                /help/article/{slug}. Omit if no article yet.
 *                                The service nulls any slug the viewer cannot
 *                                open, so an absent/unknown slug degrades to
 *                                plain text - never a dead link.
 *   children (array, optional)   sub-flow nodes (same field shape); revealed on
 *                                drill-in. A child's own `children` are the
 *                                third-level detail nodes.
 *   child_edges (array, optional) [from-id, to-id] pairs inside the stage (at
 *                                child level) or inside a child (at grandchild
 *                                level) - only shown while that level is open
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
                [
                    'id' => 'acquire.acquisition', 'label' => 'Acquisition', 'sub' => 'Library / archival intake', 'help' => 'library-acquisitions-user-guide',
                    'children' => [
                        ['id' => 'acquire.acquisition.order',   'label' => 'Order / request', 'sub' => 'Purchase or gift request'],
                        ['id' => 'acquire.acquisition.receive', 'label' => 'Receive',         'sub' => 'Mark items received'],
                        ['id' => 'acquire.acquisition.method',  'label' => 'Method',          'sub' => 'Purchase / gift / loan'],
                    ],
                    'child_edges' => [
                        ['acquire.acquisition.order', 'acquire.acquisition.receive'],
                    ],
                ],
                [
                    'id' => 'acquire.accession', 'label' => 'Accession record', 'sub' => 'Accession Management V2', 'help' => 'accession-v2-user-guide',
                    'children' => [
                        ['id' => 'acquire.accession.id',     'label' => 'Accession number', 'sub' => 'Auto-generated identifier'],
                        ['id' => 'acquire.accession.scope',  'label' => 'Scope & content',  'sub' => 'What was accessioned'],
                        ['id' => 'acquire.accession.toio',   'label' => 'Create record',    'sub' => 'Spawn archival description', 'help' => 'information-object-manage-user-guide'],
                    ],
                    'child_edges' => [
                        ['acquire.accession.id', 'acquire.accession.scope'],
                        ['acquire.accession.scope', 'acquire.accession.toio'],
                    ],
                ],
                [
                    'id' => 'acquire.donor', 'label' => 'Donors & source', 'sub' => 'Provenance origin', 'help' => 'provenance-user-guide',
                    'children' => [
                        ['id' => 'acquire.donor.actor',  'label' => 'Donor actor',     'sub' => 'Person / organisation', 'help' => 'authority-resolution-user-guide'],
                        ['id' => 'acquire.donor.chain',  'label' => 'Provenance chain', 'sub' => 'Custody history',       'help' => 'provenance-user-guide'],
                        ['id' => 'acquire.donor.rights', 'label' => 'Donor rights',     'sub' => 'Conditions of gift'],
                    ],
                    'child_edges' => [
                        ['acquire.donor.actor', 'acquire.donor.chain'],
                    ],
                ],
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
                [
                    'id' => 'ingest.configure', 'label' => 'Configure', 'sub' => 'Pick source / template', 'help' => 'data-ingest-user-guide',
                    'children' => [
                        ['id' => 'ingest.configure.source',   'label' => 'Source',   'sub' => 'CSV / folder / scan', 'help' => 'scanner-capture-user-guide'],
                        ['id' => 'ingest.configure.template', 'label' => 'Template', 'sub' => 'Entity + field set',  'help' => 'data-ingest-user-guide'],
                    ],
                ],
                [
                    'id' => 'ingest.upload', 'label' => 'Upload', 'sub' => 'CSV + files / scan capture', 'help' => 'scanner-capture-user-guide',
                    'children' => [
                        ['id' => 'ingest.upload.files',   'label' => 'Files',         'sub' => 'CSV + digital objects'],
                        ['id' => 'ingest.upload.watched', 'label' => 'Watched folder','sub' => 'Auto-pick scan output', 'help' => 'scanner-capture-user-guide'],
                    ],
                ],
                [
                    'id' => 'ingest.map', 'label' => 'Map', 'sub' => 'Column -> field mapping', 'help' => 'data-ingest-user-guide',
                    'children' => [
                        ['id' => 'ingest.map.columns', 'label' => 'Columns',  'sub' => 'CSV header -> field'],
                        ['id' => 'ingest.map.defaults','label' => 'Defaults',  'sub' => 'Fixed / fallback values'],
                    ],
                ],
                [
                    'id' => 'ingest.validate', 'label' => 'Validate', 'sub' => 'File + metadata checks', 'help' => 'atom-heratio-filevalidationservice-feature-overview',
                    'children' => [
                        ['id' => 'ingest.validate.file', 'label' => 'File checks',     'sub' => 'Type / size / virus', 'help' => 'atom-heratio-filevalidationservice-feature-overview'],
                        ['id' => 'ingest.validate.meta', 'label' => 'Metadata checks', 'sub' => 'Required fields'],
                    ],
                    'child_edges' => [
                        ['ingest.validate.file', 'ingest.validate.meta'],
                    ],
                ],
                [
                    'id' => 'ingest.ai', 'label' => 'AI steps', 'sub' => 'OCR / NER / summarize / scan', 'help' => 'ner-user-guide',
                    'children' => [
                        ['id' => 'ingest.ai.ocr',       'label' => 'OCR / HTR',  'sub' => 'Text from images',  'help' => 'htr-user-guide'],
                        ['id' => 'ingest.ai.ner',       'label' => 'NER',        'sub' => 'Entity extraction', 'help' => 'ner-user-guide'],
                        ['id' => 'ingest.ai.summarize', 'label' => 'Summarize',  'sub' => 'Scope-note draft'],
                        ['id' => 'ingest.ai.gateway',   'label' => 'AI gateway', 'sub' => 'ai.theahg.co.za'],
                    ],
                    'child_edges' => [
                        ['ingest.ai.ocr', 'ingest.ai.ner'],
                        ['ingest.ai.ner', 'ingest.ai.gateway'],
                    ],
                ],
                [
                    'id' => 'ingest.commit', 'label' => 'Commit', 'sub' => 'Create records', 'help' => 'data-ingest-user-guide',
                    'children' => [
                        ['id' => 'ingest.commit.preview', 'label' => 'Preview', 'sub' => 'Dry-run before write'],
                        ['id' => 'ingest.commit.create',  'label' => 'Create',  'sub' => 'Write records + index'],
                    ],
                    'child_edges' => [
                        ['ingest.commit.preview', 'ingest.commit.create'],
                    ],
                ],
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
                [
                    'id' => 'describe.isad', 'label' => 'ISAD(G)', 'sub' => 'Archival description', 'help' => 'information-object-manage-user-guide',
                    'children' => [
                        ['id' => 'describe.isad.identity', 'label' => 'Identity',    'sub' => 'Reference / title / dates'],
                        ['id' => 'describe.isad.context',  'label' => 'Context',     'sub' => 'Creator / custody'],
                        ['id' => 'describe.isad.content',  'label' => 'Content',     'sub' => 'Scope / arrangement'],
                        ['id' => 'describe.isad.levels',   'label' => 'Levels',      'sub' => 'Fonds -> item hierarchy'],
                    ],
                    'child_edges' => [
                        ['describe.isad.identity', 'describe.isad.context'],
                        ['describe.isad.context', 'describe.isad.content'],
                    ],
                ],
                [
                    'id' => 'describe.ric', 'label' => 'RiC', 'sub' => 'Records in Contexts', 'help' => 'ric-user-guide',
                    'children' => [
                        ['id' => 'describe.ric.entities', 'label' => 'Entities',  'sub' => 'Record / agent / activity', 'help' => 'ric-user-guide'],
                        ['id' => 'describe.ric.relations','label' => 'Relations', 'sub' => 'rico:* predicates'],
                        ['id' => 'describe.ric.graph',    'label' => 'Graph',     'sub' => 'JSON-LD / SPARQL',          'help' => 'knowledge-graph-user-guide'],
                    ],
                    'child_edges' => [
                        ['describe.ric.entities', 'describe.ric.relations'],
                        ['describe.ric.relations', 'describe.ric.graph'],
                    ],
                ],
                [
                    'id' => 'describe.spectrum', 'label' => 'Museum procedures', 'sub' => 'Museum object procedures', 'help' => 'spectrum-user-guide',
                    'children' => [
                        ['id' => 'describe.spectrum.object', 'label' => 'Object entry', 'sub' => 'Object identification', 'help' => 'spectrum-user-guide'],
                        ['id' => 'describe.spectrum.loc',    'label' => 'Location',     'sub' => 'Movement control'],
                        ['id' => 'describe.spectrum.privacy','label' => 'Privacy',      'sub' => 'Compliance hooks'],
                    ],
                    'child_edges' => [
                        ['describe.spectrum.object', 'describe.spectrum.loc'],
                    ],
                ],
                [
                    'id' => 'describe.cco', 'label' => 'CCO / VRA', 'sub' => 'Cultural objects / works', 'help' => 'gallery-user-guide',
                    'children' => [
                        ['id' => 'describe.cco.work',  'label' => 'Work',  'sub' => 'Work record',  'help' => 'gallery-user-guide'],
                        ['id' => 'describe.cco.image', 'label' => 'Image', 'sub' => 'Surrogate / view'],
                    ],
                ],
                [
                    'id' => 'describe.authority', 'label' => 'Authorities', 'sub' => 'Actors, terms, places', 'help' => 'authority-resolution-user-guide',
                    'children' => [
                        ['id' => 'describe.authority.actor', 'label' => 'Actors', 'sub' => 'ISAAR persons / bodies', 'help' => 'authority-resolution-user-guide'],
                        ['id' => 'describe.authority.term',  'label' => 'Terms',  'sub' => 'Subjects / places'],
                        ['id' => 'describe.authority.dedupe','label' => 'Resolve','sub' => 'Match / merge'],
                    ],
                    'child_edges' => [
                        ['describe.authority.actor', 'describe.authority.dedupe'],
                        ['describe.authority.term', 'describe.authority.dedupe'],
                    ],
                ],
                [
                    'id' => 'describe.condition', 'label' => 'Condition', 'sub' => 'Condition reports', 'help' => 'condition-reports',
                    'children' => [
                        ['id' => 'describe.condition.assess', 'label' => 'Assessment', 'sub' => 'Condition grade', 'help' => 'condition-reports'],
                        ['id' => 'describe.condition.photos', 'label' => 'Photos',     'sub' => 'Condition images'],
                    ],
                ],
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
                [
                    'id' => 'preserve.oais', 'label' => 'OAIS ingest', 'sub' => 'AIP / SIP / DIP', 'help' => 'preservation-user-guide',
                    'children' => [
                        ['id' => 'preserve.oais.sip', 'label' => 'SIP', 'sub' => 'Submission package'],
                        ['id' => 'preserve.oais.aip', 'label' => 'AIP', 'sub' => 'Archival package'],
                        ['id' => 'preserve.oais.dip', 'label' => 'DIP', 'sub' => 'Dissemination package'],
                    ],
                    'child_edges' => [
                        ['preserve.oais.sip', 'preserve.oais.aip'],
                        ['preserve.oais.aip', 'preserve.oais.dip'],
                    ],
                ],
                [
                    'id' => 'preserve.premis', 'label' => 'PREMIS', 'sub' => 'Preservation events', 'help' => 'preservation-user-guide',
                    'children' => [
                        ['id' => 'preserve.premis.events', 'label' => 'Events',  'sub' => 'Capture / migrate', 'help' => 'preservation-user-guide'],
                        ['id' => 'preserve.premis.agents', 'label' => 'Agents',  'sub' => 'Who / what acted'],
                    ],
                ],
                [
                    'id' => 'preserve.fixity', 'label' => 'Fixity', 'sub' => 'Checksums / audits', 'help' => 'preservation-user-guide',
                    'children' => [
                        ['id' => 'preserve.fixity.checksum', 'label' => 'Checksums', 'sub' => 'SHA / MD5 on ingest'],
                        ['id' => 'preserve.fixity.audit',    'label' => 'Audits',    'sub' => 'Scheduled re-verify'],
                    ],
                    'child_edges' => [
                        ['preserve.fixity.checksum', 'preserve.fixity.audit'],
                    ],
                ],
                [
                    'id' => 'preserve.3d', 'label' => '3D / media', 'sub' => '3D model preservation', 'help' => '3d-preservation',
                    'children' => [
                        ['id' => 'preserve.3d.formats', 'label' => 'Formats', 'sub' => 'glTF / PLY / splat', 'help' => '3d-preservation'],
                        ['id' => 'preserve.3d.derive',  'label' => 'Derivatives','sub' => 'Web-ready surrogates'],
                    ],
                ],
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
                [
                    'id' => 'search.es', 'label' => 'Elasticsearch', 'sub' => 'Index + advanced search', 'help' => 'elasticsearch-configuration',
                    'children' => [
                        ['id' => 'search.es.indices',   'label' => 'Indices',   'sub' => 'heratio_* indices',     'help' => 'elasticsearch-configuration'],
                        ['id' => 'search.es.reindex',   'label' => 'Reindex',   'sub' => 'ahg:es-reindex command'],
                        ['id' => 'search.es.analyzers', 'label' => 'Analyzers', 'sub' => 'Tokenize / stem'],
                    ],
                    'child_edges' => [
                        ['search.es.indices', 'search.es.reindex'],
                    ],
                ],
                [
                    'id' => 'search.advanced', 'label' => 'Advanced', 'sub' => 'Facets / filters', 'help' => 'advanced-search-user-guide',
                    'children' => [
                        ['id' => 'search.advanced.facets',   'label' => 'Facets',   'sub' => 'Aggregated filters', 'help' => 'advanced-search-user-guide'],
                        ['id' => 'search.advanced.ancestor', 'label' => 'Ancestor', 'sub' => 'lft/rgt subtree'],
                    ],
                ],
                [
                    'id' => 'search.semantic', 'label' => 'Semantic', 'sub' => 'Vector / KM RAG', 'help' => 'semantic-search-user-guide',
                    'children' => [
                        ['id' => 'search.semantic.vectors', 'label' => 'Vectors', 'sub' => 'Embeddings store',   'help' => 'semantic-search-user-guide'],
                        ['id' => 'search.semantic.km',      'label' => 'KM RAG',   'sub' => 'km.theahg.co.za'],
                        ['id' => 'search.semantic.gateway', 'label' => 'AI gateway','sub' => 'Embedding endpoint'],
                    ],
                    'child_edges' => [
                        ['search.semantic.vectors', 'search.semantic.km'],
                    ],
                ],
                [
                    'id' => 'search.graph', 'label' => 'Knowledge graph', 'sub' => 'Entity links', 'help' => 'knowledge-graph-user-guide',
                    'children' => [
                        ['id' => 'search.graph.nodes', 'label' => 'Entities', 'sub' => 'Records / actors / terms', 'help' => 'knowledge-graph-user-guide'],
                        ['id' => 'search.graph.edges', 'label' => 'Links',    'sub' => 'Typed relations'],
                    ],
                ],
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
                [
                    'id' => 'display.glam', 'label' => 'GLAM browse', 'sub' => 'Card / grid / table', 'help' => 'glam-browse-user-guide',
                    'children' => [
                        ['id' => 'display.glam.views',  'label' => 'View modes', 'sub' => 'Card / grid / table / full', 'help' => 'glam-browse-user-guide'],
                        ['id' => 'display.glam.facets', 'label' => 'Facets',     'sub' => 'Refine results'],
                    ],
                ],
                [
                    'id' => 'display.library', 'label' => 'Library', 'sub' => 'Library sector show', 'help' => 'library-user-guide',
                    'children' => [
                        ['id' => 'display.library.bib',  'label' => 'Bibliographic', 'sub' => 'MARC / item record'],
                        ['id' => 'display.library.copy', 'label' => 'Holdings',      'sub' => 'Copies / availability'],
                    ],
                ],
                [
                    'id' => 'display.museum', 'label' => 'Museum', 'sub' => 'Museum sector show', 'help' => 'museum-user-guide',
                    'children' => [
                        ['id' => 'display.museum.object', 'label' => 'Object',   'sub' => 'Museum object view', 'help' => 'museum-user-guide'],
                        ['id' => 'display.museum.loc',    'label' => 'Location', 'sub' => 'Current location'],
                    ],
                ],
                [
                    'id' => 'display.gallery', 'label' => 'Gallery', 'sub' => 'Gallery sector show', 'help' => 'gallery-user-guide',
                    'children' => [
                        ['id' => 'display.gallery.work',  'label' => 'Work',  'sub' => 'CCO work view', 'help' => 'gallery-user-guide'],
                        ['id' => 'display.gallery.image', 'label' => 'Images','sub' => 'Views / surrogates'],
                    ],
                ],
                [
                    'id' => 'display.dam', 'label' => 'DAM', 'sub' => 'Digital asset show', 'help' => 'dam-user-guide',
                    'children' => [
                        ['id' => 'display.dam.asset',   'label' => 'Asset',    'sub' => 'Master + derivatives', 'help' => 'dam-user-guide'],
                        ['id' => 'display.dam.formats', 'label' => 'Formats',  'sub' => 'Image / AV / docs'],
                    ],
                ],
                [
                    'id' => 'display.iiif', 'label' => 'IIIF viewer', 'sub' => 'Mirador / OpenSeadragon', 'help' => 'mirador-user-guide',
                    'children' => [
                        ['id' => 'display.iiif.mirador', 'label' => 'Mirador',     'sub' => 'Multi-image workspace', 'help' => 'mirador-user-guide'],
                        ['id' => 'display.iiif.osd',     'label' => 'OpenSeadragon','sub' => 'Deep-zoom tiles'],
                        ['id' => 'display.iiif.canta',   'label' => 'Cantaloupe',   'sub' => 'IIIF Image API 3.0'],
                    ],
                    'child_edges' => [
                        ['display.iiif.canta', 'display.iiif.osd'],
                    ],
                ],
                [
                    'id' => 'display.3d', 'label' => '3D / splat', 'sub' => '3D + Gaussian splat', 'help' => '3d-model-viewer-user-guide',
                    'children' => [
                        ['id' => 'display.3d.model', 'label' => '3D model',  'sub' => 'glTF mesh viewer', 'help' => '3d-model-viewer-user-guide'],
                        ['id' => 'display.3d.splat', 'label' => 'Splat',     'sub' => 'Gaussian splat scene'],
                    ],
                ],
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
                [
                    'id' => 'exhibit.builder', 'label' => 'Exhibition builder', 'sub' => 'Curate + landing page', 'help' => 'exhibition-user-guide',
                    'children' => [
                        ['id' => 'exhibit.builder.curate', 'label' => 'Curate',  'sub' => 'Pick + order items', 'help' => 'exhibition-user-guide'],
                        ['id' => 'exhibit.builder.layout', 'label' => 'Layout',  'sub' => 'Landing-page design'],
                        ['id' => 'exhibit.builder.publish','label' => 'Publish', 'sub' => 'Go live'],
                    ],
                    'child_edges' => [
                        ['exhibit.builder.curate', 'exhibit.builder.layout'],
                        ['exhibit.builder.layout', 'exhibit.builder.publish'],
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
                    'id' => 'exhibit.engage', 'label' => 'Engage', 'sub' => 'Cart / marketplace', 'help' => 'marketplace-flow-guide',
                    'children' => [
                        ['id' => 'exhibit.engage.cart',   'label' => 'Cart',   'sub' => 'Add to cart', 'help' => 'marketplace-flow-guide'],
                        ['id' => 'exhibit.engage.repro',  'label' => 'Reproduction','sub' => 'Order copies'],
                    ],
                ],
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
                [
                    'id' => 'ai.ner', 'label' => 'NER', 'sub' => 'Entity extraction', 'help' => 'ner-user-guide',
                    'children' => [
                        ['id' => 'ai.ner.gateway',    'label' => 'Gateway',          'sub' => 'ai.theahg.co.za'],
                        ['id' => 'ai.ner.models',     'label' => 'Models',           'sub' => 'Ollama / vLLM'],
                        ['id' => 'ai.ner.provenance', 'label' => 'Provenance logged','sub' => 'Inference recorded', 'help' => 'ai-inference-provenance-user-guide'],
                    ],
                    'child_edges' => [
                        ['ai.ner.gateway', 'ai.ner.models'],
                        ['ai.ner.models', 'ai.ner.provenance'],
                    ],
                ],
                [
                    'id' => 'ai.condition', 'label' => 'AI condition', 'sub' => 'Condition assessment', 'help' => 'ai-condition-user-guide',
                    'children' => [
                        ['id' => 'ai.condition.gateway', 'label' => 'Gateway',         'sub' => 'ai.theahg.co.za'],
                        ['id' => 'ai.condition.vision',  'label' => 'Vision model',    'sub' => 'Image assessment'],
                        ['id' => 'ai.condition.report',  'label' => 'Condition report','sub' => 'Feeds Description',  'help' => 'condition-reports'],
                    ],
                    'child_edges' => [
                        ['ai.condition.gateway', 'ai.condition.vision'],
                        ['ai.condition.vision', 'ai.condition.report'],
                    ],
                ],
                [
                    'id' => 'ai.translate', 'label' => 'Translate', 'sub' => 'Machine translation', 'help' => 'translation-user-guide',
                    'children' => [
                        ['id' => 'ai.translate.gateway', 'label' => 'Gateway',  'sub' => 'ai.theahg.co.za'],
                        ['id' => 'ai.translate.engine',  'label' => 'MT engine','sub' => 'NLLB / adapter'],
                        ['id' => 'ai.translate.review',  'label' => 'Review',   'sub' => 'Human post-edit'],
                    ],
                    'child_edges' => [
                        ['ai.translate.gateway', 'ai.translate.engine'],
                        ['ai.translate.engine', 'ai.translate.review'],
                    ],
                ],
                [
                    'id' => 'ai.dedupe', 'label' => 'Duplicates', 'sub' => 'Duplicate detection', 'help' => 'duplicate-detection-user-guide',
                    'children' => [
                        ['id' => 'ai.dedupe.candidates', 'label' => 'Candidates', 'sub' => 'Similarity match',  'help' => 'duplicate-detection-user-guide'],
                        ['id' => 'ai.dedupe.merge',      'label' => 'Merge',      'sub' => 'Resolve duplicates'],
                    ],
                    'child_edges' => [
                        ['ai.dedupe.candidates', 'ai.dedupe.merge'],
                    ],
                ],
                [
                    'id' => 'ai.provenance', 'label' => 'AI provenance', 'sub' => 'Inference provenance', 'help' => 'ai-inference-provenance-user-guide',
                    'children' => [
                        ['id' => 'ai.provenance.record', 'label' => 'Record',   'sub' => 'Model / prompt / output', 'help' => 'ai-inference-provenance-user-guide'],
                        ['id' => 'ai.provenance.trace',  'label' => 'Trace',     'sub' => 'Audit / FOIA trail'],
                        ['id' => 'ai.provenance.override','label' => 'Override', 'sub' => 'Human correction'],
                    ],
                    'child_edges' => [
                        ['ai.provenance.record', 'ai.provenance.trace'],
                    ],
                ],
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
                [
                    'id' => 'interop.rest', 'label' => 'REST / GraphQL', 'sub' => 'API v1 / v2 + GraphQL', 'help' => 'api-user-guide',
                    'children' => [
                        ['id' => 'interop.rest.v1',    'label' => 'API v1',   'sub' => 'CRUD endpoints', 'help' => 'api-user-guide'],
                        ['id' => 'interop.rest.v2',    'label' => 'API v2',   'sub' => 'REST resources'],
                        ['id' => 'interop.rest.keys',  'label' => 'API keys', 'sub' => 'Key + scope auth'],
                    ],
                    'child_edges' => [
                        ['interop.rest.keys', 'interop.rest.v2'],
                    ],
                ],
                [
                    'id' => 'interop.iiif', 'label' => 'IIIF API', 'sub' => 'Presentation + Image', 'help' => 'iiif-integration-user-guide',
                    'children' => [
                        ['id' => 'interop.iiif.presentation', 'label' => 'Presentation', 'sub' => 'Manifests / collections', 'help' => 'iiif-integration-user-guide'],
                        ['id' => 'interop.iiif.image',        'label' => 'Image',        'sub' => 'Cantaloupe tiles'],
                    ],
                ],
                [
                    'id' => 'interop.ld', 'label' => 'Linked Data', 'sub' => 'RiC JSON-LD / SPARQL', 'help' => 'ric-user-guide',
                    'children' => [
                        ['id' => 'interop.ld.jsonld', 'label' => 'JSON-LD', 'sub' => 'RiC-O serialization', 'help' => 'ric-user-guide'],
                        ['id' => 'interop.ld.sparql', 'label' => 'SPARQL',  'sub' => 'Graph query'],
                    ],
                ],
                [
                    'id' => 'interop.oai', 'label' => 'OAI-PMH', 'sub' => 'Metadata harvesting',
                    'children' => [
                        ['id' => 'interop.oai.formats', 'label' => 'Formats', 'sub' => 'Dublin Core / EAD'],
                        ['id' => 'interop.oai.sets',    'label' => 'Sets',    'sub' => 'Selective harvesting'],
                    ],
                ],
                [
                    'id' => 'interop.c2pa', 'label' => 'C2PA', 'sub' => 'Content credentials',
                    'children' => [
                        ['id' => 'interop.c2pa.manifest', 'label' => 'Manifest', 'sub' => 'Provenance assertions'],
                        ['id' => 'interop.c2pa.sign',     'label' => 'Sign',     'sub' => 'Cryptographic seal'],
                    ],
                ],
                [
                    'id' => 'interop.doi', 'label' => 'DOI', 'sub' => 'Persistent identifiers', 'help' => 'doi-user-guide',
                    'children' => [
                        ['id' => 'interop.doi.mint',     'label' => 'Mint',     'sub' => 'Register DOI', 'help' => 'doi-user-guide'],
                        ['id' => 'interop.doi.metadata', 'label' => 'Metadata', 'sub' => 'DataCite record'],
                    ],
                    'child_edges' => [
                        ['interop.doi.metadata', 'interop.doi.mint'],
                    ],
                ],
                [
                    'id' => 'interop.federation', 'label' => 'Federation', 'sub' => 'Federated search', 'help' => 'federation-user-guide',
                    'children' => [
                        ['id' => 'interop.federation.peers', 'label' => 'Peers',  'sub' => 'Connected sources', 'help' => 'federation-user-guide'],
                        ['id' => 'interop.federation.merge', 'label' => 'Merge',  'sub' => 'Unified results'],
                    ],
                ],
            ],
            'child_edges' => [
                ['interop.rest', 'interop.oai'],
                ['interop.iiif', 'interop.ld'],
            ],
        ],

        // -----------------------------------------------------------------
        // North Star: the vision Heratio builds toward. Update as moonshot
        // slices ship - each child is a north-star, its detail nodes the
        // shipped pieces (linking in-app help where it exists).
        // -----------------------------------------------------------------
        [
            'id' => 'northstar', 'label' => 'North Star', 'sub' => 'The vision - where Heratio is heading', 'color' => '#5319e7',
            'children' => [
                ['id' => 'northstar.graph', 'label' => 'World heritage graph', 'sub' => 'Open memory protocol', 'help' => 'open-data-graph-api',
                    'children' => [
                        ['id' => 'northstar.graph.api', 'label' => 'Graph API', 'sub' => 'JSON-LD / Turtle / RDF', 'help' => 'open-data-graph-api'],
                        ['id' => 'northstar.graph.harvest', 'label' => 'OAI-PMH + sitemap', 'sub' => 'Harvestable', 'help' => 'open-data-graph-sitemap-oai-pmh-user-guide'],
                        ['id' => 'northstar.graph.dumps', 'label' => 'Bulk data', 'sub' => 'CSV / JSON-LD dumps'],
                    ],
                ],
                ['id' => 'northstar.loss', 'label' => 'Race against loss', 'sub' => 'Capture what is at risk', 'help' => 'race-against-loss-user-guide',
                    'children' => [
                        ['id' => 'northstar.loss.register', 'label' => 'At-risk register', 'sub' => 'Prioritised', 'help' => 'race-against-loss-user-guide'],
                        ['id' => 'northstar.loss.queue', 'label' => 'Capture queue', 'sub' => 'Operator workflow'],
                    ],
                ],
                ['id' => 'northstar.reconstruct', 'label' => 'Reconstruct lost places', 'sub' => 'Walk what is gone', 'help' => 'reconstruction-montage-user-guide',
                    'children' => [
                        ['id' => 'northstar.reconstruct.montage', 'label' => 'Rebuild montage', 'sub' => 'Assembly / time-lapse', 'help' => 'reconstruction-montage-user-guide'],
                        ['id' => 'northstar.reconstruct.twin', 'label' => 'Walkable twin', 'sub' => '3D walkthrough'],
                    ],
                ],
                ['id' => 'northstar.repatriation', 'label' => 'Repatriation engine', 'sub' => 'Trace + virtual return', 'help' => 'displaced-heritage-register-user-guide',
                    'children' => [
                        ['id' => 'northstar.repatriation.register', 'label' => 'Displaced register', 'sub' => 'Origin vs holder', 'help' => 'displaced-heritage-register-user-guide'],
                        ['id' => 'northstar.repatriation.return', 'label' => 'Virtual return', 'sub' => 'Experience it back'],
                    ],
                ],
                ['id' => 'northstar.truth', 'label' => 'Truth anchor', 'sub' => 'Verifiable authenticity', 'help' => 'content-credentials-authenticity-user-guide',
                    'children' => [
                        ['id' => 'northstar.truth.verify', 'label' => 'Verify', 'sub' => 'Content credentials', 'help' => 'content-credentials-authenticity-user-guide'],
                        ['id' => 'northstar.truth.trace', 'label' => 'Provenance trace', 'sub' => 'Full chain'],
                        ['id' => 'northstar.truth.coverage', 'label' => 'Coverage', 'sub' => 'How much is signed'],
                    ],
                ],
                ['id' => 'northstar.talk', 'label' => 'Talk to the culture', 'sub' => 'Corpus-grounded history', 'help' => 'ask-the-collection-user-guide',
                    'children' => [
                        ['id' => 'northstar.talk.ask', 'label' => 'Ask the collection', 'sub' => 'Grounded answers', 'help' => 'ask-the-collection-user-guide'],
                    ],
                ],
                ['id' => 'northstar.scholarship', 'label' => 'Generative scholarship', 'sub' => 'AI finds connections', 'help' => 'discoveries-user-guide',
                    'children' => [
                        ['id' => 'northstar.scholarship.discoveries', 'label' => 'Discoveries', 'sub' => 'Cross-collection links', 'help' => 'discoveries-user-guide'],
                    ],
                ],
                ['id' => 'northstar.access', 'label' => 'Universal access', 'sub' => 'Every museum, every language', 'help' => 'read-record-in-your-language-user-guide',
                    'children' => [
                        ['id' => 'northstar.access.translate', 'label' => 'Read in your language', 'sub' => 'On-demand translation', 'help' => 'read-record-in-your-language-user-guide'],
                        ['id' => 'northstar.access.pref', 'label' => 'Language preference', 'sub' => 'Remembered'],
                    ],
                ],
                ['id' => 'northstar.museum', 'label' => 'Encyclopedic museum', 'sub' => 'Building-scale twin', 'help' => 'exhibition-wayfinding-user-guide',
                    'children' => [
                        ['id' => 'northstar.museum.wayfinding', 'label' => 'Wayfinding', 'sub' => 'Take me to X', 'help' => 'exhibition-wayfinding-user-guide'],
                        ['id' => 'northstar.museum.scale', 'label' => 'Building scale', 'sub' => 'Wings / floors (next)'],
                    ],
                ],
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
        ['display', 'northstar'],   // everything we build feeds the vision
    ],
];
