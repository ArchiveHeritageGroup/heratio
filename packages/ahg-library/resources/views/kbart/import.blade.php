@extends('theme::layouts.1col')
@section('title', 'Import KBART')

@section('content')
<div class="container py-4">

    {{-- Header breadcrumb --}}
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('library.kbart') }}" class="btn btn-light btn-sm">
            <i class="fas fa-arrow-left me-1"></i>KBART
        </a>
        <h1 class="h4 mb-0">
            <i class="fas fa-upload me-2"></i>{{ __('Import KBART') }}
        </h1>
    </div>

    {{-- Flash-level alerts (success / error from redirects) --}}
    @if(session('success'))
        <div class="alert alert-success d-flex align-items-center" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
        </div>
    @endif

    {{-- Commit error shown by the controller re-rendering this page --}}
    @if(!empty($commit_error))
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>{!! nl2br(e($commit_error)) !!}
        </div>
    @endif
    @if(!empty($commit_success))
        <div class="alert alert-success d-flex align-items-center" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ $commit_success }}
        </div>
    @endif

    {{-- No preview: show the upload form --}}
    @if(!$preview_data || empty($preview_data))
        <div class="row">
            <div class="col-lg-8">

                <div class="card mb-4">
                    <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
                        <h6 class="mb-0"><i class="fas fa-upload me-2"></i>Upload KBART File</h6>
                    </div>
                    <div class="card-body">

                        <form method="POST"
                              action="{{ route('library.kbart-preview') }}"
                              enctype="multipart/form-data">
                            @csrf

                            <p class="text-muted small mb-3">
                                Upload a NISO KBART tab-separated values (.tsv) file to import or
                                update serial titles and catalogue records. You will see a preview
                                before anything is written to the database.
                            </p>

                            <div class="mb-3">
                                <label for="kbart_file" class="form-label fw-semibold">
                                    KBART file <span class="text-danger">*</span>
                                </label>
                                <input type="file"
                                       name="kbart_file"
                                       id="kbart_file"
                                       class="form-control"
                                       accept=".txt,.tsv,.csv"
                                       required>
                                <div class="form-text">
                                    Maximum 50 MB. Accepts .tsv, .txt, or .csv with tab delimiters.
                                </div>
                                @error('kbart_file')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Format notes</label>
                                <div class="card bg-light">
                                    <div class="card-body py-2 px-3 mb-0">
                                        <small class="text-muted">
                                            The file must include a header row with NISO KBART column names.
                                            <br>Required columns: <code>publication_title</code> (always required);
                                            at least one identifier: <code>isbn</code>, <code>print_issn</code>,
                                            <code>eissn</code>, or <code>proprietary_id</code>.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-eye me-1"></i>Preview Import
                            </button>
                        </form>

                    </div>
                </div>

                <div class="card">
                    <div class="card-body text-center">
                        <p class="text-muted small mb-2">
                            No KBART file? Download a blank template to fill in manually.
                        </p>
                        <a href="{{ route('library.kbart-template') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-file-download me-1"></i>Download KBART Template
                        </a>
                    </div>
                </div>

            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">KBART Import Help</h6>
                    </div>
                    <div class="card-body pb-0">
                        <ul class="small mb-0 text-muted" style="padding-left: 1.2em;">
                            <li>File must be tab-separated (.tsv)</li>
                            <li>First row must be the NISO KBART header</li>
                            <li><code>publication_title</code> is required per row</li>
                            <li>At least one identifier required: ISBN, ISSN, eISSN, or proprietary_id</li>
                            <li>Duplicates (same ISBN) are skipped automatically</li>
                            <li>Serials detected by ISSN or <code>publication_type</code></li>
                            <li>Preview shows up to 20 rows before you commit</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Preview shown after successful file parse --}}
    @if($preview_data && !empty($preview_data))
        <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            Previewing <strong>{{ $record_count }}</strong> row(s).
            Review carefully before committing. Rows with errors will not be imported.
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-list-check me-2"></i>Preview</h6>
                            <span class="badge bg-light text-dark">{{ $record_count }} rows</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped table-sm mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 3rem;">#</th>
                                    <th style="width: 5rem;">Status</th>
                                    <th>Publication Title</th>
                                    <th>ISBN</th>
                                    <th>Print ISSN</th>
                                    <th>eISSN</th>
                                    <th>Errors</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($preview_data as $row)
                                    <tr>
                                        <td class="text-center text-muted">{{ $row['row_number'] }}</td>
                                        <td class="text-center">
                                            @if($row['is_valid'])
                                                <span class="badge bg-success"><i class="fas fa-check"></i> OK</span>
                                            @else
                                                <span class="badge bg-danger"><i class="fas fa-times"></i> Error</span>
                                            @endif
                                        </td>
                                        <td>{{ e($row['publication_title']) }}</td>
                                        <td>
                                            @if($row['isbn'])
                                                <code>{{ e($row['isbn']) }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row['issn'])
                                                <code>{{ e($row['issn']) }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row['eissn'])
                                                <code>{{ e($row['eissn']) }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row['errors'])
                                                <ul class="text-danger small mb-0" style="padding-left: 1.2em;">
                                                    @foreach($row['errors'] as $error)
                                                        <li>{{ e($error) }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Commit / Cancel actions --}}
        <div class="row g-3">
            <div class="col-sm-6">
                <form method="POST" action="{{ route('library.kbart-commit') }}">
                    @csrf
                    <input type="hidden" name="raw_tsv" value="{{ e($raw_tsv ?? '') }}">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-database me-1"></i>Commit Import
                    </button>
                </form>
            </div>
            <div class="col-sm-6">
                <a href="{{ route('library.kbart-import') }}"
                   class="btn btn-outline-secondary w-100">
                    <i class="fas fa-arrow-left me-1"></i>Cancel & Upload New File
                </a>
            </div>
        </div>
    @endif

</div>
@endsection
