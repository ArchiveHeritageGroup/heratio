@extends('theme::layouts.1col')

@section('title', 'Compare Duplicates')
@section('body-class', 'admin dedupe compare')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-columns me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Compare Duplicates</h1>
      <span class="small text-muted">
        Similarity score:
        @php
          $score = (float) $duplicate->similarity_score;
          $badgeClass = $score >= 90 ? 'bg-danger' : ($score >= 75 ? 'bg-warning text-dark' : 'bg-info text-dark');
        @endphp
        <span class="badge {{ $badgeClass }}">{{ number_format($score, 0) }}%</span>
        &middot; Method: {{ $duplicate->detection_method }}
        &middot; Status: {{ ucfirst($duplicate->status) }}
      </span>
    </div>
    <div class="ms-auto d-flex gap-2">
      @if($duplicate->status !== 'merged')
        <a href="{{ route('dedupe.merge', $duplicate->id) }}" class="btn atom-btn-outline-success">
          <i class="fas fa-compress-arrows-alt me-1"></i> Merge Records
        </a>
      @endif
      <a href="{{ route('dedupe.browse') }}" class="btn atom-btn-white">
        <i class="fas fa-arrow-left me-1"></i> Back
      </a>
    </div>
  </div>

  {{-- Side-by-side column headers --}}
  <div class="row mb-3">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <strong>Record A</strong>
          @if($recordA)
            <a href="{{ route('informationobject.show', $recordA->id) }}" class="text-white float-end">
              <i class="fas fa-external-link-alt"></i> View
            </a>
          @endif
        </div>
        <div class="card-body py-2">
          @if($recordA)
            <strong>{{ $recordA->title ?: '[Untitled]' }}</strong>
            <br><small class="text-muted">ID: {{ $recordA->id }} &middot; {{ $recordA->identifier ?? 'No identifier' }}</small>
          @else
            <span class="text-danger">Record not found (ID: {{ $duplicate->record_a_id }})</span>
          @endif
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <strong>Record B</strong>
          @if($recordB)
            <a href="{{ route('informationobject.show', $recordB->id) }}" class="text-white float-end">
              <i class="fas fa-external-link-alt"></i> View
            </a>
          @endif
        </div>
        <div class="card-body py-2">
          @if($recordB)
            <strong>{{ $recordB->title ?: '[Untitled]' }}</strong>
            <br><small class="text-muted">ID: {{ $recordB->id }} &middot; {{ $recordB->identifier ?? 'No identifier' }}</small>
          @else
            <span class="text-danger">Record not found (ID: {{ $duplicate->record_b_id }})</span>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Field comparison table --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><strong>Field Comparison</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered mb-0">
          <thead>
            <tr>
              <th style="width: 180px;">Field</th>
              <th>Record A</th>
              <th>Record B</th>
              <th style="width: 80px;">Match</th>
            </tr>
          </thead>
          <tbody>
            @foreach($comparison as $field)
              @php
                $bgClass = '';
                if ($field['a'] !== '' || $field['b'] !== '') {
                    $bgClass = $field['match'] ? 'table-success' : 'table-warning';
                }
              @endphp
              <tr class="{{ $bgClass }}">
                <td><strong>{{ $field['label'] }}</strong></td>
                <td>{!! nl2br(e($field['a'] ?: '-')) !!}</td>
                <td>{!! nl2br(e($field['b'] ?: '-')) !!}</td>
                <td class="text-center">
                  @if($field['match'] && ($field['a'] !== '' || $field['b'] !== ''))
                    <i class="fas fa-check text-success"></i>
                  @elseif($field['a'] !== '' || $field['b'] !== '')
                    <i class="fas fa-times text-danger"></i>
                  @else
                    <span class="text-muted">-</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Detection Info --}}
  <div class="alert alert-info mb-4">
    <div class="row">
      <div class="col-md-3">
        <strong>Similarity Score:</strong>
        <span class="badge {{ $badgeClass }} fs-6">{{ number_format($score, 1) }}%</span>
      </div>
      <div class="col-md-3">
        <strong>Detection Method:</strong>
        {{ ucwords(str_replace('_', ' ', $duplicate->detection_method)) }}
      </div>
      <div class="col-md-3">
        <strong>Status:</strong>
        @php
          $statusColors = ['pending' => 'bg-warning text-dark', 'confirmed' => 'bg-info', 'dismissed' => 'bg-secondary', 'merged' => 'bg-success'];
        @endphp
        <span class="badge {{ $statusColors[$duplicate->status] ?? 'bg-secondary' }}">{{ $duplicate->status }}</span>
      </div>
      <div class="col-md-3">
        <strong>Detected:</strong>
        {{ $duplicate->detected_at ? \Carbon\Carbon::parse($duplicate->detected_at)->format('M j, Y H:i') : '-' }}
      </div>
    </div>
  </div>

  {{-- Field Comparison Legend --}}
  <div class="alert alert-secondary mb-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Legend:</strong>
    <span class="badge bg-success">Green rows</span> indicate matching values.
    <span class="badge bg-warning text-dark">Yellow rows</span> indicate differing values between records.
  </div>

  {{-- Detection Details --}}
  @if(!empty($duplicate->detection_details))
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><strong><i class="fas fa-microscope me-2"></i>Detection Details</strong></div>
      <div class="card-body">
        <pre class="mb-0">{{ json_encode(json_decode($duplicate->detection_details), JSON_PRETTY_PRINT) }}</pre>
      </div>
    </div>
  @endif

  {{-- Action buttons --}}
  @if($duplicate->status === 'pending')
    <div class="d-flex gap-2">
      <button type="button" class="btn atom-btn-outline-danger" id="btn-confirm-duplicate">
        <i class="fas fa-check me-1"></i> Confirm Duplicate
      </button>
      <button type="button" class="btn atom-btn-white" id="btn-dismiss-duplicate">
        <i class="fas fa-times me-1"></i> Dismiss
      </button>
    </div>
  @else
    <div class="alert alert-info">
      This duplicate pair has been <strong>{{ $duplicate->status }}</strong>.
      @if($duplicate->reviewed_at)
        Reviewed at {{ \Carbon\Carbon::parse($duplicate->reviewed_at)->format('Y-m-d H:i') }}.
      @endif
    </div>
  @endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    var dismissBtn = document.getElementById('btn-dismiss-duplicate');
    if (dismissBtn) {
        dismissBtn.addEventListener('click', function () {
            if (!confirm('Dismiss this duplicate pair?')) return;
            fetch('{{ route("dedupe.dismiss", $duplicate->id) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.href = '{{ route("dedupe.browse") }}';
                }
            });
        });
    }

    var confirmBtn = document.getElementById('btn-confirm-duplicate');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            if (!confirm('Confirm this as a duplicate pair?')) return;
            // Confirm uses the same dismiss endpoint pattern — reuse for now
            // In production this would be a dedicated confirm endpoint
            alert('Confirm action is not yet implemented. Use the dismiss action or manage via the database.');
        });
    }
});
</script>
@endpush
