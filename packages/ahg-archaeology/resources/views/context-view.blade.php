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
  @if(session('error'))
    <div class="alert alert-danger py-2">{{ session('error') }}</div>
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

      <div class="card mb-3">
        <div class="card-header">{{ __('Stratigraphic relationships') }}</div>
        <div class="card-body">
          @if($relationships->isEmpty())
            <p class="text-muted small mb-3">{{ __('No relationships recorded.') }}</p>
          @else
            <ul class="list-unstyled small mb-3">
              @foreach($relationships as $r)
                <li class="d-flex justify-content-between align-items-center border-bottom py-1">
                  <span>
                    {{ __('This context') }}
                    <strong>{{ $relTypes[$r->relationship_type]['label'] ?? $r->relationship_type }}</strong>
                    <a href="{{ route('archaeology.context', $r->related_id) }}">{{ __('Context') }} {{ $r->related_number }}</a>
                    @if($r->note)<span class="text-muted">- {{ $r->note }}</span>@endif
                  </span>
                  <form method="post" action="{{ route('archaeology.context.relationship.delete', [$context->id, $r->id]) }}" class="ms-2"
                        onsubmit="return confirm('{{ __('Remove this relationship?') }}')">
                    @csrf
                    <button class="btn btn-outline-danger btn-sm py-0" type="submit">&times;</button>
                  </form>
                </li>
              @endforeach
            </ul>
          @endif

          @if($otherContexts->isNotEmpty())
            <form method="post" action="{{ route('archaeology.context.relationship.store', $context->id) }}" class="row g-2 align-items-end">
              @csrf
              <div class="col-md-4">
                <label class="form-label small mb-0">{{ __('This context') }}</label>
                <select name="relationship_type" class="form-select form-select-sm">
                  @foreach($relTypes as $code => $meta)
                    <option value="{{ $code }}">{{ $meta['label'] }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-0">{{ __('Context') }}</label>
                <select name="related_context_id" class="form-select form-select-sm">
                  @foreach($otherContexts as $oc)
                    <option value="{{ $oc->id }}">{{ $oc->context_number }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-0">{{ __('Note') }}</label>
                <input type="text" name="note" class="form-control form-control-sm">
              </div>
              <div class="col-md-1">
                <button class="btn btn-primary btn-sm w-100" type="submit" title="{{ __('Add') }}">+</button>
              </div>
            </form>
          @else
            <p class="text-muted small mb-0">{{ __('Add another context to this site to record a relationship.') }}</p>
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
