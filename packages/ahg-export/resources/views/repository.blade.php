@extends('ahg-theme-b5::layout')

@section('title', 'Export Repositories')

@section('content')
<div class="container-fluid py-4">
    <h1><i class="bi bi-building me-2"></i>Export Repositories</h1>

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('homepage') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('export.index') }}">Export</a></li>
            <li class="breadcrumb-item active">Repositories</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-download me-2"></i>Export Settings</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Export Format</label>
                                <select name="format" class="form-select">
                                    <option value="csv">CSV</option>
                                </select>
                                <div class="form-text">CSV format includes all repository fields and contact information.</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Limit</label>
                                <select name="limit" class="form-select">
                                    <option value="0">No limit (all records)</option>
                                    <option value="50">50 records</option>
                                    <option value="100">100 records</option>
                                    <option value="500">500 records</option>
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
                        <dt>Total Repositories</dt>
                        <dd class="h3 text-info">{{ number_format($repositoryCount) }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>Exported Fields</h6>
                </div>
                <div class="card-body small">
                    <ul class="mb-0">
                        <li>Name, Identifier, History</li>
                        <li>Collecting Policies, Holdings</li>
                        <li>Opening Times, Access Conditions</li>
                        <li>Contact Information (address, phone, email)</li>
                        <li>GPS Coordinates (if available)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
