@extends('theme::layouts.1col')
@section('title', 'MARC Editor')

@section('content')
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex align-items-center">
                <a href="{{ route('library.index') }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back to Library') }}"><i class="fas fa-arrow-left"></i></a>
                <h2 class="mb-1">MARC Editor</h2>
            </div>
            <p class="text-muted mt-1">Import MARC records in batch or edit existing library items in MARC format.</p>
        </div>
    </div>

    <div class="row g-4">
        {{-- Card 1: Import MARCXML --}}
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <span class="badge bg-primary rounded-circle p-3">
                            <i class="fas fa-file-import fa-2x"></i>
                        </span>
                    </div>
                    <h5 class="card-title">Import MARCXML</h5>
                    <p class="card-text text-muted small">
                        Upload a MARCXML file to batch-import one or more records.
                    </p>
                    <a href="{{ route('library.marc-import') }}" class="btn btn-primary mt-2">
                        <i class="fas fa-upload me-2"></i>Import MARCXML
                    </a>
                </div>
            </div>
        </div>

        {{-- Card 2: MARC Binary Import --}}
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <span class="badge bg-warning rounded-circle p-3 text-dark">
                            <i class="fas fa-file-code fa-2x"></i>
                        </span>
                    </div>
                    <h5 class="card-title">Import MARC Binary</h5>
                    <p class="card-text text-muted small">
                        Upload a MARC21 binary file (ISO 2709 / .mrc) to import records.
                    </p>
                    <a href="{{ route('library.marc-binary') }}" class="btn btn-warning mt-2">
                        <i class="fas fa-file me-2"></i>Import MARC Binary
                    </a>
                </div>
            </div>
        </div>

        {{-- Card 3: Edit Existing Records --}}
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <span class="badge bg-secondary rounded-circle p-3">
                            <i class="fas fa-edit fa-2x"></i>
                        </span>
                    </div>
                    <h5 class="card-title text-center">Edit Existing Records</h5>
                    <p class="card-text text-muted small text-center mb-3">
                        Open a library item and edit MARC fields in place.
                    </p>

                    <form method="GET" action="{{ route('library.marc-edit-redirect') }}" class="d-flex gap-2 align-items-end">
                        <div class="flex-grow-1">
                            <label for="edit_item_id" class="form-label small fw-semibold">Item ID</label>
                            <input type="number" name="id" id="edit_item_id" class="form-control"
                                   placeholder="e.g. 123" min="1" required>
                        </div>
                        <button type="submit" class="btn btn-outline-secondary flex-shrink-0">
                            <i class="fas fa-pen"></i>
                        </button>
                    </form>

                    @error('id')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="alert alert-info d-flex align-items-center" role="alert">
                <i class="fas fa-info-circle me-3 fa-lg"></i>
                <div>
                    <strong>Supported formats:</strong>
                    MARCXML (.xml, .marcxml) and MARC21 binary (.mrc).
                    Records are read from the archival description fields linked to each library item.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
