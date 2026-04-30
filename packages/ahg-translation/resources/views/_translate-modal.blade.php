{{-- Partial: translate-modal (migrated from ahgTranslationPlugin/translation/_translateModal.php) --}}
{{-- Usage: @include('ahg-translation::_translate-modal', ['objectId' => $io->id]) --}}

@php
    $objectId = (int) ($objectId ?? 0);
    $userCulture = app()->getLocale();

    // Query which cultures actually exist for this record
    $availableCultures = [];
    try {
        $availableCultures = \Illuminate\Support\Facades\DB::table('information_object_i18n')
            ->where('id', $objectId)
            ->pluck('culture')
            ->toArray();
    } catch (\Exception $e) {
        $availableCultures = [];
    }

    // Get the slug for this object
    $ioSlug = \Illuminate\Support\Facades\DB::table('slug')
        ->where('object_id', $objectId)
        ->value('slug') ?? '';

    // Target languages
    $targetLanguages = [
        'en' => 'English', 'af' => 'Afrikaans', 'zu' => 'isiZulu', 'xh' => 'isiXhosa',
        'st' => 'Sesotho', 'tn' => 'Setswana', 'nso' => 'Sepedi (Northern Sotho)',
        'ts' => 'Xitsonga', 'ss' => 'SiSwati', 've' => 'Tshivenda', 'nr' => 'isiNdebele',
        'nl' => 'Dutch', 'fr' => 'French', 'de' => 'German', 'es' => 'Spanish',
        'pt' => 'Portuguese', 'sw' => 'Swahili', 'ar' => 'Arabic',
    ];

    // All translatable fields from information_object_i18n
    $allFields = [
        'title' => 'Title', 'alternate_title' => 'Alternate Title',
        'scope_and_content' => 'Scope and Content', 'archival_history' => 'Archival History',
        'acquisition' => 'Acquisition', 'arrangement' => 'Arrangement',
        'access_conditions' => 'Access Conditions', 'reproduction_conditions' => 'Reproduction Conditions',
        'finding_aids' => 'Finding Aids', 'related_units_of_description' => 'Related Units',
        'appraisal' => 'Appraisal', 'accruals' => 'Accruals',
        'physical_characteristics' => 'Physical Characteristics',
        'location_of_originals' => 'Location of Originals',
        'location_of_copies' => 'Location of Copies', 'extent_and_medium' => 'Extent and Medium',
        'sources' => 'Sources', 'rules' => 'Rules', 'revision_history' => 'Revision History',
    ];

    // Settings
    $selectedFields = ['title', 'scope_and_content'];
    $defaultTarget = 'af';
    $saveCultureDefault = true;
    $overwriteDefault = false;
    try {
        $settings = \Illuminate\Support\Facades\DB::table('ahg_translation_settings')->pluck('setting_value', 'setting_key')->toArray();
        $selectedFields = json_decode($settings['translation_fields'] ?? '["title","scope_and_content"]', true) ?: $selectedFields;
        $defaultTarget = $settings['translation_target_lang'] ?? $defaultTarget;
        $saveCultureDefault = ($settings['translation_save_culture'] ?? '1') === '1';
        $overwriteDefault = ($settings['translation_overwrite'] ?? '0') === '1';
    } catch (\Exception $e) {}
@endphp

<a href="#" data-bs-toggle="modal" data-bs-target="#ahgTranslateModal-{{ $objectId }}">
  <i class="bi bi-translate me-1"></i>{{ __('Translate') }}
</a>

<div class="modal fade"
     id="ahgTranslateModal-{{ $objectId }}"
     tabindex="-1"
     aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" data-object-id="{{ $objectId }}">

      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title">
          <i class="fas fa-language me-2"></i>Translate Record
          <span class="ahg-step-indicator badge bg-light text-dark ms-2">{{ __('Step 1: Select Fields') }}</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>

      <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">

        {{-- STEP 1: Field Selection --}}
        <div class="ahg-step-1">
          <div class="row mb-3">
            <div class="col-md-4">
              <label class="form-label fw-bold">{{ __('Read from culture') }}</label>
              <select class="form-select ahg-translate-read-culture">
                @foreach ($availableCultures as $c)
                  @php
                    $cLabel = $targetLanguages[$c] ?? $c;
                    $cLabel .= ' (' . $c . ')';
                  @endphp
                  <option value="{{ $c }}" {{ $c === $userCulture ? 'selected' : '' }}>{{ $cLabel }}</option>
                @endforeach
              </select>
              <small class="text-muted">{{ __('Culture where text is stored') }}</small>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">{{ __('Source Language') }}</label>
              <select class="form-select ahg-translate-source">
                @foreach ($targetLanguages as $code => $name)
                  <option value="{{ $code }}" {{ $code === $userCulture ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
              </select>
              <small class="text-muted">{{ __('Actual language of the text') }}</small>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">{{ __('Target Language') }}</label>
              <select class="form-select ahg-translate-target">
                @foreach ($targetLanguages as $code => $name)
                  <option value="{{ $code }}" {{ $code === $defaultTarget ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <div class="form-check form-switch">
                <input class="form-check-input ahg-save-culture" type="checkbox" id="ahg-save-culture-{{ $objectId }}" {{ $saveCultureDefault ? 'checked' : '' }}>
                <label class="form-check-label fw-bold" for="ahg-save-culture-{{ $objectId }}">{{ __('Save with culture code') }}</label>
              </div>
              <small class="text-muted">{{ __("Saves translation in target language's culture") }}</small>
            </div>
            <div class="col-md-6">
              <div class="form-check form-switch">
                <input class="form-check-input ahg-overwrite" type="checkbox" id="ahg-overwrite-{{ $objectId }}" {{ $overwriteDefault ? 'checked' : '' }}>
                <label class="form-check-label fw-bold" for="ahg-overwrite-{{ $objectId }}">{{ __('Overwrite existing') }}</label>
              </div>
              <small class="text-muted">{{ __('Overwrite if target field already has content') }}</small>
            </div>
          </div>

          <hr>

          <div class="mb-2">
            <span class="fw-bold">{{ __('Fields to Translate') }}</span>
            <div class="float-end">
              <button type="button" class="btn btn-sm btn-outline-secondary ahg-select-all">{{ __('Select All') }}</button>
              <button type="button" class="btn btn-sm btn-outline-secondary ahg-deselect-all">{{ __('Deselect All') }}</button>
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
                       id="ahg-translate-{{ $objectId }}-{{ $key }}"
                       {{ in_array($key, $selectedFields) ? 'checked' : '' }}>
                <label class="form-check-label" for="ahg-translate-{{ $objectId }}-{{ $key }}">{{ $label }}</label>
              </div>
              @if ($i % 10 === 9 || $i === count($allFields) - 1)</div>@endif
              @php $i++; @endphp
            @endforeach
          </div>

          <div class="alert alert-info py-2 mt-3 mb-0">
            <i class="fas fa-info-circle me-1"></i>
            {{ __('Click "Translate" to preview translations before saving.') }}
          </div>
        </div>

        {{-- STEP 2: Review Translations --}}
        <div class="ahg-step-2" style="display:none;">
          <div class="alert alert-warning py-2 mb-3">
            <i class="fas fa-eye me-1"></i>
            <strong>{{ __('Review Translations') }}</strong> - Edit if needed, then click "Approve &amp; Save" to apply.
          </div>
          <div class="ahg-translations-preview"></div>
        </div>

        {{-- Status Messages --}}
        <div class="mt-3">
          <div class="alert py-2 mb-0 ahg-translate-status" style="display:none; white-space:pre-wrap;"></div>
        </div>
      </div>

      <div class="modal-footer">
        <div class="ahg-step-1-buttons">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i>{{ __('Close') }}
          </button>
          <button type="button" class="btn btn-primary ahg-translate-run">
            <i class="fas fa-language me-1"></i>{{ __('Translate') }}
          </button>
        </div>
        <div class="ahg-step-2-buttons" style="display:none;">
          <button type="button" class="btn btn-outline-secondary ahg-back-to-step1">
            <i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i>{{ __('Cancel') }}
          </button>
          <button type="button" class="btn btn-success ahg-approve-save">
            <i class="fas fa-check me-1"></i>{{ __('Approve &amp; Save') }}
          </button>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
(function(){
  var modalEl = document.getElementById("ahgTranslateModal-{{ $objectId }}");
  if (!modalEl) return;

  var content = modalEl.querySelector(".modal-content");
  var objectId = content.getAttribute("data-object-id");
  var slug = "{{ $ioSlug }}";
  var statusEl = content.querySelector(".ahg-translate-status");
  var readCultureSel = content.querySelector(".ahg-translate-read-culture");
  var sourceSel = content.querySelector(".ahg-translate-source");
  var targetSel = content.querySelector(".ahg-translate-target");
  var saveCultureCb = content.querySelector(".ahg-save-culture");
  var overwriteCb = content.querySelector(".ahg-overwrite");
  var stepIndicator = content.querySelector(".ahg-step-indicator");

  var step1 = content.querySelector(".ahg-step-1");
  var step2 = content.querySelector(".ahg-step-2");
  var step1Buttons = content.querySelector(".ahg-step-1-buttons");
  var step2Buttons = content.querySelector(".ahg-step-2-buttons");
  var previewContainer = content.querySelector(".ahg-translations-preview");

  var btnTranslate = content.querySelector(".ahg-translate-run");
  var btnBack = content.querySelector(".ahg-back-to-step1");
  var btnApprove = content.querySelector(".ahg-approve-save");

  var translationResults = [];

  content.querySelector(".ahg-select-all").addEventListener("click", function() {
    content.querySelectorAll(".ahg-translate-field").forEach(function(cb) { cb.checked = true; });
  });
  content.querySelector(".ahg-deselect-all").addEventListener("click", function() {
    content.querySelectorAll(".ahg-translate-field").forEach(function(cb) { cb.checked = false; });
  });

  function showStep(step) {
    if (step === 1) {
      step1.style.display = "block"; step2.style.display = "none";
      step1Buttons.style.display = "block"; step2Buttons.style.display = "none";
      stepIndicator.textContent = "Step 1: Select Fields";
    } else {
      step1.style.display = "none"; step2.style.display = "block";
      step1Buttons.style.display = "none"; step2Buttons.style.display = "block";
      stepIndicator.textContent = "Step 2: Review & Approve";
    }
  }

  function getSelectedFields() {
    return Array.from(content.querySelectorAll(".ahg-translate-field:checked"))
      .map(function(cb) { return { source: cb.value, label: cb.dataset.label }; });
  }

  function showStatus(msg, type) {
    statusEl.style.display = "block"; statusEl.textContent = msg;
    statusEl.className = "alert py-2 mb-0 alert-" + (type || "secondary");
  }

  function hideStatus() { statusEl.style.display = "none"; }

  function escapeHtml(text) {
    var div = document.createElement('div'); div.textContent = text; return div.innerHTML;
  }

  async function translateFieldPreview(sourceField, source, target) {
    var body = new URLSearchParams({
      field: sourceField, targetField: sourceField,
      readCulture: readCultureSel.value, source: source, target: target,
      apply: "0", saveCulture: "0", overwrite: "0",
      _token: "{{ csrf_token() }}"
    });
    var res = await fetch("/admin/translation/translate/" + slug, {
      method: "POST", headers: {"Content-Type":"application/x-www-form-urlencoded"}, body: body
    });
    try { return await res.json(); } catch(e) { return { ok:false, error:"Invalid JSON response" }; }
  }

  function renderPreview(results) {
    var html = '<div class="accordion" id="translationAccordion-' + objectId + '">';
    results.forEach(function(r, idx) {
      var badge = r.ok ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">Failed</span>';
      html += '<div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-' + objectId + '-' + idx + '" aria-expanded="true">' + badge + ' <strong class="ms-2">' + escapeHtml(r.label) + '</strong></button></h2>';
      html += '<div id="collapse-' + objectId + '-' + idx + '" class="accordion-collapse collapse show"><div class="accordion-body">';
      if (r.ok) {
        html += '<div class="row"><div class="col-md-6"><label class="form-label fw-bold text-muted">Source Text</label><div class="border rounded p-2 bg-light" style="max-height:150px;overflow-y:auto;">' + escapeHtml(r.sourceText || '(empty)') + '</div></div>';
        html += '<div class="col-md-6"><label class="form-label fw-bold text-success"><i class="fas fa-arrow-right me-1"></i>Translation</label><textarea class="form-control ahg-translated-text" data-field="' + r.field + '" data-draft-id="' + r.draft_id + '" rows="4" style="max-height:150px;">' + escapeHtml(r.translation || '') + '</textarea>';
        html += '<div class="d-flex justify-content-end mt-2"><button type="button" class="btn btn-sm btn-outline-success ahg-save-one" data-field="' + r.field + '"><i class="fas fa-save me-1"></i>Save this field</button> <span class="ms-2 small ahg-save-one-status text-muted"></span></div>';
        html += '</div></div>';
      } else {
        html += '<div class="alert alert-danger mb-0"><i class="fas fa-exclamation-triangle me-1"></i>' + escapeHtml(r.error || 'Translation failed') + '</div>';
      }
      html += '</div></div></div>';
    });
    html += '</div>';
    previewContainer.innerHTML = html;
  }

  btnTranslate.addEventListener("click", async function() {
    var source = sourceSel.value, target = targetSel.value, fields = getSelectedFields();
    if (!fields.length) { showStatus("Select at least one field.", "danger"); return; }
    if (source === target) { showStatus("Source and target language must be different.", "danger"); return; }

    btnTranslate.disabled = true;
    var targetName = targetSel.options[targetSel.selectedIndex].text;
    showStatus("Translating " + fields.length + " field(s) to " + targetName + "...", "info");

    translationResults = [];
    for (var i = 0; i < fields.length; i++) {
      showStatus("Translating " + (i+1) + "/" + fields.length + ": " + fields[i].label + "...", "info");
      var result = await translateFieldPreview(fields[i].source, source, target);
      translationResults.push({
        field: fields[i].source, label: fields[i].label, ok: result.ok,
        translation: result.translation || '', sourceText: result.source_text || '',
        draft_id: result.draft_id, error: result.error
      });
    }
    btnTranslate.disabled = false;

    if (translationResults.filter(function(r){return r.ok;}).length === 0) {
      showStatus("All translations failed. Check the MT service.", "danger"); return;
    }
    hideStatus(); renderPreview(translationResults); showStep(2);
  });

  btnBack.addEventListener("click", function() { showStep(1); hideStatus(); });

  // Per-field Save button (event delegation; preview is rebuilt on each translate run).
  previewContainer.addEventListener("click", async function(ev) {
    var btn = ev.target.closest(".ahg-save-one");
    if (!btn) return;
    var field = btn.dataset.field;
    var ta = previewContainer.querySelector('.ahg-translated-text[data-field="' + field + '"]');
    if (!ta) return;
    var statusSpan = btn.parentElement.querySelector(".ahg-save-one-status");
    btn.disabled = true;
    statusSpan.textContent = "Saving...";
    statusSpan.className = "ms-2 small ahg-save-one-status text-muted";
    var body = new URLSearchParams({
      object_id: objectId,
      culture: targetSel.value,
      field: field,
      value: ta.value,
      confirmed: "1",
      _token: "{{ csrf_token() }}"
    });
    try {
      var res = await fetch("/admin/translation/save", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded", "Accept": "application/json"},
        body: body
      });
      var json = await res.json();
      if (json.ok) {
        statusSpan.textContent = "Saved (" + (json.source || "human") + ", " + (json.culture || targetSel.value) + ")";
        statusSpan.className = "ms-2 small ahg-save-one-status text-success";
      } else {
        statusSpan.textContent = "Error: " + (json.error || "save failed");
        statusSpan.className = "ms-2 small ahg-save-one-status text-danger";
      }
    } catch (e) {
      statusSpan.textContent = "Network error";
      statusSpan.className = "ms-2 small ahg-save-one-status text-danger";
    }
    btn.disabled = false;
  });

  btnApprove.addEventListener("click", async function() {
    var saveCulture = saveCultureCb.checked, overwrite = overwriteCb.checked, target = targetSel.value;
    btnApprove.disabled = true; showStatus("Saving translations...", "info");

    var saved = 0, failed = 0;
    var textareas = previewContainer.querySelectorAll(".ahg-translated-text");
    for (var ta of textareas) {
      if (!ta.dataset.draftId) continue;
      var body = new URLSearchParams({
        draftId: ta.dataset.draftId, overwrite: overwrite?"1":"0",
        saveCulture: saveCulture?"1":"0", targetCulture: target,
        editedText: ta.value, _token: "{{ csrf_token() }}"
      });
      try {
        var res = await fetch("/admin/translation/apply", { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body: body });
        var json = await res.json();
        if (json.ok) saved++; else failed++;
      } catch(e) { failed++; }
    }
    btnApprove.disabled = false;

    if (failed === 0) {
      showStatus("Successfully saved " + saved + " translation(s) with culture code \"" + target + "\".", "success");
      setTimeout(function(){ location.reload(); }, 2000);
    } else {
      showStatus("Saved: " + saved + ", Failed: " + failed, "warning");
    }
  });

  modalEl.addEventListener("hidden.bs.modal", function() {
    showStep(1); hideStatus(); previewContainer.innerHTML = ""; translationResults = [];
  });
})();
</script>
