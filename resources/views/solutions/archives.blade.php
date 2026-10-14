@php
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
