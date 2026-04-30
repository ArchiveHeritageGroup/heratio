@extends('theme::layouts.1col')

@section('title', 'NER-to-Authority Pipeline')
@section('body-class', 'authority ner')

@section('content')

@php
  $byStatus = $stats['by_status'] ?? [];
  $stubItems = $stubs['data'] ?? [];
  $pendingItems = $pendingEntities['data'] ?? [];
@endphp

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item">
      <a href="{{ route('actor.dashboard') }}">Authority Dashboard</a>
    </li>
    <li class="breadcrumb-item active">NER Pipeline</li>
  </ol>
</nav>

<h1 class="mb-4"><i class="fas fa-robot me-2"></i>NER-to-Authority Pipeline</h1>

{{-- Stats --}}
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card text-center border-info">
      <div class="card-body">
        <h3>{{ number_format($stats['pending_entities'] ?? 0) }}</h3>
        <small class="text-muted">Pending Entities</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center border-warning">
      <div class="card-body">
        <h3>{{ isset($byStatus['stub']) ? $byStatus['stub']->count : 0 }}</h3>
        <small class="text-muted">Stubs</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center border-success">
      <div class="card-body">
        <h3>{{ isset($byStatus['promoted']) ? $byStatus['promoted']->count : 0 }}</h3>
        <small class="text-muted">Promoted</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center border-danger">
      <div class="card-body">
        <h3>{{ isset($byStatus['rejected']) ? $byStatus['rejected']->count : 0 }}</h3>
        <small class="text-muted">Rejected</small>
      </div>
    </div>
  </div>
</div>

{{-- Pending NER Entities --}}
<div class="card mb-3">
  <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
    <i class="fas fa-magic me-1"></i>Pending NER Entities (not yet stubbed)
  </div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0">
      <thead>
        <tr>
          <th>{{ __('Entity Value') }}</th>
          <th>{{ __('Type') }}</th>
          <th>{{ __('Confidence') }}</th>
          <th>{{ __('Source') }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @if (empty($pendingItems))
          <tr><td colspan="5" class="text-center text-muted py-3">No pending NER entities. Requires AI plugin with NER extraction.</td></tr>
        @else
          @foreach ($pendingItems as $entity)
            @php $entity = (object) $entity; @endphp
            <tr>
              <td><strong>{{ e($entity->entity_value) }}</strong></td>
              <td><span class="badge bg-secondary">{{ e($entity->entity_type) }}</span></td>
              <td>{{ number_format(($entity->confidence ?? 0) * 100, 1) }}%</td>
              <td><small>{{ e($entity->source_title ?? '') }}</small></td>
              <td>
                <button class="btn btn-sm btn-success btn-create-stub" data-id="{{ $entity->id }}">
                  <i class="fas fa-user-plus me-1"></i>Create Stub
                </button>
              </td>
            </tr>
          @endforeach
        @endif
      </tbody>
    </table>
  </div>
</div>

{{-- Existing Stubs --}}
<div class="card mb-3">
  <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
    <i class="fas fa-user-clock me-1"></i>Authority Stubs
  </div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0">
      <thead>
        <tr>
          <th>{{ __('Entity') }}</th>
          <th>{{ __('Type') }}</th>
          <th>{{ __('Actor') }}</th>
          <th>{{ __('Status') }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @if (empty($stubItems))
          <tr><td colspan="5" class="text-center text-muted py-3">No stubs created yet.</td></tr>
        @else
          @foreach ($stubItems as $stub)
            @php
              $stub = (object) $stub;
              $statusColors = ['stub' => 'warning', 'promoted' => 'success', 'rejected' => 'danger'];
            @endphp
            <tr>
              <td>{{ e($stub->entity_value) }}</td>
              <td><span class="badge bg-secondary">{{ e($stub->entity_type) }}</span></td>
              <td>
                @if ($stub->slug)
                  <a href="{{ route('actor.show', $stub->slug) }}">{{ e($stub->actor_name ?? '') }}</a>
                @else
                  {{ e($stub->actor_name ?? 'Actor #' . $stub->actor_id) }}
                @endif
              </td>
              <td>
                <span class="badge bg-{{ $statusColors[$stub->status] ?? 'secondary' }}">
                  {{ ucfirst($stub->status) }}
                </span>
              </td>
              <td>
                @if ($stub->status === 'stub')
                  <button class="btn btn-sm btn-outline-success btn-promote" data-id="{{ $stub->id }}">
                    <i class="fas fa-arrow-up"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger btn-reject" data-id="{{ $stub->id }}">
                    <i class="fas fa-ban"></i>
                  </button>
                @endif
              </td>
            </tr>
          @endforeach
        @endif
      </tbody>
    </table>
  </div>
</div>

<script>
var csrfToken = '{{ csrf_token() }}';

document.querySelectorAll('.btn-create-stub').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var data = new FormData();
    data.append('_token', csrfToken);
    data.append('ner_entity_id', this.dataset.id);
    fetch('{{ route("actor.api.ner.create-stub") }}', { method: 'POST', body: data })
      .then(function(r) { return r.json(); })
      .then(function(d) { if (d.success) location.reload(); else alert(d.error || 'Failed'); });
  });
});

document.querySelectorAll('.btn-promote').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var data = new FormData();
    data.append('_token', csrfToken);
    fetch('/api/authority/ner/' + this.dataset.id + '/promote', { method: 'POST', body: data })
      .then(function(r) { return r.json(); })
      .then(function(d) { if (d.success) location.reload(); });
  });
});

document.querySelectorAll('.btn-reject').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var data = new FormData();
    data.append('_token', csrfToken);
    fetch('/api/authority/ner/' + this.dataset.id + '/reject', { method: 'POST', body: data })
      .then(function(r) { return r.json(); })
      .then(function(d) { if (d.success) location.reload(); });
  });
});
</script>

@endsection
