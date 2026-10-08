{{--
  Custom fields on an edit form (heratio#1530). Include inside the record's
  <form>; the values are saved with the record by
  CustomFieldService::saveFromRequest(). Variables: $objectId (null on create),
  optional $entityType (default informationobject).
--}}
@php
  $cfService = app(\AhgCustomFields\Services\CustomFieldService::class);
  $cfFields = $cfService->fieldsWithValues($entityType ?? \AhgCustomFields\Services\CustomFieldService::IO, $objectId ?? null)
      ->where('is_visible_edit', 1);
@endphp
@if($cfFields->isNotEmpty())
  <input type="hidden" name="{{ \AhgCustomFields\Services\CustomFieldService::FORM_MARKER }}" value="1">
  <div class="accordion mb-3" id="customFieldsAccordion">
    <div class="accordion-item">
      <h2 class="accordion-header" id="custom-fields-heading">
        <button class="accordion-button {{ $errors->has('cf.*') ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#custom-fields-collapse" aria-expanded="{{ $errors->has('cf.*') ? 'true' : 'false' }}" aria-controls="custom-fields-collapse">
          {{ __('Additional fields') }}
        </button>
      </h2>
      <div id="custom-fields-collapse" class="accordion-collapse collapse {{ $errors->has('cf.*') ? 'show' : '' }}" aria-labelledby="custom-fields-heading">
        <div class="accordion-body">
          @foreach($cfFields->groupBy(fn ($f) => $f->field_group ?: '') as $cfGroup => $cfGroupFields)
            @if($cfGroup !== '')
              <h3 class="h6 mt-2 mb-3">{{ $cfGroup }}</h3>
            @endif
            @foreach($cfGroupFields as $field)
              @php
                $cfKey = $field->field_key;
                $cfId = 'cf_' . $cfKey;
                $cfName = 'cf[' . $cfKey . ']';
                $cfDefault = $objectId ?? null ? null : $field->default_value;
                $cfValue = old('cf.' . $cfKey, $field->value ?? $cfDefault);
                $cfOptions = in_array($field->field_type, ['dropdown', 'multiselect'], true) ? $cfService->getDropdownOptions($field->dropdown_taxonomy) : [];
                $cfInputType = ['date' => 'date', 'number' => 'number', 'url' => 'url'][$field->field_type] ?? 'text';
              @endphp
              <div class="mb-3">
                @if($field->field_type === 'boolean')
                  <div class="form-check">
                    <input class="form-check-input @error('cf.' . $cfKey) is-invalid @enderror" type="checkbox" id="{{ $cfId }}" name="{{ $cfName }}" value="1" @checked((bool) $cfValue)>
                    <label class="form-check-label" for="{{ $cfId }}">{{ $field->field_label }}</label>
                  </div>
                @else
                  <label for="{{ $cfId }}" class="form-label">
                    {{ $field->field_label }}
                    @if($field->is_required)
                      <span class="badge bg-danger ms-1">{{ __('Required') }}</span>
                    @else
                      <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span>
                    @endif
                  </label>
                  @if($field->field_type === 'multiselect')
                    <select class="form-select @error('cf.' . $cfKey) is-invalid @enderror" id="{{ $cfId }}" name="{{ $cfName }}[]" multiple>
                      @foreach($cfOptions as $code => $label)
                        <option value="{{ $code }}" @selected(in_array($code, (array) $cfValue, true))>{{ $label }}</option>
                      @endforeach
                    </select>
                  @elseif($field->field_type === 'dropdown')
                    <select class="form-select @error('cf.' . $cfKey) is-invalid @enderror" id="{{ $cfId }}" name="{{ $cfName }}">
                      <option value="">{{ __('-- Select --') }}</option>
                      @foreach($cfOptions as $code => $label)
                        <option value="{{ $code }}" @selected((string) $cfValue === (string) $code)>{{ $label }}</option>
                      @endforeach
                    </select>
                  @elseif($field->is_repeatable)
                    {{-- ponytail: one input per stored value plus one blank; add more by saving again. Upgrade: an "add another" button. --}}
                    @foreach(array_merge(array_values(array_filter((array) $cfValue, fn ($v) => $v !== null && $v !== '')), ['']) as $i => $cfOne)
                      @if($field->field_type === 'textarea')
                        <textarea class="form-control mb-1 @error('cf.' . $cfKey) is-invalid @enderror" id="{{ $cfId }}{{ $i ? '_' . $i : '' }}" name="{{ $cfName }}[]" rows="2">{{ $cfOne }}</textarea>
                      @else
                        <input type="{{ $cfInputType }}" class="form-control mb-1 @error('cf.' . $cfKey) is-invalid @enderror" id="{{ $cfId }}{{ $i ? '_' . $i : '' }}" name="{{ $cfName }}[]" value="{{ $cfOne }}" @if($cfInputType === 'number') step="any" @endif>
                      @endif
                    @endforeach
                  @elseif($field->field_type === 'textarea')
                    <textarea class="form-control @error('cf.' . $cfKey) is-invalid @enderror" id="{{ $cfId }}" name="{{ $cfName }}" rows="3">{{ $cfValue }}</textarea>
                  @else
                    <input type="{{ $cfInputType }}" class="form-control @error('cf.' . $cfKey) is-invalid @enderror" id="{{ $cfId }}" name="{{ $cfName }}" value="{{ $cfValue }}" @if($cfInputType === 'number') step="any" @endif>
                  @endif
                @endif
                @error('cf.' . $cfKey)
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                @if($field->help_text)
                  <div class="form-text">{{ $field->help_text }}</div>
                @endif
              </div>
            @endforeach
          @endforeach
        </div>
      </div>
    </div>
  </div>
@endif
