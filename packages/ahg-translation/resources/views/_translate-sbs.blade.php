{{--
  Side-by-Side per-record translator (issue #54-style for *_i18n entities).

  Usage from any show.blade.php:
      @include('ahg-translation::_translate-sbs', ['objectId' => $io->id])

  Renders a Bootstrap modal #ahgTranslateSbsModal-{objectId} with:
    - source culture + target culture dropdowns
    - per-field row: source value (read-only) | editable target | AI-suggest | Save | status
    - admin-only "Request second review" checkbox (otherwise admin auto-applies)
    - editor banner: "your changes will be queued for review"
    - workflow split: admin save = direct write, editor save = ahg_translation_draft

  All saves POST to route('ahgtranslation.save'); the controller branches on role.
--}}
@php
  $objectId = (int) ($objectId ?? 0);

  // Fields per entity class — currently info-object only since all 5 GLAM/DAM
  // sectors share `information_object_i18n`. Actor / repo / term follow when
  // their show pages get their own translate include.
  $allFieldsForIo = [
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

  // Cultures this record actually has rows in (populates the source dropdown)
  $availableCultures = \Illuminate\Support\Facades\DB::table('information_object_i18n')
      ->where('id', $objectId)
      ->pluck('culture')
      ->toArray();

  // Pre-fetched i18n rows so the modal can switch source culture client-side
  // without round-tripping. Map: culture => [field => value]
  $i18nByCulture = [];
  foreach (\Illuminate\Support\Facades\DB::table('information_object_i18n')
      ->where('id', $objectId)
      ->get() as $row) {
      $i18nByCulture[$row->culture] = (array) $row;
  }

  $cultureLabels = [
    'en' => 'English', 'af' => 'Afrikaans', 'zu' => 'isiZulu', 'xh' => 'isiXhosa',
    'st' => 'Sesotho', 'tn' => 'Setswana', 'nso' => 'Sepedi', 'ts' => 'Xitsonga',
    'ss' => 'siSwati', 've' => 'Tshivenda', 'nr' => 'isiNdebele', 'nl' => 'Dutch',
    'fr' => 'French', 'de' => 'German', 'es' => 'Spanish', 'pt' => 'Portuguese',
    'sw' => 'Kiswahili', 'ar' => 'Arabic',
  ];

  $enabledLocales = [];
  try {
      $enabledLocales = \Illuminate\Support\Facades\DB::table('setting')
          ->where('scope', 'i18n_languages')->where('editable', 1)
          ->pluck('name')->toArray();
  } catch (\Throwable $e) {}
  if (empty($enabledLocales)) {
      $enabledLocales = array_keys($cultureLabels);
  }

  $isAdminUser = \AhgCore\Services\AclService::isAdministrator();
  $defaultSource = in_array(app()->getLocale(), $availableCultures, true) ? app()->getLocale() : ($availableCultures[0] ?? 'en');
  $defaultTarget = $isAdminUser ? 'af' : 'af';

  // Curated short list of the most-visible link/button labels across the app.
  // Editable side-by-side in the same modal so an editor doesn't have to
  // hunt them down in /admin/translation/strings. Add more here as needed.
  $commonButtons = [
    // Buttons / actions
    'Edit', 'Delete', 'Save', 'Cancel', 'Add new', 'Submit', 'Back', 'Next',
    'Previous', 'More', 'Close', 'Apply', 'Reset',
    'Filter', 'Sort', 'Print', 'Export', 'Import', 'Translate',
    'Approve', 'Reject', 'Show', 'Hide', 'View', 'Download',
    'Upload', 'Confirm', 'Duplicate', 'Move', 'Rename', 'Refresh',
    // Edit-form action buttons (shown across IO/actor/repository edit pages)
    'Add', 'Add alternative identifier', 'Add child level', 'Add date',
    'Add language', 'Add note', 'Add publication note', 'Add script',
    'Generate', 'Remove',
    // Form badges and status
    'Optional', 'Required', 'Recommended', 'Draft', 'Published',
    'Start', 'End', 'Date', 'Type', 'Value', 'Label', 'Title', 'Identifier', 'Level',
    'This is a mandatory element.',
    // Cart / marketplace
    'Add to cart', 'Remove from cart', 'Place bid', 'Make offer',
    'Add to favourites', 'Remove from favourites', 'My Favourites', 'Cart',
    // Clipboard
    'Add to clipboard', 'Remove from clipboard', 'Clipboard',
    // RiC sidebar block (header + items)
    'RiC', 'Graph Explorer', 'JSON-LD Export', 'Timeline',
    // Tasks sidebar block (header + Calculate dates row + Last run line)
    'Tasks', 'Calculate dates', 'Last run:', 'Never',
    // Finding aid sidebar block + actions
    'Finding aid', 'Download', 'Regenerate',
    // Rights actions (museum/dam/IO show + rights edit page)
    'Add rights', 'Edit rights',
    // AI tools block (header + items)
    'AI Tools', 'AI Translate (MT wizard)',
    // Image viewer toolbar (IO/DAM/Library digital-object viewer)
    'Deep Zoom', 'Mirador', 'Image',
    'OpenSeadragon Deep Zoom', 'Mirador IIIF Viewer',
    // Top nav / global menu
    'Search', 'Browse', 'Login', 'Logout', 'Sign in', 'Sign out',
    'My Profile', 'Change Password', 'My Plugins', 'My Tasks',
    'Help Center', 'Help', 'RiC Tools', 'Language', 'Other languages available',
    'Dashboard', 'Settings', 'Admin', 'Home',
    // Sectors
    'Gallery', 'Library', 'Archive', 'Museum', 'Marketplace',
  ];

  // Pre-load lang/{locale}.json for every enabled locale so the modal can show
  // the existing translation in the target column without an extra round trip.
  // Saves go through ahgtranslation.strings.save (same workflow split as
  // record-content saves: admin auto-applies, editor queues).
  $labelTranslations = [];
  // CCO field labels also live as UI strings — include them so the per-record
  // SBS modal can edit the label translations alongside the IO field labels.
  $ccoLabelStrings = [];
  // Defined below in $museumGroups; flattened here for the labels pre-load.
  // Mirror of the museumGroups arrays — kept in sync manually.
  $ccoLabelStrings = [
    // Section / group headers (rendered as card headers on the show view)
    'CCO / Museum metadata',
    'Object / Work', 'Creator', 'Dates', 'Dates (display only)', 'Materials & technique', 'Measurements',
    'Style / Period / Context', 'Subject', 'Condition & treatment', 'Inscriptions & marks',
    'Edition / State', 'Location / Geography', 'Related works', 'Provenance & rights', 'Cataloguing',
    // Field labels
    'Work type', 'Object type', 'Classification', 'Object class', 'Object category', 'Object sub-category', 'Record type', 'Record level',
    'Creator', 'Role', 'Extent', 'Qualifier', 'Attribution',
    'Date display', 'Date qualifier',
    'Materials', 'Techniques', 'Technique (CCO)', 'Technique qualifier', 'Facture', 'Color', 'Physical appearance',
    'Measurements', 'Dimensions', 'Orientation', 'Shape',
    'Style/Period', 'Style', 'Period', 'Cultural context', 'Cultural group', 'Movement', 'School', 'Dynasty',
    'Indexing type', 'Subject display', 'Subject extent', 'Historical context', 'Architectural context', 'Archaeological context',
    'Condition', 'Condition description', 'Condition agent', 'Condition notes', 'Treatment type', 'Treatment agent', 'Treatment description',
    'Inscription', 'Inscriptions', 'Transcription', 'Inscription type', 'Inscription location', 'Inscription language', 'Translation', 'Mark type', 'Mark description', 'Mark location',
    'Edition description', 'Edition number', 'Edition size', 'State description', 'State identification',
    'Current location', 'Repository', 'Geography', 'Reference number', 'Creation place', 'Creation place type', 'Discovery place', 'Discovery place type',
    'Relationship type', 'Relationship', 'Label',
    'Provenance', 'Provenance text', 'Ownership history', 'Legal status', 'Rights type', 'Rights holder', 'Rights date', 'Rights remarks',
    'Cataloger', 'Institution', 'Remarks',
  ];
  $allUiKeys = array_unique(array_merge(array_values($allFieldsForIo), $commonButtons, $ccoLabelStrings));
  foreach ($enabledLocales as $loc) {
      $p = base_path('lang/' . preg_replace('/[^a-z0-9_-]/i', '', $loc) . '.json');
      if (is_readable($p)) {
          $j = json_decode((string) file_get_contents($p), true);
          if (is_array($j)) {
              // Only keep keys we render — keeps payload small.
              $labelTranslations[$loc] = array_intersect_key($j, array_flip($allUiKeys));
          }
      }
  }

  // ── CCO / Museum metadata (issue #56) ──
  // Only renders if this IO has a museum_metadata row. Pre-load every culture
  // already in museum_metadata_i18n so the modal switches client-side without
  // a round trip, mirroring $i18nByCulture.
  $museumByCulture = [];
  $museumRowId = null;
  $museumGroups = [];
  try {
      $mmRow = \Illuminate\Support\Facades\DB::table('museum_metadata')
          ->where('object_id', $objectId)
          ->first();
      if ($mmRow) {
          $museumRowId = (int) $mmRow->id;
          // Section grouping mirrors the show view (so the editor sees the
          // same blocks the public sees, in the same order).
          $museumGroups = [
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
          // Pre-fetch all museum_metadata_i18n rows for this record. The parent
          // (museum_metadata) row stands in as the source-culture cache (en).
          foreach (\Illuminate\Support\Facades\DB::table('museum_metadata_i18n')
              ->where('id', $museumRowId)->get() as $mi18n) {
              $museumByCulture[$mi18n->culture] = (array) $mi18n;
          }
      }
  } catch (\Throwable $e) {
      // museum_metadata / museum_metadata_i18n missing — skip CCO section.
      $museumRowId = null;
  }
@endphp

<div class="modal fade" id="ahgTranslateSbsModal-{{ $objectId }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title"><i class="fas fa-language me-2"></i>{{ __('Translate this record (side-by-side)') }}</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <div class="row g-2 align-items-end mb-3">
          <div class="col-md-4">
            <label class="form-label small mb-0 fw-semibold">{{ __('Source language') }}</label>
            <select class="form-select form-select-sm sbs-source" data-object-id="{{ $objectId }}">
              @foreach($availableCultures as $c)
                <option value="{{ $c }}" {{ $c === $defaultSource ? 'selected' : '' }}>{{ ($cultureLabels[$c] ?? strtoupper($c)) . ' (' . $c . ')' }}</option>
              @endforeach
            </select>
            <small class="text-muted">{{ __('Read-only. The text already on file in this culture.') }}</small>
          </div>
          <div class="col-md-4">
            <label class="form-label small mb-0 fw-semibold">{{ __('Target language') }}</label>
            <select class="form-select form-select-sm sbs-target" data-object-id="{{ $objectId }}">
              @foreach($enabledLocales as $c)
                @if($c === 'en') @continue @endif
                <option value="{{ $c }}" {{ $c === $defaultTarget ? 'selected' : '' }}>{{ ($cultureLabels[$c] ?? strtoupper($c)) . ' (' . $c . ')' }}</option>
              @endforeach
            </select>
            <small class="text-muted">{{ __('Save will write to this culture.') }}</small>
          </div>
          <div class="col-md-4">
            @if($isAdminUser)
              <div class="form-check form-switch">
                <input class="form-check-input sbs-review-toggle" type="checkbox" id="sbs-review-{{ $objectId }}" data-object-id="{{ $objectId }}">
                <label class="form-check-label small" for="sbs-review-{{ $objectId }}">
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

        <div id="sbs-section-labels-{{ $objectId }}"></div>
        {{-- ── Field LABEL translations (UI strings) ── --}}
        <h6 class="text-uppercase small fw-bold text-muted mt-2 mb-2"><i class="fas fa-tag me-1"></i>{{ __('Field labels') }} <span class="text-muted">({{ __('translations of the field names themselves — saved into lang/{locale}.json') }})</span></h6>
        <div class="table-responsive mb-4">
          <table class="table table-sm table-bordered align-top">
            <thead class="table-light">
              <tr>
                <th style="width:18%;">{{ __('Field key') }}</th>
                <th style="width:38%;"><span class="badge bg-light text-dark">en</span> {{ __('Label (English source)') }}</th>
                <th style="width:38%;"><span class="badge bg-light text-dark sbs-tgt-label-lbl" data-object-id="{{ $objectId }}">{{ $defaultTarget }}</span> {{ __('Label (target — edit here)') }}</th>
                <th style="width:6%;"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($allFieldsForIo as $fieldKey => $fieldLabel)
                <tr class="sbs-lbl-row" data-object-id="{{ $objectId }}" data-source-label="{{ $fieldLabel }}">
                  <td><code class="small">{{ $fieldKey }}</code></td>
                  <td><div class="small">{{ $fieldLabel }}</div></td>
                  <td>
                    <input type="text" class="form-control form-control-sm sbs-lbl-tgt">
                    <div class="sbs-lbl-status small mt-1" style="min-height:1em;"></div>
                  </td>
                  <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary mb-1 sbs-lbl-mt-btn w-100" title="{{ __('AI suggest') }}"><i class="fas fa-magic"></i></button>
                    <button type="button" class="btn btn-sm btn-success sbs-lbl-save-btn w-100" title="{{ __('Save label') }}"><i class="fas fa-save"></i></button>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @if($museumRowId && !empty($museumGroups))
        {{-- ── CCO field LABELS (UI strings — saved to lang/{locale}.json + mirrored to setting_i18n) ── --}}
        <div class="alert alert-success small mt-3 mb-2 py-2">
          <strong><i class="fas fa-tag me-1"></i>{{ __('Use this section to translate FIELD LABELS (the field names themselves).') }}</strong>
          {{ __('e.g. "Edition description" → "Uitgawe beskrywing". Saves to lang/{locale}.json and mirrors to setting_i18n. Same place as /admin/translation/strings.') }}
        </div>
        <h6 class="text-uppercase small fw-bold text-success mb-2"><i class="fas fa-landmark me-1"></i>{{ __('CCO / Museum field LABELS') }} <span class="text-success">({{ __('field names — saved into lang/{locale}.json') }})</span></h6>
        @foreach($museumGroups as $groupTitle => $groupFields)
          <div class="card mb-2 border-secondary-subtle">
            <div class="card-header py-1 small fw-semibold" style="background:#f5f5f5">{{ __($groupTitle) }}</div>
            <div class="card-body p-0">
              <table class="table table-sm table-bordered align-top mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width:18%;">{{ __('Field key') }}</th>
                    <th style="width:38%;"><span class="badge bg-light text-dark">en</span> {{ __('Label (English source)') }}</th>
                    <th style="width:38%;"><span class="badge bg-light text-dark sbs-tgt-label-lbl" data-object-id="{{ $objectId }}">{{ $defaultTarget }}</span> {{ __('Label (target — edit here)') }}</th>
                    <th style="width:6%;"></th>
                  </tr>
                </thead>
                <tbody>
                  {{-- Group HEADER itself as an editable row (so admins can translate the section title too). --}}
                  <tr class="sbs-lbl-row table-info" data-object-id="{{ $objectId }}" data-source-label="{{ $groupTitle }}">
                    <td><code class="small">group_header</code></td>
                    <td><div class="small fw-bold">{{ $groupTitle }}</div></td>
                    <td>
                      <input type="text" class="form-control form-control-sm sbs-lbl-tgt">
                      <div class="sbs-lbl-status small mt-1" style="min-height:1em;"></div>
                    </td>
                    <td class="text-end">
                      <button type="button" class="btn btn-sm btn-outline-primary mb-1 sbs-lbl-mt-btn w-100" title="{{ __('AI suggest') }}"><i class="fas fa-magic"></i></button>
                      <button type="button" class="btn btn-sm btn-success sbs-lbl-save-btn w-100" title="{{ __('Save label') }}"><i class="fas fa-save"></i></button>
                    </td>
                  </tr>
                  @foreach($groupFields as $fieldKey => $fieldLabel)
                    <tr class="sbs-lbl-row" data-object-id="{{ $objectId }}" data-source-label="{{ $fieldLabel }}">
                      <td><code class="small">{{ $fieldKey }}</code></td>
                      <td><div class="small">{{ $fieldLabel }}</div></td>
                      <td>
                        <input type="text" class="form-control form-control-sm sbs-lbl-tgt">
                        <div class="sbs-lbl-status small mt-1" style="min-height:1em;"></div>
                      </td>
                      <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary mb-1 sbs-lbl-mt-btn w-100" title="{{ __('AI suggest') }}"><i class="fas fa-magic"></i></button>
                        <button type="button" class="btn btn-sm btn-success sbs-lbl-save-btn w-100" title="{{ __('Save label') }}"><i class="fas fa-save"></i></button>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @endforeach
        @endif

        {{-- ── Common link / button labels (UI strings) ── --}}
        <h6 class="text-uppercase small fw-bold text-muted mt-3 mb-2"><i class="fas fa-mouse-pointer me-1"></i>{{ __('Common buttons & links') }} <span class="text-muted">({{ __('UI strings that appear all over the app — saved into lang/{locale}.json') }})</span></h6>
        <div class="table-responsive mb-4">
          <table class="table table-sm table-bordered align-top">
            <thead class="table-light">
              <tr>
                <th style="width:18%;">{{ __('Key') }}</th>
                <th style="width:38%;"><span class="badge bg-light text-dark">en</span> {{ __('Source') }}</th>
                <th style="width:38%;"><span class="badge bg-light text-dark sbs-tgt-label-lbl" data-object-id="{{ $objectId }}">{{ $defaultTarget }}</span> {{ __('Target — edit here') }}</th>
                <th style="width:6%;"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($commonButtons as $btnKey)
                <tr class="sbs-lbl-row" data-object-id="{{ $objectId }}" data-source-label="{{ $btnKey }}">
                  <td><code class="small">{{ $btnKey }}</code></td>
                  <td><div class="small">{{ $btnKey }}</div></td>
                  <td>
                    <input type="text" class="form-control form-control-sm sbs-lbl-tgt">
                    <div class="sbs-lbl-status small mt-1" style="min-height:1em;"></div>
                  </td>
                  <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary mb-1 sbs-lbl-mt-btn w-100" title="{{ __('AI suggest') }}"><i class="fas fa-magic"></i></button>
                    <button type="button" class="btn btn-sm btn-success sbs-lbl-save-btn w-100" title="{{ __('Save label') }}"><i class="fas fa-save"></i></button>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @if($isAdminUser)
          <p class="small text-muted mb-3">
            <i class="fas fa-external-link-alt me-1"></i>
            {{ __('Need to translate other UI strings beyond this curated list? ') }}
            <a href="{{ route('ahgtranslation.strings') }}" target="_blank">{{ __('Open the full UI string editor →') }}</a>
          </p>
        @endif

        <div id="sbs-section-values-{{ $objectId }}"></div>
        {{-- ── Field VALUE translations (record content in *_i18n) ── --}}
        <div class="d-flex justify-content-between align-items-center mt-2 mb-2 flex-wrap gap-2">
          <h6 class="text-uppercase small fw-bold text-muted mb-0"><i class="fas fa-database me-1"></i>{{ __('Field values') }} <span class="text-muted">({{ __('the actual record content — saved into information_object_i18n') }})</span></h6>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input sbs-filter-empty" type="checkbox" id="sbs-filter-empty-{{ $objectId }}" data-object-id="{{ $objectId }}">
            <label class="form-check-label small" for="sbs-filter-empty-{{ $objectId }}">
              <i class="fas fa-filter me-1"></i>{{ __('Only show empty target fields') }}
            </label>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-top">
            <thead class="table-light">
              <tr>
                <th style="width:18%;">{{ __('Field') }}</th>
                <th style="width:38%;"><span class="badge bg-light text-dark sbs-src-label" data-object-id="{{ $objectId }}">{{ $defaultSource }}</span> {{ __('Source') }}</th>
                <th style="width:38%;"><span class="badge bg-light text-dark sbs-tgt-label" data-object-id="{{ $objectId }}">{{ $defaultTarget }}</span> {{ __('Target — edit here') }}</th>
                <th style="width:6%;"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($allFieldsForIo as $fieldKey => $fieldLabel)
                <tr class="sbs-row" data-object-id="{{ $objectId }}" data-field="{{ $fieldKey }}">
                  <td><span class="small fw-semibold">{{ $fieldLabel }}</span><br><code class="small text-muted">{{ $fieldKey }}</code></td>
                  <td><div class="sbs-src small text-muted" style="white-space:pre-wrap;"></div></td>
                  <td>
                    <textarea class="form-control form-control-sm sbs-tgt" rows="3"></textarea>
                    <div class="sbs-status small mt-1" style="min-height:1em;"></div>
                  </td>
                  <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary mb-1 sbs-mt-btn w-100" title="{{ __('AI suggest from source') }}"><i class="fas fa-magic"></i></button>
                    <button type="button" class="btn btn-sm btn-success sbs-save-btn w-100" title="{{ __('Save row') }}"><i class="fas fa-save"></i></button>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{-- CCO field VALUES section moved to its own modal (_translate-cco-values.blade.php).
             Triggered separately from the More menu → "Translate field data values".
             Keeps this modal LABELS-only so admins can't accidentally type label
             text into a value cell. --}}

      </div>
      <div class="modal-footer flex-wrap gap-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
        <a href="{{ route('ahgtranslation.drafts') }}" class="btn btn-outline-warning" target="_blank">
          <i class="fas fa-clipboard-check me-1"></i>{{ __('Translation workflow — record drafts queue') }}
        </a>
        @if(\AhgCore\Services\AclService::isAdministrator())
          <a href="{{ route('ahgtranslation.strings.pending') }}" class="btn btn-outline-warning" target="_blank">
            <i class="fas fa-clipboard-check me-1"></i>{{ __('UI-string review queue') }}
          </a>
        @endif
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  // i18n data preloaded server-side. Switching source language just re-renders
  // from this map — no extra round trip.
  window.__sbsData = window.__sbsData || {};
  window.__sbsData[{{ $objectId }}] = @json($i18nByCulture);

  // Pre-loaded UI label translations per locale (lang/{locale}.json subset).
  // Allows the label rows + button rows to populate without round-tripping.
  window.__sbsLabels = window.__sbsLabels || {};
  window.__sbsLabels[{{ $objectId }}] = @json($labelTranslations);

  {{-- CCO values preload moved to _translate-cco-values.blade.php (own modal). --}}

  function applySource(objectId) {
    var src = document.querySelector('.sbs-source[data-object-id="' + objectId + '"]').value;
    document.querySelectorAll('.sbs-src-label[data-object-id="' + objectId + '"]').forEach(function (e) { e.textContent = src; });
    var data = (window.__sbsData[objectId] || {})[src] || {};
    document.querySelectorAll('.sbs-row[data-object-id="' + objectId + '"]').forEach(function (row) {
      var field = row.getAttribute('data-field');
      var srcEl = row.querySelector('.sbs-src');
      srcEl.textContent = (data[field] || '').toString();
    });
  }

  function applyTargetLabel(objectId) {
    var tgt = document.querySelector('.sbs-target[data-object-id="' + objectId + '"]').value;
    document.querySelectorAll('.sbs-tgt-label[data-object-id="' + objectId + '"]').forEach(function (e) { e.textContent = tgt; });
    // Pre-fill target textarea with existing value if any (so editor sees what's already there)
    var data = (window.__sbsData[objectId] || {})[tgt] || {};
    document.querySelectorAll('.sbs-row[data-object-id="' + objectId + '"]').forEach(function (row) {
      var field = row.getAttribute('data-field');
      row.querySelector('.sbs-tgt').value = (data[field] || '').toString();
    });
  }

  function init(objectId) {
    var modal = document.getElementById('ahgTranslateSbsModal-' + objectId);
    if (!modal || modal.__sbsBound) return;
    modal.__sbsBound = true;

    var srcSel = modal.querySelector('.sbs-source');
    var tgtSel = modal.querySelector('.sbs-target');
    srcSel.addEventListener('change', function () { applySource(objectId); });
    tgtSel.addEventListener('change', function () { applyTargetLabel(objectId); });

    // First fill on open + optional scroll to a specific section based on the
    // trigger's data-sbs-section attribute ("labels" or "values").
    modal.addEventListener('shown.bs.modal', function (e) {
      applySource(objectId);
      applyTargetLabel(objectId);
      var trigger = e.relatedTarget;
      var section = trigger && trigger.getAttribute ? trigger.getAttribute('data-sbs-section') : null;
      if (section) {
        var anchor = modal.querySelector('#sbs-section-' + section + '-' + objectId);
        if (anchor && anchor.scrollIntoView) anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
    var saveUrl     = "{{ route('ahgtranslation.save') }}";
    var labelSaveUrl = "{{ route('ahgtranslation.strings.save') }}";
    var mtUrl       = "{{ route('ahgtranslation.strings.mt-suggest') }}";

    // ─── Empty-only filter (Field values section) ───
    function applyEmptyFilter() {
      var on = (modal.querySelector('.sbs-filter-empty[data-object-id="' + {{ $objectId }} + '"]') || {}).checked;
      modal.querySelectorAll('.sbs-row[data-object-id="' + {{ $objectId }} + '"]').forEach(function (row) {
        if (!on) {
          row.style.display = '';
          return;
        }
        var v = (row.querySelector('.sbs-tgt') || {}).value || '';
        row.style.display = v.trim() === '' ? '' : 'none';
      });
    }
    var emptyToggle = modal.querySelector('.sbs-filter-empty[data-object-id="' + {{ $objectId }} + '"]');
    if (emptyToggle) emptyToggle.addEventListener('change', applyEmptyFilter);
    // Re-apply when switching target (textareas re-pre-fill, so what's "empty"
    // changes accordingly).
    var prevApplyTargetForFilter = applyTargetLabel;
    applyTargetLabel = function (objId) { prevApplyTargetForFilter(objId); applyEmptyFilter(); };

    // ─── Label / button rows (UI strings — saved to lang/{locale}.json) ───
    function applyLabelTargetValues(objId) {
      var tgt = tgtSel.value;
      var labelsByLocale = window.__sbsLabels[objId] || {};
      var current = labelsByLocale[tgt] || {};
      modal.querySelectorAll('.sbs-lbl-row[data-object-id="' + objId + '"]').forEach(function (row) {
        var srcLabel = row.getAttribute('data-source-label');
        row.querySelector('.sbs-lbl-tgt').value = current[srcLabel] || '';
      });
      modal.querySelectorAll('.sbs-tgt-label-lbl[data-object-id="' + objId + '"]').forEach(function (e) { e.textContent = tgt; });
    }
    // Fire after the existing applyTargetLabel
    var prevApplyTargetLabel = applyTargetLabel;
    applyTargetLabel = function (objId) { prevApplyTargetLabel(objId); applyLabelTargetValues(objId); };

    modal.querySelectorAll('.sbs-lbl-save-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var row = btn.closest('.sbs-lbl-row');
        var srcLabel = row.getAttribute('data-source-label');
        var input    = row.querySelector('.sbs-lbl-tgt');
        var status   = row.querySelector('.sbs-lbl-status');
        var target   = tgtSel.value;
        var review = (modal.querySelector('.sbs-review-toggle') || {}).checked ? '1' : '0';

        btn.disabled = true; status.textContent = '...';
        var fd = new FormData();
        fd.append('_token', csrf);
        fd.append('locale', target);
        fd.append('key',    srcLabel);
        fd.append('value',  input.value);
        fd.append('review', review);
        fetch(labelSaveUrl, {
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
              window.__sbsLabels[{{ $objectId }}] = window.__sbsLabels[{{ $objectId }}] || {};
              window.__sbsLabels[{{ $objectId }}][target] = window.__sbsLabels[{{ $objectId }}][target] || {};
              window.__sbsLabels[{{ $objectId }}][target][srcLabel] = input.value;
            }
          } else {
            status.innerHTML = '<span class="text-danger">✗ ' + ((d && d.error) || 'save failed') + '</span>';
          }
        })
        .catch(function (e) { status.innerHTML = '<span class="text-danger">✗ ' + e.message + '</span>'; })
        .finally(function () { btn.disabled = false; });
      });
    });

    modal.querySelectorAll('.sbs-lbl-mt-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var row = btn.closest('.sbs-lbl-row');
        var srcLabel = row.getAttribute('data-source-label');
        var input    = row.querySelector('.sbs-lbl-tgt');
        var status   = row.querySelector('.sbs-lbl-status');
        var target   = tgtSel.value;
        btn.disabled = true; status.textContent = 'fetching MT...';
        var u = mtUrl + '?locale=' + encodeURIComponent(target) + '&text=' + encodeURIComponent(srcLabel);
        fetch(u, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (d && d.ok && d.translated) {
              input.value = d.translated;
              status.innerHTML = '<span class="text-info">MT — review then save</span>';
            } else {
              status.innerHTML = '<span class="text-danger">✗ ' + ((d && d.error) || 'MT failed') + '</span>';
            }
          })
          .catch(function (e) { status.innerHTML = '<span class="text-danger">✗ ' + e.message + '</span>'; })
          .finally(function () { btn.disabled = false; });
      });
    });
    // ─── End label/button rows ───

    // Per-row Save
    modal.querySelectorAll('.sbs-save-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var row = btn.closest('.sbs-row');
        var field  = row.getAttribute('data-field');
        var status = row.querySelector('.sbs-status');
        var value  = row.querySelector('.sbs-tgt').value;
        var target = tgtSel.value;
        var review = (modal.querySelector('.sbs-review-toggle') || {}).checked ? '1' : '0';

        btn.disabled = true; status.textContent = '...';
        var fd = new FormData();
        fd.append('_token', csrf);
        fd.append('object_id', objectId);
        fd.append('culture',   target);
        fd.append('field',     field);
        fd.append('value',     value);
        fd.append('confirmed', '1');
        fd.append('review',    review);
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
              // Live-update the cached i18n so reopening the modal shows the new value
              window.__sbsData[objectId] = window.__sbsData[objectId] || {};
              window.__sbsData[objectId][target] = window.__sbsData[objectId][target] || {};
              window.__sbsData[objectId][target][field] = value;
            }
          } else {
            status.innerHTML = '<span class="text-danger">✗ ' + ((d && d.error) || 'save failed') + '</span>';
          }
        })
        .catch(function (e) { status.innerHTML = '<span class="text-danger">✗ ' + e.message + '</span>'; })
        .finally(function () { btn.disabled = false; });
      });
    });

    {{-- CCO values handlers moved to _translate-cco-values.blade.php (own modal). --}}

    // Per-row AI suggest — reuses /admin/translation/strings/mt-suggest
    // (returns translated text from the Ollama backend per #45). Source text
    // taken from the rendered .sbs-src column.
    modal.querySelectorAll('.sbs-mt-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var row = btn.closest('.sbs-row');
        var srcText = (row.querySelector('.sbs-src').textContent || '').trim();
        var status  = row.querySelector('.sbs-status');
        var tgtArea = row.querySelector('.sbs-tgt');
        var target  = tgtSel.value;
        if (!srcText) { status.innerHTML = '<span class="text-muted">no source text</span>'; return; }

        btn.disabled = true; status.textContent = 'fetching MT suggestion...';
        var u = mtUrl + '?locale=' + encodeURIComponent(target) + '&text=' + encodeURIComponent(srcText);
        fetch(u, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (d && d.ok && d.translated) {
              // Prefix with [AI] so admins can spot machine-translated values at
              // a glance and decide whether to confirm or edit before saving.
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

  document.addEventListener('DOMContentLoaded', function () { init({{ $objectId }}); });
})();
</script>
