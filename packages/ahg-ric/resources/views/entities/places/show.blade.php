@extends('theme::layouts.1col')
@section('title', $entity->name ?? 'Place')
@section('body-class', 'admin ric')
@section('content')
<h1 class="mb-3"><i class="fas fa-map-marker-alt me-2"></i>{{ $entity->name ?? 'Unnamed Place' }}</h1>

<div class="row">
    <div class="col-md-9">
        @include('ahg-ric::entities._hierarchy', ['entity' => $entity, 'hierarchy' => $hierarchy, 'entityType' => 'place'])

        <section class="mb-3">
            <h2 class="h6 text-muted">Details</h2>
            <table class="table table-sm">
                <tr><th style="width:180px">Type</th><td><span class="badge bg-success">{{ $entity->type_id ?? 'Not set' }}</span></td></tr>
                @if($entity->latitude && $entity->longitude)
                <tr><th>Coordinates</th><td>{{ $entity->latitude }}, {{ $entity->longitude }}</td></tr>
                @endif
                @if($entity->address)<tr><th>Address</th><td>{!! nl2br(e($entity->address)) !!}</td></tr>@endif
                @if($entity->description)<tr><th>Description</th><td>{!! nl2br(e($entity->description)) !!}</td></tr>@endif
            </table>
        </section>

        <section>
            <h2 class="h6 text-muted">Relations</h2>
            @include('ahg-ric::_relation-editor', ['recordId' => $entity->id])
        </section>
    </div>
    <div class="col-md-3">
        @include('ahg-ric::entities._sidebar', [
            'entity' => $entity,
            'ricOType' => 'Place',
            'browseRoute' => route('ric.places.browse'),
            'typeSlug' => 'places',
        ])
    </div>
</div>
@endsection
