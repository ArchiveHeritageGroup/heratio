@csrf
@if(isset($item) && $item)
  @method('PUT')
@endif

@php
  $spec = $item->specialisations ?? null;
  if (is_string($spec) && $spec !== '') {
      $decoded = json_decode($spec, true);
      $spec = is_array($decoded) ? implode(', ', $decoded) : '';
  } elseif (! $spec) {
      $spec = '';
  }
@endphp

<div class="card mb-3">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="bi bi-person-badge me-2"></i>{{ __('Valuer Details') }}</div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required value="{{ old('name', $item->name ?? '') }}">
      </div>
      <div class="col-md-6">
        <label class="form-label">{{ __('Credential') }}</label>
        <input type="text" name="credential" class="form-control" placeholder="{{ __('e.g. RICS, ASA, AIC member') }}" value="{{ old('credential', $item->credential ?? '') }}">
      </div>
      <div class="col-md-6">
        <label class="form-label">{{ __('Professional Body') }}</label>
        <input type="text" name="professional_body" class="form-control" placeholder="{{ __('e.g. Royal Institution of Chartered Surveyors') }}" value="{{ old('professional_body', $item->professional_body ?? '') }}">
      </div>
      <div class="col-md-6">
        <label class="form-label">{{ __('Accreditation Number') }}</label>
        <input type="text" name="accreditation_number" class="form-control" value="{{ old('accreditation_number', $item->accreditation_number ?? '') }}">
      </div>
      <div class="col-md-6">
        <label class="form-label">{{ __('Email') }}</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $item->email ?? '') }}">
      </div>
      <div class="col-md-6">
        <label class="form-label">{{ __('Phone') }}</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone', $item->phone ?? '') }}">
      </div>
      <div class="col-md-12">
        <label class="form-label">{{ __('Specialisations') }} <small class="text-muted">{{ __('comma-separated, e.g. fine_art, manuscripts, natural_history') }}</small></label>
        <input type="text" name="specialisations" class="form-control" value="{{ old('specialisations', $spec) }}">
      </div>
      <div class="col-md-3">
        <label class="form-label">{{ __('Status') }}</label>
        <select name="active" class="form-select">
          <option value="1" {{ old('active', $item->active ?? 1) ? 'selected' : '' }}>{{ __('Active') }}</option>
          <option value="0" {{ ! old('active', $item->active ?? 1) ? 'selected' : '' }}>{{ __('Inactive') }}</option>
        </select>
      </div>
      <div class="col-md-12">
        <label class="form-label">{{ __('Notes') }}</label>
        <textarea name="notes" class="form-control" rows="3">{{ old('notes', $item->notes ?? '') }}</textarea>
      </div>
    </div>
  </div>
</div>

<div class="d-flex gap-2">
  <button type="submit" class="btn atom-btn-white"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
  <a href="{{ route('heritage.valuer.index') }}" class="btn atom-btn-white">{{ __('Cancel') }}</a>
</div>
