{{-- Objects Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Objects Report')
@section('body-class', 'museum-reports objects')
@section('sidebar')
<div class="sidebar-content">
  <h4>{{ __('Museum Reports') }}</h4>
  <ul class="list-unstyled">
    <li><a href="{{ route('museum.report-objects') }}"><i class="fas fa-cube me-2"></i>{{ __('Objects') }}</a></li>
    <li><a href="{{ route('museum.report-creators') }}"><i class="fas fa-user-edit me-2"></i>{{ __('Creators') }}</a></li>
    <li><a href="{{ route('museum.report-condition') }}"><i class="fas fa-heartbeat me-2"></i>{{ __('Condition') }}</a></li>
    <li><a href="{{ route('museum.report-provenance') }}"><i class="fas fa-history me-2"></i>{{ __('Provenance') }}</a></li>
    <li><a href="{{ route('museum.report-style-period') }}"><i class="fas fa-theater-masks me-2"></i>{{ __('Style & Period') }}</a></li>
    <li><a href="{{ route('museum.report-materials') }}"><i class="fas fa-layer-group me-2"></i>{{ __('Materials') }}</a></li>
  </ul>
  <hr>
  <a href="{{ route('museum.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back to Dashboard') }}</a>
</div>
@endsection
@section('title-block')<h1><i class="fas fa-cube me-2"></i>Objects Report</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($objects) }}</strong> objects found</div>
<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark"><tr><th>{{ __('Title') }}</th><th>{{ __('Work Type') }}</th><th>{{ __('Classification') }}</th><th>{{ __('Materials') }}</th><th>{{ __('Condition') }}</th></tr></thead>
    <tbody>
      @forelse($objects as $o)
      <tr>
        <td><strong>{{ e($o->title ?? '-') }}</strong></td>
        <td>{{ e($o->work_type ?? '-') }}</td>
        <td>{{ e($o->classification ?? '-') }}</td>
        <td>{{ e($o->materials ?? '-') }}</td>
        <td><span class="badge bg-{{ in_array($o->condition_term ?? '', ['poor','critical']) ? 'danger' : (($o->condition_term ?? '') === 'good' ? 'success' : 'secondary') }}">{{ ucfirst($o->condition_term ?? '-') }}</span></td>
      </tr>
      @empty
      <tr><td colspan="5" class="text-muted text-center py-4">No objects found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
