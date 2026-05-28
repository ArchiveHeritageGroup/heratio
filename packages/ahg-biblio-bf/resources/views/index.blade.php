{{-- ahg-biblio-bf/index.blade.php — BIBFRAME integration dashboard --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  <h1 class="h3 mb-3">
    <i class="bi bi-link-45deg"></i>
    BIBFRAME Integration
  </h1>
  <p class="text-muted small mb-4">
    Convert bibliographic catalogue records to/from
    <a href="https://www.loc.gov/standards/bibframe/" target="_blank" rel="noopener">BIBFRAME 2.0 (Library of Congress)</a>.
    All round-trips go through the OpenRiC RiC-O service layer.
  </p>

  {{-- Stats row --}}
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card border-primary">
        <div class="card-body text-center py-3">
          <div class="display-6 text-primary">{{ number_format($stats['bibframe_export_total'] ?? 0) }}</div>
          <div class="small text-muted text-uppercase">Total Works</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-success">
        <div class="card-body text-center py-3">
          <div class="display-6 text-success">{{ number_format($stats['bibframe_export_rdf'] ?? 0) }}</div>
          <div class="small text-muted text-uppercase">With RDF</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-secondary">
        <div class="card-body text-center py-3">
          <div class="display-6 text-secondary">{{ number_format($stats['bibframe_export_total'] - ($stats['bibframe_export_rdf'] ?? 0)) }}</div>
          <div class="small text-muted text-uppercase">Pending RDF</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Task cards --}}
  <div class="row g-3">
    <div class="col-md-6 col-lg-3">
      <div class="card h-100">
        <div class="card-header bg-primary text-white">
          <i class="bi bi-box-arrow-up-right me-1"></i> Export
        </div>
        <div class="card-body">
          <p class="small text-muted">Export one or more catalogue works as BIBFRAME 2.0 RDF. Choose XML, Turtle, or JSON-LD.</p>
          <a href="{{ route('bibframe.export') }}" class="btn btn-outline-primary btn-sm w-100">
            Open Export UI
          </a>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-lg-3">
      <div class="card h-100">
        <div class="card-header bg-success text-white">
          <i class="bi bi-box-arrow-down-left me-1"></i> Import
        </div>
        <div class="card-body">
          <p class="small text-muted">Import a BIBFRAME RDF/XML document and merge the works into the catalogue.</p>
          <a href="{{ route('bibframe.import') }}" class="btn btn-outline-success btn-sm w-100">
            Open Import UI
          </a>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-lg-3">
      <div class="card h-100">
        <div class="card-header bg-warning text-dark">
          <i class="bi bi-check-circle me-1"></i> Validate
        </div>
        <div class="card-body">
          <p class="small text-muted">Validate a BIBFRAME document for structural correctness against the LoC profile.</p>
          <a href="{{ route('bibframe.validate') }}" class="btn btn-outline-warning btn-sm w-100">
            Open Validate UI
          </a>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-lg-3">
      <div class="card h-100">
        <div class="card-header bg-secondary text-white">
          <i class="bi bi-person me-1"></i> Agents
        </div>
        <div class="card-body">
          <p class="small text-muted">Browse the agent authority used in BIBFRAME records — authors, editors, illustrators.</p>
          <a href="{{ route('bibframe.agent') }}" class="btn btn-outline-secondary btn-sm w-100">
            Browse Agents
          </a>
        </div>
      </div>
    </div>
  </div>

  {{-- Info panel --}}
  <div class="row mt-4">
    <div class="col-lg-8">
      <div class="alert alert-info small mb-0">
        <strong>BIBFRAME 2.0 model</strong> — Work (intellectual creation) &rarr; Instance (edition/format) &rarr; Item (copy).
        Conversion uses <code>library_biblio_work</code>, <code>library_biblio_instance</code>, and
        <code>library_biblio_agent</code>. All RDF round-trips are proxied through the OpenRiC RiC-O service.
        See <a href="https://www.loc.gov/standards/bibframe/docs/" target="_blank" rel="noopener">LoC BIBFRAME docs</a>.
      </div>
    </div>
  </div>

</div>
@endsection
