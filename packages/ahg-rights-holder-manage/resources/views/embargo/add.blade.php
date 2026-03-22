@extends('theme::layouts.1col')

@section('title', 'Add Embargo')
@section('body-class', 'embargo add')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">Add Embargo</h1>
    <span class="small" id="heading-label">{{ $resource->title ?? $resource->slug ?? '' }}</span>
  </div>
@endsection

@section('content')
<form method="post" action="{{ route('embargo.store', ['objectId' => $objectId]) }}">
  @csrf

  <div class="card mb-4">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h4 class="mb-0">Embargo Details</h4>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="embargo_type" class="form-label">Embargo Type <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
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
          <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
          <input type="date" name="start_date" id="start_date" class="form-control" required value="{{ date('Y-m-d') }}">
        </div>
        <div class="col-md-4 mb-3">
          <label for="end_date" class="form-label">End Date <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="date" name="end_date" id="end_date" class="form-control">
          <small class="text-muted">Leave blank for perpetual embargo</small>
        </div>
        <div class="col-md-4 mb-3 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_perpetual" value="1" id="is_perpetual">
            <label class="form-check-label" for="is_perpetual">Perpetual (no end date) <span class="badge bg-secondary ms-1">Recommended</span></label>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <label for="reason" class="form-label">Reason <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="text" name="reason" id="reason" class="form-control" placeholder="e.g., Donor restriction, Privacy concerns, Legal hold">
      </div>

      <div class="mb-3">
        <label for="public_message" class="form-label">Public Message <span class="badge bg-secondary ms-1">Optional</span></label>
        <textarea name="public_message" id="public_message" class="form-control" rows="2" placeholder="Message displayed to users when they encounter this embargo"></textarea>
      </div>

      <div class="mb-3">
        <label for="notes" class="form-label">Internal Notes <span class="badge bg-secondary ms-1">Optional</span></label>
        <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
      </div>
    </div>
  </div>

  {{-- Propagation Options --}}
  <div class="card mb-4">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h4 class="mb-0">Apply to Hierarchy</h4>
    </div>
    <div class="card-body">
      @if(($descendantCount ?? 0) > 0)
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="apply_to_children" value="1" id="apply_to_children">
          <label class="form-check-label" for="apply_to_children">
            <strong>Apply to all descendants</strong>
            <span class="badge bg-info ms-2">{{ $descendantCount }} {{ $descendantCount === 1 ? 'record' : 'records' }}</span>
          </label>
          <div class="form-text text-muted">This will create the same embargo on all child records below this item in the hierarchy.</div>
        </div>
        <div class="alert alert-warning mb-0" id="propagation-warning" style="display: none;">
          <i class="fas fa-exclamation-triangle me-2"></i>
          Warning: This action cannot be easily undone. Each child record will have its own embargo that must be lifted individually.
        </div>
      @else
        <p class="text-muted mb-0"><i class="fas fa-info-circle me-2"></i>This record has no child records.</p>
      @endif
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h4 class="mb-0">Notifications</h4>
    </div>
    <div class="card-body">
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="notify_on_expiry" value="1" id="notify_on_expiry" checked>
        <label class="form-check-label" for="notify_on_expiry">Send notification before expiry <span class="badge bg-secondary ms-1">Optional</span></label>
      </div>
      <div class="row">
        <div class="col-md-4">
          <label for="notify_days_before" class="form-label">Notify days before expiry <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="number" name="notify_days_before" id="notify_days_before" class="form-control" value="30" min="1" max="365">
        </div>
      </div>
    </div>
  </div>

  <section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
    <a href="{{ url()->previous() }}" class="btn atom-btn-outline-light">Cancel</a>
    <button type="submit" class="btn atom-btn-outline-danger"><i class="fas fa-lock me-1"></i> Create Embargo</button>
  </section>
</form>

<script>
document.getElementById('is_perpetual').addEventListener('change', function() {
  document.getElementById('end_date').disabled = this.checked;
  if (this.checked) document.getElementById('end_date').value = '';
});
var pc = document.getElementById('apply_to_children');
if (pc) pc.addEventListener('change', function() {
  var w = document.getElementById('propagation-warning');
  if (w) w.style.display = this.checked ? 'block' : 'none';
});
</script>
@endsection
