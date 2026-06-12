{{--
  Cite this record - clean 404 (CitationController::show).

  Shown when the requested record is unknown, unpublished, or the synthetic
  root - never a 500, never a leak of a draft. Themed (Bootstrap 5).

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.

  Extends the public 1-column theme layout.
--}}
@extends('theme::layouts.1col')
@section('title', __('Record not found'))

@section('content')
<div class="container py-5 text-center" style="max-width:560px">
  <i class="fas fa-quote-right fa-2x text-muted mb-3"></i>
  <h1 class="h4 mb-2">{{ __('Nothing to cite') }}</h1>
  <p class="text-muted mb-4">
    {{ __('No published record matches that identifier, so there is no citation to build.') }}
  </p>
  <a href="{{ url('/glam/browse') }}" class="btn btn-outline-secondary">
    <i class="fas fa-search me-1"></i>{{ __('Browse the collection') }}
  </a>
</div>
@endsection
