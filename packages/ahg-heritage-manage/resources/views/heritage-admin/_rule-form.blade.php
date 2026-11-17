@php
  $isEdit = (bool) $rule;
  $val = fn ($f, $d = '') => old($f, $isEdit ? ($rule->{$f} ?? $d) : $d);
  $selStd = old('standard_id', $isEdit ? $rule->standard_id : $preselectedStandard);
  $sevLabels = ['error' => __('Must fix'), 'warning' => __('Should fix'), 'info' => __('Best practice')];
  $sevColors = ['error' => 'danger', 'warning' => 'warning', 'info' => 'secondary'];
  $isActive = old('_submitted') ? (bool) old('is_active') : (!$isEdit || $rule->is_active);
@endphp
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex align-items-center mb-3">
      <a href="{{ route('heritage.hadmin.rule-list', array_filter(['standard_id' => $selStd])) }}" class="btn btn-outline-secondary btn-sm me-3" aria-label="{{ __('Back') }}"><i class="fas fa-arrow-left"></i></a>
      <h1 class="h3 mb-0"><i class="fas fa-clipboard-check me-2"></i>{{ $isEdit ? __('Edit Compliance Rule') : __('Add Compliance Rule') }}</h1>
    </div>

    <form method="post" action="{{ $formAction }}">
      @csrf
      @if($isEdit) @method('PUT') @endif
      <input type="hidden" name="_submitted" value="1">
      <div class="row">
        <div class="col-lg-8">
          <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Rule Details') }}</div>
            <div class="card-body">
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label" for="ruleStd">{{ __('Accounting Standard') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                  <select id="ruleStd" name="standard_id" class="form-select" required>
                    <option value="">{{ __('Select Standard...') }}</option>
                    @foreach($standards as $s)
                      <option value="{{ $s->id }}" {{ (string) $selStd === (string) $s->id ? 'selected' : '' }}>{{ $s->code }} - {{ $s->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label" for="ruleCat">{{ __('Category') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                  <select id="ruleCat" name="category" class="form-select" required>
                    @foreach($categories as $code => $label)
                      <option value="{{ $code }}" {{ $val('category') === $code ? 'selected' : '' }}>{{ __($label) }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label" for="ruleCode">{{ __('Rule Code') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                  <input type="text" id="ruleCode" name="code" class="form-control text-uppercase" required maxlength="50" value="{{ $val('code') }}" placeholder="{{ __('e.g. REC001') }}">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label" for="ruleName">{{ __('Rule Name') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                <input type="text" id="ruleName" name="name" class="form-control" required maxlength="255" value="{{ $val('name') }}" placeholder="{{ __('e.g. Asset Class Required') }}">
              </div>
              <div class="mb-3">
                <label class="form-label" for="ruleDesc">{{ __('Description') }}</label>
                <textarea id="ruleDesc" name="description" class="form-control" rows="2">{{ $val('description') }}</textarea>
              </div>
              <div class="mb-3">
                <label class="form-label" for="ruleErr">{{ __('Error Message') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                <input type="text" id="ruleErr" name="error_message" class="form-control" required maxlength="255" value="{{ $val('error_message') }}" placeholder="{{ __('Message shown when rule fails') }}">
              </div>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label" for="ruleRef">{{ __('Standard Reference') }}</label>
                  <input type="text" id="ruleRef" name="reference" class="form-control" maxlength="100" value="{{ $val('reference') }}" placeholder="{{ __('e.g. IPSAS 45.12') }}">
                </div>
                <div class="col-md-3">
                  <label class="form-label" for="ruleSort">{{ __('Sort Order') }}</label>
                  <input type="number" id="ruleSort" name="sort_order" class="form-control" min="0" value="{{ $val('sort_order', 0) }}">
                </div>
              </div>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Validation Logic') }}</div>
            <div class="card-body">
              <div class="row mb-3">
                <div class="col-md-4">
                  <label class="form-label" for="ruleCheck">{{ __('Check Type') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                  <select id="ruleCheck" name="check_type" class="form-select" required>
                    @foreach($checkTypes as $code => $label)
                      <option value="{{ $code }}" {{ $val('check_type') === $code ? 'selected' : '' }}>{{ __($label) }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="ruleField">{{ __('Field Name') }}</label>
                  <input type="text" id="ruleField" name="field_name" class="form-control" maxlength="100" value="{{ $val('field_name') }}" placeholder="{{ __('e.g. asset_class_id') }}">
                  <small class="text-muted">{{ __('Database field to check') }}</small>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="ruleCond">{{ __('Condition') }}</label>
                  <input type="text" id="ruleCond" name="condition" class="form-control" maxlength="255" value="{{ $val('condition') }}" placeholder="{{ __('e.g. >0, >=1, !=0') }}">
                  <small class="text-muted">{{ __('For value_check type') }}</small>
                </div>
              </div>
              <div class="alert alert-info mb-0">
                <strong>{{ __('Available Fields:') }}</strong>
                @foreach(['asset_class_id', 'recognition_date', 'recognition_status', 'measurement_basis', 'acquisition_date', 'acquisition_cost', 'current_carrying_amount', 'fair_value_at_acquisition', 'heritage_significance', 'significance_statement', 'restrictions_on_use', 'restrictions_on_disposal', 'conservation_requirements', 'insurance_value'] as $f)<code>{{ $f }}</code>{{ $loop->last ? '' : ', ' }}@endforeach
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Settings') }}</div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">{{ __('Severity') }}</label>
                @foreach($severities as $sev => $label)
                  <div class="form-check">
                    <input type="radio" name="severity" value="{{ $sev }}" class="form-check-input" id="sev_{{ $sev }}" {{ $val('severity', 'error') === $sev ? 'checked' : '' }}>
                    <label class="form-check-label" for="sev_{{ $sev }}"><span class="badge bg-{{ $sevColors[$sev] ?? 'secondary' }}">{{ __($label) }}</span> @if(isset($sevLabels[$sev]))- {{ $sevLabels[$sev] }}@endif</label>
                  </div>
                @endforeach
              </div>
              <div class="form-check form-switch">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" {{ $isActive ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active"><strong>{{ __('Active') }}</strong></label>
              </div>
            </div>
          </div>
          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>{{ $isEdit ? __('Update Rule') : __('Add Rule') }}</button>
            <a href="{{ route('heritage.hadmin.rule-list', array_filter(['standard_id' => $selStd])) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
