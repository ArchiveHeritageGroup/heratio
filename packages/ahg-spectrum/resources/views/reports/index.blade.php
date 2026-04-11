{{-- Spectrum Reports Dashboard — cloned from AtoM spectrumReports/indexSuccess.blade.php. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Spectrum Reports Dashboard')
@section('body-class', 'admin spectrum-reports')
@section('sidebar')
<div class="sidebar-content">
  <h4>Spectrum Reports</h4>
  <ul class="list-unstyled">
    <li><a href="{{ route('ahgspectrum.report-object-entry') }}"><i class="fas fa-sign-in-alt me-2"></i>Object Entry</a></li>
    <li><a href="{{ route('ahgspectrum.report-loans') }}"><i class="fas fa-exchange-alt me-2"></i>Loans</a></li>
    <li><a href="{{ route('ahgspectrum.report-acquisitions') }}"><i class="fas fa-hand-holding me-2"></i>Acquisitions</a></li>
    <li><a href="{{ route('ahgspectrum.report-movements') }}"><i class="fas fa-truck me-2"></i>Movements</a></li>
    <li><a href="{{ route('ahgspectrum.report-conditions') }}"><i class="fas fa-heartbeat me-2"></i>Condition Checks</a></li>
    <li><a href="{{ route('ahgspectrum.report-conservation') }}"><i class="fas fa-tools me-2"></i>Conservation</a></li>
    <li><a href="{{ route('ahgspectrum.report-valuations') }}"><i class="fas fa-dollar-sign me-2"></i>Valuations</a></li>
  </ul>
  <hr>
  <a href="{{ route('reports.dashboard') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
</div>
@endsection
@section('title-block')<h1><i class="fas fa-clipboard-list me-2"></i>Spectrum Reports Dashboard</h1>@endsection
@section('content')
<div class="spectrum-dashboard">
  <div class="row mb-4">
    <div class="col-md-3"><div class="card text-center bg-primary text-white"><div class="card-body"><h2>{{ number_format($stats['conditionCheck'] ?? 0) }}</h2><p class="mb-0">Condition Checks</p></div></div></div>
    <div class="col-md-3"><div class="card text-center bg-success text-white"><div class="card-body"><h2>{{ number_format(($stats['loanIn'] ?? 0) + ($stats['loanOut'] ?? 0)) }}</h2><p class="mb-0">Total Loans</p></div></div></div>
    <div class="col-md-3"><div class="card text-center bg-info text-white"><div class="card-body"><h2>{{ number_format($stats['valuation'] ?? 0) }}</h2><p class="mb-0">Valuations</p></div></div></div>
    <div class="col-md-3"><div class="card text-center bg-warning text-dark"><div class="card-body"><h2>{{ number_format($stats['acquisition'] ?? 0) }}</h2><p class="mb-0">Acquisitions</p></div></div></div>
  </div>
  <div class="row">
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Procedure Summary</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between"><span>Object Entry</span><span class="badge bg-primary">{{ $stats['objectEntry'] ?? 0 }}</span></li>
          <li class="list-group-item d-flex justify-content-between"><span>Object Exit</span><span class="badge bg-secondary">{{ $stats['objectExit'] ?? 0 }}</span></li>
          <li class="list-group-item d-flex justify-content-between"><span>Loans In</span><span class="badge bg-success">{{ $stats['loanIn'] ?? 0 }}</span></li>
          <li class="list-group-item d-flex justify-content-between"><span>Loans Out</span><span class="badge bg-info">{{ $stats['loanOut'] ?? 0 }}</span></li>
          <li class="list-group-item d-flex justify-content-between"><span>Movements</span><span class="badge bg-warning">{{ $stats['movement'] ?? 0 }}</span></li>
          <li class="list-group-item d-flex justify-content-between"><span>Conservation</span><span class="badge bg-danger">{{ $stats['conservation'] ?? 0 }}</span></li>
          <li class="list-group-item d-flex justify-content-between"><span>Deaccession</span><span class="badge bg-dark">{{ $stats['deaccession'] ?? 0 }}</span></li>
        </ul>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5></div>
        <ul class="list-group list-group-flush">
          @forelse($recentActivity ?? [] as $a)
          <li class="list-group-item"><small class="text-muted">{{ $a->action_date ?? '-' }}</small><br>{{ $a->action ?? $a->event_type ?? '-' }}</li>
          @empty
          <li class="list-group-item text-muted">No recent activity</li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection
