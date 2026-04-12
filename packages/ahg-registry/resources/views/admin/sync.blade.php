{{--
  Registry Admin — Sync Dashboard
  Cloned from PSIS adminSyncSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Sync Dashboard') . ' — ' . __('Admin'))
@section('body-class', 'registry registry-admin-sync')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Sync') }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0"><i class="fas fa-sync-alt me-2"></i>{{ __('Sync Dashboard') }}</h1>
  @csrf
  <form method="post" action="{{ url('/registry/admin/sync/run') }}" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-primary"><i class="fas fa-play me-1"></i>{{ __('Run Sync Now') }}</button>
  </form>
</div>

<div class="card">
  <div class="card-header fw-semibold">{{ __('Recent Sync Runs') }}</div>
  @if($logs->isNotEmpty())
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ __('Started') }}</th>
            <th>{{ __('Type') }}</th>
            <th class="text-center">{{ __('Status') }}</th>
            <th class="text-center">{{ __('Items') }}</th>
            <th class="text-center">{{ __('Duration') }}</th>
            <th>{{ __('Notes') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($logs as $log)
          <tr>
            <td><small>{{ !empty($log->created_at) ? date('Y-m-d H:i:s', strtotime($log->created_at)) : '—' }}</small></td>
            <td><span class="badge bg-secondary">{{ $log->sync_type ?? '' }}</span></td>
            <td class="text-center">
              @php
                $statusClass = match($log->status ?? '') {
                  'success' => 'bg-success',
                  'partial' => 'bg-warning text-dark',
                  'failed' => 'bg-danger',
                  default => 'bg-secondary',
                };
              @endphp
              <span class="badge {{ $statusClass }}">{{ ucfirst($log->status ?? '') }}</span>
            </td>
            <td class="text-center">{{ (int) ($log->items_processed ?? 0) }}</td>
            <td class="text-center"><small>{{ !empty($log->duration_ms) ? round($log->duration_ms / 1000, 1) . 's' : '—' }}</small></td>
            <td><small class="text-muted">{{ \Illuminate\Support\Str::limit($log->notes ?? '', 80) }}</small></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <div class="card-body text-center text-muted py-4">
      <i class="fas fa-sync-alt fa-2x mb-2"></i>
      <p class="mb-0">{{ __('No sync runs recorded yet.') }}</p>
    </div>
  @endif
</div>
@endsection
