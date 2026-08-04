{{--
  Shared heritage-asset create/edit form body (no <form> tag - the including
  view supplies it, plus @method('PUT') on edit). Expects:
    $asset     nullable heritage_asset row (null = create)
    $standards heritage_accounting_standard collection
    $classes   heritage_asset_class collection
    $io        nullable linked archival record (id/title/slug)
--}}
@php
    $asset = $asset ?? null;
    $io = $io ?? null;
    // Prefill: submitted-old wins, then the persisted asset value, then a default.
    $v = fn ($f, $d = '') => old($f, data_get($asset, $f, $d));
    $isSel = fn ($f, $opt, $d = '') => ((string) $v($f, $d) === (string) $opt) ? 'selected' : '';
    $linkedIoId = $io->id ?? data_get($asset, 'information_object_id');
@endphp

@if(isset($errors) && $errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="row">
  <div class="col-md-8">

    {{-- Identification & classification --}}
    <div class="card mb-4">
      <div class="card-header" style="background:#10373E;color:#fff"><h5 class="mb-0">{{ __('Identification & classification') }}</h5></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            @if($io)
              <label class="form-label">{{ __('Linked record') }}</label>
              <div class="form-control bg-light">{{ $io->title ?: ($io->slug ?: ('#' . $linkedIoId)) }}</div>
              <input type="hidden" name="information_object_id" value="{{ $linkedIoId }}">
            @else
              <label class="form-label">{{ __('Link to archival record') }}</label>
              <div class="position-relative">
                <input type="text" id="ioSearch" class="form-control" placeholder="{{ __('Type to search...') }}" autocomplete="off">
                <div id="ioResults" class="autocomplete-dropdown"></div>
              </div>
              <input type="hidden" name="information_object_id" id="ioId" value="{{ $v('information_object_id') }}">
              <small class="text-muted">{{ __('Optional: link to an archival description') }}</small>
            @endif
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('Accounting standard') }}</label>
            <select name="accounting_standard_id" class="form-select">
              <option value="">{{ __('-- Select standard --') }}</option>
              @foreach($standards ?? [] as $s)
                <option value="{{ $s->id }}" {{ $isSel('accounting_standard_id', $s->id) }}>{{ $s->code . ' - ' . $s->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('Asset class') }}</label>
            <select name="asset_class_id" class="form-select">
              <option value="">{{ __('-- Select class --') }}</option>
              @foreach($classes ?? [] as $c)
                <option value="{{ $c->id }}" {{ $isSel('asset_class_id', $c->id) }}>{{ $c->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('Sub-class') }}</label>
            <input type="text" name="asset_sub_class" class="form-control" value="{{ $v('asset_sub_class') }}">
          </div>
        </div>
      </div>
    </div>

    {{-- Recognition --}}
    <div class="card mb-4">
      <div class="card-header" style="background:#10373E;color:#fff"><h5 class="mb-0">{{ __('Recognition') }}</h5></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">{{ __('Recognition status') }}</label>
            <select name="recognition_status" class="form-select">
              <option value="pending" {{ $isSel('recognition_status', 'pending', 'pending') }}>{{ __('Pending') }}</option>
              <option value="recognised" {{ $isSel('recognition_status', 'recognised') }}>{{ __('Recognised') }}</option>
              <option value="not_recognised" {{ $isSel('recognition_status', 'not_recognised') }}>{{ __('Not Recognised') }}</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Recognition date') }}</label>
            <input type="date" name="recognition_date" class="form-control" value="{{ $v('recognition_date') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Measurement basis') }}</label>
            <select name="measurement_basis" class="form-select">
              <option value="cost" {{ $isSel('measurement_basis', 'cost', 'cost') }}>{{ __('Cost') }}</option>
              <option value="fair_value" {{ $isSel('measurement_basis', 'fair_value') }}>{{ __('Fair Value') }}</option>
              <option value="nominal" {{ $isSel('measurement_basis', 'nominal') }}>{{ __('Nominal') }}</option>
              <option value="not_practicable" {{ $isSel('measurement_basis', 'not_practicable') }}>{{ __('Not Practicable') }}</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">{{ __('Recognition status reason') }}</label>
            <textarea name="recognition_status_reason" class="form-control" rows="2">{{ $v('recognition_status_reason') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    {{-- Acquisition & initial measurement --}}
    <div class="card mb-4">
      <div class="card-header" style="background:#10373E;color:#fff"><h5 class="mb-0">{{ __('Acquisition & initial measurement') }}</h5></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">{{ __('Acquisition method') }}</label>
            <select name="acquisition_method" class="form-select">
              <option value="">{{ __('-- Select --') }}</option>
              @foreach(['purchase'=>'Purchase','donation'=>'Donation','bequest'=>'Bequest','transfer'=>'Transfer','found'=>'Found','exchange'=>'Exchange','other'=>'Other'] as $mv => $ml)
                <option value="{{ $mv }}" {{ $isSel('acquisition_method', $mv) }}>{{ __($ml) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Acquisition date') }}</label>
            <input type="date" name="acquisition_date" class="form-control" value="{{ $v('acquisition_date') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Acquisition cost') }}</label>
            <input type="number" step="0.01" name="acquisition_cost" class="form-control" value="{{ $v('acquisition_cost', '0.00') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Fair value at acquisition') }}</label>
            <input type="number" step="0.01" name="fair_value_at_acquisition" class="form-control" value="{{ $v('fair_value_at_acquisition') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Nominal value') }}</label>
            <input type="number" step="0.01" name="nominal_value" class="form-control" value="{{ $v('nominal_value', '1.00') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Donor name') }}</label>
            <input type="text" name="donor_name" class="form-control" value="{{ $v('donor_name') }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('Initial carrying amount') }}</label>
            <input type="number" step="0.01" name="initial_carrying_amount" class="form-control" value="{{ $v('initial_carrying_amount', '0.00') }}">
          </div>
          <div class="col-12">
            <label class="form-label">{{ __('Donor restrictions') }}</label>
            <textarea name="donor_restrictions" class="form-control" rows="2">{{ $v('donor_restrictions') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    {{-- Carrying amount & valuation --}}
    <div class="card mb-4">
      <div class="card-header" style="background:#10373E;color:#fff"><h5 class="mb-0">{{ __('Carrying amount & valuation') }}</h5></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">{{ __('Current carrying amount') }}</label>
            <input type="number" step="0.01" name="current_carrying_amount" class="form-control" value="{{ $v('current_carrying_amount', '0.00') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Last valuation date') }}</label>
            <input type="date" name="last_valuation_date" class="form-control" value="{{ $v('last_valuation_date') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Last valuation amount') }}</label>
            <input type="number" step="0.01" name="last_valuation_amount" class="form-control" value="{{ $v('last_valuation_amount') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Valuation method') }}</label>
            <input type="text" name="valuation_method" class="form-control" value="{{ $v('valuation_method') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Valuer') }}</label>
            <input type="text" name="valuer_name" class="form-control" value="{{ $v('valuer_name') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Valuer credentials') }}</label>
            <input type="text" name="valuer_credentials" class="form-control" value="{{ $v('valuer_credentials') }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('Valuation report reference') }}</label>
            <input type="text" name="valuation_report_reference" class="form-control" value="{{ $v('valuation_report_reference') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Revaluation frequency') }}</label>
            <input type="text" name="revaluation_frequency" class="form-control" value="{{ $v('revaluation_frequency') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Revaluation surplus') }}</label>
            <input type="number" step="0.01" name="revaluation_surplus" class="form-control" value="{{ $v('revaluation_surplus') }}">
          </div>
        </div>
      </div>
    </div>

    {{-- Depreciation --}}
    <div class="card mb-4">
      <div class="card-header" style="background:#10373E;color:#fff"><h5 class="mb-0">{{ __('Depreciation') }}</h5></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">{{ __('Useful life (years)') }}</label>
            <input type="number" name="useful_life_years" class="form-control" value="{{ $v('useful_life_years') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Residual value') }}</label>
            <input type="number" step="0.01" name="residual_value" class="form-control" value="{{ $v('residual_value') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Annual depreciation') }}</label>
            <input type="number" step="0.01" name="annual_depreciation" class="form-control" value="{{ $v('annual_depreciation') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Accumulated depreciation') }}</label>
            <input type="number" step="0.01" name="accumulated_depreciation" class="form-control" value="{{ $v('accumulated_depreciation') }}">
          </div>
          <div class="col-12">
            <label class="form-label">{{ __('Depreciation policy') }}</label>
            <textarea name="depreciation_policy" class="form-control" rows="2">{{ $v('depreciation_policy') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    {{-- Impairment --}}
    <div class="card mb-4">
      <div class="card-header" style="background:#10373E;color:#fff"><h5 class="mb-0">{{ __('Impairment') }}</h5></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">{{ __('Last impairment assessment') }}</label>
            <input type="date" name="last_impairment_date" class="form-control" value="{{ $v('last_impairment_date') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Impairment loss') }}</label>
            <input type="number" step="0.01" name="impairment_loss" class="form-control" value="{{ $v('impairment_loss') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Recoverable amount') }}</label>
            <input type="number" step="0.01" name="recoverable_amount" class="form-control" value="{{ $v('recoverable_amount') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Impairment indicators') }}</label>
            <input type="text" name="impairment_indicators" class="form-control" value="{{ $v('impairment_indicators') }}">
          </div>
          <div class="col-12">
            <label class="form-label">{{ __('Indicator details') }}</label>
            <textarea name="impairment_indicators_details" class="form-control" rows="2">{{ $v('impairment_indicators_details') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    {{-- Derecognition --}}
    <div class="card mb-4">
      <div class="card-header" style="background:#10373E;color:#fff"><h5 class="mb-0">{{ __('Derecognition') }}</h5></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">{{ __('Derecognition date') }}</label>
            <input type="date" name="derecognition_date" class="form-control" value="{{ $v('derecognition_date') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Proceeds') }}</label>
            <input type="number" step="0.01" name="derecognition_proceeds" class="form-control" value="{{ $v('derecognition_proceeds') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Gain / loss on derecognition') }}</label>
            <input type="number" step="0.01" name="gain_loss_on_derecognition" class="form-control" value="{{ $v('gain_loss_on_derecognition') }}">
          </div>
          <div class="col-12">
            <label class="form-label">{{ __('Reason') }}</label>
            <textarea name="derecognition_reason" class="form-control" rows="2">{{ $v('derecognition_reason') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    {{-- Restrictions & conservation --}}
    <div class="card mb-4">
      <div class="card-header" style="background:#10373E;color:#fff"><h5 class="mb-0">{{ __('Restrictions & conservation') }}</h5></div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">{{ __('Restrictions on use') }}</label>
          <textarea name="restrictions_on_use" class="form-control" rows="2">{{ $v('restrictions_on_use') }}</textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Restrictions on disposal') }}</label>
          <textarea name="restrictions_on_disposal" class="form-control" rows="2">{{ $v('restrictions_on_disposal') }}</textarea>
        </div>
        <div class="mb-0">
          <label class="form-label">{{ __('Conservation requirements') }}</label>
          <textarea name="conservation_requirements" class="form-control" rows="2">{{ $v('conservation_requirements') }}</textarea>
        </div>
      </div>
    </div>

  </div>

  <div class="col-md-4">
    {{-- Heritage information --}}
    <div class="card mb-4">
      <div class="card-header" style="background:#10373E;color:#fff"><h5 class="mb-0">{{ __('Heritage information') }}</h5></div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">{{ __('Significance') }}</label>
          <select name="heritage_significance" class="form-select">
            <option value="">{{ __('-- Select --') }}</option>
            @foreach(['exceptional'=>'Exceptional','high'=>'High','medium'=>'Medium','low'=>'Low'] as $sv => $sl)
              <option value="{{ $sv }}" {{ $isSel('heritage_significance', $sv) }}>{{ __($sl) }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-0">
          <label class="form-label">{{ __('Significance statement') }}</label>
          <textarea name="significance_statement" class="form-control" rows="3">{{ $v('significance_statement') }}</textarea>
        </div>
      </div>
    </div>

    {{-- Location & condition --}}
    <div class="card mb-4">
      <div class="card-header" style="background:#10373E;color:#fff"><h5 class="mb-0">{{ __('Location & condition') }}</h5></div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">{{ __('Current location') }}</label>
          <input type="text" name="current_location" class="form-control" value="{{ $v('current_location') }}">
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Condition') }}</label>
          <select name="condition_rating" class="form-select">
            <option value="">{{ __('-- Select --') }}</option>
            @foreach(['excellent'=>'Excellent','good'=>'Good','fair'=>'Fair','poor'=>'Poor','critical'=>'Critical'] as $cv => $cl)
              <option value="{{ $cv }}" {{ $isSel('condition_rating', $cv) }}>{{ __($cl) }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Last condition assessment') }}</label>
          <input type="date" name="last_condition_assessment" class="form-control" value="{{ $v('last_condition_assessment') }}">
        </div>
        <div class="mb-0">
          <label class="form-label">{{ __('Storage conditions') }}</label>
          <textarea name="storage_conditions" class="form-control" rows="2">{{ $v('storage_conditions') }}</textarea>
        </div>
      </div>
    </div>

    {{-- Insurance --}}
    <div class="card mb-4">
      <div class="card-header" style="background:#10373E;color:#fff"><h5 class="mb-0">{{ __('Insurance') }}</h5></div>
      <div class="card-body">
        <div class="form-check mb-3">
          <input type="hidden" name="insurance_required" value="0">
          <input type="checkbox" name="insurance_required" class="form-check-input" value="1" {{ $v('insurance_required', 1) ? 'checked' : '' }}>
          <label class="form-check-label">{{ __('Insurance required') }}</label>
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Insured value') }}</label>
          <input type="number" step="0.01" name="insurance_value" class="form-control" value="{{ $v('insurance_value') }}">
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Policy number') }}</label>
          <input type="text" name="insurance_policy_number" class="form-control" value="{{ $v('insurance_policy_number') }}">
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Provider') }}</label>
          <input type="text" name="insurance_provider" class="form-control" value="{{ $v('insurance_provider') }}">
        </div>
        <div class="mb-0">
          <label class="form-label">{{ __('Policy expiry') }}</label>
          <input type="date" name="insurance_expiry_date" class="form-control" value="{{ $v('insurance_expiry_date') }}">
        </div>
      </div>
    </div>

    {{-- Notes --}}
    <div class="card mb-4">
      <div class="card-header" style="background:#10373E;color:#fff"><h5 class="mb-0">{{ __('Notes') }}</h5></div>
      <div class="card-body">
        <textarea name="notes" class="form-control" rows="4">{{ $v('notes') }}</textarea>
      </div>
    </div>
  </div>
</div>

@unless($io)
<style>
.autocomplete-dropdown { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #ddd; border-radius:4px; max-height:250px; overflow-y:auto; z-index:1000; display:none; box-shadow:0 2px 8px rgba(0,0,0,0.15); }
.autocomplete-dropdown .ac-item { padding:8px 12px; cursor:pointer; border-bottom:1px solid #eee; }
.autocomplete-dropdown .ac-item:hover { background-color:#f5f5f5; }
.autocomplete-dropdown .ac-item:last-child { border-bottom:none; }
</style>
@push('js')
<script nonce="{{ csp_nonce() }}">
document.addEventListener('DOMContentLoaded', function () {
  var searchInput = document.getElementById('ioSearch');
  var resultsDiv = document.getElementById('ioResults');
  var hiddenInput = document.getElementById('ioId');
  var debounceTimer;
  if (!searchInput || !resultsDiv || !hiddenInput) return;
  searchInput.addEventListener('input', function () {
    var query = this.value.trim();
    clearTimeout(debounceTimer);
    hiddenInput.value = '';
    if (query.length < 2) { resultsDiv.style.display = 'none'; resultsDiv.innerHTML = ''; return; }
    debounceTimer = setTimeout(function () {
      fetch('/api/informationobject/autocomplete?query=' + encodeURIComponent(query))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.length) { resultsDiv.style.display = 'none'; return; }
          resultsDiv.innerHTML = data.map(function (item) {
            return '<div class="ac-item" data-id="' + item.id + '" data-label="' + (item.label || item.title || '').replace(/"/g, '&quot;') + '">' + (item.label || item.title || '') + '</div>';
          }).join('');
          resultsDiv.style.display = 'block';
        })
        .catch(function () { resultsDiv.style.display = 'none'; });
    }, 300);
  });
  resultsDiv.addEventListener('click', function (e) {
    if (e.target.classList.contains('ac-item')) {
      searchInput.value = e.target.dataset.label;
      hiddenInput.value = e.target.dataset.id;
      resultsDiv.style.display = 'none';
    }
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('#ioSearch') && !e.target.closest('#ioResults')) { resultsDiv.style.display = 'none'; }
  });
});
</script>
@endpush
@endunless
