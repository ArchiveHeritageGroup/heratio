@php
    $def = $definition ?? null;
    $isEdit = !empty($def);
@endphp

<form id="cf-definition-form" method="post" action="{{ route('customFields.save') }}">
    @csrf
    @if($isEdit)
        <input type="hidden" name="id" value="{{ $def->id }}">
    @endif

    <div class="row">
        <div class="col-md-8">
            <div class="mb-3">
                <label for="cf-field-label" class="form-label">Field Label <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                <input type="text" class="form-control" id="cf-field-label" name="field_label"
                       value="{{ $def->field_label ?? old('field_label', '') }}" required>
            </div>

            <div class="mb-3">
                <label for="cf-machine-name" class="form-label">Machine Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                <input type="text" class="form-control" id="cf-machine-name" name="machine_name"
                       value="{{ $def->machine_name ?? old('machine_name', '') }}" required
                       pattern="[a-z0-9_]+" title="Lowercase letters, numbers, and underscores only">
                <div class="form-text">Used internally. Lowercase letters, numbers, underscores only.</div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="cf-entity-type" class="form-label">Entity Type <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                    <select class="form-select" id="cf-entity-type" name="entity_type" required>
                        <option value="">-- Select --</option>
                        @foreach($entityTypes as $key => $label)
                            <option value="{{ $key }}" {{ ($def->entity_type ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="cf-field-type" class="form-label">Field Type <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                    <select class="form-select" id="cf-field-type" name="field_type" required>
                        <option value="">-- Select --</option>
                        @foreach($fieldTypes as $key => $label)
                            <option value="{{ $key }}" {{ ($def->field_type ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="cf-options" class="form-label">Options (for dropdown/multi-select) <span class="badge bg-secondary ms-1">Optional</span></label>
                <textarea class="form-control" id="cf-options" name="options" rows="3"
                          placeholder="One option per line">{{ $def->options ?? old('options', '') }}</textarea>
                <div class="form-text">One option per line. Only used for dropdown and multi-select field types.</div>
            </div>

            <div class="mb-3">
                <label for="cf-help-text" class="form-label">Help Text <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" id="cf-help-text" name="help_text"
                       value="{{ $def->help_text ?? old('help_text', '') }}">
            </div>

            <div class="mb-3">
                <label for="cf-default-value" class="form-label">Default Value <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" id="cf-default-value" name="default_value"
                       value="{{ $def->default_value ?? old('default_value', '') }}">
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Settings</h6>
                </div>
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="cf-required" name="is_required" value="1"
                               {{ ($def->is_required ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="cf-required">Required <span class="badge bg-secondary ms-1">Optional</span></label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="cf-active" name="is_active" value="1"
                               {{ ($def->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="cf-active">Active <span class="badge bg-secondary ms-1">Optional</span></label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="cf-searchable" name="is_searchable" value="1"
                               {{ ($def->is_searchable ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="cf-searchable">Searchable <span class="badge bg-secondary ms-1">Optional</span></label>
                    </div>

                    <div class="mb-3">
                        <label for="cf-sort-order" class="form-label">Sort Order <span class="badge bg-secondary ms-1">Optional</span></label>
                        <input type="number" class="form-control" id="cf-sort-order" name="sort_order"
                               value="{{ $def->sort_order ?? old('sort_order', 0) }}" min="0">
                    </div>

                    <div class="mb-3">
                        <label for="cf-field-group" class="form-label">Field Group <span class="badge bg-secondary ms-1">Optional</span></label>
                        <input type="text" class="form-control" id="cf-field-group" name="field_group"
                               value="{{ $def->field_group ?? old('field_group', '') }}">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <hr>
    <div class="d-flex justify-content-between">
        <a href="{{ route('customFields.index') }}" class="atom-btn-white">Cancel</a>
        <button type="submit" class="atom-btn-white">
            <i class="bi bi-check-lg me-1"></i>{{ $isEdit ? 'Update Field' : 'Create Field' }}
        </button>
    </div>
</form>
