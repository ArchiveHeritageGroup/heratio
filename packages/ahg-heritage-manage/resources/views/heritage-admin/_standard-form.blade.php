@php
  $isEdit = (bool) $standard;
  $val = fn ($f, $d = '') => old($f, $isEdit ? ($standard->{$f} ?? $d) : $d);
  $selectedMethods = old('valuation_methods', $isEdit ? (json_decode((string) $standard->valuation_methods, true) ?: []) : []);
  $disclosure = old('disclosure_requirements', $isEdit ? implode("\n", json_decode((string) $standard->disclosure_requirements, true) ?: []) : '');
  $isActive = old('_submitted') ? (bool) old('is_active') : (!$isEdit || $standard->is_active);
  $capReq = old('_submitted') ? (bool) old('capitalisation_required') : ($isEdit && $standard->capitalisation_required);
@endphp
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex align-items-center mb-3">
      <a href="{{ route('heritage.hadmin.standard-list') }}" class="btn btn-outline-secondary btn-sm me-3" aria-label="{{ __('Back') }}"><i class="fas fa-arrow-left"></i></a>
      <h1 class="h3 mb-0"><i class="fas fa-balance-scale me-2"></i>{{ $isEdit ? __('Edit Accounting Standard') : __('Add Accounting Standard') }}</h1>
    </div>

    <form method="post" action="{{ $formAction }}">
      @csrf
      @if($isEdit) @method('PUT') @endif
      <input type="hidden" name="_submitted" value="1">
      <div class="row">
        <div class="col-lg-8">
          <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Standard Details') }}</div>
            <div class="card-body">
              <div class="row mb-3">
                <div class="col-md-4">
                  <label class="form-label" for="stdCode">{{ __('Code') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                  <input type="text" id="stdCode" name="code" class="form-control text-uppercase" required maxlength="20" value="{{ $val('code') }}" placeholder="{{ __('e.g. IPSAS45, FRS102') }}">
                  <small class="text-muted">{{ __('Unique identifier, uppercase') }}</small>
                </div>
                <div class="col-md-8">
                  <label class="form-label" for="stdName">{{ __('Name') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                  <input type="text" id="stdName" name="name" class="form-control" required maxlength="100" value="{{ $val('name') }}" placeholder="{{ __('e.g. IPSAS 45 Property, Plant and Equipment') }}">
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label" for="stdCountry">{{ __('Country / Region') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                  <input type="text" id="stdCountry" name="country" class="form-control" required maxlength="50" value="{{ $val('country') }}" placeholder="{{ __('e.g. United Kingdom, International') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="stdSort">{{ __('Sort Order') }}</label>
                  <input type="number" id="stdSort" name="sort_order" class="form-control" min="0" value="{{ $val('sort_order', 99) }}">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label" for="stdDesc">{{ __('Description') }}</label>
                <textarea id="stdDesc" name="description" class="form-control" rows="3" placeholder="{{ __('Brief description of the standard and its applicability...') }}">{{ $val('description') }}</textarea>
              </div>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Requirements') }}</div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">{{ __('Valuation Methods Allowed') }}</label>
                <div class="row">
                  @foreach($valuationMethods as $key => $label)
                    <div class="col-md-4">
                      <div class="form-check">
                        <input type="checkbox" name="valuation_methods[]" value="{{ $key }}" class="form-check-input" id="vm_{{ $key }}" {{ in_array($key, (array) $selectedMethods, true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="vm_{{ $key }}">{{ __($label) }}</label>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label" for="stdDisclosure">{{ __('Disclosure Requirements') }}</label>
                <textarea id="stdDisclosure" name="disclosure_requirements" class="form-control" rows="5" placeholder="{{ __('One requirement per line...') }}">{{ $disclosure }}</textarea>
                <small class="text-muted">{{ __('Enter one disclosure requirement per line') }}</small>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Settings') }}</div>
            <div class="card-body">
              <div class="form-check form-switch mb-3">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" {{ $isActive ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active"><strong>{{ __('Active') }}</strong><br><small class="text-muted">{{ __('Available for selection') }}</small></label>
              </div>
              <div class="form-check form-switch mb-3">
                <input type="checkbox" name="capitalisation_required" value="1" class="form-check-input" id="capitalisation_required" {{ $capReq ? 'checked' : '' }}>
                <label class="form-check-label" for="capitalisation_required"><strong>{{ __('Capitalisation Required') }}</strong><br><small class="text-muted">{{ __('Monetary value must be recorded') }}</small></label>
              </div>
            </div>
          </div>
          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>{{ $isEdit ? __('Update Standard') : __('Add Standard') }}</button>
            <a href="{{ route('heritage.hadmin.standard-list') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
