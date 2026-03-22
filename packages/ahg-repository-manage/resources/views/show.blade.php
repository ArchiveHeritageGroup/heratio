@extends('theme::layouts.3col')

@section('title', $repository->authorized_form_of_name ?? 'Archival institution')
@section('body-class', 'view repository')

@section('sidebar')
  @include('ahg-core::components.digital-object', ['digitalObjects' => $digitalObjects])

  @if($holdingsCount > 0)
    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
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

@section('right')
  <nav>
    {{-- Clipboard --}}
    <h4 class="h5 mb-2">Clipboard</h4>
    <ul class="list-unstyled mb-3">
      <li>
        @include('ahg-core::clipboard._button', ['slug' => $repository->slug, 'type' => 'repository', 'wide' => true])
      </li>
    </ul>

    {{-- Primary contact in right sidebar (matching AtoM) --}}
    @if($contacts->isNotEmpty())
      @php $primaryContact = $contacts->first(); @endphp
      <section id="primary-contact" class="mb-3">
        <h4 class="h5 mb-2">Primary contact</h4>
        <div class="mb-2">
          @if($primaryContact->street_address)<div>{{ $primaryContact->street_address }}</div>@endif
          @if($primaryContact->city || $primaryContact->region || $primaryContact->postal_code)
            <div>{{ $primaryContact->city ?? '' }}{{ $primaryContact->region ? ', ' . $primaryContact->region : '' }} {{ $primaryContact->postal_code ?? '' }}</div>
          @endif
          @if($primaryContact->country_code)<div>{{ $primaryContact->country_code }}</div>@endif
          @if($primaryContact->telephone)<div>{{ $primaryContact->telephone }}</div>@endif
        </div>
        <div class="d-flex gap-2 flex-wrap">
          @if($primaryContact->website)
            <a class="btn atom-btn-white" href="{{ str_starts_with($primaryContact->website, 'http') ? $primaryContact->website : 'http://' . $primaryContact->website }}" target="_blank" rel="noopener">Website</a>
          @endif
          @if($primaryContact->email)
            <a class="btn atom-btn-white" href="mailto:{{ $primaryContact->email }}">Email</a>
          @endif
        </div>
      </section>
    @endif
  </nav>
@endsection

@section('content')

  <h1>{{ $repository->authorized_form_of_name }}</h1>

  {{-- Breadcrumb (matching AtoM) --}}
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('repository.browse') }}">Archival institution</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $repository->authorized_form_of_name }}</li>
    </ol>
  </nav>

  <div class="mb-3">
    @auth
      <a href="{{ route('repository.edit', $repository->slug) }}" class="btn btn-sm atom-btn-white">Edit</a>
      <a href="{{ route('repository.confirmDelete', $repository->slug) }}" class="btn btn-sm atom-btn-outline-danger">Delete</a>
      <a href="{{ route('repository.create') }}" class="btn btn-sm atom-btn-outline-success">Add new</a>
      <a href="{{ route('repository.edit', $repository->slug) }}?rename=1" class="btn atom-btn-outline-light" title="Rename"><i class="fas fa-i-cursor me-1"></i>Rename</a>
    @endauth
    <a href="{{ route('repository.print', $repository->slug) }}" class="btn btn-sm atom-btn-white" target="_blank">
      <i class="fas fa-print me-1"></i> Print
    </a>
    <button class="btn atom-btn-white clipboard ms-2" data-clipboard-slug="{{ $repository->slug ?? '' }}" data-clipboard-type="repository" title="Add to clipboard">
      <i class="fas fa-paperclip"></i>
    </button>
  </div>

  {{-- Identity area (ISDIAH 5.1) --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Identity area</h2>

    @if($repository->identifier)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Identifier</div>
        <div class="col-md-9">{{ $repository->identifier }}</div>
      </div>
    @endif

    @if($repository->authorized_form_of_name)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Authorized form of name</div>
        <div class="col-md-9">{{ $repository->authorized_form_of_name }}</div>
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
        <div class="col-md-3 fw-bold">Type</div>
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
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Description area</h2>

    @foreach([
      'history' => 'History',
      'geocultural_context' => 'Geographical and cultural context',
      'mandates' => 'Mandates/Sources of authority',
      'internal_structures' => 'Administrative structure',
      'collecting_policies' => 'Records management and collecting policies',
      'buildings' => 'Buildings',
      'holdings' => 'Holdings',
      'finding_aids' => 'Finding aids, guides and publications',
    ] as $field => $label)
      @if($repository->$field)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">{{ $label }}</div>
          <div class="col-md-9">{!! nl2br(e($repository->$field)) !!}</div>
        </div>
      @endif
    @endforeach
  </section>

  {{-- Access area (ISDIAH 5.4) --}}
  @if($repository->opening_times || $repository->access_conditions || $repository->disabled_access)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Access area</h2>

      @foreach([
        'opening_times' => 'Opening times',
        'access_conditions' => 'Access conditions and requirements',
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

    @if($repository->desc_identifier ?? null)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Description identifier</div>
        <div class="col-md-9">{{ $repository->desc_identifier }}</div>
      </div>
    @endif

    @foreach([
      'desc_institution_identifier' => 'Institution identifier',
      'desc_rules' => 'Rules and/or conventions used',
      'desc_sources' => 'Sources',
      'desc_revision_history' => 'Dates of creation, revision and deletion',
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

  {{-- Action buttons (bottom bar, matching AtoM) --}}
  @auth
  <ul class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
    <li><a class="btn atom-btn-outline-light" href="{{ route('repository.edit', $repository->slug) }}">Edit</a></li>
    <li><a class="btn atom-btn-outline-danger" href="{{ route('repository.confirmDelete', $repository->slug) }}">Delete</a></li>
    <li><a class="btn atom-btn-outline-light" href="{{ route('repository.create') }}">Add new</a></li>
    <li><a class="btn atom-btn-outline-light" href="{{ route('informationobject.create', ['repository' => $repository->id]) }}">Add description</a></li>
    <li><a class="btn atom-btn-outline-light" href="{{ route('repository.edit', $repository->slug) }}?theme=1">Edit theme</a></li>
  </ul>
  @endauth
@endsection
