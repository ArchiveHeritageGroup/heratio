@extends('theme::layouts.1col')
@section('title', 'KBART Knowledge Base')

@section('content')
<div class="container py-4">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div class="d-flex align-items-start">
            <a href="{{ route('library.index') }}" class="btn btn-outline-secondary btn-sm me-3 mt-1" title="{{ __('Back to Library') }}"><i class="fas fa-arrow-left"></i></a>
            <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-table me-2"></i>{{ __('KBART Knowledge Base') }}
            </h1>
            <p class="text-muted small mb-0">
                NISO KBART (ANSI/NISO Z39.83) — import, export, and manage your library title knowledge base.
            </p>
            </div>
        </div>
        <span class="badge bg-info">KBART 1.2</span>
    </div>

    {{-- Feature cards --}}
    <div class="row g-4 mb-4">
        {{-- Export --}}
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <span class="badge bg-success rounded-circle p-3 mb-3">
                        <i class="fas fa-download fa-lg"></i>
                    </span>
                    <h5 class="card-title">Export</h5>
                    <p class="card-text text-muted small">
                        Download the full catalogue as a NISO KBART TSV file.
                        Filter by date range or limit the row count.
                    </p>
                    <a href="{{ route('library.kbart-export') }}" class="btn btn-outline-success btn-sm mt-2">
                        <i class="fas fa-download me-1"></i>Download TSV
                    </a>
                </div>
            </div>
        </div>

        {{-- Import --}}
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <span class="badge bg-primary rounded-circle p-3 mb-3">
                        <i class="fas fa-upload fa-lg"></i>
                    </span>
                    <h5 class="card-title">Import</h5>
                    <p class="card-text text-muted small">
                        Upload a KBART TSV file to bulk-import or update serial
                        titles and catalogue records. Preview before committing.
                    </p>
                    <a href="{{ route('library.kbart-import') }}" class="btn btn-outline-primary btn-sm mt-2">
                        <i class="fas fa-upload me-1"></i>Import TSV
                    </a>
                </div>
            </div>
        </div>

        {{-- Template --}}
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <span class="badge bg-secondary rounded-circle p-3 mb-3">
                        <i class="fas fa-file-alt fa-lg"></i>
                    </span>
                    <h5 class="card-title">Template</h5>
                    <p class="card-text text-muted small">
                        Download a blank KBART template with all NISO column headers.
                        Fill it in manually or use it as a data-entry guide.
                    </p>
                    <a href="{{ route('library.kbart-template') }}" class="btn btn-outline-secondary btn-sm mt-2">
                        <i class="fas fa-file-download me-1"></i>Get Template
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Format info --}}
    <div class="card">
        <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>NISO KBART Format Reference</h6>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-sm mb-0">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>KBART column</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Publication title</td>
                        <td><code>publication_title</code></td>
                        <td>Required</td>
                    </tr>
                    <tr>
                        <td>ISBN</td>
                        <td><code>isbn</code></td>
                        <td>Monographs</td>
                    </tr>
                    <tr>
                        <td>Print ISSN</td>
                        <td><code>print_issn</code></td>
                        <td>Serials</td>
                    </tr>
                    <tr>
                        <td>Electronic ISSN</td>
                        <td><code>eissn</code></td>
                        <td>Electronic serials</td>
                    </tr>
                    <tr>
                        <td>Publisher</td>
                        <td><code>publisher</code></td>
                        <td>Publisher name</td>
                    </tr>
                    <tr>
                        <td>Publication type</td>
                        <td><code>publication_type</code></td>
                        <td>Book, Journal, Serial, etc.</td>
                    </tr>
                    <tr>
                        <td>DOI</td>
                        <td><code>doi</code></td>
                        <td>Digital Object Identifier</td>
                    </tr>
                    <tr>
                        <td>Proprietary ID</td>
                        <td><code>proprietary_id</code></td>
                        <td>Barcode / system ID</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
