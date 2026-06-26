@extends('theme::layouts.1col')

@section('title', __('RDM Dashboard'))
@section('body-class', 'rdm dashboard')

@section('content')
@php
  $k = $d['kpi'];
  $verdictColors     = ['CLEAR' => '#198754', 'PERSONAL' => '#fd7e14', 'SPECIAL_CATEGORY' => '#dc3545', 'unscanned' => '#adb5bd'];
  $dispositionColors = ['restrict' => '#dc3545', 'embargo' => '#fd7e14', 'de-identify' => '#0dcaf0', 'release' => '#198754', 'undecided' => '#adb5bd'];
  $methodColors      = ['deterministic' => '#0d6efd', 'lexicon' => '#6f42c1', 'ner' => '#20c997'];
@endphp

<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 mb-0"><i class="fas fa-gauge-high me-2"></i>{{ __('RDM Dashboard') }}</h1>
  <div class="d-flex gap-2">
    <a href="{{ route('rdm.datasets.compliance') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-clipboard-check me-1"></i>{{ __('Compliance scoreboard') }}</a>
    <a href="{{ route('rdm.datasets.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('All datasets') }}</a>
    <a href="{{ route('rdm.datasets.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>{{ __('New dataset') }}</a>
  </div>
</div>

{{-- Defensibility one-liner --}}
<div class="alert alert-light border d-flex flex-wrap gap-3 align-items-center small mb-3">
  <span><i class="fas fa-shield-halved text-success me-1"></i><strong>{{ $k['datasets'] }}</strong> {{ __('datasets') }}</span>
  <span class="text-danger"><strong>{{ $k['flagged'] }}</strong> {{ __('POPIA-flagged') }}</span>
  <span class="text-warning"><strong>{{ $k['restricted'] }}</strong> {{ __('restricted/embargoed') }}</span>
  <span class="text-info"><strong>{{ $k['dmp_linked'] }}</strong> {{ __('DMP-linked') }} ({{ $k['dmp_pct'] }}%)</span>
  @if ($k['backlog'] > 0)
    <span class="ms-auto badge bg-warning text-dark"><i class="fas fa-gavel me-1"></i>{{ $k['backlog'] }} {{ __('awaiting human review') }}</span>
  @else
    <span class="ms-auto badge bg-success"><i class="fas fa-check me-1"></i>{{ __('No review backlog') }}</span>
  @endif
</div>

{{-- KPI cards --}}
<div class="row g-2 mb-3">
  @foreach ([
    ['datasets','Datasets','#0d6efd','fa-database'],
    ['files','Files deposited','#6c757d','fa-file'],
    ['flagged','POPIA-flagged','#dc3545','fa-user-shield'],
    ['backlog','Awaiting review','#fd7e14','fa-gavel'],
    ['restricted','Restricted','#fd7e14','fa-lock'],
    ['open','Open access','#198754','fa-lock-open'],
    ['dois','DOIs minted','#0dcaf0','fa-fingerprint'],
    ['dmp_linked','DMP-linked','#20c997','fa-clipboard-list'],
  ] as [$key,$label,$color,$icon])
    <div class="col-6 col-md-3 col-xl">
      <div class="card h-100"><div class="card-body py-2 text-center">
        <div class="small text-muted"><i class="fas {{ $icon }} me-1"></i>{{ __($label) }}</div>
        <div class="h4 mb-0" style="color:{{ $color }}">{{ $k[$key] }}</div>
      </div></div>
    </div>
  @endforeach
</div>

{{-- Charts row 1 --}}
<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card h-100"><div class="card-header fw-bold py-2 small">{{ __('POPIA verdict') }}</div>
      <div class="card-body"><canvas id="verdictChart" height="200"></canvas></div></div>
  </div>
  <div class="col-md-4">
    <div class="card h-100"><div class="card-header fw-bold py-2 small">{{ __('Access disposition') }}</div>
      <div class="card-body"><canvas id="dispositionChart" height="200"></canvas></div></div>
  </div>
  <div class="col-md-4">
    <div class="card h-100"><div class="card-header fw-bold py-2 small">{{ __('Detection method') }} <span class="text-muted">({{ __('rule vs AI') }})</span></div>
      <div class="card-body"><canvas id="methodChart" height="200"></canvas></div></div>
  </div>
</div>

{{-- Charts row 2 --}}
<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="card h-100"><div class="card-header fw-bold py-2 small">{{ __('Findings by PII type') }}</div>
      <div class="card-body"><canvas id="typeChart" height="220"></canvas></div></div>
  </div>
  <div class="col-md-6">
    <div class="card h-100"><div class="card-header fw-bold py-2 small">{{ __('Deposits (last 12 months)') }}</div>
      <div class="card-body"><canvas id="trendChart" height="220"></canvas></div></div>
  </div>
</div>

{{-- Per-faculty posture + gate backlog --}}
<div class="row g-3 mb-3">
  <div class="col-md-7">
    <div class="card h-100">
      <div class="card-header fw-bold py-2 small">{{ __('Posture by faculty / institution') }}</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0 align-middle">
          <thead><tr><th>{{ __('Faculty') }}</th><th class="text-end">{{ __('Datasets') }}</th><th class="text-end">{{ __('Flagged') }}</th><th class="text-end">{{ __('DMP') }}</th></tr></thead>
          <tbody>
            @forelse ($d['by_institution'] as $row)
              <tr>
                <td class="small">{{ $row->institution }}</td>
                <td class="text-end">{{ (int) $row->total }}</td>
                <td class="text-end">@if ((int) $row->flagged > 0)<span class="badge bg-danger">{{ (int) $row->flagged }}</span>@else <span class="text-muted">0</span>@endif</td>
                <td class="text-end">@if ($d['has_dmp'])<span class="text-info">{{ (int) $row->dmp }}</span>@else <span class="text-muted">—</span>@endif</td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted py-3">{{ __('No datasets yet.') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card h-100">
      <div class="card-header fw-bold py-2 small"><i class="fas fa-gavel me-1"></i>{{ __('Human-gate backlog') }}</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0 align-middle">
          <thead><tr><th>{{ __('Dataset') }}</th><th>{{ __('Verdict') }}</th><th class="text-end">{{ __('Pending') }}</th></tr></thead>
          <tbody>
            @forelse ($d['backlog_list'] as $row)
              <tr>
                <td class="small"><a href="{{ route('rdm.datasets.show', $row->id) }}">{{ \Illuminate\Support\Str::limit($row->title, 32) }}</a></td>
                <td>@if ($row->verdict)<span class="badge bg-{{ $row->verdict === 'SPECIAL_CATEGORY' ? 'danger' : 'warning' }} text-dark">{{ $row->verdict }}</span>@else —@endif</td>
                <td class="text-end"><span class="badge bg-warning text-dark">{{ (int) $row->pending }}</span></td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center text-success py-3"><i class="fas fa-check-circle me-1"></i>{{ __('Nothing awaiting review.') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

{{-- Recent deposits --}}
<div class="card mb-3">
  <div class="card-header fw-bold py-2 small">{{ __('Recent deposits') }}</div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0 align-middle">
      <thead><tr><th>{{ __('Dataset') }}</th><th>{{ __('Status') }}</th><th>{{ __('Verdict') }}</th><th>{{ __('Access') }}</th><th>{{ __('DOI') }}</th><th>{{ __('Deposited') }}</th></tr></thead>
      <tbody>
        @forelse ($d['recent'] as $row)
          <tr>
            <td class="small"><a href="{{ route('rdm.datasets.show', $row->id) }}">{{ \Illuminate\Support\Str::limit($row->title, 40) }}</a></td>
            <td class="small text-muted">{{ $row->status }}</td>
            <td>@if ($row->verdict)<span class="badge" style="background:{{ $verdictColors[$row->verdict] ?? '#6c757d' }}">{{ $row->verdict }}</span>@else <span class="text-muted small">{{ __('not scanned') }}</span>@endif</td>
            <td class="small">{{ $row->disposition ?? '—' }}</td>
            <td class="small">@if ($row->doi)<code>{{ $row->doi }}</code>@else <span class="text-muted">—</span>@endif</td>
            <td class="small text-muted">{{ \Illuminate\Support\Str::limit((string) $row->created_at, 10, '') }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No deposits yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
  if (typeof Chart === 'undefined') return;
  Chart.defaults.font.size = 11;
  Chart.defaults.plugins.legend.position = 'bottom';

  const verdict     = @json($d['verdict']);
  const disposition = @json($d['disposition']);
  const byMethod    = @json($d['findings_by_method']);
  const byType      = @json($d['findings_by_type']);
  const trend       = @json($d['deposits_by_month']);
  const vColors     = @json($verdictColors);
  const dColors     = @json($dispositionColors);
  const mColors     = @json($methodColors);

  const pruneZero = (obj) => Object.fromEntries(Object.entries(obj).filter(([, v]) => Number(v) > 0));

  const doughnut = (id, dataObj, colorMap, fallback) => {
    const el = document.getElementById(id);
    if (!el) return;
    const data = pruneZero(dataObj);
    const labels = Object.keys(data);
    if (!labels.length) { el.parentNode.innerHTML = '<p class="text-muted small text-center mb-0 py-4">' + (fallback || 'No data') + '</p>'; return; }
    new Chart(el, {
      type: 'doughnut',
      data: { labels, datasets: [{ data: Object.values(data).map(Number),
        backgroundColor: labels.map((l) => (colorMap && colorMap[l]) || '#0d6efd') }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '55%' },
    });
  };

  doughnut('verdictChart', verdict, vColors, 'No datasets yet');
  doughnut('dispositionChart', disposition, dColors, 'No datasets yet');
  doughnut('methodChart', byMethod, mColors, 'No findings yet');

  const typeEl = document.getElementById('typeChart');
  if (typeEl) {
    const labels = Object.keys(byType);
    if (!labels.length) { typeEl.parentNode.innerHTML = '<p class="text-muted small text-center mb-0 py-4">No findings yet</p>'; }
    else new Chart(typeEl, {
      type: 'bar',
      data: { labels, datasets: [{ label: 'Findings', data: Object.values(byType).map(Number), backgroundColor: '#dc3545' }] },
      options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } },
    });
  }

  const trendEl = document.getElementById('trendChart');
  if (trendEl) new Chart(trendEl, {
    type: 'line',
    data: { labels: trend.map((p) => p.label),
      datasets: [{ label: 'Deposits', data: trend.map((p) => p.count), borderColor: '#0d6efd',
        backgroundColor: 'rgba(13,110,253,.1)', fill: true, tension: .3 }] },
    options: { responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
  });
})();
</script>
@endpush
