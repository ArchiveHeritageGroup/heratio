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
  @if(\App\Support\DemoTour::enabled())
    <p class="mt-3 mb-1"><a href="{{ route('solutions.tour') }}">{{ __('Take the guided tour of the live demo') }}</a></p>
  @endif
  <p class="mt-1 mb-1"><a href="{{ route('solutions.documentation') }}">{{ __('Install, upgrade and security documentation for this release') }}</a></p>
  <p class="mt-1"><a href="{{ route('solutions.capabilities') }}">{{ __('Every capability, with its status: released, preview or planned') }}</a></p>
@endsection
