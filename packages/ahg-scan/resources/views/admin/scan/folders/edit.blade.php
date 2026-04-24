@extends('theme::layouts.1col')
@section('title', $folder->id ? 'Edit watched folder' : 'New watched folder')

@section('content')
<h1>{{ $folder->id ? 'Edit watched folder' : 'New watched folder' }}</h1>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('scan.dashboard') }}">Scan</a></li>
        <li class="breadcrumb-item"><a href="{{ route('scan.folders.index') }}">Watched folders</a></li>
        <li class="breadcrumb-item active">{{ $folder->id ? $folder->label : 'New' }}</li>
    </ol>
</nav>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ $folder->id ? route('scan.folders.update', $folder->id) : route('scan.folders.store') }}">
    @csrf
    @if($folder->id) @method('PUT') @endif

    <div class="card mb-3">
        <div class="card-header"><strong>Folder</strong></div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Code <span class="text-danger">*</span></label>
                <input type="text" name="code" value="{{ old('code', $folder->code) }}"
                       class="form-control" {{ $folder->id ? 'readonly' : '' }}
                       pattern="[a-z0-9][a-z0-9_-]*" maxlength="64" required>
                <div class="form-text">Lowercase slug. Used as <code>source_ref</code> on the ingest session.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Label <span class="text-danger">*</span></label>
                <input type="text" name="label" value="{{ old('label', $folder->label) }}" class="form-control" maxlength="255" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Absolute path <span class="text-danger">*</span></label>
                <input type="text" name="path" value="{{ old('path', $folder->path) }}" class="form-control" maxlength="1024" required>
                <div class="form-text">e.g. <code>/mnt/nas/heratio/scan_inbox/archive-main</code> — must be readable by the Heratio user.</div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Layout</label>
                    <select name="layout" class="form-select">
                        <option value="path" {{ old('layout', $folder->layout) === 'path' ? 'selected' : '' }}>Path as destination</option>
                        <option value="flat-sidecar" {{ old('layout', $folder->layout) === 'flat-sidecar' ? 'selected' : '' }}>Flat files + XML sidecar</option>
                    </select>
                    <div class="form-text">
                        Path: <code>&lt;folder&gt;/&lt;parent-slug&gt;/&lt;identifier&gt;/file.tiff</code><br>
                        Flat-sidecar: <code>&lt;folder&gt;/ARC-2026-0001.tiff</code> + <code>&lt;folder&gt;/ARC-2026-0001.xml</code>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Quiet period (seconds)</label>
                    <input type="number" name="min_quiet_seconds" value="{{ old('min_quiet_seconds', $folder->min_quiet_seconds) }}" min="1" max="3600" class="form-control">
                    <div class="form-text">File must be idle for this long before ingest.</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Enabled</label>
                    <div class="form-check form-switch mt-2">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" value="1" class="form-check-input" {{ old('enabled', $folder->enabled) ? 'checked' : '' }}>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Destination + descriptive standard</strong></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Sector</label>
                    <select name="sector" class="form-select">
                        @foreach(['archive','library','gallery','museum'] as $s)
                            <option value="{{ $s }}" {{ old('sector', $folder->sector ?? 'archive') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Standard</label>
                    <input type="text" name="standard" value="{{ old('standard', $folder->standard ?? 'isadg') }}" class="form-control" maxlength="32">
                    <div class="form-text">e.g. isadg, rad, dacs, marc21, mods, lido, spectrum, dwc</div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Default parent (fallback if path doesn't resolve)</label>
                    <select name="parent_id" class="form-select">
                        <option value="">— None (require path-resolution) —</option>
                        @foreach($parents as $p)
                            <option value="{{ $p['id'] }}" {{ (int) old('parent_id', $folder->parent_id ?? 0) === (int) $p['id'] ? 'selected' : '' }}>{{ $p['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Repository</label>
                    <select name="repository_id" class="form-select">
                        <option value="">— None —</option>
                        @foreach($repositories as $r)
                            <option value="{{ $r['id'] }}" {{ (int) old('repository_id', $folder->repository_id ?? 0) === (int) $r['id'] ? 'selected' : '' }}>{{ $r['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Processing</strong></div>
        <div class="card-body">
            <div class="form-check form-switch mb-3">
                <input type="hidden" name="auto_commit" value="0">
                <input type="checkbox" name="auto_commit" value="1" class="form-check-input" id="ac" {{ old('auto_commit', $folder->auto_commit ?? 1) ? 'checked' : '' }}>
                <label class="form-check-label" for="ac"><strong>Auto-commit</strong> — process files automatically without human approval (default on)</label>
            </div>

            @if($folder->id && !empty($folder->ingest_session_id ?? null))
                <div class="alert alert-info d-flex align-items-center justify-content-between mb-0">
                    <div>
                        <strong>Derivatives, virus scan, OCR, SIP/AIP/DIP</strong> are configured on this folder's ingest session.
                        The scanner pipeline honours those settings.
                    </div>
                    <a href="{{ url('/ingest/configure/' . $folder->ingest_session_id) }}" class="btn btn-sm btn-outline-primary ms-3">
                        <i class="fas fa-sliders-h me-1"></i>Configure processing
                    </a>
                </div>
            @else
                <div class="alert alert-warning mb-0">
                    Derivative and processing options (thumbnails, reference images, virus scan, OCR, SIP/AIP/DIP packaging)
                    will be configurable in the Ingest wizard after this folder is created — a persistent ingest session is
                    created on save.
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Disposition</strong></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">On success</label>
                    <select name="disposition_success" class="form-select">
                        <option value="move" {{ old('disposition_success', $folder->disposition_success) === 'move' ? 'selected' : '' }}>Move to archive folder</option>
                        <option value="leave" {{ old('disposition_success', $folder->disposition_success) === 'leave' ? 'selected' : '' }}>Leave in place</option>
                        <option value="delete" {{ old('disposition_success', $folder->disposition_success) === 'delete' ? 'selected' : '' }}>Delete (not recommended)</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">On failure</label>
                    <select name="disposition_failure" class="form-select">
                        <option value="quarantine" {{ old('disposition_failure', $folder->disposition_failure) === 'quarantine' ? 'selected' : '' }}>Move to quarantine</option>
                        <option value="leave" {{ old('disposition_failure', $folder->disposition_failure) === 'leave' ? 'selected' : '' }}>Leave in place</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('scan.folders.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i>{{ $folder->id ? 'Save' : 'Create' }}
        </button>
    </div>
</form>
@endsection
