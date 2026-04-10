@extends('theme::layouts.1col')

@section('title', 'Duplicate Detection')
@section('body-class', 'admin dedupe')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-clone me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Duplicate Detection</h1>
      <span class="small text-muted">Dashboard</span>
    </div>
    <div class="ms-auto d-flex gap-2">
      <a href="{{ route('dedupe.scan') }}" class="btn atom-btn-outline-success">
        <i class="fas fa-search me-1"></i> New Scan
      </a>
      <a href="{{ route('dedupe.rules') }}" class="btn atom-btn-white">
        <i class="fas fa-cog me-1"></i> Rules
      </a>
      <a href="{{ route('dedupe.browse') }}" class="btn atom-btn-white">
        <i class="fas fa-list me-1"></i> Browse All
      </a>
    </div>
  </div>

  {{-- Stat cards --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold">{{ number_format($stats['total']) }}</div>
          <div class="small text-muted">Total Detected</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center border-warning">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-warning">{{ number_format($stats['pending']) }}</div>
          <div class="small text-muted">Pending Review</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center border-danger">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-danger">{{ number_format($stats['confirmed']) }}</div>
          <div class="small text-muted">Confirmed</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center border-success">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-success">{{ number_format($stats['merged']) }}</div>
          <div class="small text-muted">Merged</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center border-secondary">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-secondary">{{ number_format($stats['dismissed']) }}</div>
          <div class="small text-muted">Dismissed</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card text-center border-primary">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-primary">{{ number_format($stats['activeRules']) }}</div>
          <div class="small text-muted">Active Rules</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    {{-- Main content: Pending Review --}}
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header d-flex align-items-center" style="background:var(--ahg-primary);color:#fff">
          <strong>Pending Review</strong>
          <span class="badge bg-warning text-dark ms-2">{{ number_format($stats['pending']) }}</span>
          <a href="{{ route('dedupe.browse', ['status' => 'pending']) }}" class="ms-auto small">View all</a>
        </div>
        <div class="card-body p-0">
          @if($topPending->isEmpty())
            <div class="p-3 text-muted">No pending duplicates.</div>
          @else
            <div class="table-responsive">
              <table class="table table-bordered table-striped mb-0">
                <thead>
                  <tr>
                    <th style="width: 70px;">Score</th>
                    <th>Record A</th>
                    <th>Record B</th>
                    <th>Method</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($topPending as $dup)
                    <tr id="dup-row-{{ $dup->id }}">
                      <td class="text-center">
                        @php
                          $score = (float) $dup->similarity_score;
                          $badgeClass = $score >= 90 ? 'bg-danger' : ($score >= 75 ? 'bg-warning text-dark' : 'bg-info text-dark');
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ number_format($score, 0) }}%</span>
                      </td>
                      <td>
                        <a href="{{ route('informationobject.show', $dup->record_a_id) }}">
                          {{ Str::limit($dup->record_a_title ?: '[Untitled]', 40) }}
                        </a>
                      </td>
                      <td>
                        <a href="{{ route('informationobject.show', $dup->record_b_id) }}">
                          {{ Str::limit($dup->record_b_title ?: '[Untitled]', 40) }}
                        </a>
                      </td>
                      <td><span class="badge bg-light text-dark">{{ $dup->detection_method }}</span></td>
                      <td>
                        <div class="btn-group btn-group-sm">
                          <a href="{{ route('dedupe.compare', $dup->id) }}" class="btn atom-btn-white" title="Compare">
                            <i class="fas fa-columns"></i>
                          </a>
                          <a href="{{ route('dedupe.merge', $dup->id) }}" class="btn atom-btn-white" title="Merge">
                            <i class="fas fa-compress-arrows-alt"></i>
                          </a>
                          <button type="button" class="btn atom-btn-white btn-dismiss" data-id="{{ $dup->id }}" title="Dismiss">
                            <i class="fas fa-times"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Sidebar --}}
    <div class="col-lg-4">
      {{-- Detection Methods --}}
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><strong>Detection Methods</strong></div>
        <ul class="list-group list-group-flush">
          @forelse($methodCounts as $mc)
            <li class="list-group-item d-flex justify-content-between align-items-center">
              {{ $mc->detection_method }}
              <span class="badge bg-secondary rounded-pill">{{ number_format($mc->total) }}</span>
            </li>
          @empty
            <li class="list-group-item text-muted">No detection data.</li>
          @endforelse
        </ul>
      </div>

      {{-- Recent Scans --}}
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><strong>Recent Scans</strong></div>
        <ul class="list-group list-group-flush">
          @forelse($recentScans as $scan)
            <li class="list-group-item">
              <div class="d-flex justify-content-between">
                <span>
                  @if($scan->status === 'completed')
                    <span class="badge bg-success">Completed</span>
                  @elseif($scan->status === 'running')
                    <span class="badge bg-primary">Running</span>
                  @elseif($scan->status === 'failed')
                    <span class="badge bg-danger">Failed</span>
                  @else
                    <span class="badge bg-secondary">{{ ucfirst($scan->status) }}</span>
                  @endif
                </span>
                <small class="text-muted">{{ \Carbon\Carbon::parse($scan->created_at)->format('Y-m-d H:i') }}</small>
              </div>
              <small>
                {{ number_format($scan->processed_records) }}/{{ number_format($scan->total_records) }} records
                &middot;
                {{ number_format($scan->duplicates_found) }} duplicates found
              </small>
            </li>
          @empty
            <li class="list-group-item text-muted">
              No scans yet.
              <a href="{{ route('dedupe.scan') }}" class="btn btn-sm atom-btn-outline-success mt-2 d-block">Run First Scan</a>
            </li>
          @endforelse
        </ul>
      </div>

      {{-- Quick Links --}}
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><strong>Quick Links</strong></div>
        <div class="list-group list-group-flush">
          <a href="{{ route('dedupe.browse') }}" class="list-group-item list-group-item-action">
            <i class="fas fa-list me-2"></i> Browse All Duplicates
          </a>
          <a href="{{ route('dedupe.browse', ['status' => 'pending']) }}" class="list-group-item list-group-item-action">
            <i class="fas fa-clock me-2"></i> Pending Review
          </a>
          <a href="{{ route('dedupe.browse', ['status' => 'confirmed']) }}" class="list-group-item list-group-item-action">
            <i class="fas fa-check me-2"></i> Confirmed Duplicates
          </a>
          <a href="{{ route('dedupe.rules') }}" class="list-group-item list-group-item-action">
            <i class="fas fa-cog me-2"></i> Detection Rules
          </a>
          <a href="{{ route('dedupe.report') }}" class="list-group-item list-group-item-action">
            <i class="fas fa-chart-bar me-2"></i> Report
          </a>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('js')
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
