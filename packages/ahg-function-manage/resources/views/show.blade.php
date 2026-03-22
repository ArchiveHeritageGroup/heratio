@extends('theme::layouts.1col')

@section('title', $function->authorized_form_of_name ?? 'Function')
@section('body-class', 'view function')

@section('title-block')
  <h1 id="resource-name">{{ $function->authorized_form_of_name ?: '[Untitled]' }}</h1>

  {{-- Breadcrumb --}}
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('function.browse') }}">Functions</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $function->authorized_form_of_name ?: '[Untitled]' }}</li>
    </ol>
  </nav>
@endsection

@section('content')

  {{-- ===== Identity area (ISDF 5.1) ===== --}}
  <section class="section border-bottom" id="identityArea">
    <h2 class="h6 mb-0 py-2 px-3 rounded-top" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#identity-collapse">Identity area</a>
      @auth
        <a href="{{ route('function.edit', $function->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit"><i class="fas fa-pencil-alt"></i></a>
      @endauth
    </h2>
    <div id="identity-collapse">

      @if($typeName)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Type</h3>
          <div class="col-9 p-2">{{ $typeName }}</div>
        </div>
      @endif

      @if($function->authorized_form_of_name)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Authorized form of name</h3>
          <div class="col-9 p-2">{{ $function->authorized_form_of_name }}</div>
        </div>
      @endif

      @if(isset($parallelNames) && count($parallelNames) > 0)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Parallel form(s) of name</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($parallelNames as $pn)
                <li>{{ $pn }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      @if(isset($otherNames) && count($otherNames) > 0)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Other form(s) of name</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($otherNames as $on)
                <li>{{ $on }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      @if($function->classification)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Classification</h3>
          <div class="col-9 p-2">{{ $function->classification }}</div>
        </div>
      @endif

    </div>
  </section>

  {{-- ===== Context area (ISDF 5.2) ===== --}}
  <section class="section border-bottom" id="contextArea">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#context-collapse">Context area</a>
      @auth
        <a href="{{ route('function.edit', $function->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit"><i class="fas fa-pencil-alt"></i></a>
      @endauth
    </h2>
    <div id="context-collapse">

      @if($function->dates)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Dates</h3>
          <div class="col-9 p-2">{{ $function->dates }}</div>
        </div>
      @endif

      @if($function->description)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Description</h3>
          <div class="col-9 p-2">{!! nl2br(e($function->description)) !!}</div>
        </div>
      @endif

      @if($function->history)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">History</h3>
          <div class="col-9 p-2">{!! nl2br(e($function->history)) !!}</div>
        </div>
      @endif

      @if($function->legislation)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Legislation</h3>
          <div class="col-9 p-2">{!! nl2br(e($function->legislation)) !!}</div>
        </div>
      @endif

    </div>
  </section>

  {{-- ===== Relationships area (ISDF 5.3) ===== --}}
  <section class="section border-bottom" id="relationshipsArea">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#relationships-collapse">Relationships area</a>
      @auth
        <a href="{{ route('function.edit', $function->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit"><i class="fas fa-pencil-alt"></i></a>
      @endauth
    </h2>
    <div id="relationships-collapse">

      {{-- Related functions --}}
      @if(isset($relatedFunctions) && $relatedFunctions->isNotEmpty())
        @foreach($relatedFunctions as $related)
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Related function</h3>
            <div class="col-9 p-2">
              <div class="ms-2">
                <div class="field row g-0">
                  <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 small">Authorized form of name</h3>
                  <div class="col-8 p-1"><a href="{{ route('function.show', $related->slug) }}">{{ $related->authorized_form_of_name ?: '[Untitled]' }}</a></div>
                </div>
                @if($related->description_identifier ?? null)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 small">Identifier</h3>
                    <div class="col-8 p-1">{{ $related->description_identifier }}</div>
                  </div>
                @endif
                @if($related->type_name ?? null)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 small">Type</h3>
                    <div class="col-8 p-1">{{ $related->type_name }}</div>
                  </div>
                @endif
                @if($related->relation_type ?? null)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 small">Category of relationship</h3>
                    <div class="col-8 p-1">{{ $related->relation_type }}</div>
                  </div>
                @endif
                @if($related->relation_description ?? null)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 small">Description of relationship</h3>
                    <div class="col-8 p-1">{!! nl2br(e($related->relation_description)) !!}</div>
                  </div>
                @endif
                @if($related->relation_dates ?? null)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 small">Dates of relationship</h3>
                    <div class="col-8 p-1">{{ $related->relation_dates }}</div>
                  </div>
                @endif
              </div>
            </div>
          </div>
        @endforeach
      @endif

      {{-- Related authority records --}}
      @if(isset($relatedActors) && count($relatedActors) > 0)
        @foreach($relatedActors as $actor)
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Related authority record</h3>
            <div class="col-9 p-2">
              <div class="ms-2">
                <div class="field row g-0">
                  <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 small">Authorized form of name</h3>
                  <div class="col-8 p-1"><a href="{{ route('actor.show', $actor->slug) }}">{{ $actor->authorized_form_of_name ?: '[Untitled]' }}</a></div>
                </div>
                @if($actor->description_identifier ?? null)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 small">Identifier</h3>
                    <div class="col-8 p-1">{{ $actor->description_identifier }}</div>
                  </div>
                @endif
                @if($actor->relation_description ?? null)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 small">Nature of relationship</h3>
                    <div class="col-8 p-1">{!! nl2br(e($actor->relation_description)) !!}</div>
                  </div>
                @endif
                @if($actor->relation_dates ?? null)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 small">Dates of the relationship</h3>
                    <div class="col-8 p-1">{{ $actor->relation_dates }}</div>
                  </div>
                @endif
              </div>
            </div>
          </div>
        @endforeach
      @endif

      {{-- Related resources --}}
      @if(isset($relatedResources) && $relatedResources->isNotEmpty())
        @foreach($relatedResources as $resource)
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Related resource</h3>
            <div class="col-9 p-2">
              <div class="ms-2">
                <div class="field row g-0">
                  <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 small">Title</h3>
                  <div class="col-8 p-1"><a href="{{ route('informationobject.show', $resource->slug) }}">{{ $resource->title ?: '[Untitled]' }}</a></div>
                </div>
                @if($resource->identifier ?? null)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 small">Identifier</h3>
                    <div class="col-8 p-1">{{ $resource->identifier }}</div>
                  </div>
                @endif
                @if($resource->relation_description ?? null)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 small">Nature of relationship</h3>
                    <div class="col-8 p-1">{!! nl2br(e($resource->relation_description)) !!}</div>
                  </div>
                @endif
                @if($resource->relation_dates ?? null)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 small">Dates of the relationship</h3>
                    <div class="col-8 p-1">{{ $resource->relation_dates }}</div>
                  </div>
                @endif
              </div>
            </div>
          </div>
        @endforeach
      @endif

    </div>
  </section>

  {{-- ===== Control area (ISDF 5.4) ===== --}}
  <section class="section border-bottom" id="controlArea">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#control-collapse">Control area</a>
      @auth
        <a href="{{ route('function.edit', $function->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit"><i class="fas fa-pencil-alt"></i></a>
      @endauth
    </h2>
    <div id="control-collapse">

      @if($function->description_identifier)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Description identifier</h3>
          <div class="col-9 p-2">{{ $function->description_identifier }}</div>
        </div>
      @endif

      @if($function->institution_identifier)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Institution identifier</h3>
          <div class="col-9 p-2">{{ $function->institution_identifier }}</div>
        </div>
      @endif

      @if($function->rules)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rules and/or conventions used</h3>
          <div class="col-9 p-2">{!! nl2br(e($function->rules)) !!}</div>
        </div>
      @endif

      @if(isset($descriptionStatus) && $descriptionStatus)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Status</h3>
          <div class="col-9 p-2">{{ $descriptionStatus }}</div>
        </div>
      @endif

      @if(isset($descriptionDetail) && $descriptionDetail)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Level of detail</h3>
          <div class="col-9 p-2">{{ $descriptionDetail }}</div>
        </div>
      @endif

      @if($function->revision_history)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Dates of creation, revision or deletion</h3>
          <div class="col-9 p-2">{!! nl2br(e($function->revision_history)) !!}</div>
        </div>
      @endif

      @if(isset($languages) && count($languages) > 0)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Language(s)</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($languages as $lang)
                <li>{{ $lang }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      @if(isset($scripts) && count($scripts) > 0)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Script(s)</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($scripts as $script)
                <li>{{ $script }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      @if($function->sources)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Sources</h3>
          <div class="col-9 p-2">{!! nl2br(e($function->sources)) !!}</div>
        </div>
      @endif

      @if(isset($maintenanceNote) && $maintenanceNote)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Maintenance notes</h3>
          <div class="col-9 p-2">{!! nl2br(e($maintenanceNote)) !!}</div>
        </div>
      @endif

    </div>
  </section>

@endsection

@section('after-content')
  @auth
    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('function.edit', $function->slug) }}" class="btn atom-btn-outline-light">Edit</a></li>
      <li><a href="{{ route('function.confirmDelete', $function->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
      <li><a href="{{ route('function.create') }}" class="btn atom-btn-outline-light">Add new</a></li>
    </ul>
  @endauth
@endsection
