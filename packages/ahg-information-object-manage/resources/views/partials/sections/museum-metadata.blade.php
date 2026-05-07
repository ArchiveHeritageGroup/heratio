  {{-- ===== Museum / CCO metadata (shown only if this IO has a museum_metadata row) ===== --}}
  @if(!empty($museumMetadata))
    <section id="museum-metadata" class="mb-4">
      <h2 class="fs-5 fw-bold border-bottom pb-2 mb-3">
        <i class="fas fa-landmark me-1"></i> {{ __('CCO / Museum metadata') }}
      </h2>

      {{-- Object / Work section --}}
      @if(($museumMetadata['work_type'] ?? null) || ($museumMetadata['object_type'] ?? null) || ($museumMetadata['classification'] ?? null) || ($museumMetadata['object_class'] ?? null) || ($museumMetadata['object_category'] ?? null) || ($museumMetadata['object_sub_category'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">{{ __('Object / Work') }}</div>
          <div class="card-body">
            @foreach(['work_type' => 'Work type', 'object_type' => 'Object type', 'classification' => 'Classification', 'object_class' => 'Object class', 'object_category' => 'Object category', 'object_sub_category' => 'Object sub-category', 'record_type' => 'Record type', 'record_level' => 'Record level'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ __($label) }}</div><div class="col-sm-8">{{ $museumMetadata[$field] }}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Creator section --}}
      @if(($museumMetadata['creator_identity'] ?? null) || ($museumMetadata['creator_role'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">{{ __('Creator') }}</div>
          <div class="card-body">
            @foreach(['creator_identity' => 'Creator', 'creator_role' => 'Role', 'creator_extent' => 'Extent', 'creator_qualifier' => 'Qualifier', 'creator_attribution' => 'Attribution'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ __($label) }}</div><div class="col-sm-8">{{ $museumMetadata[$field] }}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Dates section --}}
      @if(($museumMetadata['creation_date_display'] ?? null) || ($museumMetadata['creation_date_earliest'] ?? null) || ($museumMetadata['creation_date_latest'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">{{ __('Dates') }}</div>
          <div class="card-body">
            @foreach(['creation_date_display' => 'Date display', 'creation_date_earliest' => 'Earliest date', 'creation_date_latest' => 'Latest date', 'creation_date_qualifier' => 'Date qualifier'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ __($label) }}</div><div class="col-sm-8">{{ $museumMetadata[$field] }}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Materials / Technique section --}}
      @if(($museumMetadata['materials'] ?? null) || ($museumMetadata['techniques'] ?? null) || ($museumMetadata['technique_cco'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">{{ __('Materials & technique') }}</div>
          <div class="card-body">
            @foreach(['materials' => 'Materials', 'techniques' => 'Techniques', 'technique_cco' => 'Technique (CCO)', 'technique_qualifier' => 'Technique qualifier', 'facture_description' => 'Facture', 'color' => 'Color', 'physical_appearance' => 'Physical appearance'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ __($label) }}</div><div class="col-sm-8">{!! nl2br(e($museumMetadata[$field])) !!}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Measurements section --}}
      @if(($museumMetadata['measurements'] ?? null) || ($museumMetadata['dimensions'] ?? null) || ($museumMetadata['orientation'] ?? null) || ($museumMetadata['shape'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">{{ __('Measurements') }}</div>
          <div class="card-body">
            @foreach(['measurements' => 'Measurements', 'dimensions' => 'Dimensions', 'orientation' => 'Orientation', 'shape' => 'Shape'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ __($label) }}</div><div class="col-sm-8">{{ $museumMetadata[$field] }}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Style / Period / Cultural context --}}
      @if(($museumMetadata['style_period'] ?? null) || ($museumMetadata['style'] ?? null) || ($museumMetadata['period'] ?? null) || ($museumMetadata['cultural_context'] ?? null) || ($museumMetadata['cultural_group'] ?? null) || ($museumMetadata['movement'] ?? null) || ($museumMetadata['school'] ?? null) || ($museumMetadata['dynasty'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">{{ __('Style / Period / Context') }}</div>
          <div class="card-body">
            @foreach(['style_period' => 'Style/Period', 'style' => 'Style', 'period' => 'Period', 'cultural_context' => 'Cultural context', 'cultural_group' => 'Cultural group', 'movement' => 'Movement', 'school' => 'School', 'dynasty' => 'Dynasty'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ __($label) }}</div><div class="col-sm-8">{{ $museumMetadata[$field] }}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Subject section --}}
      @if(($museumMetadata['subject_indexing_type'] ?? null) || ($museumMetadata['subject_display'] ?? null) || ($museumMetadata['subject_extent'] ?? null) || ($museumMetadata['historical_context'] ?? null) || ($museumMetadata['architectural_context'] ?? null) || ($museumMetadata['archaeological_context'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">{{ __('Subject') }}</div>
          <div class="card-body">
            @foreach(['subject_indexing_type' => 'Indexing type', 'subject_display' => 'Subject display', 'subject_extent' => 'Subject extent', 'historical_context' => 'Historical context', 'architectural_context' => 'Architectural context', 'archaeological_context' => 'Archaeological context'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ __($label) }}</div><div class="col-sm-8">{!! nl2br(e($museumMetadata[$field])) !!}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Condition / Treatment section --}}
      @if(($museumMetadata['condition_term'] ?? null) || ($museumMetadata['condition_notes'] ?? null) || ($museumMetadata['condition_description'] ?? null) || ($museumMetadata['treatment_type'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">{{ __('Condition & treatment') }}</div>
          <div class="card-body">
            @foreach(['condition_term' => 'Condition', 'condition_date' => 'Condition date', 'condition_description' => 'Condition description', 'condition_agent' => 'Condition agent', 'condition_notes' => 'Condition notes', 'treatment_type' => 'Treatment type', 'treatment_date' => 'Treatment date', 'treatment_agent' => 'Treatment agent', 'treatment_description' => 'Treatment description'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ __($label) }}</div><div class="col-sm-8">{!! nl2br(e($museumMetadata[$field])) !!}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Inscriptions / Marks section --}}
      @if(($museumMetadata['inscription'] ?? null) || ($museumMetadata['inscriptions'] ?? null) || ($museumMetadata['inscription_transcription'] ?? null) || ($museumMetadata['mark_type'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">{{ __('Inscriptions & marks') }}</div>
          <div class="card-body">
            @foreach(['inscription' => 'Inscription', 'inscriptions' => 'Inscriptions', 'inscription_transcription' => 'Transcription', 'inscription_type' => 'Inscription type', 'inscription_location' => 'Inscription location', 'inscription_language' => 'Inscription language', 'inscription_translation' => 'Translation', 'mark_type' => 'Mark type', 'mark_description' => 'Mark description', 'mark_location' => 'Mark location'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ __($label) }}</div><div class="col-sm-8">{!! nl2br(e($museumMetadata[$field])) !!}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Edition section --}}
      @if(($museumMetadata['edition_description'] ?? null) || ($museumMetadata['edition_number'] ?? null) || ($museumMetadata['edition_size'] ?? null) || ($museumMetadata['state_description'] ?? null) || ($museumMetadata['state_identification'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">{{ __('Edition / State') }}</div>
          <div class="card-body">
            @foreach(['edition_description' => 'Edition description', 'edition_number' => 'Edition number', 'edition_size' => 'Edition size', 'state_description' => 'State description', 'state_identification' => 'State identification'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ __($label) }}</div><div class="col-sm-8">{!! nl2br(e($museumMetadata[$field])) !!}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Location section --}}
      @if(($museumMetadata['current_location'] ?? null) || ($museumMetadata['current_location_repository'] ?? null) || ($museumMetadata['creation_place'] ?? null) || ($museumMetadata['discovery_place'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">{{ __('Location / Geography') }}</div>
          <div class="card-body">
            @foreach(['current_location' => 'Current location', 'current_location_repository' => 'Repository', 'current_location_geography' => 'Geography', 'current_location_coordinates' => 'Coordinates', 'current_location_ref_number' => 'Reference number', 'creation_place' => 'Creation place', 'creation_place_type' => 'Creation place type', 'discovery_place' => 'Discovery place', 'discovery_place_type' => 'Discovery place type'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ __($label) }}</div><div class="col-sm-8">{{ $museumMetadata[$field] }}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Related works section --}}
      @if(($museumMetadata['related_work_type'] ?? null) || ($museumMetadata['related_work_label'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">{{ __('Related works') }}</div>
          <div class="card-body">
            @foreach(['related_work_type' => 'Relationship type', 'related_work_relationship' => 'Relationship', 'related_work_label' => 'Label', 'related_work_id' => 'Identifier'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ __($label) }}</div><div class="col-sm-8">{{ $museumMetadata[$field] }}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Provenance / Rights section --}}
      @if(($museumMetadata['provenance'] ?? null) || ($museumMetadata['provenance_text'] ?? null) || ($museumMetadata['ownership_history'] ?? null) || ($museumMetadata['legal_status'] ?? null) || ($museumMetadata['rights_type'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">{{ __('Provenance & rights') }}</div>
          <div class="card-body">
            @foreach(['provenance' => 'Provenance', 'provenance_text' => 'Provenance text', 'ownership_history' => 'Ownership history', 'legal_status' => 'Legal status', 'rights_type' => 'Rights type', 'rights_holder' => 'Rights holder', 'rights_date' => 'Rights date', 'rights_remarks' => 'Rights remarks'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ __($label) }}</div><div class="col-sm-8">{!! nl2br(e($museumMetadata[$field])) !!}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Cataloguing section --}}
      @if(($museumMetadata['cataloger_name'] ?? null) || ($museumMetadata['cataloging_date'] ?? null) || ($museumMetadata['cataloging_institution'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">{{ __('Cataloguing') }}</div>
          <div class="card-body">
            @foreach(['cataloger_name' => 'Cataloger', 'cataloging_date' => 'Date', 'cataloging_institution' => 'Institution', 'cataloging_remarks' => 'Remarks'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ __($label) }}</div><div class="col-sm-8">{!! nl2br(e($museumMetadata[$field])) !!}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

    </section>
@endif
