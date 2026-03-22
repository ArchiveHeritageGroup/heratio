{{-- Generate Finding Aid - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'reports'])@endsection
@section('title', 'Generate Finding Aid')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.reports') }}">Reports</a></li><li class="breadcrumb-item active">Finding Aid</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-sitemap text-primary me-2"></i>Generate Finding Aid</h1>
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">Finding Aid Configuration</div>
            <div class="card-body">
                <form method="POST">@csrf
                    <div class="mb-3"><label class="form-label">Collection / Fonds <span class="text-danger">*</span></label>
                        <select name="collection_id" class="form-select" required><option value="">-- Select --</option>@foreach($collections ?? [] as $c)<option value="{{ $c->id }}">{{ e($c->title ?? '') }}</option>@endforeach</select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6"><label class="form-label">Format</label><select name="format" class="form-select"><option value="ead">EAD (XML)</option><option value="pdf">PDF</option><option value="html">HTML</option></select></div>
                        <div class="col-md-6"><label class="form-label">Depth</label><select name="depth" class="form-select"><option value="full">Full (all levels)</option><option value="series">Series level</option><option value="file">File level</option></select></div>
                    </div>
                    <div class="form-check mb-2"><input type="checkbox" name="include_dao" class="form-check-input" id="includeDao" checked><label class="form-check-label" for="includeDao">Include digital object links</label></div>
                    <div class="form-check mb-2"><input type="checkbox" name="include_access" class="form-check-input" id="includeAccess" checked><label class="form-check-label" for="includeAccess">Include access points</label></div>
                    <div class="form-check mb-3"><input type="checkbox" name="include_notes" class="form-check-input" id="includeNotes"><label class="form-check-label" for="includeNotes">Include archival notes</label></div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-file-export me-1"></i>Generate</button>
                    <a href="{{ route('research.reports') }}" class="btn atom-btn-white">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">About Finding Aids</h6></div>
            <div class="card-body small text-muted">
                A finding aid is a document that describes a collection of records. It helps researchers locate relevant materials by providing hierarchical descriptions, access points, and administrative metadata.
            </div>
        </div>
    </div>
</div>
@endsection