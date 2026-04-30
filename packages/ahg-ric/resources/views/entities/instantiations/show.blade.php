@extends('theme::layouts.1col')
@section('title', $entity->title ?? 'Instantiation')
@section('body-class', 'admin ric')
@section('content')
<h1 class="mb-3"><i class="fas fa-file-alt me-2"></i>{{ $entity->title ?? 'Unnamed Instantiation' }}</h1>

<div class="row">
    <div class="col-md-9">
        @include('ahg-ric::entities._hierarchy', ['entity' => $entity, 'hierarchy' => $hierarchy, 'entityType' => 'instantiation'])

        <section class="mb-3">
            <h2 class="h6 text-muted">{{ __('Details') }}</h2>
            <table class="table table-sm">
                <tr><th style="width:180px">{{ __('Carrier Type') }}</th><td><span class="badge bg-secondary">{{ $entity->carrier_type ?? 'Not set' }}</span></td></tr>
                <tr><th>{{ __('MIME Type') }}</th><td><code>{{ $entity->mime_type ?? '' }}</code></td></tr>
                @if($entity->extent_value)<tr><th>{{ __('Extent') }}</th><td>{{ $entity->extent_value }} {{ $entity->extent_unit ?? '' }}</td></tr>@endif
                @if($entity->description)<tr><th>{{ __('Description') }}</th><td>{!! nl2br(e($entity->description)) !!}</td></tr>@endif
                @if($entity->technical_characteristics)<tr><th>{{ __('Technical') }}</th><td>{!! nl2br(e($entity->technical_characteristics)) !!}</td></tr>@endif
                @if($entity->production_technical_characteristics)<tr><th>{{ __('Production Technical') }}</th><td>{!! nl2br(e($entity->production_technical_characteristics)) !!}</td></tr>@endif
            </table>
        </section>

        <section>
            <h2 class="h6 text-muted">{{ __('Relations') }}</h2>
            @include('ahg-ric::_relation-editor', ['recordId' => $entity->id])
        </section>
    </div>
    <div class="col-md-3">
        @include('ahg-ric::entities._sidebar', [
            'entity' => $entity,
            'ricOType' => 'Instantiation',
            'browseRoute' => route('ric.instantiations.browse'),
            'typeSlug' => 'instantiations',
        ])
    </div>
</div>
@endsection
