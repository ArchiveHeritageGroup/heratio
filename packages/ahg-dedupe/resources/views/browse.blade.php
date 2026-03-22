@extends('theme::layouts.1col')

@section('title', 'Browse Duplicates')
@section('body-class', 'browse dedupe')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-clone me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">Duplicate Detection</span>
    </div>
    <div class="ms-auto">
      <a href="{{ route('dedupe.index') }}" class="btn atom-btn-white">
        <i class="fas fa-tachometer-alt me-1"></i> Dashboard
      </a>
    </div>
  </div>

  {{-- Filters --}}
  <form method="GET" action="{{ route('dedupe.browse') }}" class="row g-2 mb-4 align-items-end">
    <div class="col-auto">
      <label class="form-label small mb-1">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">All statuses</option>
        @foreach(['pending', 'confirmed', 'merged', 'dismissed'] as $opt)
          <option value="{{ $opt }}" {{ $currentStatus === $opt ? 'selected' : '' }}>{{ ucfirst($opt) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label small mb-1">Detection Method</label>
      <select name="method" class="form-select form-select-sm">
        <option value="">All methods</option>
        @foreach($methods as $m)
          <option value="{{ $m }}" {{ $currentMethod === $m ? 'selected' : '' }}>{{ $m }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label small mb-1">Min Score</label>
      <select name="min_score" class="form-select form-select-sm">
        <option value="">Any</option>
        @foreach([50, 60, 70, 75, 80, 85, 90, 95] as $s)
          <option value="{{ $s }}" {{ $currentScore == $s ? 'selected' : '' }}>{{ $s }}%</option>
        @endforeach
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn atom-btn-outline-light btn-sm">
        <i class="fas fa-filter me-1"></i> Filter
      </button>
      <a href="{{ route('dedupe.browse') }}" class="btn btn-sm atom-btn-white">Reset</a>
    </div>
  </form>

  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr style="background:var(--ahg-primary);color:#fff">
            <th style="width: 70px;">Score</th>
            <th>Record A</th>
            <th>Record B</th>
            <th>Method</th>
            <th>Status</th>
            <th>Detected</th>
            <th style="width: 140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pager->getResults() as $dup)
            <tr id="dup-row-{{ $dup['id'] }}">
              <td class="text-center">
                @php
                  $score = (float) $dup['similarity_score'];
                  $badgeClass = $score >= 90 ? 'bg-danger' : ($score >= 75 ? 'bg-warning text-dark' : 'bg-info text-dark');
                @endphp
                <span class="badge {{ $badgeClass }}">{{ number_format($score, 0) }}%</span>
              </td>
              <td>
                <a href="{{ route('informationobject.show', $dup['record_a_id']) }}">
                  {{ $dup['record_a_title'] ?: '[Untitled]' }}
                </a>
              </td>
              <td>
                <a href="{{ route('informationobject.show', $dup['record_b_id']) }}">
                  {{ $dup['record_b_title'] ?: '[Untitled]' }}
                </a>
              </td>
              <td><span class="badge bg-light text-dark">{{ $dup['detection_method'] }}</span></td>
              <td>
                @php
                  $statusColors = [
                    'pending'   => 'bg-warning text-dark',
                    'confirmed' => 'bg-danger',
                    'merged'    => 'bg-success',
                    'dismissed' => 'bg-secondary',
                  ];
                @endphp
                <span class="badge {{ $statusColors[$dup['status']] ?? 'bg-secondary' }}">{{ ucfirst($dup['status']) }}</span>
              </td>
              <td>{{ $dup['detected_at'] ? \Carbon\Carbon::parse($dup['detected_at'])->format('Y-m-d') : '' }}</td>
              <td>
                <div class="btn-group btn-group-sm">
                  <a href="{{ route('dedupe.compare', $dup['id']) }}" class="btn atom-btn-white" title="Compare">
                    <i class="fas fa-columns"></i>
                  </a>
                  @if($dup['status'] === 'pending')
                    <button type="button" class="btn atom-btn-white btn-dismiss" data-id="{{ $dup['id'] }}" title="Dismiss">
                      <i class="fas fa-times"></i>
                    </button>
                  @endif
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @include('ahg-core::components.pager', ['pager' => $pager])
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-dismiss').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-id');
            if (!confirm('Dismiss this duplicate pair?')) return;
            fetch('{{ url("/admin/dedupe/dismiss") }}/' + id, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    var row = document.getElementById('dup-row-' + id);
                    if (row) row.remove();
                }
            });
        });
    });
});
</script>
@endpush
