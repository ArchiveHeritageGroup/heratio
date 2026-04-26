{{--
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems / AGPL v3+
  RDF inbound import — TTL / JSON-LD / RDF-XML upload + dry-run + commit.
--}}
@extends('theme::layouts.1col')

@section('title', 'RDF Import')
@section('body-class', 'edit')

@section('content')
<div class="container my-4">
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-file-import me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">RDF Import</h1>
      <small class="text-muted">Parse Turtle / JSON-LD / RDF-XML and create archival descriptions or actors. Dry-run first; commit only when the mapping looks right.</small>
    </div>
  </div>

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <form method="POST" action="{{ route('ric.import.run') }}" enctype="multipart/form-data" class="card mb-4">
    @csrf
    <div class="card-header bg-light"><strong>Source</strong></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Format</label>
          <select name="format" class="form-select" required>
            <option value="turtle" {{ old('format') === 'turtle' ? 'selected' : '' }}>Turtle (.ttl)</option>
            <option value="jsonld" {{ old('format') === 'jsonld' ? 'selected' : '' }}>JSON-LD (.jsonld / .json)</option>
            <option value="rdfxml" {{ old('format') === 'rdfxml' ? 'selected' : '' }}>RDF/XML (.rdf / .xml)</option>
          </select>
        </div>
        <div class="col-md-9">
          <label class="form-label">Upload file <small class="text-muted">(or paste below)</small></label>
          <input type="file" name="file" class="form-control" accept=".ttl,.jsonld,.json,.rdf,.xml,.n3">
        </div>
        <div class="col-12">
          <label class="form-label">Or paste RDF</label>
          <textarea name="payload" rows="10" class="form-control font-monospace" placeholder="@prefix rico: <https://www.ica.org/standards/RiC/ontology#> .&#10;@prefix dc:   <http://purl.org/dc/elements/1.1/> .&#10;&#10;<https://example.org/record/1> a rico:Record ;&#10;    rico:name &quot;Cabinet minutes, 1965&quot;@en ;&#10;    dc:description &quot;Bound volume of cabinet minutes&quot;@en .">{{ old('payload') }}</textarea>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input type="hidden" name="commit" value="0">
            <input class="form-check-input" type="checkbox" name="commit" value="1" id="commit"
                   {{ old('commit') === '1' ? 'checked' : '' }}>
            <label class="form-check-label" for="commit">
              <strong>Commit:</strong> create rows in the database
              (leave unchecked for a dry-run)
            </label>
          </div>
        </div>
      </div>
    </div>
    <div class="card-footer text-end">
      <button type="submit" class="btn btn-primary"><i class="fas fa-play me-1"></i>Parse</button>
    </div>
  </form>

  @if($result)
    <div class="card mb-3">
      <div class="card-header bg-light"><strong>Dry-run summary</strong> — {{ ucfirst($result['format']) }}</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3"><div class="border rounded p-2 text-center"><div class="h4 mb-0">{{ number_format($result['triples']) }}</div><small class="text-muted">Triples</small></div></div>
          <div class="col-md-3"><div class="border rounded p-2 text-center"><div class="h4 mb-0">{{ number_format($result['subjects']) }}</div><small class="text-muted">Subjects</small></div></div>
          <div class="col-md-3"><div class="border rounded p-2 text-center"><div class="h4 mb-0 text-success">{{ number_format($result['would_create']['information_object']) }}</div><small class="text-muted">→ Records</small></div></div>
          <div class="col-md-3"><div class="border rounded p-2 text-center"><div class="h4 mb-0 text-info">{{ number_format($result['would_create']['actor']) }}</div><small class="text-muted">→ Actors</small></div></div>
        </div>

        @if($result['would_create']['unknown'] > 0)
          <div class="alert alert-warning mt-3 mb-0">
            <strong>{{ $result['would_create']['unknown'] }}</strong> subjects had no recognisable rdf:type and would be skipped.
            Add a <code>rico:Record</code>, <code>rico:Agent</code>, <code>rico:CorporateBody</code>, or other supported type, or extend the type map.
          </div>
        @endif
      </div>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <div class="card mb-3">
          <div class="card-header bg-success text-white"><strong>Mapped predicates</strong></div>
          <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Predicate</th><th class="text-end">Count</th></tr></thead>
            <tbody>
              @forelse($result['mapped_predicates'] as $p => $n)
                <tr><td><code>{{ $p }}</code></td><td class="text-end">{{ $n }}</td></tr>
              @empty
                <tr><td colspan="2" class="text-muted text-center">No mapped predicates yet — check rdf:type values.</td></tr>
              @endforelse
            </tbody>
          </table></div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card mb-3">
          <div class="card-header bg-warning"><strong>Unmapped predicates</strong></div>
          <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Predicate</th><th class="text-end">Count</th></tr></thead>
            <tbody>
              @forelse($result['unmapped_predicates'] as $p => $n)
                <tr><td><code>{{ $p }}</code></td><td class="text-end">{{ $n }}</td></tr>
              @empty
                <tr><td colspan="2" class="text-muted text-center">All predicates are mapped.</td></tr>
              @endforelse
            </tbody>
          </table></div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header bg-light"><strong>rdf:type values seen</strong></div>
      <div class="card-body">
        @forelse($result['classes_seen'] as $c => $n)
          <span class="badge bg-secondary me-1 mb-1"><code class="text-white">{{ $c }}</code> × {{ $n }}</span>
        @empty
          <em class="text-muted">No classes detected.</em>
        @endforelse
      </div>
    </div>

    @if(!empty($result['sample']))
      <div class="card mb-3">
        <div class="card-header bg-light"><strong>Sample subjects</strong></div>
        <ul class="list-group list-group-flush">
          @foreach($result['sample'] as $s)
            <li class="list-group-item">
              <span class="badge bg-{{ $s['type'] === 'actor' ? 'info' : 'success' }} me-2">{{ $s['type'] }}</span>
              <strong>{{ $s['title'] ?: '(no title)' }}</strong>
              <small class="text-muted ms-2"><code>{{ $s['subject'] }}</code></small>
            </li>
          @endforeach
        </ul>
      </div>
    @endif
  @endif

  @if($committed)
    <div class="card mb-3 border-success">
      <div class="card-header bg-success text-white"><strong><i class="fas fa-check me-1"></i>Committed</strong></div>
      <div class="card-body">
        <ul class="mb-2">
          <li>Information objects created: <strong>{{ count($committed['created_io']) }}</strong></li>
          <li>Actors created: <strong>{{ count($committed['created_actor']) }}</strong></li>
          <li>Skipped (unknown rdf:type): <strong>{{ $committed['skipped'] }}</strong></li>
        </ul>
        @foreach($committed['errors'] as $err)
          <div class="alert alert-danger mb-2">{{ $err }}</div>
        @endforeach
        @if($committed['created_io'])
          <small class="text-muted">New IO IDs: {{ implode(', ', array_slice($committed['created_io'], 0, 50)) }}@if(count($committed['created_io']) > 50) …@endif</small>
        @endif
      </div>
    </div>
  @endif

  @if($sparqlEnabled)
    <div class="card mb-3">
      <div class="card-header bg-light"><strong>SPARQL endpoint (read-only)</strong></div>
      <div class="card-body">
        <p class="mb-2 text-muted">Federated clients can query Heratio's RiC graph via the proxy below. SELECT / ASK / CONSTRUCT / DESCRIBE only.</p>
        <pre class="bg-dark text-light p-2 rounded small mb-0"><code>GET {{ url('/api/sparql') }}?query=…</code></pre>
      </div>
    </div>
  @endif
</div>
@endsection
