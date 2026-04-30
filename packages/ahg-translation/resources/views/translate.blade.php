@extends('theme::layouts.1col')

@section('title', 'Translate: ' . $title)

@section('content')
<div class="multiline-header d-flex align-items-center mb-3">
  <i class="fas fa-3x fa-language me-3" aria-hidden="true"></i>
  <div class="d-flex flex-column">
    <h1 class="mb-0">{{ __('Translate Record') }}</h1>
    <span class="text-muted">{{ $title }}</span>
  </div>
</div>

<div id="ahgTranslateApp" data-object-id="{{ $objectId }}" data-slug="{{ $slug }}">

  {{-- STEP 1: Field Selection --}}
  <div class="card mb-4 shadow-sm">
    <div class="card-header bg-secondary text-white">
      <h5 class="mb-0">
        <i class="fas fa-language me-2"></i>Step 1: Select Fields
        <span class="ahg-step-indicator badge bg-light text-dark ms-2" id="step-indicator">{{ __('Step 1: Select Fields') }}</span>
      </h5>
    </div>
    <div class="card-body" id="step1-body">

      {{-- Language selectors --}}
      <div class="row mb-3">
        <div class="col-md-4">
          <label class="form-label fw-bold">{{ __('Read from culture') }}</label>
          <select class="form-select" id="read-culture">
            @foreach ($availableCultures as $c)
              <option value="{{ $c }}" {{ $c === $culture ? 'selected' : '' }}>
                {{ $targetLanguages[$c] ?? $c }} ({{ $c }})
              </option>
            @endforeach
          </select>
          <small class="text-muted">{{ __('Culture where text is stored') }}</small>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-bold">{{ __('Source Language') }}</label>
          <select class="form-select" id="source-lang">
            @foreach ($targetLanguages as $code => $name)
              <option value="{{ $code }}" {{ $code === $culture ? 'selected' : '' }}>{{ $name }}</option>
            @endforeach
          </select>
          <small class="text-muted">{{ __('Actual language of the text') }}</small>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-bold">{{ __('Target Language') }}</label>
          <select class="form-select" id="target-lang">
            @foreach ($targetLanguages as $code => $name)
              <option value="{{ $code }}" {{ $code === $defaultTarget ? 'selected' : '' }}>{{ $name }}</option>
            @endforeach
          </select>
        </div>
      </div>

      {{-- Options --}}
      <div class="row mb-3">
        <div class="col-md-6">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="save-culture" {{ $saveCultureDefault ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="save-culture">{{ __('Save with culture code') }}</label>
          </div>
          <small class="text-muted">{{ __("Saves translation in target language's culture") }}</small>
        </div>
        <div class="col-md-6">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="overwrite" {{ $overwriteDefault ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="overwrite">{{ __('Overwrite existing') }}</label>
          </div>
          <small class="text-muted">{{ __('Overwrite if target field already has content') }}</small>
        </div>
      </div>

      <hr>

      {{-- Fields Selection --}}
      <div class="mb-2">
        <span class="fw-bold">{{ __('Fields to Translate') }}</span>
        <div class="float-end">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="select-all">{{ __('Select All') }}</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="deselect-all">{{ __('Deselect All') }}</button>
        </div>
      </div>

      <div class="row">
        @php $i = 0; @endphp
        @foreach ($allFields as $key => $label)
          @if ($i % 10 === 0)<div class="col-md-6">@endif
          <div class="form-check">
            <input class="form-check-input ahg-translate-field"
                   type="checkbox"
                   value="{{ $key }}"
                   data-label="{{ $label }}"
                   id="field-{{ $key }}"
                   {{ in_array($key, $selectedFields) ? 'checked' : '' }}>
            <label class="form-check-label" for="field-{{ $key }}">{{ $label }}</label>
          </div>
          @if ($i % 10 === 9 || $i === count($allFields) - 1)</div>@endif
          @php $i++; @endphp
        @endforeach
      </div>

      <div class="alert alert-info py-2 mt-3 mb-0">
        <i class="fas fa-info-circle me-1"></i>
        Click "Translate" to preview translations before saving.
      </div>
    </div>
  </div>

  {{-- STEP 2: Review Translations --}}
  <div class="card mb-4 shadow-sm" id="step2-card" style="display:none;">
    <div class="card-header bg-secondary text-white">
      <h5 class="mb-0"><i class="fas fa-eye me-2"></i>Step 2: Review & Approve</h5>
    </div>
    <div class="card-body">
      <div class="alert alert-warning py-2 mb-3">
        <i class="fas fa-eye me-1"></i>
        <strong>{{ __('Review Translations') }}</strong> - Edit if needed, then click "Approve &amp; Save" to apply.
      </div>
      <div id="translations-preview"></div>
    </div>
  </div>

  {{-- Status Messages --}}
  <div class="mt-3">
    <div class="alert py-2 mb-0" id="translate-status" style="display:none; white-space:pre-wrap;"></div>
  </div>

  {{-- Action Buttons --}}
  <div class="d-flex gap-2 mb-4">
    <div id="step1-buttons">
      <a href="{{ url()->previous() }}" class="btn btn-secondary">
        <i class="fas fa-times me-1"></i>{{ __('Close') }}
      </a>
      <button type="button" class="btn btn-primary" id="btn-translate">
        <i class="fas fa-language me-1"></i>{{ __('Translate') }}
      </button>
    </div>
    <div id="step2-buttons" style="display:none;">
      <button type="button" class="btn btn-outline-secondary" id="btn-back">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}
      </button>
      <a href="{{ url()->previous() }}" class="btn btn-secondary">
        <i class="fas fa-times me-1"></i>{{ __('Cancel') }}
      </a>
      <button type="button" class="btn btn-success" id="btn-approve">
        <i class="fas fa-check me-1"></i>{{ __('Approve &amp; Save') }}
      </button>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
(function(){
  'use strict';

  var app = document.getElementById('ahgTranslateApp');
  var objectId = app.dataset.objectId;
  var slug = app.dataset.slug;
  var statusEl = document.getElementById('translate-status');
  var readCultureSel = document.getElementById('read-culture');
  var sourceSel = document.getElementById('source-lang');
  var targetSel = document.getElementById('target-lang');
  var saveCultureCb = document.getElementById('save-culture');
  var overwriteCb = document.getElementById('overwrite');
  var stepIndicator = document.getElementById('step-indicator');

  var step1Body = document.getElementById('step1-body');
  var step2Card = document.getElementById('step2-card');
  var step1Buttons = document.getElementById('step1-buttons');
  var step2Buttons = document.getElementById('step2-buttons');
  var previewContainer = document.getElementById('translations-preview');

  var btnTranslate = document.getElementById('btn-translate');
  var btnBack = document.getElementById('btn-back');
  var btnApprove = document.getElementById('btn-approve');

  var translationResults = [];

  document.getElementById('select-all').addEventListener('click', function() {
    document.querySelectorAll('.ahg-translate-field').forEach(function(cb) { cb.checked = true; });
  });
  document.getElementById('deselect-all').addEventListener('click', function() {
    document.querySelectorAll('.ahg-translate-field').forEach(function(cb) { cb.checked = false; });
  });

  function showStep(step) {
    if (step === 1) {
      step1Body.parentElement.style.display = 'block';
      step2Card.style.display = 'none';
      step1Buttons.style.display = 'block';
      step2Buttons.style.display = 'none';
      stepIndicator.textContent = 'Step 1: Select Fields';
    } else {
      step1Body.parentElement.style.display = 'none';
      step2Card.style.display = 'block';
      step1Buttons.style.display = 'none';
      step2Buttons.style.display = 'block';
      stepIndicator.textContent = 'Step 2: Review & Approve';
    }
  }

  function getSelectedFields() {
    return Array.from(document.querySelectorAll('.ahg-translate-field:checked'))
      .map(function(cb) { return { source: cb.value, label: cb.dataset.label }; });
  }

  function showStatus(msg, type) {
    statusEl.style.display = 'block';
    statusEl.textContent = msg;
    statusEl.className = 'alert py-2 mb-0 alert-' + (type || 'secondary');
  }

  function hideStatus() {
    statusEl.style.display = 'none';
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  async function translateFieldPreview(sourceField, source, target) {
    var body = new URLSearchParams({
      field: sourceField,
      targetField: sourceField,
      readCulture: readCultureSel.value,
      source: source,
      target: target,
      apply: '0',
      saveCulture: '0',
      overwrite: '0',
      _token: '{{ csrf_token() }}'
    });

    var res = await fetch('/admin/translation/translate/' + slug, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: body
    });

    try { return await res.json(); } catch (e) { return { ok: false, error: 'Invalid JSON response' }; }
  }

  function renderPreview(results) {
    var html = '<div class="accordion" id="translationAccordion">';
    results.forEach(function(r, idx) {
      var statusBadge = r.ok
        ? '<span class="badge bg-success">OK</span>'
        : '<span class="badge bg-danger">Failed</span>';

      html += '<div class="accordion-item">';
      html += '<h2 class="accordion-header">';
      html += '<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-' + idx + '" aria-expanded="true">';
      html += statusBadge + ' <strong class="ms-2">' + escapeHtml(r.label) + '</strong>';
      html += '</button></h2>';
      html += '<div id="collapse-' + idx + '" class="accordion-collapse collapse show">';
      html += '<div class="accordion-body">';

      if (r.ok) {
        html += '<div class="row">';
        html += '<div class="col-md-6">';
        html += '<label class="form-label fw-bold text-muted">Source Text</label>';
        html += '<div class="border rounded p-2 bg-light" style="max-height:150px;overflow-y:auto;">' + escapeHtml(r.sourceText || '(empty)') + '</div>';
        html += '</div>';
        html += '<div class="col-md-6">';
        html += '<label class="form-label fw-bold text-success"><i class="fas fa-arrow-right me-1"></i>Translation</label>';
        html += '<textarea class="form-control ahg-translated-text" data-field="' + r.field + '" data-draft-id="' + r.draft_id + '" rows="4" style="max-height:150px;">' + escapeHtml(r.translation || '') + '</textarea>';
        html += '</div></div>';
      } else {
        html += '<div class="alert alert-danger mb-0"><i class="fas fa-exclamation-triangle me-1"></i>' + escapeHtml(r.error || 'Translation failed') + '</div>';
      }

      html += '</div></div></div>';
    });
    html += '</div>';
    previewContainer.innerHTML = html;
  }

  btnTranslate.addEventListener('click', async function() {
    var source = sourceSel.value;
    var target = targetSel.value;
    var fields = getSelectedFields();

    if (!fields.length) { showStatus('Select at least one field.', 'danger'); return; }
    if (source === target) { showStatus('Source and target language must be different.', 'danger'); return; }

    btnTranslate.disabled = true;
    var targetName = targetSel.options[targetSel.selectedIndex].text;
    showStatus('Translating ' + fields.length + ' field(s) to ' + targetName + '...', 'info');

    translationResults = [];
    for (var i = 0; i < fields.length; i++) {
      var f = fields[i];
      showStatus('Translating ' + (i + 1) + '/' + fields.length + ': ' + f.label + '...', 'info');

      var result = await translateFieldPreview(f.source, source, target);
      translationResults.push({
        field: f.source,
        label: f.label,
        ok: result.ok,
        translation: result.translation || '',
        sourceText: result.source_text || '',
        draft_id: result.draft_id,
        error: result.error
      });
    }

    btnTranslate.disabled = false;

    var okCount = translationResults.filter(function(r) { return r.ok; }).length;
    if (okCount === 0) {
      showStatus('All translations failed. Check the MT service.', 'danger');
      return;
    }

    hideStatus();
    renderPreview(translationResults);
    showStep(2);
  });

  btnBack.addEventListener('click', function() {
    showStep(1);
    hideStatus();
  });

  btnApprove.addEventListener('click', async function() {
    var saveCulture = saveCultureCb.checked;
    var overwrite = overwriteCb.checked;
    var target = targetSel.value;

    btnApprove.disabled = true;
    showStatus('Saving translations...', 'info');

    var savedCount = 0;
    var failCount = 0;
    var textareas = previewContainer.querySelectorAll('.ahg-translated-text');

    for (var ta of textareas) {
      var draftId = ta.dataset.draftId;
      if (!draftId) continue;

      var body = new URLSearchParams({
        draftId: draftId,
        overwrite: overwrite ? '1' : '0',
        saveCulture: saveCulture ? '1' : '0',
        targetCulture: target,
        editedText: ta.value,
        _token: '{{ csrf_token() }}'
      });

      try {
        var res = await fetch('/admin/translation/apply', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: body
        });
        var json = await res.json();
        if (json.ok) { savedCount++; } else { failCount++; }
      } catch (e) {
        failCount++;
      }
    }

    btnApprove.disabled = false;

    if (failCount === 0) {
      showStatus('Successfully saved ' + savedCount + ' translation(s) with culture code "' + target + '".', 'success');
      setTimeout(function() { location.reload(); }, 2000);
    } else {
      showStatus('Saved: ' + savedCount + ', Failed: ' + failCount, failCount > 0 ? 'warning' : 'success');
    }
  });
})();
</script>
@endpush
