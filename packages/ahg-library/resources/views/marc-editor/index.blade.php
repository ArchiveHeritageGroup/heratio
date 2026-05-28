@extends('theme::layouts.1col')
@section('title', 'MARC Editor')

@section('content')
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-1">MARC Editor</h2>
            <p class="text-muted">Import MARC records in batch or edit existing library items in MARC format.</p>
        </div>
    </div>

    <div class="row g-4">
        {{-- Card 1: Import MARC Records --}}
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center p-5">
                    <div class="mb-4">
                        <span class="badge bg-primary rounded-circle p-3">
                            <i class="fas fa-file-import fa-2x"></i>
                        </span>
                    </div>
                    <h5 class="card-title">Import MARC Records</h5>
                    <p class="card-text text-muted">
                        Upload a MARCXML file to import one or more records into the library catalogue.
                        Preview before committing to verify field mapping.
                    </p>
                    <a href="{{ route('library.marc-import') }}" class="btn btn-primary mt-3">
                        <i class="fas fa-upload me-2"></i>Import MARCXML
                    </a>
                </div>
            </div>
        </div>

        {{-- Card 2: Edit Existing Records --}}
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <span class="badge bg-secondary rounded-circle p-3">
                            <i class="fas fa-edit fa-2x"></i>
                        </span>
                    </div>
                    <h5 class="card-title text-center">Edit Existing Records</h5>
                    <p class="card-text text-muted text-center mb-4">
                        Open a library item in the MARC editor to view and modify individual
                        MARC fields (245, 100, 264, 300, 500, 6XX, 856, etc.).
                    </p>

                    {{-- Pick a library item by ID --}}
                    <form method="GET" action="{{ route('library.marc-edit-redirect') }}" class="d-flex gap-2 align-items-end">
                        @csrf
                        <div class="flex-grow-1">
                            <label for="edit_item_id" class="form-label small fw-semibold">Library Item ID</label>
                            <input type="number" name="id" id="edit_item_id" class="form-control"
                                   placeholder="e.g. 123" min="1" required>
                        </div>
                        <button type="submit" class="btn btn-outline-secondary flex-shrink-0">
                            <i class="fas fa-pen me-1"></i>Edit
                        </button>
                    </form>

                    @error('id')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror

                    <hr class="my-4">

                    <div class="text-center">
                        <a href="{{ route('library.browse') }}" class="text-decoration-none small">
                            <i class="fas fa-list me-1"></i>Browse library catalogue
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick info --}}
    <div class="row mt-5">
        <div class="col-12">
            <div class="alert alert-info d-flex align-items-center mb-0" role="alert">
                <i class="fas fa-info-circle me-3 fa-lg"></i>
                <div>
                    <strong>Supported formats:</strong>
                    MARCXML (.xml, .marcxml) and MARC21 binary (.mrc).
                    Records are read from the archival description fields (information_object) linked to each library item.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection