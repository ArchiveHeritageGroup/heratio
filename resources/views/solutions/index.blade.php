@extends('theme::layouts.1col')

@section('title', __('Solutions').' - Heratio')

@section('title-block')
  <h1>{{ __('Solutions') }}</h1>
  <p class="lead mb-3">{{ __('Open-source GLAM and archival management software') }}</p>
@endsection

@section('content')
  <div class="row g-3">
    @foreach($pages as $slug => [$title, $summary])
      <div class="col-md-6 col-lg-4">
        <a href="{{ route('solutions.show', $slug) }}" class="card h-100 text-decoration-none">
          <div class="card-body">
            <h2 class="h5">{{ __($title) }}</h2>
            <p class="mb-0 text-body">{{ __($summary) }}</p>
          </div>
        </a>
      </div>
    @endforeach
  </div>
@endsection
