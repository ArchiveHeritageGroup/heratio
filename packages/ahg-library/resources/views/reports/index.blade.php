{{--
  Library Reports Dashboard — stats overview with sidebar nav
  Cloned from AtoM ahgLibraryPlugin libraryReports/indexSuccess.php

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Library Reports Dashboard')
@section('body-class', 'library-reports index')

@section('sidebar')
<div class="sidebar-content">
  <h4>{{ __('Library Reports') }}</h4>
  <ul class="list-unstyled">
    <li><a href="{{ route('library.report-catalogue') }}"><i class="fas fa-book me-2"></i>{{ __('Catalogue') }}</a></li>
    <li><a href="{{ route('library.report-creators') }}"><i class="fas fa-user-edit me-2"></i>{{ __('Creators') }}</a></li>
    <li><a href="{{ route('library.report-subjects') }}"><i class="fas fa-tags me-2"></i>{{ __('Subjects') }}</a></li>
    <li><a href="{{ route('library.report-publishers') }}"><i class="fas fa-building me-2"></i>{{ __('Publishers') }}</a></li>
    <li><a href="{{ route('library.report-call-numbers') }}"><i class="fas fa-sort-alpha-down me-2"></i>{{ __('Call Numbers') }}</a></li>
  </ul>
  <hr>
  <a href="{{ Route::has('library.browse') ? route('library.browse') : url('/library') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back to Library') }}</a>
</div>
@endsection

@section('title-block')
<h1><i class="fas fa-book-reader me-2"></i>{{ __('Library Reports Dashboard') }}</h1>
@endsection

@section('content')
@php
  $stats = $stats ?? ['items' => ['total' => 0, 'available' => 0, 'onLoan' => 0, 'reference' => 0], 'byType' => collect(), 'creators' => 0, 'subjects' => 0, 'recentlyAdded' => 0];
@endphp

<div class="library-reports-dashboard">
  {{-- Items Stats --}}
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card text-center bg-primary text-white">
        <div class="card-body">
          <h2>{{ number_format($stats['items']['total'] ?? 0) }}</h2>
          <p class="mb-0">Total Items</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center bg-success text-white">
        <div class="card-body">
          <h2>{{ number_format($stats['items']['available'] ?? 0) }}</h2>
          <p class="mb-0">Available</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center bg-warning text-dark">
        <div class="card-body">
          <h2>{{ number_format($stats['items']['onLoan'] ?? 0) }}</h2>
          <p class="mb-0">On Loan</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center bg-info text-white">
        <div class="card-body">
          <h2>{{ number_format($stats['items']['reference'] ?? 0) }}</h2>
          <p class="mb-0">Reference</p>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-4">
    {{-- By Material Type --}}
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-dark text-white">
          <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>{{ __('By Material Type') }}</h5>
        </div>
        <ul class="list-group list-group-flush">
          @forelse($stats['byType'] ?? [] as $type)
          <li class="list-group-item d-flex justify-content-between align-items-center">
            {{ ucfirst(str_replace('_', ' ', $type->material_type ?? '')) }}
            <span class="badge bg-primary rounded-pill">{{ $type->count ?? 0 }}</span>
          </li>
          @empty
          <li class="list-group-item text-muted">No items yet</li>
          @endforelse
        </ul>
      </div>
    </div>

    {{-- Quick Stats --}}
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-dark text-white">
          <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>{{ __('Quick Stats') }}</h5>
        </div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><i class="fas fa-user-edit me-2 text-muted"></i>{{ __('Unique Creators') }}</span>
            <span class="badge bg-success rounded-pill">{{ $stats['creators'] ?? 0 }}</span>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><i class="fas fa-tags me-2 text-muted"></i>{{ __('Unique Subjects') }}</span>
            <span class="badge bg-info rounded-pill">{{ $stats['subjects'] ?? 0 }}</span>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><i class="fas fa-plus-circle me-2 text-muted"></i>{{ __('Added (30 days)') }}</span>
            <span class="badge bg-warning rounded-pill">{{ $stats['recentlyAdded'] ?? 0 }}</span>
          </li>
        </ul>
        <div class="card-footer">
          <a href="{{ route('library.report-catalogue') }}" class="btn btn-primary btn-sm w-100">View Full Catalogue</a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
