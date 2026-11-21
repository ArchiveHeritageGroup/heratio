{{-- Search by photo results (heratio#1764). Staff only. --}}
@extends('theme::layouts.1col')
@section('title', __('Search by photo'))

@section('content')
<h1 class="h3">{{ __('Search by photo') }}</h1>
@if($detected === 0)
  <div class="alert alert-warning">{{ __('No face was found in the photo, or the face service is not available.') }}</div>
@elseif(count($hits) === 0)
  <p class="text-muted">{{ __('No stored faces with a similarity of :t or more.', ['t' => number_format($threshold, 2)]) }}</p>
@else
  <p class="text-muted small">{{ __('Ranked by similarity. These are candidates for a person to check, not identifications.') }}</p>
  @include('ahg-core::faces._hits', ['hits' => $hits, 'confirmFor' => null])
@endif
<p><a href="{{ route('faces.index') }}">{{ __('Back to face recognition') }}</a></p>
@endsection
