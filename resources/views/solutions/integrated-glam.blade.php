@php
  $capabilities = [
    ['One catalogue', 'Archive, library, museum, gallery and digital asset records in one database, with one browse, print and CSV export.', null],
    ['Search', 'Keyword, advanced and autocomplete search across every kind of record.', null],
    ['Digital asset management', 'Assets with IPTC metadata, bulk creation and reports.', 'IPTC'],
    ['Batch ingest', 'Configure, upload, map, validate, preview and commit, with CSV templates for each sector.', null],
    ['Scanning into the catalogue', 'Drop scans into a watched folder and they are ingested automatically.', null],
    ['IIIF', 'Manifests for records, collections, exhibitions and 3D models, shown in Mirador, Universal Viewer or OpenSeadragon. Deep-zoom tiles need a Cantaloupe image server.', 'IIIF Presentation 3, Image API 3'],
    ['Open APIs', 'A public read API, an authenticated API with full create and update, harvesting and change notification.', 'REST, OAI-PMH, ResourceSync'],
    ['Rights and access', 'Rights statements and licences, embargoes, and access policies enforced when records are viewed or reproduced.', 'ODRL, RightsStatements.org, Creative Commons'],
    ['Privacy and data protection', 'Requests from data subjects, impact assessments, a processing register, scanning for personal data and redaction. The rules for each jurisdiction are pluggable modules.', 'GDPR, POPIA, NDPA rule sets'],
    ['Encryption', 'Restricted digital objects stored encrypted and decrypted only for authorised users.', 'AES-256-GCM'],
    ['Content credentials', 'Signed authenticity reports and verification pages for digital objects.', 'C2PA'],
    ['Audit and versions', 'Who changed what and when, record versions you can compare and restore, and task queues.', null],
    ['Controlled lists and custom fields', 'Edit every dropdown, and add your own fields to any form, carried through to exports.', null],
    ['Housekeeping', 'Merge duplicates, scheduled backups, expiring share links and offline portable exports.', null],
    ['Reports', 'A report builder, data-quality dashboards, usage statistics and a sitemap.', null],
    ['Deployment', 'Install over an existing AtoM database, as a fresh install, from a Docker image, or on a provisioned virtual machine.', 'Docker (amd64, arm64)'],
  ];
  $demoLinks = [
    ['Browse every collection together', route('glam.browse')],
    ['Search', url('/search')],
    ['Digital assets', url('/dam')],
    ['Public API', url('/api/v1/informationobjects')],
    ['ResourceSync', url('/.well-known/resourcesync')],
  ];
@endphp
@extends('solutions._layout')

@section('intro')
  <p>{{ __('Most institutions hold more than one kind of collection. A university has its archive, its library and a museum; a museum has its own archive and library. Heratio keeps all of them in one catalogue, so researchers search once and staff learn one system.') }}</p>
  <p>{{ __('Each sector keeps its own standards and workflows. What is shared is everything underneath: search, digital objects, rights, privacy, preservation, the APIs and the audit trail.') }}</p>
@endsection
