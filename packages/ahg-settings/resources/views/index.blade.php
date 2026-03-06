@extends('theme::layouts.1col')

@section('title', 'Settings')
@section('body-class', 'admin settings')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="mb-0"><i class="fas fa-cogs"></i> Settings</h1>
  </div>
  <p class="text-muted mb-4">Configure theme, plugin, and system settings</p>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  <div class="row">
    {{-- Theme tile --}}
    <div class="col-lg-4 col-md-6 mb-4">
      <a href="{{ route('settings.themes') }}" class="text-decoration-none">
        <div class="card h-100 shadow-sm settings-tile">
          <div class="card-body text-center py-4">
            <div class="mb-3"><i class="fas fa-palette fa-3x text-primary"></i></div>
            <h5 class="card-title text-dark">Theme Configuration</h5>
            <p class="card-text text-muted small">Customize appearance, colours, logo, branding, and custom CSS</p>
          </div>
          <div class="card-footer bg-white border-0 text-center pb-4">
            <span class="btn btn-primary"><i class="fas fa-cog"></i> Configure</span>
          </div>
        </div>
      </a>
    </div>

    {{-- Heratio setting scopes --}}
    @foreach($scopeCards as $card)
    <div class="col-lg-4 col-md-6 mb-4">
      <a href="{{ route('settings.section', $card->key) }}" class="text-decoration-none">
        <div class="card h-100 shadow-sm settings-tile">
          <div class="card-body text-center py-4">
            <div class="mb-3"><i class="fas {{ $card->icon }} fa-3x text-primary"></i></div>
            <h5 class="card-title text-dark">{{ $card->label }}</h5>
            <p class="card-text text-muted small">{{ $card->description }}</p>
          </div>
          <div class="card-footer bg-white border-0 text-center pb-4">
            <span class="btn btn-primary"><i class="fas fa-cog"></i> Configure</span>
          </div>
        </div>
      </a>
    </div>
    @endforeach

    {{-- AHG setting groups --}}
    @foreach($ahgGroups as $group)
    @php
      $icons = [
        'accession' => 'fa-inbox', 'ai_condition' => 'fa-robot', 'compliance' => 'fa-clipboard-check',
        'data_protection' => 'fa-user-shield', 'email' => 'fa-envelope', 'encryption' => 'fa-lock',
        'faces' => 'fa-user-circle', 'features' => 'fa-star', 'fuseki' => 'fa-project-diagram',
        'general' => 'fa-palette', 'iiif' => 'fa-images', 'ingest' => 'fa-file-import',
        'integrity' => 'fa-check-double', 'jobs' => 'fa-tasks', 'media' => 'fa-play-circle',
        'metadata' => 'fa-tags', 'multi_tenant' => 'fa-building', 'photos' => 'fa-camera',
        'portable_export' => 'fa-compact-disc', 'security' => 'fa-shield-alt',
        'spectrum' => 'fa-archive', 'voice_ai' => 'fa-microphone',
      ];
      $colors = [
        'security' => 'danger', 'encryption' => 'danger', 'data_protection' => 'warning',
        'ai_condition' => 'info', 'iiif' => 'info', 'media' => 'info',
        'compliance' => 'warning', 'integrity' => 'success',
      ];
      $icon = $icons[$group->key] ?? 'fa-puzzle-piece';
      $color = $colors[$group->key] ?? 'primary';
    @endphp
    <div class="col-lg-4 col-md-6 mb-4">
      <a href="{{ route('settings.ahg', $group->key) }}" class="text-decoration-none">
        <div class="card h-100 shadow-sm settings-tile {{ $color !== 'primary' ? 'border-' . $color : '' }}">
          <div class="card-body text-center py-4">
            <div class="mb-3"><i class="fas {{ $icon }} fa-3x text-{{ $color }}"></i></div>
            <h5 class="card-title text-dark">{{ $group->label }}</h5>
            <p class="card-text text-muted small">{{ ucfirst(str_replace('_', ' ', $group->key)) }} settings</p>
          </div>
          <div class="card-footer bg-white border-0 text-center pb-4">
            <span class="btn btn-{{ $color }}"><i class="fas fa-cog"></i> Configure</span>
          </div>
        </div>
      </a>
    </div>
    @endforeach
  </div>

  <style>
    .settings-tile { transition: transform 0.15s ease, box-shadow 0.15s ease; cursor: pointer; }
    .settings-tile:hover { transform: translateY(-4px); box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.15) !important; }
    .settings-tile .card-footer { background-color: transparent !important; }
  </style>
@endsection
