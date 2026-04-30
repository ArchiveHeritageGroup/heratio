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
@endphp

<h1>{{ __('Upload Files') }}</h1>

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('ingest.index') }}">Ingestion Manager</a></li>
        <li class="breadcrumb-item">{{ $session->title ?? ('Session #' . ($session->id ?? '')) }}</li>
        <li class="breadcrumb-item active" aria-current="page">Upload</li>
    </ol>
</nav>

{{-- Wizard Progress --}}
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
                            <i class="fas fa-file-{{ ($f->file_type ?? '') === 'csv' ? 'csv' : (($f->file_type ?? '') === 'zip' ? 'archive' : 'code') }} me-1"></i>
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
    if (!dropZone || !fileInput) return;

    ['dragenter', 'dragover'].forEach(function(ev) {
        dropZone.addEventListener(ev, function(e) {
            e.preventDefault();
            dropZone.classList.add('border-primary', 'bg-light');
        });
    });

    ['dragleave', 'drop'].forEach(function(ev) {
        dropZone.addEventListener(ev, function(e) {
            e.preventDefault();
            dropZone.classList.remove('border-primary', 'bg-light');
        });
    });

    dropZone.addEventListener('drop', function(e) {
        if (e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            showFileInfo(e.dataTransfer.files[0]);
        }
    });

    dropZone.addEventListener('click', function() {
        fileInput.click();
    });

    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            showFileInfo(this.files[0]);
        }
    });

    function showFileInfo(file) {
        var size = (file.size / 1024 / 1024).toFixed(2);
        fileInfo.style.display = '';
        fileInfo.innerHTML = '<strong>' + file.name + '</strong> (' + size + ' MB)';
    }
});
</script>
@endsection
