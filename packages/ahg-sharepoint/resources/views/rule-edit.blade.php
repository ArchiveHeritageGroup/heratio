@extends('theme::layouts.1col')

@section('title', $rule ? 'Edit SharePoint rule' : 'New SharePoint rule')

@section('content')
@php
    $flags = $rule && $rule->process_flags ? (json_decode($rule->process_flags, true) ?: []) : [];
    $templatesJson = [];
    foreach ($templatesByDrive as $dId => $list) {
        $templatesJson[(int) $dId] = $list->map(function ($t) {
            return ['id' => (int) $t->id, 'name' => $t->name, 'sector' => $t->sector, 'standard' => $t->standard, 'is_default' => (int) $t->is_default];
        })->values()->all();
    }
@endphp

<h1>{{ $rule ? __('Edit rule') : __('New rule') }}</h1>

<form method="post" action="{{ route('sharepoint.rule.save') }}">
    @csrf
    @if($rule)
        <input type="hidden" name="id" value="{{ (int) $rule->id }}">
    @endif

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">{{ __('Source') }}</h5></div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">{{ __('Name') }}</label>
                <input type="text" name="name" class="form-control" required value="{{ $rule->name ?? '' }}">
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Drive') }}</label>
                <select name="drive_id" class="form-select" required>
                    <option value="">— {{ __('Select drive') }} —</option>
                    @foreach($drives as $d)
                        <option value="{{ (int) $d->id }}" @selected($rule && (int) $rule->drive_id === (int) $d->id)>
                            {{ $d->site_title ?: '?' }} / {{ $d->drive_name ?: '?' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">{{ __('Folder path (optional)') }}</label>
                    <input type="text" name="folder_path" class="form-control" placeholder="/Shared Documents/Archive" value="{{ $rule->folder_path ?? '' }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">{{ __('File pattern (CSV of globs)') }}</label>
                    <input type="text" name="file_pattern" class="form-control" placeholder="*.pdf,*.tif" value="{{ $rule->file_pattern ?? '' }}">
                </div>
            </div>
            <div class="mb-3">
                @php $hasLabel = !empty($rule->retention_label ?? ''); @endphp
                <label class="form-label d-block">{{ __('Purview retention/disposal label gating') }}</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="retention_mode" id="retention_mode_off" value="off" @checked(!$hasLabel)>
                    <label class="form-check-label" for="retention_mode_off">
                        {{ __('Ingest all matching files') }}
                        <small class="text-muted ms-1">({{ __('no Purview filter — use this for demos or tenants without Purview') }})</small>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="retention_mode" id="retention_mode_on" value="on" @checked($hasLabel)>
                    <label class="form-check-label" for="retention_mode_on">
                        {{ __('Only items carrying specific Purview retention label(s)') }}
                    </label>
                </div>
                <input type="text" name="retention_label" id="retention_label" class="form-control mt-2"
                       placeholder="{{ __('e.g. Ready for Archive, Permanent') }}"
                       value="{{ $rule->retention_label ?? '' }}"
                       @disabled(!$hasLabel)>
                <small class="text-muted">
                    {{ __('Comma-separated label names exactly as defined in Microsoft Purview.') }}
                </small>
            </div>
            <div class="mb-3">
                <label class="form-label" for="template_id">{{ __('Mapping template') }}</label>
                <select name="template_id" id="template_id" class="form-select">
                    <option value="">— {{ __('Use drive default') }} —</option>
                </select>
                <small class="text-muted">{{ __('Templates are managed at') }}
                    <a href="{{ route('sharepoint.mappings') }}">{{ __('SharePoint → Mappings') }}</a>.
                </small>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">{{ __('Target') }}</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">{{ __('Sector') }}</label>
                    <select name="sector" class="form-select">
                        @foreach(['archive','library','museum','gallery','dam'] as $s)
                            <option value="{{ $s }}" @selected($rule && $rule->sector === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">{{ __('Standard') }}</label>
                    <select name="standard" class="form-select">
                        @foreach(['isadg','dacs','dc','mods','rad'] as $s)
                            <option value="{{ $s }}" @selected($rule && $rule->standard === $s)>{{ strtoupper($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">{{ __('Repository ID (optional)') }}</label>
                    <input type="number" name="repository_id" class="form-control" value="{{ $rule->repository_id ?? '' }}">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">{{ __('Parent placement') }}</label>
                    <select name="parent_placement" class="form-select">
                        @foreach(['top_level','existing','new'] as $s)
                            <option value="{{ $s }}" @selected($rule && $rule->parent_placement === $s)>{{ str_replace('_',' ', ucfirst($s)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="parent_id">{{ __('Parent (when existing)') }}</label>
                    <select name="parent_id" id="parent_id" class="form-select tom-select-parent">
                        @if(!empty($parentLabel))
                            <option value="{{ (int) $parentLabel->id }}" selected
                                    data-identifier="{{ $parentLabel->identifier ?? '' }}">
                                @php
                                    $bits = [];
                                    if (!empty($parentLabel->identifier)) { $bits[] = $parentLabel->identifier; }
                                    $bits[] = $parentLabel->name ?: ('Record #' . $parentLabel->id);
                                @endphp
                                {{ implode(' — ', $bits) }}
                            </option>
                        @endif
                    </select>
                    <small class="text-muted">{{ __('Only used when Parent placement is set to "existing".') }}</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">{{ __('AI processing') }}</h5></div>
        <div class="card-body row">
            @foreach(['virus_scan'=>'Virus scan','ocr'=>'OCR','ner'=>'NER','summarize'=>'Summarize','spellcheck'=>'Spellcheck','translate'=>'Translate','format_id'=>'Format ID','face_detect'=>'Face detect'] as $key => $label)
            <div class="col-md-3 mb-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="process_{{ $key }}" id="proc_{{ $key }}" value="1" @checked(!empty($flags[$key]))>
                    <label class="form-check-label" for="proc_{{ $key }}">{{ __($label) }}</label>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">{{ __('Schedule') }}</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">{{ __('Cron expression') }}</label>
                    <input type="text" name="schedule_cron" class="form-control" value="{{ $rule->schedule_cron ?? '*/15 * * * *' }}">
                    <small class="text-muted">{{ __('Standard cron syntax. The cron daemon must invoke artisan sharepoint:auto-ingest periodically.') }}</small>
                </div>
                <div class="col-md-6 mb-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="is_enabled" @checked(!$rule || $rule->is_enabled)>
                        <label class="form-check-label" for="is_enabled">{{ __('Enabled') }}</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('sharepoint.rules') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary">{{ __('Save rule') }}</button>
    </div>
</form>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var templatesByDrive = @json($templatesJson);
    var initialTemplateId = {{ $rule && !empty($rule->template_id) ? (int) $rule->template_id : 0 }};
    var driveSelEl = document.querySelector('select[name="drive_id"]');
    var templateSelEl = document.getElementById('template_id');
    function populateTemplates() {
        if (!driveSelEl || !templateSelEl) return;
        var driveId = parseInt(driveSelEl.value, 10) || 0;
        var prev = parseInt(templateSelEl.value, 10) || initialTemplateId || 0;
        while (templateSelEl.options.length > 1) templateSelEl.remove(1);
        var list = templatesByDrive[driveId] || [];
        list.forEach(function (t) {
            var o = document.createElement('option');
            o.value = String(t.id);
            o.textContent = t.name + (t.is_default ? ' ★' : '') + ' (' + t.sector + '/' + t.standard + ')';
            if (prev && t.id === prev) o.selected = true;
            templateSelEl.appendChild(o);
        });
    }
    if (driveSelEl) driveSelEl.addEventListener('change', populateTemplates);
    populateTemplates();

    var parentEl = document.getElementById('parent_id');
    if (!parentEl || typeof TomSelect === 'undefined') return;
    var parentSearchUrl = @json(route('informationobject.autocomplete'));
    var parentTs = new TomSelect(parentEl, {
        plugins: ['clear_button'],
        valueField: 'id',
        labelField: 'name',
        searchField: ['name', 'identifier', 'slug'],
        preload: false,
        maxOptions: 50,
        load: function (query, callback) {
            if (!query || query.length < 2) { callback(); return; }
            fetch(parentSearchUrl + '?query=' + encodeURIComponent(query), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var items = Array.isArray(data) ? data : (data && data.data ? data.data : []);
                callback(items);
            })
            .catch(function () { callback(); });
        },
        render: {
            option: function (data, escape) {
                var ident = data.identifier ? '<code class="me-2">' + escape(data.identifier) + '</code>' : '';
                return '<div>' + ident + escape(data.name || data.title || ('Record #' + data.id)) + '</div>';
            },
            item: function (data, escape) {
                var ident = data.identifier ? '<code class="me-2">' + escape(data.identifier) + '</code>' : '';
                return '<div>' + ident + escape(data.name || data.title || data.text || ('Record #' + data.id)) + '</div>';
            },
        },
    });

    var retentionRadios = document.querySelectorAll('input[name="retention_mode"]');
    var retentionInput = document.getElementById('retention_label');
    function syncRetention() {
        var on = document.getElementById('retention_mode_on').checked;
        if (retentionInput) {
            retentionInput.disabled = !on;
            if (!on) retentionInput.value = '';
        }
    }
    retentionRadios.forEach(function (r) { r.addEventListener('change', syncRetention); });
    syncRetention();

    var placementSel = document.querySelector('select[name="parent_placement"]');
    function syncParentEnabled() {
        if (!placementSel) return;
        var enabled = placementSel.value === 'existing';
        var wrapper = parentEl.closest('.col-md-6');
        if (enabled) { parentTs.enable(); } else { parentTs.clear(true); parentTs.disable(); }
        if (wrapper) { wrapper.style.opacity = enabled ? '1' : '0.5'; }
    }
    placementSel.addEventListener('change', syncParentEnabled);
    syncParentEnabled();
});
</script>
@endsection
