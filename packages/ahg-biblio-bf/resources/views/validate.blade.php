{{-- ahg-biblio-bf/validate.blade.php — Validate BIBFRAME RDF --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h4 mb-0"><i class="bi bi-check-circle"></i> BIBFRAME Validation</h1>
      <p class="small text-muted mb-0">Check a BIBFRAME document for structural correctness</p>
    </div>
    <a href="{{ route('bibframe.index') }}" class="btn btn-outline-secondary btn-sm">&larr; Back</a>
  </div>

  <form method="post" action="{{ route('bibframe.validate-run') }}">
    @csrf

    <div class="card mb-3">
      <div class="card-header">Paste RDF content</div>
      <div class="card-body">
        <div class="mb-3">
          <label for="rdf_content" class="form-label">{{ __('RDF/XML or Turtle content') }}</label>
          <textarea class="form-control font-monospace" name="rdf_content" id="rdf_content"
                    rows="12"
                    placeholder="{{ __('<?xml version=') }}"1.0" encoding="UTF-8"?>
<rdf:RDF xmlns:bf="http://id.loc.gov/ontologies/bibframe/" ...">{{ old('rdf_content') }}</textarea>
        </div>
        <p class="small text-muted mb-2">Or upload a file instead:</p>
        <input class="form-control" type="file" name="rdf_file" accept=".xml,.rdf,.ttl">
      </div>
    </div>

    <button type="submit" class="btn btn-warning">
      <i class="bi bi-check2"></i> Validate
    </button>
  </form>

  {{-- Show results from a previous run --}}
  @php $result = session('validation_result'); @endphp
  @if($result)
    <div class="mt-4">
      <h2 class="h5">{{ __('Validation report') }}</h2>
      @if(empty($result['fatal']) && empty($result['errors']))
        <div class="alert alert-success">
          <i class="bi bi-check-circle"></i> Valid BIBFRAME 2.0 document.
        </div>
      @else
        <div class="alert alert-danger">
          <i class="bi bi-x-circle"></i> Document has errors:
          <ul class="mb-0">
            @foreach(($result['fatal'] ?? []) as $e)
              <li><strong>FATAL:</strong> {{ $e }}</li>
            @endforeach
            @foreach(($result['errors'] ?? []) as $e)
              <li>{{ $e }}</li>
            @endforeach
          </ul>
        </div>
      @endif
      @if(! empty($result['warnings']))
        <div class="alert alert-warning">
          Warnings:
          <ul class="mb-0">
            @foreach($result['warnings'] as $w)
              <li>{{ $w }}</li>
            @endforeach
          </ul>
        </div>
      @endif
    </div>
  @endif

</div>
@endsection
