@extends('theme::layouts.1col')

@section('title', 'Structured Occupations')
@section('body-class', 'authority occupations')

@section('content')

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item">
      <a href="{{ route('actor.dashboard') }}">Authority Dashboard</a>
    </li>
    <li class="breadcrumb-item">
      <a href="{{ $actor->slug ? route('actor.show', $actor->slug) : '#' }}">{{ e($actor->name ?? '') }}</a>
    </li>
    <li class="breadcrumb-item active">Occupations</li>
  </ol>
</nav>

<h1 class="mb-4"><i class="fas fa-briefcase me-2"></i>Structured Occupations</h1>

<div class="card mb-3">
  <div class="card-header d-flex justify-content-between" style="background: var(--ahg-primary); color: #fff;">
    <span><i class="fas fa-briefcase me-1"></i>{{ __('Occupations') }}</span>
    <button class="btn btn-sm atom-btn-white" data-bs-toggle="modal" data-bs-target="#addOccupationModal">
      <i class="fas fa-plus me-1"></i>{{ __('Add') }}
    </button>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>{{ __('Occupation') }}</th>
          <th>{{ __('From') }}</th>
          <th>{{ __('To') }}</th>
          <th>{{ __('Notes') }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @if (empty($occupations))
          <tr><td colspan="5" class="text-center text-muted py-3">No occupations recorded.</td></tr>
        @else
          @foreach ($occupations as $occ)
            <tr>
              <td>
                @if ($occ->term_name)
                  <span class="badge bg-info">{{ e($occ->term_name) }}</span>
                @endif
                @if ($occ->occupation_text)
                  {{ e($occ->occupation_text) }}
                @endif
              </td>
              <td>{{ e($occ->date_from ?? '') }}</td>
              <td>{{ e($occ->date_to ?? '') }}</td>
              <td><small>{{ e($occ->notes ?? '') }}</small></td>
              <td>
                <button class="btn btn-sm btn-outline-danger btn-delete-occ" data-id="{{ $occ->id }}">
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

{{-- Add Occupation Modal --}}
<div class="modal fade" id="addOccupationModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Add Occupation') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">{{ __('Occupation (free text)') }}</label>
          <input type="text" id="occ-text" class="form-control">
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">{{ __('Date from') }}</label>
            <input type="text" id="occ-from" class="form-control" placeholder="{{ __('YYYY') }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('Date to') }}</label>
            <input type="text" id="occ-to" class="form-control" placeholder="{{ __('YYYY') }}">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Notes') }}</label>
          <textarea id="occ-notes" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="button" class="btn atom-btn-white" id="btn-save-occ">
          <i class="fas fa-save me-1"></i>{{ __('Save') }}
        </button>
      </div>
    </div>
  </div>
</div>

<script>
var actorId = {{ (int) $actor->id }};
var csrfToken = '{{ csrf_token() }}';

document.getElementById('btn-save-occ').addEventListener('click', function() {
  var data = new FormData();
  data.append('_token', csrfToken);
  data.append('actor_id', actorId);
  data.append('occupation_text', document.getElementById('occ-text').value);
  data.append('date_from', document.getElementById('occ-from').value);
  data.append('date_to', document.getElementById('occ-to').value);
  data.append('notes', document.getElementById('occ-notes').value);

  fetch('{{ route("actor.api.occupation.save") }}', { method: 'POST', body: data })
    .then(function(r) { return r.json(); })
    .then(function(d) { if (d.success) location.reload(); });
});

document.querySelectorAll('.btn-delete-occ').forEach(function(btn) {
  btn.addEventListener('click', function() {
    if (!confirm('Delete this occupation?')) return;
    var data = new FormData();
    data.append('_token', csrfToken);
    fetch('/api/authority/occupation/' + this.dataset.id + '/delete', { method: 'POST', body: data })
      .then(function(r) { return r.json(); })
      .then(function(d) { if (d.success) location.reload(); });
  });
});
</script>

@endsection
