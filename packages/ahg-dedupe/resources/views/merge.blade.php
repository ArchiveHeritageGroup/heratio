@extends('theme::layouts.1col')

@section('title', 'Merge Duplicate Records')
@section('body-class', 'admin dedupe merge')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-compress-arrows-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Merge Duplicate Records</h1>
      <span class="small text-muted">Duplicate Detection</span>
    </div>
    <div class="ms-auto d-flex gap-2">
      <a href="{{ route('dedupe.compare', $duplicate->id) }}" class="btn atom-btn-white">
        <i class="fas fa-columns me-1"></i> Back to Compare
      </a>
    </div>
  </div>

  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Warning:</strong> Merging records is permanent. The secondary record will be archived and its digital objects and child records transferred to the primary record.
  </div>

  <form method="post" action="{{ route('dedupe.merge.execute', $duplicate->id) }}" id="mergeForm">
    @csrf

    {{-- Detection Info --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">Detection Details</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4">
            <strong>Similarity Score:</strong>
            @php $score = (float) $duplicate->similarity_score; @endphp
            <span class="badge {{ $score >= 90 ? 'bg-danger' : ($score >= 75 ? 'bg-warning text-dark' : 'bg-info text-dark') }}">
              {{ number_format($score, 1) }}%
            </span>
          </div>
          <div class="col-md-4">
            <strong>Detection Method:</strong>
            {{ ucwords(str_replace('_', ' ', $duplicate->detection_method)) }}
          </div>
          <div class="col-md-4">
            <strong>Detected:</strong>
            {{ $duplicate->detected_at ? \Carbon\Carbon::parse($duplicate->detected_at)->format('M j, Y H:i') : '-' }}
          </div>
        </div>
      </div>
    </div>

    {{-- Select Primary Record --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-star me-2"></i>Step 1: Select Primary Record</h5>
      </div>
      <div class="card-body">
        <p class="text-muted mb-4">The primary record will be kept. The secondary record's data will be merged into it.</p>

        <div class="row">
          <div class="col-md-6">
            <div class="card h-100 border-2 primary-option" id="optionA">
              <div class="card-header bg-light">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="primary_id"
                         value="{{ $recordA->id ?? '' }}" id="primaryA" checked>
                  <label class="form-check-label fw-bold" for="primaryA">
                    Record A (Keep This) <span class="badge bg-secondary ms-1">Required</span>
                  </label>
                </div>
              </div>
              <div class="card-body">
                @if($recordA)
                  <h5>{{ $recordA->title ?? 'Untitled' }}</h5>
                  <p class="text-muted mb-2"><strong>Identifier:</strong> {{ $recordA->identifier ?? 'N/A' }}</p>
                  <p class="text-muted mb-2"><strong>Level:</strong> {{ $recordA->level_of_description ?? 'N/A' }}</p>
                  <p class="text-muted mb-0"><strong>Repository:</strong> {{ $recordA->repository_name ?? 'N/A' }}</p>
                @else
                  <span class="text-danger">Record not found</span>
                @endif
              </div>
              <div class="card-footer bg-light">
                @if($recordA)
                  <a href="{{ route('informationobject.show', $recordA->id) }}" target="_blank" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-external-link-alt me-1"></i> View Record
                  </a>
                @endif
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="card h-100 border-2 primary-option" id="optionB">
              <div class="card-header bg-light">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="primary_id"
                         value="{{ $recordB->id ?? '' }}" id="primaryB">
                  <label class="form-check-label fw-bold" for="primaryB">
                    Record B (Keep This) <span class="badge bg-secondary ms-1">Required</span>
                  </label>
                </div>
              </div>
              <div class="card-body">
                @if($recordB)
                  <h5>{{ $recordB->title ?? 'Untitled' }}</h5>
                  <p class="text-muted mb-2"><strong>Identifier:</strong> {{ $recordB->identifier ?? 'N/A' }}</p>
                  <p class="text-muted mb-2"><strong>Level:</strong> {{ $recordB->level_of_description ?? 'N/A' }}</p>
                  <p class="text-muted mb-0"><strong>Repository:</strong> {{ $recordB->repository_name ?? 'N/A' }}</p>
                @else
                  <span class="text-danger">Record not found</span>
                @endif
              </div>
              <div class="card-footer bg-light">
                @if($recordB)
                  <a href="{{ route('informationobject.show', $recordB->id) }}" target="_blank" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-external-link-alt me-1"></i> View Record
                  </a>
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- What Will Happen --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Step 2: Review Merge Actions</h5>
      </div>
      <div class="card-body">
        <p>The following actions will be performed:</p>
        <ul class="list-group list-group-flush">
          <li class="list-group-item">
            <i class="fas fa-file me-2 text-primary"></i>
            Digital objects from the secondary record will be transferred to the primary record
          </li>
          <li class="list-group-item">
            <i class="fas fa-sitemap me-2 text-primary"></i>
            Child records from the secondary record will be moved under the primary record
          </li>
          <li class="list-group-item">
            <i class="fas fa-link me-2 text-primary"></i>
            The secondary record's slug will redirect to the primary record
          </li>
          <li class="list-group-item">
            <i class="fas fa-archive me-2 text-primary"></i>
            The secondary record will be archived (not deleted) for audit purposes
          </li>
          <li class="list-group-item">
            <i class="fas fa-history me-2 text-primary"></i>
            A merge log entry will be created for compliance and auditing
          </li>
        </ul>
      </div>
    </div>

    {{-- Confirmation --}}
    <div class="card mb-4">
      <div class="card-header bg-danger text-white">
        <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Step 3: Confirm Merge</h5>
      </div>
      <div class="card-body">
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="confirmMerge" required>
          <label class="form-check-label" for="confirmMerge">
            I understand that this action is permanent and cannot be undone. <span class="badge bg-secondary ms-1">Required</span>
          </label>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn atom-btn-outline-danger" id="mergeBtn" disabled>
            <i class="fas fa-compress-arrows-alt me-1"></i> Merge Records
          </button>
          <a href="{{ route('dedupe.compare', $duplicate->id) }}" class="btn atom-btn-white">
            <i class="fas fa-columns me-1"></i> Back to Compare
          </a>
          <a href="{{ route('dedupe.browse') }}" class="btn atom-btn-white">Cancel</a>
        </div>
      </div>
    </div>
  </form>
@endsection

@push('styles')
<style>
.primary-option { cursor: pointer; transition: all 0.2s; }
.primary-option:hover { box-shadow: 0 0 10px rgba(0,0,0,0.1); }
.primary-option.selected { border-color: #0d6efd !important; box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25); }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var confirmCheck = document.getElementById('confirmMerge');
    var mergeBtn = document.getElementById('mergeBtn');
    var optionA = document.getElementById('optionA');
    var optionB = document.getElementById('optionB');
    var radioA = document.getElementById('primaryA');
    var radioB = document.getElementById('primaryB');

    confirmCheck.addEventListener('change', function() {
        mergeBtn.disabled = !this.checked;
    });

    function updateSelection() {
        optionA.classList.toggle('selected', radioA.checked);
        optionB.classList.toggle('selected', radioB.checked);
    }

    radioA.addEventListener('change', updateSelection);
    radioB.addEventListener('change', updateSelection);

    optionA.addEventListener('click', function(e) {
        if (e.target.tagName !== 'A' && e.target.tagName !== 'BUTTON') {
            radioA.checked = true;
            updateSelection();
        }
    });

    optionB.addEventListener('click', function(e) {
        if (e.target.tagName !== 'A' && e.target.tagName !== 'BUTTON') {
            radioB.checked = true;
            updateSelection();
        }
    });

    updateSelection();
});
</script>
@endpush
