{{-- Partial: Render custom fields in edit forms --}}
@if(isset($customFields) && $customFields->count())
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-input-cursor-text me-2"></i>Custom Fields</h6>
        </div>
        <div class="card-body">
            @foreach($customFields as $field)
                <div class="mb-3">
                    <label for="cf_{{ $field->machine_name }}" class="form-label">
                        {{ $field->field_label }}
                        @if($field->is_required ?? false)
                            <span class="text-danger">*</span>
                        @endif
                    </label>

                    @if($field->field_type === 'textarea')
                        <textarea class="form-control" id="cf_{{ $field->machine_name }}"
                                  name="custom_fields[{{ $field->id }}]" rows="3"
                                  {{ ($field->is_required ?? false) ? 'required' : '' }}>{{ $field->value ?? $field->default_value ?? '' }}</textarea>
                    @elseif($field->field_type === 'select')
                        <select class="form-select" id="cf_{{ $field->machine_name }}"
                                name="custom_fields[{{ $field->id }}]"
                                {{ ($field->is_required ?? false) ? 'required' : '' }}>
                            <option value="">-- Select --</option>
                            @foreach(explode("\n", $field->options ?? '') as $opt)
                                <option value="{{ trim($opt) }}" {{ ($field->value ?? '') === trim($opt) ? 'selected' : '' }}>{{ trim($opt) }}</option>
                            @endforeach
                        </select>
                    @elseif($field->field_type === 'boolean')
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="cf_{{ $field->machine_name }}"
                                   name="custom_fields[{{ $field->id }}]" value="1"
                                   {{ ($field->value ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="cf_{{ $field->machine_name }}">{{ $field->field_label }}</label>
                        </div>
                    @elseif($field->field_type === 'date')
                        <input type="date" class="form-control" id="cf_{{ $field->machine_name }}"
                               name="custom_fields[{{ $field->id }}]"
                               value="{{ $field->value ?? $field->default_value ?? '' }}"
                               {{ ($field->is_required ?? false) ? 'required' : '' }}>
                    @elseif($field->field_type === 'number')
                        <input type="number" class="form-control" id="cf_{{ $field->machine_name }}"
                               name="custom_fields[{{ $field->id }}]"
                               value="{{ $field->value ?? $field->default_value ?? '' }}"
                               {{ ($field->is_required ?? false) ? 'required' : '' }}>
                    @else
                        <input type="text" class="form-control" id="cf_{{ $field->machine_name }}"
                               name="custom_fields[{{ $field->id }}]"
                               value="{{ $field->value ?? $field->default_value ?? '' }}"
                               {{ ($field->is_required ?? false) ? 'required' : '' }}>
                    @endif

                    @if($field->help_text ?? false)
                        <div class="form-text">{{ $field->help_text }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
