@extends('theme::layouts.1col')

@section('title', 'Export Data')

@section('content')
<div class="container-fluid py-4">
    <div class="mb-3">
        <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-header" style="background-color: var(--ahg-primary); color: #fff;">
            <h4 class="mb-0"><i class="fas fa-file-export me-2"></i>Export Data</h4>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-archive me-2"></i>Archival Descriptions</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Export archival descriptions in various formats.</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('export.archival', ['format' => 'ead']) }}" class="atom-btn-white">
                            <i class="fas fa-code me-1"></i>EAD 2002
                        </a>
                        <a href="{{ route('export.archival', ['format' => 'dc']) }}" class="atom-btn-white">
                            <i class="fas fa-file-code me-1"></i>Dublin Core
                        </a>
                        <a href="{{ route('export.archival', ['format' => 'csv']) }}" class="atom-btn-white">
                            <i class="fas fa-file-csv me-1"></i>CSV
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Authority Records</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Export authority records (people, organizations, families).</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('export.authority', ['format' => 'eac']) }}" class="atom-btn-white">
                            <i class="fas fa-code me-1"></i>EAC-CPF
                        </a>
                        <a href="{{ route('export.authority', ['format' => 'csv']) }}" class="atom-btn-white">
                            <i class="fas fa-file-csv me-1"></i>CSV
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-building me-2"></i>Repositories</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Export repository/institution records.</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('export.repository', ['format' => 'csv']) }}" class="atom-btn-white">
                            <i class="fas fa-file-csv me-1"></i>CSV
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Accessions</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Export accession records to CSV. Compatible with ingest wizard re-import.</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('export.accessionCsv') }}" class="atom-btn-white">
                            <i class="fas fa-file-csv me-1"></i>Accession CSV
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        For bulk exports, use the command line tools: <code>php artisan export:bulk</code>
    </div>
</div>
@endsection
