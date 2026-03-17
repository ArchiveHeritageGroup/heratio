@extends('theme::layouts.master')

@section('title', 'Display Configuration')
@section('body-class', 'admin display')

@section('breadcrumbs')
  <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
  <li class="breadcrumb-item active" aria-current="page">Display Configuration</li>
@endsection

@section('layout-content')
@php
  if (!function_exists('getTypeIcon')) {
    function getTypeIcon($type) {
      return match($type) {
        'archive' => 'fa-archive',
        'museum'  => 'fa-landmark',
        'gallery' => 'fa-palette',
        'library' => 'fa-book',
        'dam'     => 'fa-images',
        default   => 'fa-globe',
      };
    }
  }
  if (!function_exists('getTypeColor')) {
    function getTypeColor($type) {
      return match($type) {
        'archive' => 'success',
        'museum'  => 'warning',
        'gallery' => 'info',
        'library' => 'primary',
        'dam'     => 'danger',
        default   => 'secondary',
      };
    }
  }
  if (!function_exists('getLayoutIcon')) {
    function getLayoutIcon($layout) {
      return match($layout) {
        'grid'     => 'fa-th',
        'gallery'  => 'fa-image',
        'timeline' => 'fa-history',
        'tree'     => 'fa-sitemap',
        default    => 'fa-list',
      };
    }
  }
@endphp

<div id="main-column" role="main">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center">
      <i class="fas fa-3x fa-desktop me-3 text-primary" aria-hidden="true"></i>
      <div>
        <h1 class="mb-0">Display Configuration</h1>
        <span class="small text-muted">Manage display profiles, levels, fields, and collection types</span>
      </div>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  {{-- Stat cards --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card text-white bg-primary">
        <div class="card-body text-center py-3">
          <div class="fs-2 fw-bold">{{ number_format($stats['total_objects'] ?? 0) }}</div>
          <div class="small">Total Objects</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-white bg-success">
        <div class="card-body text-center py-3">
          <div class="fs-2 fw-bold">{{ number_format($stats['configured_objects'] ?? 0) }}</div>
          <div class="small">Configured Objects</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-white bg-info">
        <div class="card-body text-center py-3">
          <div class="fs-2 fw-bold">{{ number_format($stats['display_profiles'] ?? 0) }}</div>
          <div class="small">Display Profiles</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-white bg-warning">
        <div class="card-body text-center py-3">
          <div class="fs-2 fw-bold">{{ number_format($stats['level_types'] ?? 0) }}</div>
          <div class="small">Level Types</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    {{-- Main content area --}}
    <div class="col-lg-8">

      {{-- Display Profiles by Domain --}}
      <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
          <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Display Profiles by Domain</h5>
          <a href="{{ route('glam.profiles') }}" class="btn btn-sm btn-outline-primary">
            View all <i class="fas fa-arrow-right ms-1"></i>
          </a>
        </div>
        <div class="card-body">
          @if(!empty($profiles) && count($profiles))
            @php
              $grouped = collect($profiles)->groupBy('domain');
            @endphp
            @foreach($grouped as $domain => $domainProfiles)
              <h6 class="text-uppercase text-muted mt-3 mb-2">
                <i class="fas {{ getTypeIcon($domain) }} me-1"></i> {{ ucfirst($domain) }}
              </h6>
              <div class="list-group mb-3">
                @foreach($domainProfiles as $profile)
                  <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                      <i class="fas {{ getLayoutIcon($profile->layout ?? 'list') }} me-2 text-muted"></i>
                      <strong>{{ $profile->name ?? $profile->code ?? 'Unnamed' }}</strong>
                      <span class="badge bg-{{ getTypeColor($domain) }} ms-2">{{ ucfirst($domain) }}</span>
                    </div>
                    <div>
                      <span class="text-muted small me-2">{{ ucfirst($profile->layout ?? 'list') }}</span>
                      @if(!empty($profile->is_default))
                        <span class="badge bg-success">Default</span>
                      @endif
                    </div>
                  </div>
                @endforeach
              </div>
            @endforeach
          @else
            <p class="text-muted mb-0">No display profiles configured.</p>
          @endif
        </div>
      </div>

      {{-- Collection Types --}}
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0"><i class="fas fa-folder-open me-2"></i>Collection Types</h5>
        </div>
        <div class="card-body">
          @if(!empty($collectionTypes) && count($collectionTypes))
            <div class="list-group">
              @foreach($collectionTypes as $type)
                <div class="list-group-item">
                  <div class="d-flex align-items-center">
                    <i class="fas {{ getTypeIcon($type->code ?? $type->name ?? '') }} fa-lg me-3 text-{{ getTypeColor($type->code ?? $type->name ?? '') }}"></i>
                    <div>
                      <strong>{{ $type->name ?? $type->code ?? 'Unknown' }}</strong>
                      @if(!empty($type->description))
                        <p class="mb-0 text-muted small">{{ $type->description }}</p>
                      @endif
                    </div>
                  </div>
                </div>
              @endforeach
            </div>
          @else
            <p class="text-muted mb-0">No collection types defined.</p>
          @endif
        </div>
      </div>
    </div>

    {{-- Sidebar --}}
    <div class="col-lg-4">

      {{-- Quick Links --}}
      <div class="card mb-4">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0"><i class="fas fa-link me-2"></i>Quick Links</h5>
        </div>
        <div class="list-group list-group-flush">
          <a href="{{ route('glam.profiles') }}" class="list-group-item list-group-item-action">
            <i class="fas fa-layer-group me-2"></i> Profiles
          </a>
          <a href="{{ route('glam.levels') }}" class="list-group-item list-group-item-action">
            <i class="fas fa-sitemap me-2"></i> Levels
          </a>
          <a href="{{ route('glam.fields') }}" class="list-group-item list-group-item-action">
            <i class="fas fa-columns me-2"></i> Fields
          </a>
          <a href="{{ route('glam.bulk.set.type') }}" class="list-group-item list-group-item-action">
            <i class="fas fa-tags me-2"></i> Bulk Set Types
          </a>
          <a href="{{ route('glam.browse.settings') }}" class="list-group-item list-group-item-action">
            <i class="fas fa-cog me-2"></i> Browse Settings
          </a>
        </div>
      </div>

      {{-- By Type --}}
      <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
          <h5 class="mb-0"><i class="fas fa-cubes me-2"></i>By Type</h5>
        </div>
        <div class="list-group list-group-flush">
          @if(!empty($collectionTypes) && count($collectionTypes))
            @foreach($collectionTypes as $type)
              <div class="list-group-item d-flex justify-content-between align-items-center">
                <span>
                  <i class="fas {{ getTypeIcon($type->code ?? $type->name ?? '') }} me-2 text-{{ getTypeColor($type->code ?? $type->name ?? '') }}"></i>
                  {{ $type->name ?? $type->code ?? 'Unknown' }}
                </span>
                <span class="badge bg-{{ getTypeColor($type->code ?? $type->name ?? '') }} rounded-pill">
                  {{ number_format($type->count ?? 0) }}
                </span>
              </div>
            @endforeach
          @else
            <div class="list-group-item text-muted">No types available.</div>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
