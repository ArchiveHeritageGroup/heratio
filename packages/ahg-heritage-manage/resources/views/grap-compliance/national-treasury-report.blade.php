{{--
  GRAP 103 National Treasury Report

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')
@section('title', __('National Treasury Report'))
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3 d-print-none">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h1 class="h3 mb-1"><i class="fas fa-file-alt me-2"></i>{{ __('National Treasury Report') }}</h1>
        <p class="text-muted mb-0">{{ __('GRAP 103 Heritage Assets - Financial Year :fy', ['fy' => $financialYear]) }}</p>
      </div>
      <div class="d-flex gap-2 d-print-none">
        <form method="get" class="d-flex gap-2 align-items-center">
          <label for="fy" class="form-label mb-0 small">{{ __('Financial year') }}</label>
          <input type="number" id="fy" name="fy" class="form-control form-control-sm" style="width:6rem" value="{{ $financialYear }}" min="1900" max="2999">
          <button type="submit" class="btn btn-sm atom-btn-white">{{ __('Go') }}</button>
        </form>
        <a href="{{ route('heritage.grap.national-treasury-report', ['fy' => $financialYear, 'export' => 'csv']) }}" class="btn btn-sm atom-btn-white"><i class="fas fa-download me-1"></i>{{ __('Export CSV') }}</a>
        <button type="button" class="btn btn-sm atom-btn-white" data-heritage-print><i class="fas fa-print me-1"></i>{{ __('Print') }}</button>
      </div>
    </div>

    @if(!$standardId)
      <div class="alert alert-warning">{{ __('The GRAP 103 standard is not installed. Install the South Africa (GRAP) region under Heritage Admin > Regions to use this report.') }}</div>
    @endif

    {{-- Summary by class --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Summary by Asset Class') }}</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Asset Class') }}</th>
                <th class="text-end">{{ __('Count') }}</th>
                <th class="text-end">{{ __('Total Carrying Amount') }}</th>
                <th class="text-end">{{ __('Total Impairment') }}</th>
                <th class="text-end">{{ __('Revaluation Surplus') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($byClass as $class)
                <tr>
                  <td>{{ $class->class_name ?: __('Unclassified') }}</td>
                  <td class="text-end">{{ number_format($class->count) }}</td>
                  <td class="text-end">{{ number_format((float) $class->total_value, 2) }}</td>
                  <td class="text-end text-danger">{{ number_format((float) $class->total_impairment, 2) }}</td>
                  <td class="text-end text-success">{{ number_format((float) $class->total_surplus, 2) }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-muted py-3">{{ __('No GRAP 103 assets recorded yet.') }}</td></tr>
              @endforelse
            </tbody>
            @if(count($byClass))
            <tfoot class="table-secondary fw-bold">
              <tr>
                <td>{{ __('Grand Total') }}</td>
                <td class="text-end">{{ number_format($byClass->sum('count')) }}</td>
                <td class="text-end">{{ number_format((float) $byClass->sum('total_value'), 2) }}</td>
                <td class="text-end text-danger">{{ number_format((float) $byClass->sum('total_impairment'), 2) }}</td>
                <td class="text-end text-success">{{ number_format((float) $byClass->sum('total_surplus'), 2) }}</td>
              </tr>
            </tfoot>
            @endif
          </table>
        </div>
      </div>
    </div>

    {{-- Detailed register --}}
    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Heritage Asset Register') }} ({{ count($assets) }})</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Asset ID') }}</th>
                <th>{{ __('Description') }}</th>
                <th>{{ __('Class') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Basis') }}</th>
                <th class="text-end">{{ __('Cost') }}</th>
                <th class="text-end">{{ __('Carrying Amount') }}</th>
                <th>{{ __('Acquisition Date') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($assets as $asset)
                <tr>
                  <td><a href="{{ route('heritage.accounting.view', $asset->id) }}">{{ $asset->object_identifier ?: '#' . $asset->id }}</a></td>
                  <td>{{ $asset->object_title ?: '-' }}</td>
                  <td>{{ $asset->class_name ?: '-' }}</td>
                  <td>{{ ucfirst(str_replace('_', ' ', (string) $asset->recognition_status)) }}</td>
                  <td>{{ $asset->measurement_basis ? ucfirst(str_replace('_', ' ', $asset->measurement_basis)) : '-' }}</td>
                  <td class="text-end">{{ number_format((float) $asset->acquisition_cost, 2) }}</td>
                  <td class="text-end">{{ number_format((float) $asset->current_carrying_amount, 2) }}</td>
                  <td>{{ $asset->acquisition_date ?: '-' }}</td>
                </tr>
              @empty
                <tr><td colspan="8" class="text-center text-muted py-3">{{ __('No heritage asset records found.') }}</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="mt-4 d-print-none">
      <a href="{{ route('heritage.grap.dashboard') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to GRAP Dashboard') }}</a>
    </div>
  </div>
</div>
@include('ahg-heritage-manage::partials._print-script')
@endsection
