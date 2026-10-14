@php
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
