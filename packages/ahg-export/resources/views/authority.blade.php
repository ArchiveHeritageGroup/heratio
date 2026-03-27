@extends('theme::layouts.1col')

@section('title', 'Export Authority Records')

@section('content')
<div class="container-fluid py-4">
    <h1><i class="bi bi-person-badge me-2"></i>Export Authority Records</h1>

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('homepage') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('export.index') }}">Export</a></li>
            <li class="breadcrumb-item active">Authority Records</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-download me-2"></i>Export Settings</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Export Format <span class="badge bg-secondary ms-1">Optional</span></label>
                                <select name="format" class="form-select">
                                    <option value="eac">EAC-CPF (XML)</option>
                                    <option value="csv">CSV</option>
                                </select>
                                <div class="form-text">EAC-CPF is the standard for authority record exchange.</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Entity Type <span class="badge bg-secondary ms-1">Optional</span></label>
                                <select name="entity_type_id" class="form-select">
                                    <option value="">All types</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Limit <span class="badge bg-secondary ms-1">Optional</span></label>
                                <select name="limit" class="form-select">
                                    <option value="0">No limit (all records)</option>
                                    <option value="100">100 records</option>
                                    <option value="500">500 records</option>
                                    <option value="1000">1,000 records</option>
                                    <option value="5000">5,000 records</option>
                                </select>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('export.index') }}" class="atom-btn-white">
                                <i class="bi bi-arrow-left me-1"></i>Back
                            </a>
                            <button type="submit" class="atom-btn-white">
                                <i class="bi bi-download me-1"></i>Export
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Statistics</h6>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Total Authority Records</dt>
                        <dd class="h3" style="color: var(--ahg-primary);">{{ number_format($authorityCount) }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-question-circle me-2"></i>About EAC-CPF</h6>
                </div>
                <div class="card-body small">
                    <p><strong>EAC-CPF</strong> (Encoded Archival Context - Corporate Bodies, Persons, and Families) is an XML standard for encoding contextual information about the creators of archival materials.</p>
                    <p class="mb-0">Use this format for exchanging authority records with other archival systems or for backup purposes.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
