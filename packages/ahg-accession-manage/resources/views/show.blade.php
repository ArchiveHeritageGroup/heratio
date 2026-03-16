@extends('theme::layouts.1col')

@section('title', $accession->title ?? 'Accession record')
@section('body-class', 'view accession')

@section('content')

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="d-flex justify-content-between align-items-start mb-3">
    <h1 class="mb-0">{{ $accession->title ?: $accession->identifier ?: '[Untitled]' }}</h1>

    @auth
      <div class="d-flex gap-2">
        <a href="{{ route('accession.create') }}" class="btn btn-sm btn-outline-primary">Add new</a>
        <a href="{{ route('accession.edit', $accession->slug) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
        @if(auth()->user()->is_admin ?? false)
          <a href="{{ route('accession.confirmDelete', $accession->slug) }}" class="btn btn-sm btn-outline-danger">Delete</a>
        @endif
      </div>
    @endauth
  </div>

  {{-- Accession area --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Accession area</h2>

    @if($accession->title)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Title</div>
        <div class="col-md-9">{{ $accession->title }}</div>
      </div>
    @endif

    @if($accession->identifier)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Accession number</div>
        <div class="col-md-9">{{ $accession->identifier }}</div>
      </div>
    @endif

    @if($accession->date)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Acquisition date</div>
        <div class="col-md-9">{{ \Carbon\Carbon::parse($accession->date)->format('Y-m-d') }}</div>
      </div>
    @endif

    @if($accession->acquisition_type_id && isset($termNames[$accession->acquisition_type_id]))
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Acquisition type</div>
        <div class="col-md-9">{{ $termNames[$accession->acquisition_type_id] }}</div>
      </div>
    @endif

    @if($accession->resource_type_id && isset($termNames[$accession->resource_type_id]))
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Resource type</div>
        <div class="col-md-9">{{ $termNames[$accession->resource_type_id] }}</div>
      </div>
    @endif

    @if($accession->source_of_acquisition)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Source of acquisition</div>
        <div class="col-md-9">{!! nl2br(e($accession->source_of_acquisition)) !!}</div>
      </div>
    @endif

    @if($accession->location_information)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Location information</div>
        <div class="col-md-9">{!! nl2br(e($accession->location_information)) !!}</div>
      </div>
    @endif
  </section>

  {{-- Donor area --}}
  @if($donor)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Donor</h2>

      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Donor name</div>
        <div class="col-md-9">
          <a href="{{ route('actor.show', $donor->slug) }}">{{ $donor->name }}</a>
        </div>
      </div>
    </section>
  @endif

  {{-- Content and structure area --}}
  @if($accession->scope_and_content || $accession->appraisal || $accession->archival_history || $accession->received_extent_units || $accession->physical_characteristics)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Content and structure area</h2>

      @foreach([
        'scope_and_content' => 'Scope and content',
        'appraisal' => 'Appraisal, destruction and scheduling',
        'archival_history' => 'Archival history',
        'received_extent_units' => 'Received extent units',
        'physical_characteristics' => 'Physical characteristics',
      ] as $field => $label)
        @if($accession->$field)
          <div class="row mb-2">
            <div class="col-md-3 fw-bold">{{ $label }}</div>
            <div class="col-md-9">{!! nl2br(e($accession->$field)) !!}</div>
          </div>
        @endif
      @endforeach
    </section>
  @endif

  {{-- Administrative area --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Administration area</h2>

    @if($accession->processing_status_id && isset($termNames[$accession->processing_status_id]))
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Processing status</div>
        <div class="col-md-9">{{ $termNames[$accession->processing_status_id] }}</div>
      </div>
    @endif

    @if($accession->processing_priority_id && isset($termNames[$accession->processing_priority_id]))
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Processing priority</div>
        <div class="col-md-9">{{ $termNames[$accession->processing_priority_id] }}</div>
      </div>
    @endif

    @if($accession->processing_notes)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Processing notes</div>
        <div class="col-md-9">{!! nl2br(e($accession->processing_notes)) !!}</div>
      </div>
    @endif

    @if($accession->created_at)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Created</div>
        <div class="col-md-9">{{ \Carbon\Carbon::parse($accession->created_at)->format('Y-m-d H:i') }}</div>
      </div>
    @endif

    @if($accession->updated_at)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Updated</div>
        <div class="col-md-9">{{ \Carbon\Carbon::parse($accession->updated_at)->format('Y-m-d H:i') }}</div>
      </div>
    @endif
  </section>

  {{-- Deaccessions --}}
  @if($deaccessions->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Deaccessions</h2>

      @foreach($deaccessions as $deaccession)
        <div class="card mb-2">
          <div class="card-body">
            @if($deaccession->identifier)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Identifier</div>
                <div class="col-md-9">{{ $deaccession->identifier }}</div>
              </div>
            @endif

            @if($deaccession->date)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Date</div>
                <div class="col-md-9">{{ \Carbon\Carbon::parse($deaccession->date)->format('Y-m-d') }}</div>
              </div>
            @endif

            @if($deaccession->scope_id && isset($scopeNames[$deaccession->scope_id]))
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Scope</div>
                <div class="col-md-9">{{ $scopeNames[$deaccession->scope_id] }}</div>
              </div>
            @endif

            @if($deaccession->description)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Description</div>
                <div class="col-md-9">{!! nl2br(e($deaccession->description)) !!}</div>
              </div>
            @endif

            @if($deaccession->extent)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Extent</div>
                <div class="col-md-9">{!! nl2br(e($deaccession->extent)) !!}</div>
              </div>
            @endif

            @if($deaccession->reason)
              <div class="row mb-1">
                <div class="col-md-3 fw-bold">Reason</div>
                <div class="col-md-9">{!! nl2br(e($deaccession->reason)) !!}</div>
              </div>
            @endif
          </div>
        </div>
      @endforeach
    </section>
  @endif
@endsection
