@extends('theme::layouts.1col')

@section('title', 'External Identifiers')
@section('body-class', 'authority identifiers')

@section('content')

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item">
      <a href="{{ route('actor.dashboard') }}">Authority Dashboard</a>
    </li>
    <li class="breadcrumb-item">
      <a href="{{ $actor->slug ? route('actor.show', $actor->slug) : '#' }}">{{ e($actor->name ?? '') }}</a>
    </li>
    <li class="breadcrumb-item active">External Identifiers</li>
  </ol>
</nav>

<h1 class="mb-4"><i class="fas fa-link me-2"></i>External Identifiers</h1>

{{-- Existing Identifiers --}}
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between" style="background: var(--ahg-primary); color: #fff;">
    <span><i class="fas fa-external-link-alt me-1"></i>{{ __('Linked Identifiers') }}</span>
    <button type="button" class="btn btn-sm atom-btn-white" data-bs-toggle="modal" data-bs-target="#addIdentifierModal">
      <i class="fas fa-plus me-1"></i>{{ __('Add') }}
    </button>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>{{ __('Source') }}</th>
          <th>{{ __('Identifier') }}</th>
          <th>{{ __('Label') }}</th>
          <th>{{ __('Verified') }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="identifiers-list">
        @if (empty($identifiers))
          <tr id="no-identifiers"><td colspan="5" class="text-center text-muted py-3">No external identifiers yet.</td></tr>
        @else
          @foreach ($identifiers as $ident)
            <tr data-id="{{ $ident->id }}">
              <td>
                <span class="badge bg-secondary">{{ strtoupper($ident->identifier_type) }}</span>
              </td>
              <td>
                @if ($ident->uri)
                  <a href="{{ e($ident->uri) }}" target="_blank" rel="noopener">
                    {{ e($ident->identifier_value) }}
                    <i class="fas fa-external-link-alt ms-1 small"></i>
                  </a>
                @else
                  {{ e($ident->identifier_value) }}
                @endif
              </td>
              <td>{{ e($ident->label ?? '') }}</td>
              <td>
                @if ($ident->is_verified)
                  <span class="badge bg-success"><i class="fas fa-check"></i> {{ __('Verified') }}</span>
                @else
                  <button class="btn btn-sm btn-outline-success btn-verify" data-id="{{ $ident->id }}">
                    <i class="fas fa-check"></i> {{ __('Verify') }}
                  </button>
                @endif
              </td>
              <td>
                <button class="btn btn-sm btn-outline-danger btn-delete-id" data-id="{{ $ident->id }}">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
          @endforeach
        @endif
      </tbody>
    </table>
  </div>
</div>

{{-- Lookup Tools --}}
<div class="card mb-3">
  <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
    <i class="fas fa-search me-1"></i>Search External Authorities
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3">
      <div class="col-md-4">
        <input type="text" id="lookup-query" class="form-control" placeholder="{{ __('Search name...') }}">
      </div>
      <div class="col-auto">
        <button class="btn atom-btn-white" onclick="searchAuthority('wikidata')">
          <i class="fas fa-globe me-1"></i>{{ __('Wikidata') }}
        </button>
      </div>
      <div class="col-auto">
        <button class="btn atom-btn-white" onclick="searchAuthority('viaf')">{{ __('VIAF') }}</button>
      </div>
      <div class="col-auto">
        <button class="btn atom-btn-white" onclick="searchAuthority('ulan')">ULAN</button>
      </div>
      <div class="col-auto">
        <button class="btn atom-btn-white" onclick="searchAuthority('lcnaf')">LCNAF</button>
      </div>
    </div>
    <div id="lookup-results" class="d-none">
      <table class="table table-sm table-hover">
        <thead>
          <tr>
            <th>{{ __('ID') }}</th>
            <th>{{ __('Label') }}</th>
            <th>{{ __('Description') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="lookup-results-body"></tbody>
      </table>
    </div>
  </div>
</div>

{{-- Add Identifier Modal --}}
<div class="modal fade" id="addIdentifierModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Add External Identifier') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">{{ __('Source') }}</label>
          <select id="add-id-type" class="form-select">
            @foreach (array_keys($uriPatterns) as $type)
              <option value="{{ $type }}">{{ strtoupper($type) }}</option>
            @endforeach
            <option value="uri">{{ __('Other URI') }}</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Identifier Value') }}</label>
          <input type="text" id="add-id-value" class="form-control" placeholder="{{ __('Q12345') }}">
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Label (optional)') }}</label>
          <input type="text" id="add-id-label" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('URI (auto-constructed if blank)') }}</label>
          <input type="text" id="add-id-uri" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="button" class="btn atom-btn-white" id="btn-save-identifier">
          <i class="fas fa-save me-1"></i>{{ __('Save') }}
        </button>
      </div>
    </div>
  </div>
</div>

<script>
var actorId = {{ (int) $actor->id }};
var csrfToken = '{{ csrf_token() }}';

document.getElementById('btn-save-identifier').addEventListener('click', function() {
  var data = new FormData();
  data.append('_token', csrfToken);
  data.append('actor_id', actorId);
  data.append('identifier_type', document.getElementById('add-id-type').value);
  data.append('identifier_value', document.getElementById('add-id-value').value);
  data.append('label', document.getElementById('add-id-label').value);
  data.append('uri', document.getElementById('add-id-uri').value);

  fetch('{{ route("actor.api.identifier.save") }}', { method: 'POST', body: data })
    .then(function(r) { return r.json(); })
    .then(function(d) { if (d.success) location.reload(); });
});

document.querySelectorAll('.btn-delete-id').forEach(function(btn) {
  btn.addEventListener('click', function() {
    if (!confirm('Delete this identifier?')) return;
    var data = new FormData();
    data.append('_token', csrfToken);
    fetch('/api/authority/identifier/' + this.dataset.id + '/delete', { method: 'POST', body: data })
      .then(function(r) { return r.json(); })
      .then(function(d) { if (d.success) location.reload(); });
  });
});

document.querySelectorAll('.btn-verify').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var data = new FormData();
    data.append('_token', csrfToken);
    fetch('/api/authority/identifier/' + this.dataset.id + '/verify', { method: 'POST', body: data })
      .then(function(r) { return r.json(); })
      .then(function(d) { if (d.success) location.reload(); });
  });
});

function searchAuthority(source) {
  var q = document.getElementById('lookup-query').value;
  if (!q) return;

  fetch('/api/authority/' + source + '/search?q=' + encodeURIComponent(q))
    .then(function(r) { return r.json(); })
    .then(function(d) {
      var results = d.results || [];
      var tbody = document.getElementById('lookup-results-body');
      tbody.innerHTML = '';

      results.forEach(function(r) {
        var tr = document.createElement('tr');
        tr.innerHTML = '<td>' + (r.id || '') + '</td>' +
          '<td>' + (r.label || '') + '</td>' +
          '<td><small>' + (r.description || '') + '</small></td>' +
          '<td><button class="btn btn-sm btn-success" onclick="linkResult(\'' + source + '\',\'' +
          (r.id || '').replace(/'/g, "\\'") + '\',\'' +
          (r.label || '').replace(/'/g, "\\'") + '\',\'' +
          (r.uri || '').replace(/'/g, "\\'") + '\')"><i class="fas fa-link"></i></button></td>';
        tbody.appendChild(tr);
      });

      document.getElementById('lookup-results').classList.remove('d-none');
    });
}

function linkResult(source, id, label, uri) {
  var data = new FormData();
  data.append('_token', csrfToken);
  data.append('actor_id', actorId);
  data.append('identifier_type', source);
  data.append('identifier_value', id);
  data.append('label', label);
  data.append('uri', uri);
  data.append('source', 'reconciliation');

  fetch('{{ route("actor.api.identifier.save") }}', { method: 'POST', body: data })
    .then(function(r) { return r.json(); })
    .then(function(d) { if (d.success) location.reload(); });
}
</script>

@endsection
