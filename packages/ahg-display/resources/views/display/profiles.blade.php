@extends('theme::layouts.master')

@section('title', 'Display Profiles')
@section('body-class', 'admin display profiles')

@section('breadcrumbs')
  <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
  <li class="breadcrumb-item"><a href="{{ route('glam.index') }}">Display Configuration</a></li>
  <li class="breadcrumb-item active" aria-current="page">Profiles</li>
@endsection

@section('layout-content')
@php
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
@endphp

<div id="main-column" role="main">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center">
      <i class="fas fa-3x fa-layer-group me-3 text-primary" aria-hidden="true"></i>
      <div>
        <h1 class="mb-0">Display Profiles</h1>
        <span class="small text-muted">Configure how objects are displayed in different domains</span>
      </div>
    </div>
    <a href="{{ route('glam.index') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i> Back
    </a>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  @if(!empty($profiles) && count($profiles))
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
      @foreach($profiles as $profile)
        <div class="col">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <div>
                <i class="fas {{ getLayoutIcon($profile->layout ?? 'list') }} me-2"></i>
                <strong>{{ $profile->name ?? $profile->code ?? 'Unnamed Profile' }}</strong>
              </div>
              <span class="badge bg-{{ getTypeColor($profile->domain ?? '') }}">
                {{ ucfirst($profile->domain ?? 'general') }}
              </span>
            </div>
            <div class="card-body">
              <table class="table table-sm table-borderless mb-0">
                <tbody>
                  <tr>
                    <th class="text-muted" style="width: 40%;">Code</th>
                    <td>{{ $profile->code ?? '-' }}</td>
                  </tr>
                  <tr>
                    <th class="text-muted">Layout</th>
                    <td>
                      <i class="fas {{ getLayoutIcon($profile->layout ?? 'list') }} me-1"></i>
                      {{ ucfirst($profile->layout ?? 'list') }}
                    </td>
                  </tr>
                  <tr>
                    <th class="text-muted">Thumbnail</th>
                    <td>
                      @if(!empty($profile->thumbnail_size) || !empty($profile->thumbnail_position))
                        {{ $profile->thumbnail_size ?? 'default' }}
                        @if(!empty($profile->thumbnail_position))
                          / {{ $profile->thumbnail_position }}
                        @endif
                      @else
                        -
                      @endif
                    </td>
                  </tr>
                  <tr>
                    <th class="text-muted">Default</th>
                    <td>
                      @if(!empty($profile->is_default))
                        <span class="badge bg-success">Yes</span>
                      @else
                        <span class="badge bg-secondary">No</span>
                      @endif
                    </td>
                  </tr>
                </tbody>
              </table>

              @if(!empty($profile->description))
                <hr class="my-2">
                <p class="text-muted small mb-0">{{ $profile->description }}</p>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @else
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-2"></i>No display profiles have been configured.
    </div>
  @endif
</div>
@endsection
