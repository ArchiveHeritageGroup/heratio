{{-- ahg-biblio-bf/import.blade.php — Import BIBFRAME RDF --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h4 mb-0"><i class="bi bi-box-arrow-down-left"></i> BIBFRAME Import</h1>
      <p class="small text-muted mb-0">Import a BIBFRAME RDF document into the catalogue</p>
    </div>
    <a href="{{ route('bibframe.index') }}" class="btn btn-outline-secondary btn-sm">&larr; Back</a>
  </div>

  <form method="post" action="{{ route('bibframe.import-run') }}" enctype="multipart/form-data">
    @csrf

    <div class="card mb-3">
      <div class="card-header">Upload BIBFRAME document</div>
      <div class="card-body">
        <div class="mb-3">
          <label for="rdf_file" class="form-label">RDF/XML, .rdf, or .ttl file (max 10 MB)</label>
          <input class="form-control" type="file" name="rdf_file" id="rdf_file" required accept=".xml,.rdf,.ttl">
          @error('rdf_file')
            <div class="text-danger small mt-1">{{ $message }}</div>
          @enderror
        </div>
        <p class="small text-muted">
          Supported formats: BIBFRAME 2.0 XML/RDF, Turtle. The importer will
          upsert Works, Instances, and Items it finds in the document.
          Records that already exist (matched by title for Works) are updated.
        </p>
      </div>
    </div>

    <button type="submit" class="btn btn-success">
      <i class="bi bi-upload"></i> Import
    </button>
  </form>

  @if(session('success'))
    <div class="alert alert-success mt-3">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger mt-3">{{ session('error') }}</div>
  @endif

</div>
@endsection
