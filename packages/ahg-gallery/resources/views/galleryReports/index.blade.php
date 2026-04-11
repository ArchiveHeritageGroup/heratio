{{--
  Gallery Reports Dashboard — stats overview with sidebar navigation
  Cloned from AtoM ahgGalleryPlugin galleryReports/indexSuccess.php

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Gallery Reports Dashboard')
@section('body-class', 'gallery-reports index')

@section('sidebar')
<div class="sidebar-content">
  <h4>Gallery Reports</h4>
  <ul class="list-unstyled">
    <li><a href="{{ route('gallery-reports.exhibitions') }}"><i class="fas fa-images me-2"></i>Exhibitions</a></li>
    <li><a href="{{ route('gallery-reports.loans') }}"><i class="fas fa-exchange-alt me-2"></i>Loans</a></li>
    <li><a href="{{ route('gallery-reports.valuations') }}"><i class="fas fa-coins me-2"></i>Valuations</a></li>
    <li><a href="{{ route('gallery-reports.facility-reports') }}"><i class="fas fa-building me-2"></i>Facility Reports</a></li>
    <li><a href="{{ route('gallery-reports.spaces') }}"><i class="fas fa-th-large me-2"></i>Spaces</a></li>
  </ul>
</div>
@endsection

@section('title-block')
<h1><i class="fas fa-paint-brush me-2"></i>Gallery Reports Dashboard</h1>
@endsection

@section('content')
<div class="gallery-reports-dashboard">
  {{-- Exhibitions Row --}}
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0"><i class="fas fa-images me-2"></i>Exhibitions</h5>
        </div>
        <div class="card-body">
          <div class="row text-center">
            <div class="col-md-3">
              <h2 class="text-primary">{{ number_format($stats['exhibitions']['total'] ?? 0) }}</h2>
              <p class="text-muted">Total</p>
            </div>
            <div class="col-md-3">
              <h2 class="text-success">{{ number_format($stats['exhibitions']['open'] ?? 0) }}</h2>
              <p class="text-muted">Currently Open</p>
            </div>
            <div class="col-md-3">
              <h2 class="text-warning">{{ number_format($stats['exhibitions']['planning'] ?? 0) }}</h2>
              <p class="text-muted">In Planning</p>
            </div>
            <div class="col-md-3">
              <h2 class="text-info">{{ number_format($stats['exhibitions']['upcoming'] ?? 0) }}</h2>
              <p class="text-muted">Upcoming</p>
            </div>
          </div>
          <div class="text-end mt-2">
            <a href="{{ route('gallery-reports.exhibitions') }}" class="btn btn-sm btn-outline-primary">View Report</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Artists & Loans Row --}}
  <div class="row mb-4">
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-success text-white">
          <h5 class="mb-0"><i class="fas fa-palette me-2"></i>Artists</h5>
        </div>
        <div class="card-body">
          <div class="row text-center">
            <div class="col-4">
              <h3>{{ number_format($stats['artists']['total'] ?? 0) }}</h3>
              <small class="text-muted">Total</small>
            </div>
            <div class="col-4">
              <h3>{{ number_format($stats['artists']['represented'] ?? 0) }}</h3>
              <small class="text-muted">Represented</small>
            </div>
            <div class="col-4">
              <h3>{{ number_format($stats['artists']['active'] ?? 0) }}</h3>
              <small class="text-muted">Active</small>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-info text-white">
          <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Loans</h5>
        </div>
        <div class="card-body">
          <div class="row text-center">
            <div class="col-3">
              <h3>{{ number_format($stats['loans']['total'] ?? 0) }}</h3>
              <small class="text-muted">Total</small>
            </div>
            <div class="col-3">
              <h3>{{ number_format($stats['loans']['active'] ?? 0) }}</h3>
              <small class="text-muted">Active</small>
            </div>
            <div class="col-3">
              <h3>{{ number_format($stats['loans']['incoming'] ?? 0) }}</h3>
              <small class="text-muted">Incoming</small>
            </div>
            <div class="col-3">
              <h3>{{ number_format($stats['loans']['outgoing'] ?? 0) }}</h3>
              <small class="text-muted">Outgoing</small>
            </div>
          </div>
          @if(($stats['loans']['pending'] ?? 0) > 0)
          <div class="alert alert-warning mt-3 mb-0 py-2">
            <i class="fas fa-clock me-2"></i>{{ $stats['loans']['pending'] }} pending requests
          </div>
          @endif
          <div class="text-end mt-3">
            <a href="{{ route('gallery-reports.loans') }}" class="btn btn-sm btn-outline-info">View Report</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Valuations Row --}}
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-header bg-warning text-dark">
          <h5 class="mb-0"><i class="fas fa-coins me-2"></i>Valuations</h5>
        </div>
        <div class="card-body">
          <div class="row text-center">
            <div class="col-md-3">
              <h3>{{ number_format($stats['valuations']['total'] ?? 0) }}</h3>
              <small class="text-muted">Total Records</small>
            </div>
            <div class="col-md-3">
              <h3>{{ number_format($stats['valuations']['current'] ?? 0) }}</h3>
              <small class="text-muted">Current</small>
            </div>
            <div class="col-md-3">
              <h3>R {{ number_format($stats['valuations']['totalValue'] ?? $stats['valuations']['total_value'] ?? 0, 2) }}</h3>
              <small class="text-muted">Total Value</small>
            </div>
            <div class="col-md-3">
              @if(($stats['valuations']['expiringSoon'] ?? $stats['valuations']['expiring_soon'] ?? 0) > 0)
              <h3 class="text-danger">{{ number_format($stats['valuations']['expiringSoon'] ?? $stats['valuations']['expiring_soon'] ?? 0) }}</h3>
              <small class="text-danger">Expiring Soon</small>
              @else
              <h3 class="text-success">0</h3>
              <small class="text-muted">Expiring Soon</small>
              @endif
            </div>
          </div>
          <div class="text-end mt-2">
            <a href="{{ route('gallery-reports.valuations') }}" class="btn btn-sm btn-outline-warning">View Report</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
