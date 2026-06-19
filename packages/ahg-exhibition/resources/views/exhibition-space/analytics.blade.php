{{-- heratio#1148 — Analytics dashboard: historical reading trends. --}}
@extends('theme::layouts.1col')

@section('title', __('Analytics') . ' — ' . $space->name)
@section('body-class', 'exhibition-space analytics')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-2 gap-2">
    <h1 class="mb-0 flex-grow-1"><i class="fas fa-chart-area me-2"></i>{{ __('Analytics') }} <small class="text-muted">{{ $space->name }}</small></h1>
    @include('ahg-exhibition::exhibition-space._nav-actions', ['space' => $space, 'current' => 'analytics'])
  </div>
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <span class="small text-muted">{{ __('Period') }}:</span>
    @foreach(['1' => __('24 hours'), '7' => __('7 days'), '30' => __('30 days'), '90' => __('90 days'), '3650' => __('All')] as $d => $lbl)
      <a href="{{ route('exhibition-space.analytics', ['slug' => $space->slug, 'days' => $d]) }}" class="btn btn-sm {{ (int)$days === (int)$d ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $lbl }}</a>
    @endforeach
    <span class="small text-muted ms-2">{{ __('Bucketed by') }} {{ $data['bucket'] }}</span>
  </div>

  {{-- heratio#1188 - live sensor binding + conservation alerts (staff only: token is sensitive) --}}
  @isset($sensor)
  @if($sensor)
  <div class="card mb-3">
    <div class="card-header py-2"><strong><i class="fas fa-satellite-dish me-1"></i>{{ __('Live sensor feed') }}</strong> <small class="text-muted">{{ __('bind a real light / temperature / humidity sensor to this space') }}</small></div>
    <div class="card-body">
      @if(count($sensor['alerts']))
        <div class="mb-3">
          <div class="fw-bold small mb-1 text-danger"><i class="fas fa-triangle-exclamation me-1"></i>{{ __('Conservation alerts') }}</div>
          @foreach($sensor['alerts'] as $a)
            <div class="alert alert-{{ $a['severity'] === 'critical' ? 'danger' : 'warning' }} py-1 px-2 mb-1 small d-flex justify-content-between">
              <span>{{ $a['message'] }}</span><span class="text-muted ms-2">{{ \Illuminate\Support\Carbon::parse($a['at'])->diffForHumans() }}</span>
            </div>
          @endforeach
        </div>
      @else
        <div class="small text-muted mb-2"><i class="fas fa-check-circle text-success me-1"></i>{{ __('No conservation alerts.') }}</div>
      @endif
      <div class="small mb-1">{{ __('Sensors POST readings to this endpoint with the space token:') }}</div>
      <pre class="small bg-light p-2 rounded" style="white-space:pre-wrap">curl -X POST {{ url('/exhibition-space/sensor/ingest') }} \
  -H "X-Sensor-Token: {{ $sensor['token'] }}" -H "Content-Type: application/json" \
  -d '{"readings":[{"metric":"temp_c","value":21.5},{"metric":"humidity","value":52},{"metric":"lux","value":180}]}'</pre>
      <div class="d-flex align-items-center gap-2">
        <code class="small">{{ $sensor['token'] }}</code>
        <button type="button" id="sxRegen" class="btn btn-sm btn-outline-danger">{{ __('Regenerate token') }}</button>
        <span class="small text-muted">{{ __('Thresholds: temp 16-24 C, humidity 40-60% RH, light <= 200 lux (or the space target).') }}</span>
      </div>
    </div>
  </div>
  <script nonce="{{ $cspNonce ?? '' }}">
  (function () {
    var btn = document.getElementById('sxRegen'); if (!btn) return;
    btn.addEventListener('click', function () {
      if (!confirm('{{ __('Regenerate the token? Any sensor using the old token will stop working until updated.') }}')) return;
      fetch('{{ route('exhibition-space.sensor.regenerate', ['slug' => $space->slug]) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); }).then(function (d) { if (d && d.ok) location.reload(); });
    });
  })();
  </script>
  @endif
  @endisset

  {{-- heratio#1173 - automatic visitor analytics --}}
  @isset($visitors)
  <div class="card mb-3">
    <div class="card-header py-2"><strong><i class="fas fa-users me-1"></i>{{ __('Visitors') }}</strong> <small class="text-muted">{{ __('tracked automatically from the walkthrough') }}</small></div>
    <div class="card-body">
      <div class="row text-center g-2 mb-3">
        <div class="col"><div class="h4 mb-0">{{ $visitors['sessions'] }}</div><div class="small text-muted">{{ __('Sessions') }}</div></div>
        <div class="col"><div class="h4 mb-0">{{ gmdate('i:s', (int) $visitors['avg_seconds']) }}</div><div class="small text-muted">{{ __('Avg visit') }}</div></div>
        <div class="col"><div>@forelse($visitors['devices'] as $dev => $n)<span class="badge bg-secondary me-1">{{ $dev }}: {{ $n }}</span>@empty<span class="text-muted small">-</span>@endforelse</div><div class="small text-muted">{{ __('Devices') }}</div></div>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="fw-bold small mb-1">{{ __('Dwell time per room') }}</div>
          @forelse($visitors['dwell'] as $d)
            <div class="d-flex justify-content-between small border-bottom py-1"><span>{{ $d['room'] }}</span><span>{{ gmdate('i:s', (int) $d['seconds']) }}</span></div>
          @empty<div class="text-muted small">{{ __('No data yet.') }}</div>@endforelse
        </div>
        <div class="col-md-6">
          <div class="fw-bold small mb-1">{{ __('Object attention') }} <span class="fw-normal text-muted">({{ __('views · dwell') }})</span></div>
          @forelse($visitors['top_objects'] as $o)
            <div class="d-flex justify-content-between small border-bottom py-1"><span>{{ \Illuminate\Support\Str::limit($o['title'], 40) }}</span><span class="text-nowrap ms-2">{{ $o['views'] }}@if(($o['seconds'] ?? 0) > 0) · {{ gmdate('i:s', (int) $o['seconds']) }}@endif</span></div>
          @empty<div class="text-muted small">{{ __('No data yet.') }}</div>@endforelse
        </div>
      </div>
    </div>
  </div>
  @endisset

  {{-- heratio#1187 - visitor heatmap (rooms shaded by dwell, dots = object attention) --}}
  @isset($heatmap)
  @if(count($heatmap['rooms']))
  <div class="card mb-3">
    <div class="card-header py-2"><strong><i class="fas fa-fire me-1"></i>{{ __('Visitor heatmap') }}</strong> <small class="text-muted">{{ __('rooms shaded by time spent; dot size = dwell on each object (attention)') }}</small></div>
    <div class="card-body">
      <canvas id="heatCanvas" style="width:100%;max-width:760px;display:block;margin:0 auto;background:#f8f9fa;border-radius:6px"></canvas>
      <div class="small text-muted mt-2 d-flex align-items-center gap-2 justify-content-center">
        <span>{{ __('Less time') }}</span>
        <span style="display:inline-block;width:120px;height:10px;border-radius:5px;background:linear-gradient(90deg,#dfe7ef,#4e9be8,#46c06b,#f4d03f,#e8553a)"></span>
        <span>{{ __('More time') }}</span>
      </div>
      <div class="small text-muted mt-1 text-center">{{ __('Object dots grow and deepen with dwell: large = draws attention, faint = seen but skipped.') }}</div>
    </div>
  </div>
  <script nonce="{{ $cspNonce ?? '' }}">
  (function () {
    var H = @json($heatmap);
    var cv = document.getElementById('heatCanvas'); if (!cv || !H.rooms || !H.rooms.length) return;
    var W = 760, Hh = 430; cv.width = W; cv.height = Hh;
    var ctx = cv.getContext('2d');
    var bw = (H.max_x - H.min_x) || 1, bd = (H.max_z - H.min_z) || 1, pad = 26;
    var sc = Math.min((W - 2 * pad) / bw, (Hh - 2 * pad) / bd);
    var ox = (W - bw * sc) / 2 - H.min_x * sc, oy = (Hh - bd * sc) / 2 - H.min_z * sc;
    function heat(r) {
      var st = [[223,231,239],[78,155,232],[70,192,107],[244,208,63],[232,85,58]];
      var t = Math.max(0, Math.min(1, r)) * (st.length - 1), i = Math.floor(t), f = t - i;
      var a = st[i], b = st[Math.min(i + 1, st.length - 1)];
      return 'rgb(' + Math.round(a[0]+(b[0]-a[0])*f) + ',' + Math.round(a[1]+(b[1]-a[1])*f) + ',' + Math.round(a[2]+(b[2]-a[2])*f) + ')';
    }
    ctx.clearRect(0, 0, W, Hh);
    H.rooms.forEach(function (rm) {
      var x = ox + rm.x * sc, y = oy + rm.z * sc, w = rm.w * sc, h = rm.d * sc;
      var ratio = H.max_seconds > 0 ? rm.seconds / H.max_seconds : 0;
      ctx.fillStyle = rm.seconds > 0 ? heat(ratio) : '#eef1f4';
      ctx.fillRect(x, y, w, h);
      ctx.strokeStyle = '#adb5bd'; ctx.lineWidth = 1; ctx.strokeRect(x, y, w, h);
      ctx.fillStyle = '#212529'; ctx.font = 'bold 11px sans-serif'; ctx.textAlign = 'center';
      ctx.fillText(rm.name || '', x + w / 2, y + h / 2 - 3);
      ctx.fillStyle = '#495057'; ctx.font = '10px sans-serif';
      var lbl = rm.seconds > 0 ? (Math.floor(rm.seconds / 60) + 'm ' + (rm.seconds % 60) + 's') : '{{ __('no visits') }}';
      ctx.fillText(lbl, x + w / 2, y + h / 2 + 11);
    });
    (H.objects || []).forEach(function (o) {
      var x = ox + o.x * sc, y = oy + o.z * sc;
      // #1187 attention = dwell on the object; fall back to view count until dwell accrues.
      var aRatio = H.max_object_seconds > 0 ? (o.seconds / H.max_object_seconds)
                 : (H.max_views > 0 ? (o.views / H.max_views) : 0);
      var rr = 4 + aRatio * 12;
      ctx.beginPath(); ctx.arc(x, y, rr, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(232,85,58,' + (0.30 + 0.55 * aRatio).toFixed(2) + ')'; ctx.fill();
      ctx.strokeStyle = 'rgba(150,30,15,0.85)'; ctx.lineWidth = 1; ctx.stroke();
      if (o.seconds > 0) {
        ctx.fillStyle = '#7a1d0c'; ctx.font = '9px sans-serif'; ctx.textAlign = 'center';
        ctx.fillText(Math.floor(o.seconds / 60) + 'm ' + (o.seconds % 60) + 's', x, y - rr - 2);
      }
    });
  })();
  </script>
  @endif
  @endisset

  @if(count($data['labels']) === 0)
    <div class="alert alert-info">{{ __('No readings in this period yet. Use "Simulate live data" in the Digital Twin Builder, or POST sensor readings to the space readings endpoint.') }}</div>
  @endif

  <div class="row g-3" id="charts">
    @foreach(['lux' => __('Light (lux)'), 'temp_c' => __('Temperature (C)'), 'humidity' => __('Humidity (%)'), 'visitors' => __('Visitors')] as $m => $title)
      <div class="col-lg-6">
        <div class="card"><div class="card-header py-2"><strong>{{ $title }}</strong></div>
          <div class="card-body"><canvas id="chart-{{ $m }}" height="160" data-metric="{{ $m }}"></canvas></div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="card mt-3">
    <div class="card-header py-2"><strong>{{ __('Summary') }}</strong></div>
    <div class="card-body p-0"><div class="table-responsive">
      <table class="table table-sm table-hover mb-0 small align-middle">
        <thead class="table-light"><tr>
          <th>{{ __('Room') }}</th>
          <th class="text-end">{{ __('Light avg/latest') }}</th>
          <th class="text-end">{{ __('Temp avg/latest') }}</th>
          <th class="text-end">{{ __('Humidity avg/latest') }}</th>
          <th class="text-end">{{ __('Visitors avg/peak') }}</th>
        </tr></thead>
        <tbody>
        @foreach($data['rooms'] as $rm)
          @php $s = $data['summary'][$rm['id']]; @endphp
          <tr>
            <td>{{ $rm['name'] }}</td>
            <td class="text-end">{{ $s['lux']['avg'] ?? '—' }} / {{ $s['lux']['latest'] ?? '—' }}</td>
            <td class="text-end">{{ $s['temp_c']['avg'] ?? '—' }} / {{ $s['temp_c']['latest'] ?? '—' }}</td>
            <td class="text-end">{{ $s['humidity']['avg'] ?? '—' }} / {{ $s['humidity']['latest'] ?? '—' }}</td>
            <td class="text-end">{{ $s['visitors']['avg'] ?? '—' }} / {{ $s['visitors']['max'] ?? '—' }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div></div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script nonce="{{ $cspNonce ?? '' }}">
  (function () {
    var DATA = @json($data);
    if (typeof Chart === 'undefined') return;
    function colour(i) { return 'hsl(' + ((i * 67) % 360) + ',65%,48%)'; }
    DATA.metrics.forEach(function (m) {
      var cv = document.getElementById('chart-' + m); if (!cv) return;
      var datasets = DATA.rooms.map(function (rm, i) {
        return { label: rm.name, data: (DATA.series[m] && DATA.series[m][rm.id]) ? DATA.series[m][rm.id] : [], borderColor: colour(i), backgroundColor: colour(i), spanGaps: true, tension: 0.25, pointRadius: 2, borderWidth: 2 };
      });
      new Chart(cv.getContext('2d'), {
        type: 'line',
        data: { labels: DATA.labels, datasets: datasets },
        options: { responsive: true, interaction: { mode: 'nearest' }, plugins: { legend: { labels: { boxWidth: 10, font: { size: 10 } } } }, scales: { x: { ticks: { maxTicksLimit: 8, font: { size: 9 } } }, y: { beginAtZero: true } } }
      });
    });
  })();
  </script>
@endsection
