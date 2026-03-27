@extends('theme::layouts.print')

@section('title', $repository->authorized_form_of_name ?? config('app.ui_label_repository', 'Archival institution'))
@section('record-title', $repository->authorized_form_of_name ?? '[Untitled]')
@section('record-type')
  {{ config('app.ui_label_repository', 'Archival institution') }}
  @if($repository->identifier) &mdash; {{ $repository->identifier }} @endif
@endsection

@section('content')

  {{-- ===== 1. Identity area (ISDIAH 5.1) ===== --}}
  <h2 class="section-heading">Identity area</h2>

  @if($repository->authorized_form_of_name)
    <div class="field-row">
      <div class="field-label">Authorized form of name</div>
      <div class="field-value">{{ $repository->authorized_form_of_name }}</div>
    </div>
  @endif

  @if($repository->identifier)
    <div class="field-row">
      <div class="field-label">Identifier</div>
      <div class="field-value">{{ $repository->identifier }}</div>
    </div>
  @endif

  @if(($otherNames ?? collect())->isNotEmpty())
    @php $parallelNames = $otherNames->where('type_id', 148); @endphp
    @if($parallelNames->isNotEmpty())
      <div class="field-row">
        <div class="field-label">Parallel form(s) of name</div>
        <div class="field-value">
          @foreach($parallelNames as $name)
            <div>{{ $name->name }}</div>
          @endforeach
        </div>
      </div>
    @endif

    @php $otherFormNames = $otherNames->where('type_id', 149); @endphp
    @if($otherFormNames->isNotEmpty())
      <div class="field-row">
        <div class="field-label">Other form(s) of name</div>
        <div class="field-value">
          @foreach($otherFormNames as $name)
            <div>{{ $name->name }}</div>
          @endforeach
        </div>
      </div>
    @endif
  @endif

  @if(($repositoryTypes ?? collect())->isNotEmpty())
    <div class="field-row">
      <div class="field-label">Repository type</div>
      <div class="field-value">
        @foreach($repositoryTypes as $type)
          {{ $type->name }}@if(!$loop->last), @endif
        @endforeach
      </div>
    </div>
  @endif

  {{-- ===== 2. Contact area (ISDIAH 5.2) ===== --}}
  @if($contacts->isNotEmpty())
    <h2 class="section-heading">Contact area</h2>
    @foreach($contacts as $contact)
      <div class="contact-block">
        @if($contact->contact_person) <div><strong>Contact person:</strong> {{ $contact->contact_person }}</div> @endif
        @if($contact->street_address) <div>{{ $contact->street_address }}</div> @endif
        @if($contact->city)
          <div>{{ $contact->city }}{{ $contact->region ? ', ' . $contact->region : '' }} {{ $contact->postal_code ?? '' }}</div>
        @endif
        @if($contact->country_code) <div>{{ $contact->country_code }}</div> @endif
        @if($contact->telephone) <div><strong>Telephone:</strong> {{ $contact->telephone }}</div> @endif
        @if($contact->fax) <div><strong>Fax:</strong> {{ $contact->fax }}</div> @endif
        @if($contact->email) <div><strong>Email:</strong> {{ $contact->email }}</div> @endif
        @if($contact->website) <div><strong>Website:</strong> {{ $contact->website }}</div> @endif
      </div>
    @endforeach
  @endif

  {{-- ===== 3. Description area (ISDIAH 5.3) ===== --}}
  @if($repository->history || $repository->geocultural_context || $repository->mandates || $repository->internal_structures || $repository->collecting_policies || $repository->buildings)
    <h2 class="section-heading">Description area</h2>
    @foreach([
      'history' => 'History',
      'geocultural_context' => 'Geographical and cultural context',
      'mandates' => 'Mandates/Sources of authority',
      'internal_structures' => 'Administrative structure',
      'collecting_policies' => 'Collecting policies',
      'buildings' => 'Buildings',
    ] as $field => $label)
      @if($repository->$field)
        <div class="field-row">
          <div class="field-label">{{ $label }}</div>
          <div class="field-value">{!! nl2br(e($repository->$field)) !!}</div>
        </div>
      @endif
    @endforeach
  @endif

  {{-- ===== 4. Holdings and finding aids (ISDIAH 5.3 cont.) ===== --}}
  @if($repository->holdings || $repository->finding_aids)
    <h2 class="section-heading">Holdings and finding aids</h2>

    @if($repository->holdings)
      <div class="field-row">
        <div class="field-label">Archival and other holdings</div>
        <div class="field-value">{!! nl2br(e($repository->holdings)) !!}</div>
      </div>
    @endif

    @if($repository->finding_aids)
      <div class="field-row">
        <div class="field-label">Finding aids</div>
        <div class="field-value">{!! nl2br(e($repository->finding_aids)) !!}</div>
      </div>
    @endif
  @endif

  {{-- ===== 5. Access area (ISDIAH 5.4) ===== --}}
  @if($repository->opening_times || $repository->access_conditions || $repository->disabled_access)
    <h2 class="section-heading">Access area</h2>
    @foreach([
      'opening_times' => 'Opening times',
      'access_conditions' => 'Conditions and requirements',
      'disabled_access' => 'Accessibility',
    ] as $field => $label)
      @if($repository->$field)
        <div class="field-row">
          <div class="field-label">{{ $label }}</div>
          <div class="field-value">{!! nl2br(e($repository->$field)) !!}</div>
        </div>
      @endif
    @endforeach
  @endif

  {{-- ===== 6. Services area (ISDIAH 5.5) ===== --}}
  @if($repository->research_services || $repository->reproduction_services || $repository->public_facilities)
    <h2 class="section-heading">Services area</h2>
    @foreach([
      'research_services' => 'Research services',
      'reproduction_services' => 'Reproduction services',
      'public_facilities' => 'Public areas',
    ] as $field => $label)
      @if($repository->$field)
        <div class="field-row">
          <div class="field-label">{{ $label }}</div>
          <div class="field-value">{!! nl2br(e($repository->$field)) !!}</div>
        </div>
      @endif
    @endforeach
  @endif

  {{-- ===== 7. Control area (ISDIAH 5.6) ===== --}}
  <h2 class="section-heading">Control area</h2>

  @foreach([
    'desc_institution_identifier' => 'Description identifier',
    'desc_rules' => 'Rules and/or conventions',
    'desc_sources' => 'Sources',
    'desc_revision_history' => 'Revision history',
  ] as $field => $label)
    @if($repository->$field)
      <div class="field-row">
        <div class="field-label">{{ $label }}</div>
        <div class="field-value">{!! nl2br(e($repository->$field)) !!}</div>
      </div>
    @endif
  @endforeach

  @if($descStatusName ?? null)
    <div class="field-row">
      <div class="field-label">Status</div>
      <div class="field-value">{{ $descStatusName }}</div>
    </div>
  @endif

  @if($descDetailName ?? null)
    <div class="field-row">
      <div class="field-label">Level of detail</div>
      <div class="field-value">{{ $descDetailName }}</div>
    </div>
  @endif

  @if(!empty($languages ?? []))
    <div class="field-row">
      <div class="field-label">Language(s)</div>
      <div class="field-value">{{ implode(', ', $languages) }}</div>
    </div>
  @endif

  @if(!empty($scripts ?? []))
    <div class="field-row">
      <div class="field-label">Script(s)</div>
      <div class="field-value">{{ implode(', ', $scripts) }}</div>
    </div>
  @endif

  @if($maintenanceNotes ?? null)
    <div class="field-row">
      <div class="field-label">Maintenance notes</div>
      <div class="field-value">{!! nl2br(e($maintenanceNotes)) !!}</div>
    </div>
  @endif

  @if($holdingsCount > 0)
    <div class="field-row">
      <div class="field-label">Holdings count</div>
      <div class="field-value">{{ number_format($holdingsCount) }} description{{ $holdingsCount !== 1 ? 's' : '' }}</div>
    </div>
  @endif

  @if($repository->updated_at)
    <div class="field-row">
      <div class="field-label">Last updated</div>
      <div class="field-value">{{ $repository->updated_at }}</div>
    </div>
  @endif

  {{-- ===== 8. Access points ===== --}}
  @if(($thematicAreas ?? collect())->isNotEmpty() || ($geographicSubregions ?? collect())->isNotEmpty())
    <h2 class="section-heading">Access points</h2>

    @if(($thematicAreas ?? collect())->isNotEmpty())
      <div class="field-row">
        <div class="field-label">Thematic area(s)</div>
        <div class="field-value">
          @foreach($thematicAreas as $area)
            {{ $area->name }}@if(!$loop->last), @endif
          @endforeach
        </div>
      </div>
    @endif

    @if(($geographicSubregions ?? collect())->isNotEmpty())
      <div class="field-row">
        <div class="field-label">Geographic subregion(s)</div>
        <div class="field-value">
          @foreach($geographicSubregions as $region)
            {{ $region->name }}@if(!$loop->last), @endif
          @endforeach
        </div>
      </div>
    @endif
  @endif

@endsection
