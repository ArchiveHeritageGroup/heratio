{{-- Researcher offline packages (Phase 1–2) --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'offline'])
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1><i class="fas fa-laptop me-2"></i>{{ __('Work Offline') }}</h1>
  </div>

  <p class="text-muted">
    {{ __('Take one of your research groups offline as a self-contained package you can browse and annotate with no internet — on a laptop, USB stick or in the field. When you are back online, use “Save for sync” inside the package and bring your changes home.') }}
  </p>

  <div class="alert alert-info small">
    <i class="fas fa-shield-halved me-1"></i>
    {{ __('Packages only ever contain records you are permitted to see. Restricted, embargoed or unpublished records are automatically withheld.') }}
  </div>

  <div class="alert alert-secondary small">
    <strong>{{ __('How to create a package:') }}</strong>
    {{ __('open one of your Projects, Collections, Workspaces or Favourites folders and click “Take offline”. It will appear in the list below when built.') }}
  </div>

  @if($packages->isEmpty())
    <div class="text-center text-muted py-5">
      <i class="fas fa-box-open fa-2x mb-2"></i>
      <p>{{ __('You have not created any offline packages yet.') }}</p>
    </div>
  @else
    <table class="table align-middle">
      <thead>
        <tr>
          <th>{{ __('Package') }}</th>
          <th>{{ __('From') }}</th>
          <th>{{ __('Records') }}</th>
          <th>{{ __('Status') }}</th>
          <th class="text-end">{{ __('Action') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($packages as $p)
          @php
            $disc = !empty($p->disclosure_summary) ? json_decode($p->disclosure_summary, true) : null;
            $withheld = $disc['withheld_total'] ?? null;
          @endphp
          <tr data-package="{{ $p->id }}" data-status="{{ $p->status }}">
            <td>
              <span class="fw-semibold">{{ $p->title }}</span>
              @if($withheld)
                <span class="badge bg-warning text-dark ms-1" title="{{ __('Records withheld because you may not export them') }}">
                  <i class="fas fa-shield-halved"></i> {{ $withheld }} {{ __('withheld') }}
                </span>
              @endif
            </td>
            <td><span class="badge bg-light text-dark text-capitalize">{{ $p->group_source }}</span></td>
            <td>
              <span class="js-records">{{ (int) $p->total_descriptions }}</span>
              @if((int) $p->total_objects > 0)
                <span class="text-muted small">(+{{ (int) $p->total_objects }} {{ __('objects') }})</span>
              @endif
            </td>
            <td class="js-status">
              @include('research::research._offline-status', ['p' => $p])
            </td>
            <td class="text-end js-action">
              @if($p->status === 'complete')
                <a href="{{ route('research.offline.download', $p->id) }}" class="btn btn-sm btn-success">
                  <i class="fas fa-download me-1"></i>{{ __('Download') }}
                </a>
              @elseif(in_array($p->status, ['pending', 'running']))
                <span class="text-muted small">{{ __('Building…') }}</span>
              @else
                <span class="text-danger small">{{ __('Failed') }}</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
@endsection

@section('scripts')
<script>
(function () {
  // Poll build progress for any package that is still pending/processing.
  var rows = Array.prototype.slice.call(document.querySelectorAll('tr[data-package]'))
    .filter(function (r) { return ['pending', 'running'].indexOf(r.dataset.status) !== -1; });
  if (!rows.length) return;

  function poll() {
    rows.forEach(function (row) {
      if (['complete', 'failed'].indexOf(row.dataset.status) !== -1) return;
      var id = row.dataset.package;
      fetch('/research/offline/' + id + '/status', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d || !d.success) return;
          row.dataset.status = d.status;
          var st = row.querySelector('.js-status');
          var badge = d.status === 'complete' ? 'success' : (d.status === 'failed' ? 'danger' : 'info');
          var label = d.status === 'running' ? ('Building ' + (d.progress || 0) + '%') : d.status;
          if (st) st.innerHTML = '<span class="badge bg-' + badge + ' text-capitalize">' + label + '</span>';
          var rec = row.querySelector('.js-records');
          if (rec && d.total_descriptions) rec.textContent = d.total_descriptions;
          if (d.status === 'complete') {
            var act = row.querySelector('.js-action');
            if (act) act.innerHTML = '<a href="/research/offline/' + id + '/download" class="btn btn-sm btn-success"><i class="fas fa-download me-1"></i>Download</a>';
          }
        })
        .catch(function () {});
    });
    if (rows.some(function (r) { return ['pending', 'running'].indexOf(r.dataset.status) !== -1; })) {
      setTimeout(poll, 2500);
    }
  }
  setTimeout(poll, 2000);
})();
</script>
@endsection
