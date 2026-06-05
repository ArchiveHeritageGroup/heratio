{{-- heratio#1147 — Conservation forecast (simulation & prediction). --}}
@extends('theme::layouts.1col')

@section('title', __('Conservation forecast') . ' — ' . $space->name)
@section('body-class', 'exhibition-space forecast')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-2 gap-2">
    <h1 class="mb-0 flex-grow-1"><i class="fas fa-chart-line me-2"></i>{{ __('Conservation forecast') }} <small class="text-muted">{{ $space->name }}</small></h1>
    <a href="{{ route('exhibition-space.analytics', ['slug' => $space->slug]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-chart-area me-1"></i>{{ __('Analytics') }}</a>
    <a href="{{ route('exhibition-space.walkthrough', ['slug' => $space->slug]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-vr-cardboard me-1"></i>{{ __('Walkthrough') }}</a>
    <a href="{{ route('exhibition-space.builder', ['slug' => $space->slug]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-cubes me-1"></i>{{ __('Builder') }}</a>
  </div>
  <p class="text-muted small mb-3">{{ __('Projected from the last 30 days of light readings: annual light dose (lux-hours) vs the material light budget, time until the budget is reached, and visitor load. Budgets follow international museum guidance (very sensitive 50k, sensitive 150k, durable 600k lux-hours/year).') }}</p>

  {{-- What-if simulator --}}
  <div class="card mb-3">
    <div class="card-header py-2"><strong><i class="fas fa-sliders-h me-1"></i>{{ __('What-if simulator') }}</strong></div>
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-auto"><label class="form-label small mb-0">{{ __('Light level (lux)') }}</label><input type="number" id="wLux" class="form-control form-control-sm" value="200" min="0" step="10"></div>
        <div class="col-auto"><label class="form-label small mb-0">{{ __('Display hours/day') }}</label><input type="number" id="wHours" class="form-control form-control-sm" value="8" min="0" max="24" step="0.5"></div>
        <div class="col-auto"><label class="form-label small mb-0">{{ __('Lux target') }}</label><input type="number" id="wTarget" class="form-control form-control-sm" value="200" min="0" step="10"></div>
      </div>
      <div id="wOut" class="mt-3 small"></div>
    </div>
  </div>

  {{-- Per-room forecast --}}
  <div class="card">
    <div class="card-header py-2"><strong>{{ __('Per-room forecast') }}</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 small align-middle">
          <thead class="table-light">
            <tr>
              <th>{{ __('Room') }}</th>
              <th class="text-end">{{ __('Avg lux') }}</th>
              <th class="text-end">{{ __('Target') }}</th>
              <th class="text-end">{{ __('Annual dose') }}</th>
              <th class="text-end">{{ __('Budget') }}</th>
              <th class="text-end">{{ __('% of budget') }}</th>
              <th class="text-end">{{ __('Days to budget') }}</th>
              <th>{{ __('Risk') }}</th>
              <th class="text-end">{{ __('Visitors (avg/peak)') }}</th>
            </tr>
          </thead>
          <tbody>
          @forelse($rooms as $r)
            @php $badge = ['ok'=>'success','warn'=>'warning','alert'=>'danger','none'=>'secondary'][$r['risk']] ?? 'secondary'; @endphp
            <tr>
              <td>{{ $r['name'] }}</td>
              <td class="text-end">{{ $r['avg_lux'] !== null ? $r['avg_lux'] : '—' }}</td>
              <td class="text-end">{{ $r['lux_target'] !== null ? (int) $r['lux_target'] : '—' }}</td>
              <td class="text-end">{{ $r['annual_dose'] !== null ? number_format($r['annual_dose']) : '—' }}</td>
              <td class="text-end">{{ number_format($r['budget']) }}</td>
              <td class="text-end">{{ $r['pct_of_budget'] !== null ? $r['pct_of_budget'].'%' : '—' }}</td>
              <td class="text-end">{{ $r['days_to_budget'] !== null ? number_format($r['days_to_budget']) : '—' }}</td>
              <td><span class="badge bg-{{ $badge }}">{{ strtoupper($r['risk']) }}</span></td>
              <td class="text-end">
                {{ $r['avg_visitors'] !== null ? $r['avg_visitors'] : '—' }} / {{ $r['peak_visitors'] !== null ? (int) $r['peak_visitors'] : '—' }}
                @if($r['capacity'] !== null)<small class="text-muted"> ({{ (int) $r['capacity'] }})</small>@endif
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="p-3 text-muted">{{ __('No rooms in this building.') }}</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <p class="text-muted small mt-2">{{ __('No readings yet? Open the Digital Twin Builder and use "Simulate live data", or POST sensor readings to the space readings endpoint.') }}</p>

  <script nonce="{{ $cspNonce ?? '' }}">
  (function () {
    var OPEN_DAYS = 312;
    function budgetFor(t) { if (t === '' || t == null) return 150000; t = +t; if (t <= 50) return 50000; if (t <= 200) return 150000; return 600000; }
    function calc() {
      var lux = parseFloat(document.getElementById('wLux').value) || 0;
      var hrs = parseFloat(document.getElementById('wHours').value) || 0;
      var target = document.getElementById('wTarget').value;
      var budget = budgetFor(target);
      var annual = lux * hrs * OPEN_DAYS;
      var pct = budget > 0 ? (annual / budget * 100) : 0;
      var days = (lux > 0 && hrs > 0) ? Math.round(budget / (lux * hrs)) : null;
      var risk = pct > 150 ? ['danger', 'ALERT'] : (pct > 100 ? ['warning', 'WARN'] : ['success', 'OK']);
      document.getElementById('wOut').innerHTML =
        '{{ __('Annual dose') }}: <b>' + Math.round(annual).toLocaleString() + '</b> {{ __('lux-hours') }} &middot; ' +
        '{{ __('Budget') }}: ' + budget.toLocaleString() + ' &middot; ' +
        '<b>' + pct.toFixed(1) + '%</b> {{ __('of budget') }} ' +
        '<span class="badge bg-' + risk[0] + '">' + risk[1] + '</span> &middot; ' +
        '{{ __('Reaches budget in') }} <b>' + (days != null ? days.toLocaleString() + ' {{ __('display-days') }}' : '—') + '</b>';
    }
    ['wLux', 'wHours', 'wTarget'].forEach(function (id) { document.getElementById(id).addEventListener('input', calc); });
    calc();
  })();
  </script>
@endsection
