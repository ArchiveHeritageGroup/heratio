@extends('theme::layouts.1col')
@section('title', 'Elasticsearch Integration')
@section('body-class', 'admin')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('display.index') }}">Display</a></li>
        <li class="breadcrumb-item active">Elasticsearch Reindex</li>
    </ol>
</nav>

<h1 class="h2 mb-4"><i class="fas fa-sync text-primary me-2"></i>Elasticsearch Integration</h1>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-database me-2"></i>Mapping Status</h5>
            </div>
            <div class="card-body">
                @if($hasMapping ?? false)
                <div class="alert alert-success mb-3">
                    <i class="fas fa-check-circle me-2"></i>
                    Display fields are present in the Elasticsearch mapping.
                </div>
                @else
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Display fields are NOT in the Elasticsearch mapping. You need to update the mapping first.
                </div>

                <form method="post" action="{{ route('displaySearch.updateMapping') }}">
                    @csrf
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-plus me-1"></i> Add Display Fields to Mapping
                    </button>
                </form>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-sync me-2"></i>Reindex Display Data</h5>
            </div>
            <div class="card-body">
                <p>This will update all existing Elasticsearch documents with display-specific fields (object type, profile, etc.).</p>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    This is a <strong>partial update</strong> - it won't re-index all data, just add/update display fields.
                    For a full re-index, use the standard search:populate task.
                </div>

                <form method="post" action="{{ route('displaySearch.reindex') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('Batch Size') }}</label>
                        <select name="batch_size" class="form-select" style="width: auto;">
                            <option value="50">50 (slower, less memory)</option>
                            <option value="100" selected>100 (recommended)</option>
                            <option value="200">200 (faster)</option>
                            <option value="500">500 (fastest, high memory)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" {{ !($hasMapping ?? false) ? 'disabled' : '' }}>
                        <i class="fas fa-sync me-1"></i> Start Reindex
                    </button>

                    @if(!($hasMapping ?? false))
                    <small class="text-muted d-block mt-2">Update mapping first before reindexing.</small>
                    @endif
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>About ES Integration</h5>
            </div>
            <div class="card-body">
                <p>The Display plugin adds these fields to Elasticsearch:</p>
                <ul class="small">
                    <li><code>display_object_type</code> - archive, museum, gallery, etc.</li>
                    <li><code>display_profile</code> - Default display profile</li>
                    <li><code>display_level_code</code> - Extended level code</li>
                    <li><code>display.*</code> - Nested display-specific fields</li>
                </ul>

                <hr>

                <p class="small text-muted mb-0">
                    <strong>When to reindex:</strong><br>
                    &bull; After bulk setting object types<br>
                    &bull; After changing display profiles<br>
                    &bull; After initial plugin installation
                </p>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-terminal me-2"></i>CLI Alternative</h5>
            </div>
            <div class="card-body">
                <p class="small">You can also reindex via command line:</p>
                <pre class="bg-dark text-light p-2 rounded small"><code>php artisan display:reindex --batch=100</code></pre>
            </div>
        </div>
    </div>
</div>
@endsection
