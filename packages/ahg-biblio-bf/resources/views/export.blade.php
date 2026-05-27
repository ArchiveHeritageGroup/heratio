{{-- ahg-biblio-bf/export.blade.php — Export BIBFRAME RDF --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h4 mb-0"><i class="bi bi-box-arrow-up-right"></i> BIBFRAME Export</h1>
      <p class="small text-muted mb-0">Convert catalogue works to BIBFRAME 2.0 RDF</p>
    </div>
    <a href="{{ route('bibframe.index') }}" class="btn btn-outline-secondary btn-sm">
      &larr; Back
    </a>
  </div>

  <form method="post" action="{{ route('bibframe.export-run') }}">
    @csrf

    <div class="card mb-3">
      <div class="card-header">Select works to export</div>
      <div class="card-body">
        <div class="mb-3">
          <label for="work_id" class="form-label">Single work</label>
          <select class="form-select" name="work_id" id="work_id">
            <option value="">— Select a work —</option>
            @foreach($works as $w)
              <option value="{{ $w->id }}">{{ $w->title }} ({{ $w->author ?? 'no author' }})</option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label for="format" class="form-label">RDF Serialisation</label>
          <select class="form-select" name="format" id="format">
            <option value="xml">XML (application/rdf+xml)</option>
            <option value="turtle">Turtle (*.ttl)</option>
            <option value="ntriples">N-Triples</option>
            <option value="json-ld">JSON-LD (*.jsonld)</option>
          </select>
        </div>

        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="batch" id="batch" value="1">
          <label class="form-check-label" for="batch">
            Batch mode — return all works as JSON
          </label>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">
      <i class="bi bi-download"></i> Export
    </button>
  </form>

  @if(session('info'))
    <div class="alert alert-info mt-3">{{ session('info') }}</div>
  @endif

</div>
@endsection
