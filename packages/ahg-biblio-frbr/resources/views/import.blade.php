{{-- ahg-biblio-frbr/import.blade.php — FRBR import UI --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">FRBR Import</h1>
    <span class="badge bg-success">Import</span>
  </div>
  <p class="text-muted small mb-4">
    Upload an FRBR XML document to import works, expressions, and items into the catalogue.
    Existing works are updated; new works are inserted.
  </p>

  <div class="row">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <i class="bi bi-box-arrow-down-left me-1"></i> FRBR Document Upload
        </div>
        <div class="card-body">
          @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
          @endif

          <form method="POST" action="{{ route('frbr.import-run') }}" enctype="multipart/form-data">
            @csrf

            <div class="mb-3">
              <label for="frbr_file" class="form-label">FRBR XML File</label>
              <input type="file" name="frbr_file" id="frbr_file" class="form-control"
                accept=".xml" required>
              <div class="form-text">
                Maximum file size: 10 MB. Accepts <code>.xml</code> files with FRBR namespace.
              </div>
              @error('frbr_file')
                <div class="text-danger small mt-1">{{ $message }}</div>
              @enderror
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-success">
                <i class="bi bi-upload me-1"></i> Import FRBR
              </button>
              <a href="{{ route('frbr.index') }}" class="btn btn-outline-secondary">Back to Dashboard</a>
            </div>
          </form>
        </div>
      </div>

      {{-- Validation before import shortcut --}}
      <div class="card mt-4">
        <div class="card-header">
          <i class="bi bi-check-circle me-1"></i> Validate First
        </div>
        <div class="card-body">
          <p class="small text-muted mb-2">
            Validate your FRBR document before importing to catch structural errors.
          </p>
          <a href="{{ route('frbr.validate') }}" class="btn btn-outline-warning btn-sm">
            Go to Validator
          </a>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">Expected FRBR Structure</div>
        <div class="card-body small">
          <p class="mb-2">Imported documents should use the FRBRer namespace:</p>
          <pre class="bg-light p-2 small mb-2" style="font-size:0.75rem;">xmlns:frbr="http://iflastandards.info/
ns/fr/frbr/frbrer/"</pre>
          <p class="mb-2">The importer handles these elements:</p>
          <ul class="mb-0 text-muted">
            <li><code>frbr:Work</code> &rarr; <code>library_biblio_work</code></li>
            <li><code>frbr:Expression</code> &rarr; <code>library_biblio_instance</code></li>
            <li><code>frbr:Item</code> &rarr; <code>library_biblio_item</code></li>
          </ul>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
