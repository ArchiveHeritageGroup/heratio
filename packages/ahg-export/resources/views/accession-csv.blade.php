@extends('ahg-theme-b5::layout')

@section('title', 'Accession CSV Export')

@section('content')
<div class="container-fluid py-4">
    <h1>Accession CSV Export</h1>

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Admin</a></li>
            <li class="breadcrumb-item"><a href="{{ route('export.index') }}">Export</a></li>
            <li class="breadcrumb-item active">Accession CSV</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-csv me-2"></i>Export Accession Records</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Export accession records to CSV format. The output matches the ingest accession import format for round-trip compatibility.</p>

                    <form method="post" action="{{ route('export.accessionCsv.post') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="repository_id" class="form-label">Repository (optional) <span class="badge bg-secondary ms-1">Optional</span></label>
                            <select class="form-select" id="repository_id" name="repository_id">
                                <option value="">-- All repositories --</option>
                                @foreach($repositories as $repo)
                                    <option value="{{ $repo->id }}">{{ e($repo->name) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date_from" class="form-label">Acquisition Date From <span class="badge bg-secondary ms-1">Optional</span></label>
                                <input type="date" class="form-control" id="date_from" name="date_from">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="date_to" class="form-label">Acquisition Date To <span class="badge bg-secondary ms-1">Optional</span></label>
                                <input type="date" class="form-control" id="date_to" name="date_to">
                            </div>
                        </div>

                        <button type="submit" class="atom-btn-white">
                            <i class="fas fa-download me-1"></i>Download CSV
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Summary</h5>
                </div>
                <div class="card-body">
                    <p><strong>Total accessions:</strong> {{ number_format($accessionCount) }}</p>
                    <hr>
                    <h6>Exported Columns</h6>
                    <small class="text-muted">
                        accessionNumber, title, acquisitionDate, sourceOfAcquisition,
                        locationInformation, receivedExtentUnits, scopeAndContent,
                        appraisal, archivalHistory, processingNotes,
                        acquisitionType, resourceType, processingStatus, processingPriority,
                        donorName, donorStreetAddress, donorCity, donorRegion,
                        donorCountry, donorPostalCode, donorTelephone, donorFax,
                        donorEmail, donorContactPerson, donorNote,
                        accessionEventTypes, accessionEventDates, accessionEventAgents,
                        alternativeIdentifiers, alternativeIdentifierNotes,
                        intakeNotes, intakePriority, culture
                    </small>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6>Re-import</h6>
                    <p class="text-muted mb-0">
                        The exported CSV can be re-imported using the Ingest wizard (Admin &gt; Ingest &gt; New &gt; Accessions) or the command:
                        <br><code>php artisan csv:accession-import filename.csv</code>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
