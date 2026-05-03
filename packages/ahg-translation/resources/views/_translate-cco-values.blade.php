{{--
  CCO / Museum field VALUES translator (issue #56 → split out of SBS modal).

  Translates per-record content stored in museum_metadata. Each save writes a
  row in museum_metadata_i18n keyed on (museum_metadata.id, culture). Distinct
  from the SBS modal which only handles UI labels — opening this modal makes
  it impossible to confuse the two surfaces.

  Usage from a show.blade.php (only renders when the IO has a museum_metadata row):
      @include('ahg-translation::_translate-cco-values', ['objectId' => $io->id])

  Default behaviour: hides rows where the en source value is empty (nothing to
  translate). "Show all fields" toggle reveals the full schema.

  @copyright Johan Pieterse / Plain Sailing Information Systems
  @license   AGPL-3.0-or-later
--}}
@php
  $objectId = (int) ($objectId ?? 0);
  $ccoMuseumRowId = null;
  $ccoByCulture = [];
  $ccoGroups = [];
  try {
      $mmRow = \Illuminate\Support\Facades\DB::table('museum_metadata')
          ->where('object_id', $objectId)
          ->first();
      if ($mmRow) {
          $ccoMuseumRowId = (int) $mmRow->id;
          $ccoGroups = [
              'Object / Work'                => ['work_type' => 'Work type', 'object_type' => 'Object type', 'classification' => 'Classification', 'object_class' => 'Object class', 'object_category' => 'Object category', 'object_sub_category' => 'Object sub-category', 'record_type' => 'Record type', 'record_level' => 'Record level'],
              'Creator'                      => ['creator_identity' => 'Creator', 'creator_role' => 'Role', 'creator_extent' => 'Extent', 'creator_qualifier' => 'Qualifier', 'creator_attribution' => 'Attribution'],
              'Dates (display only)'         => ['creation_date_display' => 'Date display', 'creation_date_qualifier' => 'Date qualifier'],
              'Materials & technique'        => ['materials' => 'Materials', 'techniques' => 'Techniques', 'technique_cco' => 'Technique (CCO)', 'technique_qualifier' => 'Technique qualifier', 'facture_description' => 'Facture', 'color' => 'Color', 'physical_appearance' => 'Physical appearance'],
              'Measurements'                 => ['measurements' => 'Measurements', 'dimensions' => 'Dimensions', 'orientation' => 'Orientation', 'shape' => 'Shape'],
              'Style / Period / Context'     => ['style_period' => 'Style/Period', 'style' => 'Style', 'period' => 'Period', 'cultural_context' => 'Cultural context', 'cultural_group' => 'Cultural group', 'movement' => 'Movement', 'school' => 'School', 'dynasty' => 'Dynasty'],
              'Subject'                      => ['subject_indexing_type' => 'Indexing type', 'subject_display' => 'Subject display', 'subject_extent' => 'Subject extent', 'historical_context' => 'Historical context', 'architectural_context' => 'Architectural context', 'archaeological_context' => 'Archaeological context'],
              'Condition & treatment'        => ['condition_term' => 'Condition', 'condition_description' => 'Condition description', 'condition_agent' => 'Condition agent', 'condition_notes' => 'Condition notes', 'treatment_type' => 'Treatment type', 'treatment_agent' => 'Treatment agent', 'treatment_description' => 'Treatment description'],
              'Inscriptions & marks'         => ['inscription' => 'Inscription', 'inscriptions' => 'Inscriptions', 'inscription_transcription' => 'Transcription', 'inscription_type' => 'Inscription type', 'inscription_location' => 'Inscription location', 'inscription_language' => 'Inscription language', 'inscription_translation' => 'Translation', 'mark_type' => 'Mark type', 'mark_description' => 'Mark description', 'mark_location' => 'Mark location'],
              'Edition / State'              => ['edition_description' => 'Edition description', 'edition_number' => 'Edition number', 'edition_size' => 'Edition size', 'state_description' => 'State description', 'state_identification' => 'State identification'],
              'Location / Geography'         => ['current_location' => 'Current location', 'current_location_repository' => 'Repository', 'current_location_geography' => 'Geography', 'current_location_ref_number' => 'Reference number', 'creation_place' => 'Creation place', 'creation_place_type' => 'Creation place type', 'discovery_place' => 'Discovery place', 'discovery_place_type' => 'Discovery place type'],
              'Related works'                => ['related_work_type' => 'Relationship type', 'related_work_relationship' => 'Relationship', 'related_work_label' => 'Label'],
              'Provenance & rights'          => ['provenance' => 'Provenance', 'provenance_text' => 'Provenance text', 'ownership_history' => 'Ownership history', 'legal_status' => 'Legal status', 'rights_type' => 'Rights type', 'rights_holder' => 'Rights holder', 'rights_date' => 'Rights date', 'rights_remarks' => 'Rights remarks'],
              'Cataloguing'                  => ['cataloger_name' => 'Cataloger', 'cataloging_institution' => 'Institution', 'cataloging_remarks' => 'Remarks'],
          ];
          // Pre-fetch every culture row so language switches don't round-trip.
          $parentArr = (array) $mmRow;
          $ccoByCulture['en'] = $parentArr;
          foreach (\Illuminate\Support\Facades\DB::table('museum_metadata_i18n')
              ->where('id', $ccoMuseumRowId)->get() as $mi18n) {
              $ccoByCulture[$mi18n->culture] = (array) $mi18n;
          }
          // Map field -> bool indicating whether the en (parent) source has content.
          // Used to hide rows with no record content by default.
          $ccoHasSource = [];
          foreach ($ccoGroups as $g => $fields) {
              foreach ($fields as $k => $_lbl) {
                  $v = $parentArr[$k] ?? null;
                  $ccoHasSource[$k] = ($v !== null && (string) $v !== '');
              }
          }
      }
  } catch (\Throwable $e) {
      $ccoMuseumRowId = null;
  }

  $ccoCultureLabels = [
    'en' => 'English', 'af' => 'Afrikaans', 'zu' => 'isiZulu', 'xh' => 'isiXhosa',
    'st' => 'Sesotho', 'tn' => 'Setswana', 'nso' => 'Sepedi', 'ts' => 'Xitsonga',
    'ss' => 'siSwati', 've' => 'Tshivenda', 'nr' => 'isiNdebele', 'nl' => 'Dutch',
    'fr' => 'French', 'de' => 'German', 'es' => 'Spanish', 'pt' => 'Portuguese',
    'sw' => 'Kiswahili', 'ar' => 'Arabic',
  ];
  $ccoEnabledLocales = [];
  try {
      $ccoEnabledLocales = \Illuminate\Support\Facades\DB::table('setting')
          ->where('scope', 'i18n_languages')->where('editable', 1)
          ->pluck('name')->toArray();
  } catch (\Throwable $e) {}
  if (empty($ccoEnabledLocales)) $ccoEnabledLocales = array_keys($ccoCultureLabels);

  $ccoIsAdmin = \AhgCore\Services\AclService::isAdministrator();
  $ccoDefaultTarget = (string) app()->getLocale() !== 'en' ? (string) app()->getLocale() : 'af';
@endphp

@if($ccoMuseumRowId)
<div class="modal fade" id="ahgTranslateCcoValuesModal-{{ $objectId }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-warning-subtle">
        <h5 class="modal-title"><i class="fas fa-landmark me-2"></i>{{ __('Translate field data values (CCO / Museum metadata)') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <div class="alert alert-warning small mb-3 py-2">
          <strong><i class="fas fa-info-circle me-1"></i>{{ __('You are editing record CONTENT, not field labels.') }}</strong><br>
          {{ __('Each row\'s "Source" column shows the value currently stored on this record (e.g. "Painting" for the work_type field). Translate that value into the target culture and Save. To translate the field LABELS themselves (e.g. "Work type" → "Werktipe"), use the Translate (side-by-side) modal — that\'s a different surface saved into lang/{locale}.json.') }}
        </div>

        <div class="row g-2 align-items-end mb-3">
          <div class="col-md-4">
            <label class="form-label small mb-0 fw-semibold">{{ __('Source language') }}</label>
            <select class="form-select form-select-sm cco-source" data-object-id="{{ $objectId }}">
              @foreach(array_keys($ccoByCulture) as $c)
                <option value="{{ $c }}" {{ $c === 'en' ? 'selected' : '' }}>{{ ($ccoCultureLabels[$c] ?? strtoupper($c)) . ' (' . $c . ')' }}</option>
              @endforeach
            </select>
            <small class="text-muted">{{ __('Read-only. The text already stored in this culture.') }}</small>
          </div>
          <div class="col-md-4">
            <label class="form-label small mb-0 fw-semibold">{{ __('Target language') }}</label>
            <select class="form-select form-select-sm cco-target" data-object-id="{{ $objectId }}">
              @foreach($ccoEnabledLocales as $c)
                @if($c === 'en') @continue @endif
                <option value="{{ $c }}" {{ $c === $ccoDefaultTarget ? 'selected' : '' }}>{{ ($ccoCultureLabels[$c] ?? strtoupper($c)) . ' (' . $c . ')' }}</option>
              @endforeach
            </select>
            <small class="text-muted">{{ __('Save will write to this culture.') }}</small>
          </div>
          <div class="col-md-4">
            @if($ccoIsAdmin)
              <div class="form-check form-switch">
                <input class="form-check-input cco-review-toggle" type="checkbox" id="cco-review-{{ $objectId }}">
                <label class="form-check-label small" for="cco-review-{{ $objectId }}">
                  <i class="fas fa-user-check me-1"></i>{{ __('Request second review') }}
                </label>
              </div>
              <small class="text-muted">{{ __('Queue saves instead of applying immediately.') }}</small>
            @else
              <div class="alert alert-info py-2 mb-0 small">
                <i class="fas fa-info-circle me-1"></i>{{ __('Your saves will be queued for an Administrator to review.') }}
              </div>
            @endif
          </div>
        </div>

        <div class="d-flex flex-wrap gap-3 mb-3">
          <div class="form-check form-switch">
            <input class="form-check-input cco-show-empty-source" type="checkbox" id="cco-show-empty-{{ $objectId }}" data-object-id="{{ $objectId }}">
            <label class="form-check-label small" for="cco-show-empty-{{ $objectId }}">
              <i class="fas fa-eye me-1"></i>{{ __('Show fields with no en value (full schema)') }}
            </label>
            <div class="small text-muted">{{ __('Default hides them — empty source = nothing to translate.') }}</div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input cco-only-empty-target" type="checkbox" id="cco-only-empty-tgt-{{ $objectId }}" data-object-id="{{ $objectId }}">
            <label class="form-check-label small" for="cco-only-empty-tgt-{{ $objectId }}">
              <i class="fas fa-filter me-1"></i>{{ __('Only show empty target fields (untranslated)') }}
            </label>
          </div>
        </div>

        @foreach($ccoGroups as $groupTitle => $groupFields)
          <div class="card mb-2 border-warning-subtle cco-group" data-object-id="{{ $objectId }}">
            <div class="card-header py-1 small fw-semibold" style="background:#fff8e1">{{ __($groupTitle) }}</div>
            <div class="card-body p-0">
              <table class="table table-sm table-bordered align-top mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width:18%;">{{ __('Field') }}</th>
                    <th style="width:38%;"><span class="badge bg-light text-dark cco-src-label" data-object-id="{{ $objectId }}">en</span> {{ __('Source value') }}</th>
                    <th style="width:38%;"><span class="badge bg-light text-dark cco-tgt-label" data-object-id="{{ $objectId }}">{{ $ccoDefaultTarget }}</span> {{ __('Target — edit here') }}</th>
                    <th style="width:6%;"></th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($groupFields as $fieldKey => $fieldLabel)
                    <tr class="cco-row" data-object-id="{{ $objectId }}" data-field="{{ $fieldKey }}" data-has-source="{{ ($ccoHasSource[$fieldKey] ?? false) ? '1' : '0' }}">
                      <td><span class="small fw-semibold">{{ __($fieldLabel) }}</span><br><code class="small text-muted">{{ $fieldKey }}</code></td>
                      <td><div class="cco-src small text-muted" style="white-space:pre-wrap;"></div></td>
                      <td>
                        <textarea class="form-control form-control-sm cco-tgt" rows="2"></textarea>
                        <div class="cco-status small mt-1" style="min-height:1em;"></div>
                      </td>
                      <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary mb-1 cco-mt-btn w-100" title="{{ __('AI suggest from source') }}"><i class="fas fa-magic"></i></button>
                        <button type="button" class="btn btn-sm btn-success cco-save-btn w-100" title="{{ __('Save row') }}"><i class="fas fa-save"></i></button>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @endforeach

      </div>
      <div class="modal-footer flex-wrap gap-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
        <a href="{{ route('ahgtranslation.drafts') }}" class="btn btn-outline-warning" target="_blank">
          <i class="fas fa-clipboard-check me-1"></i>{{ __('Translation workflow — record drafts queue') }}
        </a>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  window.__ccoData = window.__ccoData || {};
  window.__ccoData[{{ $objectId }}] = @json($ccoByCulture);

  function ccoApply(objectId) {
    var modal = document.getElementById('ahgTranslateCcoValuesModal-' + objectId);
    if (!modal) return;
    var srcSel = modal.querySelector('.cco-source');
    var tgtSel = modal.querySelector('.cco-target');
    var src = srcSel.value, tgt = tgtSel.value;

    modal.querySelectorAll('.cco-src-label').forEach(function (e) { e.textContent = src; });
    modal.querySelectorAll('.cco-tgt-label').forEach(function (e) { e.textContent = tgt; });

    var byCulture = window.__ccoData[objectId] || {};
    var srcRow = byCulture[src] || byCulture['en'] || {};
    var tgtRow = byCulture[tgt] || {};

    modal.querySelectorAll('.cco-row[data-object-id="' + objectId + '"]').forEach(function (row) {
      var f = row.getAttribute('data-field');
      row.querySelector('.cco-src').textContent = (srcRow[f] || '').toString();
      row.querySelector('.cco-tgt').value = (tgtRow[f] || '').toString();
    });
    ccoApplyFilters(objectId);
  }

  function ccoApplyFilters(objectId) {
    var modal = document.getElementById('ahgTranslateCcoValuesModal-' + objectId);
    if (!modal) return;
    var showEmpty = (modal.querySelector('.cco-show-empty-source') || {}).checked;
    var onlyEmptyTgt = (modal.querySelector('.cco-only-empty-target') || {}).checked;
    modal.querySelectorAll('.cco-row[data-object-id="' + objectId + '"]').forEach(function (row) {
      var hasSource = row.getAttribute('data-has-source') === '1';
      var tgtVal = (row.querySelector('.cco-tgt') || {}).value || '';
      var visible = true;
      if (!showEmpty && !hasSource) visible = false;
      if (onlyEmptyTgt && tgtVal.trim() !== '') visible = false;
      row.style.display = visible ? '' : 'none';
    });
    // Hide group cards that ended up with no visible rows.
    modal.querySelectorAll('.cco-group[data-object-id="' + objectId + '"]').forEach(function (card) {
      var anyVisible = false;
      card.querySelectorAll('.cco-row').forEach(function (row) {
        if (row.style.display !== 'none') anyVisible = true;
      });
      card.style.display = anyVisible ? '' : 'none';
    });
  }

  function ccoInit(objectId) {
    var modal = document.getElementById('ahgTranslateCcoValuesModal-' + objectId);
    if (!modal || modal.__ccoBound) return;
    modal.__ccoBound = true;

    var srcSel = modal.querySelector('.cco-source');
    var tgtSel = modal.querySelector('.cco-target');
    srcSel.addEventListener('change', function () { ccoApply(objectId); });
    tgtSel.addEventListener('change', function () { ccoApply(objectId); });
    var showEmptyToggle = modal.querySelector('.cco-show-empty-source');
    var onlyEmptyTgtToggle = modal.querySelector('.cco-only-empty-target');
    if (showEmptyToggle) showEmptyToggle.addEventListener('change', function () { ccoApplyFilters(objectId); });
    if (onlyEmptyTgtToggle) onlyEmptyTgtToggle.addEventListener('change', function () { ccoApplyFilters(objectId); });

    modal.addEventListener('shown.bs.modal', function () { ccoApply(objectId); });

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
    var saveUrl = "{{ route('ahgtranslation.save') }}";
    var mtUrl   = "{{ route('ahgtranslation.strings.mt-suggest') }}";

    modal.querySelectorAll('.cco-save-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var row = btn.closest('.cco-row');
        var field = row.getAttribute('data-field');
        var status = row.querySelector('.cco-status');
        var value = row.querySelector('.cco-tgt').value;
        var target = tgtSel.value;
        var review = (modal.querySelector('.cco-review-toggle') || {}).checked ? '1' : '0';

        btn.disabled = true; status.textContent = '...';
        var fd = new FormData();
        fd.append('_token', csrf);
        fd.append('object_id', objectId);
        fd.append('class_name', 'QubitMuseumMetadata');
        fd.append('culture', target);
        fd.append('field', field);
        fd.append('value', value);
        fd.append('confirmed', '1');
        fd.append('review', review);
        fetch(saveUrl, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: fd,
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.ok) {
            if (d.state === 'pending') {
              status.innerHTML = '<span class="text-warning">⏱ submitted for review</span>';
            } else {
              status.innerHTML = '<span class="text-success">✓ approved</span>';
              window.__ccoData[objectId] = window.__ccoData[objectId] || {};
              window.__ccoData[objectId][target] = window.__ccoData[objectId][target] || {};
              window.__ccoData[objectId][target][field] = value;
            }
          } else {
            status.innerHTML = '<span class="text-danger">✗ ' + ((d && d.error) || 'save failed') + '</span>';
          }
        })
        .catch(function (e) { status.innerHTML = '<span class="text-danger">✗ ' + e.message + '</span>'; })
        .finally(function () { btn.disabled = false; });
      });
    });

    modal.querySelectorAll('.cco-mt-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var row = btn.closest('.cco-row');
        var srcText = (row.querySelector('.cco-src').textContent || '').trim();
        var status = row.querySelector('.cco-status');
        var tgtArea = row.querySelector('.cco-tgt');
        var target = tgtSel.value;
        if (!srcText) { status.innerHTML = '<span class="text-muted">no source text</span>'; return; }
        btn.disabled = true; status.textContent = 'fetching MT suggestion...';
        var u = mtUrl + '?locale=' + encodeURIComponent(target) + '&text=' + encodeURIComponent(srcText);
        fetch(u, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (d && d.ok && d.translated) {
              // Prefix with [AI] so a machine-translated value is visibly
              // marked in the field and stays marked once saved into
              // museum_metadata_i18n. Strip it later when an admin confirms.
              tgtArea.value = (d.translated.indexOf('[AI]') === 0 ? d.translated : '[AI] ' + d.translated);
              status.innerHTML = '<span class="text-info">MT suggestion — review then save</span>';
            } else {
              status.innerHTML = '<span class="text-danger">✗ MT failed: ' + ((d && d.error) || 'unknown') + '</span>';
            }
          })
          .catch(function (e) { status.innerHTML = '<span class="text-danger">✗ MT error: ' + e.message + '</span>'; })
          .finally(function () { btn.disabled = false; });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () { ccoInit({{ $objectId }}); });
})();
</script>
@endif
