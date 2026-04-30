@extends('ahg-theme-b5::layout')

@section('title', 'Batch Rights Operations')

@section('content')
<div class="container-fluid mt-3">
  @include('ahg-extended-rights::admin._sidebar')

  <h1><i class="fas fa-layer-group"></i> {{ __('Batch Rights Operations') }}</h1>

{{-- Cloned from PSIS extendedRights/batchSuccess.blade.php — preserves the 3-step structure --}}
<form method="POST" action="{{ route('ext-rights-admin.batch-store') }}" id="batch-rights-form">
  @csrf

  {{-- Step 1: Action Selection (PSIS radio buttons) --}}
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">{{ __('1. Select Action') }}</h5></div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="batch_action" id="action_assign" value="assign" checked>
            <label class="form-check-label" for="action_assign">
              <strong>{{ __('Assign Rights') }}</strong><br>
              <small class="text-muted">{{ __('Apply rights to selected objects') }}</small>
            </label>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="batch_action" id="action_embargo" value="embargo">
            <label class="form-check-label" for="action_embargo">
              <strong>{{ __('Apply Embargo') }}</strong><br>
              <small class="text-muted">{{ __('Restrict access to selected objects') }}</small>
            </label>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="batch_action" id="action_clear" value="clear">
            <label class="form-check-label" for="action_clear">
              <strong>{{ __('Clear Rights') }}</strong><br>
              <small class="text-muted">{{ __('Remove rights from selected objects') }}</small>
            </label>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Step 2: Object Selection (textarea + overwrite checkbox per PSIS) --}}
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">{{ __('2. Select Objects') }}</h5></div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">{{ __('Object IDs (one per line or comma-separated)') }}</label>
        <textarea name="object_ids" class="form-control" rows="4" placeholder="{{ __('123&#10;456&#10;789') }}" required></textarea>
        <small class="text-muted">{{ __('Enter the IDs of the information objects to apply this batch operation to.') }}</small>
      </div>
      <div class="form-check">
        <input type="checkbox" name="overwrite" id="overwrite" value="1" class="form-check-input">
        <label class="form-check-label" for="overwrite">{{ __('Overwrite existing rights') }}</label>
      </div>
    </div>
  </div>

  {{-- Step 3: Rights Details (PSIS Assign Options) --}}
  <div id="assign_options" class="card mb-4">
    <div class="card-header"><h5 class="mb-0">{{ __('3. Rights Details') }}</h5></div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">{{ __('Rights Statement') }}</label>
          <select name="rights_statement_id" class="form-select">
            <option value="">{{ __('-- Select --') }}</option>
            @foreach($statements ?? [] as $stmt)
              <option value="{{ $stmt->id }}">@if(!empty($stmt->code))[{{ $stmt->code }}] @endif{{ e($stmt->name ?? '') }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">{{ __('Creative Commons License') }}</label>
          <select name="creative_commons_id" class="form-select">
            <option value="">{{ __('-- Select --') }}</option>
            @foreach($ccLicenses ?? [] as $cc)
              <option value="{{ $cc->id }}">@if(!empty($cc->code))[{{ $cc->code }}] @endif{{ e($cc->name ?? '') }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">{{ __('Rights Holder (Donor)') }}</label>
          <select name="rights_holder_id" class="form-select">
            <option value="">{{ __('-- Select --') }}</option>
            @foreach($donors ?? [] as $donor)
              <option value="{{ $donor->id }}">{{ e($donor->name ?? 'Unknown') }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">{{ __('Copyright Notice') }}</label>
          <input type="text" name="copyright_notice" class="form-control" placeholder="{{ __('© 2026 ...') }}">
        </div>
      </div>

      {{-- TK Labels checkbox grid --}}
      <div class="mb-3">
        <label class="form-label">{{ __('Traditional Knowledge Labels') }}</label>
        <div class="row">
          @foreach($tkLabels ?? [] as $tk)
            <div class="col-md-4 mb-2">
              <div class="form-check">
                <input type="checkbox" name="tk_label_ids[]" value="{{ $tk->id }}" class="form-check-input" id="tk_{{ $tk->id }}">
                <label class="form-check-label" for="tk_{{ $tk->id }}">
                  @if(!empty($tk->icon_url))
                    <img src="{{ $tk->icon_url }}" alt="" style="width:20px;height:20px;" class="me-1">
                  @endif
                  {{ $tk->name ?? $tk->code ?? '' }}
                </label>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  {{-- Embargo Options (hidden by default, shown via JS) --}}
  <div id="embargo_options" class="card mb-4" style="display:none;">
    <div class="card-header"><h5 class="mb-0">{{ __('Embargo Details') }}</h5></div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">{{ __('Embargo Type') }}</label>
          <select name="embargo_type" class="form-select">
            <option value="full">{{ __('Full (no access)') }}</option>
            <option value="metadata_only">{{ __('Metadata only') }}</option>
            <option value="thumbnail_only">{{ __('Thumbnail only') }}</option>
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">{{ __('End Date (optional)') }}</label>
          <input type="date" name="embargo_end_date" class="form-control">
        </div>
      </div>
    </div>
  </div>

  {{-- Submit row --}}
  <div class="d-flex justify-content-between mb-4">
    <a href="{{ route('ext-rights-admin.index') }}" class="btn btn-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Cancel') }}
    </a>
    <button type="submit" class="btn btn-primary" onclick="return confirm('Apply batch operation to these objects?')">
      <i class="fas fa-check me-1"></i>{{ __('Execute Batch Operation') }}
    </button>
  </div>
</form>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('input[name="batch_action"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
      var assignOptions = document.getElementById('assign_options');
      var embargoOptions = document.getElementById('embargo_options');
      if (this.value === 'assign') {
        assignOptions.style.display = 'block';
        embargoOptions.style.display = 'none';
      } else if (this.value === 'embargo') {
        assignOptions.style.display = 'none';
        embargoOptions.style.display = 'block';
      } else {
        assignOptions.style.display = 'none';
        embargoOptions.style.display = 'none';
      }
    });
  });
});
</script>
@endpush

  {{-- Recent Batch Operations --}}
  @if(!empty($recentBatches))
  <div class="card">
    <div class="card-header"><h5 class="mb-0">{{ __('Recent Batch Operations') }}</h5></div>
    <div class="card-body table-responsive">
      <table class="table table-sm table-striped">
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Operation') }}</th><th>{{ __('Objects') }}</th><th>{{ __('By') }}</th><th>{{ __('Result') }}</th></tr></thead>
        <tbody>
          @foreach($recentBatches as $batch)
          <tr>
            <td>{{ $batch->created_at ?? '' }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $batch->action ?? '')) }}</td>
            <td>{{ $batch->object_count ?? 0 }}</td>
            <td>{{ e($batch->performed_by ?? '') }}</td>
            <td><span class="badge bg-{{ ($batch->result ?? '') === 'success' ? 'success' : 'danger' }}">{{ ucfirst($batch->result ?? '') }}</span></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif
</div>
@endsection
