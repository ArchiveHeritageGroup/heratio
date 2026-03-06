@extends('theme::layouts.2col')

@section('title', $actor->authorized_form_of_name ?? 'Authority record')
@section('body-class', 'view actor')

@section('sidebar')
  @include('ahg-core::components.digital-object', ['digitalObjects' => $digitalObjects])

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
@endsection

@section('content')
  <h1>{{ $actor->authorized_form_of_name }}</h1>

  @if($entityTypeName)
    <span class="badge bg-secondary mb-3">{{ $entityTypeName }}</span>
  @endif

  {{-- Identity area --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Identity area</h2>

    @if($actor->authorized_form_of_name)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Authorized form of name</div>
        <div class="col-md-9">{{ $actor->authorized_form_of_name }}</div>
      </div>
    @endif

    @if($actor->description_identifier)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Identifier</div>
        <div class="col-md-9">{{ $actor->description_identifier }}</div>
      </div>
    @endif

    @if($otherNames->isNotEmpty())
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Other name(s)</div>
        <div class="col-md-9">
          @foreach($otherNames as $name)
            <div>{{ $name->name }}</div>
          @endforeach
        </div>
      </div>
    @endif
  </section>

  {{-- Description area --}}
  @if($actor->history || $actor->places || $actor->legal_status || $actor->functions || $actor->mandates || $actor->internal_structures || $actor->general_context)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Description area</h2>

      @foreach([
        'history' => 'History',
        'places' => 'Places',
        'legal_status' => 'Legal status',
        'functions' => 'Functions',
        'mandates' => 'Mandates/Sources of authority',
        'internal_structures' => 'Internal structures',
        'general_context' => 'General context',
      ] as $field => $label)
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
            @if($contact->contact_person) <div><strong>Contact:</strong> {{ $contact->contact_person }}</div> @endif
            @if($contact->street_address) <div>{{ $contact->street_address }}</div> @endif
            @if($contact->city) <div>{{ $contact->city }}{{ $contact->region ? ', ' . $contact->region : '' }} {{ $contact->postal_code ?? '' }}</div> @endif
            @if($contact->country_code) <div>{{ $contact->country_code }}</div> @endif
            @if($contact->telephone) <div><strong>Tel:</strong> {{ $contact->telephone }}</div> @endif
            @if($contact->email) <div><strong>Email:</strong> {{ $contact->email }}</div> @endif
            @if($contact->website) <div><strong>Web:</strong> <a href="{{ $contact->website }}">{{ $contact->website }}</a></div> @endif
          </div>
        </div>
      @endforeach
    </section>
  @endif

  {{-- Control area --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Control area</h2>

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

    @if($actor->sources)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Sources</div>
        <div class="col-md-9">{!! nl2br(e($actor->sources)) !!}</div>
      </div>
    @endif
  </section>
@endsection
