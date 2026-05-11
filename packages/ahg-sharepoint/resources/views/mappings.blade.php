@extends('theme::layouts.1col')

@section('title', 'SharePoint Mapping Templates')

@section('content')
@php
    $standardsBySector = [
        'archive' => [['isadg','ISAD(G)'],['rad','RAD'],['dacs','DACS'],['dc','Dublin Core']],
        'library' => [['mods','MODS'],['dc','Dublin Core'],['isadg','ISAD(G)']],
        'museum'  => [['spectrum','SPECTRUM'],['dc','Dublin Core']],
        'gallery' => [['cco','CCO'],['dc','Dublin Core']],
        'dam'     => [['dc','Dublin Core']],
    ];
    $standardsAllFlat = [];
    foreach ($standardsBySector as $sec => $stds) {
        foreach ($stds as [$code, $label]) {
            $standardsAllFlat[$code] = $standardsAllFlat[$code] ?? ['label' => $label, 'sectors' => []];
            $standardsAllFlat[$code]['sectors'][] = $sec;
        }
    }
    $sectorVal = $selectedTemplate->sector ?? 'archive';
    $standardVal = $selectedTemplate->standard ?? 'isadg';
    $currentTargetFields = $targetFieldsByStandard[$standardVal] ?? ($targetFieldsByStandard['isadg'] ?? []);
    $isadgFields = $targetFieldsByStandard['isadg'] ?? [];
    $targetFieldGroups = [];
    foreach ($targetFieldsByStandard as $code => $all) {
        $extras = ($code === 'isadg') ? [] : array_values(array_diff($all, $isadgFields));
        $core   = ($code === 'isadg') ? array_values($all) : array_values(array_intersect($all, $isadgFields));
        $targetFieldGroups[$code] = ['extras' => $extras, 'core' => $core];
    }
    $standardLabels = [];
    foreach ($standardsAllFlat as $code => $info) { $standardLabels[$code] = $info['label']; }
    $currentGroups = $targetFieldGroups[$standardVal] ?? ($targetFieldGroups['isadg'] ?? ['extras' => [], 'core' => $currentTargetFields]);
@endphp

<h1>{{ __('SharePoint Mapping Templates') }}</h1>

@if(session('notice'))
<div class="alert alert-success alert-dismissible fade show">{{ session('notice') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<p>
    <a href="{{ route('sharepoint.rules') }}" class="btn btn-outline-secondary">{{ __('Rules') }}</a>
</p>

<form method="get" action="{{ route('sharepoint.mappings') }}" class="row g-2 mb-3 align-items-end">
    <div class="col-md-5">
        <label class="form-label">{{ __('Drive') }}</label>
        <select name="drive_id" class="form-select" onchange="this.form.submit()">
            <option value="">— {{ __('Select drive') }} —</option>
            @foreach($drives as $d)
                <option value="{{ (int) $d->id }}" @selected($driveId === (int) $d->id)>{{ $d->site_title ?: '?' }} / {{ $d->drive_name ?: '?' }}</option>
            @endforeach
        </select>
    </div>
    @if($driveId)
    <div class="col-md-5">
        <label class="form-label">{{ __('Template') }}</label>
        <select name="template_id" class="form-select" onchange="this.form.submit()">
            @if($templates->isEmpty())
                <option value="">— {{ __('No templates yet — create one below') }} —</option>
            @endif
            @foreach($templates as $t)
                <option value="{{ (int) $t->id }}" @selected($selectedTemplate && (int) $selectedTemplate->id === (int) $t->id)>
                    {{ $t->name }}{{ $t->is_default ? ' ★' : '' }} ({{ $t->sector }}/{{ $t->standard }})
                </option>
            @endforeach
            <option value="new" @selected(!$selectedTemplate)>— {{ __('+ New template…') }} —</option>
        </select>
    </div>
    @endif
</form>

@if($driveId)
<form method="post" action="{{ route('sharepoint.mappings.save') }}">
    @csrf
    <input type="hidden" name="drive_id" value="{{ $driveId }}">
    <input type="hidden" name="template_id" value="{{ $selectedTemplate ? (int) $selectedTemplate->id : 0 }}">

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">{{ __('Template details') }}</h5></div>
        <div class="card-body row">
            <div class="col-md-4 mb-3">
                <label class="form-label">{{ __('Template name') }} <span class="text-danger">*</span></label>
                <input type="text" name="template_name" class="form-control" required
                       value="{{ $selectedTemplate->name ?? '' }}"
                       placeholder="{{ __('e.g. Heritage Photos — ISAD(G)') }}">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label" for="sector">{{ __('Sector') }}</label>
                <select class="form-select" id="sector" name="sector">
                    @foreach(['archive'=>'Archive','library'=>'Library','museum'=>'Museum','gallery'=>'Gallery','dam'=>'DAM'] as $val => $label)
                        <option value="{{ $val }}" @selected($sectorVal === $val)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label" for="standard">{{ __('Standard') }}</label>
                <select class="form-select" id="standard" name="standard">
                    @foreach($standardsAllFlat as $code => $info)
                        <option value="{{ $code }}" data-sectors="{{ implode(',', $info['sectors']) }}" @selected($standardVal === $code)>{{ $info['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 mb-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1" @checked($selectedTemplate && $selectedTemplate->is_default)>
                    <label class="form-check-label" for="is_default">{{ __('Drive default') }}</label>
                </div>
            </div>
        </div>
    </div>

    <table class="table" id="mappings-table">
        <thead>
            <tr>
                <th style="width:30%">{{ __('SharePoint field') }}</th>
                <th style="width:30%">{{ __('AtoM target') }}</th>
                <th style="width:15%">{{ __('Transform') }}</th>
                <th style="width:20%">{{ __('Default value') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="mappings-tbody">
        @foreach($mappings as $m)
            <tr class="mapping-row">
                <td><input type="text" name="source_field[]" class="form-control" value="{{ $m->source_field }}"></td>
                <td>
                    <select name="target_field[]" class="form-select atom-target">
                        <option value=""></option>
                        @if(!empty($currentGroups['extras']))
                            <optgroup label="{{ ($standardLabels[$standardVal] ?? strtoupper($standardVal)) . ' ' . __('elements') }}">
                                @foreach($currentGroups['extras'] as $tf)
                                    <option value="{{ $tf }}" @selected($m->target_field === $tf)>{{ $tf }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        <optgroup label="{{ __('AtoM core fields') }}">
                            @foreach($currentGroups['core'] as $tf)
                                <option value="{{ $tf }}" @selected($m->target_field === $tf)>{{ $tf }}</option>
                            @endforeach
                        </optgroup>
                        @if($m->target_field && !in_array($m->target_field, $currentTargetFields, true))
                            <option value="{{ $m->target_field }}" selected>{{ $m->target_field }} {{ __('(not in standard)') }}</option>
                        @endif
                    </select>
                </td>
                <td>
                    <select name="transform[]" class="form-select">
                        <option value=""></option>
                        @foreach(['uppercase','lowercase','trim','titlecase','date_iso','strip_html'] as $t)
                            <option value="{{ $t }}" @selected($m->transform === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </td>
                <td><input type="text" name="default_value[]" class="form-control" value="{{ $m->default_value ?? '' }}"></td>
                <td><button type="button" class="btn btn-outline-danger btn-sm row-remove"><i class="fas fa-trash"></i></button></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <p>
        <button type="button" class="btn btn-outline-primary btn-sm" id="add-row">
            <i class="fas fa-plus me-1"></i>{{ __('Add mapping') }}
        </button>
    </p>

    <div class="d-flex justify-content-between">
        @if($selectedTemplate && (int) $selectedTemplate->id > 0)
            <button type="button" class="btn btn-outline-danger" id="delete-template-btn"
                    data-template-name="{{ $selectedTemplate->name }}">
                <i class="fas fa-trash me-1"></i>{{ __('Delete template') }}
            </button>
        @else
            <span></span>
        @endif
        <button type="submit" class="btn btn-primary">{{ __('Save template') }}</button>
    </div>
</form>

@if($selectedTemplate && (int) $selectedTemplate->id > 0)
<form id="delete-template-form" method="post" action="{{ route('sharepoint.mappings.template.delete') }}" style="display:none;">
    @csrf
    <input type="hidden" name="drive_id" value="{{ $driveId }}">
    <input type="hidden" name="template_id" value="{{ (int) $selectedTemplate->id }}">
</form>
@endif

<script>
(function () {
    var tbody = document.getElementById('mappings-tbody');
    var sectorSel = document.getElementById('sector');
    var standardSel = document.getElementById('standard');
    var targetFieldsByStandard = @json($targetFieldsByStandard);
    var targetFieldGroups = @json($targetFieldGroups);
    var standardLabels = @json($standardLabels);

    function currentTargetFields() {
        return targetFieldsByStandard[standardSel.value] || [];
    }
    function currentGroups() {
        return targetFieldGroups[standardSel.value] || {extras: [], core: currentTargetFields()};
    }

    function buildTargetSelect(currentValue) {
        var groups = currentGroups();
        var stdLabel = standardLabels[standardSel.value] || standardSel.value.toUpperCase();
        var sel = document.createElement('select');
        sel.name = 'target_field[]';
        sel.className = 'form-select atom-target';
        var blank = document.createElement('option'); blank.value = ''; sel.appendChild(blank);
        var allValues = [];
        function addOption(parent, value) {
            var o = document.createElement('option');
            o.value = value; o.textContent = value;
            if (currentValue === value) o.selected = true;
            parent.appendChild(o);
            allValues.push(value);
        }
        if (groups.extras && groups.extras.length) {
            var og1 = document.createElement('optgroup');
            og1.label = stdLabel + ' elements';
            groups.extras.forEach(function (tf) { addOption(og1, tf); });
            sel.appendChild(og1);
        }
        if (groups.core && groups.core.length) {
            var og2 = document.createElement('optgroup');
            og2.label = 'AtoM core fields';
            groups.core.forEach(function (tf) { addOption(og2, tf); });
            sel.appendChild(og2);
        }
        if (currentValue && allValues.indexOf(currentValue) === -1) {
            var orphan = document.createElement('option');
            orphan.value = currentValue; orphan.selected = true;
            orphan.textContent = currentValue + ' (not in standard)';
            sel.appendChild(orphan);
        }
        return sel;
    }

    function rebuildAllTargetSelects() {
        tbody.querySelectorAll('tr.mapping-row').forEach(function (row) {
            var old = row.querySelector('select.atom-target');
            var current = old ? old.value : '';
            var fresh = buildTargetSelect(current);
            old.parentNode.replaceChild(fresh, old);
        });
    }

    function filterStandards() {
        var sector = sectorSel.value;
        var currentVal = standardSel.value;
        var firstVisible = null;
        var currentVisible = false;
        for (var i = 0; i < standardSel.options.length; i++) {
            var opt = standardSel.options[i];
            var sectors = (opt.getAttribute('data-sectors') || '').split(',');
            var show = sectors.indexOf(sector) !== -1;
            opt.style.display = show ? '' : 'none';
            opt.disabled = !show;
            if (show && !firstVisible) firstVisible = opt.value;
            if (show && opt.value === currentVal) currentVisible = true;
        }
        if (!currentVisible && firstVisible) {
            standardSel.value = firstVisible;
            rebuildAllTargetSelects();
        }
    }

    sectorSel.addEventListener('change', filterStandards);
    standardSel.addEventListener('change', rebuildAllTargetSelects);
    filterStandards();

    document.getElementById('add-row').addEventListener('click', function () {
        var tr = document.createElement('tr');
        tr.className = 'mapping-row';
        var tdSource = document.createElement('td');
        var src = document.createElement('input'); src.type = 'text'; src.name = 'source_field[]'; src.className = 'form-control'; src.placeholder = 'e.g. Title';
        tdSource.appendChild(src);
        var tdTarget = document.createElement('td'); tdTarget.appendChild(buildTargetSelect(''));
        var tdTransform = document.createElement('td');
        tdTransform.innerHTML = '<select name="transform[]" class="form-select"><option value=""></option><option>uppercase</option><option>lowercase</option><option>trim</option><option>titlecase</option><option>date_iso</option><option>strip_html</option></select>';
        var tdDefault = document.createElement('td'); tdDefault.innerHTML = '<input type="text" name="default_value[]" class="form-control">';
        var tdAct = document.createElement('td'); tdAct.innerHTML = '<button type="button" class="btn btn-outline-danger btn-sm row-remove"><i class="fas fa-trash"></i></button>';
        tr.appendChild(tdSource); tr.appendChild(tdTarget); tr.appendChild(tdTransform); tr.appendChild(tdDefault); tr.appendChild(tdAct);
        tbody.appendChild(tr);
    });
    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.row-remove');
        if (btn) btn.closest('tr').remove();
    });
    var del = document.getElementById('delete-template-btn');
    if (del) {
        del.addEventListener('click', function () {
            var n = del.dataset.templateName || 'this template';
            if (!confirm('Delete "' + n + '" and all of its mappings? This cannot be undone.')) return;
            document.getElementById('delete-template-form').submit();
        });
    }
})();
</script>
@else
    <div class="alert alert-info">{{ __('Select a drive to edit its mapping templates.') }}</div>
@endif
@endsection
