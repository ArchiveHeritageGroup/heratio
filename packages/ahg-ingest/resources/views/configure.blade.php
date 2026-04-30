{{--
  New Ingest / Edit Ingest Configuration — Heratio
  Migrated from AtoM ahgIngestPlugin ingest/configureSuccess.php

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems

  This file is part of Heratio.
  Heratio is free software under the GNU AGPL v3.
--}}
@extends('theme::layouts.1col')
@section('title', $session ? 'Edit Ingest Configuration' : 'New Ingest')

@section('content')
<h1>{{ $session ? 'Edit Ingest Configuration' : 'New Ingest' }}</h1>

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('ingest.index') }}">Ingestion Manager</a></li>
        <li class="breadcrumb-item active">{{ $session ? 'Edit Configuration' : 'New Ingest' }}</li>
    </ol>
</nav>

{{-- Wizard Progress --}}
<div class="mb-4">
    <div class="d-flex justify-content-between text-center">
        <div class="flex-fill"><span class="badge bg-primary rounded-pill">1</span><br><small class="fw-bold">{{ __('Configure') }}</small></div>
        <div class="flex-fill"><span class="badge bg-secondary rounded-pill">2</span><br><small class="text-muted">{{ __('Upload') }}</small></div>
        <div class="flex-fill"><span class="badge bg-secondary rounded-pill">3</span><br><small class="text-muted">{{ __('Map') }}</small></div>
        <div class="flex-fill"><span class="badge bg-secondary rounded-pill">4</span><br><small class="text-muted">{{ __('Validate') }}</small></div>
        <div class="flex-fill"><span class="badge bg-secondary rounded-pill">5</span><br><small class="text-muted">{{ __('Preview') }}</small></div>
        <div class="flex-fill"><span class="badge bg-secondary rounded-pill">6</span><br><small class="text-muted">{{ __('Commit') }}</small></div>
    </div>
    <div class="progress mt-2" style="height:4px;">
        <div class="progress-bar" style="width:8%"></div>
    </div>
</div>

@php
    $sectorVal = $session->sector ?? 'archive';
    $standardVal = $session->standard ?? 'isadg';
    $placementVal = $session->parent_placement ?? 'top_level';
    $entityTypeVal = $session->entity_type ?? 'description';
@endphp

<form method="POST" action="{{ route('ingest.configure', $session->id ?? null) }}">
    @csrf

    <div class="row">
        {{-- Left Column: Core Settings --}}
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-cog me-2"></i>Ingest Settings</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">{{ __('Session Title') }}</label>
                        <input type="text" class="form-control" id="title" name="title"
                               value="{{ e($session->title ?? '') }}" placeholder="{{ __('e.g. Annual Report Collection 2024') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Record Type <span class="text-danger">*</span></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="entity_type" id="entity_type_description"
                                   value="description" @checked($entityTypeVal === 'description')>
                            <label class="btn btn-outline-primary" for="entity_type_description">
                                <i class="fas fa-archive me-1"></i>Archival Descriptions
                            </label>
                            <input type="radio" class="btn-check" name="entity_type" id="entity_type_accession"
                                   value="accession" @checked($entityTypeVal === 'accession')>
                            <label class="btn btn-outline-primary" for="entity_type_accession">
                                <i class="fas fa-clipboard-list me-1"></i>Accessions
                            </label>
                        </div>
                        <small class="text-muted">{{ __('Choose whether to import archival descriptions or accession records') }}</small>
                    </div>

                    <div class="row" id="sector-standard-row">
                        <div class="col-md-6 mb-3">
                            <label for="sector" class="form-label">Sector <span class="text-danger">*</span></label>
                            <select class="form-select" id="sector" name="sector">
                                @foreach(['archive' => 'Archive', 'museum' => 'Museum', 'library' => 'Library', 'gallery' => 'Gallery', 'dam' => 'DAM'] as $val => $label)
                                    <option value="{{ $val }}" @selected($sectorVal === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="standard" class="form-label">Descriptive Standard <span class="text-danger">*</span></label>
                            <select class="form-select" id="standard" name="standard">
                                @foreach([
                                    'isadg' => ['label' => 'ISAD(G)', 'sectors' => 'archive,library'],
                                    'dc' => ['label' => 'Dublin Core', 'sectors' => 'archive,library,museum,gallery,dam'],
                                    'rad' => ['label' => 'RAD', 'sectors' => 'archive'],
                                    'dacs' => ['label' => 'DACS', 'sectors' => 'archive'],
                                    'mods' => ['label' => 'MODS', 'sectors' => 'library'],
                                    'spectrum' => ['label' => 'SPECTRUM', 'sectors' => 'museum'],
                                    'cco' => ['label' => 'CCO', 'sectors' => 'gallery'],
                                ] as $val => $info)
                                    <option value="{{ $val }}" data-sectors="{{ $info['sectors'] }}" @selected($standardVal === $val)>{{ $info['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="repository_id" class="form-label">{{ __('Repository') }}</label>
                        <select class="form-select" id="repository_id" name="repository_id">
                            <option value="">— Select repository —</option>
                            @foreach($repositories as $repo)
                                <option value="{{ $repo->id }}" @selected(($session->repository_id ?? '') == $repo->id)>{{ e($repo->name) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Hierarchy Placement --}}
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>Hierarchy Placement</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Where should imported records be placed?') }}</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="parent_placement" id="placement_top" value="top_level" @checked($placementVal === 'top_level')>
                            <label class="form-check-label" for="placement_top">{{ __('Top-level (directly under root)') }}</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="parent_placement" id="placement_existing" value="existing" @checked($placementVal === 'existing')>
                            <label class="form-check-label" for="placement_existing">{{ __('Under an existing record') }}</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="parent_placement" id="placement_new" value="new" @checked($placementVal === 'new')>
                            <label class="form-check-label" for="placement_new">{{ __('Create a new parent record') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="parent_placement" id="placement_csv" value="csv_hierarchy" @checked($placementVal === 'csv_hierarchy')>
                            <label class="form-check-label" for="placement_csv">{{ __('Use hierarchy from CSV (legacyId/parentId)') }}</label>
                        </div>
                    </div>

                    <div id="existing-parent-panel" class="mb-3" style="display:none;">
                        <label for="parent_search" class="form-label">{{ __('Search for parent record') }}</label>
                        <input type="text" class="form-control" id="parent_search" placeholder="{{ __('Type to search...') }}" autocomplete="off">
                        <input type="hidden" name="parent_id" id="parent_id" value="{{ $session->parent_id ?? '' }}">
                        <div id="parent_results" class="list-group mt-1"></div>
                    </div>

                    <div id="new-parent-panel" style="display:none;">
                        <div class="mb-3">
                            <label for="new_parent_title" class="form-label">{{ __('New parent title') }}</label>
                            <input type="text" class="form-control" id="new_parent_title" name="new_parent_title" value="{{ e($session->new_parent_title ?? '') }}">
                        </div>
                        <div class="mb-3">
                            <label for="new_parent_level" class="form-label">{{ __('Level of description') }}</label>
                            <select class="form-select" id="new_parent_level" name="new_parent_level">
                                @foreach(['Fonds', 'Collection', 'Series', 'Subfonds'] as $lvl)
                                    <option value="{{ $lvl }}" @selected(($session->new_parent_level ?? 'Fonds') === $lvl)>{{ $lvl }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Output Options --}}
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Output Options</h5></div>
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="output_create_records" name="output_create_records" value="1" @checked($session->output_create_records ?? true)>
                        <label class="form-check-label" for="output_create_records">{{ __('Create records') }}</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="output_generate_sip" name="output_generate_sip" value="1" @checked($session->output_generate_sip ?? false)>
                        <label class="form-check-label" for="output_generate_sip">{{ __('Generate SIP package') }}</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="output_generate_aip" name="output_generate_aip" value="1" @checked($session->output_generate_aip ?? false)>
                        <label class="form-check-label" for="output_generate_aip">{{ __('Generate AIP package') }}</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="output_generate_dip" name="output_generate_dip" value="1" @checked($session->output_generate_dip ?? false)>
                        <label class="form-check-label" for="output_generate_dip">{{ __('Generate DIP package') }}</label>
                    </div>
                    <hr>
                    <h6>{{ __('Derivatives') }}</h6>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="derivative_thumbnails" name="derivative_thumbnails" value="1" @checked($session->derivative_thumbnails ?? true)>
                        <label class="form-check-label" for="derivative_thumbnails">{{ __('Generate thumbnails') }}</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="derivative_reference" name="derivative_reference" value="1" @checked($session->derivative_reference ?? true)>
                        <label class="form-check-label" for="derivative_reference">{{ __('Generate reference images') }}</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Full-width: Processing Options --}}
    <div class="card mb-4">
        <div class="card-header bg-info text-white"><h5 class="mb-0"><i class="fas fa-brain me-2"></i>Processing Options</h5></div>
        <div class="card-body">
            <p class="text-muted mb-3">Select AI and processing actions to run on ingested records after commit.</p>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="process_virus_scan" name="process_virus_scan" value="1" @checked($session->process_virus_scan ?? true)>
                        <label class="form-check-label" for="process_virus_scan"><i class="fas fa-shield-virus text-danger me-1"></i>Virus Scan</label>
                    </div>
                    <small class="text-muted d-block ms-4">{{ __('ClamAV malware scan') }}</small>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="process_ocr" name="process_ocr" value="1" @checked($session->process_ocr ?? false)>
                        <label class="form-check-label" for="process_ocr"><i class="fas fa-file-alt text-primary me-1"></i>OCR</label>
                    </div>
                    <small class="text-muted d-block ms-4">{{ __('Tesseract text extraction') }}</small>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="process_ner" name="process_ner" value="1" @checked($session->process_ner ?? false)>
                        <label class="form-check-label" for="process_ner"><i class="fas fa-tags text-success me-1"></i>NER</label>
                    </div>
                    <small class="text-muted d-block ms-4">{{ __('Named entity extraction') }}</small>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="process_summarize" name="process_summarize" value="1" @checked($session->process_summarize ?? false)>
                        <label class="form-check-label" for="process_summarize"><i class="fas fa-compress-alt text-warning me-1"></i>Summarize</label>
                    </div>
                    <small class="text-muted d-block ms-4">{{ __('Auto-generate summaries') }}</small>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="process_spellcheck" name="process_spellcheck" value="1" @checked($session->process_spellcheck ?? false)>
                        <label class="form-check-label" for="process_spellcheck"><i class="fas fa-spell-check text-info me-1"></i>Spell Check</label>
                    </div>
                    <small class="text-muted d-block ms-4">aspell grammar check</small>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="process_format_id" name="process_format_id" value="1" @checked($session->process_format_id ?? false)>
                        <label class="form-check-label" for="process_format_id"><i class="fas fa-fingerprint text-secondary me-1"></i>Format ID</label>
                    </div>
                    <small class="text-muted d-block ms-4">{{ __('Siegfried PRONOM identification') }}</small>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="process_face_detect" name="process_face_detect" value="1" @checked($session->process_face_detect ?? false)>
                        <label class="form-check-label" for="process_face_detect"><i class="fas fa-user-circle text-dark me-1"></i>Face Detection</label>
                    </div>
                    <small class="text-muted d-block ms-4">{{ __('Detect & match faces') }}</small>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="process_translate" name="process_translate" value="1" @checked($session->process_translate ?? false)>
                        <label class="form-check-label" for="process_translate"><i class="fas fa-language text-primary me-1"></i>Translate</label>
                    </div>
                    <small class="text-muted d-block ms-4">{{ __('Argos offline translation') }}</small>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('ingest.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Dashboard') }}
        </a>
        <button type="submit" class="btn atom-btn-white">
            Next: Upload Files <i class="fas fa-arrow-right ms-1"></i>
        </button>
    </div>
</form>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var radios = document.querySelectorAll('input[name="parent_placement"]');
    var existingPanel = document.getElementById('existing-parent-panel');
    var newPanel = document.getElementById('new-parent-panel');

    function togglePanels() {
        var val = document.querySelector('input[name="parent_placement"]:checked').value;
        existingPanel.style.display = val === 'existing' ? '' : 'none';
        newPanel.style.display = val === 'new' ? '' : 'none';
    }
    radios.forEach(function(r) { r.addEventListener('change', togglePanels); });
    togglePanels();

    // Entity type toggle
    var entityRadios = document.querySelectorAll('input[name="entity_type"]');
    var sectorStandardRow = document.getElementById('sector-standard-row');
    var hierarchyCard = document.querySelector('input[name="parent_placement"]').closest('.card');
    var sectorSel = document.getElementById('sector');

    function toggleEntityType() {
        var val = document.querySelector('input[name="entity_type"]:checked').value;
        sectorStandardRow.style.display = val === 'accession' ? 'none' : '';
        hierarchyCard.style.display = val === 'accession' ? 'none' : '';
        if (val === 'accession') sectorSel.value = 'archive';
    }
    entityRadios.forEach(function(r) { r.addEventListener('change', toggleEntityType); });
    toggleEntityType();

    // Filter standards by sector
    var standardSel = document.getElementById('standard');
    function filterStandards() {
        var sector = sectorSel.value;
        var currentVal = standardSel.value;
        var firstVisible = null, currentVisible = false;
        for (var i = 0; i < standardSel.options.length; i++) {
            var opt = standardSel.options[i];
            var sectors = (opt.getAttribute('data-sectors') || '').split(',');
            var show = sectors.indexOf(sector) !== -1;
            opt.style.display = show ? '' : 'none';
            opt.disabled = !show;
            if (show && !firstVisible) firstVisible = opt.value;
            if (show && opt.value === currentVal) currentVisible = true;
        }
        if (!currentVisible && firstVisible) standardSel.value = firstVisible;
    }
    sectorSel.addEventListener('change', filterStandards);
    filterStandards();

    // Parent search autocomplete
    var searchInput = document.getElementById('parent_search');
    var parentIdInput = document.getElementById('parent_id');
    var resultsDiv = document.getElementById('parent_results');
    var debounce;
    searchInput.addEventListener('input', function() {
        clearTimeout(debounce);
        var q = this.value.trim();
        if (q.length < 2) { resultsDiv.innerHTML = ''; return; }
        debounce = setTimeout(function() {
            fetch('{{ route("informationobject.autocomplete") }}?query=' + encodeURIComponent(q), {
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var items = Array.isArray(data) ? data : (data.data || []);
                resultsDiv.innerHTML = '';
                items.forEach(function(item) {
                    var a = document.createElement('a');
                    a.className = 'list-group-item list-group-item-action';
                    a.href = '#';
                    a.textContent = (item.identifier ? '[' + item.identifier + '] ' : '') + (item.name || item.title || '');
                    a.addEventListener('click', function(e) {
                        e.preventDefault();
                        parentIdInput.value = item.id;
                        searchInput.value = item.name || item.title || '';
                        resultsDiv.innerHTML = '';
                    });
                    resultsDiv.appendChild(a);
                });
            });
        }, 300);
    });
});
</script>
@endpush
@endsection
