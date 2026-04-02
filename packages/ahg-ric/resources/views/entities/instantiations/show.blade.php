@extends('theme::layouts.1col')
@section('title', $entity->title ?? 'Instantiation')
@section('body-class', 'admin ric')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-file-alt me-2"></i>{{ $entity->title ?? 'Unnamed Instantiation' }}</h1>
    <div>
        <a href="{{ route('ric.instantiations.browse') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Browse</a>
        <a href="{{ route('ric.entities.edit', ['instantiations', $entity->slug]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit"></i> Edit</a>
        <form method="post" action="{{ route('ric.entities.destroy-form', ['instantiations', $entity->slug]) }}" class="d-inline" onsubmit="return confirm('Delete this instantiation?')">
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
                <tr><th style="width:180px">Carrier Type</th><td><span class="badge bg-secondary">{{ $entity->carrier_type ?? 'Not set' }}</span></td></tr>
                <tr><th>MIME Type</th><td><code>{{ $entity->mime_type ?? '' }}</code></td></tr>
                @if($entity->extent_value)<tr><th>Extent</th><td>{{ $entity->extent_value }} {{ $entity->extent_unit ?? '' }}</td></tr>@endif
                @if($entity->description)<tr><th>Description</th><td>{!! nl2br(e($entity->description)) !!}</td></tr>@endif
                @if($entity->technical_characteristics)<tr><th>Technical</th><td>{!! nl2br(e($entity->technical_characteristics)) !!}</td></tr>@endif
                @if($entity->production_technical_characteristics)<tr><th>Production Technical</th><td>{!! nl2br(e($entity->production_technical_characteristics)) !!}</td></tr>@endif
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
                <p class="mb-1"><small class="text-muted">RiC-O Type:</small> <code>rico:Instantiation</code></p>
                <p class="mb-1"><small class="text-muted">ID:</small> {{ $entity->id }}</p>
                <p class="mb-1"><small class="text-muted">Slug:</small> {{ $entity->slug }}</p>
                @if($entity->digital_object_id)<p class="mb-1"><small class="text-muted">Digital Object:</small> #{{ $entity->digital_object_id }}</p>@endif
                @if($entity->record_id)<p class="mb-1"><small class="text-muted">Record:</small> #{{ $entity->record_id }}</p>@endif
                <p class="mb-1"><small class="text-muted">Created:</small> {{ $entity->created_at }}</p>
                <p class="mb-0"><small class="text-muted">Updated:</small> {{ $entity->updated_at }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
