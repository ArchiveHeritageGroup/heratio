@extends('theme::layouts.1col')
@section('title', ($entityData->display_label ?? $entityData->canonical_value ?? 'Entity'))
@section('body-class', 'heritage')

@php
$typeColors = ['person'=>'#4e79a7','organization'=>'#59a14f','place'=>'#e15759','date'=>'#b07aa1','event'=>'#76b7b2','work'=>'#ff9da7','concept'=>'#edc949'];
$objectsArray = (array)($objects ?? []);
$relatedEntitiesArray = (array)($relatedEntities ?? []);
$entityData = $entityData ?? (object)[];
@endphp

@section('content')
<div class="heritage-entity-page py-4">
  <div class="container-xxl">
    <nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('heritage.landing') }}">Heritage</a></li><li class="breadcrumb-item"><a href="{{ route('heritage.graph') }}">Knowledge Graph</a></li><li class="breadcrumb-item active">{{ $entityData->canonical_value ?? 'Entity' }}</li></ol></nav>

    <div class="row">
      <div class="col-lg-8">
        <div class="card shadow-sm mb-4"><div class="card-body">
          <div class="d-flex align-items-start">
            <div class="rounded-3 p-3 me-4 d-flex align-items-center justify-content-center" style="background-color:{{ $typeColors[$entityData->entity_type ?? 'concept'] ?? '#999' }};min-width:80px;min-height:80px"><i class="fas fa-tag text-white fs-1"></i></div>
            <div class="flex-grow-1">
              <h1 class="h2 mb-2">{{ $entityData->display_label ?? $entityData->canonical_value ?? 'Entity' }}</h1>
              <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge fs-6" style="background-color:{{ $typeColors[$entityData->entity_type ?? ''] ?? '#999' }}">{{ ucfirst($entityData->entity_type ?? 'Unknown') }}</span>
                <span class="badge bg-light text-dark fs-6"><i class="fas fa-file me-1"></i>{{ number_format($entityData->occurrence_count ?? 0) }} records</span>
                @if(($entityData->confidence_avg ?? 0) >= 0.9)<span class="badge bg-success fs-6"><i class="fas fa-check-circle me-1"></i>High Confidence</span>
                @elseif(($entityData->confidence_avg ?? 0) >= 0.7)<span class="badge bg-warning text-dark fs-6">Medium Confidence</span>@endif
              </div>
              @if(!empty($entityData->description))<p class="lead mb-0">{{ $entityData->description }}</p>@endif
            </div>
          </div>
        </div></div>

        <div class="card shadow-sm mb-4">
          <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff"><h4 class="mb-0">Records containing this entity</h4><a href="{{ route('heritage.search', ['ner_'.($entityData->entity_type ?? 'person') => $entityData->canonical_value ?? '']) }}" class="btn btn-sm atom-btn-white">View All <i class="fas fa-arrow-right ms-1"></i></a></div>
          <div class="card-body p-0">
            @if(!empty($objectsArray))
            <div class="list-group list-group-flush">
              @foreach(array_slice($objectsArray,0,10) as $obj)
              <a href="{{ route('informationobject.show', $obj->slug ?? '#') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div><h6 class="mb-1">{{ $obj->title ?? 'Untitled' }}</h6>@if(($obj->mention_count ?? 0) > 1)<small class="text-muted">Mentioned {{ $obj->mention_count }} times</small>@endif</div>
                <span class="badge bg-primary rounded-pill">{{ round(($obj->confidence ?? 0) * 100) }}%</span>
              </a>
              @endforeach
            </div>
            @else<div class="text-center py-5 text-muted"><p class="mb-0">No records found.</p></div>@endif
          </div>
        </div>

        @if(!empty($relatedEntitiesArray))
        <div class="card shadow-sm">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h4 class="mb-0">Related Entities</h4></div>
          <div class="card-body"><div class="row g-3">
            @foreach($relatedEntitiesArray as $related)
            <div class="col-md-6"><div class="card h-100 border"><div class="card-body py-2"><div class="d-flex align-items-center"><span class="badge me-2" style="background-color:{{ $typeColors[$related['entity_type'] ?? ''] ?? '#999' }}">{{ ucfirst(substr($related['entity_type'] ?? '',0,1)) }}</span><a href="{{ route('heritage.entity', ['type'=>$related['entity_type'],'value'=>$related['value']]) }}" class="text-decoration-none fw-medium">{{ $related['label'] }}</a><span class="badge bg-light text-muted ms-auto">{{ $related['co_occurrences'] }}</span></div></div></div></div>
            @endforeach
          </div></div>
        </div>
        @endif
      </div>

      <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">Actions</h5></div>
          <div class="card-body d-grid gap-2">
            <a href="{{ route('heritage.search', ['ner_'.($entityData->entity_type ?? '') => $entityData->canonical_value ?? '']) }}" class="btn atom-btn-secondary"><i class="fas fa-search me-1"></i>Search All Records</a>
            <a href="{{ route('heritage.graph', ['focus'=>$entityData->id ?? '']) }}" class="btn atom-btn-white"><i class="fas fa-project-diagram me-1"></i>View in Graph</a>
          </div>
        </div>
        <div class="card shadow-sm mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">Details</h5></div>
          <div class="card-body"><dl class="row mb-0">
            <dt class="col-5 text-muted">Type</dt><dd class="col-7">{{ ucfirst($entityData->entity_type ?? '') }}</dd>
            <dt class="col-5 text-muted">Occurrences</dt><dd class="col-7">{{ number_format($entityData->occurrence_count ?? 0) }}</dd>
            <dt class="col-5 text-muted">Avg. Confidence</dt><dd class="col-7">{{ round(($entityData->confidence_avg ?? 0) * 100) }}%</dd>
          </dl></div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
