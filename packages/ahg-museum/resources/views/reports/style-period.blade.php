{{-- Style & Period Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Style & Period Report')
@section('body-class', 'museum-reports style-period')
@section('sidebar')<div class="sidebar-content"><a href="{{ route('museum.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back') }}</a></div>@endsection
@section('title-block')<h1><i class="fas fa-theater-masks me-2"></i>Style & Period Report</h1>@endsection
@section('content')
<div class="row">
  <div class="col-md-6 mb-4">
    <div class="card">
      <div class="card-header bg-dark text-white">By Style</div>
      <ul class="list-group list-group-flush">
        @forelse($byStyle as $s)
        <li class="list-group-item d-flex justify-content-between">{{ e($s->style ?? '') }} <span class="badge bg-primary">{{ $s->count ?? 0 }}</span></li>
        @empty
        <li class="list-group-item text-muted">No styles recorded</li>
        @endforelse
      </ul>
    </div>
  </div>
  <div class="col-md-6 mb-4">
    <div class="card">
      <div class="card-header bg-dark text-white">By Period</div>
      <ul class="list-group list-group-flush">
        @forelse($byPeriod as $p)
        <li class="list-group-item d-flex justify-content-between">{{ e($p->period ?? '') }} <span class="badge bg-primary">{{ $p->count ?? 0 }}</span></li>
        @empty
        <li class="list-group-item text-muted">No periods recorded</li>
        @endforelse
      </ul>
    </div>
  </div>
</div>
@endsection
