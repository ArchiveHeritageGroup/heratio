{{-- Archaeological find detail --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h1 class="h4 mb-1">{{ $object->title ?: __('Untitled find') }}</h1>
      <div class="text-muted small">{{ $object->accession_number }}</div>
    </div>
    <a href="{{ route('archaeology.objects') }}" class="btn btn-outline-secondary btn-sm">&larr; {{ __('All finds') }}</a>
  </div>

  @if($object->item_count > 1)
    {{-- A bulk record stands for many physical objects; make that explicit so
         the count is never mistaken for a single artefact. --}}
    <div class="alert alert-info small">
      <i class="fas fa-layer-group me-1"></i>
      {{ __('Bulk record representing :count physical items.', ['count' => number_format($object->item_count)]) }}
    </div>
  @endif

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-header">{{ __('Identification') }}</div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-sm-5">{{ __('Object type') }}</dt><dd class="col-sm-7">{{ $object->object_type_name ?: '-' }}</dd>
            <dt class="col-sm-5">{{ __('Material') }}</dt><dd class="col-sm-7">{{ $object->material_name ?: '-' }}</dd>
            <dt class="col-sm-5">{{ __('Technique') }}</dt><dd class="col-sm-7">{{ $object->technique_name ?: '-' }}</dd>
            <dt class="col-sm-5">{{ __('Period') }}</dt><dd class="col-sm-7">{{ $object->period_name ?: '-' }}</dd>
            <dt class="col-sm-5">{{ __('Condition') }}</dt><dd class="col-sm-7">{{ $object->condition_name ?: '-' }}</dd>
          </dl>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">{{ __('Recovery') }}</div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-sm-5">{{ __('Site') }}</dt>
            <dd class="col-sm-7">
              @if($object->site_id)
                <a href="{{ route('archaeology.site', $object->site_id) }}">{{ $object->site_number }}</a>
              @else
                <span class="badge bg-warning text-dark">{{ __('Not linked to a site') }}</span>
              @endif
            </dd>
            <dt class="col-sm-5">{{ __('Method') }}</dt><dd class="col-sm-7">{{ $object->recovery_method_name ?: '-' }}</dd>
            <dt class="col-sm-5">{{ __('Context') }}</dt><dd class="col-sm-7">{{ $object->context_reference ?: '-' }}</dd>
            <dt class="col-sm-5">{{ __('Excavation ref.') }}</dt><dd class="col-sm-7">{{ $object->excavation_reference ?: '-' }}</dd>
            <dt class="col-sm-5">{{ __('Find location') }}</dt><dd class="col-sm-7">{{ $object->find_location ?: '-' }}</dd>
            <dt class="col-sm-5">{{ __('Find date') }}</dt><dd class="col-sm-7">{{ $object->find_date ?: '-' }}</dd>
            @if($object->finder)
              <dt class="col-sm-5">{{ __('Finder') }}</dt><dd class="col-sm-7">{{ $object->finder }}</dd>
            @endif
          </dl>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-header">{{ __('Dating') }}</div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-sm-5">{{ __('Range') }}</dt>
            <dd class="col-sm-7">{{ $object->date_earliest ?: '?' }} &ndash; {{ $object->date_latest ?: '?' }}</dd>
            <dt class="col-sm-5">{{ __('Method') }}</dt><dd class="col-sm-7">{{ $object->dating_method_name ?: '-' }}</dd>
          </dl>
          @if($object->dating_note)
            <hr class="my-2"><div class="small text-muted">{{ $object->dating_note }}</div>
          @endif
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">{{ __('Measurements') }}</div>
        <div class="card-body">
          @php
            $dims = collect([
              __('Length')    => $object->length_mm,
              __('Width')     => $object->width_mm,
              __('Thickness') => $object->thickness_mm,
              __('Diameter')  => $object->diameter_mm,
            ])->filter(fn ($v) => $v !== null);
          @endphp
          @if($dims->isEmpty() && ! $object->weight_g)
            <p class="text-muted small mb-0">{{ __('No measurements recorded.') }}</p>
          @else
            <dl class="row mb-0 small">
              @foreach($dims as $label => $value)
                <dt class="col-sm-5">{{ $label }}</dt><dd class="col-sm-7">{{ rtrim(rtrim(number_format($value, 2), '0'), '.') }} mm</dd>
              @endforeach
              @if($object->weight_g)
                <dt class="col-sm-5">{{ __('Weight') }}</dt>
                <dd class="col-sm-7">{{ rtrim(rtrim(number_format($object->weight_g, 3), '0'), '.') }} g</dd>
              @endif
              <dt class="col-sm-5">{{ __('Item count') }}</dt><dd class="col-sm-7">{{ number_format($object->item_count) }}</dd>
            </dl>
          @endif
          @if($object->dimensions_note)
            <hr class="my-2"><div class="small text-muted">{{ $object->dimensions_note }}</div>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header">{{ __('Custody') }}</div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-sm-5">{{ __('Storage') }}</dt><dd class="col-sm-7">{{ $object->storage_location ?: '-' }}</dd>
          </dl>
          @if($object->provenance)
            <hr class="my-2">
            <div class="small"><strong>{{ __('Provenance') }}:</strong> {{ $object->provenance }}</div>
          @endif
        </div>
      </div>
    </div>
  </div>

  @if($object->notes)
    <div class="card mt-3">
      <div class="card-header">{{ __('Notes') }}</div>
      <div class="card-body small">{{ $object->notes }}</div>
    </div>
  @endif

</div>
@endsection
