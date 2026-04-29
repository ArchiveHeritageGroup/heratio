{{--
  Registry — Schema & ERD Documentation
  Cloned from PSIS erdBrowseSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('Schema & ERD Documentation'))
@section('body-class', 'registry registry-erd-browse')

@php
  $items = $result['items'] ?? collect();
  $isAdmin = auth()->check() && (auth()->user()->is_admin ?? false);
  $catLabels = [
    'core'        => ['Core', 'primary', 'fas fa-cube'],
    'sector'      => ['GLAM Sectors', 'info', 'fas fa-layer-group'],
    'compliance'  => ['Compliance & Accounting', 'danger', 'fas fa-shield-alt'],
    'collection'  => ['Collection Management', 'success', 'fas fa-boxes'],
    'rights'      => ['Rights Management', 'warning', 'fas fa-gavel'],
    'research'    => ['Research & Public Access', 'secondary', 'fas fa-microscope'],
    'ai'          => ['AI & Automation', 'info', 'fas fa-brain'],
    'ingest'      => ['Data Ingest', 'warning', 'fas fa-file-import'],
    'integration' => ['Integration', 'dark', 'fas fa-plug'],
    'exhibition'  => ['Exhibition & Engagement', 'info', 'fas fa-palette'],
    'reporting'   => ['Reporting & Admin', 'primary', 'fas fa-chart-bar'],
  ];
  $grouped = [];
  foreach ($items as $erd) {
    $cat = $erd->category ?? 'general';
    $grouped[$cat][] = $erd;
  }
  $catOrder = array_keys($catLabels);
  uksort($grouped, function ($a, $b) use ($catOrder) {
    $ia = array_search($a, $catOrder);
    $ib = array_search($b, $catOrder);
    if ($ia === false) { $ia = 999; }
    if ($ib === false) { $ib = 999; }
    return $ia - $ib;
  });
  $selCat = request('category', '');
  $selVendor = request('vendor', '');
@endphp

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Schema & ERD') }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="h3 mb-1"><i class="fas fa-project-diagram me-2"></i>{{ __('Schema & ERD Documentation') }}</h1>
    <p class="text-muted mb-0">{{ __('Database schemas, entity relationships, and field definitions for all Heratio plugins.') }}</p>
  </div>
  @if($isAdmin && Route::has('registry.admin.erd'))
    <a href="{{ route('registry.admin.erd') }}" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-cog me-1"></i>{{ __('Manage') }}
    </a>
  @endif
</div>

@if(!empty($grouped))
<div class="card bg-light border-0 mb-4">
  <div class="card-body py-3">
    <div>
      <span class="small text-muted me-2"><i class="fas fa-tag me-1"></i>{{ __('Category') }}:</span>
      <a href="{{ route('registry.erdBrowse') }}" class="btn btn-sm {{ empty($selCat) ? 'btn-dark' : 'btn-outline-dark' }} me-1 mb-1">
        {{ __('All') }} <span class="badge bg-light text-dark ms-1">{{ count($items) }}</span>
      </a>
      @foreach(array_keys($grouped) as $cat)
        @php $cl = $catLabels[$cat] ?? [$cat, 'secondary', 'fas fa-folder']; $catCount = count($grouped[$cat]); @endphp
        <a href="{{ route('registry.erdBrowse', ['category' => $cat]) }}"
           class="btn btn-sm {{ $selCat === $cat ? 'btn-'.$cl[1] : 'btn-outline-'.$cl[1] }} me-1 mb-1">
          {{ $cl[0] }} <span class="badge bg-light text-dark ms-1">{{ $catCount }}</span>
        </a>
      @endforeach
    </div>
  </div>
</div>
@endif

@if(empty($items) || count($items) === 0)
  <div class="alert alert-info">{{ __('No ERD entries found.') }}</div>
@else
  @foreach($grouped as $cat => $erdItems)
    @if(!empty($selCat) && $selCat !== $cat) @continue @endif
    @php $cl = $catLabels[$cat] ?? [$cat, 'secondary', 'fas fa-folder']; @endphp
    <div class="mb-5">
      <div class="d-flex align-items-center mb-3">
        <i class="{{ $cl[2] }} fa-lg text-{{ $cl[1] }} me-2"></i>
        <h2 class="h5 mb-0">{{ $cl[0] }}</h2>
        <span class="badge bg-{{ $cl[1] }} ms-2">{{ count($erdItems) }}</span>
      </div>
      <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3">
        @foreach($erdItems as $erd)
          @php
            $icon = $erd->icon ?? 'fas fa-database';
            $color = $erd->color ?? 'primary';
            $tables = json_decode($erd->tables_json ?? '[]', true);
            $tableCount = is_array($tables) ? count($tables) : 0;
          @endphp
          <div class="col">
            @if(!empty($erd->id) && Route::has('registry.erdView'))
              <a href="{{ route('registry.erdView', ['id' => (int) $erd->id]) }}" class="card h-100 text-decoration-none border-start border-{{ $color }} border-4">
            @else
              <div class="card h-100 border-start border-{{ $color }} border-4">
            @endif
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <i class="{{ $icon }} fa-2x text-{{ $color }} opacity-75"></i>
                  <span class="badge bg-light text-dark border"><i class="fas fa-table me-1"></i>{{ $tableCount }}</span>
                </div>
                <h6 class="card-title mb-1">{{ $erd->display_name ?? '' }}</h6>
                <p class="card-text small text-muted mb-2">{{ \Illuminate\Support\Str::limit(strip_tags($erd->description ?? ''), 120) }}</p>
                <code class="small text-muted">{{ $erd->plugin_name ?? '' }}</code>
              </div>
            @if(!empty($erd->id) && Route::has('registry.erdView'))
              </a>
            @else
              </div>
            @endif
          </div>
        @endforeach
      </div>
    </div>
  @endforeach
@endif
@endsection
