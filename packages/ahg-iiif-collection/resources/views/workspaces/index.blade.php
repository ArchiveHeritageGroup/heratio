{{--
  Mirador workspace persistence - admin page (issue #699).
  Heratio - Bootstrap 5; matches the styling used elsewhere in ahg-iiif-collection.
  Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', __('Mirador Workspaces'))
@section('body-class', 'admin iiif workspaces')

@section('sidebar')
<div class="sidebar-content">
  <div class="card mb-3">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="fas fa-th-large me-2"></i>{{ __('Workspaces') }}</h5>
    </div>
    <div class="card-body">
      <p class="small text-muted mb-0">
        {{ __('Saved Mirador 4 workspace layouts. Rename, delete, or set the default-on-load workspace.') }}
      </p>
    </div>
  </div>
  <div class="card">
    <div class="card-header bg-light">
      <h5 class="mb-0"><i class="fas fa-link me-2"></i>{{ __('Quick Links') }}</h5>
    </div>
    <div class="list-group list-group-flush">
      <a href="{{ route('iiif-collection.index') }}" class="list-group-item list-group-item-action">
        <i class="fas fa-layer-group me-2"></i>{{ __('Manage Collections') }}
      </a>
      <a href="{{ route('iiif.settings') }}" class="list-group-item list-group-item-action">
        <i class="fas fa-images me-2"></i>{{ __('IIIF Settings') }}
      </a>
    </div>
  </div>
</div>
@endsection

@section('title-block')
  <h1><i class="fas fa-th-large me-2"></i>{{ __('Mirador Workspaces') }}</h1>
@endsection

@section('content')
<div id="workspaces-app">
  <div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    {{ __('Workspaces are saved from the Mirador toolbar via the "Save current" / "Save as new" actions. localStorage holds an auto-save copy; this list is the durable, per-user, server-side store.') }}
  </div>

  @if(empty($workspaces))
    <div class="alert alert-secondary">
      <i class="fas fa-folder-open me-2"></i>
      {{ __('You have no saved workspaces yet. Open the Mirador viewer and click "Save current" in the workspace dropdown.') }}
    </div>
  @else
    <div class="card">
      <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <strong>{{ __('Saved workspaces') }} ({{ count($workspaces) }})</strong>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle" id="workspaces-table">
          <thead class="table-light">
            <tr>
              <th>{{ __('Name') }}</th>
              <th>{{ __('Default') }}</th>
              <th>{{ __('Created') }}</th>
              <th>{{ __('Updated') }}</th>
              <th class="text-end">{{ __('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($workspaces as $w)
              <tr data-id="{{ (int) $w['id'] }}">
                <td>
                  <span class="ws-name">{{ e($w['name']) }}</span>
                </td>
                <td>
                  @if(!empty($w['is_default']))
                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>{{ __('Default') }}</span>
                  @else
                    <span class="text-muted small">-</span>
                  @endif
                </td>
                <td><small class="text-muted">{{ $w['created_at'] ?? '' }}</small></td>
                <td><small class="text-muted">{{ $w['updated_at'] ?? '' }}</small></td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary ws-rename" title="{{ __('Rename') }}">
                      <i class="fas fa-pen me-1"></i>{{ __('Rename') }}
                    </button>
                    <button type="button" class="btn btn-outline-success ws-set-default" title="{{ __('Set as default') }}" @if(!empty($w['is_default'])) disabled @endif>
                      <i class="fas fa-star me-1"></i>{{ __('Set default') }}
                    </button>
                    <button type="button" class="btn btn-outline-danger ws-delete" title="{{ __('Delete') }}">
                      <i class="fas fa-trash me-1"></i>{{ __('Delete') }}
                    </button>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
</div>

<script>
(function () {
  // Thin admin glue that calls the WorkspaceController REST endpoints. The
  // table re-renders by reloading the page after each successful action;
  // keeps the markup honest and avoids stale-state edge cases.
  var root = document.getElementById('workspaces-app');
  if (!root) return;

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function api(method, url, body) {
    var opts = {
      method: method,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
    };
    if (body) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    return fetch(url, opts).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  root.addEventListener('click', function (ev) {
    var btn = ev.target.closest('button');
    if (!btn) return;
    var tr = btn.closest('tr[data-id]');
    if (!tr) return;
    var id = tr.getAttribute('data-id');

    if (btn.classList.contains('ws-rename')) {
      var current = tr.querySelector('.ws-name').textContent.trim();
      var next = window.prompt('{{ __('New workspace name') }}', current);
      if (next === null) return;
      next = next.trim();
      if (!next) return;
      api('PUT', '/api/iiif/workspace/' + id, { name: next })
        .then(function () { window.location.reload(); })
        .catch(function (e) { alert('Rename failed: ' + e.message); });
    } else if (btn.classList.contains('ws-set-default')) {
      api('POST', '/api/iiif/workspace/' + id + '/load', {})
        .then(function () { window.location.reload(); })
        .catch(function (e) { alert('Set-default failed: ' + e.message); });
    } else if (btn.classList.contains('ws-delete')) {
      if (!window.confirm('{{ __('Delete this workspace? This cannot be undone.') }}')) return;
      api('DELETE', '/api/iiif/workspace/' + id)
        .then(function () { window.location.reload(); })
        .catch(function (e) { alert('Delete failed: ' + e.message); });
    }
  });
})();
</script>
@endsection
