{{--
  Grid editing of archival descriptions (heratio#1581).

  A real ARIA grid: one cell in the tab order, arrow keys move, Enter (or F2)
  edits, Escape cancels, Enter or Tab keeps the change. Each row is saved on
  its own (its Save button, or Ctrl+S in the row) through
  InformationObjectService::update, and refused when the record changed
  after the grid loaded. Staff with update permission only (route acl:update).
--}}
@extends('theme::layouts.1col')
@section('title', __('Grid editing'))

@section('content')
<h1 id="grid-heading">{{ __('Grid editing') }}</h1>

<p class="mb-2">
  @if($collection)
    {{ __('Descriptions in') }}
    <a href="{{ url('/'.$collection->slug) }}"><strong>{{ $collection->title ?: $collection->slug }}</strong></a>
  @elseif($rows)
    {{ __('Selected descriptions') }}
  @else
    {{ __('Open the grid from a collection (its More menu) or from browse results.') }}
  @endif
</p>

@if($rows)
<style nonce="{{ csp_nonce() }}">
  #desc-grid-wrap { overflow-x: auto; }
  #desc-grid { width: max-content; min-width: 100%; font-size: .875rem; }
  #desc-grid th, #desc-grid td { vertical-align: top; }
  #desc-grid td[role="gridcell"] { min-width: 8rem; max-width: 24rem; cursor: default; }
  #desc-grid td[role="gridcell"].g-long { min-width: 16rem; }
  #desc-grid td[role="gridcell"]:focus { outline: 2px solid var(--bs-primary, #0d6efd); outline-offset: -2px; }
  #desc-grid .g-val { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; white-space: pre-wrap; word-break: break-word; }
  #desc-grid td.g-dirty { background: var(--bs-warning-bg-subtle, #fff3cd); }
  #desc-grid tr[aria-readonly="true"] td[role="gridcell"] { color: var(--bs-secondary-color, #6c757d); }
  #desc-grid .g-editor { width: 100%; min-width: 12rem; font-size: .875rem; }
  #desc-grid textarea.g-editor { min-height: 6rem; }
  #desc-grid .g-status { white-space: nowrap; }
</style>

<div class="alert alert-info small py-2">
  {{ __('Arrow keys move between cells. Enter or F2 edits a cell, Escape cancels, Enter or Tab keeps the change (Shift+Enter starts a new line in long text). Ctrl+S, or the Save button, saves the row. Changes are saved in the current language (:lang); for a record described in another language they are saved as its translation.', ['lang' => $culture]) }}
</div>

<details class="mb-3" id="grid-columns">
  <summary class="btn btn-sm btn-outline-secondary">{{ __('Columns') }}</summary>
  <fieldset class="border rounded p-2 mt-2">
    <legend class="visually-hidden">{{ __('Columns to show') }}</legend>
    <div class="d-flex flex-wrap gap-3">
      @foreach($columns as $field => $label)
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="gc-{{ $field }}" value="{{ $field }}" @checked(in_array($field, $defaultColumns, true))>
          <label class="form-check-label" for="gc-{{ $field }}">{{ $label }}</label>
        </div>
      @endforeach
    </div>
  </fieldset>
</details>

<div id="grid-live" class="visually-hidden" role="status" aria-live="polite"></div>

<div id="desc-grid-wrap" class="border rounded">
  <table class="table table-sm table-bordered mb-0" id="desc-grid" role="grid" aria-labelledby="grid-heading" aria-rowcount="{{ $rows->total() + 1 }}">
    <thead class="table-light">
      <tr role="row" aria-rowindex="1">
        <th role="columnheader" scope="col">{{ __('Record') }}</th>
        @foreach($columns as $field => $label)
          <th role="columnheader" scope="col" data-col="{{ $field }}" @if(!in_array($field, $defaultColumns, true)) hidden @endif>{{ $label }}</th>
        @endforeach
        <th role="columnheader" scope="col">{{ __('Row') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $i => $row)
        @php $rowLabel = ($row->identifier ? $row->identifier.' - ' : '').($row->title ?: __('Untitled')); @endphp
        <tr role="row" aria-rowindex="{{ $rows->firstItem() + $i + 1 }}" data-id="{{ $row->id }}" data-updated="{{ $row->updated_at }}"
            @unless($row->editable) aria-readonly="true" @endunless>
          <th role="rowheader" scope="row" tabindex="-1">
            @if($row->slug)<a href="{{ url('/'.$row->slug) }}" tabindex="-1">{{ $rowLabel }}</a>@else{{ $rowLabel }}@endif
            @if($row->source_culture && $row->source_culture !== $culture)
              <span class="badge text-bg-light border" title="{{ __('Described in :lang', ['lang' => $row->source_culture]) }}">{{ $row->source_culture }}</span>
            @endif
          </th>
          @foreach($columns as $field => $label)
            @php
              $raw = $row->{$field} ?? null;
              $shown = $field === 'level_of_description_id' ? ($levels[$raw] ?? '') : (string) $raw;
            @endphp
            <td role="gridcell" tabindex="-1" data-col="{{ $field }}" data-value="{{ $raw }}"
                class="{{ in_array($field, $longText, true) ? 'g-long' : '' }}"
                @unless($row->editable) aria-readonly="true" @endunless
                @if(!in_array($field, $defaultColumns, true)) hidden @endif><div class="g-val">{{ $shown }}</div></td>
          @endforeach
          <td class="g-status">
            @if($row->editable)
              <button type="button" class="btn btn-sm btn-outline-primary g-save" disabled
                      aria-label="{{ __('Save row: :r', ['r' => $rowLabel]) }}">{{ __('Save') }}</button>
            @elseif(! $row->readable)
              <span class="small text-muted">{{ __('Withheld by a rights policy') }}</span>
            @else
              <span class="small text-muted">{{ __('Read-only') }}</span>
            @endif
            <span class="small g-msg ms-1"></span>
          </td>
        </tr>
      @empty
        <tr><td colspan="{{ count($columns) + 2 }}" class="text-muted">{{ __('No descriptions to show.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $rows->links() }}</div>

<select id="grid-level-options" hidden aria-hidden="true">
  <option value=""></option>
  @foreach($levels as $lid => $lname)<option value="{{ $lid }}">{{ $lname }}</option>@endforeach
</select>

<script nonce="{{ csp_nonce() }}">
(function () {
  'use strict';
  var grid = document.getElementById('desc-grid');
  var live = document.getElementById('grid-live');
  var saveUrl = @json(route('informationobject.grid.save'));
  var longText = @json($longText);
  var levelOptions = document.getElementById('grid-level-options');
  var STORE = 'heratio.descGrid.columns';
  var msg = {
    saving: @json(__('Saving...')),
    saved: @json(__('Saved.')),
    failed: @json(__('Not saved.')),
    conflict: @json(__('Changed by someone else - reload to see it. Not saved.')),
    readOnly: @json(__('This row is read-only.')),
    unsaved: @json(__('The grid has unsaved rows.')),
    editing: @json(__('Editing')),
  };

  function say(text) { live.textContent = ''; setTimeout(function () { live.textContent = text; }, 30); }

  // ---- columns, remembered per user in this browser --------------------
  var boxes = Array.prototype.slice.call(document.querySelectorAll('#grid-columns input[type=checkbox]'));
  function applyColumns(list) {
    boxes.forEach(function (b) {
      b.checked = list.indexOf(b.value) >= 0;
      grid.querySelectorAll('[data-col="' + b.value + '"]').forEach(function (el) { el.hidden = !b.checked; });
    });
    var active = grid.querySelector('[tabindex="0"]');
    if (active && active.hidden) { setActive(grid.querySelector('tbody tr[data-id] th[role=rowheader]')); }
  }
  var stored = null;
  try { stored = JSON.parse(window.localStorage.getItem(STORE) || 'null'); } catch (e) { stored = null; }
  if (Array.isArray(stored) && stored.length) { applyColumns(stored); }
  boxes.forEach(function (b) {
    b.addEventListener('change', function () {
      var list = boxes.filter(function (x) { return x.checked; }).map(function (x) { return x.value; });
      applyColumns(list);
      try { window.localStorage.setItem(STORE, JSON.stringify(list)); } catch (e) { /* private mode: not remembered */ }
    });
  });

  // ---- roving focus over visible cells --------------------------------
  function rows() { return Array.prototype.slice.call(grid.querySelectorAll('tbody tr[data-id]')); }
  function cellsOf(tr) {
    return Array.prototype.filter.call(tr.querySelectorAll('th[role=rowheader], td[role=gridcell]'), function (c) { return !c.hidden; });
  }
  function setActive(cell) {
    if (!cell) { return; }
    grid.querySelectorAll('[tabindex="0"]').forEach(function (c) { if (c !== cell) { c.tabIndex = -1; } });
    cell.tabIndex = 0;
    cell.focus();
  }
  var first = grid.querySelector('tbody tr[data-id] th[role=rowheader]');
  if (first) { first.tabIndex = 0; }

  function moveFocus(cell, dr, dc, abs) {
    var tr = cell.closest('tr'), all = rows();
    var r = all.indexOf(tr), cs = cellsOf(tr), c = cs.indexOf(cell);
    if (abs === 'home') { c = 0; } else if (abs === 'end') { c = cs.length - 1; }
    else if (abs === 'first') { r = 0; c = 0; } else if (abs === 'last') { r = all.length - 1; c = cellsOf(all[r]).length - 1; }
    else { r = Math.max(0, Math.min(all.length - 1, r + dr)); c = c + dc; }
    var target = cellsOf(all[r]);
    setActive(target[Math.max(0, Math.min(target.length - 1, c))]);
  }

  // ---- editing --------------------------------------------------------
  var editing = null; // {cell, editor, before}

  function startEdit(cell) {
    if (cell.getAttribute('role') !== 'gridcell') { return; }
    if (cell.getAttribute('aria-readonly') === 'true') { say(msg.readOnly); return; }
    var col = cell.dataset.col, value = cell.dataset.value || '', ed;
    if (col === 'level_of_description_id') {
      ed = levelOptions.cloneNode(true);
      ed.removeAttribute('id'); ed.hidden = false; ed.removeAttribute('aria-hidden');
      ed.className = 'form-select form-select-sm g-editor';
      ed.value = value;
    } else if (longText.indexOf(col) >= 0) {
      ed = document.createElement('textarea');
      ed.className = 'form-control form-control-sm g-editor';
      ed.value = value;
    } else {
      ed = document.createElement('input');
      ed.type = 'text';
      ed.className = 'form-control form-control-sm g-editor';
      ed.value = value;
    }
    var header = grid.querySelector('thead th[data-col="' + col + '"]');
    ed.setAttribute('aria-label', msg.editing + ': ' + (header ? header.textContent.trim() : col));
    editing = { cell: cell, editor: ed, before: cell.innerHTML };
    cell.innerHTML = '';
    cell.appendChild(ed);
    ed.focus();
    ed.addEventListener('keydown', editorKeys);
    ed.addEventListener('blur', function () { if (editing && editing.editor === ed) { endEdit(true, null); } });
  }

  function endEdit(keep, then) {
    var e = editing; if (!e) { return; }
    editing = null;
    var cell = e.cell, val = e.editor.value;
    if (keep && val !== (cell.dataset.value || '')) {
      cell.dataset.value = val;
      cell.classList.add('g-dirty');
      markDirty(cell.closest('tr'));
    }
    var shown = cell.dataset.col === 'level_of_description_id'
      ? (levelOptions.querySelector('option[value="' + CSS.escape(cell.dataset.value || '') + '"]') || {}).textContent || ''
      : (cell.dataset.value || '');
    if (keep) {
      cell.innerHTML = '';
      var div = document.createElement('div'); div.className = 'g-val'; div.textContent = shown;
      cell.appendChild(div);
    } else {
      cell.innerHTML = e.before;
    }
    if (then) { then(cell); } else { setActive(cell); }
  }

  function editorKeys(ev) {
    var isArea = ev.target.tagName === 'TEXTAREA';
    if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); endEdit(false); }
    else if (ev.key === 'Enter' && !(isArea && ev.shiftKey)) { ev.preventDefault(); ev.stopPropagation(); endEdit(true); }
    else if (ev.key === 'Tab') {
      ev.preventDefault(); ev.stopPropagation();
      var back = ev.shiftKey;
      endEdit(true, function (cell) { moveFocus(cell, 0, back ? -1 : 1); });
    } else if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 's') {
      ev.preventDefault(); ev.stopPropagation();
      endEdit(true, function (cell) { setActive(cell); saveRow(cell.closest('tr')); });
    }
  }

  grid.addEventListener('keydown', function (ev) {
    if (editing) { return; }
    var cell = ev.target.closest('th[role=rowheader], td[role=gridcell]');
    if (!cell) { return; }
    switch (ev.key) {
      case 'ArrowRight': moveFocus(cell, 0, 1); break;
      case 'ArrowLeft': moveFocus(cell, 0, -1); break;
      case 'ArrowDown': moveFocus(cell, 1, 0); break;
      case 'ArrowUp': moveFocus(cell, -1, 0); break;
      case 'Home': moveFocus(cell, 0, 0, ev.ctrlKey ? 'first' : 'home'); break;
      case 'End': moveFocus(cell, 0, 0, ev.ctrlKey ? 'last' : 'end'); break;
      case 'Enter':
      case 'F2':
        if (cell.getAttribute('role') === 'rowheader') {
          var a = cell.querySelector('a'); if (a && ev.key === 'Enter') { a.click(); }
          break;
        }
        startEdit(cell); break;
      case 's':
      case 'S':
        if (!(ev.ctrlKey || ev.metaKey)) { return; }
        saveRow(cell.closest('tr')); break;
      default: return;
    }
    ev.preventDefault();
  });
  grid.addEventListener('click', function (ev) {
    var cell = ev.target.closest('th[role=rowheader], td[role=gridcell]');
    if (cell && !editing) { setActive(cell); }
  });
  grid.addEventListener('dblclick', function (ev) {
    var cell = ev.target.closest('td[role=gridcell]');
    if (cell && !editing) { startEdit(cell); }
  });

  // ---- saving, one row at a time --------------------------------------
  function markDirty(tr) {
    tr.dataset.dirty = '1';
    var b = tr.querySelector('.g-save'); if (b) { b.disabled = false; }
    tr.querySelector('.g-msg').textContent = '';
  }

  function saveRow(tr) {
    if (!tr || tr.dataset.dirty !== '1' || tr.dataset.saving === '1') { return; }
    var fields = {};
    tr.querySelectorAll('td.g-dirty').forEach(function (c) { fields[c.dataset.col] = c.dataset.value; });
    var out = tr.querySelector('.g-msg'), btn = tr.querySelector('.g-save');
    tr.dataset.saving = '1';
    out.className = 'small g-msg ms-1 text-muted'; out.textContent = msg.saving;
    fetch(saveUrl, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '' },
      body: JSON.stringify({ id: tr.dataset.id, updated_at: tr.dataset.updated, fields: fields })
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) { return { status: r.status, d: d }; });
    }).then(function (res) {
      tr.dataset.saving = '';
      if (res.status === 200 && res.d.ok) {
        tr.dataset.updated = res.d.updated_at;
        tr.dataset.dirty = '';
        tr.querySelectorAll('td.g-dirty').forEach(function (c) { c.classList.remove('g-dirty'); });
        if (btn) { btn.disabled = true; }
        out.className = 'small g-msg ms-1 text-success'; out.textContent = msg.saved;
        say(msg.saved);
        return;
      }
      var text = res.status === 409 ? msg.conflict : (res.d.error || msg.failed);
      out.className = 'small g-msg ms-1 text-danger'; out.textContent = text;
      say(text);
    }).catch(function () {
      tr.dataset.saving = '';
      out.className = 'small g-msg ms-1 text-danger'; out.textContent = msg.failed;
      say(msg.failed);
    });
  }

  grid.querySelectorAll('.g-save').forEach(function (b) {
    b.addEventListener('click', function () { saveRow(b.closest('tr')); });
  });

  window.addEventListener('beforeunload', function (e) {
    if (grid.querySelector('tr[data-dirty="1"]')) { e.preventDefault(); e.returnValue = msg.unsaved; return msg.unsaved; }
  });
})();
</script>
@endif
@endsection
