@extends('theme::layouts.1col')

@section('title', 'CSV Export')

@section('content')
<div class="container-fluid py-4">
    <h1>{{ __('CSV Export') }}</h1>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">{{ __('Export Archival Descriptions to CSV') }}</h5>
        </div>
        <div class="card-body">

            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                {{ __('Export archival descriptions in CSV format compatible with AtoM import.') }}
            </div>

            <form action="{{ route('export.csv') }}" method="post">
                @csrf

                <div class="mb-3">
                    <label class="form-label">{{ __('Repository') }}</label>
                    <select name="repository_id" class="form-select">
                        <option value="">{{ __('All repositories') }}</option>
                        @foreach($repositories as $repo)
                            <option value="{{ $repo->id }}">{{ e($repo->name) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('Level of Description') }}</label>
                    <select name="level_ids[]" class="form-select" multiple size="5">
                        @foreach($levels as $level)
                            <option value="{{ $level->id }}">{{ e($level->name) }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">{{ __('Hold Ctrl/Cmd to select multiple. Leave empty for all levels.') }}</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('Parent Record Slug (Optional)') }}</label>
                    <input type="text" name="parent_slug" class="form-control" placeholder="{{ __('e.g. my-fonds-123') }}">
                    <small class="text-muted">{{ __('Export only descendants of this record.') }}</small>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="include_descendants" value="1" class="form-check-input" id="includeDescendants">
                    <label class="form-check-label" for="includeDescendants">
                        Include all descendants (not just direct children)
                    </label>
                </div>

                <hr>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('export.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>{{ __('Back') }}
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-download me-1"></i>{{ __('Export CSV') }}
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>
@endsection
