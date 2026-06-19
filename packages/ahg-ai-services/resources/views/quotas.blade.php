{{--
  AI Services - Per-tenant quota dashboard (Issue #667 Phase 1).

  Lists the current ahg_ai_quota rows (one row per tenant_id x service) with
  usage counters and a small upsert form so the operator can set / change
  daily and monthly caps without dropping to SQL.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'AI Quotas')
@section('body-class', 'admin ai-services quotas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="bi bi-speedometer2"></i> {{ __('AI Service Quotas') }}</h1>
  <a href="{{ route('admin.ai.index') }}" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>{{ __('Back to AI Services') }}
  </a>
</div>

<p class="text-muted">Per-tenant daily and monthly call limits across the seven gated AI services. A limit of <code>0</code> means unlimited. Tenant <code>0</code> is the global default.</p>

@if(session('status'))
<div class="alert alert-success alert-dismissible fade show">
  <i class="bi bi-check-circle me-2"></i>{{ session('status') }}
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger">
  <strong>{{ __('Could not save') }}</strong>
  <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="card shadow-sm mb-4">
  <div class="card-header bg-white">
    <strong><i class="bi bi-list-check me-2"></i>{{ __('Current Quotas') }}</strong>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>{{ __('Tenant') }}</th>
          <th>{{ __('Service') }}</th>
          <th class="text-end">{{ __('Daily limit') }}</th>
          <th class="text-end">{{ __('Used today') }}</th>
          <th class="text-end">{{ __('Monthly limit') }}</th>
          <th class="text-end">{{ __('Used this month') }}</th>
          <th class="text-center">{{ __('Reset day') }}</th>
          <th>{{ __('Last reset') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
        <tr>
          <td>{{ $r['tenant_id'] }}</td>
          <td><code>{{ $r['service'] }}</code></td>
          <td class="text-end">{{ (int) $r['daily_limit'] === 0 ? '-' : number_format((int) $r['daily_limit']) }}</td>
          <td class="text-end">
            {{ number_format((int) $r['used_today']) }}
            @php $dl = (int) $r['daily_limit']; $ut = (int) $r['used_today']; @endphp
            @if($dl > 0 && $ut >= $dl)<span class="badge bg-danger ms-1"><i class="bi bi-exclamation-triangle"></i></span>@endif
          </td>
          <td class="text-end">{{ (int) $r['monthly_limit'] === 0 ? '-' : number_format((int) $r['monthly_limit']) }}</td>
          <td class="text-end">
            {{ number_format((int) $r['used_this_month']) }}
            @php $ml = (int) $r['monthly_limit']; $um = (int) $r['used_this_month']; @endphp
            @if($ml > 0 && $um >= $ml)<span class="badge bg-danger ms-1"><i class="bi bi-exclamation-triangle"></i></span>@endif
          </td>
          <td class="text-center">{{ (int) $r['reset_day'] }}</td>
          <td class="small text-muted">{{ $r['last_reset_at'] ?? '-' }}</td>
        </tr>
        @empty
        <tr><td colspan="8" class="text-center text-muted py-3">{{ __('No quota rows yet. Use the form below to seed one.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white">
    <strong><i class="bi bi-pencil-square me-2"></i>{{ __('Set / update a quota') }}</strong>
  </div>
  <div class="card-body">
    <form action="{{ route('admin.ai-services.quotas.save') }}" method="post" class="row g-3">
      @csrf
      <div class="col-md-2">
        <label class="form-label" for="tenant_id">{{ __('Tenant ID') }}</label>
        <input id="tenant_id" name="tenant_id" type="number" min="0" value="0" class="form-control" required>
        <div class="form-text">{{ __('0 = global default') }}</div>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="service">{{ __('Service') }}</label>
        <select id="service" name="service" class="form-select" required>
          @foreach($services as $svc)
          <option value="{{ $svc }}">{{ $svc }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="daily_limit">{{ __('Daily limit') }}</label>
        <input id="daily_limit" name="daily_limit" type="number" min="0" value="0" class="form-control" required>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="monthly_limit">{{ __('Monthly limit') }}</label>
        <input id="monthly_limit" name="monthly_limit" type="number" min="0" value="0" class="form-control" required>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="reset_day">{{ __('Reset day') }}</label>
        <input id="reset_day" name="reset_day" type="number" min="1" max="28" value="1" class="form-control" required>
        <div class="form-text">{{ __('1-28; day-of-month anchor') }}</div>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-save me-1"></i>{{ __('Save') }}
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
