{{--
  Grid entry (heratio#1581): type or paste many descriptions under one parent.
  Port of the AHG Extensions for AtoM grid entry (atom-ahg-plugins#208).
  Saving validates the rows server-side, stages them as an ingest session and
  hands over to the ingest wizard's commit step.
--}}
@extends('theme::layouts.1col')
@section('title', __('Grid entry'))

@php
  $labels = [];
  foreach ($columns as $field => $label) { $labels[$field] = __($label); }
@endphp

@section('content')
<h1>{{ __('Grid entry') }}</h1>

<nav aria-label="{{ __('breadcrumb') }}">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ingest.index') }}">{{ __('Ingestion Manager') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Grid entry') }}</li>
  </ol>
</nav>

<style nonce="{{ csp_nonce() }}">
#grid-table { table-layout: fixed; min-width: 1100px; }
#grid-table td { padding: 0; vertical-align: top; }
#grid-table td.grid-rownum { padding: .35rem .4rem; text-align: right; color: #6c757d; width: 3.2rem; font-size: .85rem; }
#grid-table td.grid-actions { width: 11.5rem; white-space: nowrap; padding: .25rem .4rem; }
#grid-table td.grid-actions .btn { padding: .1rem .45rem; font-size: .8rem; }
#grid-table .grid-cell { border: 0; border-radius: 0; width: 100%; padding: .3rem .4rem; font-size: .9rem; background: transparent; }
#grid-table .grid-cell:focus { outline: 2px solid var(--bs-primary, #0d6efd); outline-offset: -2px; background: #fff; box-shadow: none; }
#grid-table textarea.grid-cell { resize: vertical; min-height: 2rem; }
#grid-table .grid-cell.is-invalid { background: #f8d7da; }
#grid-table th { font-size: .85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#grid-table col.c-identifier { width: 9rem; }
#grid-table col.c-title { width: 18rem; }
#grid-table col.c-levelOfDescription { width: 9rem; }
#grid-table col.c-creationDates { width: 9rem; }
#grid-table col.c-creationDatesStart, #grid-table col.c-creationDatesEnd { width: 7.5rem; }
#grid-table col.c-extentAndMedium { width: 10rem; }
#grid-table col.c-scopeAndContent { width: 22rem; }
#grid-scroll { overflow-x: auto; }
#existing-table td { font-size: .85rem; }
#parent-results { position: absolute; z-index: 20; width: 100%; max-height: 18rem; overflow-y: auto; }
#parent-box { max-width: 36rem; }
</style>

<div class="card mb-4">
  <div class="card-header"><h2 class="h5 mb-0"><i class="fas fa-sitemap me-2" aria-hidden="true"></i>{{ __('Parent description') }}</h2></div>
  <div class="card-body">
    @if($parent)
      <p class="mb-2">
        {{ __('New descriptions will be added below:') }}
        <a href="{{ url('/'.$parent->slug) }}"><strong>{{ $parent->title ?: $parent->slug }}</strong></a>
        @if($parent->identifier)<span class="text-muted">({{ $parent->identifier }})</span>@endif
      </p>
    @else
      <p class="mb-2">{{ __('Choose the description the new records belong under. To start a new batch, create the parent (for example a new series) first, then choose it here.') }}</p>
    @endif
    <div class="position-relative" id="parent-box">
      <label for="parent-search" class="form-label visually-hidden">{{ __('Find a parent description') }}</label>
      <input type="search" class="form-control" id="parent-search" autocomplete="off"
             placeholder="{{ __('Type at least two letters of a title to find a parent...') }}">
      <div id="parent-results" class="list-group shadow-sm"></div>
    </div>
  </div>
</div>

@if($parent)

@if(!empty($children))
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h2 class="h5 mb-0"><i class="fas fa-list me-2" aria-hidden="true"></i>{{ __('Existing descriptions under this parent (:n)', ['n' => count($children)]) }}</h2>
    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#existing-wrap" aria-expanded="false" aria-controls="existing-wrap">{{ __('Show / hide') }}</button>
  </div>
  <div class="collapse" id="existing-wrap">
    <div class="card-body p-0 table-responsive">
      <table class="table table-sm table-striped mb-0" id="existing-table">
        <thead>
          <tr>
            <th>{{ $labels['identifier'] }}</th><th>{{ $labels['title'] }}</th><th>{{ $labels['levelOfDescription'] }}</th>
            <th>{{ __('Dates') }}</th><th>{{ $labels['extentAndMedium'] }}</th><th>{{ $labels['scopeAndContent'] }}</th>
          </tr>
        </thead>
        <tbody>
        @foreach($children as $c)
          @php $d = explode('|', (string) $c->dates) + ['', '', '']; @endphp
          <tr>
            <td>{{ (string) $c->identifier }}</td>
            <td>@if($c->slug)<a href="{{ url('/'.$c->slug) }}">{{ $c->title ?: $c->slug }}</a>@else{{ $c->title }}@endif</td>
            <td>{{ (string) $c->level }}</td>
            <td>{{ $d[0] ?: trim($d[1].' - '.$d[2], ' -') }}</td>
            <td>{{ (string) $c->extent }}</td>
            <td>{{ mb_strimwidth(strip_tags((string) $c->scope), 0, 160, '...') }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endif

<div class="card mb-4">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h2 class="h5 mb-0"><i class="fas fa-table me-2" aria-hidden="true"></i>{{ __('New descriptions') }}</h2>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-add-row"><i class="fas fa-plus me-1" aria-hidden="true"></i>{{ __('Add row') }}</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-add-10"><i class="fas fa-plus me-1" aria-hidden="true"></i>{{ __('Add 10 rows') }}</button>
    </div>
  </div>
  <div class="card-body">
    <p class="text-muted small mb-2">
      {{ __('Type into the cells, or copy a block of cells from Excel or another spreadsheet and paste it into the grid: it fills from the cell you are in and adds rows as needed. If the first copied row holds column headings that match the grid, the columns are matched by heading. Dates as YYYY, YYYY-MM or YYYY-MM-DD. Empty rows are ignored. New descriptions are saved as drafts.') }}
    </p>
    <div id="grid-alert" class="alert d-none" role="alert"></div>
    <div id="grid-scroll" class="border rounded">
      <table class="table table-bordered mb-0" id="grid-table">
        <colgroup>
          <col><col>
          @foreach($columns as $field => $label)<col class="c-{{ $field }}">@endforeach
        </colgroup>
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th><span class="visually-hidden">{{ __('Row actions') }}</span></th>
            @foreach($columns as $field => $label)
              <th title="{{ $labels[$field] }}">{{ $labels[$field] }}@if($field === 'title') <span class="text-danger">*</span>@endif</th>
            @endforeach
          </tr>
        </thead>
        <tbody id="grid-body"></tbody>
      </table>
    </div>
    <datalist id="grid-levels">
      @foreach($levels as $l)<option value="{{ $l }}"></option>@endforeach
    </datalist>
  </div>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <a href="{{ route('ingest.index') }}" class="btn btn-outline-secondary">{{ __('Back to Ingestion Manager') }}</a>
    <div>
      <span class="text-muted small me-3" id="grid-count"></span>
      <button type="button" class="btn btn-primary" id="btn-save"><i class="fas fa-save me-1" aria-hidden="true"></i>{{ __('Validate & save') }}</button>
    </div>
  </div>
</div>

@endif

<script nonce="{{ csp_nonce() }}">
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var token = document.querySelector('meta[name="csrf-token"]')?.content || '';

  // ---- Parent search (the ingest wizard's parent lookup) ----
  var searchUrl = @json(route('informationobject.autocomplete'));
  var gridUrl = @json(route('ingest.grid'));
  var search = document.getElementById('parent-search');
  var results = document.getElementById('parent-results');
  var timer = null;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'})[c];
    });
  }

  search.addEventListener('input', function () {
    clearTimeout(timer);
    var q = search.value.trim();
    if (q.length < 2) { results.innerHTML = ''; return; }
    timer = setTimeout(function () {
      fetch(searchUrl + '?query=' + encodeURIComponent(q), {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
        .then(function (r) { return r.json(); })
        .then(function (list) {
          results.innerHTML = '';
          (Array.isArray(list) ? list : (list.results || list.data || [])).forEach(function (it) {
            var a = document.createElement('a');
            a.href = gridUrl + '?parent=' + encodeURIComponent(it.id);
            a.className = 'list-group-item list-group-item-action';
            a.innerHTML = esc(it.name || it.title || it.slug) + (it.identifier ? ' <small class="text-muted">(' + esc(it.identifier) + ')</small>' : '');
            results.appendChild(a);
          });
          if (!results.children.length) {
            results.innerHTML = '<div class="list-group-item text-muted">' + esc(@json(__('No matches'))) + '</div>';
          }
        }).catch(function () { results.innerHTML = ''; });
    }, 250);
  });

  var body = document.getElementById('grid-body');
  if (!body) { return; }

  // ---- Grid ----
  var parentId = {{ (int) ($parent->id ?? 0) }};
  var saveUrl = @json(route('ingest.grid.save'));
  var fields = @json(array_keys($columns));
  var labels = @json($labels);
  var maxRows = {{ (int) $maxRows }};
  var msg = {
    removeRow: @json(__('Delete')),
    removeRowLabel: @json(__('Delete row :n')),
    duplicateRow: @json(__('Duplicate')),
    duplicateRowLabel: @json(__('Duplicate row :n below it')),
    tooMany: @json(__('The grid holds at most :n rows.', ['n' => $maxRows])),
    saving: @json(__('Validating and saving...')),
    failed: @json(__('Saving failed. Nothing was written.')),
    rowsFilled: @json(__(':n rows filled')),
    review: @json(__('Review in the ingest wizard')),
    rowWord: @json(__('Row')),
    leave: @json(__('The grid has unsaved rows.'))
  };
  var dirty = false;
  var submitting = false;

  function makeCell(field) {
    var el;
    if (field === 'scopeAndContent') {
      el = document.createElement('textarea');
      el.rows = 1;
    } else {
      el = document.createElement('input');
      el.type = 'text';
      if (field === 'levelOfDescription') { el.setAttribute('list', 'grid-levels'); }
      if (field === 'creationDatesStart' || field === 'creationDatesEnd') { el.placeholder = 'YYYY-MM-DD'; }
    }
    el.className = 'form-control grid-cell';
    el.dataset.field = field;
    el.setAttribute('aria-label', labels[field]);
    return el;
  }

  function addRow(values) {
    if (body.rows.length >= maxRows) { showAlert('warning', esc(msg.tooMany)); return null; }
    var tr = document.createElement('tr');
    var num = document.createElement('td');
    num.className = 'grid-rownum';
    tr.appendChild(num);
    // Row actions sit next to the number, so they stay in view on a wide grid.
    var actions = document.createElement('td');
    actions.className = 'grid-actions';
    [['grid-dup', 'btn-outline-secondary', 'fa-copy', msg.duplicateRow], ['grid-del', 'btn-outline-danger', 'fa-trash', msg.removeRow]].forEach(function (b) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-sm ' + b[1] + ' ' + b[0] + ' me-1';
      btn.innerHTML = '<i class="fas ' + b[2] + ' me-1" aria-hidden="true"></i>' + esc(b[3]);
      actions.appendChild(btn);
    });
    tr.appendChild(actions);
    fields.forEach(function (f) {
      var td = document.createElement('td');
      var cell = makeCell(f);
      if (values && values[f] != null) { cell.value = values[f]; }
      td.appendChild(cell);
      tr.appendChild(td);
    });
    body.appendChild(tr);
    renumber();
    return tr;
  }

  function renumber() {
    var filled = 0;
    Array.prototype.forEach.call(body.rows, function (tr, i) {
      tr.cells[0].textContent = i + 1;
      tr.querySelector('.grid-dup').setAttribute('aria-label', msg.duplicateRowLabel.replace(':n', i + 1));
      tr.querySelector('.grid-del').setAttribute('aria-label', msg.removeRowLabel.replace(':n', i + 1));
      if (rowValues(tr).some(function (v) { return v.trim() !== ''; })) { filled++; }
    });
    document.getElementById('grid-count').textContent = msg.rowsFilled.replace(':n', filled);
  }

  function rowValues(tr) {
    return Array.prototype.map.call(tr.querySelectorAll('.grid-cell'), function (c) { return c.value; });
  }

  function cellAt(r, c) {
    var tr = body.rows[r];
    return tr ? tr.querySelectorAll('.grid-cell')[c] : null;
  }

  function position(cell) {
    var tr = cell.closest('tr');
    return {row: Array.prototype.indexOf.call(body.rows, tr), col: fields.indexOf(cell.dataset.field)};
  }

  body.addEventListener('click', function (e) {
    var dup = e.target.closest('.grid-dup');
    if (dup) {
      var src = dup.closest('tr');
      var values = {};
      src.querySelectorAll('.grid-cell').forEach(function (c) { values[c.dataset.field] = c.value; });
      var copy = addRow(values);
      if (copy) {
        src.after(copy);
        copy.querySelector('.grid-cell').focus();
        dirty = true;
        renumber();
      }
      return;
    }
    var btn = e.target.closest('.grid-del');
    if (!btn) { return; }
    btn.closest('tr').remove();
    if (!body.rows.length) { addRow(); }
    dirty = true;
    renumber();
  });

  body.addEventListener('input', function (e) {
    if (e.target.classList.contains('grid-cell')) {
      dirty = true;
      e.target.classList.remove('is-invalid');
      e.target.removeAttribute('title');
    }
  });
  body.addEventListener('change', renumber);

  // Arrow up / down moves between rows (single-line cells only); Enter on
  // the last row adds a new one.
  body.addEventListener('keydown', function (e) {
    var cell = e.target;
    if (!cell.classList.contains('grid-cell') || cell.tagName === 'TEXTAREA') { return; }
    // The level cell's suggestion list uses the arrow keys itself.
    if (cell.dataset.field === 'levelOfDescription' && e.key !== 'Enter') { return; }
    var p = position(cell);
    var next = null;
    if (e.key === 'ArrowDown' || e.key === 'Enter') {
      if (p.row === body.rows.length - 1 && e.key === 'Enter') { addRow(); }
      next = cellAt(p.row + 1, p.col);
    } else if (e.key === 'ArrowUp') {
      next = cellAt(p.row - 1, p.col);
    }
    if (next) { e.preventDefault(); next.focus(); }
  });

  // Tab-separated text as spreadsheets put it on the clipboard: quoted
  // cells may hold tabs, newlines and doubled quotes.
  function parseTsv(text) {
    var rows = [], row = [], cell = '', quoted = false, i = 0, ch;
    text = text.replace(/\r\n?/g, '\n');
    while (i < text.length) {
      ch = text.charAt(i);
      if (quoted) {
        if (ch === '"') {
          if (text.charAt(i + 1) === '"') { cell += '"'; i += 2; continue; }
          quoted = false; i++; continue;
        }
        cell += ch; i++; continue;
      }
      if (ch === '"' && cell === '') { quoted = true; i++; continue; }
      if (ch === '\t') { row.push(cell); cell = ''; i++; continue; }
      if (ch === '\n') { row.push(cell); rows.push(row); row = []; cell = ''; i++; continue; }
      cell += ch; i++;
    }
    if (cell !== '' || row.length) { row.push(cell); rows.push(row); }
    return rows;
  }

  function norm(s) { return String(s).toLowerCase().replace(/[^a-z0-9]/g, ''); }

  // Field for a pasted heading, or null. Matches the grid labels and the
  // CSV column names (scopeAndContent etc.).
  function headingField(h) {
    var n = norm(h);
    if (!n) { return null; }
    for (var i = 0; i < fields.length; i++) {
      if (n === norm(fields[i]) || n === norm(labels[fields[i]])) { return fields[i]; }
    }
    return null;
  }

  body.addEventListener('paste', function (e) {
    var cell = e.target;
    if (!cell.classList.contains('grid-cell')) { return; }
    var text = (e.clipboardData || window.clipboardData).getData('text');
    if (!text || (text.indexOf('\t') === -1 && text.indexOf('\n') === -1 && text.indexOf('\r') === -1)) { return; }
    var data = parseTsv(text);
    if (!data.length) { return; }
    e.preventDefault();

    var start = position(cell);
    var map = null;
    var heads = data[0].map(headingField);
    if (heads.some(Boolean) && data[0].every(function (h, i) { return heads[i] || String(h).trim() === ''; })) {
      map = heads;
      data.shift();
    }

    data.forEach(function (vals, r) {
      var rowIndex = start.row + r;
      while (rowIndex >= body.rows.length) {
        if (!addRow()) { return; }
      }
      vals.forEach(function (v, c) {
        var field = map ? map[c] : fields[start.col + c];
        if (!field) { return; }
        var target = cellAt(rowIndex, fields.indexOf(field));
        if (target) {
          target.value = v.trim();
          target.classList.remove('is-invalid');
        }
      });
    });
    dirty = true;
    renumber();
  });

  function showAlert(kind, html) {
    var a = document.getElementById('grid-alert');
    a.className = 'alert alert-' + kind;
    a.innerHTML = html;
  }

  function collect() {
    return Array.prototype.map.call(body.rows, function (tr) {
      var o = {};
      tr.querySelectorAll('.grid-cell').forEach(function (c) { o[c.dataset.field] = c.value; });
      return o;
    });
  }

  function markErrors(errors) {
    var list = [];
    Object.keys(errors).forEach(function (idx) {
      Object.keys(errors[idx]).forEach(function (field) {
        var c = cellAt(parseInt(idx, 10), fields.indexOf(field));
        if (c) { c.classList.add('is-invalid'); c.title = errors[idx][field]; }
        list.push('<li>' + esc(msg.rowWord) + ' ' + (parseInt(idx, 10) + 1) + ', ' + esc(labels[field] || field) + ': ' + esc(errors[idx][field]) + '</li>');
      });
    });
    return list.length ? '<ul class="mb-0 mt-2">' + list.slice(0, 30).join('') + (list.length > 30 ? '<li>...</li>' : '') + '</ul>' : '';
  }

  document.getElementById('btn-add-row').addEventListener('click', function () { var tr = addRow(); if (tr) { tr.querySelector('.grid-cell').focus(); } });
  document.getElementById('btn-add-10').addEventListener('click', function () { for (var i = 0; i < 10; i++) { addRow(); } });

  document.getElementById('btn-save').addEventListener('click', function () {
    var btn = this;
    body.querySelectorAll('.is-invalid').forEach(function (c) { c.classList.remove('is-invalid'); c.removeAttribute('title'); });
    btn.disabled = true;
    showAlert('info', esc(msg.saving));
    fetch(saveUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
      body: JSON.stringify({parent_id: parentId, rows: collect()})
    }).then(function (r) {
      return r.json().catch(function () { return {error: msg.failed}; });
    }).then(function (res) {
      if (res && res.ok && res.commit_url) {
        // Hand over to the ingest wizard's commit step, exactly as its own
        // "Start commit" button does.
        submitting = true;
        var f = document.createElement('form');
        f.method = 'post';
        f.action = res.commit_url;
        var t = document.createElement('input');
        t.type = 'hidden'; t.name = '_token'; t.value = token;
        f.appendChild(t);
        document.body.appendChild(f);
        f.submit();
        return;
      }
      btn.disabled = false;
      var html = esc((res && res.error) || msg.failed);
      if (res && res.errors) { html += markErrors(res.errors); }
      showAlert('danger', html);
    }).catch(function () {
      btn.disabled = false;
      showAlert('danger', esc(msg.failed));
    });
  });

  window.addEventListener('beforeunload', function (e) {
    if (dirty && !submitting) { e.preventDefault(); e.returnValue = msg.leave; return msg.leave; }
  });

  for (var i = 0; i < 10; i++) { addRow(); }
  dirty = false;
});
</script>
@endsection
