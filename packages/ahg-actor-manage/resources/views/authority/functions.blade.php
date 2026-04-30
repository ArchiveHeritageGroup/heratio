@extends('theme::layouts.1col')

@section('title', 'Function Links')
@section('body-class', 'authority functions')

@section('content')

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item">
      <a href="{{ route('actor.dashboard') }}">Authority Dashboard</a>
    </li>
    <li class="breadcrumb-item">
      <a href="{{ $actor->slug ? route('actor.show', $actor->slug) : '#' }}">{{ e($actor->name ?? '') }}</a>
    </li>
    <li class="breadcrumb-item active">Functions</li>
  </ol>
</nav>

<h1 class="mb-4"><i class="fas fa-sitemap me-2"></i>Function Links</h1>

<div class="card mb-3">
  <div class="card-header d-flex justify-content-between" style="background: var(--ahg-primary); color: #fff;">
    <span><i class="fas fa-sitemap me-1"></i>{{ __('ISDF Function Links') }}</span>
    <button class="btn btn-sm atom-btn-white" data-bs-toggle="modal" data-bs-target="#addFunctionModal">
      <i class="fas fa-plus me-1"></i>{{ __('Add') }}
    </button>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>{{ __('Function') }}</th>
          <th>{{ __('Relation') }}</th>
          <th>{{ __('Period') }}</th>
          <th>{{ __('Notes') }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @if (empty($functionLinks))
          <tr><td colspan="5" class="text-center text-muted py-3">No function links.</td></tr>
        @else
          @foreach ($functionLinks as $link)
            <tr>
              <td>
                @if ($link->function_slug)
                  <a href="{{ url('/' . $link->function_slug) }}">
                    {{ e($link->function_title ?? 'Function #' . $link->function_id) }}
                  </a>
                @else
                  {{ e($link->function_title ?? 'Function #' . $link->function_id) }}
                @endif
              </td>
              <td>
                <span class="badge bg-info">
                  {{ e($relationTypes[$link->relation_type] ?? $link->relation_type) }}
                </span>
              </td>
              <td>
                {{ e($link->date_from ?? '') }}
                @if ($link->date_from || $link->date_to) &ndash; @endif
                {{ e($link->date_to ?? '') }}
              </td>
              <td><small>{{ e($link->notes ?? '') }}</small></td>
              <td>
                <button class="btn btn-sm btn-outline-danger btn-delete-func" data-id="{{ $link->id }}">
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

{{-- Add Function Link Modal --}}
<div class="modal fade" id="addFunctionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Link Function') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">{{ __('Function ID') }}</label>
          <input type="number" id="func-id" class="form-control" placeholder="{{ __('Enter function object ID') }}">
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Relation type') }}</label>
          <select id="func-rel-type" class="form-select">
            @foreach ($relationTypes as $key => $label)
              <option value="{{ $key }}">{{ e($label) }}</option>
            @endforeach
          </select>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">{{ __('Date from') }}</label>
            <input type="text" id="func-from" class="form-control" placeholder="{{ __('YYYY') }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('Date to') }}</label>
            <input type="text" id="func-to" class="form-control" placeholder="{{ __('YYYY') }}">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Notes') }}</label>
          <textarea id="func-notes" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="button" class="btn atom-btn-white" id="btn-save-func">
          <i class="fas fa-save me-1"></i>{{ __('Save') }}
        </button>
      </div>
    </div>
  </div>
</div>

<script>
var actorId = {{ (int) $actor->id }};
var csrfToken = '{{ csrf_token() }}';

document.getElementById('btn-save-func').addEventListener('click', function() {
  var data = new FormData();
  data.append('_token', csrfToken);
  data.append('actor_id', actorId);
  data.append('function_id', document.getElementById('func-id').value);
  data.append('relation_type', document.getElementById('func-rel-type').value);
  data.append('date_from', document.getElementById('func-from').value);
  data.append('date_to', document.getElementById('func-to').value);
  data.append('notes', document.getElementById('func-notes').value);

  fetch('{{ route("actor.api.function.save") }}', { method: 'POST', body: data })
    .then(function(r) { return r.json(); })
    .then(function(d) { if (d.success) location.reload(); });
});

document.querySelectorAll('.btn-delete-func').forEach(function(btn) {
  btn.addEventListener('click', function() {
    if (!confirm('Delete this function link?')) return;
    var data = new FormData();
    data.append('_token', csrfToken);
    fetch('/api/authority/function/' + this.dataset.id + '/delete', { method: 'POST', body: data })
      .then(function(r) { return r.json(); })
      .then(function(d) { if (d.success) location.reload(); });
  });
});
</script>

@endsection
