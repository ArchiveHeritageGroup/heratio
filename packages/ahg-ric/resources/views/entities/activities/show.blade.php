@extends('theme::layouts.1col')
@section('title', $entity->name ?? 'Activity')
@section('body-class', 'admin ric')
@section('content')
<h1 class="mb-3"><i class="fas fa-running me-2"></i>{{ $entity->name ?? 'Unnamed Activity' }}</h1>

<div class="row">
    <div class="col-md-9">
        @include('ahg-ric::entities._hierarchy', ['entity' => $entity, 'hierarchy' => $hierarchy, 'entityType' => 'activity'])

        <section class="mb-3">
            <h2 class="h6 text-muted">Details</h2>
            <table class="table table-sm">
                <tr><th style="width:180px">Type</th><td><span class="badge bg-info">{{ $entity->type_id ?? 'Not set' }}</span></td></tr>
                <tr><th>Start Date</th><td>{{ $entity->start_date ?? '' }}</td></tr>
                <tr><th>End Date</th><td>{{ $entity->end_date ?? '' }}</td></tr>
                <tr><th>Date Display</th><td>{{ $entity->date_display ?? '' }}</td></tr>
                @if($entity->place_name)<tr><th>Place</th><td>{{ $entity->place_name }}</td></tr>@endif
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
            'ricOType' => 'Activity',
            'browseRoute' => route('ric.activities.browse'),
            'typeSlug' => 'activities',
        ])
    </div>
</div>
@endsection
