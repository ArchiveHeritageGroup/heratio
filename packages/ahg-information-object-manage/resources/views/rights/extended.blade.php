@extends('theme::layouts.1col')

@section('title', 'Edit Rights: ' . ($io->title ?? 'Untitled'))

@push('styles')
<link href="/vendor/ahg-theme-b5/css/vendor/tom-select.bootstrap5.min.css" rel="stylesheet">
@endpush

@section('content')

<h1>Edit Rights: {{ $io->title ?? 'Untitled' }}</h1>
<nav aria-label="breadcrumb" class="mb-4">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('io.rights.extended', $io->slug) }}">Extended Rights</a></li>
    <li class="breadcrumb-item active">Edit</li>
  </ol>
</nav>

@if(session('notice'))
  <div class="alert alert-success">{{ session('notice') }}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form method="post" action="{{ route('io.rights.extended.store', $io->slug) }}">
  @csrf
  <div class="row">
    <div class="col-md-6">
      <!-- Rights Statement -->
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><strong>Rights Statement</strong></div>
        <div class="card-body">
          <label for="rights_statement_id" class="form-label">Rights Statement <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="rights_statement_id" id="rights_statement_id" class="form-select">
            <option value="">-- None --</option>
            @foreach($rightsStatements ?? [] as $rs)
              <option value="{{ $rs->id }}"
                @if(isset($currentRights->rights_statement) && $currentRights->rights_statement->rights_statement_id == $rs->id) selected @endif>
                {{ $rs->name ?? $rs->code }}
              </option>
            @endforeach
          </select>
        </div>
      </div>

      <!-- Creative Commons -->
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><strong>Creative Commons License</strong></div>
        <div class="card-body">
          <label for="cc_license_id" class="form-label">Creative Commons License <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="cc_license_id" id="cc_license_id" class="form-select">
            <option value="">-- None --</option>
            @foreach($ccLicenses ?? [] as $cc)
              <option value="{{ $cc->id }}"
                @if(isset($currentRights->cc_license) && $currentRights->cc_license->creative_commons_license_id == $cc->id) selected @endif>
                {{ $cc->name ?? $cc->code }}
              </option>
            @endforeach
          </select>
        </div>
      </div>

      <!-- Rights Holder (Donor) -->
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><strong>Rights Holder (Donor)</strong></div>
        <div class="card-body">
          <label for="rights_holder_id" class="form-label">Rights Holder <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="rights_holder_id" id="rights_holder_id" class="form-select" placeholder="Type to search...">
            <option value="">-- None --</option>
            @if(isset($donors) && count($donors) > 0)
              @php
                $currentHolderId = isset($currentRights->rights_holder->donor_id) ? $currentRights->rights_holder->donor_id : null;
              @endphp
              @foreach($donors as $donor)
                <option value="{{ $donor->id }}"
                  @if($currentHolderId == $donor->id) selected @endif>
                  {{ $donor->name ?? 'Unknown' }}
                </option>
              @endforeach
            @endif
          </select>
          <small class="text-muted">Select the donor who holds the rights to this material.</small>
        </div>
      </div>

      <!-- I18n fields: copyright_notice, usage_conditions, rights_note -->
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><strong>Rights Details</strong></div>
        <div class="card-body">
          @php
            $primaryExtended = isset($extendedRights) ? $extendedRights->firstWhere('is_primary', 1) : null;
          @endphp

          <div class="mb-3">
            <label for="copyright_notice" class="form-label">Copyright Notice <span class="badge bg-secondary ms-1">Optional</span></label>
            <textarea name="copyright_notice" id="copyright_notice" class="form-control" rows="2">{{ old('copyright_notice', $primaryExtended->copyright_notice ?? '') }}</textarea>
          </div>

          <div class="mb-3">
            <label for="usage_conditions" class="form-label">Usage Conditions <span class="badge bg-secondary ms-1">Optional</span></label>
            <textarea name="usage_conditions" id="usage_conditions" class="form-control" rows="2">{{ old('usage_conditions', $primaryExtended->usage_conditions ?? '') }}</textarea>
          </div>

          <div class="mb-3">
            <label for="rights_note" class="form-label">Rights Note <span class="badge bg-secondary ms-1">Optional</span></label>
            <textarea name="rights_note" id="rights_note" class="form-control" rows="3">{{ old('rights_note', $primaryExtended->rights_note ?? '') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <!-- TK Labels -->
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><strong>TK Labels</strong></div>
        <div class="card-body">
          <label class="form-label">TK Labels <span class="badge bg-secondary ms-1">Optional</span></label>
          @php
            $selectedTkLabels = $currentRights->tk_labels ?? [];
            if (!is_array($selectedTkLabels)) {
                $selectedTkLabels = [];
            }
          @endphp
          @foreach($tkLabels ?? [] as $tk)
            <div class="form-check">
              <input type="checkbox" name="tk_label_ids[]" value="{{ $tk->id }}"
                     class="form-check-input" id="tk_{{ $tk->id }}"
                     @if(in_array($tk->id, $selectedTkLabels)) checked @endif>
              <label class="form-check-label" for="tk_{{ $tk->id }}">
                {{ $tk->name ?? $tk->code }}
               <span class="badge bg-secondary ms-1">Required</span></label>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="mb-4">
    <button type="submit" class="btn atom-btn-outline-success">
      <i class="fas fa-save"></i> Save Rights
    </button>
    <a href="{{ isset($io->slug) ? route('informationobject.show', $io->slug) : '#' }}" class="btn atom-btn-white">
      Cancel
    </a>
  </div>
</form>
@endsection

@push('scripts')
<script src="/vendor/ahg-theme-b5/js/vendor/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new TomSelect('#rights_holder_id', {
        create: false,
        sortField: { field: 'text', direction: 'asc' },
        placeholder: 'Type to search for donors...'
    });
});
</script>
@endpush
