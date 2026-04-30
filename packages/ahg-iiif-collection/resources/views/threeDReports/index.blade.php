{{--
  3D Object Reports Dashboard — cloned from PSIS (atom-ahg-plugins/ahgIiifPlugin/threeDReports/indexSuccess.php).
  @author Johan Pieterse
  @copyright Plain Sailing
  @license AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', '3D Object Reports Dashboard')
@section('body-class', 'admin three-d-reports index')

@section('sidebar')
<div class="sidebar-content">
    <h4>{{ __('3D Reports') }}</h4>
    <ul class="list-unstyled">
        <li><a href="{{ route('iiif.three-d-reports.models') }}"><i class="fas fa-cube me-2"></i>3D Models</a></li>
        <li><a href="{{ route('iiif.three-d-reports.hotspots') }}"><i class="fas fa-map-pin me-2"></i>{{ __('Hotspots') }}</a></li>
        <li><a href="{{ route('iiif.three-d-reports.thumbnails') }}"><i class="fas fa-image me-2"></i>{{ __('Thumbnails') }}</a></li>
        <li><a href="{{ route('iiif.three-d-reports.digital-objects') }}"><i class="fas fa-file me-2"></i>3D Files</a></li>
        <li><a href="{{ route('iiif.three-d-reports.settings') }}"><i class="fas fa-cog me-2"></i>{{ __('Viewer Settings') }}</a></li>
    </ul>
</div>
@endsection

@section('title-block')
<h1><i class="fas fa-cube"></i> 3D Object Reports Dashboard</h1>
@endsection

@php
if (!function_exists('threeDFmtBytes')) {
    function threeDFmtBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
    }
}
@endphp

@section('content')
<div class="threeDReports-dashboard">
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center bg-primary text-white">
                <div class="card-body">
                    <h2>{{ number_format($stats['totalModels'] ?? 0) }}</h2>
                    <p class="mb-0">3D Models</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-success text-white">
                <div class="card-body">
                    <h2>{{ number_format($stats['digitalObjects3D'] ?? 0) }}</h2>
                    <p class="mb-0">3D Files</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-info text-white">
                <div class="card-body">
                    <h2>{{ number_format($stats['totalHotspots'] ?? 0) }}</h2>
                    <p class="mb-0">Hotspots</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-warning text-dark">
                <div class="card-body">
                    <h2>{{ threeDFmtBytes($stats['totalSize'] ?? 0) }}</h2>
                    <p class="mb-0">Total Size</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>By Format</h5>
                </div>
                <ul class="list-group list-group-flush">
                    @if (empty($stats['byFormat']) || (is_countable($stats['byFormat']) && count($stats['byFormat']) === 0))
                    <li class="list-group-item text-muted">No models yet</li>
                    @else
                    @foreach ($stats['byFormat'] as $f)
                    <li class="list-group-item d-flex justify-content-between">
                        <span><code>.{{ strtoupper($f->format) }}</code></span>
                        <span class="badge bg-primary">{{ $f->count }}</span>
                    </li>
                    @endforeach
                    @endif
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Coverage</h5>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span><i class="fas fa-image me-2 text-success"></i>{{ __('With Thumbnails') }}</span>
                        <span class="badge bg-success">{{ $stats['withThumbnails'] ?? 0 }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><i class="fas fa-photo-video me-2 text-info"></i>{{ __('With Posters') }}</span>
                        <span class="badge bg-info">{{ $stats['withPosters'] ?? 0 }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><i class="fas fa-mobile-alt me-2 text-warning"></i>{{ __('AR Enabled') }}</span>
                        <span class="badge bg-warning">{{ $stats['arEnabled'] ?? 0 }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
