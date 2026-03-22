@extends('theme::layouts.1col')
@section('title', 'Gallery Reports Dashboard')
@section('body-class', 'gallery-reports index')
@section('title-block')<h1 class="mb-0"><i class="fas fa-paint-brush me-2"></i>Gallery Reports Dashboard</h1>@endsection
@section('content')
<div class="row mb-4">
  <div class="col-12"><div class="card"><div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-images me-2"></i>Exhibitions</h5></div>
  <div class="card-body"><div class="row text-center">
    <div class="col-md-3"><h2 class="text-primary">{{ number_format($stats['exhibitions']['total'] ?? 0) }}</h2><p class="text-muted">Total</p></div>
    <div class="col-md-3"><h2 class="text-success">{{ number_format($stats['exhibitions']['open'] ?? 0) }}</h2><p class="text-muted">Currently Open</p></div>
    <div class="col-md-3"><h2 class="text-warning">{{ number_format($stats['exhibitions']['planning'] ?? 0) }}</h2><p class="text-muted">In Planning</p></div>
    <div class="col-md-3"><h2 class="text-info">{{ number_format($stats['exhibitions']['upcoming'] ?? 0) }}</h2><p class="text-muted">Upcoming</p></div>
  </div><div class="text-end mt-2"><a href="{{ route('gallery-reports.exhibitions') }}" class="btn btn-sm atom-btn-white">View Report</a></div></div></div></div>
</div>
<div class="row mb-4">
  <div class="col-md-6"><div class="card h-100"><div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Loans</h5></div>
  <div class="card-body"><div class="row text-center"><div class="col"><h3>{{ number_format($stats['loans']['total'] ?? 0) }}</h3><small class="text-muted">Total</small></div><div class="col"><h3 class="text-danger">{{ number_format($stats['loans']['overdue'] ?? 0) }}</h3><small class="text-muted">Overdue</small></div></div>
  <div class="text-end mt-2"><a href="{{ route('gallery-reports.loans') }}" class="btn btn-sm atom-btn-white">View Report</a></div></div></div></div>
  <div class="col-md-6"><div class="card h-100"><div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Valuations</h5></div>
  <div class="card-body"><div class="row text-center"><div class="col"><h3>{{ number_format($stats['valuations']['total'] ?? 0) }}</h3><small class="text-muted">Total</small></div><div class="col"><h3>R {{ number_format($stats['valuations']['total_value'] ?? 0, 0) }}</h3><small class="text-muted">Total Value</small></div></div>
  <div class="text-end mt-2"><a href="{{ route('gallery-reports.valuations') }}" class="btn btn-sm atom-btn-white">View Report</a></div></div></div></div>
</div>
<div class="row">
  <div class="col-md-4 mb-3"><a href="{{ route('gallery-reports.exhibitions') }}" class="btn atom-btn-white w-100"><i class="fas fa-images me-1"></i>Exhibitions</a></div>
  <div class="col-md-4 mb-3"><a href="{{ route('gallery-reports.loans') }}" class="btn atom-btn-white w-100"><i class="fas fa-exchange-alt me-1"></i>Loans</a></div>
  <div class="col-md-4 mb-3"><a href="{{ route('gallery-reports.valuations') }}" class="btn atom-btn-white w-100"><i class="fas fa-coins me-1"></i>Valuations</a></div>
  <div class="col-md-4 mb-3"><a href="{{ route('gallery-reports.facility-reports') }}" class="btn atom-btn-white w-100"><i class="fas fa-building me-1"></i>Facility Reports</a></div>
  <div class="col-md-4 mb-3"><a href="{{ route('gallery-reports.spaces') }}" class="btn atom-btn-white w-100"><i class="fas fa-th-large me-1"></i>Spaces</a></div>
</div>
@endsection
