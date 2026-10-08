@php
    $def = $definition ?? null;
    $isEdit = !empty($def);
    $val = fn (string $col, $fallback = '') => old($col, $def->{$col} ?? $fallback);
    $flag = fn (string $col, bool $fallback) => (bool) old($col, $isEdit ? ($def->{$col} ?? $fallback) : $fallback);
@endphp

<form id="cf-definition-form" method="post" action="{{ route('customFields.save') }}" autocomplete="off">
    @csrf
    @if($isEdit)
        <input type="hidden" name="id" value="{{ $def->id }}">
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <div class="mb-3">
                <label for="cf-field-label" class="form-label">{{ __('Field Label') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                <input type="text" class="form-control @error('field_label') is-invalid @enderror" id="cf-field-label" name="field_label"
                       value="{{ $val('field_label') }}" required maxlength="255">
            </div>

            <div class="mb-3">
                <label for="cf-field-key" class="form-label">{{ __('Field key') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" class="form-control @error('field_key') is-invalid @enderror" id="cf-field-key" name="field_key"
                       value="{{ $val('field_key') }}" maxlength="100"
                       pattern="[a-z0-9_]+" title="{{ __('Lowercase letters, numbers, and underscores only') }}">
                <div class="form-text">{{ __('Used in exports (EAD, CSV). Lowercase letters, numbers, underscores. Left blank, it is made from the label.') }}</div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="cf-entity-type" class="form-label">{{ __('Entity Type') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                    <select class="form-select @error('entity_type') is-invalid @enderror" id="cf-entity-type" name="entity_type" required>
                        <option value="">{{ __('-- Select --') }}</option>
                        @foreach($entityTypes as $key => $label)
                            <option value="{{ $key }}" @selected(\AhgCustomFields\Services\CustomFieldService::normaliseEntity((string) $val('entity_type')) === $key)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="cf-field-type" class="form-label">{{ __('Field Type') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                    <select class="form-select @error('field_type') is-invalid @enderror" id="cf-field-type" name="field_type" required>
                        <option value="">{{ __('-- Select --') }}</option>
                        @foreach($fieldTypes as $key => $label)
                            <option value="{{ $key }}" @selected($val('field_type') === $key)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="cf-dropdown-taxonomy" class="form-label">{{ __('Dropdown list') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <select class="form-select @error('dropdown_taxonomy') is-invalid @enderror" id="cf-dropdown-taxonomy" name="dropdown_taxonomy">
                    <option value="">{{ __('-- None --') }}</option>
                    @foreach($dropdownTaxonomies as $tax)
                        <option value="{{ $tax->taxonomy }}" @selected($val('dropdown_taxonomy') === $tax->taxonomy)>{{ $tax->taxonomy_label ?: $tax->taxonomy }}</option>
                    @endforeach
                </select>
                <div class="form-text">{{ __('For dropdown and multi-select fields: the Dropdown Manager list the values come from.') }}</div>
            </div>

            <div class="mb-3">
                <label for="cf-help-text" class="form-label">{{ __('Help Text') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" class="form-control" id="cf-help-text" name="help_text" value="{{ $val('help_text') }}" maxlength="500">
            </div>

            <div class="mb-3">
                <label for="cf-default-value" class="form-label">{{ __('Default Value') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" class="form-control" id="cf-default-value" name="default_value" value="{{ $val('default_value') }}" maxlength="500">
                <div class="form-text">{{ __('Pre-filled on new records.') }}</div>
            </div>

            <div class="mb-3">
                <label for="cf-validation-rule" class="form-label">{{ __('Validation rule') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" class="form-control @error('validation_rule') is-invalid @enderror" id="cf-validation-rule" name="validation_rule" value="{{ $val('validation_rule') }}" maxlength="255" placeholder="max:255">
                <div class="form-text">{{ __('max:N for a length limit, or regex:/pattern/ for a format.') }}</div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">{{ __('Settings') }}</h6>
                </div>
                <div class="card-body">
                    @foreach([
                        'is_required' => [__('Required'), false],
                        'is_active' => [__('Active'), true],
                        'is_repeatable' => [__('Repeatable'), false],
                        'is_visible_edit' => [__('Shown on the edit form'), true],
                        'is_visible_public' => [__('Visible to the public'), true],
                        'include_in_export' => [__('Included in finding aids and exports'), true],
                        'is_searchable' => [__('Searchable'), false],
                    ] as $col => [$label, $default])
                        <div class="form-check mb-2">
                            <input type="hidden" name="{{ $col }}" value="0">
                            <input class="form-check-input" type="checkbox" id="cf-{{ $col }}" name="{{ $col }}" value="1" @checked($flag($col, $default))>
                            <label class="form-check-label" for="cf-{{ $col }}">{{ $label }}</label>
                        </div>
                    @endforeach

                    <div class="mb-3 mt-3">
                        <label for="cf-sort-order" class="form-label">{{ __('Sort Order') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                        <input type="number" class="form-control" id="cf-sort-order" name="sort_order" value="{{ $val('sort_order', 0) }}" min="0">
                    </div>

                    <div class="mb-3">
                        <label for="cf-field-group" class="form-label">{{ __('Field Group') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                        <input type="text" class="form-control" id="cf-field-group" name="field_group" value="{{ $val('field_group') }}" maxlength="100">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mt-3">
        <a href="{{ route('customFields.index') }}" class="atom-btn-white">{{ __('Cancel') }}</a>
        <button type="submit" class="atom-btn-white">
            <i class="bi bi-check-lg me-1"></i>{{ $isEdit ? __('Update Field') : __('Create Field') }}
        </button>
    </div>
</form>
