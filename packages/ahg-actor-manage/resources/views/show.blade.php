@extends('theme::layouts.3col')

@section('title', $actor->authorized_form_of_name ?? 'Authority record')
@section('body-class', 'view actor')

@section('sidebar')
  @if(count($relatedActors) > 0)
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">Related authority records</h5>
      </div>
      <ul class="list-group list-group-flush">
        @foreach($relatedActors as $related)
          <li class="list-group-item">
            <a href="{{ route('actor.show', $related->slug) }}">{{ $related->name ?: '[Untitled]' }}</a>
            @if(!empty($related->identifier))
              <br><small class="text-muted">Identifier: {{ $related->identifier }}</small>
            @endif
            @if(!empty($related->type_id) && isset($relationCategoryNames[$related->type_id]))
              <br><small class="text-muted">Category: {{ $relationCategoryNames[$related->type_id] }}</small>
            @endif
            @if(!empty($related->type_id) && isset($relationTypeNames[$related->type_id]))
              <br><small class="text-muted">Type: {{ $relationTypeNames[$related->type_id] }}</small>
            @endif
            @if(!empty($related->relation_description))
              <br><small class="text-muted">{{ $related->relation_description }}</small>
            @endif
            @if(!empty($related->relation_date))
              <br><small class="text-muted">Dates: {{ $related->relation_date }}</small>
            @elseif(!empty($related->start_date) || !empty($related->end_date))
              <br><small class="text-muted">Dates: {{ $related->start_date ?? '?' }} - {{ $related->end_date ?? '?' }}</small>
            @endif
          </li>
        @endforeach
      </ul>
    </div>
  @endif

  @if($relatedResources->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">Creator of</h5>
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
            <a href="{{ route('glam.browse') }}?topLevel=0&creator={{ $actor->id }}" class="text-muted">
              ... and {{ $relatedResources->count() - 10 }} more
            </a>
          </li>
        @endif
      </ul>
      <div class="card-body p-0">
        <a class="btn atom-btn-white border-0 w-100" href="{{ route('glam.browse') }}?topLevel=0&creator={{ $actor->id }}">
          <i class="fas fa-search me-1" aria-hidden="true"></i>
          Browse {{ $relatedResources->count() }} result{{ $relatedResources->count() !== 1 ? 's' : '' }}
        </a>
      </div>
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

@section('right')
  @include('ahg-core::components.digital-object', ['digitalObjects' => $digitalObjects])

  <nav>
    {{-- Clipboard --}}
    <h4 class="h5 mb-2">Clipboard</h4>
    <ul class="list-unstyled mb-3">
      <li>
        @include('ahg-core::clipboard._button', ['slug' => $actor->slug, 'type' => 'actor', 'wide' => true])
      </li>
    </ul>

    {{-- Export (matching AtoM) --}}
    <h4 class="h5 mb-2">Export</h4>
    <ul class="list-unstyled mb-3">
      <li>
        <a class="atom-icon-link" href="{{ route('export.authority') }}?slug={{ $actor->slug }}">
          <i class="fas fa-fw fa-upload me-1" aria-hidden="true"></i>EAC
        </a>
      </li>
    </ul>

    {{-- Subject access points in sidebar (matching AtoM) --}}
    @if(isset($subjects) && count($subjects) > 0)
      <h4 class="h5 mb-2">Subject access points</h4>
      <ul class="list-unstyled mb-3">
        @foreach($subjects as $subject)
          <li><span class="badge bg-light text-dark me-1">{{ $subject->name }}</span></li>
        @endforeach
      </ul>
    @endif

    {{-- Place access points in sidebar (matching AtoM) --}}
    @if(isset($places) && count($places) > 0)
      <h4 class="h5 mb-2">Place access points</h4>
      <ul class="list-unstyled mb-3">
        @foreach($places as $place)
          <li><span class="badge bg-light text-dark me-1">{{ $place->name }}</span></li>
        @endforeach
      </ul>
    @endif
  </nav>

@endsection

@section('content')

  <h1>{{ $actor->authorized_form_of_name }}</h1>

  {{-- Breadcrumb (matching AtoM) --}}
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('actor.browse') }}">Authority record</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $actor->authorized_form_of_name }}</li>
    </ol>
  </nav>

  {{-- Identity area --}}
  <section id="identityArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Identity area</div></h2>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Type of entity</h3><div class="col-9 p-2">{{ $entityTypeName ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Authorized form of name</h3><div class="col-9 p-2">{{ $actor->authorized_form_of_name ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Parallel form(s) of name</h3><div class="col-9 p-2"><ul class="m-0 ms-1 ps-3">@foreach(($otherNames ?? collect())->where('type_id', 148) as $n)<li>{{ $n->name }}</li>@endforeach</ul></div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Standardized form(s) of name according to other rules</h3><div class="col-9 p-2"><ul class="m-0 ms-1 ps-3">@foreach(($otherNames ?? collect())->where('type_id', 165) as $n)<li>{{ $n->name }}</li>@endforeach</ul></div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Other form(s) of name</h3><div class="col-9 p-2"><ul class="m-0 ms-1 ps-3">@foreach(($otherNames ?? collect())->where('type_id', 149) as $n)<li>{{ $n->name }}</li>@endforeach</ul></div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Identifiers for corporate bodies</h3><div class="col-9 p-2">{{ $actor->corporate_body_identifiers ?? '' }}</div></div>
  </section>

  {{-- Description area --}}
  <section id="descriptionArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Description area</div></h2>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Dates of existence</h3><div class="col-9 p-2">{{ $actor->dates_of_existence ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">History</h3><div class="col-9 p-2">{!! ($actor->history ?? '') ? nl2br(e($actor->history)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Places</h3><div class="col-9 p-2">{{ $actor->places ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Legal status</h3><div class="col-9 p-2">{{ $actor->legal_status ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Functions, occupations and activities</h3><div class="col-9 p-2">{{ $actor->functions ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Mandates/sources of authority</h3><div class="col-9 p-2">{{ $actor->mandates ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Internal structures/genealogy</h3><div class="col-9 p-2">{{ $actor->internal_structures ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">General context</h3><div class="col-9 p-2">{{ $actor->general_context ?? '' }}</div></div>
  </section>

  {{-- Relationships area --}}
  <section id="relationshipsArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Relationships area</div></h2>
    @foreach($relatedActors ?? [] as $related)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Related entity</h3>
        <div class="col-9 p-2">
          <a href="{{ route('actor.show', $related->slug) }}">{{ $related->name ?: '[Untitled]' }}</a>
          @if(!empty($related->type_id) && isset($relationTypeNames[$related->type_id]))
            <br><small class="text-muted">{{ $relationTypeNames[$related->type_id] }}</small>
          @endif
          @if(!empty($related->relation_description))
            <br><small class="text-muted">{{ $related->relation_description }}</small>
          @endif
          @if(!empty($related->relation_date))
            <br><small class="text-muted">Dates: {{ $related->relation_date }}</small>
          @elseif(!empty($related->start_date) || !empty($related->end_date))
            <br><small class="text-muted">Dates: {{ $related->start_date ?? '?' }} - {{ $related->end_date ?? '?' }}</small>
          @endif
        </div>
      </div>
    @endforeach

    {{-- Related functions (matching AtoM) --}}
    @foreach($relatedFunctions ?? [] as $fn)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Related function</h3>
        <div class="col-9 p-2">
          <a href="{{ route('function.show', $fn->slug) }}">{{ $fn->name ?: '[Untitled]' }}</a>
        </div>
      </div>
    @endforeach
  </section>

  {{-- Contact information --}}
  <section id="contactArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Contact information</div></h2>
    @foreach($contacts ?? [] as $contact)
      @if(!$loop->first)
        <hr class="my-3">
      @endif
      <div class="p-2">
        @if($contact->primary_contact) <span class="badge bg-success mb-1">Primary</span> @endif
        @if($contact->contact_person) <div>{{ $contact->contact_person }}</div> @endif
        @if($contact->street_address) <div>{{ $contact->street_address }}</div> @endif
        @if($contact->city || $contact->region || $contact->postal_code)
          <div>{{ $contact->city ?? '' }}{{ $contact->region ? ', ' . $contact->region : '' }} {{ $contact->postal_code ?? '' }}</div>
        @endif
        @if($contact->country_code) <div>{{ $contact->country_code }}</div> @endif
        @if($contact->telephone) <div><a href="tel:{{ $contact->telephone }}">{{ $contact->telephone }}</a></div> @endif
        @if($contact->fax) <div>Fax: {{ $contact->fax }}</div> @endif
        @if($contact->email) <div><a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a></div> @endif
        @if($contact->website) <div><a href="{{ $contact->website }}" target="_blank">{{ $contact->website }} <i class="fas fa-external-link-alt"></i></a></div> @endif
        @if($contact->note) <div class="text-muted mt-1">{{ $contact->note }}</div> @endif
      </div>
    @endforeach
  </section>

  {{-- Access points area --}}
  <section id="accessPointsArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Access points area</div></h2>
    <div class="field row g-0">
      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Subject access points</h3>
      <div class="col-9 p-2">
        @foreach($subjects ?? [] as $subject)
          <span class="badge bg-light text-dark me-1">{{ $subject->name }}</span>
        @endforeach
      </div>
    </div>
    <div class="field row g-0">
      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Place access points</h3>
      <div class="col-9 p-2">
        @foreach($places ?? [] as $place)
          <span class="badge bg-light text-dark me-1">{{ $place->name }}</span>
        @endforeach
      </div>
    </div>
    <div class="field row g-0">
      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Occupations</h3>
      <div class="col-9 p-2">
        @foreach($occupations ?? [] as $occ)
          <span class="badge bg-light text-dark me-1">{{ $occ->name }}</span>
        @endforeach
      </div>
    </div>
  </section>

  {{-- Control area --}}
  <section id="controlArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Control area</div></h2>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Authority record identifier</h3><div class="col-9 p-2">{{ $actor->description_identifier ?? '' }}</div></div>
    @if($maintainingRepository ?? null)
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Maintained by</h3><div class="col-9 p-2"><a href="{{ route('repository.show', $maintainingRepository->slug) }}">{{ $maintainingRepository->name ?: '[Untitled]' }}</a></div></div>
    @endif
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Institution identifier</h3><div class="col-9 p-2">{{ $actor->institution_responsible_identifier ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rules and/or conventions used</h3><div class="col-9 p-2">{!! ($actor->rules ?? '') ? nl2br(e($actor->rules)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Status</h3><div class="col-9 p-2">{{ $descriptionStatusName ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Level of detail</h3><div class="col-9 p-2">{{ $descriptionDetailName ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Dates of creation, revision and deletion</h3><div class="col-9 p-2">{!! ($actor->revision_history ?? '') ? nl2br(e($actor->revision_history)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Language(s)</h3><div class="col-9 p-2"><ul class="m-0 ms-1 ps-3">@foreach($languages ?? [] as $lang)<li>{{ $lang }}</li>@endforeach</ul></div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Script(s)</h3><div class="col-9 p-2"><ul class="m-0 ms-1 ps-3">@foreach($scripts ?? [] as $scr)<li>{{ $scr }}</li>@endforeach</ul></div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Sources</h3><div class="col-9 p-2">{!! ($actor->sources ?? '') ? nl2br(e($actor->sources)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Maintenance notes</h3><div class="col-9 p-2">{!! ($maintenanceNotes ?? '') ? nl2br(e($maintenanceNotes)) : '' !!}</div></div>
  </section>

  {{-- Action buttons (bottom bar, matching AtoM _actions.php) --}}
  @auth
  <ul class="actions mb-3 nav gap-2">
    <li><a class="btn atom-btn-outline-light" href="{{ route('actor.edit', $actor->slug) }}">Edit</a></li>
    <li><a class="btn atom-btn-outline-danger" href="{{ route('actor.confirmDelete', $actor->slug) }}">Delete</a></li>
    <li><a class="btn atom-btn-outline-light" href="{{ route('actor.add') }}">Add new</a></li>
    <li><a class="btn atom-btn-outline-light" href="{{ route('actor.rename', $actor->slug) }}">Rename</a></li>
    <li>
      <div class="dropup">
        <button type="button" class="btn atom-btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          More
        </button>
        <ul class="dropdown-menu mb-2">
          @if(isset($digitalObject) && $digitalObject)
            <li><a href="{{ route('actor.edit', $actor->slug) }}" class="dropdown-item">Edit digital object</a></li>
          @else
            <li><a href="{{ route('actor.edit', $actor->slug) }}" class="dropdown-item">Link digital object</a></li>
          @endif
        </ul>
      </div>
    </li>
  </ul>
  @endauth
@endsection
