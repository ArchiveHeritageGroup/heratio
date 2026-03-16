@extends('ahg-theme-b5::layouts.app')

@section('title', 'Edit Rights: ' . ($io->title ?? 'Untitled'))

@push('styles')
<link href="/vendor/ahg-theme-b5/css/vendor/tom-select.bootstrap5.min.css" rel="stylesheet">
@endpush

@section('content')

<h1>Edit Rights: {{ $io->title ?? 'Untitled' }}</h1>
<nav aria-label="breadcrumb" class="mb-4">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="#">Extended Rights</a></li>
    <li class="breadcrumb-item active">Edit</li>
  </ol>
</nav>

@if(session('notice'))
  <div class="alert alert-success">{{ session('notice') }}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form method="post">
  @csrf
  <div class="row">
    <div class="col-md-6">
      <!-- Rights Statement -->
      <div class="card mb-4">
        <div class="card-header"><strong>Rights Statement</strong></div>
        <div class="card-body">
          <select name="rights_statement_id" class="form-select">
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
        <div class="card-header"><strong>Creative Commons License</strong></div>
        <div class="card-body">
          <select name="cc_license_id" class="form-select">
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
        <div class="card-header"><strong>Rights Holder (Donor)</strong></div>
        <div class="card-body">
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
    </div>

    <div class="col-md-6">
      <!-- TK Labels -->
      <div class="card mb-4">
        <div class="card-header"><strong>TK Labels</strong></div>
        <div class="card-body">
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
              </label>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="mb-4">
    <button type="submit" class="btn btn-primary">
      <i class="fas fa-save"></i> Save Rights
    </button>
    <a href="{{ isset($io->slug) ? route('informationobject.show', $io->slug) : '#' }}" class="btn btn-secondary">
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
