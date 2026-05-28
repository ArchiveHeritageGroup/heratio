{{-- ahg-biblio-frbr/index.blade.php — FRBR integration dashboard --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  <h1 class="h3 mb-3">
    <i class="bi bi-diagram-3"></i>
    FRBR Integration
  </h1>
  <p class="text-muted small mb-4">
    Convert bibliographic catalogue records to/from the
    <a href="https://www.ifla.org/publications/united-for-recommendations-2/" target="_blank" rel="noopener">IFLA FRBR conceptual model</a>
    &mdash; Work, Expression, Item, Manifestation.
    All round-trips go through the OpenRiC RiC-O service layer.
  </p>

  {{-- Stats row --}}
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card border-primary">
        <div class="card-body text-center py-3">
          <div class="display-6 text-primary">{{ number_format($stats['frbr_works'] ?? 0) }}</div>
          <div class="small text-muted text-uppercase">Total Works</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-success">
        <div class="card-body text-center py-3">
          <div class="display-6 text-success">{{ number_format($stats['frbr_expressions'] ?? 0) }}</div>
          <div class="small text-muted text-uppercase">Expressions</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-secondary">
        <div class="card-body text-center py-3">
          <div class="display-6 text-secondary">{{ number_format($stats['frbr_items'] ?? 0) }}</div>
          <div class="small text-muted text-uppercase">Items</div>
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
          <p class="small text-muted">Export a catalogue work as FRBR XML or JSON. Choose XML or JSON format.</p>
          <a href="{{ route('frbr.export') }}" class="btn btn-outline-primary btn-sm w-100">
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
          <p class="small text-muted">Import an FRBR XML document and merge the works into the catalogue.</p>
          <a href="{{ route('frbr.import') }}" class="btn btn-outline-success btn-sm w-100">
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
          <p class="small text-muted">Validate an FRBR document for structural correctness against the IFLA model.</p>
          <a href="{{ route('frbr.validate') }}" class="btn btn-outline-warning btn-sm w-100">
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
          <p class="small text-muted">Browse the agent authority used in FRBR records &mdash; creators, contributors, publishers.</p>
          <a href="{{ route('frbr.agent') }}" class="btn btn-outline-secondary btn-sm w-100">
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
        <strong>IFLA FRBR model</strong> &mdash; Work (intellectual creation) &rarr; Expression (text, translation, edition)
        &rarr; Manifestation (carrier, format) &rarr; Item (concrete copy).
        Conversion uses <code>library_biblio_work</code>, <code>library_biblio_instance</code>,
        <code>library_biblio_item</code>, and <code>library_biblio_agent</code>.
        All XML round-trips are proxied through the OpenRiC RiC-O service.
      </div>
    </div>
  </div>

</div>
@endsection
