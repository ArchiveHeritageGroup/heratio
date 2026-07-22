@extends('theme::layouts.1col')

@section('title', 'FS-Scotland Indexer')
@section('body-class', 'admin ai htr')

@push('css')
<style>
  #fs-grid-wrap { max-height: 64vh; overflow: auto; }
  #fs-grid { font-size: 0.76rem; white-space: nowrap; }
  #fs-grid th { position: sticky; top: 0; background: #f1f3f5; z-index: 2; }
  #fs-grid td { padding: 0; }
  #fs-grid input { border: 0; width: 11ch; padding: 2px 4px; font-size: 0.76rem; background: transparent; }
  #fs-grid input:focus { outline: 2px solid #0d6efd; background: #fff; }
  #fs-grid tr.fs-active td { background: #e8f0fe; }
  #fs-grid tr.fs-nonrec input { color: #adb5bd; font-style: italic; }
  #fs-preview-panel { position: sticky; top: 1rem; }
  #fs-preview { width: 100%; display: block; border: 1px solid #dee2e6; border-radius: 4px; background: #000; }
</style>
@endpush

@section('content')
<div class="d-flex align-items-center mb-3">
  <h1 class="h4 mb-0"><i class="fas fa-table me-2"></i>{{ __('FS-Scotland Indexer') }}</h1>
  <span class="small text-muted ms-3">{{ __('Batch grid: list a DGS folder → read per image → Data Safe CSV') }}</span>
  <a href="{{ route('admin.ai.htr.fsOverlay') }}" class="btn btn-primary btn-sm ms-auto"><i class="fas fa-vector-square me-1"></i>{{ __('Visual correction (FS Overlay)') }}</a>
</div>

<div class="alert alert-info small d-flex align-items-center">
  <i class="fas fa-info-circle me-2"></i>
  <div>{{ __('This grid is for fast batch review + CSV. For image-overlay correction (drag boxes, AI-read each field, save a reusable layout per form type), use') }}
    <a href="{{ route('admin.ai.htr.fsOverlay') }}">{{ __('FS Overlay') }}</a> {{ __('— select a "FamilySearch Data Safe — Scotland …" form type there.') }}</div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-6">
        <label class="form-label small fw-bold">{{ __('DGS image folder (server path)') }}</label>
        <input type="text" id="fs-folder" class="form-control form-control-sm" placeholder="/usr/share/nginx/heratio-dev/fs-metadata-capture/images/008066403">
      </div>
      <div class="col-md-2"><label class="form-label small fw-bold">{{ __('Collection ID') }}</label><input type="text" id="fs-collection" class="form-control form-control-sm"></div>
      <div class="col-md-2"><label class="form-label small fw-bold">{{ __('PPQ ID') }}</label><input type="text" id="fs-ppq" class="form-control form-control-sm"></div>
      <div class="col-md-2"><label class="form-label small fw-bold">{{ __('Event type') }}</label>
        <select id="fs-event" class="form-select form-select-sm">
          <option value="Marriage">{{ __('Marriage (type_c)') }}</option>
          <option value="Birth">{{ __('Birth (type_a)') }}</option>
          <option value="Baptism">{{ __('Baptism (type_a)') }}</option>
          <option value="Death">{{ __('Death (type_b)') }}</option>
          <option value="Burial">{{ __('Burial (type_b)') }}</option>
        </select>
      </div>
    </div>
    <div class="mt-3 d-flex gap-2 align-items-center">
      <button id="fs-run" class="btn btn-primary btn-sm"><i class="fas fa-play me-1"></i>{{ __('List images') }}</button>
      <button id="fs-readall" class="btn btn-outline-primary btn-sm" disabled><i class="fas fa-bolt me-1"></i>{{ __('Read all') }}</button>
      <button id="fs-stop" class="btn btn-outline-danger btn-sm d-none"><i class="fas fa-stop me-1"></i>{{ __('Stop') }}</button>
      <button id="fs-csv" class="btn atom-btn-outline-secondary btn-sm"><i class="fas fa-file-csv me-1"></i>{{ __('Download corrected CSV') }}</button>
      <button id="fs-save" class="btn atom-btn-outline-secondary btn-sm"><i class="fas fa-save me-1"></i>{{ __('Save corrections') }}</button>
      <span id="fs-status" class="small text-muted"></span>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card"><div class="card-body p-0"><div id="fs-grid-wrap">
      <table class="table table-sm table-bordered table-hover mb-0" id="fs-grid">
        <thead id="fs-thead"></thead><tbody id="fs-tbody"></tbody>
      </table>
    </div></div></div>
  </div>
  <div class="col-lg-4">
    <div id="fs-preview-panel" class="card"><div class="card-body p-2">
      <div class="small text-muted mb-2" id="fs-preview-cap">{{ __('Click a row to read its image + preview it') }}</div>
      <img id="fs-preview" alt="source image" style="display:none;">
      <a id="fs-open-overlay" href="{{ route('admin.ai.htr.fsOverlay') }}" class="btn btn-outline-primary btn-sm w-100 mt-2"><i class="fas fa-vector-square me-1"></i>{{ __('Correct this in FS Overlay') }}</a>
    </div></div>
  </div>
</div>
@endsection

@push('js')
<script>
(function () {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const $ = id => document.getElementById(id);
  const status = m => { $('fs-status').textContent = m; };
  let COLUMNS = @json(array_keys($columns));
  let folder = '';
  let curRow = null;

  const project = () => ({
    folder: $('fs-folder').value.trim(),
    collection_id: $('fs-collection').value.trim(),
    ppq_id: $('fs-ppq').value.trim(),
    event_type: $('fs-event').value,
  });

  function collectRows() {
    const rows = [];
    document.querySelectorAll('#fs-tbody tr').forEach(tr => {
      const row = { _src: tr.dataset.src || '' };
      tr.querySelectorAll('input[data-col]').forEach(inp => { row[inp.dataset.col] = inp.value; });
      rows.push(row);
    });
    return rows;
  }

  // One HTR extraction for a single image: assembled Data Safe row + image URL.
  async function fetchImageFields(src) {
    const p = project();
    const r = await fetch('{{ route("admin.ai.htr.fsIndexFields") }}', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
      body: JSON.stringify({ folder, fname: src, event_type: p.event_type, collection_id: p.collection_id, ppq_id: p.ppq_id }),
    });
    return r.json();
  }

  // Fill a grid row's cells from a Data Safe record (non-empty values only).
  function fillRow(tr, row) {
    if (!row) return;
    Object.keys(row).forEach(col => {
      if (col === '_src') return;
      const v = row[col];
      if (v === '' || v == null) return;
      const inp = tr.querySelector('input[data-col="' + col + '"]');
      if (inp) inp.value = v;
    });
  }

  // Read one image: fill the row + show the preview.
  async function loadRow(tr) {
    curRow = tr;
    const src = tr.dataset.src || '';
    $('fs-preview-cap').textContent = src + ' — reading…';
    try {
      const d = await fetchImageFields(src);
      if (!d.success) { $('fs-preview-cap').textContent = src + ' — ' + (d.error || 'load failed'); return; }
      fillRow(tr, d.row);
      if (d.image_url) {
        $('fs-preview').src = d.image_url + '&_=' + Date.now();
        $('fs-preview').style.display = 'block';
      }
      $('fs-preview-cap').textContent = src;
    } catch (e) { $('fs-preview-cap').textContent = src + ' — error: ' + e.message; }
  }

  function renderGrid(columns, rows) {
    COLUMNS = columns;
    $('fs-thead').innerHTML = '<tr>' + columns.map(c => '<th>' + c + '</th>').join('') + '</tr>';
    $('fs-tbody').innerHTML = rows.map(row => {
      const nonRec = !!(row.FS_IMAGE_TYPE && row.FS_IMAGE_TYPE.length);
      const cells = columns.map(c => {
        const v = (row[c] ?? '').toString().replace(/"/g, '&quot;');
        return '<td><input data-col="' + c + '" value="' + v + '"></td>';
      }).join('');
      return '<tr class="' + (nonRec ? 'fs-nonrec' : '') + '" data-src="' + (row._src || '') + '">' + cells + '</tr>';
    }).join('');
    document.querySelectorAll('#fs-tbody tr').forEach(tr => {
      tr.addEventListener('focusin', () => {
        document.querySelectorAll('#fs-tbody tr.fs-active').forEach(x => x.classList.remove('fs-active'));
        tr.classList.add('fs-active');
        if (tr !== curRow) loadRow(tr);
      });
    });
  }

  // "List images" - instant: one row per image, event fields blank.
  $('fs-run').addEventListener('click', async () => {
    status('Listing images…'); folder = project().folder;
    curRow = null; $('fs-preview').style.display = 'none'; $('fs-readall').disabled = true;
    try {
      const r = await fetch('{{ route("admin.ai.htr.fsIndexRun") }}', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
        body: JSON.stringify(project()),
      });
      const d = await r.json();
      if (!d.success) { status(d.error || 'Failed'); return; }
      renderGrid(d.columns, d.rows);
      $('fs-readall').disabled = d.total === 0;
      status(d.total + ' image(s) — click a row to read it, or "Read all".');
    } catch (e) { status('Error: ' + e.message); }
  });

  // "Read all" - extract every image sequentially (responsive, abortable).
  let stopFlag = false;
  $('fs-readall').addEventListener('click', async () => {
    const rows = Array.from(document.querySelectorAll('#fs-tbody tr'));
    if (!rows.length) return;
    stopFlag = false;
    $('fs-readall').disabled = true; $('fs-run').disabled = true; $('fs-stop').classList.remove('d-none');
    let n = 0;
    for (const tr of rows) {
      if (stopFlag) break;
      status('Reading ' + (n + 1) + '/' + rows.length + ' (' + (tr.dataset.src || '') + ')…');
      try { const d = await fetchImageFields(tr.dataset.src || ''); if (d.success) fillRow(tr, d.row); } catch (e) { /* skip */ }
      n++;
    }
    $('fs-stop').classList.add('d-none'); $('fs-readall').disabled = false; $('fs-run').disabled = false;
    status(stopFlag ? ('Stopped after ' + n + ' image(s).') : ('Read ' + n + ' image(s). Review, then download CSV.'));
  });
  $('fs-stop').addEventListener('click', () => { stopFlag = true; });

  function postDownload(url) {
    const dgs = (folder.split('/').filter(Boolean).pop() || 'export');
    fetch(url, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
      body: JSON.stringify({ rows: collectRows(), dgs }),
    }).then(r => r.blob()).then(b => {
      const a = document.createElement('a'); a.href = URL.createObjectURL(b);
      a.download = 'fs-scotland-' + dgs + '.csv';
      document.body.appendChild(a); a.click(); a.remove();
    });
  }
  $('fs-csv').addEventListener('click', () => postDownload('{{ route("admin.ai.htr.fsIndexCsvRows") }}'));

  $('fs-save').addEventListener('click', async () => {
    status('Saving corrections…');
    const r = await fetch('{{ route("admin.ai.htr.fsIndexSave") }}', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
      body: JSON.stringify({ rows: collectRows(), folder, dgs: (folder.split('/').filter(Boolean).pop() || 'export') }),
    });
    const d = await r.json();
    status(d.success ? ('Saved ' + d.rows + ' corrected row(s).') : ('Save failed: ' + (d.error || '')));
  });
})();
</script>
@endpush
