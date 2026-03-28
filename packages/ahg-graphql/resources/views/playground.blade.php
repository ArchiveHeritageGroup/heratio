@extends('ahg-theme-b5::layout')

@section('title', 'GraphQL Playground')

@section('content')
<div class="container-fluid mt-3">
  <h1><i class="fas fa-project-diagram"></i> GraphQL Playground</h1>

  <div class="row">
    {{-- Query Editor --}}
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
          <h5 class="mb-0">Query</h5>
          <button class="btn btn-sm btn-primary" id="runQuery"><i class="fas fa-play"></i> Run</button>
        </div>
        <div class="card-body p-0">
          <textarea id="queryEditor" class="form-control" rows="15" style="font-family: monospace; font-size: 0.85em; border: none; resize: vertical;">{
  informationObjects(limit: 10, offset: 0) {
    id
    identifier
    title
    slug
  }
}</textarea>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Variables (JSON)</h6></div>
        <div class="card-body p-0">
          <textarea id="variablesEditor" class="form-control" rows="3" style="font-family: monospace; font-size: 0.85em; border: none;">{}</textarea>
        </div>
      </div>
    </div>

    {{-- Results --}}
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
          <h5 class="mb-0">Result</h5>
          <span id="queryTime" class="text-muted small"></span>
        </div>
        <div class="card-body p-0">
          <pre id="resultOutput" style="min-height: 300px; max-height: 500px; overflow: auto; margin: 0; padding: 1rem; font-size: 0.85em; background: #f8f9fa;"></pre>
        </div>
      </div>
    </div>
  </div>

  {{-- Schema Reference --}}
  <div class="card">
    <div class="card-header"><h5 class="mb-0">Schema Reference</h5></div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <h6>Queries</h6>
          <table class="table table-sm">
            @foreach($schema['queries'] ?? [] as $query => $returnType)
            <tr>
              <td><code>{{ $query }}</code></td>
              <td><span class="badge bg-info">{{ $returnType }}</span></td>
            </tr>
            @endforeach
          </table>
        </div>
        <div class="col-md-6">
          <h6>Types</h6>
          @foreach($schema['types'] ?? [] as $type)
          <div class="mb-2">
            <strong>{{ $type['name'] }}</strong>
            <div class="small text-muted">{{ implode(', ', $type['fields'] ?? []) }}</div>
          </div>
          @endforeach
        </div>
      </div>

      <h6 class="mt-3">Example Queries</h6>
      <div class="row">
        <div class="col-md-4">
          <button class="btn btn-sm btn-outline-secondary example-query mb-1" data-query='{ informationObjects(limit: 10, offset: 0) { id identifier title slug } }'>List IOs</button>
          <button class="btn btn-sm btn-outline-secondary example-query mb-1" data-query='{ informationObject(id: 2) { id identifier title scope_and_content slug } }'>Get IO by ID</button>
        </div>
        <div class="col-md-4">
          <button class="btn btn-sm btn-outline-secondary example-query mb-1" data-query='{ actors(limit: 10) { id authorized_form_of_name slug } }'>List Actors</button>
          <button class="btn btn-sm btn-outline-secondary example-query mb-1" data-query='{ actor(id: 2) { id authorized_form_of_name history slug } }'>Get Actor by ID</button>
        </div>
        <div class="col-md-4">
          <button class="btn btn-sm btn-outline-secondary example-query mb-1" data-query='{ repositories { id authorized_form_of_name slug } }'>List Repositories</button>
          <button class="btn btn-sm btn-outline-secondary example-query mb-1" data-query='{ __schema { types { name fields } queries } }'>Introspection</button>
        </div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
const endpoint = '{{ route("ahggraphql.execute") }}';
const csrfToken = '{{ csrf_token() }}';

document.getElementById('runQuery').addEventListener('click', runQuery);
document.getElementById('queryEditor').addEventListener('keydown', function(e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); runQuery(); }
});

document.querySelectorAll('.example-query').forEach(btn => {
  btn.addEventListener('click', function() {
    document.getElementById('queryEditor').value = this.dataset.query;
    runQuery();
  });
});

function runQuery() {
  const query = document.getElementById('queryEditor').value;
  const variables = document.getElementById('variablesEditor').value;
  const output = document.getElementById('resultOutput');
  const timeEl = document.getElementById('queryTime');

  output.textContent = 'Loading...';
  const start = performance.now();

  fetch(endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
    body: JSON.stringify({ query: query, variables: variables })
  })
  .then(r => r.json())
  .then(data => {
    const elapsed = Math.round(performance.now() - start);
    timeEl.textContent = elapsed + 'ms';
    output.textContent = JSON.stringify(data, null, 2);
  })
  .catch(err => {
    output.textContent = 'Error: ' + err.message;
    timeEl.textContent = '';
  });
}
</script>
@endpush
@endsection
