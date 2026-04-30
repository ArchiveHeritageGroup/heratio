{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Form Builder')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('forms.index') }}">Form Templates</a></li>
                    <li class="breadcrumb-item active">Form Builder</li>
                </ol>
            </nav>
            <h1><i class="fas fa-edit me-2"></i>Form Builder: {{ $template->name ?? '' }}</h1>
            <p class="text-muted">{{ $template->description ?? '' }}</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('forms.preview', ['id' => $template->id ?? 0]) }}" class="btn btn-outline-info">
                <i class="fas fa-eye me-1"></i> {{ __('Preview') }}
            </a>
        </div>
    </div>

    <div class="row">
        {{-- Field Palette --}}
        <div class="col-md-3">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-toolbox me-2"></i>Field Types</h5>
                </div>
                <div class="card-body p-2">
                    <div class="field-palette">
                        @foreach(($fieldTypes ?? []) as $typeKey => $typeLabel)
                            <div class="palette-item" draggable="true" data-field-type="{{ $typeKey }}">
                                <i class="fas fa-font me-2"></i> {{ $typeLabel }}
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-database me-2"></i>Archival Fields</h5>
                </div>
                <div class="card-body p-2">
                    <div class="field-palette">
                        <div class="palette-item atom-field" draggable="true" data-field-type="text" data-atom-field="title">
                            <i class="fas fa-heading me-2"></i> Title
                        </div>
                        <div class="palette-item atom-field" draggable="true" data-field-type="text" data-atom-field="identifier">
                            <i class="fas fa-barcode me-2"></i> Identifier
                        </div>
                        <div class="palette-item atom-field" draggable="true" data-field-type="select" data-atom-field="level_of_description">
                            <i class="fas fa-layer-group me-2"></i> Level
                        </div>
                        <div class="palette-item atom-field" draggable="true" data-field-type="textarea" data-atom-field="scope_and_content">
                            <i class="fas fa-align-left me-2"></i> Scope & Content
                        </div>
                        <div class="palette-item atom-field" draggable="true" data-field-type="textarea" data-atom-field="extent_and_medium">
                            <i class="fas fa-ruler me-2"></i> Extent
                        </div>
                        <div class="palette-item atom-field" draggable="true" data-field-type="daterange" data-atom-field="dates">
                            <i class="fas fa-calendar me-2"></i> Dates
                        </div>
                        <div class="palette-item atom-field" draggable="true" data-field-type="autocomplete" data-atom-field="creators">
                            <i class="fas fa-user-edit me-2"></i> Creators
                        </div>
                        <div class="palette-item atom-field" draggable="true" data-field-type="autocomplete" data-atom-field="subjects">
                            <i class="fas fa-tags me-2"></i> Subjects
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Canvas --}}
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-th-list me-2"></i>Form Layout</h5>
                    <span class="badge bg-info">{{ count($fields ?? []) }} fields</span>
                </div>
                <div class="card-body">
                    <div id="form-canvas" class="form-canvas" data-template-id="{{ $template->id ?? 0 }}">
                        @if(empty($fields) || count($fields) === 0)
                            <div class="empty-canvas text-center text-muted py-5">
                                <i class="fas fa-arrows-alt fa-3x mb-3"></i>
                                <p>Drag fields here to build your form</p>
                            </div>
                        @else
                            @foreach($fields as $field)
                                <div class="field-item" data-field-id="{{ $field->id }}" data-sort="{{ $field->sort_order }}">
                                    <div class="field-handle"><i class="fas fa-grip-vertical"></i></div>
                                    <div class="field-content">
                                        <div class="field-label">
                                            {{ $field->label }}
                                            @if(!empty($field->is_required))
                                                <span class="text-danger">*</span>
                                            @endif
                                        </div>
                                        <div class="field-meta">
                                            <span class="badge bg-secondary">{{ $field->field_type }}</span>
                                            @if(!empty($field->atom_field))
                                                <span class="badge bg-info">{{ $field->atom_field }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="field-actions">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-edit-field" data-field-id="{{ $field->id }}">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-field" data-field-id="{{ $field->id }}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Field Properties --}}
        <div class="col-md-3">
            <div class="card" id="field-properties-panel">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Field Properties</h5>
                </div>
                <div class="card-body">
                    <div id="no-field-selected" class="text-center text-muted py-4">
                        <i class="fas fa-mouse-pointer fa-2x mb-2"></i>
                        <p>Select a field to edit its properties</p>
                    </div>
                    <form id="field-properties-form" style="display: none;">
                        @csrf
                        <input type="hidden" id="prop-field-id" name="field_id">

                        <div class="mb-3">
                            <label for="prop-label" class="form-label">{{ __('Label') }}</label>
                            <input type="text" class="form-control" id="prop-label" name="label">
                        </div>

                        <div class="mb-3">
                            <label for="prop-name" class="form-label">{{ __('Field Name') }}</label>
                            <input type="text" class="form-control" id="prop-name" name="field_name">
                            <div class="form-text">Internal field identifier</div>
                        </div>

                        <div class="mb-3">
                            <label for="prop-help" class="form-label">{{ __('Help Text') }}</label>
                            <textarea class="form-control" id="prop-help" name="help_text" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="prop-placeholder" class="form-label">{{ __('Placeholder') }}</label>
                            <input type="text" class="form-control" id="prop-placeholder" name="placeholder">
                        </div>

                        <div class="mb-3">
                            <label for="prop-default" class="form-label">{{ __('Default Value') }}</label>
                            <input type="text" class="form-control" id="prop-default" name="default_value">
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="prop-required" name="is_required">
                            <label class="form-check-label" for="prop-required">{{ __('Required') }}</label>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="prop-readonly" name="is_readonly">
                            <label class="form-check-label" for="prop-readonly">{{ __('Read Only') }}</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-1"></i> {{ __('Save Field') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.field-palette { display: flex; flex-direction: column; gap: 4px; }
.palette-item {
    padding: 8px 12px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    cursor: grab;
    transition: all 0.2s;
}
.palette-item:hover { background: #e9ecef; border-color: #adb5bd; }
.palette-item.atom-field { background: #e7f1ff; border-color: #b6d4fe; }
.form-canvas {
    min-height: 400px;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 16px;
}
.form-canvas.drag-over { border-color: #0d6efd; background-color: #f0f7ff; }
.field-item {
    display: flex;
    align-items: center;
    padding: 12px;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    margin-bottom: 8px;
    transition: all 0.2s;
}
.field-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.field-item.selected { border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25); }
.field-item.dragging { opacity: 0.5; }
.field-handle { cursor: grab; padding: 0 12px 0 4px; color: #adb5bd; }
.field-content { flex: 1; }
.field-label { font-weight: 500; }
.field-meta { margin-top: 4px; }
.field-actions { display: flex; gap: 4px; }
.empty-canvas { color: #adb5bd; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var canvas = document.getElementById('form-canvas');
    if (!canvas) return;
    var templateId = canvas.dataset.templateId;
    var selectedField = null;
    var csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';

    document.querySelectorAll('.palette-item').forEach(function(item) {
        item.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/plain', JSON.stringify({
                type: 'new',
                fieldType: this.dataset.fieldType,
                atomField: this.dataset.atomField || null
            }));
        });
    });

    canvas.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('drag-over');
    });

    canvas.addEventListener('dragleave', function(e) {
        this.classList.remove('drag-over');
    });

    canvas.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');

        var data = JSON.parse(e.dataTransfer.getData('text/plain'));
        if (data.type === 'new') {
            addNewField(data.fieldType, data.atomField);
        }
    });

    function addNewField(fieldType, atomField) {
        var label = atomField ? atomField.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); }) : 'New ' + fieldType + ' field';

        fetch('{{ route('forms.field.add') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: 'template_id=' + templateId + '&field_type=' + fieldType + '&label=' + encodeURIComponent(label) + '&atom_field=' + (atomField || '')
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert('Error adding field');
            }
        });
    }

    document.querySelectorAll('.field-item').forEach(function(item) {
        item.addEventListener('click', function(e) {
            if (e.target.closest('.field-actions')) return;
            document.querySelectorAll('.field-item').forEach(function(f) { f.classList.remove('selected'); });
            this.classList.add('selected');
            selectedField = this.dataset.fieldId;
        });
    });

    document.querySelectorAll('.btn-delete-field').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('Delete this field?')) return;
            var fieldId = this.dataset.fieldId;
            fetch('{{ route('forms.field.delete') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: 'field_id=' + fieldId
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) { location.reload(); }
            });
        });
    });

    var propsForm = document.getElementById('field-properties-form');
    if (propsForm) {
        propsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            var params = new URLSearchParams(formData).toString();

            fetch('{{ route('forms.field.update') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: params
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) { location.reload(); } else { alert('Error saving field'); }
            });
        });
    }
});
</script>
@endsection
