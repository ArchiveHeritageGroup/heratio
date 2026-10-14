@php
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
