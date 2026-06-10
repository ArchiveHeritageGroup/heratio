{{-- heratio#1196 AI Cataloguer: scan in -> draft archival record out, for review. --}}
@extends('theme::layouts.1col')
@section('title', __('AI Cataloguer'))

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
    <h1 class="h4 mb-0"><i class="fas fa-wand-magic-sparkles me-2 text-primary"></i>{{ __('AI Cataloguer') }}</h1>
    <span class="text-muted small">{{ __('Scan in, draft archival record out') }}</span>
    <a href="{{ route('scan.dashboard') }}" class="btn btn-sm btn-outline-secondary ms-auto"><i class="fas fa-arrow-left me-1"></i>{{ __('Scan dashboard') }}</a>
  </div>
  <p class="text-muted small">{{ __('Upload one scanned document. Heratio reads the text (HTR), extracts people, places and dates (NER), and drafts an ISAD(G) title + scope note for you to review. Nothing is saved until you accept it.') }}</p>

  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header py-2"><strong>{{ __('1. Upload a scan') }}</strong></div>
        <div class="card-body">
          <input type="file" id="cgFile" accept=".jpg,.jpeg,.png,.tif,.tiff,.webp,.bmp" class="form-control form-control-sm mb-2">
          <img id="cgPreview" alt="" style="max-width:100%;max-height:280px;display:none;border-radius:4px;border:1px solid #dee2e6">
          <button type="button" id="cgRun" class="btn btn-primary w-100 mt-2"><i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Draft a record') }}</button>
          <div id="cgErr" class="text-danger small mt-2" style="display:none"></div>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card">
        <div class="card-header py-2"><strong>{{ __('2. Review the draft') }}</strong></div>
        <div class="card-body" id="cgDraft" style="display:none">
          <div class="mb-2">
            <label class="form-label small mb-1">{{ __('Title') }}</label>
            <input type="text" id="cgTitle" class="form-control form-control-sm">
          </div>
          <div class="mb-2">
            <label class="form-label small mb-1">{{ __('Scope and content') }}</label>
            <textarea id="cgScope" class="form-control form-control-sm" rows="4"></textarea>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small mb-1"><i class="fas fa-user me-1"></i>{{ __('People') }}</label><div id="cgPersons" class="d-flex flex-wrap gap-1"></div></div>
            <div class="col-6"><label class="form-label small mb-1"><i class="fas fa-map-marker-alt me-1"></i>{{ __('Places') }}</label><div id="cgPlaces" class="d-flex flex-wrap gap-1"></div></div>
            <div class="col-6"><label class="form-label small mb-1"><i class="fas fa-building me-1"></i>{{ __('Organisations') }}</label><div id="cgOrgs" class="d-flex flex-wrap gap-1"></div></div>
            <div class="col-6"><label class="form-label small mb-1"><i class="fas fa-calendar me-1"></i>{{ __('Dates') }}</label><div id="cgDates" class="d-flex flex-wrap gap-1"></div></div>
          </div>
          <details class="mb-1">
            <summary class="small text-muted">{{ __('Transcription') }}</summary>
            <pre id="cgText" class="small mt-1" style="white-space:pre-wrap;max-height:220px;overflow:auto;background:#f8f9fa;padding:8px;border-radius:4px"></pre>
          </details>
          <p class="small text-muted mb-0">{{ __('Review and edit above. (Creating the record from this draft is the next step - coming soon.)') }}</p>
        </div>
        <div class="card-body text-muted" id="cgEmpty">{{ __('The draft will appear here after you run it.') }}</div>
      </div>
    </div>
  </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  var CSRF = '{{ csrf_token() }}';
  var DRAFT_URL = '{{ route('scan.cataloguer.draft') }}';
  var fileEl = document.getElementById('cgFile'), runBtn = document.getElementById('cgRun'),
      prev = document.getElementById('cgPreview'), errEl = document.getElementById('cgErr'),
      draftBox = document.getElementById('cgDraft'), emptyBox = document.getElementById('cgEmpty');
  fileEl.addEventListener('change', function () {
    var f = fileEl.files && fileEl.files[0];
    if (f) { try { prev.src = window.URL.createObjectURL(f); prev.style.display = 'inline-block'; } catch (e) {} }
  });
  function chips(id, arr) {
    var box = document.getElementById(id); box.innerHTML = '';
    if (!arr || !arr.length) { box.innerHTML = '<span class="text-muted small">{{ __('none') }}</span>'; return; }
    arr.forEach(function (v) { var s = document.createElement('span'); s.className = 'badge bg-light text-dark border'; s.textContent = v; box.appendChild(s); });
  }
  runBtn.addEventListener('click', function () {
    if (!fileEl.files || !fileEl.files[0]) { alert('{{ __('Choose a scan first.') }}'); return; }
    errEl.style.display = 'none';
    runBtn.disabled = true; runBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-1"></i>{{ __('Reading, extracting, drafting…') }}';
    var fd = new FormData(); fd.append('scan', fileEl.files[0]); fd.append('_token', CSRF);
    fetch(DRAFT_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        runBtn.disabled = false; runBtn.innerHTML = '<i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Draft a record') }}';
        if (!d || !d.ok) {
          errEl.style.display = 'block';
          errEl.textContent = (d && d.transcription === '') ? '{{ __('No readable text was found in this scan.') }}' : '{{ __('Could not draft a record from this scan.') }}';
          return;
        }
        emptyBox.style.display = 'none'; draftBox.style.display = 'block';
        document.getElementById('cgTitle').value = d.title || '';
        document.getElementById('cgScope').value = d.scope_and_content || '';
        document.getElementById('cgText').textContent = d.transcription || '';
        chips('cgPersons', d.persons); chips('cgPlaces', d.places); chips('cgOrgs', d.organizations); chips('cgDates', d.dates);
      })
      .catch(function () {
        runBtn.disabled = false; runBtn.innerHTML = '<i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Draft a record') }}';
        errEl.style.display = 'block'; errEl.textContent = '{{ __('Something went wrong. Please try again.') }}';
      });
  });
})();
</script>
@endsection
