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

@section('title', 'Upload Files')

@section('content')
@php
    $session = $session ?? null;
    $files = $files ?? [];
    $spEnabled = class_exists(\AhgSharePoint\Services\SharePointBrowserService::class);
    $spTenants = $spTenants ?? [];
@endphp

<h1>{{ __('Upload Files') }}</h1>

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('ingest.index') }}">Ingestion Manager</a></li>
        <li class="breadcrumb-item">{{ $session->title ?? ('Session #' . ($session->id ?? '')) }}</li>
        <li class="breadcrumb-item active" aria-current="page">Upload</li>
    </ol>
</nav>

<div class="mb-4">
    <div class="d-flex justify-content-between text-center">
        <div class="flex-fill"><span class="badge bg-success rounded-pill">1</span><br><small class="text-muted">{{ __('Configure') }}</small></div>
        <div class="flex-fill"><span class="badge bg-primary rounded-pill">2</span><br><small class="fw-bold">{{ __('Upload') }}</small></div>
        <div class="flex-fill"><span class="badge bg-secondary rounded-pill">3</span><br><small class="text-muted">{{ __('Map') }}</small></div>
        <div class="flex-fill"><span class="badge bg-secondary rounded-pill">4</span><br><small class="text-muted">{{ __('Validate') }}</small></div>
        <div class="flex-fill"><span class="badge bg-secondary rounded-pill">5</span><br><small class="text-muted">{{ __('Preview') }}</small></div>
        <div class="flex-fill"><span class="badge bg-secondary rounded-pill">6</span><br><small class="text-muted">{{ __('Commit') }}</small></div>
    </div>
    <div class="progress mt-2" style="height: 4px;">
        <div class="progress-bar" style="width: 25%"></div>
    </div>
</div>

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    <div class="col-md-8">

        <ul class="nav nav-tabs mb-3" id="ingestSourceTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-fileupload-tab" data-bs-toggle="tab" data-bs-target="#tab-fileupload" type="button" role="tab">
                    <i class="fas fa-cloud-upload-alt me-1"></i>{{ __('File / Directory') }}
                </button>
            </li>
            @if($spEnabled)
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-sharepoint-tab" data-bs-toggle="tab" data-bs-target="#tab-sharepoint" type="button" role="tab">
                    <i class="fab fa-microsoft me-1"></i>{{ __('From SharePoint') }}
                </button>
            </li>
            @endif
        </ul>

        <div class="tab-content" id="ingestSourceTabsContent">

            <div class="tab-pane fade show active" id="tab-fileupload" role="tabpanel">
                <form method="post" enctype="multipart/form-data" action="{{ route('ingest.upload', ['id' => $session->id ?? 0]) }}">
                    @csrf

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-cloud-upload-alt me-2"></i>{{ __('Upload File') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="ingest_file" class="form-label">{{ __('Select CSV, ZIP, or EAD file') }}</label>
                                <div id="drop-zone" class="border border-2 border-dashed rounded p-5 text-center mb-3">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                    <p class="mb-1">Drag and drop file here, or click to browse</p>
                                    <small class="text-muted">{{ __('Supported: CSV, ZIP (with CSV + digital objects), EAD XML') }}</small>
                                    <input type="file" class="form-control mt-3" id="ingest_file" name="ingest_file"
                                           accept=".csv,.zip,.xml,.ead">
                                </div>
                                <div id="file-info" class="alert alert-info" style="display:none;"></div>
                            </div>

                            <hr>

                            <div class="mb-3">
                                <label for="directory_path" class="form-label">{{ __('Or enter a server directory path') }}</label>
                                <input type="text" class="form-control" id="directory_path" name="directory_path"
                                       placeholder="{{ __('/path/to/files/on/server') }}">
                                <small class="text-muted">{{ __('For large batches, point to a directory on the server instead of uploading') }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('ingest.configure', ['id' => $session->id ?? 0]) }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}
                        </a>
                        <button type="submit" class="btn btn-primary" id="btn-upload">
                            Upload &amp; Continue <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                    </div>
                </form>

                {{-- #1328 Resumable / chunked upload for large (>1GB) single files
                     (TIFF/JP2/video/3D). Sends the file in small chunks so it is not
                     bounded by post_max_size, resumes after an interruption, and on
                     completion is ingested into this session's target record. --}}
                <div class="card mb-4 border-primary">
                    <div class="card-header d-flex align-items-center">
                        <h5 class="mb-0"><i class="fas fa-server me-2"></i>{{ __('Large file (resumable upload)') }}</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">{{ __('For a single large digital object that exceeds the normal upload limit. The file is sent in chunks and can resume after a dropped connection; when finished it is ingested into this session\'s target record.') }}</p>
                        <div id="rz-drop" class="border border-2 border-dashed rounded p-4 text-center mb-3">
                            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                            <p class="mb-1">{{ __('Choose a large file') }}</p>
                            <input type="file" class="form-control mt-2" id="rz-file">
                        </div>
                        <div id="rz-progress-wrap" class="mb-2" style="display:none;">
                            <div class="d-flex justify-content-between small">
                                <span id="rz-status">{{ __('Uploading...') }}</span><span id="rz-pct">0%</span>
                            </div>
                            <div class="progress" style="height:1.25rem;">
                                <div id="rz-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%"></div>
                            </div>
                        </div>
                        <div id="rz-result"></div>
                        <button type="button" class="btn btn-primary" id="rz-start" disabled>
                            <i class="fas fa-upload me-1"></i>{{ __('Start resumable upload') }}
                        </button>
                    </div>
                </div>
            </div>

            @if($spEnabled)
            <div class="tab-pane fade" id="tab-sharepoint" role="tabpanel">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fab fa-microsoft me-2"></i>{{ __('Import from SharePoint') }}</h5>
                    </div>
                    <div class="card-body">

                        @if(empty($spTenants))
                            <div class="alert alert-warning mb-0">
                                {{ __('No SharePoint tenants are configured.') }}
                            </div>
                        @else

                        <form method="post" id="sp-import-form" action="{{ route('ingest.sharepoint.import', ['id' => $session->id ?? 0]) }}">
                            @csrf

                            <div class="mb-3">
                                <label for="sp_tenant" class="form-label">{{ __('SharePoint tenant') }}</label>
                                <select class="form-select" id="sp_tenant" name="sp_tenant_id">
                                    @foreach($spTenants as $t)
                                        <option value="{{ (int) $t->id }}">{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="sp_site" class="form-label">{{ __('Site') }}</label>
                                <select class="form-select" id="sp_site"><option value="">— Loading sites... —</option></select>
                            </div>
                            <div class="mb-3">
                                <label for="sp_drive" class="form-label">{{ __('Drive (document library)') }}</label>
                                <select class="form-select" id="sp_drive" disabled><option value="">— Select a site first —</option></select>
                                <input type="hidden" name="sp_drive_id" id="sp_drive_id_hidden">
                                <input type="hidden" name="sp_drive_name" id="sp_drive_name_hidden">
                                <input type="hidden" name="sp_site_id" id="sp_site_id_hidden">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">{{ __('Items') }}</label>
                                <div id="sp-tree" class="border rounded p-3" style="max-height: 360px; overflow-y: auto;">
                                    <small class="text-muted">{{ __('Select a drive to browse files') }}</small>
                                </div>
                            </div>

                            <div id="sp-selected-count" class="mb-3"></div>

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('ingest.configure', ['id' => $session->id ?? 0]) }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}
                                </a>
                                <button type="submit" class="btn btn-primary" id="sp-import-btn" disabled>
                                    {{ __('Import selected & continue') }} <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </form>

                        @endif
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('Session Info') }}</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li><strong>{{ __('Sector:') }}</strong> {{ ucfirst($session->sector ?? '') }}</li>
                    <li><strong>{{ __('Standard:') }}</strong> {{ strtoupper($session->standard ?? '') }}</li>
                    <li><strong>{{ __('Placement:') }}</strong> {{ ucfirst(str_replace('_', ' ', $session->parent_placement ?? '')) }}</li>
                </ul>
            </div>
        </div>

        @if(!empty($files))
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file me-2"></i>{{ __('Uploaded Files') }}</h5>
            </div>
            <div class="card-body">
                @foreach($files as $f)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <i class="fas fa-file-{{ ($f->file_type ?? '') === 'csv' ? 'csv' : (($f->file_type ?? '') === 'zip' ? 'archive' : (($f->file_type ?? '') === 'sharepoint' ? 'cloud' : 'code')) }} me-1"></i>
                            <small>{{ $f->original_name ?? '' }}</small>
                        </div>
                        <small class="text-muted">{{ ($f->row_count ?? 0) ? ($f->row_count . ' rows') : '' }}</small>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-download me-2"></i>{{ __('CSV Templates') }}</h5>
            </div>
            <div class="card-body">
                <a href="{{ route('ingest.template', ['sector' => $session->sector ?? 'archive']) }}"
                   class="btn btn-outline-secondary btn-sm w-100">
                    <i class="fas fa-download me-1"></i>Download Template for {{ ucfirst($session->sector ?? '') }}
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var dropZone = document.getElementById('drop-zone');
    var fileInput = document.getElementById('ingest_file');
    var fileInfo = document.getElementById('file-info');

    if (dropZone) {
        ['dragenter', 'dragover'].forEach(function(ev) {
            dropZone.addEventListener(ev, function(e) { e.preventDefault(); dropZone.classList.add('border-primary', 'bg-light'); });
        });
        ['dragleave', 'drop'].forEach(function(ev) {
            dropZone.addEventListener(ev, function(e) { e.preventDefault(); dropZone.classList.remove('border-primary', 'bg-light'); });
        });
        dropZone.addEventListener('drop', function(e) {
            if (e.dataTransfer.files.length > 0) { fileInput.files = e.dataTransfer.files; showFileInfo(e.dataTransfer.files[0]); }
        });
        dropZone.addEventListener('click', function() { fileInput.click(); });
        fileInput.addEventListener('change', function() { if (this.files.length > 0) showFileInfo(this.files[0]); });
    }

    function showFileInfo(file) {
        var size = (file.size / 1024 / 1024).toFixed(2);
        fileInfo.style.display = '';
        fileInfo.innerHTML = '<strong>' + file.name + '</strong> (' + size + ' MB)';
    }

    var browseUrl = '{{ route("ingest.sharepoint.browse", ["id" => $session->id ?? 0]) }}';
    var spTenant = document.getElementById('sp_tenant');
    var spSite = document.getElementById('sp_site');
    var spDrive = document.getElementById('sp_drive');
    var spDriveIdHidden = document.getElementById('sp_drive_id_hidden');
    var spDriveNameHidden = document.getElementById('sp_drive_name_hidden');
    var spSiteIdHidden = document.getElementById('sp_site_id_hidden');
    var spTree = document.getElementById('sp-tree');
    var spImportBtn = document.getElementById('sp-import-btn');
    var spSelectedCount = document.getElementById('sp-selected-count');
    if (!spTenant) return;

    function fetchSP(params) {
        var qs = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
        return fetch(browseUrl + (browseUrl.indexOf('?') === -1 ? '?' : '&') + qs, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });
    }

    function loadSites() {
        spSite.innerHTML = '<option value="">— Loading sites... —</option>';
        fetchSP({ op: 'sites', tenant_id: spTenant.value }).then(function (json) {
            spSite.innerHTML = '<option value="">— Select site —</option>';
            (json.sites || []).forEach(function (s) {
                var o = document.createElement('option');
                o.value = s.id;
                o.dataset.name = s.displayName;
                o.textContent = s.displayName;
                spSite.appendChild(o);
            });
        }).catch(function (e) { spSite.innerHTML = '<option value="">' + e.message + '</option>'; });
    }

    function loadDrives() {
        if (!spSite.value) { spDrive.disabled = true; return; }
        spDrive.disabled = true;
        spDrive.innerHTML = '<option value="">— Loading drives... —</option>';
        fetchSP({ op: 'drives', tenant_id: spTenant.value, site_id: spSite.value }).then(function (json) {
            spDrive.innerHTML = '<option value="">— Select drive —</option>';
            (json.drives || []).forEach(function (d) {
                var o = document.createElement('option');
                o.value = d.id;
                o.dataset.name = d.name;
                o.textContent = d.name + ' (' + (d.driveType || 'documentLibrary') + ')';
                spDrive.appendChild(o);
            });
            spDrive.disabled = false;
        });
    }

    function renderChildren(parentEl, items) {
        parentEl.innerHTML = '';
        if (!items.length) { parentEl.innerHTML = '<small class="text-muted">— empty —</small>'; return; }
        var ul = document.createElement('ul');
        ul.style.listStyle = 'none'; ul.style.paddingLeft = '1rem';
        items.forEach(function (it) {
            var li = document.createElement('li'); li.style.marginBottom = '4px';
            if (it.isFolder) {
                var caret = document.createElement('span'); caret.textContent = '▶';
                caret.style.cursor = 'pointer'; caret.style.marginRight = '6px';
                var label = document.createElement('span');
                label.innerHTML = '<i class="fas fa-folder text-warning me-1"></i><strong>' + escapeHtml(it.name) + '</strong>';
                var childWrap = document.createElement('div'); childWrap.style.display = 'none'; childWrap.style.paddingLeft = '12px';
                caret.addEventListener('click', function () {
                    if (childWrap.style.display === 'none') {
                        if (!childWrap.dataset.loaded) {
                            childWrap.innerHTML = '<small class="text-muted">loading...</small>';
                            fetchSP({ op: 'children', tenant_id: spTenant.value, drive_id: spDrive.value, item_id: it.id }).then(function (json) {
                                renderChildren(childWrap, json.items || []);
                                childWrap.dataset.loaded = '1';
                            });
                        }
                        childWrap.style.display = ''; caret.textContent = '▼';
                    } else { childWrap.style.display = 'none'; caret.textContent = '▶'; }
                });
                li.appendChild(caret); li.appendChild(label); li.appendChild(childWrap);
            } else {
                var cb = document.createElement('input');
                cb.type = 'checkbox'; cb.name = 'sp_item_ids[]'; cb.value = it.id; cb.className = 'sp-item-cb me-2';
                cb.dataset.name = it.name; cb.dataset.size = it.size; cb.dataset.etag = it.etag || '';
                var lbl = document.createElement('label');
                lbl.innerHTML = '<i class="fas fa-file text-secondary me-1"></i>' + escapeHtml(it.name) +
                    ' <small class="text-muted">(' + formatBytes(it.size) + ')</small>';
                li.appendChild(cb); li.appendChild(lbl);
            }
            ul.appendChild(li);
        });
        parentEl.appendChild(ul);
    }
    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); }
    function formatBytes(n) { if (!n) return '0 B'; var u = ['B','KB','MB','GB']; var i = Math.floor(Math.log(n)/Math.log(1024)); return (n/Math.pow(1024,i)).toFixed(1) + ' ' + u[i]; }

    spTenant.addEventListener('change', loadSites);
    spSite.addEventListener('change', function () { spSiteIdHidden.value = spSite.value; loadDrives(); });
    spDrive.addEventListener('change', function () {
        if (!spDrive.value) return;
        spDriveIdHidden.value = spDrive.value;
        spDriveNameHidden.value = spDrive.options[spDrive.selectedIndex].dataset.name || '';
        spTree.innerHTML = '<small class="text-muted">loading...</small>';
        fetchSP({ op: 'children', tenant_id: spTenant.value, drive_id: spDrive.value, item_id: 'root' }).then(function (json) {
            renderChildren(spTree, json.items || []);
        });
    });
    spTree.addEventListener('change', function () {
        var n = spTree.querySelectorAll('input.sp-item-cb:checked').length;
        spSelectedCount.textContent = n + ' file(s) selected';
        spImportBtn.disabled = (n === 0);
    });

    loadSites();
});
</script>

<script>
// #1328 - resumable chunked uploader (custom protocol, no external dependency).
(function () {
    var fileEl   = document.getElementById('rz-file');
    var startBtn = document.getElementById('rz-start');
    if (!fileEl || !startBtn) { return; }
    var wrap     = document.getElementById('rz-progress-wrap');
    var bar      = document.getElementById('rz-bar');
    var pctEl    = document.getElementById('rz-pct');
    var statusEl = document.getElementById('rz-status');
    var resultEl = document.getElementById('rz-result');

    var sessionId = {{ (int) ($session->id ?? 0) }};
    var meta      = document.querySelector('meta[name="csrf-token"]');
    var token     = meta ? meta.getAttribute('content') : '';
    var base      = '{{ url('ingest') }}/' + sessionId + '/chunk';
    var CHUNK     = 8 * 1024 * 1024; // 8 MB parts

    fileEl.addEventListener('change', function () {
        startBtn.disabled = !fileEl.files.length;
        resultEl.innerHTML = '';
    });

    function uploadId(file) {
        var key = 'rz_' + sessionId + '_' + file.name + '_' + file.size;
        var id = localStorage.getItem(key);
        if (!id) {
            id = 'u' + Date.now().toString(36) + Math.floor(Math.random() * 1e9).toString(36);
            localStorage.setItem(key, id);
        }
        return { id: id, key: key };
    }

    function post(url, fd) {
        return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }, body: fd });
    }

    async function postRetry(makeFd, tries) {
        for (var t = 0; t < tries; t++) {
            try { var r = await post(base, makeFd()); if (r.ok) { return; } } catch (e) {}
            await new Promise(function (res) { setTimeout(res, 800 * (t + 1)); });
        }
        throw new Error('A chunk failed to upload.');
    }

    function setPct(p) { var v = Math.round(p * 100); bar.style.width = v + '%'; pctEl.textContent = v + '%'; }

    startBtn.addEventListener('click', async function () {
        var file = fileEl.files[0];
        if (!file) { return; }
        startBtn.disabled = true; fileEl.disabled = true; wrap.style.display = ''; resultEl.innerHTML = '';
        bar.className = 'progress-bar progress-bar-striped progress-bar-animated';

        var u = uploadId(file);
        var total = Math.max(1, Math.ceil(file.size / CHUNK));
        var done = [];
        try {
            var s = await fetch(base + '/status?upload_id=' + encodeURIComponent(u.id), { headers: { 'Accept': 'application/json' } });
            if (s.ok) { var sj = await s.json(); done = sj.received || []; }
        } catch (e) {}

        try {
            for (var i = 0; i < total; i++) {
                if (done.indexOf(i) !== -1) { setPct((i + 1) / total); continue; }
                statusEl.textContent = 'Uploading chunk ' + (i + 1) + ' of ' + total + '...';
                var blob = file.slice(i * CHUNK, Math.min((i + 1) * CHUNK, file.size));
                var idx = i;
                await postRetry(function () {
                    var fd = new FormData();
                    fd.append('upload_id', u.id);
                    fd.append('chunk_index', idx);
                    fd.append('total_chunks', total);
                    fd.append('chunk', blob, file.name + '.part' + idx);
                    return fd;
                }, 3);
                setPct((i + 1) / total);
            }
            statusEl.textContent = 'Assembling and ingesting...';
            var fd = new FormData();
            fd.append('upload_id', u.id);
            fd.append('file_name', file.name);
            fd.append('total_chunks', total);
            var cr = await post(base + '/complete', fd);
            var cj = await cr.json();
            if (cr.ok && cj.ok) {
                localStorage.removeItem(u.key);
                bar.className = 'progress-bar bg-success';
                statusEl.textContent = 'Done';
                resultEl.innerHTML = '<div class="alert alert-success mt-2">' +
                    'File uploaded and ingested (record #' + (cj.io_id || '?') + ').</div>';
            } else {
                throw new Error((cj && cj.error) ? cj.error : 'The upload could not be completed.');
            }
        } catch (e) {
            bar.className = 'progress-bar bg-danger';
            statusEl.textContent = 'Failed';
            resultEl.innerHTML = '<div class="alert alert-danger mt-2">' + (e.message || 'Upload failed.') +
                ' Select the same file and start again to resume.</div>';
            fileEl.disabled = false; startBtn.disabled = false;
        }
    });
})();
</script>
@endsection
