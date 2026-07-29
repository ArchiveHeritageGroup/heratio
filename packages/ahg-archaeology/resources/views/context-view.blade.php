{{-- Context sheet (one stratigraphic unit) - #1428 Phase 1 --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h1 class="h4 mb-1">{{ __('Context') }} {{ $context->context_number }}
        @if($context->type_name)<span class="badge bg-secondary">{{ $context->type_name }}</span>@endif
      </h1>
      <div class="text-muted small">
        {{ $context->site->title ?? __('Site') }} &middot; {{ $context->site->site_number ?? '' }}
      </div>
    </div>
    <div class="d-flex gap-2">
      @if($context->site)
        <a href="{{ route('archaeology.contexts', $context->site->id) }}" class="btn btn-outline-secondary btn-sm">&larr; {{ __('Stratigraphy') }}</a>
      @endif
      <a href="{{ route('archaeology.context.edit', $context->id) }}" class="btn btn-primary btn-sm">{{ __('Edit') }}</a>
    </div>
  </div>

  @if(session('status'))
    <div class="alert alert-success py-2">{{ session('status') }}</div>
  @endif

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card mb-3">
        <div class="card-header">{{ __('Context sheet') }}</div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-sm-4">{{ __('Type') }}</dt><dd class="col-sm-8">{{ $context->type_name ?: '-' }}</dd>
            <dt class="col-sm-4">{{ __('Phase') }}</dt><dd class="col-sm-8">{{ $context->phase_name ?: '-' }}</dd>
            <dt class="col-sm-4">{{ __('Top elevation') }}</dt>
            <dd class="col-sm-8">{{ $context->top_elevation_m !== null ? number_format($context->top_elevation_m, 3).' m' : '-' }}</dd>
            <dt class="col-sm-4">{{ __('Bottom elevation') }}</dt>
            <dd class="col-sm-8">{{ $context->bottom_elevation_m !== null ? number_format($context->bottom_elevation_m, 3).' m' : '-' }}</dd>
            <dt class="col-sm-4">{{ __('Excavation ref') }}</dt><dd class="col-sm-8">{{ $context->excavation_reference ?: '-' }}</dd>
            <dt class="col-sm-4">{{ __('Excavator') }}</dt><dd class="col-sm-8">{{ $context->excavator ?: '-' }}</dd>
            <dt class="col-sm-4">{{ __('Excavated') }}</dt><dd class="col-sm-8">{{ $context->excavation_date ?: '-' }}</dd>
            <dt class="col-sm-4">{{ __('Date range') }}</dt>
            <dd class="col-sm-8">{{ $context->date_earliest ?: '?' }} - {{ $context->date_latest ?: '?' }}
              @if($context->dating_note)<div class="text-muted">{{ $context->dating_note }}</div>@endif
            </dd>
          </dl>
          @if($context->description)
            <hr><h6>{{ __('Description') }}</h6><p class="small mb-2">{{ $context->description }}</p>
          @endif
          @if($context->interpretation)
            <h6>{{ __('Interpretation') }}</h6><p class="small mb-0">{{ $context->interpretation }}</p>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card mb-3">
        <div class="card-header">{{ __('Drawings') }}</div>
        <div class="card-body small">
          @if($context->description_slug)
            <p class="mb-2">{{ __('Plan and section drawings attach to this context\'s descriptive record.') }}</p>
            <a href="{{ url('/'.$context->description_slug) }}" class="btn btn-outline-primary btn-sm">{{ __('Open record') }}</a>
            <a href="{{ url('/informationobject/edit/'.$context->description_slug) }}" class="btn btn-outline-secondary btn-sm">{{ __('Upload drawings') }}</a>
          @else
            <p class="text-muted mb-0">{{ __('No descriptive record linked yet.') }}</p>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex justify-content-between">
          <span>{{ __('Finds in this context') }}</span>
          <span class="text-muted">{{ $context->finds->count() }}</span>
        </div>
        <ul class="list-group list-group-flush small">
          @forelse($context->finds as $f)
            <li class="list-group-item d-flex justify-content-between">
              <a href="{{ route('archaeology.object', $f->id) }}">{{ $f->title ?: __('Untitled') }}</a>
              <span class="text-muted">{{ $f->accession_number }}</span>
            </li>
          @empty
            <li class="list-group-item text-muted">{{ __('No finds catalogued to this context.') }}</li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>

</div>
@endsection
