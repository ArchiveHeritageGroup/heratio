@extends('theme::layouts.2col')

@section('title', $repository->authorized_form_of_name ?? 'Archival institution')
@section('body-class', 'view repository')

@section('sidebar')
  @include('ahg-core::components.digital-object', ['digitalObjects' => $digitalObjects])

  @if($holdingsCount > 0)
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">Holdings</h5>
      </div>
      <div class="card-body">
        <p class="mb-0">
          <a href="{{ route('informationobject.browse', ['repository' => $repository->id]) }}">
            {{ number_format($holdingsCount) }} description{{ $holdingsCount !== 1 ? 's' : '' }}
          </a>
        </p>
      </div>
    </div>
  @endif
@endsection

@section('content')

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <h1>{{ $repository->authorized_form_of_name }}</h1>

  <div class="mb-3">
    @auth
      <a href="{{ route('repository.edit', $repository->slug) }}" class="btn btn-sm btn-outline-primary">Edit</a>
      <a href="{{ route('repository.confirmDelete', $repository->slug) }}" class="btn btn-sm btn-outline-danger">Delete</a>
      <a href="{{ route('repository.create') }}" class="btn btn-sm btn-outline-success">Add new</a>
      <a href="{{ route('repository.edit', $repository->slug) }}?rename=1" class="btn atom-btn-outline-light" title="Rename"><i class="fas fa-i-cursor me-1"></i>Rename</a>
    @endauth
    <a href="{{ route('repository.print', $repository->slug) }}" class="btn btn-sm btn-outline-secondary" target="_blank">
      <i class="fas fa-print me-1"></i> Print
    </a>
    <button class="btn atom-btn-white clipboard ms-2" data-clipboard-slug="{{ $repository->slug ?? '' }}" data-clipboard-type="repository" title="Add to clipboard">
      <i class="fas fa-paperclip"></i>
    </button>
  </div>

  {{-- Identity area (ISDIAH 5.1) --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Identity area</h2>

    @if($repository->authorized_form_of_name)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Authorized form of name</div>
        <div class="col-md-9">{{ $repository->authorized_form_of_name }}</div>
      </div>
    @endif

    @if($repository->identifier)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Identifier</div>
        <div class="col-md-9">{{ $repository->identifier }}</div>
      </div>
    @endif

    @if($otherNames->isNotEmpty())
      @php $parallelNames = $otherNames->where('type_id', 148); @endphp
      @if($parallelNames->isNotEmpty())
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Parallel form(s) of name</div>
          <div class="col-md-9">
            @foreach($parallelNames as $name)
              <div>{{ $name->name }}</div>
            @endforeach
          </div>
        </div>
      @endif

      @php $otherFormNames = $otherNames->where('type_id', 149); @endphp
      @if($otherFormNames->isNotEmpty())
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Other form(s) of name</div>
          <div class="col-md-9">
            @foreach($otherFormNames as $name)
              <div>{{ $name->name }}</div>
            @endforeach
          </div>
        </div>
      @endif
    @endif

    @if($repositoryTypes->isNotEmpty())
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Repository type</div>
        <div class="col-md-9">
          @foreach($repositoryTypes as $type)
            <span class="badge bg-light text-dark me-1">{{ $type->name }}</span>
          @endforeach
        </div>
      </div>
    @endif
  </section>

  {{-- Contact area (ISDIAH 5.2) --}}
  @if($contacts->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Contact area</h2>
      @foreach($contacts as $contact)
        <div class="card mb-2">
          <div class="card-body">
            @if($contact->contact_person)
              <div><strong>Contact person:</strong> {{ $contact->contact_person }}</div>
            @endif
            @if($contact->street_address)
              <div>{{ $contact->street_address }}</div>
            @endif
            @if($contact->city)
              <div>{{ $contact->city }}{{ $contact->region ? ', ' . $contact->region : '' }} {{ $contact->postal_code ?? '' }}</div>
            @endif
            @if($contact->country_code)
              <div>{{ $contact->country_code }}</div>
            @endif
            @if($contact->telephone)
              <div><strong>Telephone:</strong> {{ $contact->telephone }}</div>
            @endif
            @if($contact->fax)
              <div><strong>Fax:</strong> {{ $contact->fax }}</div>
            @endif
            @if($contact->email)
              <div><strong>Email:</strong> <a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a></div>
            @endif
            @if($contact->website)
              <div><strong>Website:</strong> <a href="{{ $contact->website }}" target="_blank" rel="noopener">{{ $contact->website }}</a></div>
            @endif
          </div>
        </div>
      @endforeach
    </section>
  @endif

  {{-- Description area (ISDIAH 5.3) — from actor_i18n --}}
  @if($repository->history || $repository->geocultural_context || $repository->mandates || $repository->internal_structures || $repository->collecting_policies || $repository->buildings)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Description area</h2>

      @foreach([
        'history' => 'History',
        'geocultural_context' => 'Geographical and cultural context',
        'mandates' => 'Mandates/Sources of authority',
        'internal_structures' => 'Administrative structure',
        'collecting_policies' => 'Collecting policies',
        'buildings' => 'Buildings',
      ] as $field => $label)
        @if($repository->$field)
          <div class="row mb-2">
            <div class="col-md-3 fw-bold">{{ $label }}</div>
            <div class="col-md-9">{!! nl2br(e($repository->$field)) !!}</div>
          </div>
        @endif
      @endforeach
    </section>
  @endif

  {{-- Holdings and finding aids --}}
  @if($repository->holdings || $repository->finding_aids)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Holdings and finding aids</h2>

      @if($repository->holdings)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Archival and other holdings</div>
          <div class="col-md-9">{!! nl2br(e($repository->holdings)) !!}</div>
        </div>
      @endif

      @if($repository->finding_aids)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Finding aids</div>
          <div class="col-md-9">{!! nl2br(e($repository->finding_aids)) !!}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- Access area (ISDIAH 5.4) --}}
  @if($repository->opening_times || $repository->access_conditions || $repository->disabled_access)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Access area</h2>

      @foreach([
        'opening_times' => 'Opening times',
        'access_conditions' => 'Conditions and requirements',
        'disabled_access' => 'Accessibility',
      ] as $field => $label)
        @if($repository->$field)
          <div class="row mb-2">
            <div class="col-md-3 fw-bold">{{ $label }}</div>
            <div class="col-md-9">{!! nl2br(e($repository->$field)) !!}</div>
          </div>
        @endif
      @endforeach
    </section>
  @endif

  {{-- Services area (ISDIAH 5.5) --}}
  @if($repository->research_services || $repository->reproduction_services || $repository->public_facilities)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Services area</h2>

      @foreach([
        'research_services' => 'Research services',
        'reproduction_services' => 'Reproduction services',
        'public_facilities' => 'Public areas',
      ] as $field => $label)
        @if($repository->$field)
          <div class="row mb-2">
            <div class="col-md-3 fw-bold">{{ $label }}</div>
            <div class="col-md-9">{!! nl2br(e($repository->$field)) !!}</div>
          </div>
        @endif
      @endforeach
    </section>
  @endif

  {{-- Control area (ISDIAH 5.6) --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Control area</h2>

    @foreach([
      'desc_institution_identifier' => 'Description identifier',
      'desc_rules' => 'Rules and/or conventions',
      'desc_sources' => 'Sources',
      'desc_revision_history' => 'Revision history',
    ] as $field => $label)
      @if($repository->$field)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">{{ $label }}</div>
          <div class="col-md-9">{!! nl2br(e($repository->$field)) !!}</div>
        </div>
      @endif
    @endforeach

    @if($descStatusName ?? null)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Status</div>
        <div class="col-md-9">{{ $descStatusName }}</div>
      </div>
    @endif

    @if($descDetailName ?? null)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Level of detail</div>
        <div class="col-md-9">{{ $descDetailName }}</div>
      </div>
    @endif

    @if(!empty($languages))
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Language(s)</div>
        <div class="col-md-9">
          @foreach($languages as $lang)
            <span class="badge bg-light text-dark me-1">{{ $lang }}</span>
          @endforeach
        </div>
      </div>
    @endif

    @if(!empty($scripts))
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Script(s)</div>
        <div class="col-md-9">
          @foreach($scripts as $scr)
            <span class="badge bg-light text-dark me-1">{{ $scr }}</span>
          @endforeach
        </div>
      </div>
    @endif

    @if($maintenanceNotes ?? null)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Maintenance notes</div>
        <div class="col-md-9">{!! nl2br(e($maintenanceNotes)) !!}</div>
      </div>
    @endif

    @if($repository->updated_at)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Last updated</div>
        <div class="col-md-9">{{ $repository->updated_at }}</div>
      </div>
    @endif
  </section>

  {{-- Access points --}}
  @if(($thematicAreas ?? collect())->isNotEmpty() || ($geographicSubregions ?? collect())->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Access points</h2>

      @if($thematicAreas->isNotEmpty())
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Thematic area(s)</div>
          <div class="col-md-9">
            @foreach($thematicAreas as $area)
              <span class="badge bg-light text-dark me-1">{{ $area->name }}</span>
            @endforeach
          </div>
        </div>
      @endif

      @if($geographicSubregions->isNotEmpty())
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Geographic subregion(s)</div>
          <div class="col-md-9">
            @foreach($geographicSubregions as $region)
              <span class="badge bg-light text-dark me-1">{{ $region->name }}</span>
            @endforeach
          </div>
        </div>
      @endif
    </section>
  @endif
@endsection
