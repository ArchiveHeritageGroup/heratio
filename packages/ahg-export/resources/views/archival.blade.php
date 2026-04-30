@extends('theme::layouts.1col')

@section('title', 'Export Archival Descriptions')

@section('content')
<div class="container-fluid py-4">
    <h1><i class="bi bi-archive me-2"></i>Export Archival Descriptions</h1>

    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('homepage') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('export.index') }}">Export</a></li>
            <li class="breadcrumb-item active">Archival Descriptions</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header" style="background-color: var(--ahg-primary); color: #fff;">
                    <h5 class="mb-0"><i class="bi bi-download me-2"></i>Export Settings</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('export.archival') }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Export Format <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                                <select name="format" class="form-select">
                                    <option value="csv" {{ ($format ?? 'csv') === 'csv' ? 'selected' : '' }}>{{ __('CSV (Bulk Export)') }}</option>
                                    <option value="ead" {{ ($format ?? '') === 'ead' ? 'selected' : '' }}>{{ __('EAD 2002 (Single Record)') }}</option>
                                    <option value="dc" {{ ($format ?? '') === 'dc' ? 'selected' : '' }}>{{ __('Dublin Core (Single Record)') }}</option>
                                </select>
                                <div class="form-text">CSV supports bulk export. EAD/DC require selecting a specific record.</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Repository <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                                <select name="repository_id" class="form-select">
                                    <option value="">{{ __('All repositories') }}</option>
                                    @foreach($repositories as $repo)
                                        <option value="{{ $repo->id }}">{{ e($repo->name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Limit <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                                <select name="limit" class="form-select">
                                    <option value="0">{{ __('No limit (all records)') }}</option>
                                    <option value="100">100 records</option>
                                    <option value="500">500 records</option>
                                    <option value="1000">1,000 records</option>
                                    <option value="5000">5,000 records</option>
                                    <option value="10000">10,000 records</option>
                                </select>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('export.index') }}" class="atom-btn-white">
                                <i class="bi bi-arrow-left me-1"></i>{{ __('Back') }}
                            </a>
                            <button type="submit" class="atom-btn-white">
                                <i class="bi bi-download me-1"></i>{{ __('Export') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-question-circle me-2"></i>Export Tips</h6>
                </div>
                <div class="card-body small">
                    <p><strong>{{ __('CSV Export:') }}</strong> Best for bulk data extraction and spreadsheet analysis.</p>
                    <p><strong>{{ __('EAD/DC Export:') }}</strong> Navigate to a specific fonds or collection, then use Export from the "More" menu.</p>
                    <p class="mb-0"><strong>{{ __('Large exports:') }}</strong> Consider using filters to reduce the dataset size.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
