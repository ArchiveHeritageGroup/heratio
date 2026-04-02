@extends('theme::layouts.1col')
@section('title', $entity->name ?? 'Activity')
@section('body-class', 'admin ric')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-running me-2"></i>{{ $entity->name ?? 'Unnamed Activity' }}</h1>
    <div>
        <a href="{{ route('ric.activities.browse') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Browse</a>
        <a href="{{ route('ric.entities.edit', ['activities', $entity->slug]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit"></i> Edit</a>
        <form method="post" action="{{ route('ric.entities.destroy-form', ['activities', $entity->slug]) }}" class="d-inline" onsubmit="return confirm('Delete this activity?')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i> Delete</button>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
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
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-subtitle text-muted mb-2">Metadata</h6>
                <p class="mb-1"><small class="text-muted">RiC-O Type:</small> <code>rico:Activity</code></p>
                <p class="mb-1"><small class="text-muted">ID:</small> {{ $entity->id }}</p>
                <p class="mb-1"><small class="text-muted">Slug:</small> {{ $entity->slug }}</p>
                <p class="mb-1"><small class="text-muted">Created:</small> {{ $entity->created_at }}</p>
                <p class="mb-0"><small class="text-muted">Updated:</small> {{ $entity->updated_at }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
