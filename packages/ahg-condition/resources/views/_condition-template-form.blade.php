{{--
  Condition Assessment Template selector + form container.

  Vars:
    $conditionCheckId    (?int)    — current check ID (null = new)
    $selectedTemplateId  (?int)    — currently selected template ID
    $materialType        (?string) — object material type, used to pick a default
    $canEdit             (bool)    — disable controls when false

  Templates load directly from spectrum_condition_template (no service
  abstraction needed — the table is small and the view-side reads are
  trivial). The form fragment for the chosen template is fetched via AJAX
  from /condition/template/{id}/form (controller endpoint to be wired
  separately if not already).

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $canEdit = $canEdit ?? true;
    $conditionCheckId = $conditionCheckId ?? null;
    $selectedTemplateId = $selectedTemplateId ?? null;
    $materialType = $materialType ?? null;

    $templates = Schema::hasTable('spectrum_condition_template')
        ? DB::table('spectrum_condition_template')->where('is_active', 1)->orderBy('material_type')->orderBy('name')->get()
        : collect();
    $templatesByMaterial = $templates->groupBy('material_type');

    $selected = null;
    if ($selectedTemplateId) {
        $selected = $templates->firstWhere('id', (int) $selectedTemplateId);
    } elseif ($materialType) {
        $selected = $templates->where('material_type', $materialType)->where('is_default', 1)->first()
            ?? $templates->where('material_type', $materialType)->first();
    }

    $nonce = csp_nonce() ?? '';
@endphp

<div class="condition-template-container" id="conditionTemplateContainer">
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">
                <i class="fas fa-clipboard-list me-2"></i>{{ __('Condition Assessment Template') }}
            </h5>
        </div>
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <label class="form-label" for="templateSelector">
                        {{ __('Select Assessment Template') }}
                    </label>
                    <select class="form-select" id="templateSelector" name="template_id" @disabled(! $canEdit)>
                        <option value="">{{ __('-- Select Template --') }}</option>
                        @foreach ($templatesByMaterial as $matType => $matTemplates)
                            <optgroup label="{{ ucfirst(str_replace('_', ' ', $matType)) }}">
                                @foreach ($matTemplates as $t)
                                    <option value="{{ $t->id }}" data-material="{{ $t->material_type }}"
                                            @selected($selected && $selected->id == $t->id)>{{ $t->name }}{!! $t->is_default ? ' &#9733;' : '' !!}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <small class="text-muted">{{ __("Choose a template based on the object's material type") }}</small>
                </div>
                <div class="col-md-6">
                    @if ($selected)
                        <div class="alert alert-info mb-0 py-2">
                            <strong>{{ $selected->name }}</strong><br>
                            <small>{{ $selected->description }}</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div id="templateFormContainer">
        @if ($selected)
            {{-- The template form fragment is rendered server-side once it's wired;
                 the JS below also handles re-fetching when the user changes the dropdown. --}}
            <div class="alert alert-info">
                <i class="fas fa-spinner fa-spin me-2"></i>{{ __('Loading form…') }}
            </div>
        @else
            <div class="alert alert-secondary">
                <i class="fas fa-info-circle me-2"></i>{{ __('Select a template above to display the assessment form.') }}
            </div>
        @endif
    </div>
</div>

<script @if ($nonce) nonce="{{ $nonce }}" @endif>
document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('templateSelector');
    var box = document.getElementById('templateFormContainer');
    var checkId = {!! json_encode($conditionCheckId) !!};

    function load(templateId) {
        if (!templateId) {
            box.innerHTML = '<div class="alert alert-secondary"><i class="fas fa-info-circle me-2"></i>{{ __('Select a template above to display the assessment form.') }}</div>';
            return;
        }
        box.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><br>{{ __('Loading template...') }}</div>';
        var url = '/condition/template/' + templateId + '/form' + (checkId ? '?check_id=' + checkId : '');
        fetch(url)
            .then(function (r) { return r.text(); })
            .then(function (html) { box.innerHTML = html; })
            .catch(function () { box.innerHTML = '<div class="alert alert-danger">{{ __('Error loading template. Please try again.') }}</div>'; });
    }

    if (sel) {
        sel.addEventListener('change', function () { load(this.value); });
        if (sel.value) load(sel.value);
    }
});
</script>

<style @if ($nonce) nonce="{{ $nonce }}" @endif>
.condition-template-form .condition-section { border-left: 4px solid #28a745; }
.condition-template-form .condition-field { border-bottom: 1px solid #f0f0f0; padding-bottom: 0.75rem; }
.condition-template-form .condition-field:last-child { border-bottom: none; }
.condition-template-form .rating-field .form-check-inline { margin-right: 0.5rem; }
.condition-template-form .rating-field .form-check-input:checked + .form-check-label { font-weight: bold; color: #28a745; }
.condition-template-form .rating-field { background: #f8f9fa; padding: 0.5rem; border-radius: 0.25rem; }
@media print {
    .condition-template-form select,
    .condition-template-form input[type="text"],
    .condition-template-form textarea { border: none !important; background: transparent !important; }
    .condition-template-form select { -webkit-appearance: none; -moz-appearance: none; appearance: none; }
}
</style>
