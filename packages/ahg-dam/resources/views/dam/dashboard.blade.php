{{--
 | Heratio - Digital Asset Management dashboard
 |
 | @author    Johan Pieterse <johan@theahg.co.za>
 | @copyright (c) Plain Sailing (Pty) Ltd
 | @license   GNU Affero General Public License v3.0 or later
 --}}
@extends('theme::layouts.2col')

@section('title', 'Digital Asset Management')
@section('body-class', 'dashboard dam')

@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-images fa-2x text-danger me-3" aria-hidden="true"></i>
    <div>
      <h1 class="mb-0">Digital Asset Management</h1>
      <span class="small text-muted">Photo archive and DAM</span>
    </div>
  </div>
@endsection

@section('sidebar')
  <div class="card mb-3" style="background-color: #dc3545;">
    <div class="card-body py-2 text-white text-center">
      <i class="fas fa-cog"></i> DAM Actions
    </div>
  </div>

  <div class="list-group mb-3">
    <a href="{{ route('dam.create') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-plus text-success me-2"></i>Create new asset
    </a>
    <a href="{{ route('dam.bulk-create') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-upload text-primary me-2"></i>Bulk upload
    </a>
    <a href="{{ route('dam.browse') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-search text-info me-2"></i>Browse all assets
    </a>
    <a href="{{ route('dam.browse') }}?hasDigital=1" class="list-group-item list-group-item-action">
      <i class="fas fa-image text-warning me-2"></i>With digital objects
    </a>
    <a href="{{ route('dam.reports') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-chart-bar text-info me-2"></i>DAM Reports
    </a>
  </div>

  <div class="card mb-3" style="background-color: #ffc107;">
    <div class="card-body py-2 text-dark text-center">
      <i class="fas fa-exchange-alt"></i> Licensing
    </div>
  </div>

  <div class="list-group mb-3">
    <a href="#" class="list-group-item list-group-item-action">
      <i class="fas fa-file-contract text-warning me-2"></i>Manage Loans
    </a>
    <a href="#" class="list-group-item list-group-item-action">
      <i class="fas fa-plus text-success me-2"></i>New Loan Out
    </a>
  </div>
@endsection

@section('content')
  {{-- Statistics Cards --}}
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card text-white bg-danger">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <h4 class="mb-0">{{ number_format($stats['totalAssets'] ?? 0) }}</h4>
              <small>Total DAM Assets</small>
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
              <h4 class="mb-0">{{ number_format($stats['withDigitalObjects'] ?? 0) }}</h4>
              <small>With Digital Files</small>
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
              <h4 class="mb-0">{{ ($stats['totalAssets'] ?? 0) > 0 ? round((($stats['withDigitalObjects'] ?? 0) / $stats['totalAssets']) * 100) : 0 }}%</h4>
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
    <div class="card-header bg-light">
      <i class="fas fa-bolt"></i> Quick Actions
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-4 mb-2">
          <a href="{{ route('dam.create') }}" class="btn btn-success btn-lg w-100">
            <i class="fas fa-plus me-2"></i>New Asset
          </a>
        </div>
        <div class="col-md-4 mb-2">
          <a href="{{ route('dam.bulk-create') }}" class="btn btn-primary btn-lg w-100">
            <i class="fas fa-upload me-2"></i>Bulk Upload
          </a>
        </div>
        <div class="col-md-4 mb-2">
          <a href="{{ route('dam.browse') }}" class="btn btn-info btn-lg w-100">
            <i class="fas fa-search me-2"></i>Browse All
          </a>
        </div>
      </div>
    </div>
  </div>

  {{-- Media Type Breakdown --}}
  @php $mediaTypes = $stats['byAssetType'] ?? []; @endphp
  @if(!empty($mediaTypes))
  <div class="card mb-4">
    <div class="card-header bg-light">
      <i class="fas fa-chart-bar"></i> Media Types
    </div>
    <div class="card-body">
      <div class="row">
        @foreach($mediaTypes as $mt)
          @php
            $type = is_object($mt) ? ($mt->asset_type ?? $mt->media_type ?? null) : ($mt['asset_type'] ?? $mt['media_type'] ?? null);
            $count = is_object($mt) ? ($mt->count ?? 0) : ($mt['count'] ?? 0);
            $icon = match($type) {
              'image', 'photo', 'artwork', 'scan' => 'fa-image',
              'video', 'documentary', 'feature', 'short', 'news', 'interview', 'home_movie' => 'fa-video',
              'audio', 'oral_history', 'music', 'podcast', 'speech' => 'fa-music',
              'application', 'document', 'manuscript' => 'fa-file-alt',
              default => 'fa-file'
            };
            $color = match($type) {
              'image', 'photo', 'artwork', 'scan' => 'success',
              'video', 'documentary', 'feature', 'short', 'news', 'interview', 'home_movie' => 'danger',
              'audio', 'oral_history', 'music', 'podcast', 'speech' => 'warning',
              'application', 'document', 'manuscript' => 'info',
              default => 'secondary'
            };
          @endphp
          <div class="col-md-3 col-6 mb-3 text-center">
            <div class="p-3 border rounded">
              <i class="fas {{ $icon }} fa-2x text-{{ $color }} mb-2"></i>
              <h5 class="mb-0">{{ $count }}</h5>
              <small class="text-muted">{{ ucfirst(str_replace('_', ' ', $type ?: 'Unknown')) }}</small>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
  @endif

  {{-- Recent Assets --}}
  <div class="card">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
      <span><i class="fas fa-clock"></i> Recent Assets</span>
      <a href="{{ route('dam.browse') }}?sort=date&dir=desc" class="btn btn-sm btn-outline-secondary">View all</a>
    </div>
    <div class="card-body p-0">
      @if(empty($recentAssets))
        <div class="text-center text-muted py-5">
          <i class="fas fa-inbox fa-3x mb-3"></i>
          <p>No DAM assets yet</p>
          <a href="{{ route('dam.create') }}" class="btn btn-success">
            <i class="fas fa-plus"></i> Create your first asset
          </a>
        </div>
      @else
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Title</th>
              <th style="width:150px">Identifier</th>
              <th style="width:100px"></th>
            </tr>
          </thead>
          <tbody>
            @foreach($recentAssets as $asset)
              @php
                $slug = is_array($asset) ? ($asset['slug'] ?? null) : ($asset->slug ?? null);
                $title = is_array($asset) ? ($asset['title'] ?? null) : ($asset->title ?? null);
                $identifier = is_array($asset) ? ($asset['identifier'] ?? null) : ($asset->identifier ?? null);
              @endphp
              <tr>
                <td>
                  @if($slug)
                    <a href="{{ route('dam.show', $slug) }}">{{ $title ?: '[Untitled]' }}</a>
                  @else
                    {{ $title ?: '[Untitled]' }}
                  @endif
                </td>
                <td><small class="text-muted">{{ $identifier ?: '-' }}</small></td>
                <td class="text-end">
                  @if($slug)
                    <a href="{{ route('dam.show', $slug) }}" class="btn btn-sm btn-outline-success"><i class="fas fa-eye"></i></a>
                    <a href="{{ route('dam.edit', $slug) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
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
