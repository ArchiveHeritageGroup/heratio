{{--
  Catalogue Report — cloned from AtoM libraryReports/catalogueSuccess.php
  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Catalogue Report')
@section('body-class', 'library-reports catalogue')

@section('sidebar')
<div class="sidebar-content">
  <h4>{{ __('Library Reports') }}</h4>
  <ul class="list-unstyled">
    <li><a href="{{ route('library.report-catalogue') }}"><i class="fas fa-book me-2"></i>Catalogue</a></li>
    <li><a href="{{ route('library.report-creators') }}"><i class="fas fa-user-edit me-2"></i>Creators</a></li>
    <li><a href="{{ route('library.report-subjects') }}"><i class="fas fa-tags me-2"></i>Subjects</a></li>
    <li><a href="{{ route('library.report-publishers') }}"><i class="fas fa-building me-2"></i>Publishers</a></li>
    <li><a href="{{ route('library.report-call-numbers') }}"><i class="fas fa-sort-alpha-down me-2"></i>Call Numbers</a></li>
  </ul>
  <hr>
  <a href="{{ route('library.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
</div>
@endsection

@section('title-block')
<h1><i class="fas fa-book me-2"></i>Catalogue Report</h1>
@endsection

@section('content')
<div class="alert alert-info"><strong>{{ count($items) }}</strong> items found</div>
<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark">
      <tr><th>{{ __('Title') }}</th><th>{{ __('Author(s)') }}</th><th>{{ __('Type') }}</th><th>{{ __('Call #') }}</th><th>{{ __('ISBN') }}</th><th>{{ __('Publisher') }}</th><th>{{ __('Status') }}</th></tr>
    </thead>
    <tbody>
      @forelse($items as $i)
      <tr>
        <td><a href="{{ Route::has('library.show') ? route('library.show', $i->slug ?? $i->id ?? '') : '#' }}">{{ e($i->title ?? '') }}</a></td>
        <td>{{ e($i->author ?? '') }}</td>
        <td><span class="badge bg-secondary">{{ ucfirst($i->material_type ?? '') }}</span></td>
        <td><code>{{ e($i->call_number ?? '') }}</code></td>
        <td>{{ e($i->isbn ?? '') }}</td>
        <td>{{ e($i->publisher ?? '') }}</td>
        <td><span class="badge bg-{{ ($i->status ?? '') === 'available' ? 'success' : (($i->status ?? '') === 'on_loan' ? 'warning' : 'secondary') }}">{{ ucfirst(str_replace('_', ' ', $i->status ?? '')) }}</span></td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-muted text-center py-3">No items.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
