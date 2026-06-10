{{-- heratio#1197 Unified G/L/A/M graph: pick an entity, see everything related across collections. --}}
@extends('theme::layouts.1col')
@section('title', __('Cross-collection Connections'))

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
    <h1 class="h4 mb-0"><i class="fas fa-diagram-project me-2 text-primary"></i>{{ __('Cross-collection Connections') }}</h1>
    <span class="text-muted small">{{ __('Follow one record into everything related to it') }}</span>
    <a href="{{ route('ric.explorer') }}" class="btn btn-sm btn-outline-secondary ms-auto"><i class="fas fa-project-diagram me-1"></i>{{ __('Graph Explorer') }}</a>
  </div>
  <p class="text-muted small">{{ __('Search for a record, then see everything connected to it - people, organisations, repositories, subjects and places - grouped by collection domain.') }}</p>

  <div class="position-relative mb-3" style="max-width:680px">
    <input type="text" id="ccSearch" class="form-control" placeholder="{{ __('Search records by title or reference…') }}" autocomplete="off">
    <div id="ccSuggest" class="list-group position-absolute w-100 shadow" style="z-index:20;display:none;max-height:320px;overflow:auto"></div>
  </div>

  <div id="ccHead" class="mb-2" style="display:none"></div>
  <div id="ccErr" class="alert alert-warning" style="display:none"></div>
  <div class="row g-3" id="ccResult"></div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  var AC = '{{ route('ric.public-autocomplete') }}';
  var CONN = '{{ url('ric-api/connections') }}';
  var searchEl = document.getElementById('ccSearch'), sug = document.getElementById('ccSuggest'),
      head = document.getElementById('ccHead'), res = document.getElementById('ccResult'), errEl = document.getElementById('ccErr');
  function esc(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; }
  var tmr = null;
  searchEl.addEventListener('input', function () {
    var q = searchEl.value.trim();
    clearTimeout(tmr); if (q.length < 2) { sug.style.display = 'none'; return; }
    tmr = setTimeout(function () {
      fetch(AC + '?q=' + encodeURIComponent(q)).then(function (r) { return r.json(); }).then(function (rows) {
        sug.innerHTML = '';
        (rows || []).slice(0, 12).forEach(function (row) {
          var a = document.createElement('button'); a.type = 'button'; a.className = 'list-group-item list-group-item-action';
          a.innerHTML = '<span class="fw-bold">' + esc(row.title) + '</span>' + (row.identifier ? ' <span class="small text-muted">' + esc(row.identifier) + '</span>' : '');
          a.addEventListener('click', function () { sug.style.display = 'none'; searchEl.value = row.title; load(row.id, row.title); });
          sug.appendChild(a);
        });
        sug.style.display = rows && rows.length ? 'block' : 'none';
      }).catch(function () { sug.style.display = 'none'; });
    }, 250);
  });
  document.addEventListener('click', function (e) { if (!sug.contains(e.target) && e.target !== searchEl) sug.style.display = 'none'; });

  function load(id, title) {
    errEl.style.display = 'none'; res.innerHTML = ''; head.style.display = 'none';
    fetch(CONN + '/' + id).then(function (r) { return r.json(); }).then(function (d) {
      if (!d || !d.success) { errEl.style.display = 'block'; errEl.textContent = '{{ __('Could not load connections.') }}'; return; }
      head.style.display = 'block';
      head.innerHTML = '<span class="badge bg-primary">' + esc(title) + '</span> <span class="text-muted small">' + d.total + ' {{ __('connections across the collection') }}</span>';
      if (!d.groups || !d.groups.length) { res.innerHTML = '<div class="col"><div class="text-muted">{{ __('No related entities recorded for this record yet.') }}</div></div>'; return; }
      d.groups.forEach(function (g) {
        var col = document.createElement('div'); col.className = 'col-md-6 col-xl-4';
        var items = g.items.map(function (it) {
          var label = esc(it.name);
          return '<li class="small border-bottom py-1">' + (it.slug ? '<a href="/' + encodeURIComponent(it.slug) + '" target="_blank" rel="noopener">' + label + '</a>' : label) + '</li>';
        }).join('');
        col.innerHTML = '<div class="card h-100"><div class="card-header py-2"><strong>' + esc(g.domain) + '</strong> <span class="badge bg-secondary ms-1">' + g.count + '</span></div>'
          + '<div class="card-body p-2"><ul class="list-unstyled mb-0">' + items + '</ul></div></div>';
        res.appendChild(col);
      });
    }).catch(function () { errEl.style.display = 'block'; errEl.textContent = '{{ __('Could not load connections.') }}'; });
  }
})();
</script>
@endsection
