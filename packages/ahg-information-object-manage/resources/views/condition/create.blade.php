@extends('theme::layouts.1col')
@section('title', 'New Condition Report — ' . ($io->title ?? ''))
@section('body-class', 'condition create')

@section('content')
<div class="container py-3">

  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('informationobject.show', $io->slug) }}">{{ $io->title ?? $io->identifier ?? '' }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('io.condition', $io->slug) }}">Condition</a></li>
      <li class="breadcrumb-item active">New Report</li>
    </ol>
  </nav>

  <h1 class="h3 mb-4"><i class="fas fa-clipboard-check me-2"></i>{{ __('New Condition Report') }}</h1>

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $e)<p class="mb-0">{{ $e }}</p>@endforeach
    </div>
  @endif

  <form method="POST" action="{{ route('io.condition.store', $io->slug) }}" enctype="multipart/form-data">
    @csrf

    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-info-circle me-1"></i> {{ __('General Information') }}
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label for="assessment_date" class="form-label">{{ __('Assessment Date *') }}</label>
            <input type="date" class="form-control @error('assessment_date') is-invalid @enderror" id="assessment_date" name="assessment_date" value="{{ old('assessment_date', date('Y-m-d')) }}" required>
            @error('assessment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-4">
            <label for="context" class="form-label">{{ __('Context') }}</label>
            <select class="form-select" id="context" name="context">
              <option value="">-- Select --</option>
              @foreach($contextOptions as $opt)
                <option value="{{ $opt }}" @selected(old('context') === $opt)>{{ ucfirst($opt) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label for="next_check_date" class="form-label">{{ __('Next Check Date') }}</label>
            <input type="date" class="form-control" id="next_check_date" name="next_check_date" value="{{ old('next_check_date') }}">
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-thermometer-half me-1"></i> {{ __('Condition Assessment') }}
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label for="overall_rating" class="form-label">{{ __('Overall Rating *') }}</label>
            <select class="form-select @error('overall_rating') is-invalid @enderror" id="overall_rating" name="overall_rating" required>
              <option value="">-- Select --</option>
              @foreach($ratingOptions as $opt)
                <option value="{{ $opt }}" @selected(old('overall_rating') === $opt)>{{ ucfirst($opt) }}</option>
              @endforeach
            </select>
            @error('overall_rating')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-4">
            <label for="priority" class="form-label">{{ __('Treatment Priority') }}</label>
            <select class="form-select" id="priority" name="priority">
              <option value="">-- Select --</option>
              @foreach($priorityOptions as $opt)
                <option value="{{ $opt }}" @selected(old('priority') === $opt)>{{ ucfirst($opt) }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-exclamation-triangle me-1"></i> {{ __('Damage') }}
      </div>
      <div class="card-body">
        <div id="damage-rows">
          <div class="row g-2 mb-2 damage-row">
            <div class="col-md-3">
              <select class="form-select form-select-sm" name="damage_type[]">
                <option value="">-- Damage type --</option>
                @foreach($damageTypes as $dt)
                  <option value="{{ $dt }}">{{ $dt }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <input type="text" class="form-control form-control-sm" name="damage_location[]" placeholder="{{ __('Location') }}">
            </div>
            <div class="col-md-2">
              <select class="form-select form-select-sm" name="damage_severity[]">
                @foreach($severityOptions as $opt)
                  <option value="{{ $opt }}">{{ ucfirst($opt) }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <input type="text" class="form-control form-control-sm" name="damage_description[]" placeholder="{{ __('Description') }}">
            </div>
            <div class="col-md-1">
              <button type="button" class="btn btn-sm btn-outline-danger remove-damage" style="display:none;"><i class="fas fa-times"></i></button>
            </div>
          </div>
        </div>
        <button type="button" class="btn btn-sm atom-btn-white mt-1" id="add-damage"><i class="fas fa-plus me-1"></i>{{ __('Add Damage') }}</button>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-sticky-note me-1"></i> {{ __('Notes') }}
      </div>
      <div class="card-body">
        <div class="mb-3">
          <label for="summary" class="form-label">{{ __('Summary / Description') }}</label>
          <textarea class="form-control" id="summary" name="summary" rows="4">{{ old('summary') }}</textarea>
        </div>
        <div class="mb-3">
          <label for="recommendations" class="form-label">{{ __('Treatment Recommendations') }}</label>
          <textarea class="form-control" id="recommendations" name="recommendations" rows="3">{{ old('recommendations') }}</textarea>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label for="environmental_notes" class="form-label">{{ __('Environmental Notes') }}</label>
            <textarea class="form-control" id="environmental_notes" name="environmental_notes" rows="2">{{ old('environmental_notes') }}</textarea>
          </div>
          <div class="col-md-6">
            <label for="handling_notes" class="form-label">{{ __('Handling Notes') }}</label>
            <textarea class="form-control" id="handling_notes" name="handling_notes" rows="2">{{ old('handling_notes') }}</textarea>
          </div>
          <div class="col-md-6">
            <label for="display_notes" class="form-label">{{ __('Display Notes') }}</label>
            <textarea class="form-control" id="display_notes" name="display_notes" rows="2">{{ old('display_notes') }}</textarea>
          </div>
          <div class="col-md-6">
            <label for="storage_notes" class="form-label">{{ __('Storage Notes') }}</label>
            <textarea class="form-control" id="storage_notes" name="storage_notes" rows="2">{{ old('storage_notes') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>{{ __('Save Condition Report') }}</button>
      <a href="{{ route('io.condition', $io->slug) }}" class="btn atom-btn-white">Cancel</a>
    </div>

  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('add-damage').addEventListener('click', function() {
    var rows = document.getElementById('damage-rows');
    var first = rows.querySelector('.damage-row');
    var clone = first.cloneNode(true);
    clone.querySelectorAll('input, select').forEach(function(el) { el.value = ''; });
    clone.querySelector('.remove-damage').style.display = '';
    rows.appendChild(clone);
  });
  document.getElementById('damage-rows').addEventListener('click', function(e) {
    if (e.target.closest('.remove-damage')) {
      e.target.closest('.damage-row').remove();
    }
  });
});
</script>
@endsection
