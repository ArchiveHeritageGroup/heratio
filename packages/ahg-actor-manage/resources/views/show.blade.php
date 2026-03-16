@extends('theme::layouts.2col')

@section('title', $actor->authorized_form_of_name ?? 'Authority record')
@section('body-class', 'view actor')

@section('sidebar')
  @include('ahg-core::components.digital-object', ['digitalObjects' => $digitalObjects])

  @if(count($relatedActors) > 0)
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">Related authority records</h5>
      </div>
      <ul class="list-group list-group-flush">
        @foreach($relatedActors as $related)
          <li class="list-group-item">
            <a href="{{ route('actor.show', $related->slug) }}">{{ $related->name ?: '[Untitled]' }}</a>
            @if(!empty($related->type_id) && isset($relationTypeNames[$related->type_id]))
              <br><small class="text-muted">{{ $relationTypeNames[$related->type_id] }}</small>
            @endif
            @if(!empty($related->relation_description))
              <br><small class="text-muted">{{ $related->relation_description }}</small>
            @endif
          </li>
        @endforeach
      </ul>
    </div>
  @endif

  @if($relatedResources->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">Related descriptions</h5>
      </div>
      <ul class="list-group list-group-flush">
        @foreach($relatedResources->take(10) as $resource)
          <li class="list-group-item">
            <a href="{{ route('informationobject.show', $resource->slug) }}">
              {{ $resource->title ?: '[Untitled]' }}
            </a>
          </li>
        @endforeach
        @if($relatedResources->count() > 10)
          <li class="list-group-item text-muted">
            ... and {{ $relatedResources->count() - 10 }} more
          </li>
        @endif
      </ul>
    </div>
  @endif

  @if($relatedFunctions->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">Related functions</h5>
      </div>
      <ul class="list-group list-group-flush">
        @foreach($relatedFunctions as $fn)
          <li class="list-group-item">
            <a href="{{ route('function.show', $fn->slug) }}">{{ $fn->name ?: '[Untitled]' }}</a>
          </li>
        @endforeach
      </ul>
    </div>
  @endif
@endsection

@section('content')

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <h1>{{ $actor->authorized_form_of_name }}</h1>

  @if($entityTypeName)
    <span class="badge bg-secondary mb-3">{{ $entityTypeName }}</span>
  @endif

  {{-- Action buttons for authenticated users --}}
  @auth
    <div class="mb-3">
      <a href="{{ route('actor.edit', $actor->slug) }}" class="btn btn-sm btn-outline-primary">Edit</a>
      <a href="{{ route('actor.confirmDelete', $actor->slug) }}" class="btn btn-sm btn-outline-danger">Delete</a>
      <a href="{{ route('actor.create') }}" class="btn btn-sm btn-outline-success">Add new</a>
    </div>
  @endauth

  {{-- Identity area --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Identity area</h2>

    @if($entityTypeName)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Type of entity</div>
        <div class="col-md-9">{{ $entityTypeName }}</div>
      </div>
    @endif

    @if($actor->authorized_form_of_name)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Authorized form of name</div>
        <div class="col-md-9">{{ $actor->authorized_form_of_name }}</div>
      </div>
    @endif

    @if($otherNames->isNotEmpty())
      @foreach([148 => 'Parallel form(s) of name', 165 => 'Standardized form(s) of name', 149 => 'Other form(s) of name'] as $typeId => $label)
        @php $filtered = $otherNames->where('type_id', $typeId); @endphp
        @if($filtered->isNotEmpty())
          <div class="row mb-2">
            <div class="col-md-3 fw-bold">{{ $label }}</div>
            <div class="col-md-9">
              @foreach($filtered as $name)
                <div>{{ $name->name }}</div>
              @endforeach
            </div>
          </div>
        @endif
      @endforeach

      {{-- Names without a known type --}}
      @php $untyped = $otherNames->whereNotIn('type_id', [148, 149, 165]); @endphp
      @if($untyped->isNotEmpty())
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Other name(s)</div>
          <div class="col-md-9">
            @foreach($untyped as $name)
              <div>
                {{ $name->name }}
                @if(!empty($name->type_id) && isset($nameTypeNames[$name->type_id]))
                  <small class="text-muted">({{ $nameTypeNames[$name->type_id] }})</small>
                @endif
              </div>
            @endforeach
          </div>
        </div>
      @endif
    @endif

    @if($actor->corporate_body_identifiers)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Identifiers for corporate bodies</div>
        <div class="col-md-9">{{ $actor->corporate_body_identifiers }}</div>
      </div>
    @endif

    @if($actor->description_identifier)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Identifier</div>
        <div class="col-md-9">{{ $actor->description_identifier }}</div>
      </div>
    @endif
  </section>

  {{-- Description area --}}
  @php
    $descFields = [
      'dates_of_existence' => 'Dates of existence',
      'history' => 'History',
      'places' => 'Places',
      'legal_status' => 'Legal status',
      'functions' => 'Functions, occupations and activities',
      'mandates' => 'Mandates/Sources of authority',
      'internal_structures' => 'Internal structures/genealogy',
      'general_context' => 'General context',
    ];
    $hasDesc = false;
    foreach ($descFields as $f => $l) { if ($actor->$f) { $hasDesc = true; break; } }
  @endphp
  @if($hasDesc)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Description area</h2>
      @foreach($descFields as $field => $label)
        @if($actor->$field)
          <div class="row mb-2">
            <div class="col-md-3 fw-bold">{{ $label }}</div>
            <div class="col-md-9">{!! nl2br(e($actor->$field)) !!}</div>
          </div>
        @endif
      @endforeach
    </section>
  @endif

  {{-- Events (dates) --}}
  @if($events->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Dates</h2>
      @foreach($events as $event)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">{{ $event->event_name ?? 'Date' }}</div>
          <div class="col-md-9">
            {{ $event->date_display ?? '' }}
            @if($event->start_date || $event->end_date)
              <span class="text-muted small">
                ({{ $event->start_date ?? '?' }} - {{ $event->end_date ?? '?' }})
              </span>
            @endif
          </div>
        </div>
      @endforeach
    </section>
  @endif

  {{-- Contact information --}}
  @if($contacts->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Contact information</h2>
      @foreach($contacts as $contact)
        <div class="card mb-2">
          <div class="card-body">
            @if($contact->primary_contact)
              <span class="badge bg-success mb-2">Primary contact</span>
            @endif
            @if($contact->contact_person) <div><strong>Contact:</strong> {{ $contact->contact_person }}</div> @endif
            @if($contact->contact_type) <div><strong>Type:</strong> {{ $contact->contact_type }}</div> @endif
            @if($contact->street_address) <div>{{ $contact->street_address }}</div> @endif
            @if($contact->city || $contact->region || $contact->postal_code)
              <div>
                {{ $contact->city ?? '' }}{{ $contact->region ? ', ' . $contact->region : '' }} {{ $contact->postal_code ?? '' }}
              </div>
            @endif
            @if($contact->country_code) <div>{{ $contact->country_code }}</div> @endif
            @if($contact->telephone) <div><strong>Tel:</strong> {{ $contact->telephone }}</div> @endif
            @if($contact->fax) <div><strong>Fax:</strong> {{ $contact->fax }}</div> @endif
            @if($contact->email) <div><strong>Email:</strong> {{ $contact->email }}</div> @endif
            @if($contact->website) <div><strong>Web:</strong> <a href="{{ $contact->website }}" target="_blank">{{ $contact->website }}</a></div> @endif
            @if($contact->note) <div class="mt-1"><em>{{ $contact->note }}</em></div> @endif
          </div>
        </div>
      @endforeach
    </section>
  @endif

  {{-- Access points --}}
  @if($subjects->isNotEmpty() || $places->isNotEmpty() || $occupations->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Access points</h2>

      @if($subjects->isNotEmpty())
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Subject access points</div>
          <div class="col-md-9">
            @foreach($subjects as $subject)
              <span class="badge bg-light text-dark me-1">{{ $subject->name }}</span>
            @endforeach
          </div>
        </div>
      @endif

      @if($places->isNotEmpty())
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Place access points</div>
          <div class="col-md-9">
            @foreach($places as $place)
              <span class="badge bg-light text-dark me-1">{{ $place->name }}</span>
            @endforeach
          </div>
        </div>
      @endif

      @if($occupations->isNotEmpty())
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Occupations</div>
          <div class="col-md-9">
            @foreach($occupations as $occ)
              <span class="badge bg-light text-dark me-1">{{ $occ->name }}</span>
            @endforeach
          </div>
        </div>
      @endif
    </section>
  @endif

  {{-- Control area --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Control area</h2>

    @if($actor->description_identifier)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Authority record identifier</div>
        <div class="col-md-9">{{ $actor->description_identifier }}</div>
      </div>
    @endif

    @if($actor->institution_responsible_identifier)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Institution identifier</div>
        <div class="col-md-9">{{ $actor->institution_responsible_identifier }}</div>
      </div>
    @endif

    @if($actor->rules)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Rules and/or conventions</div>
        <div class="col-md-9">{!! nl2br(e($actor->rules)) !!}</div>
      </div>
    @endif

    @if($descriptionStatusName)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Status</div>
        <div class="col-md-9">{{ $descriptionStatusName }}</div>
      </div>
    @endif

    @if($descriptionDetailName)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Level of detail</div>
        <div class="col-md-9">{{ $descriptionDetailName }}</div>
      </div>
    @endif

    @if($actor->revision_history)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Dates of creation, revision and deletion</div>
        <div class="col-md-9">{!! nl2br(e($actor->revision_history)) !!}</div>
      </div>
    @endif

    @if($actor->sources)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Sources</div>
        <div class="col-md-9">{!! nl2br(e($actor->sources)) !!}</div>
      </div>
    @endif

    @if($maintenanceNotes)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Maintenance notes</div>
        <div class="col-md-9">{!! nl2br(e($maintenanceNotes)) !!}</div>
      </div>
    @endif

    @if($actor->updated_at)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Last updated</div>
        <div class="col-md-9">{{ $actor->updated_at }}</div>
      </div>
    @endif
  </section>
@endsection
