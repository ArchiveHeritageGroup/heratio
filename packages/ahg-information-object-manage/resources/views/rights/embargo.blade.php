@extends('ahg-theme-b5::layouts.app')

@section('title', 'Add Embargo — ' . ($io->title ?? ''))

@section('content')

<h1>Add Embargo</h1>
<p class="lead">{{ $io->title ?? $io->slug }}</p>

<form method="post" action="{{ route('io.rights.embargo.store', $io->slug) }}">
  @csrf

  <div class="card mb-4">
    <div class="card-header">
      <h4 class="mb-0">Embargo Details</h4>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="embargo_type" class="form-label">Embargo Type <span class="text-danger">*</span></label>
          <select name="embargo_type" id="embargo_type" class="form-select" required>
            <option value="full">Full - Hide completely</option>
            <option value="metadata_only">Metadata Only - Hide digital objects</option>
            <option value="digital_object">Digital Object - Restrict downloads</option>
            <option value="custom">Custom</option>
          </select>
        </div>
      </div>

      <div class="row">
        <div class="col-md-4 mb-3">
          <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
          <input type="date" name="start_date" id="start_date" class="form-control" required value="{{ date('Y-m-d') }}">
        </div>
        <div class="col-md-4 mb-3">
          <label for="end_date" class="form-label">End Date</label>
          <input type="date" name="end_date" id="end_date" class="form-control">
          <small class="text-muted">Leave blank for perpetual embargo</small>
        </div>
        <div class="col-md-4 mb-3 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_perpetual" value="1" id="is_perpetual">
            <label class="form-check-label" for="is_perpetual">
              Perpetual (no end date)
            </label>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <label for="reason" class="form-label">Reason</label>
        <input type="text" name="reason" id="reason" class="form-control" placeholder="e.g., Donor restriction, Privacy concerns, Legal hold">
      </div>

      <div class="mb-3">
        <label for="public_message" class="form-label">Public Message</label>
        <textarea name="public_message" id="public_message" class="form-control" rows="2" placeholder="Message displayed to users when they encounter this embargo"></textarea>
      </div>

      <div class="mb-3">
        <label for="notes" class="form-label">Internal Notes</label>
        <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
      </div>
    </div>
  </div>

  <!-- Propagation Options -->
  <div class="card mb-4">
    <div class="card-header">
      <h4 class="mb-0"><i class="fas fa-sitemap me-2"></i>Apply to Hierarchy</h4>
    </div>
    <div class="card-body">
      @php
        $descendantCount = $descendantCount ?? 0;
      @endphp
      @if($descendantCount > 0)
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="apply_to_children" value="1" id="apply_to_children">
          <label class="form-check-label" for="apply_to_children">
            <strong>Apply to all descendants</strong>
            <span class="badge bg-info ms-2">{{ $descendantCount }} {{ $descendantCount === 1 ? 'record' : 'records' }}</span>
          </label>
          <div class="form-text text-muted">
            This will create the same embargo on all child records below this item in the hierarchy.
          </div>
        </div>
        <div class="alert alert-warning mb-0" id="propagation-warning" style="display: none;">
          <i class="fas fa-exclamation-triangle me-2"></i>
          Warning: This action cannot be easily undone. Each child record will have its own embargo that must be lifted individually.
        </div>
      @else
        <p class="text-muted mb-0">
          <i class="fas fa-info-circle me-2"></i>
          This record has no child records.
        </p>
      @endif
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">
      <h4 class="mb-0">Notifications</h4>
    </div>
    <div class="card-body">
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="notify_on_expiry" value="1" id="notify_on_expiry" checked>
        <label class="form-check-label" for="notify_on_expiry">
          Send notification before expiry
        </label>
      </div>

      <div class="row">
        <div class="col-md-4">
          <label for="notify_days_before" class="form-label">Notify days before expiry</label>
          <input type="number" name="notify_days_before" id="notify_days_before" class="form-control" value="30" min="1" max="365">
        </div>
      </div>
    </div>
  </div>

  <div class="actions">
    <a href="{{ isset($io->slug) ? route('informationobject.show', $io->slug) : '#' }}" class="btn btn-secondary">
      Cancel
    </a>
    <button type="submit" class="btn btn-danger">
      <i class="fas fa-lock"></i> Create Embargo
    </button>
  </div>

</form>
@endsection

@push('scripts')
<script>
document.getElementById('is_perpetual').addEventListener('change', function() {
  document.getElementById('end_date').disabled = this.checked;
  if (this.checked) {
    document.getElementById('end_date').value = '';
  }
});

// Show warning when propagation is selected
var propagationCheckbox = document.getElementById('apply_to_children');
if (propagationCheckbox) {
  propagationCheckbox.addEventListener('change', function() {
    var warning = document.getElementById('propagation-warning');
    if (warning) {
      warning.style.display = this.checked ? 'block' : 'none';
    }
  });
}
</script>
@endpush
