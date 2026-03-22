@extends('ahg-theme-b5::layout')

@section('title', 'CSV Export')

@section('content')
<div class="container-fluid py-4">
    <h1>CSV Export</h1>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Export Archival Descriptions to CSV</h5>
        </div>
        <div class="card-body">

            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Export archival descriptions in CSV format compatible with import.
            </div>

            <form action="{{ route('export.csv') }}" method="post">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Repository</label>
                    <select name="repository_id" class="form-select">
                        <option value="">All repositories</option>
                        @foreach($repositories as $repo)
                            <option value="{{ $repo->id }}">{{ e($repo->name) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Parent Record Slug (Optional)</label>
                    <input type="text" name="parent_slug" class="form-control" placeholder="e.g. my-fonds-123">
                    <small class="text-muted">Export only descendants of this record.</small>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="include_descendants" value="1" class="form-check-input" id="includeDescendants">
                    <label class="form-check-label" for="includeDescendants">
                        Include all descendants (not just direct children)
                    </label>
                </div>

                <hr>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('export.index') }}" class="atom-btn-white">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </a>
                    <button type="submit" class="atom-btn-white">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>
@endsection
