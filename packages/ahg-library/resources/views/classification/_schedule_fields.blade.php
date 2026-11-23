<div class="row">
  <div class="col-md-3 mb-3">
    <label class="form-label" for="scheme">{{ __('Scheme') }}</label>
    <select class="form-select" id="scheme" name="scheme" required>
      @foreach($schemes as $code => $label)
        <option value="{{ $code }}" @selected(old('scheme', $s->scheme ?? '') === $code)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-4 mb-3">
    <label class="form-label" for="name">{{ __('Name') }}</label>
    <input class="form-control" id="name" name="name" maxlength="255" required value="{{ old('name', $s->name ?? '') }}">
  </div>
  <div class="col-md-2 mb-3">
    <label class="form-label" for="edition">{{ __('Edition') }}</label>
    <input class="form-control" id="edition" name="edition" maxlength="50" value="{{ old('edition', $s->edition ?? '') }}">
  </div>
  <div class="col-md-3 mb-3">
    <label class="form-label" for="validation_mode">{{ __('Validation') }}</label>
    <select class="form-select" id="validation_mode" name="validation_mode" required>
      @foreach($modes as $code => $label)
        <option value="{{ $code }}" @selected(old('validation_mode', $s->validation_mode ?? 'warn') === $code)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
</div>
<div class="mb-3">
  <label class="form-label" for="description">{{ __('Description') }}</label>
  <textarea class="form-control" id="description" name="description" rows="2" maxlength="5000">{{ old('description', $s->description ?? '') }}</textarea>
</div>
<div class="form-check mb-3">
  <input type="hidden" name="is_active" value="0">
  <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $s->is_active ?? 0))>
  <label class="form-check-label" for="is_active">{{ __('Active for this scheme (replaces the currently active schedule)') }}</label>
</div>
