{{--
  SKOS Import — Heratio

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems

  This file is part of Heratio.
  Heratio is free software under the GNU AGPL v3.
--}}
@extends('theme::layouts.1col')
@section('title', 'Import SKOS')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/taxonomy/browse') }}">Taxonomies</a></li>
        <li class="breadcrumb-item active">Import SKOS</li>
    </ol>
</nav>

<h1>Import SKOS</h1>
<p class="text-muted">Upload a SKOS RDF/XML file to import concepts as terms in a taxonomy.</p>

@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form method="POST" action="{{ route('term.import.skos') }}" enctype="multipart/form-data" class="card">
    @csrf
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label">Target taxonomy <span class="text-danger">*</span></label>
            @if($preselectedTaxonomyId)
                @php $preselectedName = $taxonomies->firstWhere('id', $preselectedTaxonomyId)->name ?? 'Taxonomy #' . $preselectedTaxonomyId; @endphp
                <input type="text" class="form-control" value="{{ $preselectedName }}" disabled>
                <input type="hidden" name="taxonomy_id" value="{{ $preselectedTaxonomyId }}">
                <div class="form-text">Importing into the taxonomy of the term you came from. <a href="{{ route('term.import.skos') }}">Choose a different taxonomy</a></div>
            @else
                <select class="form-select" name="taxonomy_id" id="taxonomy_id" required>
                    <option value="">— Select taxonomy —</option>
                    @foreach($taxonomies as $tax)
                        <option value="{{ $tax->id }}">{{ $tax->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div class="mb-3">
            <label for="skos_file" class="form-label">SKOS RDF/XML file <span class="text-danger">*</span></label>
            <input type="file" class="form-control" name="skos_file" id="skos_file" accept=".rdf,.xml,.skos" required>
            <div class="form-text">Standard SKOS RDF/XML format (.rdf or .xml)</div>
        </div>
    </div>
    <div class="card-footer text-end">
        <a href="{{ url('/taxonomy/browse') }}" class="btn atom-btn-outline-light">Cancel</a>
        <button type="submit" class="btn atom-btn-outline-success">
            <i class="fas fa-upload me-1"></i>Import
        </button>
    </div>
</form>
@endsection
