@extends('theme::layouts.1col')

@section('title', $function->authorized_form_of_name ?? 'Function')
@section('body-class', 'view function')

@section('content')

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <h1>{{ $function->authorized_form_of_name }}</h1>

  @auth
    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('function.edit', $function->slug) }}" class="btn btn-sm btn-outline-primary">Edit</a></li>
      @can('admin')
        <li><a href="{{ route('function.confirmDelete', $function->slug) }}" class="btn btn-sm btn-outline-danger">Delete</a></li>
      @endcan
      <li><a href="{{ route('function.create') }}" class="btn btn-sm btn-outline-success">Add new</a></li>
    </ul>
  @endauth

  @if($typeName)
    <span class="badge bg-secondary mb-3">{{ $typeName }}</span>
  @endif

  {{-- Identity area (ISDF 5.1) --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Identity area</h2>

    @if($function->authorized_form_of_name)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Authorized form of name</div>
        <div class="col-md-9">{{ $function->authorized_form_of_name }}</div>
      </div>
    @endif

    @if($function->description_identifier)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Identifier</div>
        <div class="col-md-9">{{ $function->description_identifier }}</div>
      </div>
    @endif

    @if($function->classification)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Classification</div>
        <div class="col-md-9">{{ $function->classification }}</div>
      </div>
    @endif

    @if($function->dates)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Dates</div>
        <div class="col-md-9">{{ $function->dates }}</div>
      </div>
    @endif
  </section>

  {{-- Description area (ISDF 5.2) --}}
  @if($function->description || $function->history || $function->legislation)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Description area</h2>

      @if($function->description)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Description</div>
          <div class="col-md-9">{!! nl2br(e($function->description)) !!}</div>
        </div>
      @endif

      @if($function->history)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">History</div>
          <div class="col-md-9">{!! nl2br(e($function->history)) !!}</div>
        </div>
      @endif

      @if($function->legislation)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Legislation</div>
          <div class="col-md-9">{!! nl2br(e($function->legislation)) !!}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- Relationships area (ISDF 5.3) --}}
  @if(($relatedFunctions->isNotEmpty()) || ($relatedResources->isNotEmpty()))
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Relationships</h2>

      @if($relatedFunctions->isNotEmpty())
        <div class="row mb-3">
          <div class="col-md-3 fw-bold">Related functions</div>
          <div class="col-md-9">
            <ul class="list-unstyled mb-0">
              @foreach($relatedFunctions as $related)
                <li>
                  <a href="{{ route('function.show', $related->slug) }}">
                    {{ $related->authorized_form_of_name ?: '[Untitled]' }}
                  </a>
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      @if($relatedResources->isNotEmpty())
        <div class="row mb-3">
          <div class="col-md-3 fw-bold">Related resources</div>
          <div class="col-md-9">
            <ul class="list-unstyled mb-0">
              @foreach($relatedResources as $resource)
                <li>
                  <a href="{{ route('informationobject.show', $resource->slug) }}">
                    {{ $resource->title ?: '[Untitled]' }}
                  </a>
                </li>
              @endforeach
              @if($relatedResources->count() >= 50)
                <li class="text-muted">... more results available</li>
              @endif
            </ul>
          </div>
        </div>
      @endif
    </section>
  @endif

  {{-- Control area (ISDF 5.4) --}}
  @if($function->institution_identifier || $function->rules || $function->sources || $function->revision_history || $descriptionStatus || $descriptionDetail || $function->source_standard)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Control area</h2>

      @if($function->institution_identifier)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Institution identifier</div>
          <div class="col-md-9">{!! nl2br(e($function->institution_identifier)) !!}</div>
        </div>
      @endif

      @if($descriptionStatus)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Description status</div>
          <div class="col-md-9">{{ $descriptionStatus }}</div>
        </div>
      @endif

      @if($descriptionDetail)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Level of detail</div>
          <div class="col-md-9">{{ $descriptionDetail }}</div>
        </div>
      @endif

      @if($function->rules)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Rules and/or conventions</div>
          <div class="col-md-9">{!! nl2br(e($function->rules)) !!}</div>
        </div>
      @endif

      @if($function->source_standard)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Source standard</div>
          <div class="col-md-9">{{ $function->source_standard }}</div>
        </div>
      @endif

      @if($function->sources)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Sources</div>
          <div class="col-md-9">{!! nl2br(e($function->sources)) !!}</div>
        </div>
      @endif

      @if($function->revision_history)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Revision history</div>
          <div class="col-md-9">{!! nl2br(e($function->revision_history)) !!}</div>
        </div>
      @endif
    </section>
  @endif
@endsection
