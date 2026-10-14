@php
  $capabilities = [
    ['Object cataloguing', 'Catalogue objects on CCO fields, with completeness scoring, a quality dashboard and Getty vocabulary links.', 'CCO, Getty AAT'],
    ['Artworks and artists', 'A guided form for artworks and artists, with valuations, venues and facility reports.', null],
    ['Condition reporting', 'Condition checks with photos you can annotate, templates, exports and a risk view.', null],
    ['Loans in and out', 'Loan agreements with object lists, status changes, extensions, returns, documents and touring clash checks.', null],
    ['Exhibitions', 'Plan exhibitions with object lists and storylines, build the space, and publish a 3D walk-through.', null],
    ['3D models', 'Upload and view glb, gltf, obj, stl, ply and usdz, with hotspots, measuring, cross-sections and embed codes.', 'IIIF 3D manifests'],
    ['Deep zoom and IIIF', 'Deep-zoom viewing, IIIF manifests and curated collections, and a compare view. Image tiles need a Cantaloupe image server.', 'IIIF Presentation 3, Image API 3'],
    ['Provenance', 'The ownership and custody chain of each object, with events and documents.', null],
    ['Linked data export', 'Export objects and people as CIDOC CRM.', 'CIDOC CRM (Turtle, RDF/XML)'],
    ['Labels', 'Object and container labels with barcodes, printed in batches.', null],
    ['Archaeology', 'Sites, contexts and finds, with a stratigraphic Harris matrix you can export.', 'Harris matrix (DOT, CSV)'],
    ['Indigenous cultural and intellectual property', 'A community consent register, consultations and Traditional Knowledge labels.', 'Local Contexts TK labels'],
    ['Art-bank requests', 'Staff request artworks, check availability, track placements and turn a request into a loan.', null],
    ['Heritage asset accounting', 'An asset register with valuations against the accounting standard you choose. The standards are pluggable market modules.', 'GRAP 103, FRS 102, GASB 34, IPSAS'],
  ];
  $demoLinks = [
    ['Walk through a 3D exhibition', route('exhibition-space.walkthrough', ['slug' => 'benson-collection'])],
    ['The Crystal Palace, reconstructed', route('exhibition-space.walkthrough', ['slug' => 'the-crystal-palace-reconstruction'])],
    ['Museum collection', url('/museum/browse')],
    ['Gallery collection', url('/gallery/browse')],
    ['IIIF collections', url('/manifest-collections')],
  ];
@endphp
@extends('solutions._layout')

@section('intro')
  <p>{{ __('Heratio catalogues museum and gallery collections to CCO, then carries each object through the work that happens around it: condition checks, loans, provenance research, exhibitions and accounting for the collection as an asset.') }}</p>
  <p>{{ __('Objects can be shown the way visitors meet them, in deep zoom, as 3D models, or hung in a 3D exhibition space that anyone can walk through in a browser. The live demo has two such spaces, one of them a building that no longer exists.') }}</p>
@endsection
