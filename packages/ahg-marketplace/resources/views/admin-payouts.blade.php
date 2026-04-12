{{--
  Marketplace Admin — Payouts Queue

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/adminPayoutsSuccess.php.
  Currency is rendered per-row from $payout->currency (international — no hardcoded currency default).
--}}
@extends('theme::layouts.1col')
@section('title', __('Manage Payouts') . ' - ' . __('Marketplace Admin'))
@section('body-class', 'admin marketplace payouts')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.admin-dashboard') }}">{{ __('Marketplace Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Payouts') }}</li>
  </ol>
</nav>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<h1 class="h3 mb-4">{{ __('Manage Payouts') }}</h1>

{{-- Pending payouts summary — grouped by currency for multi-market installs --}}
@php
  $pendingCount = 0;
  $pendingByCurrency = [];
  foreach ($payouts ?? [] as $p) {
    if (($p->status ?? '') === 'pending') {
      $cur = $p->currency ?? '';
      $pendingByCurrency[$cur] = ($pendingByCurrency[$cur] ?? 0) + (float) ($p->amount ?? 0);
      $pendingCount++;
    }
  }
@endphp
@if($pendingCount > 0)
  <div class="card bg-warning bg-opacity-10 border-warning mb-4">
    <div class="card-body d-flex justify-content-between align-items-center py-3">
      <div>
        <h5 class="mb-1">{{ __(':count Pending Payouts', ['count' => $pendingCount]) }}</h5>
        <p class="mb-0 text-muted">
          {{ __('Total pending:') }}
          @foreach($pendingByCurrency as $cur => $amount)
            <span class="me-2">{{ $cur }} {{ number_format($amount, 2) }}</span>
          @endforeach
        </p>
      </div>
      <i class="fas fa-clock fa-2x text-warning"></i>
    </div>
  </div>
@endif

{{-- Status filter --}}
<form method="GET" class="mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small">{{ __('Status') }}</label>
      <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">{{ __('All') }}</option>
        <option value="pending" {{ ($statusFilter ?? '') === 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
        <option value="processing" {{ ($statusFilter ?? '') === 'processing' ? 'selected' : '' }}>{{ __('Processing') }}</option>
        <option value="completed" {{ ($statusFilter ?? '') === 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
        <option value="failed" {{ ($statusFilter ?? '') === 'failed' ? 'selected' : '' }}>{{ __('Failed') }}</option>
        <option value="cancelled" {{ ($statusFilter ?? '') === 'cancelled' ? 'selected' : '' }}>{{ __('Cancelled') }}</option>
      </select>
    </div>
  </div>
</form>

{{-- Batch process form --}}
@if(!empty($payouts) && count($payouts) > 0)
  <form method="POST" action="{{ route('ahgmarketplace.admin-payouts-batch') }}">
    @csrf
    <input type="hidden" name="form_action" value="batch_process">

    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">{{ __('Payout Queue') }}</h5>
        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('{{ __('Process all selected payouts?') }}');">
          <i class="fas fa-check-double me-1"></i> {{ __('Process Selected') }}
        </button>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:40px;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
              <th>{{ __('Payout #') }}</th>
              <th>{{ __('Seller') }}</th>
              <th class="text-end">{{ __('Amount') }}</th>
              <th>{{ __('Currency') }}</th>
              <th>{{ __('Method') }}</th>
              <th>{{ __('Status') }}</th>
              <th>{{ __('Created') }}</th>
              <th>{{ __('Processed') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($payouts as $payout)
              @php
                $statusClass = match($payout->status ?? '') {
                  'pending' => 'warning',
                  'processing' => 'info',
                  'completed' => 'success',
                  'failed' => 'danger',
                  'cancelled' => 'secondary',
                  default => 'secondary',
                };
              @endphp
              <tr>
                <td>
                  @if(($payout->status ?? '') === 'pending')
                    <input type="checkbox" class="form-check-input payout-check" name="payout_ids[]" value="{{ (int) $payout->id }}">
                  @endif
                </td>
                <td class="small fw-semibold">{{ $payout->payout_number ?? '' }}</td>
                <td class="small">{{ $payout->seller_name ?? '-' }}</td>
                <td class="text-end fw-semibold">{{ $payout->currency ?? '' }} {{ number_format((float) ($payout->amount ?? 0), 2) }}</td>
                <td class="small">{{ $payout->currency ?? '-' }}</td>
                <td class="small">{{ ucfirst(str_replace('_', ' ', $payout->method ?? '-')) }}</td>
                <td><span class="badge bg-{{ $statusClass }}">{{ ucfirst($payout->status ?? '-') }}</span></td>
                <td class="small text-muted">{{ $payout->created_at ? date('d M Y', strtotime($payout->created_at)) : '' }}</td>
                <td class="small text-muted">{{ ($payout->processed_at ?? null) ? date('d M Y', strtotime($payout->processed_at)) : '-' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </form>

  @php $totalPages = (int) ceil(($total ?? 0) / 30); @endphp
  @if($totalPages > 1)
    <nav class="mt-4" aria-label="{{ __('Pagination') }}">
      <ul class="pagination justify-content-center">
        <li class="page-item{{ ($page ?? 1) <= 1 ? ' disabled' : '' }}">
          <a class="page-link" href="?status={{ $statusFilter ?? '' }}&page={{ ($page ?? 1) - 1 }}">&laquo;</a>
        </li>
        @for($i = max(1, ($page ?? 1) - 2); $i <= min($totalPages, ($page ?? 1) + 2); $i++)
          <li class="page-item{{ $i === ($page ?? 1) ? ' active' : '' }}">
            <a class="page-link" href="?status={{ $statusFilter ?? '' }}&page={{ $i }}">{{ $i }}</a>
          </li>
        @endfor
        <li class="page-item{{ ($page ?? 1) >= $totalPages ? ' disabled' : '' }}">
          <a class="page-link" href="?status={{ $statusFilter ?? '' }}&page={{ ($page ?? 1) + 1 }}">&raquo;</a>
        </li>
      </ul>
    </nav>
  @endif
@else
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-money-check-alt fa-3x text-muted mb-3 d-block"></i>
      <h5>{{ __('No payouts found') }}</h5>
      <p class="text-muted">{{ __('Payouts will appear here when sellers have completed sales.') }}</p>
    </div>
  </div>
@endif

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  var selectAll = document.getElementById('selectAll');
  if (selectAll) {
    selectAll.addEventListener('change', function() {
      document.querySelectorAll('.payout-check').forEach(function(cb) { cb.checked = selectAll.checked; });
    });
  }
});
</script>
@endpush
@endsection
