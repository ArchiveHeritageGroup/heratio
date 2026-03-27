@extends('theme::layouts.1col')

@section('title', 'Orphan Work - ' . ($resource->title ?? $resource->slug))
@section('body-class', 'rights orphan-work')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">{{ $resource->title ?? $resource->slug }}</h1>
    <span class="small">Orphan Work Due Diligence</span>
  </div>
@endsection

@section('content')
  @if($orphanWork)
  <div class="card mb-4">
    <div class="card-header bg-info text-white">
      <h5 class="mb-0"><i class="fas fa-search me-2"></i>Orphan Work Status</h5>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Status</dt>
        <dd class="col-sm-9">
          @php
            $owColor = match($orphanWork->status ?? '') {
              'in_progress' => 'warning', 'completed' => 'success', 'rights_holder_found' => 'info', 'abandoned' => 'secondary', default => 'light'
            };
          @endphp
          <span class="badge bg-{{ $owColor }}">{{ ucfirst(str_replace('_', ' ', $orphanWork->status ?? '')) }}</span>
        </dd>

        <dt class="col-sm-3">Work Type</dt>
        <dd class="col-sm-9">{{ ucfirst(str_replace('_', ' ', $orphanWork->work_type ?? '')) }}</dd>

        <dt class="col-sm-3">Search Started</dt>
        <dd class="col-sm-9">{{ $orphanWork->search_started_date ?? '-' }}</dd>

        @if($orphanWork->search_completed_date ?? null)
        <dt class="col-sm-3">Search Completed</dt>
        <dd class="col-sm-9">{{ $orphanWork->search_completed_date }}</dd>
        @endif

        @if($orphanWork->search_jurisdiction ?? null)
        <dt class="col-sm-3">Jurisdiction</dt>
        <dd class="col-sm-9">{{ $orphanWork->search_jurisdiction }}</dd>
        @endif

        @if($orphanWork->intended_use ?? null)
        <dt class="col-sm-3">Intended Use</dt>
        <dd class="col-sm-9">{{ $orphanWork->intended_use }}</dd>
        @endif

        @if($orphanWork->notes ?? null)
        <dt class="col-sm-3">Notes</dt>
        <dd class="col-sm-9">{!! nl2br(e($orphanWork->notes)) !!}</dd>
        @endif
      </dl>

      @auth
      <div class="mt-3">
        <a href="{{ route('ext-rights-admin.orphan-work-edit', $orphanWork->id) }}" class="btn btn-sm btn-outline-info">
          View Full Details in Admin
        </a>
      </div>
      @endauth
    </div>
  </div>
  @else
  <div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    No orphan work due diligence record exists for this item.
    @auth
    <a href="{{ route('ext-rights-admin.orphan-work-new', ['object_id' => $resource->id]) }}" class="btn btn-sm btn-info ms-2">
      Start Search
    </a>
    @endauth
  </div>
  @endif

  <section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
    <a href="{{ route('ext-rights.index', $resource->slug) }}" class="btn atom-btn-outline-light">Back to Rights</a>
  </section>
@endsection
