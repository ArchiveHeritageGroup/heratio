@extends('theme::layouts.1col')
@section('title', __('Add Valuation'))
@section('body-class', 'admin heritage')

@php
  $prev = (float) ($asset->last_valuation_amount ?? ($asset->current_carrying_amount ?? 0));
  $surplus = (float) ($asset->revaluation_surplus ?? 0);
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h1 class="h3 mb-1"><i class="fas fa-dollar-sign me-2"></i>{{ __('Add Valuation') }}</h1>
        <div class="text-muted">
          @if($io)
            @if($io->slug)<a href="{{ url('/' . $io->slug) }}">{{ $io->title ?: $io->slug }}</a>@else{{ $io->title }}@endif
            @if($io->identifier)<span class="ms-2 badge bg-light text-dark">{{ $io->identifier }}</span>@endif
          @else
            {{ __('Heritage asset') }} #{{ $asset->id }}
          @endif
        </div>
      </div>
      <a href="{{ route('heritage.accounting.view', $asset->id) }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to asset') }}
      </a>
    </div>

    @if($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    {{-- What the asset stands at now, so the operator values against a known base. --}}
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="card h-100"><div class="card-body py-2">
          <div class="small text-muted">{{ __('Current carrying amount') }}</div>
          <div class="h5 mb-0">{{ number_format((float) ($asset->current_carrying_amount ?? 0), 2) }}</div>
        </div></div>
      </div>
      <div class="col-md-4">
        <div class="card h-100"><div class="card-body py-2">
          <div class="small text-muted">{{ __('Last valuation') }}</div>
          <div class="h5 mb-0">
            {{ $asset->last_valuation_amount !== null ? number_format((float) $asset->last_valuation_amount, 2) : '-' }}
          </div>
          @if($asset->last_valuation_date)<div class="small text-muted">{{ $asset->last_valuation_date }}</div>@endif
        </div></div>
      </div>
      <div class="col-md-4">
        <div class="card h-100"><div class="card-body py-2">
          <div class="small text-muted">{{ __('Revaluation surplus (reserve)') }}</div>
          <div class="h5 mb-0">{{ number_format($surplus, 2) }}</div>
        </div></div>
      </div>
    </div>

    <form method="post" action="{{ route('heritage.accounting.store-valuation', $asset->id) }}">
      @csrf

      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-dollar-sign me-2"></i>{{ __('Valuation') }}
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label" for="valuation_date">{{ __('Valuation date') }} <span class="text-danger">*</span></label>
              <input type="date" id="valuation_date" name="valuation_date" class="form-control" required
                     value="{{ old('valuation_date', date('Y-m-d')) }}">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label" for="new_value">{{ __('Valuation amount') }} <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0" id="new_value" name="new_value" class="form-control" required
                     value="{{ old('new_value') }}" data-previous="{{ $prev }}">
              <div class="form-text" id="valuation-delta">
                {{ __('Previous value: :v', ['v' => number_format($prev, 2)]) }}
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label" for="valuation_method">{{ __('Valuation method') }}</label>
              <select id="valuation_method" name="valuation_method" class="form-select">
                <option value="">{{ __('- Select -') }}</option>
                @foreach($methods as $val => $label)
                  <option value="{{ $val }}" {{ old('valuation_method', $asset->valuation_method ?? '') === $val ? 'selected' : '' }}>{{ __($label) }}</option>
                @endforeach
              </select>
              <div class="form-text">{{ __('GRAP 103 measurement basis') }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><i class="fas fa-user-tie me-2"></i>{{ __('Valuer') }}</div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="valuer_name">{{ __('Valuer name') }}</label>
              <input type="text" id="valuer_name" name="valuer_name" class="form-control"
                     value="{{ old('valuer_name', $asset->valuer_name ?? '') }}">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="valuer_organization">{{ __('Organisation') }}</label>
              <input type="text" id="valuer_organization" name="valuer_organization" class="form-control"
                     value="{{ old('valuer_organization') }}">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="valuer_credentials">{{ __('Credentials') }}</label>
              <input type="text" id="valuer_credentials" name="valuer_credentials" class="form-control"
                     value="{{ old('valuer_credentials', $asset->valuer_credentials ?? '') }}">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="valuation_report_reference">{{ __('Report reference') }}</label>
              <input type="text" id="valuation_report_reference" name="valuation_report_reference" class="form-control"
                     value="{{ old('valuation_report_reference') }}">
            </div>
            <div class="col-12 mb-3">
              <label class="form-label" for="notes">{{ __('Notes') }}</label>
              <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Record valuation') }}</button>
        <a href="{{ route('heritage.accounting.view', $asset->id) }}" class="btn atom-btn-white">{{ __('Cancel') }}</a>
      </div>
    </form>

    @if($history->count())
      <div class="card">
        <div class="card-header"><i class="fas fa-history me-2"></i>{{ __('Valuation history') }}</div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
              <tr>
                <th>{{ __('Date') }}</th>
                <th class="text-end">{{ __('Previous') }}</th>
                <th class="text-end">{{ __('New') }}</th>
                <th class="text-end">{{ __('Change') }}</th>
                <th>{{ __('Method') }}</th>
                <th>{{ __('Valuer') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($history as $h)
                <tr>
                  <td>{{ $h->valuation_date }}</td>
                  <td class="text-end">{{ $h->previous_value !== null ? number_format((float) $h->previous_value, 2) : '-' }}</td>
                  <td class="text-end">{{ number_format((float) $h->new_value, 2) }}</td>
                  <td class="text-end {{ (float) $h->valuation_change > 0 ? 'text-success' : ((float) $h->valuation_change < 0 ? 'text-danger' : '') }}">
                    {{ number_format((float) $h->valuation_change, 2) }}
                  </td>
                  <td>{{ $h->valuation_method ? __(\AhgHeritageManage\Services\HeritageValuationService::METHODS[$h->valuation_method] ?? $h->valuation_method) : '-' }}</td>
                  <td>{{ $h->valuer_name ?: '-' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif

  </div>
</div>

@php
  $deltaStrings = [
    'none' => __('No change to the carrying amount.'),
    'up'   => __('Revaluation increase of'),
    'down' => __('Revaluation decrease of'),
  ];
@endphp
<script type="application/json" id="valuation-delta-strings">{!! json_encode($deltaStrings) !!}</script>
<script>
// Live surplus/deficit hint against the previous value - no theme JS dependency.
document.addEventListener('DOMContentLoaded', function () {
  var input = document.getElementById('new_value');
  var hint = document.getElementById('valuation-delta');
  var strEl = document.getElementById('valuation-delta-strings');
  if (!input || !hint || !strEl) return;
  var str = JSON.parse(strEl.textContent);
  var prev = parseFloat(input.dataset.previous || '0') || 0;
  var base = hint.textContent;
  input.addEventListener('input', function () {
    var v = parseFloat(input.value);
    if (isNaN(v)) { hint.textContent = base; hint.className = 'form-text'; return; }
    var d = Math.round((v - prev) * 100) / 100;
    if (d === 0) {
      hint.textContent = str.none;
      hint.className = 'form-text';
    } else {
      hint.textContent = (d > 0 ? str.up : str.down) + ' ' + Math.abs(d).toFixed(2);
      hint.className = 'form-text ' + (d > 0 ? 'text-success' : 'text-danger');
    }
  });
});
</script>
@endsection
