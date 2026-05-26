{{--
  AI Services - Per-tenant / per-service cost dashboard (Issue #667 Phase 1).

  Reads from ahg_ai_call_cost via CostService::totals() to render:
    - one summary row per gated service for the selected window
    - an overall total
    - the 100 most recent call rows for spot-inspection
    - the current ahg_ai_pricing rates

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'AI Costs')
@section('body-class', 'admin ai-services costs')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="bi bi-currency-dollar"></i> {{ __('AI Cost Dashboard') }}</h1>
  <a href="{{ route('admin.ai.index') }}" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>{{ __('Back to AI Services') }}
  </a>
</div>

<p class="text-muted">Per-call inference cost, summed by service. Costs in USD computed from <code>ahg_ai_pricing</code> at insert time; local / amortised models record 0.</p>

<form method="get" class="row g-2 mb-4">
  <div class="col-auto">
    <label for="tenant_id" class="col-form-label">{{ __('Tenant ID') }}</label>
  </div>
  <div class="col-auto">
    <input id="tenant_id" name="tenant_id" type="number" min="0" value="{{ $tenantId !== null ? $tenantId : '' }}" placeholder="{{ __('all') }}" class="form-control form-control-sm">
  </div>
  <div class="col-auto">
    <label for="since" class="col-form-label">{{ __('Since') }}</label>
  </div>
  <div class="col-auto">
    <input id="since" name="since" type="datetime-local" value="{{ \Illuminate\Support\Carbon::parse($since)->format('Y-m-d\TH:i') }}" class="form-control form-control-sm">
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>{{ __('Apply') }}</button>
  </div>
</form>

<div class="row mb-4">
  <div class="col-md-4">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small text-uppercase">{{ __('Total cost') }}</div>
        <div class="h3 mb-0">${{ number_format((float) ($overall['total_usd'] ?? 0), 4) }}</div>
        <div class="text-muted small">{{ __('USD over selected window') }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small text-uppercase">{{ __('Calls') }}</div>
        <div class="h3 mb-0">{{ number_format((int) ($overall['calls'] ?? 0)) }}</div>
        <div class="text-muted small">{{ __('inference dispatches') }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small text-uppercase">{{ __('Tokens') }}</div>
        <div class="h3 mb-0">{{ number_format((int) ($overall['tokens_in'] ?? 0) + (int) ($overall['tokens_out'] ?? 0)) }}</div>
        <div class="text-muted small">in {{ number_format((int) ($overall['tokens_in'] ?? 0)) }} / out {{ number_format((int) ($overall['tokens_out'] ?? 0)) }}</div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-header bg-white"><strong><i class="bi bi-bar-chart me-2"></i>{{ __('By service') }}</strong></div>
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>{{ __('Service') }}</th>
          <th class="text-end">{{ __('Calls') }}</th>
          <th class="text-end">{{ __('Tokens in') }}</th>
          <th class="text-end">{{ __('Tokens out') }}</th>
          <th class="text-end">{{ __('Cost (USD)') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($totalsByService as $svc => $t)
        <tr>
          <td><code>{{ $svc }}</code></td>
          <td class="text-end">{{ number_format((int) ($t['calls'] ?? 0)) }}</td>
          <td class="text-end">{{ number_format((int) ($t['tokens_in'] ?? 0)) }}</td>
          <td class="text-end">{{ number_format((int) ($t['tokens_out'] ?? 0)) }}</td>
          <td class="text-end">${{ number_format((float) ($t['total_usd'] ?? 0), 4) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-header bg-white"><strong><i class="bi bi-clock-history me-2"></i>{{ __('Recent calls (last 100)') }}</strong></div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>{{ __('Time') }}</th>
          <th>{{ __('Tenant') }}</th>
          <th>{{ __('Service') }}</th>
          <th>{{ __('Model') }}</th>
          <th class="text-end">{{ __('In') }}</th>
          <th class="text-end">{{ __('Out') }}</th>
          <th class="text-end">{{ __('ms') }}</th>
          <th class="text-end">{{ __('Cost') }}</th>
          <th>{{ __('Request') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($recent as $row)
        <tr>
          <td class="small text-nowrap">{{ $row->called_at }}</td>
          <td>{{ $row->tenant_id }}</td>
          <td><code>{{ $row->service }}</code></td>
          <td><code class="small">{{ $row->model_id }}</code></td>
          <td class="text-end">{{ number_format((int) $row->tokens_in) }}</td>
          <td class="text-end">{{ number_format((int) $row->tokens_out) }}</td>
          <td class="text-end">{{ $row->duration_ms !== null ? number_format((int) $row->duration_ms) : '-' }}</td>
          <td class="text-end">{{ $row->cost_usd !== null ? '$' . number_format((float) $row->cost_usd, 4) : '-' }}</td>
          <td class="small text-muted">{{ $row->request_id ?? '-' }}</td>
        </tr>
        @empty
        <tr><td colspan="9" class="text-center text-muted py-3">{{ __('No inference calls recorded in this window.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white"><strong><i class="bi bi-tag me-2"></i>{{ __('Model pricing reference') }}</strong></div>
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>{{ __('Model') }}</th>
          <th class="text-end">{{ __('Input / 1k tokens') }}</th>
          <th class="text-end">{{ __('Output / 1k tokens') }}</th>
          <th>{{ __('Currency') }}</th>
          <th>{{ __('Notes') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($pricing as $p)
        <tr>
          <td><code>{{ $p->model_id }}</code></td>
          <td class="text-end">${{ number_format((float) $p->input_cost_per_1k_tokens, 6) }}</td>
          <td class="text-end">${{ number_format((float) $p->output_cost_per_1k_tokens, 6) }}</td>
          <td>{{ $p->currency }}</td>
          <td class="small text-muted">{{ $p->notes }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection
