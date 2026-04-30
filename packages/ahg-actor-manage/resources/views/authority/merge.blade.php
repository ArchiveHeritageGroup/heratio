@extends('theme::layouts.1col')

@section('title', 'Merge Authority Records')
@section('body-class', 'authority merge')

@section('content')

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item">
      <a href="{{ route('actor.dashboard') }}">Authority Dashboard</a>
    </li>
    <li class="breadcrumb-item">
      <a href="{{ $actor->slug ? route('actor.show', $actor->slug) : '#' }}">{{ e($actor->name ?? '') }}</a>
    </li>
    <li class="breadcrumb-item active">Merge</li>
  </ol>
</nav>

<h1 class="mb-4"><i class="fas fa-code-branch me-2"></i>{{ __('Merge Authority Records') }}</h1>

<div class="card mb-3">
  <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
    <i class="fas fa-compress-arrows-alt me-1"></i>Merge into: {{ e($actor->name ?? '') }}
  </div>
  <div class="card-body">
    <p class="text-muted">Select a secondary actor to merge into this record. All relations, resources, contacts, and identifiers will be transferred.</p>

    <div class="row g-2 mb-3">
      <div class="col-md-6">
        <label class="form-label">{{ __('Search for actor to merge') }}</label>
        <input type="text" id="merge-search" class="form-control" placeholder="{{ __('Type actor name...') }}">
      </div>
      <div class="col-auto align-self-end">
        <button class="btn atom-btn-white" id="btn-merge-search">
          <i class="fas fa-search me-1"></i>{{ __('Search') }}
        </button>
      </div>
    </div>

    <div id="merge-results" class="d-none mb-3">
      <table class="table table-sm table-hover">
        <thead>
          <tr>
            <th>{{ __('Name') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="merge-results-body"></tbody>
      </table>
    </div>

    {{-- Comparison preview (populated via AJAX) --}}
    <div id="merge-comparison" class="d-none"></div>
  </div>
</div>

{{-- Merge History --}}
@if (!empty($mergeHistory))
  <div class="card mb-3">
    <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
      <i class="fas fa-history me-1"></i>{{ __('Merge History') }}
    </div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th>{{ __('Type') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Records Transferred') }}</th>
            <th>{{ __('Date') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($mergeHistory as $h)
            @php
              $statusColors = ['pending' => 'warning', 'approved' => 'info', 'completed' => 'success', 'rejected' => 'danger', 'reversed' => 'dark'];
              $color = $statusColors[$h->status] ?? 'secondary';
            @endphp
            <tr>
              <td><span class="badge bg-secondary">{{ ucfirst($h->merge_type) }}</span></td>
              <td><span class="badge bg-{{ $color }}">{{ ucfirst($h->status) }}</span></td>
              <td>
                Relations: {{ $h->relations_transferred ?? 0 }},
                Resources: {{ $h->resources_transferred ?? 0 }},
                Contacts: {{ $h->contacts_transferred ?? 0 }},
                IDs: {{ $h->identifiers_transferred ?? 0 }}
              </td>
              <td>{{ $h->performed_at ?? $h->created_at }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

<script>
var primaryId = {{ (int) $actor->id }};
var csrfToken = '{{ csrf_token() }}';

document.getElementById('btn-merge-search').addEventListener('click', function() {
  var q = document.getElementById('merge-search').value;
  if (!q) return;

  fetch('{{ route("actor.autocomplete") }}?query=' + encodeURIComponent(q))
    .then(function(r) { return r.json(); })
    .then(function(actors) {
      var tbody = document.getElementById('merge-results-body');
      tbody.innerHTML = '';
      actors.forEach(function(a) {
        if (a.id == primaryId) return;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td>' + a.name + '</td>' +
          '<td><button class="btn btn-sm btn-warning" onclick="previewMerge(' + a.id + ')"><i class="fas fa-columns me-1"></i>Compare</button>' +
          ' <button class="btn btn-sm btn-danger" onclick="executeMerge(' + a.id + ')"><i class="fas fa-compress-arrows-alt me-1"></i>Merge</button></td>';
        tbody.appendChild(tr);
      });
      document.getElementById('merge-results').classList.remove('d-none');
    });
});

function previewMerge(secondaryId) {
  var data = new FormData();
  data.append('_token', csrfToken);
  data.append('primary_id', primaryId);
  data.append('secondary_id', secondaryId);

  fetch('{{ route("actor.api.merge.preview") }}', { method: 'POST', body: data })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.success && d.comparison) {
        var div = document.getElementById('merge-comparison');
        var html = '<div class="card"><div class="card-header">Field Comparison</div><div class="card-body p-0"><table class="table table-sm mb-0"><thead><tr><th>Field</th><th>Primary</th><th>Secondary</th><th></th></tr></thead><tbody>';
        var comp = d.comparison.comparison || {};
        for (var field in comp) {
          var info = comp[field];
          html += '<tr><td><strong>' + field.replace(/_/g, ' ') + '</strong></td>';
          html += '<td><small>' + (info.primary || '').substring(0, 200) + '</small></td>';
          html += '<td><small>' + (info.secondary || '').substring(0, 200) + '</small></td>';
          html += '<td>' + (info.match ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>') + '</td></tr>';
        }
        html += '</tbody></table></div></div>';
        div.innerHTML = html;
        div.classList.remove('d-none');
      }
    });
}

function executeMerge(secondaryId) {
  if (!confirm('Merge this actor into the primary record? This action cannot be undone.')) return;

  var data = new FormData();
  data.append('_token', csrfToken);
  data.append('primary_id', primaryId);
  data.append('secondary_ids', secondaryId);

  fetch('{{ route("actor.api.merge.execute") }}', { method: 'POST', body: data })
    .then(function(r) { return r.json(); })
    .then(function(d) { if (d.success) location.reload(); });
}
</script>

@endsection
