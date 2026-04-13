{{--
  Museum Dashboard
  Copyright (C) 2024-2026 Johan Pieterse / Plain Sailing (Pty) Ltd
  Licensed under the GNU Affero General Public License v3.0 or later.
--}}
@extends('theme::layouts.1col')
@section('title', __('Museum Dashboard'))
@section('content')
<div class="container-fluid py-4">
  <div class="row mb-4">
    <div class="col-12">
      <h1><i class="fas fa-landmark me-2"></i>{{ __('Museum Management') }}</h1>
      <p class="lead text-muted">{{ __('Manage museum objects using CCO/Spectrum cataloguing standards') }}</p>
    </div>
  </div>

  {{-- Statistics Row --}}
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card bg-primary text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="card-title mb-0">{{ __('Total Objects') }}</h6>
              <h2 class="mb-0">{{ number_format($totalItems ?? 0) }}</h2>
            </div>
            <i class="fas fa-cube fa-2x opacity-50"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-success text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="card-title mb-0">{{ __('With Media') }}</h6>
              <h2 class="mb-0">{{ number_format($itemsWithMedia ?? 0) }}</h2>
            </div>
            <i class="fas fa-image fa-2x opacity-50"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-info text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="card-title mb-0">{{ __('Condition Checked') }}</h6>
              <h2 class="mb-0">{{ number_format($itemsWithCondition ?? 0) }}</h2>
            </div>
            <i class="fas fa-heartbeat fa-2x opacity-50"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-warning text-dark">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="card-title mb-0">{{ __('With Provenance') }}</h6>
              <h2 class="mb-0">{{ number_format($itemsWithProvenance ?? 0) }}</h2>
            </div>
            <i class="fas fa-history fa-2x opacity-50"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Actions and Recent Items --}}
  <div class="row">
    <div class="col-md-4">
      <div class="card mb-4">
        <div class="card-header bg-dark text-white">
          <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>{{ __('Quick Actions') }}</h5>
        </div>
        <div class="card-body">
          <a href="{{ route('museum.create') }}" class="btn btn-primary w-100 mb-2">
            <i class="fas fa-plus me-2"></i>{{ __('Add new museum object') }}
          </a>
          <a href="{{ route('glam.browse', ['type' => 'museum']) }}" class="btn btn-outline-primary w-100 mb-2">
            <i class="fas fa-list me-2"></i>{{ __('Browse all objects') }}
          </a>
          <a href="{{ route('museum.reports') }}" class="btn btn-outline-success w-100 mb-2">
            <i class="fas fa-chart-bar me-2"></i>{{ __('Museum Reports') }}
          </a>
          <a href="{{ route('museum.quality-dashboard') }}" class="btn btn-outline-info w-100 mb-2">
            <i class="fas fa-chart-line me-2"></i>{{ __('Data Quality Dashboard') }}
          </a>
          <a href="{{ route('exhibition.dashboard') }}" class="btn btn-outline-secondary w-100">
            <i class="fas fa-theater-masks me-2"></i>{{ __('Exhibitions') }}
          </a>
        </div>
      </div>

      @if(!empty($workTypeStats) && count($workTypeStats) > 0)
      <div class="card">
        <div class="card-header bg-dark text-white">
          <h5 class="mb-0"><i class="fas fa-shapes me-2"></i>{{ __('Top Work Types') }}</h5>
        </div>
        <ul class="list-group list-group-flush">
          @foreach($workTypeStats as $stat)
          <li class="list-group-item d-flex justify-content-between align-items-center">
            {{ e($stat->work_type ?: 'Unknown') }}
            <span class="badge bg-primary rounded-pill">{{ $stat->count }}</span>
          </li>
          @endforeach
        </ul>
      </div>
      @endif
    </div>

    <div class="col-md-8">
      <div class="card">
        <div class="card-header bg-dark text-white">
          <h5 class="mb-0"><i class="fas fa-clock me-2"></i>{{ __('Recent Museum Objects') }}</h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>{{ __('Identifier') }}</th>
                  <th>{{ __('Title') }}</th>
                  <th class="text-center">{{ __('Media') }}</th>
                  <th>{{ __('Actions') }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse($recentItems as $item)
                <tr>
                  <td><code>{{ e($item->identifier) }}</code></td>
                  <td>
                    <a href="{{ $item->slug ? url('museum/'.$item->slug) : '#' }}">
                      {{ e($item->title ?: '[Untitled]') }}
                    </a>
                  </td>
                  <td class="text-center">
                    @if($item->digital_object_id)
                      <i class="fas fa-check-circle text-success"></i>
                    @else
                      <i class="fas fa-times-circle text-muted"></i>
                    @endif
                  </td>
                  <td>
                    @if($item->slug)
                    <a href="{{ route('museum.edit', ['slug' => $item->slug]) }}" class="btn btn-sm btn-outline-primary">
                      <i class="fas fa-edit"></i>
                    </a>
                    @endif
                  </td>
                </tr>
                @empty
                <tr>
                  <td colspan="4" class="text-center text-muted py-4">
                    {{ __('No museum objects yet. Add your first object to get started.') }}
                  </td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
