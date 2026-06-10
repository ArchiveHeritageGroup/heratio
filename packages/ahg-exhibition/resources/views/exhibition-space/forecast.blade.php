{{-- heratio#1147 — Conservation forecast (simulation & prediction). --}}
@extends('theme::layouts.1col')

@section('title', __('Conservation forecast') . ' — ' . $space->name)
@section('body-class', 'exhibition-space forecast')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-2 gap-2">
    <h1 class="mb-0 flex-grow-1"><i class="fas fa-chart-line me-2"></i>{{ __('Conservation forecast') }} <small class="text-muted">{{ $space->name }}</small></h1>
    @include('ahg-exhibition::exhibition-space._nav-actions', ['space' => $space, 'current' => 'forecast'])
  </div>
  <p class="text-muted small mb-3">{{ __('Projected from the last 30 days of light readings: annual light dose (lux-hours) vs the material light budget, time until the budget is reached, and visitor load. Budgets follow international museum guidance (very sensitive 50k, sensitive 150k, durable 600k lux-hours/year).') }}</p>

  {{-- heratio#1189 - conservation time-scrubber: drag time, watch the rooms change colour --}}
  @isset($timeline)
  @if(count($timeline['rooms']))
  <div class="card mb-3">
    <div class="card-header py-2"><strong><i class="fas fa-clock-rotate-left me-1"></i>{{ __('Conservation time machine') }}</strong> <small class="text-muted">{{ __('drag time; rooms shade by conservation status') }}</small></div>
    <div class="card-body">
      <canvas id="tlCanvas" style="width:100%;max-width:760px;display:block;margin:0 auto;background:#f8f9fa;border-radius:6px"></canvas>
      <div class="d-flex align-items-center gap-2 mt-2" style="max-width:760px;margin:0 auto">
        <input type="range" id="tlSlider" class="form-range flex-grow-1" min="0" max="0" step="1">
        <span id="tlLabel" class="badge bg-secondary" style="min-width:120px"></span>
      </div>
      <div class="small text-muted mt-1 d-flex gap-3 justify-content-center">
        <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#46c06b"></span> {{ __('OK') }}</span>
        <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#f4d03f"></span> {{ __('Watch') }}</span>
        <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#e8553a"></span> {{ __('At risk') }}</span>
        <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#eef1f4"></span> {{ __('No data') }}</span>
      </div>
    </div>
  </div>
  <script nonce="{{ $cspNonce ?? '' }}">
  (function () {
    var T = @json($timeline);
    var cv = document.getElementById('tlCanvas'); if (!cv || !T.rooms || !T.rooms.length || !T.buckets.length) return;
    var slider = document.getElementById('tlSlider'), label = document.getElementById('tlLabel');
    var W = 760, H = 430; cv.width = W; cv.height = H; var ctx = cv.getContext('2d');
    var bw = (T.max_x - T.min_x) || 1, bd = (T.max_z - T.min_z) || 1, pad = 26;
    var sc = Math.min((W - 2 * pad) / bw, (H - 2 * pad) / bd);
    var ox = (W - bw * sc) / 2 - T.min_x * sc, oy = (H - bd * sc) / 2 - T.min_z * sc;
    var COL = { green: '#46c06b', amber: '#f4d03f', red: '#e8553a', none: '#eef1f4' };
    // default the slider to "today" (last historical bucket)
    var todayIdx = 0; for (var i = 0; i < T.buckets.length; i++) { if (!T.buckets[i].future) todayIdx = i; }
    slider.max = T.buckets.length - 1; slider.value = todayIdx;
    function draw(idx) {
      var b = T.buckets[idx];
      label.textContent = b.label + (b.future ? ' · {{ __('projected') }}' : '');
      label.className = 'badge ' + (b.future ? 'bg-info' : 'bg-secondary');
      ctx.clearRect(0, 0, W, H);
      T.rooms.forEach(function (rm) {
        var st = (T.status[rm.id] || [])[idx] || 'none';
        var x = ox + rm.x * sc, y = oy + rm.z * sc, w = rm.w * sc, h = rm.d * sc;
        ctx.fillStyle = COL[st] || COL.none; ctx.fillRect(x, y, w, h);
        ctx.strokeStyle = '#adb5bd'; ctx.lineWidth = 1; ctx.strokeRect(x, y, w, h);
        ctx.fillStyle = '#212529'; ctx.font = 'bold 11px sans-serif'; ctx.textAlign = 'center';
        ctx.fillText(rm.name || '', x + w / 2, y + h / 2 + 3);
      });
    }
    slider.addEventListener('input', function () { draw(+slider.value); });
    draw(todayIdx);
  })();
  </script>
  @endif
  @endisset

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
