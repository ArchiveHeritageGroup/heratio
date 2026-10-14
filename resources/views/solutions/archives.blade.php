@php
  $capabilities = [
    ['Multi-level description', 'Describe from fonds down to item, with the hierarchy kept as you arrange and rearrange.', 'ISAD(G)'],
    ['Authority records', 'People, families and organisations, with duplicate detection, merging and name suggestions drawn from your text.', 'ISAAR(CPF)'],
    ['Repository records', 'Describe the institutions that hold the material.', 'ISDIAH'],
    ['Alternative description views', 'Work on the same record through another standard\'s field set.', 'DACS, RAD, Dublin Core, MODS'],
    ['EAD import and export', 'Bring descriptions in from EAD with a preview before you commit, and download any description as EAD.', 'EAD 2002, EAD3'],
    ['Finding aids', 'Generate a finding aid for a collection as EAD or PDF.', null],
    ['Accessions and donors', 'Register accessions with structured extent, custody and transfer events, and link them to donors.', 'CAAIS 1.0'],
    ['Storage locations', 'A location tree with containers, a movement log, bulk moves and barcode labels.', null],
    ['Records management', 'Retention schedules and classes, approvals, a disposal queue and a disposal history.', null],
    ['Records in Contexts', 'RiC-O output for every record, a RiC API and a RiC editing view.', 'RiC-O'],
    ['Harvesting', 'Aggregators can harvest your records in six formats.', 'OAI-PMH: Dublin Core, EAD, EAD3, MODS, MARCXML, RiC-O'],
    ['Preservation', 'Scheduled fixity checks, PREMIS events, a format registry, integrity alerts and SIP, AIP and DIP packages.', 'PREMIS, METS, BagIt'],
    ['Reading room and research portal', 'Researcher registration, reading-room bookings with check-in and check-out, seats and equipment.', null],
    ['Access requests', 'Users ask for access to restricted material and staff grant clearances.', null],
    ['Archivematica', 'Send material to Archivematica and bring the DIP back. Needs your Archivematica server.', null],
    ['Persistent identifiers', 'Mint DOIs one at a time or in batches and keep them in step with your records. Needs DataCite credentials.', 'DataCite DOI'],
  ];
  $demoLinks = [
    ['Browse the demo collections', route('glam.browse')],
    ['Authority records', url('/actor/browse')],
    ['Repositories', url('/repository/browse')],
    ['OAI-PMH formats', url('/oai?verb=ListMetadataFormats')],
    ['Register as a researcher', url('/research/publicRegister')],
  ];
@endphp
@extends('solutions._layout')

@section('intro')
  <p>{{ __('Heratio covers the archive from accession to public access: arrangement and description, authority control, storage, retention and preservation, then a reading room and a public catalogue that aggregators can harvest.') }}</p>
  <p>{{ __('Descriptions follow the international standards archivists already work in, and the same record can be exported as EAD, RiC-O, MODS or Dublin Core without re-keying. Country-specific requirements, such as the national archives rules of a particular jurisdiction, are pluggable market modules on top of that core.') }}</p>
@endsection
