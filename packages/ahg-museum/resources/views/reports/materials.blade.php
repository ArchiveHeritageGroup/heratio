{{-- Materials Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Materials Report')
@section('body-class', 'museum-reports materials')
@section('sidebar')<div class="sidebar-content"><a href="{{ route('museum.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back') }}</a></div>@endsection
@section('title-block')<h1><i class="fas fa-layer-group me-2"></i>Materials Report</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($records) }}</strong> records with materials/techniques</div>
<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark"><tr><th>{{ __('Object') }}</th><th>{{ __('Materials') }}</th><th>{{ __('Techniques') }}</th><th>{{ __('Dimensions') }}</th></tr></thead>
    <tbody>
      @forelse($records as $r)
      <tr><td><strong>{{ e($r->title ?? '-') }}</strong></td><td>{{ e($r->materials ?? '-') }}</td><td>{{ e($r->techniques ?? '-') }}</td><td>{{ e($r->dimensions ?? '-') }}</td></tr>
      @empty
      <tr><td colspan="4" class="text-muted text-center py-4">No materials records found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
