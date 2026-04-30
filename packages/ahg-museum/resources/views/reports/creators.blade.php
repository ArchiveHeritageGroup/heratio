{{-- Creators Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Creators Report')
@section('body-class', 'museum-reports creators')
@section('sidebar')<div class="sidebar-content"><a href="{{ route('museum.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back to Dashboard') }}</a></div>@endsection
@section('title-block')<h1><i class="fas fa-user-edit me-2"></i>Creators Report</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($creators) }}</strong> creators found</div>
<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark"><tr><th>{{ __('Creator') }}</th><th>{{ __('Role') }}</th><th>{{ __('Attribution') }}</th><th>{{ __('School') }}</th><th class="text-end">{{ __('Objects') }}</th></tr></thead>
    <tbody>
      @forelse($creators as $c)
      <tr><td><strong>{{ e($c->creator_name ?? '-') }}</strong></td><td>{{ e($c->creator_role ?? '-') }}</td><td>{{ e($c->attribution ?? '-') }}</td><td>{{ e($c->school ?? '-') }}</td><td class="text-end"><span class="badge bg-primary">{{ $c->object_count ?? 0 }}</span></td></tr>
      @empty
      <tr><td colspan="5" class="text-muted text-center py-4">No creators found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
