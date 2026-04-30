{{--
  Registry Admin — Setup Guides
  Cloned from PSIS adminSetupGuidesSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('Setup Guides') . ' — ' . __('Admin'))
@section('body-class', 'registry registry-admin-setup-guides')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Setup Guides') }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0"><i class="fas fa-book-open me-2"></i>{{ __('Setup Guides') }}</h1>
  <span class="badge bg-secondary fs-6">{{ $guides->count() }} {{ __('guides') }}</span>
</div>

@if($guides->isNotEmpty())
<div class="table-responsive">
  <table class="table table-hover table-striped align-middle">
    <thead class="table-light">
      <tr>
        <th>{{ __('Title') }}</th>
        <th>{{ __('Category') }}</th>
        <th>{{ __('Updated') }}</th>
        <th class="text-center">{{ __('Active') }}</th>
        <th class="text-end">{{ __('Actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($guides as $g)
      <tr>
        <td><strong>{{ $g->title ?? '' }}</strong></td>
        <td><span class="badge bg-secondary">{{ $g->category ?? '' }}</span></td>
        <td><small class="text-muted">{{ !empty($g->updated_at) ? date('Y-m-d', strtotime($g->updated_at)) : '—' }}</small></td>
        <td class="text-center">
          @if(!isset($g->is_active) || $g->is_active)
            <span class="badge bg-success">{{ __('Active') }}</span>
          @else
            <span class="badge bg-secondary">{{ __('Inactive') }}</span>
          @endif
        </td>
        <td class="text-end">
          @if(Route::has('registry.setupGuideView'))
            <a href="{{ route('registry.setupGuideView', ['id' => (int) $g->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@else
<div class="text-center py-5">
  <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
  <h5>{{ __('No setup guides found') }}</h5>
</div>
@endif
@endsection
