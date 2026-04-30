{{-- Condition Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Condition Report')
@section('body-class', 'museum-reports condition')
@section('sidebar')<div class="sidebar-content"><a href="{{ route('museum.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a></div>@endsection
@section('title-block')<h1><i class="fas fa-heartbeat me-2"></i>Condition Report</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($records) }}</strong> condition records found</div>
<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark"><tr><th>{{ __('Object') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Date') }}</th><th>{{ __('Treatment') }}</th><th>{{ __('Notes') }}</th></tr></thead>
    <tbody>
      @forelse($records as $r)
      <tr><td><strong>{{ e($r->title ?? '-') }}</strong></td><td><span class="badge bg-{{ in_array($r->condition_term ?? '', ['poor','critical']) ? 'danger' : 'success' }}">{{ ucfirst($r->condition_term ?? '-') }}</span></td><td>{{ $r->condition_date ? date('d M Y', strtotime($r->condition_date)) : '-' }}</td><td>{{ e($r->treatment ?? '-') }}</td><td>{{ Str::limit($r->condition_notes ?? '-', 60) }}</td></tr>
      @empty
      <tr><td colspan="5" class="text-muted text-center py-4">No condition records found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
