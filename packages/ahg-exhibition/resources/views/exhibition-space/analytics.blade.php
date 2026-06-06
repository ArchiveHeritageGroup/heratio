{{-- heratio#1148 — Analytics dashboard: historical reading trends. --}}
@extends('theme::layouts.1col')

@section('title', __('Analytics') . ' — ' . $space->name)
@section('body-class', 'exhibition-space analytics')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-2 gap-2">
    <h1 class="mb-0 flex-grow-1"><i class="fas fa-chart-area me-2"></i>{{ __('Analytics') }} <small class="text-muted">{{ $space->name }}</small></h1>
    <a href="{{ route('exhibition-space.forecast', ['slug' => $space->slug]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-chart-line me-1"></i>{{ __('Forecast') }}</a>
    <a href="{{ route('exhibition-space.walkthrough', ['slug' => $space->slug]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-vr-cardboard me-1"></i>{{ __('Walkthrough') }}</a>
  </div>
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <span class="small text-muted">{{ __('Period') }}:</span>
    @foreach(['1' => __('24 hours'), '7' => __('7 days'), '30' => __('30 days'), '90' => __('90 days')] as $d => $lbl)
      <a href="{{ route('exhibition-space.analytics', ['slug' => $space->slug, 'days' => $d]) }}" class="btn btn-sm {{ (int)$days === (int)$d ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $lbl }}</a>
    @endforeach
    <span class="small text-muted ms-2">{{ __('Bucketed by') }} {{ $data['bucket'] }}</span>
  </div>

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
          <div class="fw-bold small mb-1">{{ __('Most-viewed objects') }}</div>
          @forelse($visitors['top_objects'] as $o)
            <div class="d-flex justify-content-between small border-bottom py-1"><span>{{ \Illuminate\Support\Str::limit($o['title'], 40) }}</span><span>{{ $o['views'] }}</span></div>
          @empty<div class="text-muted small">{{ __('No data yet.') }}</div>@endforelse
        </div>
      </div>
    </div>
  </div>
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
