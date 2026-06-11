{{--
  Graph Explorer - 404 (north-star #1204, next slice).

  A calm, themed not-found page for an unknown entity type, or an unknown /
  unpublished / mistyped slug. It deliberately does not say WHY (unknown vs
  draft) so an unpublished record is never disclosed. Read-only; never a 500.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')
@section('title', __('Not found') . ' - ' . __('Graph explorer'))

@section('content')
<div class="container py-5 text-center" style="max-width:640px">
  <i class="fas fa-compass fa-3x text-muted mb-3"></i>
  <h1 class="h3 mb-2">{{ __('That entity could not be found') }}</h1>
  <p class="text-muted mb-4">
    {{ __('No published record, agent or concept matches that address. It may have moved, or it may not be publicly available.') }}
  </p>
  <a href="{{ url('/graph-explorer') }}" class="btn btn-primary">
    <i class="fas fa-project-diagram me-1"></i>{{ __('Back to the graph explorer') }}
  </a>
</div>
@endsection
