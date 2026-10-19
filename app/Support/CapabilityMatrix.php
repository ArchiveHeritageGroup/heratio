<?php

/**
 * CapabilityMatrix - the single list of what Heratio can do, with a status for
 * each capability (H04). The capability page shows all of it with badges; the
 * solution pages (H03) list only RELEASED rows for their sector, so a feature
 * cannot be advertised on a buyer page while it is still in preview.
 *
 * Status meanings:
 *   released  in the current tagged release and working on the main path
 *   preview   in the code but partial, off by default, command-line only, or
 *             dependent on a service a fresh install does not have
 *   planned   not working yet; the proof link is the GitHub issue tracking it
 *
 * Every row carries a proof link: a live demo page, a help article or, for
 * planned work, the issue. Checked against the code and heratio.org on
 * 14 October 2026; update a row when its status changes.
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

namespace App\Support;

class CapabilityMatrix
{
    public const RELEASED = 'released';

    public const PREVIEW = 'preview';

    public const PLANNED = 'planned';

    private const ISSUES = 'https://github.com/ArchiveHeritageGroup/heratio/issues/';

    /**
     * Each row: [pages, name, what it gives you, standards|null, status, proof path or URL, note|null].
     * A proof path starting with "/" is on this site; anything else is absolute.
     *
     * @return list<array{0: list<string>, 1: string, 2: string, 3: ?string, 4: string, 5: string, 6: ?string}>
     */
    public static function rows(): array
    {
        $help = fn (string $slug) => '/help/article/'.$slug;

        return [
            // Archives
            [['archives'], 'Multi-level description', 'Describe from fonds down to item, with the hierarchy kept as you arrange and rearrange.', 'ISAD(G)', self::RELEASED, '/glam/browse', null],
            [['archives'], 'Authority records', 'People, families and organisations, with duplicate detection, merging and name suggestions drawn from your text.', 'ISAAR(CPF)', self::RELEASED, '/actor/browse', null],
            [['archives'], 'Repository records', 'Describe the institutions that hold the material.', 'ISDIAH', self::RELEASED, '/repository/browse', null],
            [['archives'], 'Alternative description views', 'Work on the same record through another standard\'s field set.', 'DACS, RAD, Dublin Core, MODS', self::RELEASED, $help('mods-manage-user-guide'), null],
            [['archives'], 'EAD import and export', 'Bring descriptions in from EAD with a preview before you commit, and download any description as EAD.', 'EAD 2002, EAD3', self::RELEASED, $help('ead-import-and-finding-aid'), null],
            [['archives'], 'Finding aids', 'Generate a finding aid for a collection as EAD or PDF.', null, self::RELEASED, $help('finding-aid-generation'), null],
            [['archives'], 'Accessions and donors', 'Register accessions with structured extent, custody and transfer events, and link them to donors.', 'CAAIS 1.0', self::RELEASED, $help('accession-manage-user-guide'), null],
            [['archives'], 'Storage locations', 'A location tree with nested containers, a movement log, bulk moves and barcode labels.', null, self::RELEASED, $help('label-user-guide'), null],
            [['archives'], 'Records management', 'Retention schedules and classes, approvals, a disposal queue and a disposal history.', null, self::RELEASED, $help('records-manage-user-guide'), null],
            [['archives'], 'Records in Contexts', 'RiC-O output for every record, a RiC API and a RiC editing view.', 'RiC-O', self::RELEASED, $help('ahgricexplorerplugin'), null],
            [['archives'], 'Harvesting', 'Aggregators can harvest your records in six formats.', 'OAI-PMH: Dublin Core, EAD, EAD3, MODS, MARCXML, RiC-O', self::RELEASED, '/oai?verb=ListMetadataFormats', null],
            [['archives'], 'Preservation', 'Scheduled fixity checks, PREMIS events, a format registry, integrity alerts and SIP, AIP and DIP packages.', 'PREMIS, METS, BagIt', self::RELEASED, $help('preservation-user-guide'), null],
            [['archives'], 'Reading room and research portal', 'Researcher registration, reading-room bookings with check-in and check-out, seats and equipment.', null, self::RELEASED, '/research/publicRegister', null],
            [['archives'], 'Access requests', 'Users ask for access to restricted material and staff grant clearances.', null, self::RELEASED, $help('access-request-user-guide'), null],
            [['archives'], 'Archivematica', 'Send material to Archivematica and bring the DIP back.', null, self::RELEASED, $help('archivematica-integration'), 'Needs your Archivematica server.'],
            [['archives'], 'Persistent identifiers', 'Mint DOIs one at a time or in batches and keep them in step with your records.', 'DataCite DOI', self::RELEASED, $help('doi-manage-user-guide'), 'Needs DataCite credentials.'],
            [['archives'], 'OCFL storage', 'Store objects in an OCFL repository layout.', 'OCFL', self::PREVIEW, $help('ocfl-storage'), 'Command line only; not set up by default.'],
            [['archives'], 'EAC-CPF export', 'Authority records as EAC-CPF XML.', 'EAC-CPF', self::PLANNED, self::ISSUES.'1551', null],

            // Museums and galleries
            [['museums'], 'Object cataloguing', 'Catalogue objects on CCO fields, with completeness scoring, a quality dashboard and Getty vocabulary links.', 'CCO, Getty AAT', self::RELEASED, '/museum/browse', null],
            [['museums'], 'Artworks and artists', 'A guided form for artworks and artists, with valuations, venues and facility reports.', null, self::RELEASED, '/gallery/browse', null],
            [['museums'], 'Condition reporting', 'Condition checks with photos you can annotate, templates, exports and a risk view.', null, self::RELEASED, $help('condition-user-guide'), null],
            [['museums'], 'Loans in and out', 'Loan agreements with object lists, status changes, extensions, returns, documents and touring clash checks.', null, self::RELEASED, $help('loan-user-guide'), null],
            [['museums'], 'Exhibitions', 'Plan exhibitions with object lists and storylines, build the space, and publish a 3D walk-through.', null, self::RELEASED, '/exhibition-space/benson-collection/walkthrough', null],
            [['museums'], '3D models', 'Upload and view glb, gltf, obj, stl, ply and usdz, with hotspots, measuring, cross-sections and embed codes.', 'IIIF 3D manifests', self::RELEASED, $help('3d-model-user-guide'), null],
            [['museums'], 'Deep zoom and IIIF', 'Deep-zoom viewing, IIIF manifests and curated collections, and a compare view.', 'IIIF Presentation 3, Image API 3', self::RELEASED, '/manifest-collections', 'Image tiles need a Cantaloupe image server.'],
            [['museums'], 'Provenance', 'The ownership and custody chain of each object, with events and documents.', null, self::RELEASED, $help('provenance-user-guide'), null],
            [['museums'], 'Linked data export', 'Export objects and people as CIDOC CRM.', 'CIDOC CRM (Turtle, RDF/XML)', self::RELEASED, $help('cidoc-crm-export'), null],
            [['museums'], 'Labels', 'Object and container labels with barcodes, printed in batches.', null, self::RELEASED, $help('label-printing-user-guide'), null],
            [['museums'], 'Archaeology', 'Sites, contexts and finds, with a stratigraphic Harris matrix you can export.', 'Harris matrix (DOT, CSV)', self::RELEASED, $help('harris-matrix-user-guide'), null],
            [['museums'], 'Indigenous cultural and intellectual property', 'A community consent register, consultations and Traditional Knowledge labels.', 'Local Contexts TK labels', self::RELEASED, $help('icip-user-guide'), null],
            [['museums'], 'Art-bank requests', 'Staff request artworks, check availability, track placements and turn a request into a loan.', null, self::RELEASED, $help('artwork-request-user-guide'), null],
            [['museums'], 'Heritage asset accounting', 'An asset register with valuations against the accounting standard you choose. The standards are pluggable market modules.', 'GRAP 103, FRS 102, GASB 34, IPSAS', self::RELEASED, $help('heritage-accounting-user-guide'), null],
            [['museums'], 'Collections procedures', 'The primary museum collections procedures (UK Collections Trust), with states, tasks and evidence.', null, self::PREVIEW, $help('ahgspectrumplugin'), 'Off by default; seven procedures have their own screens so far.'],
            [['museums'], 'LIDO export', 'Museum object records as LIDO XML.', 'LIDO', self::PREVIEW, $help('museum-user-guide'), 'Command line only.'],
            [['museums'], 'Image to 3D', 'Generate a 3D model from photographs.', null, self::PREVIEW, $help('3d-model-user-guide'), 'Off by default; needs a GPU service.'],
            [['museums'], 'Repatriation claims', 'A register of displaced heritage and a repatriation claim workflow.', null, self::PREVIEW, $help('displaced-heritage-register-user-guide'), 'First slice of the workflow.'],
            [['museums'], 'Shop payments', 'Listings, auctions and checkout for reproductions and items.', null, self::PREVIEW, '/marketplace/browse', 'No live payment gateway yet.'],

            // Libraries
            [['libraries'], 'Cataloguing', 'Catalogue books and other items with authority control.', null, self::RELEASED, '/library/browse', null],
            [['libraries'], 'MARC21', 'Import MARCXML or binary MARC with a preview, then validate, merge, edit and download.', 'MARC21 (MARCXML, ISO 2709)', self::RELEASED, $help('marc21-import'), null],
            [['libraries'], 'Copy cataloguing', 'Search remote catalogues and import the record you need.', 'Z39.50', self::RELEASED, $help('z3950-user-guide'), null],
            [['libraries'], 'Catalogue search for other systems', 'Other library systems can query your catalogue.', 'SRU', self::RELEASED, '/sru?operation=explain', null],
            [['libraries'], 'ISBN lookup', 'Look up an ISBN, pre-fill the record and fetch the cover.', null, self::RELEASED, $help('library-user-guide'), null],
            [['libraries'], 'Circulation', 'Check out, return and renew at a scan desk, with loan rules, holds, overdues and fines, across branches.', null, self::RELEASED, $help('library-complete-guide'), null],
            [['libraries'], 'Patron accounts', 'Staff manage patrons; patrons see their loans, holds and fines and renew online.', null, self::RELEASED, $help('library-complete-guide'), null],
            [['libraries'], 'Acquisitions', 'Orders with a status workflow, fund splits, receiving, budgets and vendors, with EDI trading and ONIX ingest.', 'EDIFACT, ONIX', self::RELEASED, $help('library-acquisitions-user-guide'), null],
            [['libraries'], 'Serials', 'Subscriptions, issue check-in and prediction, claims and bindery.', null, self::RELEASED, $help('library-serials-user-guide'), null],
            [['libraries'], 'Interlibrary loan', 'Requests with a status workflow, EDI sending and a request form for patrons.', null, self::RELEASED, $help('library-complete-guide'), null],
            [['libraries'], 'E-resources', 'Harvest and report usage, serve usage data, manage holdings and resolve links.', 'COUNTER R5, SUSHI, KBART, OpenURL', self::RELEASED, $help('counter-sushi-user-guide'), null],
            [['libraries'], 'Linked bibliographic data', 'View, import, export and validate BIBFRAME, with a graph editor.', 'BIBFRAME (RDF/XML)', self::RELEASED, '/bibframe', null],
            [['libraries'], 'Work clustering', 'Group manifestations into works, with overrides when the grouping is wrong.', 'FRBR / LRM', self::RELEASED, '/frbr', null],
            [['libraries'], 'Research data', 'A dataset register with deposit, a privacy scan, data management plan links and a landing page per dataset.', null, self::RELEASED, $help('open-data-schema-org-dataset-user-guide'), null],
            [['libraries'], 'Public catalogue (OPAC)', 'Public search, item pages, holds and renewals for patrons.', null, self::PLANNED, self::ISSUES.'1554', 'In the code, but not reachable on this demo yet.'],

            // Shared across every sector
            [['integrated-glam'], 'One catalogue', 'Archive, library, museum, gallery and digital asset records in one database, with one browse, print and CSV export.', null, self::RELEASED, '/glam/browse', null],
            [['integrated-glam'], 'Search', 'Keyword, advanced and autocomplete search across every kind of record.', null, self::RELEASED, '/search', null],
            [['integrated-glam'], 'Digital asset management', 'Assets with IPTC metadata, bulk creation and reports.', 'IPTC', self::RELEASED, '/dam', null],
            [['integrated-glam'], 'Batch ingest', 'Configure, upload, map, validate, preview and commit, with CSV templates for each sector.', null, self::RELEASED, $help('ingest-user-guide'), null],
            [['integrated-glam'], 'Scanning into the catalogue', 'Drop scans into a watched folder and they are ingested automatically.', null, self::RELEASED, $help('digital-object-ingest-end-to-end'), null],
            [['integrated-glam'], 'IIIF', 'Manifests for records, collections, exhibitions and 3D models, shown in Mirador, Universal Viewer or OpenSeadragon.', 'IIIF Presentation 3, Image API 3', self::RELEASED, $help('iiif-integration-user-guide'), 'Deep-zoom tiles need a Cantaloupe image server.'],
            [['integrated-glam'], 'Open APIs', 'A public read API, an authenticated API with full create and update, harvesting and change notification.', 'REST, OAI-PMH, ResourceSync', self::RELEASED, '/api/v1/informationobjects', null],
            [['integrated-glam'], 'Rights and access', 'Rights statements and licences, embargoes, and access policies enforced when records are viewed or reproduced.', 'ODRL, RightsStatements.org, Creative Commons', self::RELEASED, $help('extended-rights-user-guide'), null],
            [['integrated-glam'], 'Privacy and data protection', 'Requests from data subjects, impact assessments, a processing register, scanning for personal data and redaction. The rules for each jurisdiction are pluggable modules.', 'GDPR, POPIA, NDPA rule sets', self::RELEASED, $help('privacy-user-guide'), null],
            [['integrated-glam'], 'Encryption', 'Restricted digital objects stored encrypted and decrypted only for authorised users.', 'AES-256-GCM', self::RELEASED, $help('encryption-user-guide'), null],
            [['integrated-glam'], 'Content credentials', 'Signed authenticity reports and verification pages for digital objects.', 'C2PA', self::RELEASED, $help('content-credentials-explainer'), null],
            [['integrated-glam'], 'Audit and versions', 'Who changed what and when, record versions you can compare and restore, and task queues.', null, self::RELEASED, $help('audit-trail-user-guide'), null],
            [['integrated-glam'], 'Controlled lists and custom fields', 'Edit every dropdown, and add your own fields to any form, carried through to exports.', null, self::RELEASED, $help('custom-fields-user-guide'), null],
            [['integrated-glam'], 'Housekeeping', 'Merge duplicates, scheduled backups, expiring share links and offline portable exports.', null, self::RELEASED, $help('backup-user-guide'), null],
            [['integrated-glam'], 'Reports', 'A report builder, data-quality dashboards, usage statistics and a sitemap.', null, self::RELEASED, $help('ahgreportbuilderplugin'), null],
            [['integrated-glam'], 'Deployment', 'Install over an existing AtoM database, as a fresh install, from a Docker image, or on a provisioned virtual machine.', 'Docker (amd64, arm64)', self::RELEASED, 'https://github.com/ArchiveHeritageGroup/heratio#installation', null],
            [['integrated-glam'], 'AI assistance', 'Summaries, translation, named-entity extraction with human review, handwriting recognition, condition assessment and a catalogue chatbot.', null, self::PREVIEW, $help('ai-services-user-guide'), 'Works once you connect a language model; none ships by default.'],
            [['integrated-glam'], 'Semantic search', 'Search by meaning, blended with keyword results.', null, self::PREVIEW, $help('ahgsemanticsearchplugin'), 'Needs a vector database and an embedding model.'],
            [['integrated-glam'], 'GraphQL', 'Query the catalogue in GraphQL.', 'GraphQL', self::PREVIEW, $help('graphql-user-guide'), 'Staff only and read only.'],
            [['integrated-glam'], 'Multiple institutions on one install', 'Run several institutions on one installation, or federate separate installations.', null, self::PREVIEW, $help('multi-tenant-user-guide'), 'Off by default.'],
            [['integrated-glam'], 'Embedded content credentials', 'C2PA credentials written into the image files themselves.', 'C2PA', self::PREVIEW, $help('c2pa-provenance'), 'Needs c2patool on the server.'],
            [['integrated-glam'], 'Single sign-on', 'Sign in through your organisation\'s identity provider.', 'OIDC, SAML, LDAP', self::PLANNED, self::ISSUES.'1531', null],

            // Migrating from AtoM
            [['migrating-from-atom'], 'Overlay onto your AtoM database', 'Heratio runs on top of the existing AtoM database. The installer works in eight repeatable stages and has a dry run, so you can see every change before it is made.', null, self::RELEASED, $help('install-and-atom-db-onboarding'), null],
            [['migrating-from-atom'], 'Nothing deleted', 'No AtoM row is deleted or rewritten. New tables and columns are added alongside, and your settings survive. To go back, point the web server at AtoM again.', null, self::RELEASED, $help('install-and-atom-db-onboarding'), null],
            [['migrating-from-atom'], 'Same records, same addresses', 'Descriptions, authority records, terms, repositories, accessions and their web addresses are read from the AtoM tables directly.', null, self::RELEASED, '/glam/browse', null],
            [['migrating-from-atom'], 'Users keep their passwords', 'Heratio checks AtoM\'s password scheme itself, so staff sign in with the credentials they already have.', null, self::RELEASED, $help('install-and-atom-db-onboarding'), null],
            [['migrating-from-atom'], 'Digital objects stay put', 'Existing uploads keep working where they are; new uploads can go to a new storage location.', null, self::RELEASED, $help('install-and-atom-db-onboarding'), null],
            [['migrating-from-atom'], 'One-command onboarding', 'Builds the derived layers in order: database updates, hierarchy tables, the full-text index, sector classification and browse facets.', null, self::RELEASED, $help('install-and-atom-db-onboarding'), null],
            [['migrating-from-atom'], 'CSV and EAD import', 'ISAD(G) CSV with match-and-update, per-sector CSV importers, EAD 2002 and EAD3 import, and a browser ingest wizard.', 'CSV, EAD 2002, EAD3', self::RELEASED, $help('data-ingest-user-guide'), null],
            [['migrating-from-atom'], 'Data migration tool', 'Map CSV or Excel exports from other systems in the browser and run the import.', null, self::PLANNED, self::ISSUES.'1555', 'Mapping works; running the import does not yet.'],
        ];
    }

    /** RELEASED rows for one solution page, as [name, what, standards, note]. */
    public static function releasedFor(string $page): array
    {
        return array_values(array_map(
            fn ($r) => [$r[1], $r[2], $r[3], $r[6]],
            array_filter(self::rows(), fn ($r) => $r[4] === self::RELEASED && in_array($page, $r[0], true))
        ));
    }

    /** A proof link as an absolute URL. */
    /**
     * Released capabilities with an automated test that exercises them (HER-01,
     * 16 Oct 2026): capability name => test file. Only tests whose methods cover
     * the capability's main behaviour are listed; some cover part of it (e.g. the
     * IIIF test covers audio and video canvases). Unlisted means no such test yet.
     */
    private const TESTS = [
        'Multi-level description' => 'tests/Feature/InformationObjectCrudTest.php',
        'Authority records' => 'tests/Feature/ActorCrudTest.php',
        'Alternative description views' => 'tests/Feature/ModsEditTest.php',
        'EAD import and export' => 'packages/ahg-metadata-export/tests/EadXmlImporterTest.php',
        'Accessions and donors' => 'packages/ahg-accession-manage/tests/Feature/CaaisProfileTest.php',
        'Storage locations' => 'packages/ahg-storage-manage/tests/Feature/StorageLocationTest.php',
        'Records in Contexts' => 'packages/ahg-ric/tests/Feature/RicRoundTripTest.php',
        'Archivematica' => 'packages/ahg-archivematica/tests/TransferServiceTest.php',
        'Provenance' => 'tests/Feature/MuseumProvenanceDisplayTest.php',
        'Linked data export' => 'packages/ahg-ric/tests/Unit/CrmSerializerTest.php',
        'Archaeology' => 'tests/Feature/ArchaeologyMatrixTest.php',
        'Indigenous cultural and intellectual property' => 'packages/ahg-icip/tests/Feature/LocalContextsHubServiceTest.php',
        'MARC21' => 'packages/ahg-library/tests/Unit/Marc21DecoderServiceTest.php',
        'Circulation' => 'packages/ahg-library/tests/Feature/CirculationDeskTest.php',
        'Patron accounts' => 'packages/ahg-library/tests/Unit/LibraryPatronServiceTest.php',
        'Acquisitions' => 'tests/Feature/Api/LibraryAcquisitionsApiTest.php',
        'Serials' => 'packages/ahg-library/tests/Unit/LibrarySerialServiceTest.php',
        'Interlibrary loan' => 'packages/ahg-library/tests/Feature/IllRequestTest.php',
        'E-resources' => 'packages/ahg-library/tests/Feature/SushiServerTest.php',
        'IIIF' => 'packages/ahg-iiif-collection/tests/Unit/IiifAvCanvasEmissionTest.php',
        'Open APIs' => 'tests/Feature/Api/InformationObjectApiTest.php',
        'Privacy and data protection' => 'packages/ahg-privacy/tests/Unit/PiiScanServiceTest.php',
        'Content credentials' => 'packages/ahg-c2pa/tests/Unit/ManifestBuilderTest.php',
        'Audit and versions' => 'packages/ahg-audit-trail/tests/Unit/AuditServiceTest.php',
        'Controlled lists and custom fields' => 'packages/ahg-custom-fields/tests/Feature/CustomFieldsEndToEndTest.php',
        'Housekeeping' => 'packages/ahg-dedupe/tests/Feature/RecordMergeTest.php',
        'CSV and EAD import' => 'packages/ahg-data-migration/tests/Feature/ImportReplaceTest.php',
    ];

    /** GitHub link to the test covering a capability, pinned to the running release, or null. */
    public static function testUrl(string $name): ?string
    {
        if (! isset(self::TESTS[$name])) {
            return null;
        }
        $version = json_decode((string) @file_get_contents(base_path('version.json')), true)['version'] ?? '';

        return 'https://github.com/ArchiveHeritageGroup/heratio/blob/'.($version !== '' ? 'v'.$version : 'main').'/'.self::TESTS[$name];
    }

    /** @return array<string, string> capability name => test file, for tests and audits. */
    public static function tests(): array
    {
        return self::TESTS;
    }

    public static function proofUrl(string $proof): string
    {
        return str_starts_with($proof, '/') ? url($proof) : $proof;
    }
}
