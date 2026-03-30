@extends('theme::layouts.1col')
@section('title', __('Add Heritage Asset'))
@section('body-class', 'admin heritage')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <h1 class="h3 mb-0"><i class="fas fa-plus me-2"></i>{{ __('Add Heritage Asset') }}</h1>
  </div>
</div>

<form method="post" action="{{ route('heritage.accounting.store') }}">
  @csrf
  <div class="row">
    <div class="col-md-8">
      {{-- Basic Information --}}
      <div class="card mb-4">
        <div class="card-header bg-dark text-white"><h5 class="mb-0">{{ __('Basic Information') }}</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              @if(isset($io) && $io)
                <label class="form-label">{{ __('Linked Record') }}</label>
                <div class="form-control bg-light">{{ $io->title ?: 'Untitled' }}</div>
                <input type="hidden" name="information_object_id" value="{{ $io->id }}">
              @else
                <label class="form-label">{{ __('Link to Archival Record') }}</label>
                <div class="position-relative">
                  <input type="text" id="ioSearch" class="form-control" placeholder="{{ __('Type to search...') }}" autocomplete="off" value="{{ old('io_title', '') }}">
                  <div id="ioResults" class="autocomplete-dropdown"></div>
                </div>
                <input type="hidden" name="information_object_id" id="ioId" value="{{ old('information_object_id', '') }}">
                <small class="text-muted">{{ __('Optional: Link to archival description') }}</small>
              @endif
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Accounting Standard') }}</label>
              <select name="accounting_standard_id" class="form-select">
                <option value="">{{ __('-- Select Standard --') }}</option>
                @foreach($standards ?? [] as $s)
                  <option value="{{ $s->id }}" {{ old('accounting_standard_id') == $s->id ? 'selected' : '' }}>{{ $s->code . ' - ' . $s->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Asset Class') }}</label>
              <select name="asset_class_id" class="form-select">
                <option value="">{{ __('-- Select Class --') }}</option>
                @foreach($classes ?? [] as $c)
                  <option value="{{ $c->id }}" {{ old('asset_class_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Sub-class') }}</label>
              <input type="text" name="asset_sub_class" class="form-control" value="{{ old('asset_sub_class') }}">
            </div>
          </div>
        </div>
      </div>

      {{-- Recognition --}}
      <div class="card mb-4">
        <div class="card-header bg-dark text-white"><h5 class="mb-0">{{ __('Recognition') }}</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">{{ __('Recognition Status') }}</label>
              <select name="recognition_status" class="form-select">
                <option value="pending" {{ old('recognition_status', 'pending') == 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                <option value="recognised" {{ old('recognition_status') == 'recognised' ? 'selected' : '' }}>{{ __('Recognised') }}</option>
                <option value="not_recognised" {{ old('recognition_status') == 'not_recognised' ? 'selected' : '' }}>{{ __('Not Recognised') }}</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Recognition Date') }}</label>
              <input type="date" name="recognition_date" class="form-control" value="{{ old('recognition_date') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Measurement Basis') }}</label>
              <select name="measurement_basis" class="form-select">
                <option value="cost" {{ old('measurement_basis', 'cost') == 'cost' ? 'selected' : '' }}>{{ __('Cost') }}</option>
                <option value="fair_value" {{ old('measurement_basis') == 'fair_value' ? 'selected' : '' }}>{{ __('Fair Value') }}</option>
                <option value="nominal" {{ old('measurement_basis') == 'nominal' ? 'selected' : '' }}>{{ __('Nominal') }}</option>
                <option value="not_practicable" {{ old('measurement_basis') == 'not_practicable' ? 'selected' : '' }}>{{ __('Not Practicable') }}</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Recognition Status Reason') }}</label>
              <textarea name="recognition_status_reason" class="form-control" rows="2">{{ old('recognition_status_reason') }}</textarea>
            </div>
          </div>
        </div>
      </div>

      {{-- Acquisition --}}
      <div class="card mb-4">
        <div class="card-header bg-dark text-white"><h5 class="mb-0">{{ __('Acquisition') }}</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">{{ __('Acquisition Method') }}</label>
              <select name="acquisition_method" class="form-select">
                <option value="">{{ __('-- Select --') }}</option>
                <option value="purchase" {{ old('acquisition_method') == 'purchase' ? 'selected' : '' }}>{{ __('Purchase') }}</option>
                <option value="donation" {{ old('acquisition_method') == 'donation' ? 'selected' : '' }}>{{ __('Donation') }}</option>
                <option value="bequest" {{ old('acquisition_method') == 'bequest' ? 'selected' : '' }}>{{ __('Bequest') }}</option>
                <option value="transfer" {{ old('acquisition_method') == 'transfer' ? 'selected' : '' }}>{{ __('Transfer') }}</option>
                <option value="found" {{ old('acquisition_method') == 'found' ? 'selected' : '' }}>{{ __('Found') }}</option>
                <option value="exchange" {{ old('acquisition_method') == 'exchange' ? 'selected' : '' }}>{{ __('Exchange') }}</option>
                <option value="other" {{ old('acquisition_method') == 'other' ? 'selected' : '' }}>{{ __('Other') }}</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Acquisition Date') }}</label>
              <input type="date" name="acquisition_date" class="form-control" value="{{ old('acquisition_date') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Acquisition Cost') }}</label>
              <input type="number" step="0.01" name="acquisition_cost" class="form-control" value="{{ old('acquisition_cost', '0.00') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Fair Value at Acquisition') }}</label>
              <input type="number" step="0.01" name="fair_value_at_acquisition" class="form-control" value="{{ old('fair_value_at_acquisition') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Nominal Value') }}</label>
              <input type="number" step="0.01" name="nominal_value" class="form-control" value="{{ old('nominal_value', '1.00') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Donor Name') }}</label>
              <input type="text" name="donor_name" class="form-control" value="{{ old('donor_name') }}">
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Donor Restrictions') }}</label>
              <textarea name="donor_restrictions" class="form-control" rows="2">{{ old('donor_restrictions') }}</textarea>
            </div>
          </div>
        </div>
      </div>

      {{-- Carrying Amounts --}}
      <div class="card mb-4">
        <div class="card-header bg-dark text-white"><h5 class="mb-0">{{ __('Carrying Amounts') }}</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">{{ __('Initial Carrying Amount') }}</label>
              <input type="number" step="0.01" name="initial_carrying_amount" class="form-control" value="{{ old('initial_carrying_amount', '0.00') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Current Carrying Amount') }}</label>
              <input type="number" step="0.01" name="current_carrying_amount" class="form-control" value="{{ old('current_carrying_amount', '0.00') }}">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      {{-- Heritage Information --}}
      <div class="card mb-4">
        <div class="card-header bg-dark text-white"><h5 class="mb-0">{{ __('Heritage Information') }}</h5></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Significance') }}</label>
            <select name="heritage_significance" class="form-select">
              <option value="">{{ __('-- Select --') }}</option>
              <option value="exceptional" {{ old('heritage_significance') == 'exceptional' ? 'selected' : '' }}>{{ __('Exceptional') }}</option>
              <option value="high" {{ old('heritage_significance') == 'high' ? 'selected' : '' }}>{{ __('High') }}</option>
              <option value="medium" {{ old('heritage_significance') == 'medium' ? 'selected' : '' }}>{{ __('Medium') }}</option>
              <option value="low" {{ old('heritage_significance') == 'low' ? 'selected' : '' }}>{{ __('Low') }}</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Significance Statement') }}</label>
            <textarea name="significance_statement" class="form-control" rows="3">{{ old('significance_statement') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Current Location') }}</label>
            <input type="text" name="current_location" class="form-control" value="{{ old('current_location') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Condition') }}</label>
            <select name="condition_rating" class="form-select">
              <option value="">{{ __('-- Select --') }}</option>
              <option value="excellent" {{ old('condition_rating') == 'excellent' ? 'selected' : '' }}>{{ __('Excellent') }}</option>
              <option value="good" {{ old('condition_rating') == 'good' ? 'selected' : '' }}>{{ __('Good') }}</option>
              <option value="fair" {{ old('condition_rating') == 'fair' ? 'selected' : '' }}>{{ __('Fair') }}</option>
              <option value="poor" {{ old('condition_rating') == 'poor' ? 'selected' : '' }}>{{ __('Poor') }}</option>
              <option value="critical" {{ old('condition_rating') == 'critical' ? 'selected' : '' }}>{{ __('Critical') }}</option>
            </select>
          </div>
        </div>
      </div>

      {{-- Insurance --}}
      <div class="card mb-4">
        <div class="card-header bg-dark text-white"><h5 class="mb-0">{{ __('Insurance') }}</h5></div>
        <div class="card-body">
          <div class="form-check mb-3">
            <input type="checkbox" name="insurance_required" class="form-check-input" value="1" {{ old('insurance_required', 1) ? 'checked' : '' }}>
            <label class="form-check-label">{{ __('Insurance Required') }}</label>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Insurance Value') }}</label>
            <input type="number" step="0.01" name="insurance_value" class="form-control" value="{{ old('insurance_value') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Policy Number') }}</label>
            <input type="text" name="insurance_policy_number" class="form-control" value="{{ old('insurance_policy_number') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Provider') }}</label>
            <input type="text" name="insurance_provider" class="form-control" value="{{ old('insurance_provider') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Expiry Date') }}</label>
            <input type="date" name="insurance_expiry_date" class="form-control" value="{{ old('insurance_expiry_date') }}">
          </div>
        </div>
      </div>

      {{-- Notes --}}
      <div class="card mb-4">
        <div class="card-header bg-dark text-white"><h5 class="mb-0">{{ __('Notes') }}</h5></div>
        <div class="card-body">
          <textarea name="notes" class="form-control" rows="4">{{ old('notes') }}</textarea>
        </div>
      </div>

      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i>{{ __('Save Asset') }}</button>
        @if(isset($io) && $io)
          <a href="{{ url('/' . $io->slug) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        @else
          <a href="{{ route('heritage.accounting.browse') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        @endif
      </div>
    </div>
  </div>
</form>

<style>
.autocomplete-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    max-height: 250px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.autocomplete-dropdown .ac-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
}
.autocomplete-dropdown .ac-item:hover {
    background-color: #f5f5f5;
}
.autocomplete-dropdown .ac-item:last-child {
    border-bottom: none;
}
</style>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('ioSearch');
    var resultsDiv = document.getElementById('ioResults');
    var hiddenInput = document.getElementById('ioId');
    var debounceTimer;

    if (!searchInput || !resultsDiv || !hiddenInput) return;

    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(debounceTimer);
        hiddenInput.value = '';

        if (query.length < 2) {
            resultsDiv.style.display = 'none';
            resultsDiv.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(function() {
            fetch('/api/informationobject/autocomplete?query=' + encodeURIComponent(query))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.length === 0) {
                        resultsDiv.style.display = 'none';
                        return;
                    }
                    resultsDiv.innerHTML = data.map(function(item) {
                        return '<div class="ac-item" data-id="' + item.id + '" data-label="' + (item.label || item.title || '').replace(/"/g, '&quot;') + '">' + (item.label || item.title || '') + '</div>';
                    }).join('');
                    resultsDiv.style.display = 'block';
                })
                .catch(function() { resultsDiv.style.display = 'none'; });
        }, 300);
    });

    resultsDiv.addEventListener('click', function(e) {
        if (e.target.classList.contains('ac-item')) {
            searchInput.value = e.target.dataset.label;
            hiddenInput.value = e.target.dataset.id;
            resultsDiv.style.display = 'none';
        }
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#ioSearch') && !e.target.closest('#ioResults')) {
            resultsDiv.style.display = 'none';
        }
    });
});
</script>
@endpush
@endsection
