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

@section('title', 'Map & Enrich')

@section('content')
@php
    $session = $session ?? null;
    $mappings = $mappings ?? [];
    $targetFields = $targetFields ?? [];
    $savedProfiles = $savedProfiles ?? [];
    $sampleRows = $sampleRows ?? [];
    $isDirectoryImport = $isDirectoryImport ?? false;
    $rowCount = $rowCount ?? 0;
    $fieldGroups = $fieldGroups ?? [];
    $vocabularies = $vocabularies ?? [];
    $requiredFields = $requiredFields ?? [];
@endphp

<h1>{{ __('Map &amp; Enrich') }}</h1>

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('ingest.index') }}">Ingestion Manager</a></li>
        <li class="breadcrumb-item">{{ $session->title ?? ('Session #' . ($session->id ?? '')) }}</li>
        <li class="breadcrumb-item active" aria-current="page">Map &amp; Enrich</li>
    </ol>
</nav>

{{-- Wizard Progress --}}
<div class="mb-4">
    <div class="d-flex justify-content-between text-center">
        <div class="flex-fill"><span class="badge bg-success rounded-pill">1</span><br><small class="text-muted">{{ __('Configure') }}</small></div>
        <div class="flex-fill"><span class="badge bg-success rounded-pill">2</span><br><small class="text-muted">{{ __('Upload') }}</small></div>
        <div class="flex-fill"><span class="badge bg-primary rounded-pill">3</span><br><small class="fw-bold">{{ __('Map') }}</small></div>
        <div class="flex-fill"><span class="badge bg-secondary rounded-pill">4</span><br><small class="text-muted">{{ __('Validate') }}</small></div>
        <div class="flex-fill"><span class="badge bg-secondary rounded-pill">5</span><br><small class="text-muted">{{ __('Preview') }}</small></div>
        <div class="flex-fill"><span class="badge bg-secondary rounded-pill">6</span><br><small class="text-muted">{{ __('Commit') }}</small></div>
    </div>
    <div class="progress mt-2" style="height: 4px;">
        <div class="progress-bar" style="width: 42%"></div>
    </div>
</div>

@if($isDirectoryImport)
{{-- Directory Import: Metadata Entry --}}
<form method="post" action="{{ route('ingest.map', ['id' => $session->id ?? 0]) }}">
    @csrf

    <div class="alert alert-info">
        <i class="fas fa-folder-open me-2"></i>
        <strong>{{ $rowCount }} files</strong> found in directory. Enter metadata to apply to all records.
        Individual titles are generated from filenames.
        <span class="text-danger ms-2">*</span> = Required
    </div>

    <div class="row">
        <div class="col-md-8">
            {{-- Identifier Counter Option --}}
            <div class="card mb-3 border-info">
                <div class="card-header bg-info bg-opacity-10">
                    <h6 class="mb-0"><i class="fas fa-sort-numeric-down me-2"></i>{{ __('Identifier Counter / Suffix') }}</h6>
                </div>
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="enable_counter" name="metadata[_enable_counter]" value="1">
                        <label class="form-check-label" for="enable_counter">
                            <strong>{{ __('Add sequential counter to identifiers') }}</strong>
                        </label>
                    </div>
                    <div id="counter-options" style="display:none;">
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label small">{{ __('Prefix') }}</label>
                                <input type="text" class="form-control form-control-sm" name="metadata[_counter_prefix]" placeholder="{{ __('e.g. OBJ-') }}">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label small">{{ __('Start Number') }}</label>
                                <input type="number" class="form-control form-control-sm" name="metadata[_counter_start]" value="1" min="1">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label small">{{ __('Padding (digits)') }}</label>
                                <input type="number" class="form-control form-control-sm" name="metadata[_counter_padding]" value="4" min="1" max="10">
                            </div>
                        </div>
                        <small class="text-muted">Preview: <code id="counter-preview">OBJ-0001, OBJ-0002, ...</code></small>
                    </div>
                </div>
            </div>

            @foreach($fieldGroups as $groupKey => $group)
            <div class="card mb-3">
                <div class="card-header bg-primary bg-opacity-10">
                    <h6 class="mb-0"><i class="fas fa-layer-group me-2"></i>{{ $group['label'] ?? '' }}</h6>
                </div>
                <div class="card-body">
                    @foreach(($group['fields'] ?? []) as $fieldName => $fieldDef)
                        @php
                            $isRequired = !empty($fieldDef['required']);
                            $vocabKey = $fieldDef['vocabulary'] ?? null;
                            $vocabOptions = ($vocabKey && isset($vocabularies[$vocabKey])) ? $vocabularies[$vocabKey]['values'] : ($fieldDef['options'] ?? []);
                            $vocabLabel = ($vocabKey && isset($vocabularies[$vocabKey])) ? $vocabularies[$vocabKey]['label'] : '';
                        @endphp
                        <div class="mb-3">
                            <label class="form-label" for="meta_{{ $fieldName }}">
                                {{ $fieldDef['label'] ?? $fieldName }}
                                @if($isRequired)<span class="text-danger">*</span>@endif
                                @if($vocabLabel)<small class="text-muted ms-1">({{ $vocabLabel }})</small>@endif
                            </label>
                            @if(($fieldDef['type'] ?? '') === 'select' && !empty($vocabOptions))
                                <select class="form-select" id="meta_{{ $fieldName }}" name="metadata[{{ $fieldName }}]">
                                    <option value="">— Select —</option>
                                    @foreach($vocabOptions as $opt)
                                        <option value="{{ $opt }}" {{ ($fieldName === 'levelOfDescription' && $opt === 'Item') ? 'selected' : '' }}>{{ $opt }}</option>
                                    @endforeach
                                </select>
                            @elseif(($fieldDef['type'] ?? '') === 'textarea')
                                <textarea class="form-control" id="meta_{{ $fieldName }}" name="metadata[{{ $fieldName }}]" rows="3"></textarea>
                            @else
                                <input type="text" class="form-control" id="meta_{{ $fieldName }}" name="metadata[{{ $fieldName }}]">
                            @endif
                            @if(!empty($fieldDef['help']))
                                <small class="text-muted">{{ $fieldDef['help'] }}</small>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-eye me-2"></i>{{ __('Files Preview') }}</h5>
                </div>
                <div class="card-body">
                    @if(!empty($sampleRows))
                        @foreach($sampleRows as $i => $sr)
                            <div class="mb-2 p-2 border rounded">
                                <small class="text-muted">Row {{ $sr->row_number ?? '' }}</small>
                                <div><strong>{{ $sr->title ?? '—' }}</strong></div>
                                @if(!empty($sr->digital_object_path))
                                    <small class="text-muted"><i class="fas fa-file me-1"></i>{{ basename($sr->digital_object_path) }}</small>
                                @endif
                            </div>
                        @endforeach
                        @if($rowCount > 10)
                            <p class="text-muted small mt-2 mb-0">...and {{ $rowCount - 10 }} more files</p>
                        @endif
                    @else
                        <p class="text-muted mb-0">No files found in directory</p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6><i class="fas fa-info-circle me-2"></i>{{ __('About Directory Import') }}</h6>
                    <ul class="small text-muted mb-0">
                        <li>One record per file in the directory</li>
                        <li>Titles auto-generated from filenames</li>
                        <li>Metadata entered here applies to ALL records</li>
                        <li>You can edit individual records after commit</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('ingest.upload', ['id' => $session->id ?? 0]) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}
        </a>
        <button type="submit" class="btn btn-primary">
            Apply Metadata &amp; Validate <i class="fas fa-arrow-right ms-1"></i>
        </button>
    </div>
</form>

@else
{{-- CSV Import: Column Mapping --}}

@if(!empty($savedProfiles))
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="post" action="{{ route('ingest.map', ['id' => $session->id ?? 0]) }}" class="d-flex align-items-center">
            @csrf
            <input type="hidden" name="form_action" value="load_profile">
            <label class="form-label mb-0 me-2 text-nowrap">{{ __('Load saved profile:') }}</label>
            <select class="form-select form-select-sm me-2" name="mapping_profile_id" style="max-width: 300px;">
                <option value="">— Select —</option>
                @foreach($savedProfiles as $p)
                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->target_type }})</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Load') }}</button>
        </form>
    </div>
</div>
@endif

<form method="post" action="{{ route('ingest.map', ['id' => $session->id ?? 0]) }}">
    @csrf
    <input type="hidden" name="form_action" value="save">

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-columns me-2"></i>{{ __('Column Mapping') }}</h5>
                    <span class="badge bg-info">
                        {{ count($mappings) }} columns
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 30%">{{ __('Source Column') }}</th>
                                    <th style="width: 30%">{{ __('Target Field') }}</th>
                                    <th style="width: 15%">{{ __('Default') }}</th>
                                    <th style="width: 15%">{{ __('Transform') }}</th>
                                    <th style="width: 10%">{{ __('Ignore') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mappings as $map)
                                    @php
                                        $confClass = 'bg-danger bg-opacity-10';
                                        if (!empty($map->target_field)) {
                                            $confClass = 'bg-success bg-opacity-10';
                                        }
                                    @endphp
                                    <tr class="{{ $confClass }}">
                                        <td><code>{{ $map->source_column ?? '' }}</code></td>
                                        <td>
                                            <select class="form-select form-select-sm" name="target_field[{{ $map->id }}]">
                                                <option value="">— unmapped —</option>
                                                @foreach($targetFields as $tf)
                                                    <option value="{{ $tf }}" {{ ($map->target_field ?? '') === $tf ? 'selected' : '' }}>{{ $tf }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                   name="default_value[{{ $map->id }}]"
                                                   value="{{ $map->default_value ?? '' }}"
                                                   placeholder="{{ __('default') }}">
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" name="transform[{{ $map->id }}]">
                                                <option value="">{{ __('None') }}</option>
                                                @foreach(['trim', 'uppercase', 'lowercase', 'titlecase', 'date_iso', 'strip_html'] as $t)
                                                    <option value="{{ $t }}" {{ ($map->transform ?? '') === $t ? 'selected' : '' }}>{{ $t }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input"
                                                   name="is_ignored[{{ $map->id }}]" value="1"
                                                   {{ !empty($map->is_ignored) ? 'checked' : '' }}>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-eye me-2"></i>{{ __('Data Preview') }}</h5>
                </div>
                <div class="card-body">
                    @if(!empty($sampleRows))
                        @foreach($sampleRows as $i => $sr)
                            <div class="mb-2 p-2 border rounded {{ $i === 0 ? 'border-primary' : '' }}">
                                <small class="text-muted">Row {{ $sr->row_number ?? '' }}</small>
                                <div><strong>{{ $sr->title ?? '—' }}</strong></div>
                                @if(!empty($sr->level_of_description))
                                    <small class="badge bg-secondary">{{ $sr->level_of_description }}</small>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <p class="text-muted mb-0">No data rows found</p>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-link me-2"></i>{{ __('Digital Object Matching') }}</h5>
                </div>
                <div class="card-body">
                    <select class="form-select form-select-sm" name="do_match_strategy">
                        <option value="filename">{{ __('Match by filename') }}</option>
                        <option value="legacyId">{{ __('Match by legacyId') }}</option>
                        <option value="title">{{ __('Match by title') }}</option>
                    </select>
                    <small class="text-muted">{{ __('How to match digital objects from ZIP to CSV rows') }}</small>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6>{{ __('Legend') }}</h6>
                    <div class="d-flex align-items-center mb-1">
                        <span class="d-inline-block me-2 rounded" style="width:16px;height:16px;background:rgba(25,135,84,0.1);border:1px solid rgba(25,135,84,0.3)"></span>
                        <small>{{ __('Mapped (auto or manual)') }}</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="d-inline-block me-2 rounded" style="width:16px;height:16px;background:rgba(220,53,69,0.1);border:1px solid rgba(220,53,69,0.3)"></span>
                        <small>{{ __('Unmapped (needs attention)') }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('ingest.upload', ['id' => $session->id ?? 0]) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}
        </a>
        <button type="submit" class="btn btn-primary">
            Save Mappings &amp; Validate <i class="fas fa-arrow-right ms-1"></i>
        </button>
    </div>
</form>
@endif

@if($isDirectoryImport)
<script>
document.addEventListener('DOMContentLoaded', function() {
    var cb = document.getElementById('enable_counter');
    var opts = document.getElementById('counter-options');
    if (!cb || !opts) return;

    cb.addEventListener('change', function() {
        opts.style.display = this.checked ? 'block' : 'none';
    });

    var prefixEl = opts.querySelector('input[name="metadata[_counter_prefix]"]');
    var startEl = opts.querySelector('input[name="metadata[_counter_start]"]');
    var padEl = opts.querySelector('input[name="metadata[_counter_padding]"]');
    var preview = document.getElementById('counter-preview');

    function updatePreview() {
        var prefix = prefixEl ? prefixEl.value : '';
        var start = parseInt(startEl ? startEl.value : 1) || 1;
        var pad = parseInt(padEl ? padEl.value : 4) || 4;
        var examples = [];
        for (var i = 0; i < 3; i++) {
            var num = String(start + i);
            while (num.length < pad) num = '0' + num;
            examples.push(prefix + num);
        }
        if (preview) preview.textContent = examples.join(', ') + ', ...';
    }

    if (prefixEl) prefixEl.addEventListener('input', updatePreview);
    if (startEl) startEl.addEventListener('input', updatePreview);
    if (padEl) padEl.addEventListener('input', updatePreview);
});
</script>
@endif
@endsection
