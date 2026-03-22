@extends('theme::layouts.1col')

@section('title', 'DAM Dashboard')
@section('body-class', 'dashboard dam')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-images me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Digital Asset Management</h1>
      <span class="small text-muted">Photo archive and DAM</span>
    </div>
  </div>

  {{-- Statistics Cards --}}
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card text-white bg-danger">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <h4 class="mb-0">{{ number_format($stats['totalAssets']) }}</h4>
              <small>Total DAM assets</small>
            </div>
            <i class="fas fa-images fa-2x opacity-50"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-white bg-success">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <h4 class="mb-0">{{ number_format($stats['withDigitalObjects']) }}</h4>
              <small>With digital files</small>
            </div>
            <i class="fas fa-file-image fa-2x opacity-50"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-white bg-info">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <h4 class="mb-0">{{ $stats['totalAssets'] > 0 ? round(($stats['withDigitalObjects'] / $stats['totalAssets']) * 100) : 0 }}%</h4>
              <small>Digitized</small>
            </div>
            <i class="fas fa-chart-pie fa-2x opacity-50"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Quick Actions --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-bolt"></i> Quick Actions
    </div>
    <div class="card-body">
      <div class="row">
        @auth
          <div class="col-md-4 mb-2">
            <a href="{{ route('dam.create') }}" class="btn atom-btn-outline-success btn-lg w-100">
              <i class="fas fa-plus me-2"></i>New Asset
            </a>
          </div>
        @endauth
        <div class="col-md-4 mb-2">
          <a href="{{ route('dam.browse') }}" class="btn atom-btn-white btn-lg w-100">
            <i class="fas fa-search me-2"></i>Browse All
          </a>
        </div>
      </div>
    </div>
  </div>

  {{-- Asset Type Breakdown --}}
  @if(!empty($stats['byAssetType']))
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-chart-bar"></i> Asset Types
      </div>
      <div class="card-body">
        <div class="row">
          @foreach($stats['byAssetType'] as $at)
            @php
              $icon = match($at->asset_type) {
                'photo', 'artwork', 'scan' => 'fa-image',
                'documentary', 'feature', 'short', 'news', 'interview', 'home_movie' => 'fa-video',
                'oral_history', 'music', 'podcast', 'speech' => 'fa-music',
                'document', 'manuscript' => 'fa-file-alt',
                default => 'fa-file'
              };
              $color = match($at->asset_type) {
                'photo', 'artwork', 'scan' => 'success',
                'documentary', 'feature', 'short', 'news', 'interview', 'home_movie' => 'danger',
                'oral_history', 'music', 'podcast', 'speech' => 'warning',
                'document', 'manuscript' => 'info',
                default => 'secondary'
              };
            @endphp
            <div class="col-md-3 col-6 mb-3 text-center">
              <div class="p-3 border rounded">
                <i class="fas {{ $icon }} fa-2x text-{{ $color }} mb-2"></i>
                <h5 class="mb-0">{{ $at->count }}</h5>
                <small class="text-muted">{{ ucfirst(str_replace('_', ' ', $at->asset_type)) }}</small>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  {{-- License Breakdown --}}
  @if(!empty($stats['licenseTypes']))
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-gavel"></i> License Types
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-sm mb-0">
            <thead>
              <tr>
                <th>License</th>
                <th class="text-end" style="width:100px;">Count</th>
              </tr>
            </thead>
            <tbody>
              @foreach($stats['licenseTypes'] as $lt)
                <tr>
                  <td>{{ $lt->license_type }}</td>
                  <td class="text-end">{{ $lt->count }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  {{-- Recent Assets --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
      <span><i class="fas fa-clock"></i> Recent Assets</span>
      <a href="{{ route('dam.browse') }}?sort=lastUpdated&sortDir=desc" class="btn btn-sm atom-btn-white">View all</a>
    </div>
    <div class="card-body p-0">
      @if(empty($recentAssets))
        <div class="text-center text-muted py-5">
          <i class="fas fa-inbox fa-3x mb-3"></i>
          <p>No DAM assets yet</p>
          @auth
            <a href="{{ route('dam.create') }}" class="btn atom-btn-outline-success">
              <i class="fas fa-plus"></i> Create your first asset
            </a>
          @endauth
        </div>
      @else
        <table class="table table-bordered table-hover mb-0">
          <thead>
            <tr>
              <th>Title</th>
              <th style="width:120px;">Asset Type</th>
              <th style="width:150px;">Identifier</th>
              <th style="width:100px;">Creator</th>
              <th style="width:100px;"></th>
            </tr>
          </thead>
          <tbody>
            @foreach($recentAssets as $asset)
              <tr>
                <td>
                  @if($asset['slug'])
                    <a href="{{ route('dam.show', $asset['slug']) }}">
                      {{ $asset['title'] ?: '[Untitled]' }}
                    </a>
                  @else
                    {{ $asset['title'] ?: '[Untitled]' }}
                  @endif
                  @if($asset['headline'])
                    <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($asset['headline'], 60) }}</small>
                  @endif
                </td>
                <td>
                  @if($asset['asset_type'])
                    <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $asset['asset_type'])) }}</span>
                  @endif
                </td>
                <td><small class="text-muted">{{ $asset['identifier'] ?: '-' }}</small></td>
                <td><small>{{ $asset['creator'] ?? '' }}</small></td>
                <td class="text-end">
                  @if($asset['slug'])
                    <a href="{{ route('dam.show', $asset['slug']) }}" class="btn btn-sm atom-btn-outline-success"><i class="fas fa-eye"></i></a>
                    @auth
                      <a href="{{ route('dam.edit', $asset['slug']) }}" class="btn btn-sm atom-btn-white"><i class="fas fa-edit"></i></a>
                    @endauth
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>
  </div>
@endsection
