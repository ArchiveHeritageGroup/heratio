{{--
  GRAP 103 National Treasury Report

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')
@section('title', 'GRAP 103 National Treasury Report')
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-balance-scale me-2"></i>GRAP 103 National Treasury Report</h1>
    <p class="text-muted">{{ __('Heritage asset disclosures for National Treasury submission per GRAP 103.') }}</p>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Stats Cards --}}
    <div class="row mb-4">
      <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white h-100">
          <div class="card-body text-center">
            <h3 class="mb-0">{{ number_format($stats['total_assets'] ?? 0) }}</h3>
            <small>{{ __('Total Heritage Assets') }}</small>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card bg-success text-white h-100">
          <div class="card-body text-center">
            <h3 class="mb-0">{{ number_format($stats['capitalised'] ?? 0) }}</h3>
            <small>{{ __('Capitalised') }}</small>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card bg-warning text-dark h-100">
          <div class="card-body text-center">
            <h3 class="mb-0">{{ number_format($stats['non_capitalised'] ?? 0) }}</h3>
            <small>{{ __('Non-Capitalised') }}</small>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card bg-info text-white h-100">
          <div class="card-body text-center">
            <h3 class="mb-0">R {{ number_format($stats['total_value_zar'] ?? 0, 2) }}</h3>
            <small>{{ __('Total Carrying Amount') }}</small>
          </div>
        </div>
      </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="card mb-3">
      <div class="card-body">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label">{{ __('Recognition Status') }}</label>
            <select name="status" class="form-select form-select-sm">
              <option value="">{{ __('All') }}</option>
              <option value="recognised" {{ ($statusFilter ?? '') === 'recognised' ? 'selected' : '' }}>{{ __('Recognised') }}</option>
              <option value="pending" {{ ($statusFilter ?? '') === 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
              <option value="unrecognised" {{ ($statusFilter ?? '') === 'unrecognised' ? 'selected' : '' }}>{{ __('Unrecognised') }}</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Accounting Standard') }}</label>
            <select name="standard" class="form-select form-select-sm">
              <option value="">{{ __('All') }}</option>
              @foreach($standards ?? [] as $std)
                <option value="{{ $std->code }}" {{ ($standardFilter ?? '') === $std->code ? 'selected' : '' }}>{{ $std->code }} — {{ $std->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>{{ __('Filter') }}</button>
            <a href="{{ url('/grap/national-treasury-report') }}" class="btn btn-sm btn-outline-secondary">{{ __('Clear') }}</a>
            <a href="{{ url('/grap/national-treasury-report?export=csv') }}" class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i>{{ __('Export CSV') }}</a>
          </div>
        </div>
      </div>
    </form>

    {{-- Report Table --}}
    <div class="card">
      <div class="card-header bg-primary text-white">
        <i class="fas fa-file-alt me-2"></i>{{ __('Heritage Asset Register') }} ({{ count($items) }})
      </div>
      <div class="card-body p-0 table-responsive">
        <table class="table table-bordered table-sm table-striped table-hover mb-0">
          <thead class="table-light">
            <tr>
              @foreach($columns as $col)
                <th>{{ $col }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @forelse($items as $item)
              <tr>
                <td>{{ $item->id }}</td>
                <td>{{ \Illuminate\Support\Str::limit($item->asset_title ?? 'Untitled', 50) }}</td>
                <td>{{ $item->class_name ?? '-' }}</td>
                <td><code>{{ $item->standard_code ?? '-' }}</code></td>
                <td>
                  <span class="badge bg-{{ $item->recognition_status === 'recognised' ? 'success' : ($item->recognition_status === 'pending' ? 'warning text-dark' : 'secondary') }}">
                    {{ ucfirst($item->recognition_status ?? 'unknown') }}
                  </span>
                </td>
                <td class="text-end">R {{ number_format($item->current_carrying_amount ?? 0, 2) }}</td>
                <td class="text-end">{{ $item->last_valuation_amount ? 'R ' . number_format($item->last_valuation_amount, 2) : '-' }}</td>
                <td>{{ $item->last_valuation_date ?? '-' }}</td>
              </tr>
            @empty
              <tr><td colspan="{{ count($columns) }}" class="text-muted text-center py-4">{{ __('No heritage asset records found.') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
