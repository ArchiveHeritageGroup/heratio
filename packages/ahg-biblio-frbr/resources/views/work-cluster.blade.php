@extends('theme::layouts.1col')

@section('title', __('All editions of :title', ['title' => $representative->title ?? __('this work')]))

@section('content')
<div class="container py-4" style="max-width: 1000px;">
    <h1 class="h3">{{ __('All editions') }}</h1>
    <h2 class="h5 text-muted">{{ $representative->title ?? '' }}</h2>
    <p class="small text-muted">
        <span class="badge bg-light text-dark border">work-key {{ $workKey }}</span>
        - {{ __(':count manifestations', ['count' => $editionCount]) }}
    </p>

    <table class="table table-sm table-hover mt-3">
        <thead>
            <tr>
                <th>{{ __('Edition') }}</th>
                <th>{{ __('Published') }}</th>
                <th>{{ __('Publisher') }}</th>
                <th>{{ __('Language') }}</th>
                <th>{{ __('Identifiers') }}</th>
                <th>{{ __('Format') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @foreach ($items as $i)
            <tr>
                <td>
                    {{ $i->edition ?: $i->edition_statement ?: '-' }}
                </td>
                <td>{{ $i->publication_date ?: '-' }}</td>
                <td>
                    {{ $i->publisher ?: '-' }}
                    @if ($i->publication_place)
                        <br><small class="text-muted">{{ $i->publication_place }}</small>
                    @endif
                </td>
                <td>{{ $i->language ?: '-' }}</td>
                <td>
                    @if ($i->isbn)<small class="d-block">ISBN: {{ $i->isbn }}</small>@endif
                    @if ($i->issn)<small class="d-block">ISSN: {{ $i->issn }}</small>@endif
                </td>
                <td><span class="badge bg-light text-dark border">{{ $i->material_type ?: '-' }}</span></td>
                <td>
                    @if ($i->slug)
                        <a href="{{ url('/' . $i->slug) }}" class="btn btn-sm btn-outline-primary">{{ __('Open') }}</a>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
