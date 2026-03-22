@extends('theme::layouts.1col')

@section('title', 'Batch Rights Assignment')
@section('body-class', 'extended-rights batch')

@section('title-block')
  <h1 class="mb-0"><i class="fas fa-layer-group me-2"></i>Batch Rights Assignment</h1>
@endsection

@section('content')
<form method="post" action="{{ route('extended-rights.batch.store') }}" id="batch-rights-form">
  @csrf

  {{-- Action Selection --}}
  <div class="card mb-4">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h5 class="mb-0">1. Select Action</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="batch_action" id="action_assign" value="assign" checked>
            <label class="form-check-label" for="action_assign">
              <strong>Assign Rights</strong><br><small class="text-muted">Apply rights to selected objects</small>
             <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="batch_action" id="action_embargo" value="embargo">
            <label class="form-check-label" for="action_embargo">
              <strong>Apply Embargo</strong><br><small class="text-muted">Restrict access to selected objects</small>
             <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="batch_action" id="action_clear" value="clear">
            <label class="form-check-label" for="action_clear">
              <strong>Clear Rights</strong><br><small class="text-muted">Remove rights from selected objects</small>
             <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Object Selection --}}
  <div class="card mb-4">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h5 class="mb-0">2. Select Objects</h5>
    </div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">Search and select objects <span class="badge bg-secondary ms-1">Optional</span></label>
        <select name="object_ids[]" id="object_select" multiple class="form-select">
          @foreach($topLevelRecords ?? [] as $record)
            <option value="{{ $record->id }}">
              {{ $record->title ?? 'Untitled' }}
              @if(!empty($record->identifier)) [{{ $record->identifier }}]@endif
            </option>
          @endforeach
        </select>
        <small class="text-muted">Start typing to search for objects. Select multiple items.</small>
      </div>
      <div class="form-check">
        <input type="checkbox" name="overwrite" id="overwrite" value="1" class="form-check-input">
        <label class="form-check-label" for="overwrite">Overwrite existing rights <span class="badge bg-secondary ms-1">Optional</span></label>
      </div>
    </div>
  </div>

  {{-- Rights Options --}}
  <div id="assign_options" class="card mb-4">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h5 class="mb-0">3. Rights Details</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="rights_statement_id" class="form-label">Rights Statement <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="rights_statement_id" id="rights_statement_id" class="form-select">
            <option value="">-- Select --</option>
            @foreach($rightsStatements ?? [] as $rs)
              <option value="{{ $rs->id }}">[{{ $rs->code }}] {{ $rs->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label for="creative_commons_id" class="form-label">Creative Commons License <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="creative_commons_id" id="creative_commons_id" class="form-select">
            <option value="">-- Select --</option>
            @foreach($ccLicenses ?? [] as $cc)
              <option value="{{ $cc->id }}">[{{ $cc->code }}] {{ $cc->name }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="rights_holder_id" class="form-label">Rights Holder <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="rights_holder_id" id="rights_holder_id" class="form-select">
            <option value="">-- Select --</option>
            @foreach($donors ?? [] as $donor)
              <option value="{{ $donor->id }}">{{ $donor->name ?? 'Unknown' }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label for="copyright_notice" class="form-label">Copyright Notice <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="text" name="copyright_notice" id="copyright_notice" class="form-control" placeholder="&copy; {{ date('Y') }} ...">
        </div>
      </div>

      {{-- TK Labels --}}
      <div class="mb-3">
        <label class="form-label">Traditional Knowledge Labels <span class="badge bg-secondary ms-1">Optional</span></label>
        <div class="row">
          @foreach($tkLabels ?? [] as $tk)
          <div class="col-md-4 mb-2">
            <div class="form-check">
              <input type="checkbox" name="tk_label_ids[]" value="{{ $tk->id }}" class="form-check-input" id="tk_{{ $tk->id }}">
              <label class="form-check-label" for="tk_{{ $tk->id }}">{{ $tk->name ?? $tk->code }} <span class="badge bg-secondary ms-1">Required</span></label>
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  {{-- Embargo Options --}}
  <div id="embargo_options" class="card mb-4" style="display:none;">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h5 class="mb-0">Embargo Details</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="embargo_type" class="form-label">Embargo Type <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="embargo_type" id="embargo_type" class="form-select">
            <option value="full">Full (no access)</option>
            <option value="metadata_only">Metadata only</option>
            <option value="thumbnail_only">Thumbnail only</option>
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label for="embargo_end_date" class="form-label">End Date (optional) <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="date" name="embargo_end_date" id="embargo_end_date" class="form-control">
        </div>
      </div>
    </div>
  </div>

  <section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
    <a href="{{ route('extended-rights.dashboard') }}" class="btn atom-btn-outline-light"><i class="fas fa-arrow-left me-1"></i>Cancel</a>
    <button type="submit" class="btn atom-btn-outline-light"><i class="fas fa-check me-1"></i>Execute Batch Operation</button>
  </section>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var radios = document.querySelectorAll('input[name="batch_action"]');
  radios.forEach(function(radio) {
    radio.addEventListener('change', function() {
      document.getElementById('assign_options').style.display = this.value === 'assign' ? 'block' : 'none';
      document.getElementById('embargo_options').style.display = this.value === 'embargo' ? 'block' : 'none';
    });
  });
});
</script>
@endsection
