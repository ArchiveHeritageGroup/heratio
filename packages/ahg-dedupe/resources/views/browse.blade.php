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
      <label class="form-label small mb-1">Status <span class="badge bg-secondary ms-1">Optional</span></label>
      <select name="status" class="form-select form-select-sm">
        <option value="">All statuses</option>
        @foreach(['pending', 'confirmed', 'merged', 'dismissed'] as $opt)
          <option value="{{ $opt }}" {{ $currentStatus === $opt ? 'selected' : '' }}>{{ ucfirst($opt) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label small mb-1">Detection Method <span class="badge bg-secondary ms-1">Optional</span></label>
      <select name="method" class="form-select form-select-sm">
        <option value="">All methods</option>
        @foreach($methods as $m)
          <option value="{{ $m }}" {{ $currentMethod === $m ? 'selected' : '' }}>{{ $m }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label small mb-1">Min Score <span class="badge bg-secondary ms-1">Optional</span></label>
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
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <span><strong>{{ number_format($pager->getNbResults()) }}</strong> duplicate pairs found</span>
        <div class="btn-group btn-group-sm">
          <button type="button" class="btn btn-outline-light" id="selectAll">
            <i class="fas fa-check-square me-1"></i> Select All
          </button>
          <button type="button" class="btn btn-outline-light" id="dismissSelected" disabled>
            <i class="fas fa-times me-1"></i> Dismiss Selected
          </button>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0">
            <thead>
              <tr>
                <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="checkAll"></th>
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
                  <td><input type="checkbox" class="form-check-input row-check" data-id="{{ $dup['id'] }}"></td>
                  <td class="text-center">
                    @php
                      $score = (float) $dup['similarity_score'];
                      $badgeClass = $score >= 90 ? 'bg-danger' : ($score >= 75 ? 'bg-warning text-dark' : 'bg-info text-dark');
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ number_format($score, 0) }}%</span>
                  </td>
                  <td>
                    <div>
                      <a href="{{ route('informationobject.show', $dup['record_a_id']) }}" target="_blank">
                        {{ $dup['record_a_title'] ?: '[Untitled]' }}
                        <i class="fas fa-external-link-alt fa-xs text-muted"></i>
                      </a>
                    </div>
                    @if(!empty($dup['record_a_identifier']))
                      <small class="text-muted">{{ $dup['record_a_identifier'] }}</small>
                    @endif
                  </td>
                  <td>
                    <div>
                      <a href="{{ route('informationobject.show', $dup['record_b_id']) }}" target="_blank">
                        {{ $dup['record_b_title'] ?: '[Untitled]' }}
                        <i class="fas fa-external-link-alt fa-xs text-muted"></i>
                      </a>
                    </div>
                    @if(!empty($dup['record_b_identifier']))
                      <small class="text-muted">{{ $dup['record_b_identifier'] }}</small>
                    @endif
                  </td>
                  <td><span class="badge bg-light text-dark">{{ ucwords(str_replace('_', ' ', $dup['detection_method'])) }}</span></td>
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
                  <td><small class="text-muted">{{ $dup['detected_at'] ? \Carbon\Carbon::parse($dup['detected_at'])->format('M j, Y') : '-' }}</small></td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <a href="{{ route('dedupe.compare', $dup['id']) }}" class="btn atom-btn-white" title="Compare Side-by-Side">
                        <i class="fas fa-columns"></i>
                      </a>
                      @if($dup['status'] !== 'merged')
                        <a href="{{ route('dedupe.merge', $dup['id']) }}" class="btn atom-btn-white" title="Merge Records">
                          <i class="fas fa-compress-arrows-alt"></i>
                        </a>
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
      </div>
    </div>
  @endif

  @include('ahg-core::components.pager', ['pager' => $pager])
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var checkAll = document.getElementById('checkAll');
    var rowChecks = document.querySelectorAll('.row-check');
    var dismissSelected = document.getElementById('dismissSelected');
    var selectAllBtn = document.getElementById('selectAll');

    function updateDismissButton() {
        var checked = document.querySelectorAll('.row-check:checked');
        if (dismissSelected) dismissSelected.disabled = checked.length === 0;
    }

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            rowChecks.forEach(function(cb) { cb.checked = checkAll.checked; });
            updateDismissButton();
        });
    }

    rowChecks.forEach(function(cb) {
        cb.addEventListener('change', updateDismissButton);
    });

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            rowChecks.forEach(function(cb) { cb.checked = true; });
            if (checkAll) checkAll.checked = true;
            updateDismissButton();
        });
    }

    if (dismissSelected) {
        dismissSelected.addEventListener('click', function() {
            var checked = document.querySelectorAll('.row-check:checked');
            if (checked.length === 0) return;
            if (!confirm('Dismiss ' + checked.length + ' duplicate pair(s) as false positives?')) return;
            var ids = Array.from(checked).map(function(cb) { return cb.dataset.id; });
            ids.forEach(function(id) {
                fetch('{{ url("/admin/dedupe/dismiss") }}/' + id, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                });
            });
            setTimeout(function() { location.reload(); }, 500);
        });
    }

    document.querySelectorAll('.btn-dismiss').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-id');
            if (!confirm('Dismiss this duplicate pair?')) return;
            var row = this.closest('tr');
            fetch('{{ url("/admin/dedupe/dismiss") }}/' + id, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && row) row.remove();
            });
        });
    });
});
</script>
@endpush
