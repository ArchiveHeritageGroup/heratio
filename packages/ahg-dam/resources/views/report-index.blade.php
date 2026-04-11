{{--
  DAM Reports Dashboard — cloned from AtoM ahgDAMPlugin damReports/indexSuccess.php
  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Digital Asset Management Reports')
@section('body-class', 'dam-reports index')

@section('sidebar')
<div class="sidebar-content">
  <h4>DAM Reports</h4>
  <ul class="list-unstyled">
    <li><a href="{{ route('dam.reports.assets') }}"><i class="fas fa-file me-2"></i>Assets</a></li>
    <li><a href="{{ route('dam.reports.metadata') }}"><i class="fas fa-info-circle me-2"></i>Metadata</a></li>
    <li><a href="{{ route('dam.reports.iptc') }}"><i class="fas fa-camera me-2"></i>IPTC Data</a></li>
    <li><a href="{{ route('dam.reports.storage') }}"><i class="fas fa-hdd me-2"></i>Storage</a></li>
  </ul>
</div>
@endsection

@section('title-block')
<h1><i class="fas fa-photo-video me-2"></i>Digital Asset Management Reports</h1>
@endsection

@php
if (!function_exists('damFormatBytes')) {
    function damFormatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
    }
}
@endphp

@section('content')
<div class="dam-reports-dashboard">
  <div class="row mb-4">
    <div class="col-md-3"><div class="card text-center bg-primary text-white"><div class="card-body"><h2>{{ number_format($stats['total'] ?? 0) }}</h2><p class="mb-0">Total Assets</p></div></div></div>
    <div class="col-md-3"><div class="card text-center bg-success text-white"><div class="card-body"><h2>{{ damFormatBytes($stats['totalSize'] ?? 0) }}</h2><p class="mb-0">Total Storage</p></div></div></div>
    <div class="col-md-3"><div class="card text-center bg-info text-white"><div class="card-body"><h2>{{ number_format($stats['withMetadata'] ?? 0) }}</h2><p class="mb-0">With Metadata</p></div></div></div>
    <div class="col-md-3"><div class="card text-center bg-warning text-dark"><div class="card-body"><h2>{{ number_format($stats['recentUploads'] ?? 0) }}</h2><p class="mb-0">Recent (30 days)</p></div></div></div>
  </div>
  <div class="row mb-4">
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>By File Type</h5></div>
        <ul class="list-group list-group-flush">
          @forelse($stats['byMimeType'] ?? [] as $type)
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <code>{{ $type->mime_type ?? 'unknown' }}</code>
            <span><span class="badge bg-primary">{{ $type->count ?? 0 }}</span> <small class="text-muted ms-2">{{ damFormatBytes($type->size ?? 0) }}</small></span>
          </li>
          @empty
          <li class="list-group-item text-muted">No assets yet</li>
          @endforelse
        </ul>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Metadata Coverage</h5></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fas fa-info-circle me-2 text-muted"></i>With Extracted Metadata</span><span class="badge bg-success">{{ $stats['withMetadata'] ?? 0 }}</span></li>
          <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fas fa-camera me-2 text-muted"></i>With IPTC Data</span><span class="badge bg-info">{{ $stats['withIptc'] ?? 0 }}</span></li>
          <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fas fa-map-marker-alt me-2 text-muted"></i>With GPS Coordinates</span><span class="badge bg-warning">{{ $stats['withGps'] ?? 0 }}</span></li>
        </ul>
        <div class="card-footer"><a href="{{ route('dam.reports.assets') }}" class="btn btn-primary btn-sm w-100">View All Assets</a></div>
      </div>
    </div>
  </div>
</div>
@endsection
