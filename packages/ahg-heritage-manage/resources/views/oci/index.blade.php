@extends('theme::layouts.1col')
@section('title', 'OCI Movements')
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="bi bi-journal-text me-2"></i>{{ __('OCI / Revaluation Reserve Movements') }}</h1>
      <a href="{{ route('heritage.oci.create') }}" class="btn atom-btn-white"><i class="bi bi-plus-lg me-1"></i>{{ __('Record Movement') }}</a>
    </div>
    <p class="text-muted">{{ __('Revaluation, impairment, reversal and disposal movements posted to Other Comprehensive Income, P&L, or the Revaluation Reserve per GRAP 103.51 / IPSAS 45.74.') }}</p>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="get" class="row g-2 mb-3">
      <div class="col-md-3"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control" placeholder="{{ __('From') }}"></div>
      <div class="col-md-3"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control" placeholder="{{ __('To') }}"></div>
      <div class="col-md-3">
        <select name="movement_type" class="form-select">
          <option value="">{{ __('All movement types') }}</option>
          @foreach(['revaluation_up','revaluation_down','impairment','reversal','disposal'] as $mt)
            <option value="{{ $mt }}" {{ ($filters['movement_type'] ?? '') === $mt ? 'selected' : '' }}>{{ $mt }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3"><button class="btn atom-btn-white w-100"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button></div>
    </form>

    @if($summary)
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="bi bi-graph-up me-2"></i>{{ __('Period Summary') }} ({{ $summary['period_start'] }} - {{ $summary['period_end'] }})</div>
        <div class="card-body">
          <div class="row text-center">
            <div class="col"><small class="text-muted">{{ __('To OCI') }}</small><div class="fs-5">{{ number_format($summary['by_posting']['OCI'] ?? 0, 2) }}</div></div>
            <div class="col"><small class="text-muted">{{ __('To P&L') }}</small><div class="fs-5">{{ number_format($summary['by_posting']['P&L'] ?? 0, 2) }}</div></div>
            <div class="col"><small class="text-muted">{{ __('To Reserve') }}</small><div class="fs-5">{{ number_format($summary['by_posting']['Reserve'] ?? 0, 2) }}</div></div>
            <div class="col"><small class="text-muted">{{ __('Count') }}</small><div class="fs-5">{{ $summary['count'] }}</div></div>
          </div>
        </div>
      </div>
    @endif

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="bi bi-list-ul me-2"></i>{{ __('Movement Ledger') }}</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead><tr>
              <th>{{ __('Date') }}</th>
              <th>{{ __('Asset #') }}</th>
              <th>{{ __('Type') }}</th>
              <th class="text-end">{{ __('Amount') }}</th>
              <th>{{ __('Currency') }}</th>
              <th>{{ __('Posted To') }}</th>
              <th>{{ __('Method') }}</th>
              <th>{{ __('Valuer') }}</th>
              <th>{{ __('Reason') }}</th>
            </tr></thead>
            <tbody>
              @forelse($items as $m)
                <tr>
                  <td>{{ $m->valuation_date }}</td>
                  <td>{{ $m->heritage_asset_id ?? '-' }}</td>
                  <td><span class="badge bg-secondary">{{ $m->movement_type }}</span></td>
                  <td class="text-end {{ $m->amount < 0 ? 'text-danger' : '' }}">{{ number_format((float) $m->amount, 2) }}</td>
                  <td>{{ $m->currency }}</td>
                  <td>{{ $m->posted_to }}</td>
                  <td>{{ $m->valuation_method ?: '-' }}</td>
                  <td>{{ $m->valuer_name ?? '-' }}</td>
                  <td>{{ Str::limit((string) $m->reason, 80) ?: '-' }}</td>
                </tr>
              @empty
                <tr><td colspan="9" class="text-center text-muted py-3">{{ __('No movements recorded yet.') }}</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    @if(method_exists($items, 'links'))
      <div class="mt-3">{{ $items->links() }}</div>
    @endif
  </div>
</div>
@endsection
